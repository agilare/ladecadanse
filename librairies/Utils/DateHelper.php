<?php

namespace Ladecadanse\Utils;

use DateTime;

/**
 * Date utility helpers for the application.
 *
 * Supported formats:
 *   - ISO      : YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
 *   - App      : DD.MM.YYYY or HH:MM:SS DD.MM.YYYY
 *   - RFC 2822 : for RSS feeds
 */
class DateHelper
{
    // -------------------------------------------------------------------------
    // Component extraction
    // -------------------------------------------------------------------------

    /**
     * Returns the day of the month without leading zero.
     *
     * @param string $date ISO date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @return string e.g. '7' or '23'
     */
    public static function day(string $date): string
    {
        return (new DateTime($date))->format('j');
    }

    /**
     * Returns the month with leading zero.
     *
     * @param string $date ISO date (YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS)
     * @return string e.g. '04'
     */
    public static function month(string $date): string
    {
        return (new DateTime($date))->format('m');
    }

    /**
     * Returns the four-digit year.
     *
     * @param string $date ISO date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @return string e.g. '2026'
     */
    public static function year(string $date): string
    {
        return (new DateTime($date))->format('Y');
    }

    // -------------------------------------------------------------------------
    // Format conversions
    // -------------------------------------------------------------------------

    /**
     * Converts an ISO date to the application display format.
     *
     * @param string|null $date ISO date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS), or null/empty → ''
     * @return string e.g. '23.04.2026' or '09:35:01 23.04.2026', '' if empty/null
     */
    public static function isoToApp(?string $date): string
    {
        if (empty($date)) return '';

        $format = strlen($date) > 10 ? 'H:i:s d.m.Y' : 'd.m.Y';
        return (new DateTime($date))->format($format);
    }

    /**
     * Converts an application display date to ISO format.
     * Strings longer than 10 characters are assumed to be ISO datetimes
     * and are returned as-is.
     *
     * @param string $date App date (DD.MM.YYYY)
     * @return string e.g. '2026-04-23', '' if invalid or empty
     */
    public static function appToIso(string $date): string
    {
        if (empty($date) || strlen($date) > 10) return $date;

        $dt = DateTime::createFromFormat('d.m.Y', $date);
        return $dt ? $dt->format('Y-m-d') : '';
    }

    /**
     * Returns the ISO date of the day following the given ISO date.
     *
     * @param string $date ISO date (YYYY-MM-DD)
     * @return string e.g. '2026-04-24' for input '2026-04-23'
     */
    public static function isoToNextDay(string $date): string
    {
        return (new DateTime($date))->modify('+1 day')->format('Y-m-d');
    }

    // -------------------------------------------------------------------------
    // French date display
    // -------------------------------------------------------------------------

    /**
     * Returns the French day-of-week name from an ISO date.
     *
     * @param string $date ISO date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @return string e.g. 'jeudi'
     */
    public static function isoToDayName(string $date): string
    {
        static $names = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        return $names[(int)(new DateTime($date))->format('w')];
    }

    /**
     * Returns the French month name from a month number.
     *
     * @param int $month Month number (1–12)
     * @return string e.g. 'avril'
     */
    public static function monthName(int $month): string
    {
        static $names = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                         'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return $names[$month] ?? '';
    }

    /**
     * Converts an ISO date to a French long-form string.
     *
     * @param string $date          ISO date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @param string $format        '' (default) | 'tout' (append time) | 'heure' (time only) | 'annee' (append year)
     * @param bool   $showMonth     Include the French month name (default true)
     * @param bool   $showDayOfWeek Include the French day-of-week name (default true)
     * @param bool   $html          Use &nbsp; and <sup>er</sup> instead of plain text (default true)
     * @return string e.g. 'jeudi 23&nbsp;avril' or '23 avril 2026'
     */
    public static function isoToFr(
        string $date,
        string $format = '',
        bool $showMonth = true,
        bool $showDayOfWeek = true,
        bool $html = true
    ): string {
        static $dayNames   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        static $monthNames = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                              'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        $dt      = new DateTime($date);
        $hasTime = strlen($date) > 10;

        if ($format === 'heure') {
            return $hasTime ? ' ' . $dt->format('H') . 'h' . $dt->format('i') : '';
        }

        $day     = (int) $dt->format('j');
        $ordinal = $day === 1 ? ($html ? '<sup>er</sup>' : 'er') : '';
        $sep     = $html ? '&nbsp;' : ' ';
        $month   = $showMonth ? $monthNames[(int) $dt->format('n')] : '';
        $suffix  = match ($format) {
            'tout'  => $hasTime ? ' ' . $dt->format('H') . 'h' . $dt->format('i') : '',
            'annee' => ' ' . $dt->format('Y'),
            default => '',
        };

        $result = $day . $ordinal . $sep . $month . $suffix;

        return $showDayOfWeek
            ? $dayNames[(int) $dt->format('w')] . ' ' . $result
            : $result;
    }

    /**
     * Converts an ISO date to RFC 2822 format, for use in RSS <pubDate> tags.
     *
     * @param string $date ISO date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @return string e.g. 'Thu, 23 Apr 2026 09:35:01 +0200'
     */
    public static function isoToRfc2822(string $date): string
    {
        return (new DateTime($date))->format(DateTime::RFC2822);
    }
}