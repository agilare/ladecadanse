<?php

namespace Ladecadanse\Utils;

/**
 * Manipulations de texte, dont plusieurs produisent du HTML.
 *
 * Ces dernières s'appuient sur la fonction globale sanitizeForHtml()
 * (librairies/Utils/html_functions.php), résolue par le fallback global de
 * PHP sur les fonctions. Ce couplage disparaîtra avec la classe Renderer
 * prévue par l'issue #127.
 */
class Text
{
    /**
     * Uniquement pour dériver des noms HTML (id, class...) de mots français.
     */
    public static function stripAccents(string $str): string
    {
        // ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 is the default value since php 8.1
        $str = htmlentities($str, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML401, "UTF-8");
        $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1', $str);
        return html_entity_decode((string) $str, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML401, "UTF-8");
    }

    /**
     * Transforme URLs et adresses e-mail en liens, en laissant intactes les
     * balises HTML déjà présentes.
     *
     * Uniquement utilisée par event/evenement.php pour les prélocations.
     * Échappe elle-même les liens qu'elle produit via sanitizeForHtml().
     */
    public static function linkify(string $input): string
    {
        $re = <<<'REGEX'
    !
        (
          <\w++
          (?:
            \s++
          | [^"'<>]++
          | "[^"]*+"
          | '[^']*+'
          )*+
          >
        )
        |
        (\b https?://[^\s"'<>]++ )
        |
        (\b www\d*+\.\w++[^\s"'<>]++ )
        |
        (\b [^\s"'<>,]+@[^\s"'<>,]+\.[^\s"'<>,]+ )
    !xi
    REGEX;

        return preg_replace_callback($re, function ($m) {

            if ($m[1])
                return $m[1];

            $url = '';
            $text = "lien";

            if ($m[2])
            {
                $url = $m[2];
                $text = $m[2];
            }
            else if ($m[3])
            {
                $url = "http://$m[3]";
                $text = $m[3];
            }
            else if ($m[4])
            {
                $url = "mailto:$m[4]";
                $text = $m[4];
            }

            return "<a href='" . sanitizeForHtml($url) . "' rel='external'>" . sanitizeForHtml($text) . "</a>";
        }, $input);
    }

    /**
     * Sépare une URL de son libellé d'affichage, en complétant le schéma si
     * l'entrée n'en a pas.
     *
     * @param string $urlOrPath https://www.test.ch ou www.test.ch
     * @return array{url: string, urlName: string} ['https://www.test.ch', 'www.test.ch']
     */
    public static function getUrlWithName(string $urlOrPath): array
    {
        $urlComplete = $urlOrPath;
        if (!preg_match("/^https?:\/\//", $urlOrPath))
        {
            $urlComplete = 'http://' . $urlOrPath;
        }

        return ['url' => $urlComplete, 'urlName' => rtrim(preg_replace("(^https?://)", "", $urlOrPath), "/")];
    }



    /**
     * Convertit en HTML les seules conventions de saisie encore reconnues :
     * saut de ligne -> <br />, URL nue ou www. -> <a href>, et la forme
     * [http://exemple.ch libellé] -> lien avec libellé.
     *
     * Le reste de l'ancienne syntaxe wiki (==titre==, '''gras''', ''italique'',
     * ---- ) n'est plus interprété depuis longtemps.
     *
     * Attend du texte DÉJÀ échappé : la méthode produit du HTML sans échapper
     * son entrée.
     *
     * @param  string $temp Texte échappé
     * @return string Texte avec balises HTML
     */
    public static function lnAndUrlToHtml(string $temp): string
    {
        if (empty($temp))
        {
            return "";
        }

        $temp = preg_replace("/([^*]{2}|)\n/", "\\1<br />", $temp);

        $temp = preg_replace("/(([^[]|^)(http)+(s)?:(\/\/)|([^\[\/]|^)(www\.))((\w|\.|\-|_)+)(\/)?(\S+)?/i",
            "\\2\\6<a href=\"http\\4://\\7\\8\\10\\11\" title=\"\\0\">\\7\\8</a>", (string) $temp);
        //[
        $temp = preg_replace("/\[(http[s]?:\/\/)([-a-z0-9_]{2,}\.[-a-z0-9.]{2,}[-a-z0-9\/&\?=.;~_%]*) (.+?)\]/i",
                "<a href=\"\\1\\2\" title=\"\\1\\2\">\\3</a>", (string) $temp);

        $temp = preg_replace("/\[www\.([-a-z0-9.]{2,}[-a-z0-9\/&\?=.~_%]*) (.+?)\]/i",
                "<a href=\"http://www.\\1\" title=\"www.\\1\">\\2</a>", (string) $temp);

        return $temp;
    }

    /**
     * Tronque un texte brut à $maxChars caractères sans couper le dernier mot.
     *
     * Ne renvoie pas de HTML, et c'est le point : la troncature doit précéder
     * l'échappement. Compter les caractères d'un texte déjà échappé revient à
     * facturer 6 caractères par apostrophe (&#039;) et 5 par esperluette
     * (&amp;) — c'est ce qui faisait disparaître jusqu'à un tiers du texte.
     *
     * @see shortenToHtml() pour la composition complète
     */
    public static function truncateWords(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars)
        {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxChars);

        // la coupe tombe pile en fin de mot : rien à reculer
        if (preg_match('/^\s/u', mb_substr($text, $maxChars, 1)))
        {
            return rtrim($cut);
        }

        // sinon on recule jusqu'à la fin du dernier mot entier
        $trimmed = rtrim((string) preg_replace('/\s+\S*$/u', '', $cut));

        // un premier "mot" plus long que la limite : on coupe quand même
        return $trimmed === '' ? $cut : $trimmed;
    }

    /**
     * Texte brut -> HTML tronqué, prêt à être affiché.
     *
     * L'ordre des opérations est imposé : tronquer, puis échapper, puis
     * baliser. Le HTML étant produit après la coupe, aucune balise ne peut
     * rester ouverte — d'où l'absence de toute machinerie de fermeture.
     *
     * $maxChars est un plafond de charge utile, pas un plafond de hauteur :
     * le nombre de lignes réellement rendues dépend du retour à la ligne
     * automatique, que PHP ne peut pas connaître. C'est le CSS (line-clamp)
     * qui plafonne la hauteur là où il y en a un — voir isCut().
     */
    public static function shortenToHtml(string $text, int $maxChars): string
    {
        $truncated = self::truncateWords($text, $maxChars);
        $html = self::lnAndUrlToHtml(sanitizeForHtml($truncated));

        return $truncated === $text ? $html : $html . '…';
    }

    /**
     * Étiquette courte -> HTML tronqué, prêt à être affiché.
     *
     * Coupe franche au nombre de caractères, sans reculer jusqu'au mot précédent et sans
     * transformer les URL en liens : ce que shortenToHtml() fait pour un texte rédigé n'a
     * pas de sens pour une étiquette de cellule. « Jean Pierre Dupont » doit rendre
     * « Jean Pierr… » plutôt que « Jean… », et un pseudo qui ressemble à une adresse ne
     * doit pas devenir un lien à l'intérieur du lien qui l'entoure déjà.
     *
     * Sert la colonne « par » de admin/events.php, qu'un seul pseudo long élargissait tout
     * entière. Le texte complet a sa place dans un attribut title, à la charge de l'appelant.
     */
    public static function truncateCharsToHtml(string $text, int $maxChars): string
    {
        $html = sanitizeForHtml(mb_substr($text, 0, $maxChars));

        return self::isCut($text, $maxChars) ? $html . '…' : $html;
    }

    /**
     * shortenToHtml() a-t-elle coupé ce texte ?
     *
     * Permet de rendre côté serveur un lien « lire la suite » sans avoir à
     * deviner en inspectant le HTML produit.
     */
    public static function isCut(string $text, int $maxChars): bool
    {
        return mb_strlen($text) > $maxChars;
    }

    /**
     * Retourne la portion de chaîne située AVANT la dernière occurrence d'un caractère.
     *
     * Équivalent multibyte de strrchr(), mais retourne la partie gauche plutôt que droite.
     * Exemple : reverseMbStrrchr('foo/bar/baz', '/') => 'foo/bar'
     *
     * @param string|\Stringable $haystack La chaîne dans laquelle chercher.
     * @param string|\Stringable $needle   Le caractère ou la sous-chaîne à rechercher.
     *
     * @return string|false La sous-chaîne avant la dernière occurrence de $needle,
     *                      ou false si $needle est absent (position 0 incluse).
     */
    public static function reverseMbStrrchr(string|\Stringable $haystack, string|\Stringable $needle): string|false
    {
        $haystack = (string) $haystack;
        $needle   = (string) $needle;

        // Recherche la position de la dernière occurrence (de droite à gauche)
        $pos = mb_strrpos($haystack, $needle);

        // mb_strrpos retourne false si non trouvé, ou 0 si en début de chaîne.
        // Dans les deux cas on ne peut pas retourner de partie "avant" significative.
        if ($pos === false || $pos === 0) {
            return false;
        }

        return mb_substr($haystack, 0, $pos);
    }


}