<?php

declare(strict_types=1);

/**
 * Compose les fichiers de configuration serveur — .htaccess et .user.ini — à
 * partir de fragments, et les déploie sur demande.
 *
 * Les fragments publics sont dans htaccess/ et userini/ ; ceux qui portent la
 * configuration d'exploitation (adresses bannies, robots, domaine canonique,
 * chemin du journal d'erreurs) vivent dans le dépôt privé ladecadanse-docs et
 * ne doivent jamais entrer dans ce dépôt-ci. L'extension du fragment décide de
 * la cible, son préfixe numérique décide de l'ordre.
 *
 * Usage :
 *   php bin/build-config.php [--ops-dir=CHEMIN] [--only=htaccess|userini]
 *   php bin/build-config.php --deploy --scope=NOM
 *
 * Voir docs/config-serveur.md.
 */

const OPS_DIR_DEFAUT = '../ladecadanse-docs/config-serveur';

/**
 * Pour chaque fichier composé : répertoire des fragments publics, extension qui
 * le désigne, et préfixe de commentaire — Apache accepte « # », un fichier INI
 * exige « ; ».
 */
const CIBLES = [
    '.htaccess' => ['dir' => 'htaccess', 'ext' => 'conf', 'commentaire' => '#'],
    '.user.ini' => ['dir' => 'userini', 'ext' => 'ini', 'commentaire' => ';'],
];

$racine = dirname(__DIR__);
$options = getopt('', ['ops-dir::', 'require-ops', 'deploy', 'scope::', 'only::', 'out-dir::']);

$opsDir = $options['ops-dir'] ?? getenv('LDD_OPS_DIR') ?: OPS_DIR_DEFAUT;
if (!str_starts_with($opsDir, '/') && !preg_match('#^[A-Za-z]:#', $opsDir)) {
    $opsDir = $racine . '/' . $opsDir;
}
$sortieDir = $options['out-dir'] ?? $racine;
$deploiement = isset($options['deploy']);
$opsObligatoires = $deploiement || isset($options['require-ops']);
$seulement = $options['only'] ?? null;

$cibles = CIBLES;
if ($seulement !== null) {
    $cibles = array_filter(CIBLES, static fn (array $c): bool => $c['dir'] === $seulement);
    if ($cibles === []) {
        fwrite(STDERR, sprintf(
            "ERREUR : --only accepte %s\n",
            implode(' ou ', array_column(CIBLES, 'dir'))
        ));
        exit(1);
    }
}

$opsManquants = !is_dir($opsDir) || (glob($opsDir . '/*.*') ?: []) === [];
if ($opsManquants && $opsObligatoires) {
    fwrite(STDERR, <<<TXT
        ERREUR : fragments d'exploitation introuvables dans
                 {$opsDir}
                 Déployer sans eux retirerait de la production ses blocages et
                 son journal d'erreurs.
                 Cloner ladecadanse-docs, ou passer --ops-dir=CHEMIN.

        TXT);
    exit(1);
}

foreach ($cibles as $nom => $cible) {
    $publics = glob($racine . '/' . $cible['dir'] . '/*.' . $cible['ext']) ?: [];
    $ops = is_dir($opsDir) ? (glob($opsDir . '/*.' . $cible['ext']) ?: []) : [];

    if ($publics === []) {
        fwrite(STDERR, "ERREUR : aucun fragment dans {$cible['dir']}/. Dépôt incomplet ?\n");
        exit(1);
    }

    // Le préfixe numérique fait foi, quel que soit le répertoire d'origine
    $fragments = array_merge($publics, $ops);
    usort($fragments, static fn (string $a, string $b): int => strcmp(basename($a), basename($b)));

    // Les fragments d'exploitation sont désignés par « ops/ » et non par leur
    // chemin réel : celui-ci varie d'une machine à l'autre, et le fichier
    // composé part en production
    $contenu = $cible['commentaire'] . " Composé par « composer config:build » depuis :\n";
    foreach ($fragments as $fragment) {
        $chemin = str_starts_with($fragment, $racine)
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($fragment, strlen($racine) + 1))
            : 'ops/' . basename($fragment);
        $contenu .= $cible['commentaire'] . '   ' . $chemin . "\n";
    }

    foreach ($fragments as $fragment) {
        // Apache sous Linux refuse certaines directives dont l'argument traîne
        // un retour chariot : la sortie est normalisée en LF quoi qu'il arrive
        $contenu .= str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($fragment));
    }

    if (file_put_contents($sortieDir . '/' . $nom, $contenu) === false) {
        fwrite(STDERR, "ERREUR : écriture impossible dans {$sortieDir}/{$nom}\n");
        exit(1);
    }

    printf(
        "%-11s composé : %d fragments, %d lignes%s\n",
        $nom,
        count($fragments),
        substr_count($contenu, "\n"),
        $ops === [] ? ' (socle public seul)' : ''
    );
}

if ($opsManquants) {
    fwrite(STDERR, "\nAvertissement : sans les fragments de {$opsDir},\n"
        . "ces fichiers n'ont pas la configuration d'exploitation. Bon pour le\n"
        . "développement local, jamais pour un déploiement.\n");
}

if (!$deploiement) {
    exit(0);
}

// Le scope désigne le serveur de destination. Il n'a pas de valeur par défaut :
// plusieurs peuvent être configurés côte à côte — dont d'anciens hébergements —
// et deviner lequel vise la production reviendrait à parier sur la bonne machine.
exec('git config --get-regexp "^git-ftp\..*\.url"', $lignes, $etat);
$scopes = [];
foreach ($lignes as $ligne) {
    if (preg_match('/^git-ftp\.(.+)\.url\s+(\S+)/', $ligne, $trouve) === 1) {
        $scopes[$trouve[1]] = $trouve[2];
    }
}

$scope = $options['scope'] ?? getenv('LDD_FTP_SCOPE') ?: null;

if ($scope === null && count($scopes) === 1) {
    $scope = array_key_first($scopes);
}

if ($scope === null || ($scopes !== [] && !isset($scopes[$scope]))) {
    fwrite(STDERR, sprintf(
        "ERREUR : %s\n         Passer --scope=NOM, ou définir LDD_FTP_SCOPE.\n%s",
        $scope === null
            ? 'préciser le serveur de destination.'
            : sprintf('scope git-ftp « %s » non configuré.', $scope),
        $scopes === [] ? '' : "\n         Scopes configurés :\n" . implode('', array_map(
            static fn (string $nom, string $url): string => sprintf("           %-10s %s\n", $nom, $url),
            array_keys($scopes),
            $scopes
        ))
    ));
    exit(1);
}

printf("\nDestination : %s (%s)\n", $scope, $scopes[$scope] ?? 'url inconnue');

$commande = 'git ftp push -s ' . escapeshellarg($scope);
echo "\n> {$commande}\n";
passthru($commande, $code);

if ($code === 0) {
    echo "\nDéployé. Vérifier :\n"
        . "  curl -I https://www.ladecadanse.ch/lausanne     (301 : .htaccess pris)\n"
        . "  la page d'erreur PHP du jour dans le journal    (.user.ini pris)\n";
}

exit($code);
