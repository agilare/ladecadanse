<?php

namespace Ladecadanse\Utils;

/**
 * Un lien web tel qu'il s'affiche sur le site : une URL brute saisie par un
 * contributeur, rendue en un libellé court, lisible, et si possible identifiable
 * par l'icône de sa plateforme.
 *
 * La classe ne connaît que ce qu'elle lit dans l'URL : aucune requête réseau,
 * aucune dépendance. Les plateformes listées dans PLATEFORMES couvrent ~28 %
 * des URL reçues (Instagram, SoundCloud, YouTube, Facebook, billetteries) ;
 * tout le reste passe par la règle générique, qui garde le domaine et le
 * segment de chemin le plus parlant.
 */
final class WebLink
{
    /** Longueur maximale du libellé affiché, ellipse comprise. */
    private const MAX_LABEL = 48;

    /** Segments de chemin qui n'apprennent rien au lecteur. */
    private const SEGMENTS_MUETS = [
        // langues
        'fr', 'en', 'de', 'it', 'es', 'fr-ch', 'de-ch', 'en-ch', 'it-ch', 'fr-fr', 'en-us',
        // pages techniques
        'index.php', 'index.html', 'home', 'accueil', 'page', 'pages', 'www',
        // mots-conteneurs : ils nomment une rubrique, pas un contenu
        'event', 'events', 'evenement', 'evenements', 'agenda', 'shop', 'boutique',
        'billetterie', 'ticket', 'tickets', 'spectacle', 'spectacles', 'activites',
        'article', 'articles', 'actualites', 'actualite', 'news', 'p', 'post', 'posts',
    ];

    /**
     * Ponctuation collée à la fin d'une URL dans une phrase : elle appartient
     * au texte, pas au lien. Les parenthèses et crochets ne sont retirés que
     * s'ils ne sont pas ouverts dans l'URL elle-même.
     */
    private const PONCTUATION_FINALE = ".,;:!?«»\"'’…";

    private function __construct(
        public readonly string $href,
        public readonly string $label,
        public readonly ?string $icon,
    ) {
    }

    /**
     * @param string $url URL brute, avec ou sans schéma (www.exemple.ch accepté)
     */
    public static function from(string $url): self
    {
        $url = self::trimPunctuation(trim($url));
        $href = self::absolutize($url);

        [$host, $path, $query] = self::split($href);

        if ($host === '')
        {
            return new self($href, $url, null);
        }

        foreach (self::plateformes() as $motif => $handler) {
            if (preg_match($motif, $host))
            {
                [$label, $icon] = $handler($host, $path, $query);

                return new self($href, self::ellipsis($label), $icon);
            }
        }

        if (preg_match('/\.pdf$/i', $path))
        {
            return new self($href, self::ellipsis(self::genericLabel($host, $path, gardeExtension: true)), 'fa-file-pdf-o');
        }

        return new self($href, self::ellipsis(self::genericLabel($host, $path)), null);
    }

    /**
     * Le lien complet, échappé, prêt à insérer dans la page.
     *
     * @param ?string $iconeParDefaut icône des liens dont la plateforme n'est pas reconnue
     */
    public static function html(string $url, ?string $iconeParDefaut = null): string
    {
        $link = self::from($url);
        $classe = $link->icon ?? $iconeParDefaut;

        $icon = $classe !== null
            ? '<i class="fa ' . $classe . '" aria-hidden="true"></i>&nbsp;'
            : '';

        return $icon . '<a href="' . sanitizeForHtml($link->href) . '" title="' . sanitizeForHtml($link->href) . '"'
            . ' rel="external" target="_blank">' . sanitizeForHtml($link->label) . '</a>';
    }

