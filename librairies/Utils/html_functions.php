<?php
// Pas de declare(strict_types=1) : la fonction est appelée sur des centaines de sites
// avec des int et des valeurs de tableaux SQL non typées, que le mode strict refuserait.

/**
 * Échappe une valeur destinée à être affichée dans du HTML.
 *
 * Reste une fonction globale (et non une méthode de classe) tant que la classe
 * Renderer prévue par l'issue #127 n'existe pas : elle compte plus de 300 appels.
 *
 * @param ?string $chaine dirty
 * @return string clean
 * @psalm-taint-escape html
 * @psalm-taint-escape has_quotes
 */
function sanitizeForHtml(?string $chaine): string
{
    // ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 is the default value since php 8.1
    return trim(htmlspecialchars((string) $chaine, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
}
