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