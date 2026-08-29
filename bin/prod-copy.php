<?php

declare(strict_types=1);

/**
 * Fabrique une copie locale et anonymisée de la base de production, réduite aux
 * N derniers événements ajoutés et à tout ce qu'ils référencent — lieux, salles,
 * organisateurs, personnes, descriptions, galeries.
 *
 * La production n'est lue qu'en lecture seule, par MySQL distant. L'anonymisation
 * a lieu entre le SELECT et l'INSERT : aucun fichier intermédiaire ne porte jamais
 * de donnée personnelle réelle, contrairement à la voie « mysqldump complet puis
 * anonymisation locale », qui aurait en plus imposé le transfert d'1 Go.
 *
 * Les identifiants d'origine sont conservés : les noms de fichiers des flyers et
 * des logos les encodent (« {idE}_{date}.jpg »), et toutes les tables de jonction
 * en dépendent.
 *
 * Usage :
 *   php bin/prod-copy.php [--limit=1000] [--source=prod] [--dest=prod_copy]
 *                         [--only=db|files] [--password=dev] [--force]
 *                         [--base-url=URL] [--uploads-dir=CHEMIN] [--reset-uploads]
 *
 * Les connexions « prod » et « prod_copy » se déclarent dans app/db.config.php,
 * que .gitignore exclut du dépôt. Voir « Base de données » dans le README.
 */

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\PasswordPolicy;

define('__ROOT__', dirname(__DIR__)); // DbConnectorPdo::getInstance() en a besoin

require __ROOT__ . '/vendor/autoload.php';

/**
 * Les 13 tables reprises. localite ne porte aucune donnée d'événement mais
 * evenement.localite_id et lieu.localite_id y renvoient : sans elle, aucune
 * adresse ne s'affiche.
 */
const TABLES_COPIEES = [
    'localite',
    'personne',
    'affiliation',
    'lieu',
    'descriptionlieu',
    'salle',
    'organisateur',
    'lieu_organisateur',
    'personne_organisateur',
    'evenement',
    'evenement_organisateur',
    'fichierrecu',
    'lieu_fichierrecu',
];

/**
 * Colonnes portant un nom de fichier, et sous-répertoire de web/uploads/ où le
 * fichier est écrit. Chacune a sa miniature préfixée « s_ », produite à l'upload
 * par ImageDriver2 et attendue par toutes les pages de liste.
 */
const COLONNES_FICHIERS = [
    'evenement'    => ['repertoire' => 'evenements',    'colonnes' => ['flyer', 'image']],
    'lieu'         => ['repertoire' => 'lieux',         'colonnes' => ['logo', 'photo1', 'photo2']],
    'organisateur' => ['repertoire' => 'organisateurs', 'colonnes' => ['logo', 'photo']],
];

/**
 * Le pare-feu 8G renvoie 403 sur tout User-Agent contenant « curl », « scan »,
 * « archiver »… (htaccess/20-firewall-8g.conf). Un UA de navigateur est la
 * condition pour que la moindre image descende.
 */
const UA_NAVIGATEUR = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

/**
 * Transferts simultanés. Assez pour que les milliers de fichiers descendent en
 * quelques minutes, assez peu pour ne pas ressembler à un aspirateur de site.
 */
const TRANSFERTS_SIMULTANES = 5;

/**
 * sql_mode de l'application (cf. DbConnectorPdo) : sans lui, un MySQL 8 par
 * défaut refuse les « 0000-00-00 00:00:00 » dont la base est truffée, aussi bien
 * dans les DEFAULT des CREATE TABLE que dans les lignes copiées.
 */
const SQL_MODE = 'IGNORE_SPACE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

/**
 * Arborescence de web/uploads/, telle que git la suit par ses .gitkeep. Sert à la
 * recréer après --reset-uploads : ces répertoires disparus, git status les
 * signalerait comme des suppressions.
 */
const SQUELETTE_UPLOADS = ['', 'evenements', 'fichiers/lieux', 'lieux', 'lieux/galeries', 'organisateurs'];

