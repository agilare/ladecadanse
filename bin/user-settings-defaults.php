<?php

declare(strict_types=1);

/**
 * Reporte dans `personne.settings` les valeurs qu'un utilisateur saisit systématiquement.
 *
 * Même calcul que `resources/database/mailing-users-specialises.sql` : pour chaque compte actif,
 * non administrateur et **d'au moins 50 ajouts**, la catégorie, le lieu et les organisateurs
 * présents dans au moins 96 % de ses ajouts d'événements. Ce qui atteint le seuil devient une
 * valeur par défaut du formulaire d'ajout — le même réglage que le fieldset « Événements » du
 * profil (user-edit.php).
 *
 * Le plancher de 50 est ce qui rend le seuil opérant : à 96 %, il faut 25 ajouts pour s'autoriser
 * un seul écart, si bien qu'en dessous « 96 % » voudrait dire « aucune exception ». Au plancher,
 * deux écarts sont tolérés ; quatre à 100 ajouts.
 *
 * Trois différences avec la requête de mailing, qui ne cherche pas la même chose :
 *
 *  - la requête sélectionne des personnes (un motif suffit), le script remplit des champs.
 *    Chaque champ est jugé séparément : qui est à 100 % sur une catégorie mais à 60 % sur un
 *    lieu reçoit la catégorie, et rien d'autre ;
 *  - `settings.idLieu` est un identifiant. Un lieu dominant désigné par un nom libre
 *    (`evenement.nomLieu`, sans fiche) compte dans le pourcentage mais ne peut rien remplir ;
 *  - lieu et organisateurs doivent être `statut = 'actif'`, sinon le profil afficherait un
 *    réglage sans <option> correspondante, qu'un simple enregistrement effacerait.
 *
 * Le JSON n'est jamais construit ici : il sort de `UserSettings`, comme dans user-edit.php.
 * La colonne est relue puis fusionnée, de sorte qu'un réglage d'un autre domaine survive.
 * `date_derniere_modif` n'est pas touchée : ce n'est pas la personne qui a modifié son profil.
 *
 * Usage :
 *   php bin/user-settings-defaults.php                      simulation (comportement par défaut)
 *   php bin/user-settings-defaults.php --ecrire             écrit, après avoir déposé un fichier
 *                                                           de retour arrière dans var/
 *   php bin/user-settings-defaults.php --sql=var/reprise.sql
 *                                                           n'écrit rien dans la base : produit les
 *                                                           UPDATE, et leur retour dans …-retour.sql
 *   php bin/user-settings-defaults.php --csv=var/destinataires.csv
 *                                                           les comptes réellement mis à jour, au
 *                                                           format d'admin/mailing.php ; se combine
 *                                                           avec --sql comme avec --ecrire
 *   php bin/user-settings-defaults.php --seuil=0.90         part minimale (0.96 par défaut)
 *   php bin/user-settings-defaults.php --min-evenements=25  plancher d'ajouts (50 par défaut)
 *   php bin/user-settings-defaults.php --depuis-login=0     sans condition de connexion récente
 *                                                           (1 an par défaut)
 *   php bin/user-settings-defaults.php --idp=12,34          se limiter à ces comptes
 *   php bin/user-settings-defaults.php --limite=20          premier passage prudent
 *   php bin/user-settings-defaults.php --ecraser            remplace aussi un réglage déjà saisi
 *
 * Sans --ecraser, un champ déjà renseigné par la personne est laissé tel quel : le script
 * complète, il ne corrige pas un choix délibéré.
 *
 * Pour la production, c'est --sql qu'il faut : `bin/` n'est pas déployé (`.git-ftp-ignore`), et une
 * reprise d'une fois n'a pas besoin d'y être. Faire tourner le script sur une copie locale de la
 * base de production, puis rejouer le fichier obtenu dans phpMyAdmin. Chaque UPDATE y est gardé par
 * la valeur lue dans la copie, si bien qu'un profil réglé entre-temps par son propriétaire n'est
 * pas écrasé.
 *
 * Créé : 27 août 2026
 */

use Ladecadanse\UserSettings;
use Ladecadanse\Utils\DbConnectorPdo;
use PHPMailer\PHPMailer\PHPMailer;

if (PHP_SAPI !== 'cli')
{
    http_response_code(404);
    exit(1);
}

$racine = dirname(__DIR__);

