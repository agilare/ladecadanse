<?php

declare(strict_types=1);

/**
 * Compose le .htaccess de la racine à partir de ses fragments.
 *
 * Les fragments publics sont dans htaccess/ ; ceux qui portent la configuration
 * d'exploitation (adresses bannies, robots, domaine canonique) vivent dans le
 * dépôt privé ladecadanse-docs et ne doivent jamais entrer dans ce dépôt-ci.
 * L'ordre de composition est donné par le préfixe numérique des noms de
 * fichiers, toutes provenances confondues.
 *
 * Usage :
 *   php bin/htaccess.php [--ops-dir=CHEMIN] [--require-ops]
 *   php bin/htaccess.php --deploy --scope=NOM
 *
 * Voir docs/htaccess.md.
 */

const OPS_DIR_DEFAUT = '../ladecadanse-docs/htaccess';

$racine = dirname(__DIR__);
$options = getopt('', ['ops-dir::', 'require-ops', 'deploy', 'scope::', 'out::']);

$opsDir = $options['ops-dir'] ?? getenv('LDD_OPS_DIR') ?: OPS_DIR_DEFAUT;
if (!str_starts_with($opsDir, '/') && !preg_match('#^[A-Za-z]:#', $opsDir)) {
    $opsDir = $racine . '/' . $opsDir;
}
$sortie = $options['out'] ?? $racine . '/.htaccess';
$deploiement = isset($options['deploy']);
$opsObligatoires = $deploiement || isset($options['require-ops']);

$publics = glob($racine . '/htaccess/*.conf') ?: [];
$ops = is_dir($opsDir) ? (glob($opsDir . '/*.conf') ?: []) : [];

if ($publics === []) {
    fwrite(STDERR, "ERREUR : aucun fragment dans htaccess/. Dépôt incomplet ?\n");
    exit(1);
}

if ($ops === [] && $opsObligatoires) {
    fwrite(STDERR, <<<TXT
        ERREUR : fragments d'exploitation introuvables dans
                 {$opsDir}
                 Déployer sans eux retirerait de la production ses blocages.
                 Cloner ladecadanse-docs, ou passer --ops-dir=CHEMIN.

        TXT);
    exit(1);
}

// Le préfixe numérique fait foi, quel que soit le répertoire d'origine
$fragments = array_merge($publics, $ops);
usort($fragments, static fn (string $a, string $b): int => strcmp(basename($a), basename($b)));

// Les fragments d'exploitation sont désignés par « ops/ » et non par leur
// chemin réel : celui-ci varie d'une machine à l'autre, et le fichier composé
// part en production
$entete = "# Composé par « composer htaccess » depuis :\n";
foreach ($fragments as $fragment) {
    $chemin = str_starts_with($fragment, $racine)
        ? str_replace(DIRECTORY_SEPARATOR, '/', substr($fragment, strlen($racine) + 1))
        : 'ops/' . basename($fragment);
    $entete .= '#   ' . $chemin . "\n";
}

$contenu = $entete;
foreach ($fragments as $fragment) {
    // Apache sous Linux refuse certaines directives dont l'argument traîne un
    // retour chariot : la sortie est normalisée en LF quoi qu'il arrive
    $contenu .= str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($fragment));
}

if (file_put_contents($sortie, $contenu) === false) {
    fwrite(STDERR, "ERREUR : écriture impossible dans {$sortie}\n");
    exit(1);
}

printf(
    "%s composé : %d fragments, %d lignes%s\n",
    basename($sortie),
    count($fragments),
    substr_count($contenu, "\n"),
    $ops === [] ? ' (socle public seul, sans configuration d\'exploitation)' : ''
);

if (!$deploiement) {
    exit(0);
}

// Le scope désigne le serveur de destination. Il n'a pas de valeur par défaut :
// plusieurs peuvent être configurés côte à côte, et deviner lequel vise la
// production reviendrait à parier sur la bonne machine.
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
    echo "\nDéployé. Vérifier : curl -I https://www.ladecadanse.ch/lausanne\n";
}

exit($code);
