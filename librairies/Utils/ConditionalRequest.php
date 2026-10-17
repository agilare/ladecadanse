<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

final class ConditionalRequest
{
    // l'ETag prime sur la date (RFC 9110)
    public static function isNotModified(string $etag, ?int $lastModified, string $ifNoneMatch, string $ifModifiedSince): bool
    {
        if (trim($ifNoneMatch) !== '')
        {
            return self::etagMatches($etag, $ifNoneMatch);
        }

        if ($lastModified === null || $ifModifiedSince === '')
        {
            return false;
        }

        $since = strtotime($ifModifiedSince);

        return $since !== false && $since >= $lastModified;
    }

    private static function etagMatches(string $etag, string $ifNoneMatch): bool
    {
        $bare = trim($etag, '"');

        foreach (explode(',', $ifNoneMatch) as $candidate)
        {
            // mod_deflate suffixe l'ETag des réponses compressées par « -gzip »
            $candidate = (string) preg_replace('/^W\/"?|-(gzip|br)$/', '', trim(trim($candidate), '"'));

            if ($candidate === $bare)
            {
                return true;
            }
        }

        return false;
    }
}