// app/config.php lit $_SERVER['REQUEST_URI'] pour composer $page_url : sans requête HTTP,
// la clé n'existe pas et PHP 8.4 refuse le null passé à parse_url()
$_SERVER['REQUEST_URI'] ??= '/';

require_once $racine . '/app/env.php';
require $racine . '/vendor/autoload.php';
require_once $racine . '/app/config.php';

$options = getopt('', ['ecrire', 'ecraser', 'sql::', 'csv::', 'seuil::', 'min-evenements::', 'depuis-login::', 'idp::', 'limite::', 'help']);

if (isset($options['help']))
{
    fwrite(STDOUT, "Voir l'en-tête de " . __FILE__ . "\n");
    exit(0);
}

$ecrire = isset($options['ecrire']);
$ecraser = isset($options['ecraser']);
$seuil = (float) ($options['seuil'] ?? 0.96);
$minEvenements = (int) ($options['min-evenements'] ?? 50);
$anneesLogin = (int) ($options['depuis-login'] ?? 1);
$limite = (int) ($options['limite'] ?? 0);

$idpFiltre = [];
if (isset($options['idp']) && $options['idp'] !== '')
{
    $idpFiltre = array_values(array_filter(array_map('intval', explode(',', (string) $options['idp'])), static fn(int $i): bool => $i > 0));
}

if ($seuil <= 0 || $seuil > 1)
{
    fwrite(STDERR, "ERREUR : --seuil attend une part entre 0 et 1 (0.96 par défaut)\n");
    exit(1);
}

$cheminSql = null;
if (isset($options['sql']))
{
    if (!is_string($options['sql']) || trim($options['sql']) === '')
    {
        fwrite(STDERR, "ERREUR : --sql attend un chemin de fichier (--sql=var/reprise.sql)\n");
        exit(1);
    }

    $cheminSql = trim($options['sql']);
}

// les deux modes s'excluent : --sql produit un fichier pour une autre base, --ecrire touche celle-ci
if ($cheminSql !== null && $ecrire)
{
    fwrite(STDERR, "ERREUR : --sql et --ecrire ne vont pas ensemble. --sql écrit un fichier, --ecrire modifie la base.\n");
    exit(1);
}

$cheminCsv = null;
if (isset($options['csv']))
{
    if (!is_string($options['csv']) || trim($options['csv']) === '')
    {
        fwrite(STDERR, "ERREUR : --csv attend un chemin de fichier (--csv=var/destinataires.csv)\n");
        exit(1);
    }

    $cheminCsv = trim($options['csv']);
}

/*
 * La liste des catégories valides, référence de sanitizeEventNewDefaults().
 *
 * `$glo_tab_genre` vient de app/config.php, inclus plus haut par un chemin calculé que l'analyse
 * statique ne sait pas suivre. Le contrôle n'est pas une formalité : si la variable changeait de
 * nom, `array_key_exists()` refuserait silencieusement toutes les catégories et le script
 * s'achèverait sur un rapport vide, d'apparence normale.
 */
$genresAutorises = $glo_tab_genre ?? null;

if (!is_array($genresAutorises) || $genresAutorises === [])
{
    fwrite(STDERR, "ERREUR : \$glo_tab_genre est introuvable ou vide — app/config.php a-t-il changé ?\n");
    exit(1);
}

$pdo = DbConnectorPdo::getInstance()->getPDO();

/*
 * Le seuil, la catégorie et les organisateurs dominants, en une requête.
 *
 * Les bornes variables sont interpolées plutôt que liées quand elles reviennent plusieurs fois :
 * avec EMULATE_PREPARES à false, un marqueur nommé réutilisé fait échouer l'exécution (HY093).
 * Toutes viennent d'ici, aucune d'une saisie.
 */
$clauseLogin = $anneesLogin > 0
    ? "AND p.last_login >= DATE_SUB(CURDATE(), INTERVAL " . $anneesLogin . " YEAR)"
    : "";

$clauseIdp = $idpFiltre !== []
    ? "AND p.idPersonne IN (" . implode(',', $idpFiltre) . ")"
    : "";

