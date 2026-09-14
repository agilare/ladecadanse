<?php

declare(strict_types=1);

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2026 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 *
 * Vérifie que la configuration de teinte fait ce qu'elle prétend.
 *
 * Lance Psalm en mode teinte sur .psalm/banc-de-controle.php.txt, lit les marqueurs
 * « // @attendu: signale » et « // @attendu: muet » posés en fin de ligne dans le banc,
 * et compare ligne à ligne. Les numéros de ligne ne sont écrits nulle part : déplacer
 * un cas dans le banc ne casse pas la vérification.
 *
 * Vérifie en plus que les deux sinks du dépôt ont bien parlé — les appels qui passent
 * par $connector et $connectorPdo sont signalés là où se trouve le sink, pas là où on
 * les appelle, et le banc ne pourrait pas les attraper par un marqueur.
 *
 *     composer psalm:banc
 *
 * Sort 0 si tout concorde, 1 sinon.
 */

const BANC = '.psalm/banc-de-controle.php.txt';
const CONFIG = '.psalm/banc.xml';
const SINKS_ATTENDUS = [
    'librairies/Utils/DbConnector.php'    => 'mysqli_query(), atteint via $connector->query()',
    'librairies/Utils/DbConnectorPdo.php' => 'PDO::prepare(), atteint via $connectorPdo->prepare()',
];

$racine = dirname(__DIR__);
chdir($racine);

if (!is_file(BANC))
{
    fwrite(STDERR, "Banc introuvable : " . BANC . " (lancer depuis la racine du dépôt)\n");
    exit(1);
}

// --- attendus, lus dans le banc lui-même -------------------------------------

$attendus = [];
foreach (file(BANC, FILE_IGNORE_NEW_LINES) as $i => $ligne)
{
    if (preg_match('/\/\/ @attendu:\s*(signale|muet)\s*$/', $ligne, $m))
    {
        $attendus[$i + 1] = ['doitParler' => $m[1] === 'signale', 'code' => trim($ligne)];
    }
}

if ($attendus === [])
{
    fwrite(STDERR, "Aucun marqueur @attendu dans " . BANC . "\n");
    exit(1);
}

// --- analyse -----------------------------------------------------------------

$rapport = tempnam(sys_get_temp_dir(), 'psalm-banc') . '.json';

$commande = sprintf(
    '%s %s --taint-analysis --config=%s --no-cache --no-progress --report=%s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg('vendor/bin/psalm'),
    escapeshellarg(CONFIG),
    escapeshellarg($rapport),
);

exec($commande . ' 2>&1', $sortie, $code);

if (!is_file($rapport))
{
    fwrite(STDERR, "Psalm n'a pas produit de rapport (code $code) :\n" . implode("\n", $sortie) . "\n");
    exit(1);
}

/** @var list<array{type: string, file_name: string, line_from: int}> $signalements */
$signalements = json_decode((string) file_get_contents($rapport), true, 512, JSON_THROW_ON_ERROR);
unlink($rapport);

$parLigneDuBanc = [];
$fichiersSignales = [];
foreach ($signalements as $s)
{
    $fichier = str_replace('\\', '/', $s['file_name']);
    $fichiersSignales[$fichier][] = $s['type'];

    if ($fichier === BANC)
    {
        $parLigneDuBanc[$s['line_from']][$s['type']] = true;
    }
}

// --- comparaison -------------------------------------------------------------

$ecarts = 0;

foreach ($attendus as $ligne => $cas)
{
    $types = array_keys($parLigneDuBanc[$ligne] ?? []);
    $parle = $types !== [];
    $conforme = $parle === $cas['doitParler'];
    $ecarts += $conforme ? 0 : 1;

    printf(
        "%s  L%-4d %-9s %s\n",
        $conforme ? ' ok ' : ' KO ',
        $ligne,
        $parle ? implode('+', $types) : 'muet',
        mb_substr(preg_replace('/\s*\/\/ @attendu:.*$/', '', $cas['code']) ?? '', 0, 78),
    );
}

echo "\n";

foreach (SINKS_ATTENDUS as $fichier => $quoi)
{
    $parle = isset($fichiersSignales[$fichier]);
    $ecarts += $parle ? 0 : 1;
    printf("%s  sink %-38s %s\n", $parle ? ' ok ' : ' KO ', $fichier, $quoi);
}

echo "\n";

if ($ecarts > 0)
{
    printf("%d écart(s) : la configuration ne fait plus ce que le banc décrit.\n", $ecarts);
    exit(1);
}

printf("Banc conforme : %d cas et %d sinks vérifiés.\n", count($attendus), count(SINKS_ATTENDUS));
exit(0);