/**
 * Interrompt le script sur une erreur d'usage ou d'environnement.
 */
function abandonner(string $message): never
{
    fwrite(STDERR, 'ERREUR : ' . $message . "\n");
    exit(1);
}

/**
 * Valeurs entières distinctes d'une colonne, le 0 écarté.
 *
 * evenement.idLieu, idSalle et idPersonne valent 0 quand le lieu a été saisi à la
 * main ou l'événement proposé par un visiteur non connecté ; un IN (0, …) irait
 * chercher n'importe quoi.
 *
 * @param list<array<string, mixed>> $lignes
 * @return list<int>
 */
function idsDistincts(array $lignes, string $colonne): array
{
    $ids = [];

    foreach ($lignes as $ligne) {
        $id = (int) $ligne[$colonne];
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_map('intval', array_keys($ids));
}

/**
 * Fusionne des listes d'identifiants sans doublon.
 *
 * @param list<int> ...$listes
 * @return list<int>
 */
function fusionnerIds(array ...$listes): array
{
    return array_values(array_unique(array_merge(...$listes)));
}

/**
 * Lignes complètes d'une table, filtrées sur une colonne d'identifiants.
 *
 * Les listes sont découpées : une clause IN de plusieurs milliers de marqueurs
 * approche la limite du protocole préparé.
 *
 * @param list<int> $ids
 * @return list<array<string, mixed>>
 */
function lire(DbConnectorPdo $db, string $table, string $colonne, array $ids, int $paquet = 1000): array
{
    if ($ids === []) {
        return [];
    }

    $lignes = [];

    foreach (array_chunk($ids, $paquet) as $tranche) {
        [$clause, $params] = $db->buildInClause('`' . $colonne . '`', $tranche);

        $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE {$clause}");
        if ($stmt === false) {
            abandonner("préparation impossible du SELECT sur {$table}");
        }

        $stmt->execute($params);

        foreach ($stmt->fetchAll() as $ligne) {
            $lignes[] = $ligne;
        }
    }

    return $lignes;
}

/**
 * INSERT par lots, colonnes déduites de la première ligne.
 *
 * Marqueurs positionnels plutôt que nommés : une même colonne revient à chaque
 * ligne du lot, et un marqueur nommé réutilisé fait échouer PDO en HY093 tant que
 * EMULATE_PREPARES vaut false — ce qui est le cas ici.
 *
 * @param list<array<string, mixed>> $lignes
 */
function ecrire(PDO $pdo, string $table, array $lignes, int $lot = 200): int
{
    if ($lignes === []) {
        return 0;
    }

    $colonnes = array_keys($lignes[0]);
    $liste = '`' . implode('`, `', $colonnes) . '`';
    $ligneMarqueurs = '(' . implode(', ', array_fill(0, count($colonnes), '?')) . ')';
    $ecrites = 0;

    foreach (array_chunk($lignes, $lot) as $tranche) {
        $valeurs = [];
        foreach ($tranche as $ligne) {
            foreach ($colonnes as $colonne) {
                $valeurs[] = $ligne[$colonne];
            }
        }

        $marqueurs = implode(', ', array_fill(0, count($tranche), $ligneMarqueurs));

        $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$liste}) VALUES {$marqueurs}");
        if ($stmt === false) {
            abandonner("préparation impossible de l'INSERT dans {$table}");
        }

        $stmt->execute($valeurs);
        $ecrites += count($tranche);
    }

    return $ecrites;
}

/**
 * Remplace les seules colonnes déjà présentes dans la ligne.
 *
 * Le schéma est celui de la production, qui peut avoir dérivé : ajouter une clé
 * absente de la table ferait échouer l'INSERT sur un décalage de colonnes.
 *
 * @param array<string, mixed> $ligne
 * @param array<string, mixed> $remplacements
 * @return array<string, mixed>
 */