$sql = "
WITH
candidats AS (
    SELECT p.idPersonne, p.pseudo, p.email, p.settings
    FROM personne p
    WHERE p.groupe > 4
      AND p.statut = 'actif'
      $clauseLogin
      $clauseIdp
),
ajouts AS (
    SELECT
        e.idPersonne,
        e.idevenement,
        e.idLieu,
        CASE
            WHEN e.idLieu > 0          THEN CONCAT('L', e.idLieu)
            WHEN TRIM(e.nomLieu) <> '' THEN CONCAT('N', LOWER(TRIM(e.nomLieu)))
            ELSE CONCAT('E', e.idevenement)
        END AS cle_lieu,
        COALESCE(NULLIF(l.nom, ''), NULLIF(TRIM(e.nomLieu), ''), '(sans lieu)') AS nom_lieu,
        e.genre
    FROM evenement e
    JOIN candidats c ON c.idPersonne = e.idPersonne
    LEFT JOIN lieu l ON l.idLieu = e.idLieu
),
totaux AS (
    SELECT idPersonne, COUNT(*) AS nb_total FROM ajouts GROUP BY idPersonne
),
lieu_top AS (
    SELECT idPersonne, nom_lieu, idLieu, nb FROM (
        SELECT idPersonne, MAX(nom_lieu) AS nom_lieu, MAX(idLieu) AS idLieu, COUNT(*) AS nb,
               ROW_NUMBER() OVER (PARTITION BY idPersonne ORDER BY COUNT(*) DESC, cle_lieu) AS rang
        FROM ajouts GROUP BY idPersonne, cle_lieu
    ) x WHERE rang = 1
),
genre_top AS (
    SELECT idPersonne, genre, nb FROM (
        SELECT idPersonne, genre, COUNT(*) AS nb,
               ROW_NUMBER() OVER (PARTITION BY idPersonne ORDER BY COUNT(*) DESC, genre) AS rang
        FROM ajouts GROUP BY idPersonne, genre
    ) y WHERE rang = 1
),
orga_top AS (
    SELECT o.idPersonne, GROUP_CONCAT(o.idOrganisateur ORDER BY o.nb DESC) AS ids, MAX(o.nb) AS nb
    FROM (
        SELECT a.idPersonne, eo.idOrganisateur, COUNT(*) AS nb
        FROM ajouts a
        JOIN evenement_organisateur eo ON eo.idEvenement = a.idevenement
        GROUP BY a.idPersonne, eo.idOrganisateur
    ) o
    JOIN totaux t ON t.idPersonne = o.idPersonne
    WHERE o.nb >= t.nb_total * :seuil_orga
    GROUP BY o.idPersonne
)
SELECT
    c.idPersonne, c.pseudo, c.email, c.settings,
    t.nb_total,
    gt.genre     AS genre_dominant,   gt.nb AS genre_nb,
    lt.idLieu    AS lieu_id,          lt.nom_lieu AS lieu_nom, lt.nb AS lieu_nb,
    ot.ids       AS organisateurs_ids, ot.nb AS organisateurs_nb
FROM candidats c
JOIN totaux t ON t.idPersonne = c.idPersonne
LEFT JOIN lieu_top  lt ON lt.idPersonne = c.idPersonne
LEFT JOIN genre_top gt ON gt.idPersonne = c.idPersonne
LEFT JOIN orga_top  ot ON ot.idPersonne = c.idPersonne
WHERE t.nb_total >= :min_evenements
ORDER BY t.nb_total DESC, c.idPersonne
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':seuil_orga' => $seuil, ':min_evenements' => $minEvenements]);
$lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ce que le profil sait offrir : un identifiant absent de ces listes ne doit pas être écrit
$lieuxActifs = array_flip(array_map('intval', $pdo->query("SELECT idLieu FROM lieu WHERE statut = 'actif'")->fetchAll(PDO::FETCH_COLUMN)));
$orgasActifs = array_flip(array_map('intval', $pdo->query("SELECT idOrganisateur FROM organisateur WHERE statut = 'actif'")->fetchAll(PDO::FETCH_COLUMN)));

$aEcrire = [];
$ecartes = ['genre' => 0, 'lieu_nom_libre' => 0, 'lieu_inactif' => 0, 'orga_inactif' => 0, 'deja_regle' => 0];