    /**
     * Les plateformes reconnues, du plus fréquent au plus rare dans les données.
     * Chaque règle reçoit hôte, chemin et query, et rend [libellé, icône].
     *
     * @return array<string, callable(string, string, string): array{0: string, 1: ?string}>
     */
    private static function plateformes(): array
    {
        return [
            '#(^|\.)instagram\.com$#' => function ($h, $p, $q) {
                $seg = self::segments($p);
                return match ($seg[0] ?? '') {
                    ''      => ['Instagram', 'fa-instagram'],
                    'p'     => ['Instagram · publication', 'fa-instagram'],
                    'reel', 'reels' => ['Instagram · reel', 'fa-instagram'],
                    'stories' => ['Instagram · story', 'fa-instagram'],
                    'explore' => ['Instagram · ' . self::humanize(end($seg)), 'fa-instagram'],
                    default => ['@' . $seg[0], 'fa-instagram'],
                };
            },

            '#(^|\.)soundcloud\.com$#' => function ($h, $p, $q) {
                $seg = self::segments($p);
                if ($seg === [])         return ['SoundCloud', 'fa-soundcloud'];
                if (($seg[1] ?? '') === 'sets') return [self::humanize($seg[0]) . ' · ' . self::humanize($seg[2] ?? 'playlist'), 'fa-soundcloud'];
                if (isset($seg[1]))      return [self::humanize($seg[0]) . ' · ' . self::humanize($seg[1]), 'fa-soundcloud'];
                return [self::humanize($seg[0]), 'fa-soundcloud'];
            },

            '#^youtu\.be$#' => fn ($h, $p, $q) => ['YouTube · vidéo', 'fa-youtube-play'],

            '#(^|\.)youtube\.com$#' => function ($h, $p, $q) {
                $seg = self::segments($p);
                return match ($seg[0] ?? '') {
                    'watch'   => ['YouTube · vidéo', 'fa-youtube-play'],
                    'shorts'  => ['YouTube · short', 'fa-youtube-play'],
                    'playlist' => ['YouTube · playlist', 'fa-youtube-play'],
                    'channel', 'user', 'c' => ['YouTube · ' . self::humanize($seg[1] ?? 'chaîne'), 'fa-youtube-play'],
                    ''        => ['YouTube', 'fa-youtube-play'],
                    default   => [str_starts_with($seg[0], '@') ? $seg[0] : 'YouTube · ' . self::humanize($seg[0]), 'fa-youtube-play'],
                };
            },

            '#(^|\.)(facebook\.com|fb\.me|fb\.com|fb\.watch)$#' => function ($h, $p, $q) {
                $seg = self::segments($p);
                return match ($seg[0] ?? '') {
                    ''            => ['Facebook', 'fa-facebook-official'],
                    'events'      => ['Facebook · événement', 'fa-facebook-official'],
                    'groups'      => ['Facebook · groupe', 'fa-facebook-official'],
                    'share', 'photo', 'permalink.php', 'story.php', 'posts', 'watch', 'reel'
                                  => ['Facebook · publication', 'fa-facebook-official'],
                    'profile.php' => ['Facebook · profil', 'fa-facebook-official'],
                    'pg', 'pages' => ['Facebook · ' . self::humanize($seg[1] ?? ''), 'fa-facebook-official'],
                    default       => ['Facebook · ' . self::humanize($seg[0]), 'fa-facebook-official'],
                };
            },

            '#\.bandcamp\.com$#' => function ($h, $p, $q) {
                $artiste = self::humanize(explode('.', $h)[0]);
                $seg = self::segments($p);
                if (in_array($seg[0] ?? '', ['album', 'track'], true) && isset($seg[1]))
                {
                    return [$artiste . ' · ' . self::humanize($seg[1]), 'fa-bandcamp'];
                }
                return [$artiste, 'fa-bandcamp'];
            },

            // Billetteries : le nom du site importe moins que ce qu'on y achète
            '#(^|\.)(infomaniak\.events|etickets\.infomaniak\.com)$#' => fn ($h, $p, $q)
                => [self::billetterie('Infomaniak', $p), 'fa-ticket'],

            '#(^|\.)weezevent\.com$#' => fn ($h, $p, $q)
                => [self::billetterie('Weezevent', $p), 'fa-ticket'],

            '#(^|\.)petzi\.ch$#' => fn ($h, $p, $q)
                => [self::billetterie('Petzi', $p), 'fa-ticket'],

            '#(^|\.)advance-ticket\.ch$#' => fn ($h, $p, $q)
                => [self::billetterie('Advance Ticket', $p), 'fa-ticket'],

            '#^(www\.)?(ra\.co|residentadvisor\.net)$#' => function ($h, $p, $q) {
                $seg = self::segments($p);
                return match ($seg[0] ?? '') {
                    'events' => ['Resident Advisor · événement', 'fa-calendar-o'],
                    'clubs'  => ['Resident Advisor · club', 'fa-calendar-o'],
                    default  => ['Resident Advisor', 'fa-calendar-o'],
                };
            },

            '#(^|\.)(eventbrite\.[a-z.]+|helloasso\.com|shotgun\.live|dice\.fm|ticketcorner\.ch|starticket\.ch|monbillet\.ch|see-tickets\.\w+)$#'
                => fn ($h, $p, $q) => [self::billetterie('', $p), 'fa-ticket'],

            '#(^|\.)openagenda\.com$#' => fn ($h, $p, $q)
                => ['OpenAgenda' . self::suffixe(self::meilleurSegment(self::segments($p))), 'fa-calendar-o'],

            '#(^|\.)(linktr\.ee|lnk\.bio|beacons\.ai|linkin\.bio)$#' => fn ($h, $p, $q)
                => [self::humanize(self::segments($p)[0] ?? 'liens'), 'fa-link'],

            '#(^|\.)vimeo\.com$#'    => fn ($h, $p, $q) => ['Vimeo · vidéo', 'fa-vimeo'],
            '#(^|\.)spotify\.com$#'  => fn ($h, $p, $q) => ['Spotify', 'fa-spotify'],
            '#(^|\.)mixcloud\.com$#' => fn ($h, $p, $q) => ['Mixcloud' . self::suffixe(self::segments($p)[0] ?? ''), 'fa-mixcloud'],
            '#(^|\.)twitch\.tv$#'    => fn ($h, $p, $q) => ['Twitch' . self::suffixe(self::segments($p)[0] ?? ''), 'fa-twitch'],
            '#(^|\.)meetup\.com$#'   => fn ($h, $p, $q) => ['Meetup' . self::suffixe(self::meilleurSegment(self::segments($p))), 'fa-meetup'],
            '#(^|\.)linkedin\.com$#' => fn ($h, $p, $q) => ['LinkedIn', 'fa-linkedin'],
            '#^(twitter\.com|x\.com|t\.co)$#' => fn ($h, $p, $q) => ['Twitter/X', 'fa-twitter'],
            '#(^|\.)tiktok\.com$#'   => fn ($h, $p, $q) => ['TikTok' . self::suffixe(self::segments($p)[0] ?? ''), 'fa-music'],
            '#(^|\.)(wikipedia\.org)$#' => fn ($h, $p, $q) => ['Wikipédia · ' . self::humanize(basename($p)), 'fa-book'],
            '#(^|\.)(maps\.google\.\w+|goo\.gl)$#' => fn ($h, $p, $q) => ['Plan Google Maps', 'fa-map-marker'],
        ];
    }