function remplacer(array $ligne, array $remplacements): array
{
    foreach ($remplacements as $colonne => $valeur) {
        if (array_key_exists($colonne, $ligne)) {
            $ligne[$colonne] = $valeur;
        }
    }

    return $ligne;
}

/**
 * Connexion à la destination. Sans $avecBase, le DSN n'indique aucune base : c'est
 * la seule façon d'émettre le CREATE DATABASE, DbConnectorPdo construisant
 * toujours son DSN avec dbname.
 *
 * @param array{host: string, dbname: string, user: string, password: string} $config
 */
function connecterDestination(array $config, bool $avecBase): PDO
{
    $dsn = 'mysql:host=' . $config['host']
        . ($avecBase ? ';dbname=' . $config['dbname'] : '')
        . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("SET SESSION sql_mode = '" . SQL_MODE . "'");

    return $pdo;
}

/**
 * Met de côté le contenu actuel des uploads et recrée l'arborescence vide.
 *
 * Le répertoire est déplacé, jamais vidé : les fichiers d'une instance précédente
 * appartiennent à une autre base, mais ce sont les fichiers de quelqu'un. Le
 * chemin de la sauvegarde est retourné pour être annoncé.
 *
 * Les .gitkeep sont repris de la sauvegarde plutôt que recréés vides : ils sont
 * suivis par git, et un fichier vide là où git attend un saut de ligne
 * apparaîtrait comme une modification.
 */
function mettreDeCoteLesUploads(string $repUploads): string
{
    $sauvegarde = $repUploads . '-backup-' . date('Ymd-His');

    if (!rename($repUploads, $sauvegarde)) {
        abandonner("déplacement de {$repUploads} vers {$sauvegarde} impossible.");
    }

    foreach (SQUELETTE_UPLOADS as $sousRep) {
        $chemin = rtrim($repUploads . '/' . $sousRep, '/');

        if (!is_dir($chemin) && !mkdir($chemin, 0775, true)) {
            abandonner("création de {$chemin} impossible.");
        }

        $temoinOrigine = rtrim($sauvegarde . '/' . $sousRep, '/') . '/.gitkeep';
        if (is_file($temoinOrigine)) {
            copy($temoinOrigine, $chemin . '/.gitkeep');
        }
    }

    return $sauvegarde;
}

/**
 * Fichiers présents sous les uploads qu'aucune ligne de la copie ne référence.
 *
 * Presque toujours ceux d'une instance de développement antérieure : ils ne
 * gênent pas, mais mêlent deux jeux de données sans que rien ne le dise.
 *
 * @param array<string, mixed> $attendus chemins attendus, en clés
 */
function compterOrphelins(string $repUploads, array $attendus): int
{
    if (!is_dir($repUploads)) {
        return 0;
    }

    $orphelins = 0;
    $parcours = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repUploads, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($parcours as $fichier) {
        if (!$fichier->isFile() || $fichier->getFilename() === '.gitkeep') {
            continue;
        }

        $chemin = str_replace(DIRECTORY_SEPARATOR, '/', $fichier->getPathname());
        if (!isset($attendus[$chemin])) {
            $orphelins++;
        }
    }

    return $orphelins;
}

/**
 * Télécharge en parallèle ce qui n'est pas déjà sur le disque.
 *
 * @param list<array{url: string, chemin: string}> $taches
 * @return array<string, int>
 */