foreach ($lignes as $ligne)
{
    $nbTotal = (int) $ligne['nb_total'];
    $plancher = $nbTotal * $seuil;

    $actuels = UserSettings::eventNewDefaults(is_string($ligne['settings']) ? $ligne['settings'] : null);
    $calcules = [];

    // catégorie
    if ($ligne['genre_dominant'] !== null && (int) $ligne['genre_nb'] >= $plancher)
    {
        if (array_key_exists($ligne['genre_dominant'], $genresAutorises))
        {
            $calcules['genre'] = (string) $ligne['genre_dominant'];
        }
        else
        {
            // un genre hors barème en base : signalé plutôt que glissé dans la colonne
            $ecartes['genre']++;
        }
    }

    // lieu : le pourcentage confond fiche et nom libre, le report exige une fiche active
    if ($ligne['lieu_id'] !== null && (int) $ligne['lieu_nb'] >= $plancher)
    {
        $idLieu = (int) $ligne['lieu_id'];

        if ($idLieu === 0)
        {
            $ecartes['lieu_nom_libre']++;
        }
        elseif (!isset($lieuxActifs[$idLieu]))
        {
            $ecartes['lieu_inactif']++;
        }
        else
        {
            $calcules['idLieu'] = $idLieu;
        }
    }

    // organisateurs
    if ($ligne['organisateurs_ids'] !== null)
    {
        $ids = array_map('intval', explode(',', (string) $ligne['organisateurs_ids']));
        $retenus = array_values(array_filter($ids, static fn(int $id): bool => isset($orgasActifs[$id])));

        if (count($retenus) < count($ids))
        {
            $ecartes['orga_inactif']++;
        }

        if ($retenus !== [])
        {
            $calcules['idOrganisateurs'] = $retenus;
        }
    }

    if ($calcules === [])
    {
        continue;
    }

    /*
     * Fusion champ par champ. Sans --ecraser, un champ déjà renseigné est laissé intact :
     * il vient du formulaire de profil, donc d'une décision, que ce script n'a pas à défaire.
     */
    $fusion = $actuels;
    $poses = [];

    foreach ($calcules as $cle => $valeur)
    {
        $dejaRegle = $actuels[$cle] !== '' && $actuels[$cle] !== 0 && $actuels[$cle] !== [];

        if ($dejaRegle && !$ecraser)
        {
            $ecartes['deja_regle']++;
            continue;
        }

        $fusion[$cle] = $valeur;
        $poses[$cle] = $valeur;
    }

    if ($poses === [])
    {
        continue;
    }

    $normalise = UserSettings::sanitizeEventNewDefaults($fusion, $genresAutorises);
    $jsonAvant = is_string($ligne['settings']) ? $ligne['settings'] : null;
    $jsonApres = UserSettings::withEventNewDefaults($jsonAvant, $normalise);

    if ($jsonApres === ($jsonAvant ?? ''))
    {
        continue;
    }

    $aEcrire[] = [
        'idPersonne' => (int) $ligne['idPersonne'],
        'pseudo' => (string) $ligne['pseudo'],
        'email' => (string) $ligne['email'],
        'nb_total' => $nbTotal,
        'lieu_nom' => (string) ($ligne['lieu_nom'] ?? ''),
        'poses' => $poses,
        'json_avant' => $jsonAvant,
        'json_apres' => $jsonApres,
    ];

    if ($limite > 0 && count($aEcrire) >= $limite)
    {
        break;
    }
}

// -----------------------------------------------------------------------------
// Rapport
// -----------------------------------------------------------------------------

fwrite(STDOUT, sprintf(
    "%d compte(s) examiné(s) (>= %d ajouts), seuil %s %%, %d à remplir\n\n",
    count($lignes),
    $minEvenements,
    rtrim(rtrim(number_format($seuil * 100, 2, ',', ''), '0'), ','),
    count($aEcrire)
));

if ($aEcrire !== [])
{
    fwrite(STDOUT, sprintf("%-8s %-24s %7s  %s\n", 'idP', 'pseudo', 'ajouts', 'settings après'));
    fwrite(STDOUT, str_repeat('-', 110) . "\n");

    foreach ($aEcrire as $r)
    {
        fwrite(STDOUT, sprintf(
            "%-8d %-24s %7d  %s\n",
            $r['idPersonne'],
            mb_substr($r['pseudo'], 0, 24),
            $r['nb_total'],
            $r['json_apres']
        ));
    }

    fwrite(STDOUT, "\n");
}

