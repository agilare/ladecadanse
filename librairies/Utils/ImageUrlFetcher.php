<?php

namespace Ladecadanse\Utils;

/**
 * Fetches an image from a remote URL with security validations (SSRF, size, MIME).
 */
class ImageUrlFetcher
{
    private const MAX_BYTES = 10_485_760; // 10 Mo
    private const TIMEOUT = 10; // seconds, for the whole chain of redirects
    private const MAX_REDIRECTS = 3;

    /**
     * Checks one URL of the chain and returns the curl "host:port:address" entry that pins
     * the connection to the address checked here.
     *
     * @return array{pin: string, error: string|null}
     */
    private static function resolveTarget(string $url): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            return ['pin' => '', 'error' => "L'URL doit commencer par http:// ou https://"];
        }

        $parts = parse_url($url);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if ($host === '') {
            return ['pin' => '', 'error' => "L'URL n'est pas valide"];
        }

        // SSRF : refuse private/reserved IP addresses. IPv4 only, as gethostbyname() is:
        // an IPv6 literal comes back unchanged, brackets included, and fails the filter.
        $ip = gethostbyname($host);
        if (
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        ) {
            return ['pin' => '', 'error' => "Cette URL n'est pas autorisée"];
        }

        $port = (int) ($parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80));

        return ['pin' => $host . ':' . $port . ':' . $ip, 'error' => null];
    }

    /**
     * @param string[] $allowedMimes
     * @return array{data: string|null, mime: string|null, error: string|null}
     */
    public static function fetch(string $url, array $allowedMimes): array
    {
        $body = '';
        $abortedTooBig = false;
        $deadline = microtime(true) + self::TIMEOUT;

        // Redirects are followed by hand: every hop goes through the same checks as the first
        // URL, and the connection is pinned to the address that passed them. Left to curl, the
        // name was resolved a second time (DNS rebinding) and a redirect target was reached
        // before any check could run — a blind request to an internal address.
        for ($hop = 0; ; $hop++) {
            $target = self::resolveTarget($url);
            if ($target['error'] !== null) {
                return ['data' => null, 'mime' => null, 'error' => $target['error']];
            }

            $body = '';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_TIMEOUT        => max(1, (int) ceil($deadline - microtime(true))),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => [$target['pin']],
                CURLOPT_HTTPHEADER     => ['Accept: image/*,application/pdf'],
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
            $errno       = curl_errno($ch);
            $status      = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $redirectUrl = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($abortedTooBig) {
                return ['data' => null, 'mime' => null, 'error' => "L'image est trop grande (max 10 Mo)"];
            }

            // CURLE_OK = 0, CURLE_WRITE_ERROR = 23 (our intentional abort already caught above)
            if ($errno !== CURLE_OK) {
                return ['data' => null, 'mime' => null, 'error' => "Impossible d'accéder à cette URL"];
            }

            if ($status < 300 || $status >= 400 || $redirectUrl === '') {
                break;
            }

            if ($hop >= self::MAX_REDIRECTS || microtime(true) >= $deadline) {
                return ['data' => null, 'mime' => null, 'error' => "Impossible d'accéder à cette URL"];
            }

            // absolute URL, already resolved by curl against the current one
            $url = $redirectUrl;
        }

        // Le PDF se reconnaît à ses octets, getimagesizefromstring() n'en sachant
        // rien. Il n'est accepté que si l'appelant l'a explicitement autorisé :
        // sa conversion demande Imagick, absent de certains environnements.
        if (PdfToImage::estUnPdf($body)) {
            if (!in_array('application/pdf', $allowedMimes, true)) {
                return ['data' => null, 'mime' => null, 'error' => "Les PDF ne sont pas acceptés par URL. Envoyez le fichier avec le bouton « Envoyer »."];
            }

            return ['data' => $body, 'mime' => 'application/pdf', 'error' => null];
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