function telecharger(array $taches, int $simultanes): array
{
    $bilan = [
        'ecrits' => 0, 'presents' => 0, 'absents' => 0, 'refuses' => 0,
        'brides' => 0, 'non_tentes' => 0, 'echecs' => 0, 'octets' => 0,
    ];
    $restants = [];

    foreach ($taches as $tache) {
        if (is_file($tache['chemin'])) {
            $bilan['presents']++;
            continue;
        }
        $restants[] = $tache;
    }

    $total = count($restants);
    if ($total === 0) {
        return $bilan;
    }

    $mh = curl_multi_init();
    $enCours = [];
    $traites = 0;

    // Un 429 est la production qui demande d'arrêter. Continuer à lui envoyer des
    // milliers de requêtes vouées au même sort n'obtiendrait rien et allongerait la
    // sanction : on cesse d'en lancer de nouvelles et on laisse finir les en cours.
    $bride = false;

    // Codes inattendus, annoncés une fois chacun plutôt que fondus dans « échecs »,
    // où un run bridé ressemblait à une panne de réseau.
    $codesVus = [];

    while ($enCours !== [] || ($restants !== [] && !$bride)) {
        while ($restants !== [] && !$bride && count($enCours) < $simultanes) {
            $tache = array_shift($restants);

            $ch = curl_init($tache['url']);
            if ($ch === false) {
                $bilan['echecs']++;
                continue;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => UA_NAVIGATEUR,
            ]);

            curl_multi_add_handle($mh, $ch);
            $enCours[spl_object_id($ch)] = ['handle' => $ch, 'chemin' => $tache['chemin']];
        }

        curl_multi_exec($mh, $actifs);
        if ($actifs > 0) {
            curl_multi_select($mh, 1.0);
        }

        while (($info = curl_multi_info_read($mh)) !== false) {
            $ch = $info['handle'];
            $cle = spl_object_id($ch);
            $chemin = $enCours[$cle]['chemin'];
            unset($enCours[$cle]);

            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $corps = (string) curl_multi_getcontent($ch);

            if ($info['result'] !== CURLE_OK) {
                $bilan['echecs']++;
            } elseif ($code === 404) {
                $bilan['absents']++;
            } elseif ($code === 403) {
                $bilan['refuses']++;
            } elseif ($code === 429) {
                $bilan['brides']++;
                $bride = true;
            } elseif ($code === 200 && $corps !== '') {
                $repertoire = dirname($chemin);
                if (!is_dir($repertoire)) {
                    mkdir($repertoire, 0775, true);
                }
                file_put_contents($chemin, $corps);
                $bilan['ecrits']++;
                $bilan['octets'] += strlen($corps);
            } else {
                $bilan['echecs']++;
                if (!isset($codesVus[$code])) {
                    $codesVus[$code] = true;
                    printf("\n  réponse HTTP %d inattendue sur %s\n", $code, basename($chemin));
                }
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $traites++;
            if ($traites % 50 === 0 || $traites === $total) {
                printf("\r  %d / %d", $traites, $total);
            }
        }
    }

    curl_multi_close($mh);
    echo "\n";

    $bilan['non_tentes'] = count($restants);

    return $bilan;
}


// ---------------------------------------------------------------------------
// Options et configuration
// ---------------------------------------------------------------------------

$options = getopt('', ['limit::', 'source::', 'dest::', 'only::', 'password::', 'base-url::', 'uploads-dir::', 'force', 'reset-uploads']);

$limite = (int) ($options['limit'] ?? 1000);
$nomSource = (string) ($options['source'] ?? 'prod');
$nomDest = (string) ($options['dest'] ?? 'prod_copy');
$phase = (string) ($options['only'] ?? 'tout');
$motDePasse = (string) ($options['password'] ?? 'decadanse1');
$baseUrl = rtrim((string) ($options['base-url'] ?? 'https://www.ladecadanse.ch'), '/');
$repUploads = rtrim((string) ($options['uploads-dir'] ?? __ROOT__ . '/web/uploads'), "/\\");
$force = isset($options['force']);
$reinitialiserUploads = isset($options['reset-uploads']);

if ($limite < 1) {
    abandonner('--limit attend un nombre d\'événements supérieur à 0.');
}

if (!in_array($phase, ['tout', 'db', 'files'], true)) {
    abandonner('--only accepte db ou files.');
}

// user/login.php refuse un mot de passe hors de ces bornes avant même de regarder
// la base : un mot de passe plus court rendrait tous les comptes de la copie
// inutilisables, sans que rien dans la sortie du script ne le laisse deviner.
if (mb_strlen($motDePasse) < 4 || mb_strlen($motDePasse) > PasswordPolicy::LONGUEUR_MAX) {
    abandonner(sprintf(
        "--password doit faire entre 4 et %d caractères, sans quoi la page de connexion\n"
        . "         le refuse (user/login.php).",
        PasswordPolicy::LONGUEUR_MAX
    ));
}