foreach (array_filter($ecartes) as $motif => $nb)
{
    fwrite(STDOUT, sprintf("  %d champ(s) écarté(s) : %s\n", $nb, match ($motif) {
        'genre' => "genre absent de \$glo_tab_genre",
        'lieu_nom_libre' => "lieu dominant saisi en nom libre, sans fiche",
        'lieu_inactif' => "lieu dominant non actif",
        'orga_inactif' => "au moins un organisateur non actif",
        'deja_regle' => "déjà réglé par la personne (--ecraser pour passer outre)",
    }));
}

/*
 * --csv : la liste des comptes réellement mis à jour, au format qu'attend admin/mailing.php.
 *
 * Elle ne se déduit pas de la requête de mailing : celle-ci retient une personne dès qu'un motif
 * suffit, alors qu'ici le même motif peut ne rien écrire — lieu dominant désigné en nom libre,
 * organisateur désactivé, champ déjà réglé par l'intéressé. Écrire à la liste du mailing
 * reviendrait à annoncer à quelqu'un un préremplissage qu'il n'a pas reçu.
 *
 * L'adresse passe la validation même de PHPMailer, celle qu'appliquera `parserCsvDestinataires()` :
 * une ligne écrite ici est une ligne qui partira, sans rejet à l'aperçu.
 */
if ($cheminCsv !== null)
{
    $handle = fopen($cheminCsv, 'w');

    if ($handle === false)
    {
        fwrite(STDERR, "ERREUR : impossible d'écrire " . $cheminCsv . "\n");
        exit(1);
    }

    // l'en-tête est facultatif pour le formulaire, mais il documente le fichier.
    // $escape explicite : sa valeur par défaut est dépréciée depuis PHP 8.4
    fputcsv($handle, ['idPersonne', 'pseudo', 'email'], ',', '"', '');

    $lignesCsv = 0;
    $sansEmail = 0;

    foreach ($aEcrire as $r)
    {
        if (!PHPMailer::validateAddress($r['email']))
        {
            $sansEmail++;
            continue;
        }

        fputcsv($handle, [$r['idPersonne'], $r['pseudo'], $r['email']], ',', '"', '');
        $lignesCsv++;
    }

    fclose($handle);

    fwrite(STDOUT, "\n" . $lignesCsv . " destinataire(s) écrit(s) dans " . $cheminCsv . "\n");

    if ($sansEmail > 0)
    {
        fwrite(STDOUT, "  " . $sansEmail . " compte(s) hors de cette liste : adresse absente ou invalide.\n");
        fwrite(STDOUT, "  Leurs réglages sont posés quand même — settings n'a pas besoin d'une adresse.\n");
    }

    if ($cheminSql !== null)
    {
        fwrite(STDOUT, "  Liste établie avant application : si un garde du .sql ne correspond plus, le\n");
        fwrite(STDOUT, "  compte concerné n'aura pas été mis à jour. Recouper avec les lignes modifiées.\n");
    }
}

/*
 * Mode --sql : produire les UPDATE au lieu de les exécuter.
 *
 * `bin/` n'est pas déployé (`.git-ftp-ignore`), et ce script n'a de toute façon rien à faire en
 * production : c'est une reprise d'une fois, dont le produit tient en quelques UPDATE. On le fait
 * donc tourner sur une copie locale de la base de production, et on rejoue le fichier obtenu.
 *
 * Chaque instruction est gardée par la valeur lue dans la copie. Entre le dump et la reprise,
 * quelqu'un a pu régler son profil lui-même : sa ligne ne doit pas être écrasée par une valeur
 * calculée sur des données périmées. Le garde la laisse intacte, et l'écart entre le nombre de
 * lignes modifiées et le nombre annoncé ici rend ces cas visibles au lieu de les taire.
 */
