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

        // SSRF : refuse private/reserved IP addresses on initial hostname
        $ip = gethostbyname($host);
        if (
            !filter_var($ip, FILTER_VALIDATE_IP) ||
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        ) {
            return ['data' => null, 'mime' => null, 'error' => "Cette URL n'est pas autorisée"];
        }

        $body = '';
        $abortedTooBig = false;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => ['Accept: image/*'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'ladecadanse/1.0',
        ]);

        // Accumulate response body, abort if too large
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$body, &$abortedTooBig) {
            $body .= $chunk;
            if (strlen($body) > self::MAX_BYTES) {
                $abortedTooBig = true;
                return 0; // triggers CURLE_WRITE_ERROR and stops the transfer
            }
            return strlen($chunk);
        });

        curl_exec($ch);
        $errno    = curl_errno($ch);
        $finalIp  = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($abortedTooBig) {
            return ['data' => null, 'mime' => null, 'error' => "L'image est trop grande (max 10 Mo)"];
        }

        // CURLE_OK = 0, CURLE_WRITE_ERROR = 23 (our intentional abort already caught above)
        if ($errno !== CURLE_OK) {
            return ['data' => null, 'mime' => null, 'error' => "Impossible d'accéder à cette URL"];
        }

        // SSRF : also check the final IP after redirects
        if (
            $finalIp !== '' && (
                !filter_var($finalIp, FILTER_VALIDATE_IP) ||
                !filter_var($finalIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            )
        ) {
            return ['data' => null, 'mime' => null, 'error' => "Cette URL n'est pas autorisée"];
        }

        $imgInfo = @getimagesizefromstring($body);
        if ($imgInfo === false) {
            return ['data' => null, 'mime' => null, 'error' => "Le contenu de cette URL n'est pas une image reconnue"];
        }

        $mime = $imgInfo['mime'] ?? '';
        if (!in_array($mime, $allowedMimes, true)) {
            return ['data' => null, 'mime' => null, 'error' => "Ce format d'image n'est pas accepté ($mime)"];
        }

        return ['data' => $body, 'mime' => $mime, 'error' => null];
    }
}