$cheminConfig = __ROOT__ . '/app/db.config.php';
if (!is_file($cheminConfig)) {
    abandonner("app/db.config.php introuvable. Le copier depuis app/db.config_model.php.");
}

/** @var array<string, array{host: string, dbname: string, user: string, password: string}> $configs */
$configs = require $cheminConfig;

// La source ne sert qu'à la phase base : --only=files doit pouvoir tourner sur
// une machine où aucun accès à la production n'est configuré.
$connexionsRequises = $phase === 'files' ? [$nomDest] : [$nomSource, $nomDest];

foreach ($connexionsRequises as $nom) {
    if (!isset($configs[$nom])) {
        abandonner(
            "connexion « {$nom} » absente de app/db.config.php.\n"
            . "         Les entrées « prod » et « prod_copy » sont décrites dans app/db.config_model.php."
        );
    }
}

$configDest = $configs[$nomDest];
$baseDest = $configDest['dbname'];

// Le nom passe dans des identifiants entre accents graves, qu'aucun marqueur PDO
// ne sait porter : il ne peut valoir que ce qu'un nom de base MySQL accepte.
if (preg_match('/^[A-Za-z0-9_]+$/', $baseDest) !== 1) {
    abandonner("nom de base de destination invalide : « {$baseDest} ».");
}


// ---------------------------------------------------------------------------
// Phase base de données
// ---------------------------------------------------------------------------

