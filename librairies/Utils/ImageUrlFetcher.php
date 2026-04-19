<?php

namespace Ladecadanse\Utils;

/**
 * Fetches an image from a remote URL with security validations (SSRF, size, MIME).
 */
class ImageUrlFetcher
{
    private const MAX_BYTES = 10_485_760; // 10 Mo
    private const TIMEOUT = 10;

    /**
     * @param string[] $allowedMimes
     * @return array{data: string|null, mime: string|null, error: string|null}
     */
    public static function fetch(string $url, array $allowedMimes): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            return ['data' => null, 'mime' => null, 'error' => "L'URL doit commencer par http:// ou https://"];
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return ['data' => null, 'mime' => null, 'error' => "L'URL n'est pas valide"];
        }

        // SSRF : refuse private/reserved IP addresses
        $ip = gethostbyname($host);
        if (
            !filter_var($ip, FILTER_VALIDATE_IP) ||
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        ) {
            return ['data' => null, 'mime' => null, 'error' => "Cette URL n'est pas autorisée"];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header' => "Accept: image/*\r\n",
                'ignore_errors' => false,
            ],
        ]);

        $data = @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);

        if ($data === false) {
            return ['data' => null, 'mime' => null, 'error' => "Impossible d'accéder à cette URL (connexion refusée ou timeout)"];
        }

        if (strlen($data) > self::MAX_BYTES) {
            return ['data' => null, 'mime' => null, 'error' => "L'image est trop grande (max 10 Mo)"];
        }

        $imgInfo = @getimagesizefromstring($data);
        if ($imgInfo === false) {
            return ['data' => null, 'mime' => null, 'error' => "Le contenu de cette URL n'est pas une image reconnue"];
        }

        $mime = $imgInfo['mime'] ?? '';
        if (!in_array($mime, $allowedMimes, true)) {
            return ['data' => null, 'mime' => null, 'error' => "Ce format d'image n'est pas accepté ($mime)"];
        }

        return ['data' => $data, 'mime' => $mime, 'error' => null];
    }
}