    /**
     * Domaine + le segment de chemin le plus parlant, les autres élidés.
     * « petzi.ch/fr/events/60854-ptr-usine-zion-train » -> « petzi.ch/…/ptr-usine-zion-train »
     */
    private static function genericLabel(string $host, string $path, bool $gardeExtension = false): string
    {
        $host = preg_replace('/^www\d?\./', '', $host);
        $segments = self::segments($path);
        $choisi = $gardeExtension ? (string) end($segments) : self::meilleurSegment($segments);

        if ($choisi === '')
        {
            return $host;
        }

        $elision = count($segments) > 1 && $segments[0] !== $choisi ? '/…/' : '/';

        return $host . $elision . self::humanize($choisi);
    }

    /**
     * Le segment qui apprend quelque chose : on écarte les codes de langue,
     * les segments purement numériques et les identifiants opaques, puis on
     * garde le dernier restant — c'est celui qui nomme la page.
     */
    private static function meilleurSegment(array $segments): string
    {
        $utiles = array_values(array_filter($segments, static function (string $s): bool {
            $s = self::humanize($s);
            if (in_array(mb_strtolower($s), self::SEGMENTS_MUETS, true)) return false;
            if (preg_match('/^\d+$/', $s))                               return false;
            return !self::estOpaque($s);
        }));

        return $utiles === [] ? '' : (string) end($utiles);
    }

    /**
     * Un identifiant opaque : ni voyelle, ou casse mêlée façon jeton aléatoire
     * (« 7Zoti61usM69SeplfaQpw91xk595 », « aaCtwBWA8f », « DcjKmaTDLBq »).
     */
    private static function estOpaque(string $s): bool
    {
        $s = preg_replace('/\.(html?|php|aspx?)$/i', '', $s);

        if (mb_strlen($s) < 6)
        {
            return (bool) preg_match('/^[a-z0-9]{5}$/i', $s) && !preg_match('/[aeiouy]/i', $s);
        }
        if (str_contains($s, '-') || str_contains($s, '_'))
        {
            return false; // un slug lisible garde ses tirets
        }
        // beaucoup de chiffres, ou majuscules et minuscules mêlées sans mot reconnaissable
        $chiffres = preg_match_all('/\d/', $s);
        $melange  = preg_match('/[a-z]/', $s) && preg_match('/[A-Z]/', $s);

        return $chiffres >= 3 || ($melange && $chiffres >= 1) || !preg_match('/[aeiouy]/i', $s);
    }