if ($cheminSql !== null)
{
    if ($aEcrire === [])
    {
        fwrite(STDOUT, "\nRien à écrire : aucun fichier produit.\n");
        exit(0);
    }

    $valeurSql = static fn(?string $v): string => $v === null ? 'NULL' : $pdo->quote($v);

    $entete = "-- Reprise des valeurs par défaut d'ajout d'événement — " . date('c') . "\n"
        . "-- Produit par bin/user-settings-defaults.php depuis une copie locale de la base.\n"
        . "-- Paramètres : seuil " . rtrim(rtrim(number_format($seuil * 100, 2, ',', ''), '0'), ',') . " %"
        . ", plancher " . $minEvenements . " ajouts"
        . ", connexion depuis " . ($anneesLogin > 0 ? $anneesLogin . " an(s)" : "sans condition")
        . ($ecraser ? ", --ecraser" : "") . "\n"
        . "--\n"
        . "-- Chaque UPDATE est gardé par la valeur lue dans la copie : une personne qui a modifié\n"
        . "-- ses réglages entre le dump et la reprise n'est pas touchée. Comparer le nombre de\n"
        . "-- lignes modifiées à " . count($aEcrire) . " pour savoir combien ont été ignorées.\n"
        . "--\n"
        . "-- Les guillemets du JSON sont échappés par des antislashs : à rejouer sous un sql_mode\n"
        . "-- sans NO_BACKSLASH_ESCAPES, ce qui est le cas par défaut.\n"
        . "\n"
        . "SET NAMES utf8mb4;\n\n";

    $avant = $entete;
    $apres = $entete;

    foreach ($aEcrire as $r)
    {
        // aller : on ne pose la valeur que si la colonne est restée celle de la copie
        $avant .= sprintf(
            "UPDATE personne SET settings = %s WHERE idPersonne = %d AND settings <=> %s;\n",
            $valeurSql($r['json_apres']),
            $r['idPersonne'],
            $valeurSql($r['json_avant'])
        );

        // retour : symétrique, il ne défait que ce que l'aller a effectivement posé
        $apres .= sprintf(
            "UPDATE personne SET settings = %s WHERE idPersonne = %d AND settings <=> %s;\n",
            $valeurSql($r['json_avant']),
            $r['idPersonne'],
            $valeurSql($r['json_apres'])
        );
    }

    $cheminSqlRetour = preg_replace('/(\.sql)?$/', '', $cheminSql, 1) . '-retour.sql';

    if (file_put_contents($cheminSql, $avant) === false || file_put_contents($cheminSqlRetour, $apres) === false)
    {
        fwrite(STDERR, "ERREUR : impossible d'écrire " . $cheminSql . " ou " . $cheminSqlRetour . "\n");
        exit(1);
    }

    fwrite(STDOUT, "\n" . count($aEcrire) . " UPDATE écrit(s) dans " . $cheminSql . "\n");
    fwrite(STDOUT, "Retour arrière correspondant dans " . $cheminSqlRetour . "\n");
    fwrite(STDOUT, "Rien n'a été modifié dans la base locale.\n");
    exit(0);
}

if (!$ecrire)
{
    fwrite(STDOUT, "\nSimulation : rien n'a été écrit. Ajouter --ecrire pour appliquer.\n");
    exit(0);
}

if ($aEcrire === [])
{
    exit(0);
}

// -----------------------------------------------------------------------------
// Écriture, précédée du fichier de retour arrière
//
// `personne` est en MyISAM : aucune transaction ne protège ce lot. Les anciennes valeurs sont
// donc déposées sur disque avant la première écriture, sous forme d'UPDATE prêts à rejouer.
// -----------------------------------------------------------------------------

$cheminRetour = $racine . '/var/user-settings-defaults-retour-' . date('Ymd-His') . '.sql';
$retour = "-- Retour arrière du " . date('c') . " : rejouer ce fichier restaure les valeurs précédentes\n"
    . "-- Les guillemets du JSON sont échappés par des antislashs : à rejouer sous un sql_mode\n"
    . "-- sans NO_BACKSLASH_ESCAPES, ce qui est le cas par défaut.\n";

foreach ($aEcrire as $r)
{
    $retour .= sprintf(
        "UPDATE personne SET settings = %s WHERE idPersonne = %d;\n",
        $r['json_avant'] === null ? 'NULL' : $pdo->quote($r['json_avant']),
        $r['idPersonne']
    );
}

if (file_put_contents($cheminRetour, $retour) === false)
{
    fwrite(STDERR, "ERREUR : impossible d'écrire " . $cheminRetour . " — rien n'a été modifié\n");
    exit(1);
}

fwrite(STDOUT, "\nRetour arrière déposé dans " . $cheminRetour . "\n");

$maj = $pdo->prepare("UPDATE personne SET settings = :settings WHERE idPersonne = :idP");
$ecrits = 0;

foreach ($aEcrire as $r)
{
    $maj->execute([':settings' => $r['json_apres'], ':idP' => $r['idPersonne']]);
    $ecrits++;
}

fwrite(STDOUT, $ecrits . " compte(s) mis à jour.\n");
exit(0);
