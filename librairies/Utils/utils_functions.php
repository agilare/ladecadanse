<?php
//declare(strict_types=1);

/** @deprecated Use \Ladecadanse\Utils\DateHelper::isoToFr() instead */
function date_fr($d, $format = "", $affMois = "", $jour_sem = "", $html = true): string
{
    return \Ladecadanse\Utils\DateHelper::isoToFr(
        (string) $d,
        (string) $format,
        $affMois === '',
        $jour_sem === '',
        (bool) $html
    );
}

/** @deprecated Use \Ladecadanse\Utils\DateHelper::monthName() instead */
function mois2fr($mois): string
{
    return \Ladecadanse\Utils\DateHelper::monthName((int) $mois);
}

/** @deprecated Use \Ladecadanse\Utils\DateHelper::isoToDayName() instead */
function date2nomJour($date): string
{
    return \Ladecadanse\Utils\DateHelper::isoToDayName((string) $date);
}


/** @deprecated Use \Ladecadanse\Utils\DateHelper::isoToApp() instead */
function date_iso2app($date): string
{
    return \Ladecadanse\Utils\DateHelper::isoToApp($date !== null ? (string) $date : null);
}

/** @deprecated Use \Ladecadanse\Utils\DateHelper::appToIso() instead */
function date_app2iso($date): string
{
    return \Ladecadanse\Utils\DateHelper::appToIso((string) $date);
}

/** @deprecated Use \Ladecadanse\Utils\DateHelper::isoToNextDay() instead */
function dateIsoToNextDayDateIso(string $date): string
{
    return \Ladecadanse\Utils\DateHelper::isoToNextDay($date);
}

/**
 * FIXME: mv to Text class
 * @param ?string $chaine dirty
 * @return string clean
 */
function sanitizeForHtml(?string $chaine): string
{
    // ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 is the default value since php 8.1
    return trim(htmlspecialchars((string) $chaine, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
}