    /** Rend un segment lisible : décodage %20, extension et numéro d'ordre retirés. */
    private static function humanize(string $segment): string
    {
        $segment = rawurldecode($segment);
        $segment = preg_replace('/\.(html?|php|aspx?)$/i', '', $segment);
        $segment = preg_replace('/^\d{3,}[-_]/', '', $segment); // « 60854-ptr-usine » -> « ptr-usine »
        $segment = self::sansJetonFinal($segment);                // « la-jonquille-QX44V19DH7 » -> « la-jonquille »

        return trim(str_replace('_', ' ', $segment), '- ');
    }

    /**
     * « Billetterie · nom-du-spectacle » quand l'URL nomme quelque chose,
     * « Billetterie Infomaniak » quand elle ne porte qu'un identifiant.
     */
    private static function billetterie(string $marque, string $path): string
    {
        $segment = self::meilleurSegment(self::segments($path));

        if ($segment !== '')
        {
            return 'Billetterie' . self::suffixe($segment);
        }

        return trim('Billetterie ' . $marque);
    }

    /**
     * Retire l'identifiant que certaines plateformes accolent au slug, quand il
     * reste quelque chose de lisible devant.
     */
    private static function sansJetonFinal(string $segment): string
    {
        $mots = explode('-', $segment);

        if (count($mots) > 1 && self::estOpaque((string) end($mots)))
        {
            array_pop($mots);

            return implode('-', $mots);
        }

        return $segment;
    }

    private static function suffixe(string $segment): string
    {
        $segment = self::humanize($segment);

        return $segment === '' ? '' : ' · ' . $segment;
    }

    /** @return list<string> */
    private static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    }

    private static function absolutize(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : 'https://' . $url;
    }

    /** @return array{0: string, 1: string, 2: string} hôte, chemin, query */
    private static function split(string $url): array
    {
        $parts = parse_url($url);

        return [
            mb_strtolower($parts['host'] ?? ''),
            $parts['path'] ?? '',
            $parts['query'] ?? '',
        ];
    }

    /**
     * Retire la ponctuation de phrase collée à la fin de l'URL, en gardant les
     * parenthèses et crochets qui appartiennent réellement à l'adresse
     * (Wikipédia, Facebook…).
     */
    public static function trimPunctuation(string $url): string
    {
        // « [titre](https://…) » : une URL collée à une syntaxe Markdown
        if (($md = mb_strpos($url, '](')) !== false)
        {
            $url = mb_substr($url, 0, $md);
        }

        while ($url !== '')
        {
            $last = mb_substr($url, -1);

            if (mb_strpos(self::PONCTUATION_FINALE, $last) !== false)
            {
                $url = mb_substr($url, 0, -1);
                continue;
            }
            if ($last === ')' && substr_count($url, ')') > substr_count($url, '('))
            {
                $url = mb_substr($url, 0, -1);
                continue;
            }
            if ($last === ']' && substr_count($url, ']') > substr_count($url, '['))
            {
                $url = mb_substr($url, 0, -1);
                continue;
            }
            break;
        }

        return $url;
    }

    /**
     * Ne raccourcit que la dernière composante du libellé — le domaine et la
     * plateforme restent lisibles — et coupe de préférence sur un tiret, pour
     * s'arrêter entre deux mots plutôt qu'au milieu de l'un d'eux.
     */
    private static function ellipsis(string $label): string
    {
        if (mb_strlen($label) <= self::MAX_LABEL)
        {
            return $label;
        }

        $coupe = max((int) mb_strrpos($label, '/'), (int) mb_strrpos($label, '·'));
        $prefixe = $coupe > 0 ? mb_substr($label, 0, $coupe + 1) : '';
        $queue = mb_substr($label, mb_strlen($prefixe));

        $place = self::MAX_LABEL - mb_strlen($prefixe) - 1;

        if ($place < 12) // le préfixe mange tout : on rabote le libellé entier
        {
            return mb_substr($label, 0, self::MAX_LABEL - 1) . '…';
        }

        $tronque = mb_substr($queue, 0, $place);
        $surTiret = mb_strrpos($tronque, '-');

        if ($surTiret !== false && $surTiret > $place * 0.5)
        {
            $tronque = mb_substr($tronque, 0, $surTiret);
        }

        return $prefixe . rtrim($tronque, '-_ ') . '…';
    }
}