if ($phase !== 'files') {
    // La destination est éprouvée avant que la source ne soit lue : découvrir
    // qu'il manque --force après une minute de requêtes sur la production serait
    // une minute perdue pour rien.
    $pdoRacine = connecterDestination($configDest, false);

    $stmt = $pdoRacine->query('SHOW DATABASES LIKE ' . $pdoRacine->quote($baseDest));
    if ($stmt !== false && $stmt->fetchColumn() !== false && !$force) {
        abandonner(
            "la base « {$baseDest} » existe déjà.\n"
            . "         --force la supprime et la recrée ; sans transaction possible sur\n"
            . "         du MyISAM, c'est aussi ce qui rattrape une exécution interrompue."
        );
    }

    try {
        $source = DbConnectorPdo::getInstance($nomSource);
    } catch (Exception $e) {
        abandonner("connexion à la source « {$nomSource} » impossible.\n         " . $e->getMessage());
    }

    $pdoSource = $source->getPDO();

    printf("Source : %s@%s, %d derniers événements ajoutés\n", $configs[$nomSource]['dbname'], $configs[$nomSource]['host'], $limite);

    // --- Fermeture des identifiants ---------------------------------------
    //
    // Chaque étape ne s'appuie que sur des ensembles déjà connus. L'ordre compte :
    // les personnes ne peuvent être arrêtées qu'une fois lieux, salles,
    // organisateurs et descriptions retenus, chacun apportant les siennes.

    echo "\nFermeture des identifiants…\n";

    $stmt = $pdoSource->query(
        'SELECT idevenement, idLieu, idSalle, idPersonne FROM evenement ORDER BY dateAjout DESC LIMIT ' . $limite
    );
    if ($stmt === false) {
        abandonner('lecture de la table evenement impossible.');
    }
    $tete = $stmt->fetchAll();

    if ($tete === []) {
        abandonner('aucun événement dans la source.');
    }

    $idsEvenement = idsDistincts($tete, 'idevenement');
    $idsLieu = idsDistincts($tete, 'idLieu');
    $idsSalleEvenement = idsDistincts($tete, 'idSalle');

    $lieux = lire($source, 'lieu', 'idLieu', $idsLieu);

    // Toutes les salles des lieux retenus, pour que la liste déroulante du
    // formulaire d'événement soit complète, plus celles qu'un événement désigne
    // sans qu'elles appartiennent à son lieu.
    $salles = lire($source, 'salle', 'idLieu', $idsLieu);
    $sallesManquantes = array_values(array_diff($idsSalleEvenement, idsDistincts($salles, 'idSalle')));
    $salles = array_merge($salles, lire($source, 'salle', 'idSalle', $sallesManquantes));

    $evenementOrganisateur = lire($source, 'evenement_organisateur', 'idEvenement', $idsEvenement);
    $lieuOrganisateur = lire($source, 'lieu_organisateur', 'idLieu', $idsLieu);

    $idsOrganisateur = fusionnerIds(
        idsDistincts($evenementOrganisateur, 'idOrganisateur'),
        idsDistincts($lieuOrganisateur, 'idOrganisateur')
    );

    $organisateurs = lire($source, 'organisateur', 'idOrganisateur', $idsOrganisateur);
    $descriptions = lire($source, 'descriptionlieu', 'idLieu', $idsLieu);
    $personneOrganisateur = lire($source, 'personne_organisateur', 'idOrganisateur', $idsOrganisateur);

    $lieuFichiers = lire($source, 'lieu_fichierrecu', 'idLieu', $idsLieu);
    $fichiers = lire($source, 'fichierrecu', 'idFichierrecu', idsDistincts($lieuFichiers, 'idFichierrecu'));

    // lieu.idpersonne s'écrit tout en minuscules, contrairement à toutes les
    // autres colonnes du même rôle.
    $idsPersonne = fusionnerIds(
        idsDistincts($tete, 'idPersonne'),
        idsDistincts($lieux, 'idpersonne'),
        idsDistincts($salles, 'idPersonne'),
        idsDistincts($organisateurs, 'idPersonne'),
        idsDistincts($descriptions, 'idPersonne'),
        idsDistincts($personneOrganisateur, 'idPersonne')
    );

    $personnes = lire($source, 'personne', 'idPersonne', $idsPersonne);

    // affiliation.idAffiliation désigne un idLieu quand genre vaut « lieu ». Une
    // ligne pointant hors de la copie ferait joindre Personne::… dans le vide.
    $lieuxRetenus = array_flip($idsLieu);
    $affiliations = array_values(array_filter(
        lire($source, 'affiliation', 'idPersonne', $idsPersonne),
        static fn (array $a): bool => $a['genre'] !== 'lieu' || isset($lieuxRetenus[(int) $a['idAffiliation']])
    ));

    $stmt = $pdoSource->query('SELECT * FROM localite');
    if ($stmt === false) {
        abandonner('lecture de la table localite impossible.');
    }
    $localites = $stmt->fetchAll();

    $evenements = lire($source, 'evenement', 'idevenement', $idsEvenement);

    // --- Anonymisation -----------------------------------------------------

    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

    $personnes = array_map(
        static function (array $p) use ($hash): array {
            $id = (int) $p['idPersonne'];

            return remplacer($p, [
                'pseudo' => 'user' . $id,
                'mot_de_passe' => $hash,
                'email' => 'user' . $id . '@example.test',
                'cookie' => '',
                // sel du stockage sha1 historique : le laisser ferait tenter à
                // Sentry l'ancienne vérification avant password_verify()
                'gds' => '',
                'affiliation' => '',
            ]);
        },
        $personnes
    );

    $evenements = array_map(
        static function (array $e): array {
            $courriel = (string) ($e['user_email'] ?? '');

            return remplacer($e, [
                'user_email' => $courriel === '' ? $e['user_email'] : 'visiteur' . (int) $e['idevenement'] . '@example.test',
                // note privée à l'administrateur, jamais publiée : elle porte
                // parfois un téléphone ou un nom
                'remarque' => $e['remarque'] === null ? null : '',
            ]);
        },
        $evenements
    );

    $organisateurs = array_map(
        static function (array $o): array {
            $courriel = (string) ($o['email'] ?? '');

            return remplacer($o, [
                'email' => $courriel === '' ? $o['email'] : 'orga' . (int) $o['idOrganisateur'] . '@example.test',
            ]);
        },
        $organisateurs
    );

    /** @var array<string, list<array<string, mixed>>> $copie */
    $copie = [
        'localite' => $localites,
        'personne' => $personnes,
        'affiliation' => $affiliations,
        'lieu' => $lieux,
        'descriptionlieu' => $descriptions,
        'salle' => $salles,
        'organisateur' => $organisateurs,
        'lieu_organisateur' => $lieuOrganisateur,
        'personne_organisateur' => $personneOrganisateur,
        'evenement' => $evenements,
        'evenement_organisateur' => $evenementOrganisateur,
        'fichierrecu' => $fichiers,
        'lieu_fichierrecu' => $lieuFichiers,
    ];

    // --- Création de la base ----------------------------------------------
    //
    // Le schéma est celui de la production, lu par SHOW CREATE TABLE, et non celui
    // de resources/database/ladecadanse.sql. Ce dernier est tenu à jour à la main et
    // ne fait autorité que sur une installation neuve ; ici les lignes viennent de la
    // production, donc le schéma qui les accueille doit être le sien. Un écart d'une
    // seule colonne ferait échouer les INSERT.

    printf("\nCréation de %s…\n", $baseDest);

    $pdoRacine->exec("DROP DATABASE IF EXISTS `{$baseDest}`");
    $pdoRacine->exec("CREATE DATABASE `{$baseDest}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdoDest = connecterDestination($configDest, true);

    foreach (TABLES_COPIEES as $table) {
        $stmt = $pdoSource->query("SHOW CREATE TABLE `{$table}`");
        if ($stmt === false) {
            abandonner("table « {$table} » absente de la source.");
        }

        $creation = $stmt->fetch();

        try {
            $pdoDest->exec((string) $creation['Create Table']);
        } catch (PDOException $e) {
            abandonner(
                "création de la table « {$table} » refusée par le serveur local.\n"
                . "         " . $e->getMessage() . "\n"
                . "         Le schéma est rejoué tel quel : un MariaDB en production et un\n"
                . "         MySQL en local ne se rendent pas toujours le même CREATE TABLE."
            );
        }
    }

    // --- Copie -------------------------------------------------------------

    echo "\nCopie :\n";

    foreach ($copie as $table => $lignes) {
        printf("  %-24s %6d\n", $table, ecrire($pdoDest, $table, $lignes));
    }

    // Sous quel compte se connecter : le mot de passe est le même partout, seul
    // le groupe distingue ce qu'on pourra faire une fois connecté.
    $parGroupe = [];
    foreach ($personnes as $p) {
        $parGroupe[(int) $p['groupe']][] = (int) $p['idPersonne'];
    }
    ksort($parGroupe);

    printf("\nComptes copiés — mot de passe « %s » pour tous :\n", $motDePasse);
    foreach ($parGroupe as $groupe => $ids) {
        printf(
            "  %-11s %4d compte(s), par ex. user%d\n",
            UserLevel::getName($groupe),
            count($ids),
            $ids[0]
        );
    }
}


// ---------------------------------------------------------------------------
// Phase fichiers
// ---------------------------------------------------------------------------

if ($phase !== 'db') {
    // La liste se lit sur la copie, jamais sur la production : --only=files se
    // relance ainsi sans refaire la base, et ne télécharge que ce qui a été copié.
    try {
        $pdoDest = connecterDestination($configDest, true);
    } catch (PDOException $e) {
        abandonner("la base « {$baseDest} » n'est pas accessible. La construire d'abord, sans --only=files.");
    }

    $taches = [];

    /**
     * Une image et sa miniature. basename() écarte tout chemin qui traînerait dans
     * la colonne : la valeur vient de la production, pas d'ici.
     */
    $ajouter = static function (string $repertoire, string $nom) use (&$taches, $baseUrl, $repUploads): void {
        $nom = basename($nom);
        if ($nom === '') {
            return;
        }

        foreach (['', 's_'] as $prefixe) {
            $taches[$repertoire . '/' . $prefixe . $nom] = [
                // ASSETS_DIR vaut « /web » : racine URL autant que suffixe système
                'url' => $baseUrl . '/web/uploads/' . $repertoire . '/' . rawurlencode($prefixe . $nom),
                'chemin' => $repUploads . '/' . $repertoire . '/' . $prefixe . $nom,
            ];
        }
    };

    foreach (COLONNES_FICHIERS as $table => $definition) {
        $colonnes = '`' . implode('`, `', $definition['colonnes']) . '`';

        $stmt = $pdoDest->query("SELECT {$colonnes} FROM `{$table}`");
        if ($stmt === false) {
            abandonner("lecture des noms de fichiers de « {$table} » impossible.");
        }

        foreach ($stmt->fetchAll() as $ligne) {
            foreach ($ligne as $nom) {
                if (is_string($nom) && $nom !== '') {
                    $ajouter($definition['repertoire'], $nom);
                }
            }
        }
    }

    // Galerie d'un lieu : le nom du fichier se reconstruit depuis l'identifiant et
    // l'extension, la colonne n'en garde pas trace (cf. LieuEdition::enregistrer).
    $stmt = $pdoDest->query(
        "SELECT f.idFichierrecu, f.extension
         FROM fichierrecu f
         JOIN lieu_fichierrecu lf ON lf.idFichierrecu = f.idFichierrecu
         WHERE f.type = 'image'"
    );
    if ($stmt === false) {
        abandonner('lecture des galeries de lieux impossible.');
    }

    foreach ($stmt->fetchAll() as $ligne) {
        $ajouter('lieux/galeries', $ligne['idFichierrecu'] . '.' . $ligne['extension']);
    }

    printf("\nFichiers référencés : %d (miniatures comprises)\n", count($taches));

    // Les fichiers d'une instance précédente répondent à une autre base. Ils ne
    // seront pas écrasés — telecharger() saute ce qui est déjà là — mais ils
    // resteront mêlés à ceux de la copie sans que rien ne l'indique.
    if ($reinitialiserUploads) {
        printf("  contenu précédent déplacé dans %s\n", basename(mettreDeCoteLesUploads($repUploads)));
    } else {
        $attendus = [];
        foreach ($taches as $tache) {
            $attendus[$tache['chemin']] = true;
        }

        $orphelins = compterOrphelins($repUploads, $attendus);
        if ($orphelins > 0) {
            printf(
                "  %d fichier(s) déjà présent(s) qu'aucune ligne de la copie ne référence.\n"
                . "  --reset-uploads les déplace dans un répertoire daté avant de télécharger.\n",
                $orphelins
            );
        }
    }

    $bilan = telecharger(array_values($taches), TRANSFERTS_SIMULTANES);

    printf(
        "  %d écrits (%.1f Mo), %d déjà là, %d absents en production, %d refusés, %d en échec\n",
        $bilan['ecrits'],
        $bilan['octets'] / 1048576,
        $bilan['presents'],
        $bilan['absents'],
        $bilan['refuses'],
        $bilan['echecs']
    );

    if ($bilan['refuses'] > 0) {
        echo "\nDes 403 : le pare-feu 8G a rejeté la requête. Il ne laisse servir depuis\n"
            . "/web/uploads/ que jpg, jpeg, png, gif et webp, et bloque tout User-Agent\n"
            . "contenant « curl » ou « scan ».\n";
    }

    if ($bilan['brides'] > 0) {
        printf(
            "\nLa production a répondu 429 (trop de requêtes) : le téléchargement s'est\n"
            . "arrêté là, %d fichier(s) n'ont pas été tentés. Relancer --only=files plus\n"
            . "tard reprendra où il en est, les fichiers déjà écrits étant sautés.\n",
            $bilan['non_tentes']
        );
    }
}

echo "\nTerminé.\n";
