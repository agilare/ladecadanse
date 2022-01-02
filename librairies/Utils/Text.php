<?php

namespace Ladecadanse\Utils;

class Text
{
    public static function stripAccents($str)
    {
        $str = htmlentities($str, ENT_COMPAT | ENT_HTML401, "UTF-8");
        $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1', $str);
        return html_entity_decode($str);
    }
    
    public static function linkify($input)
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
            //print_r($m);
            if ($m[1])
                return $m[1];
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
            $url = htmlspecialchars($url);
            $text = htmlspecialchars($text);
            return "<a href='$url'>$text</a>";
        },
                $input);
    }  
    

    public static function formatbytes($val, $digits = 3, $mode = "SI", $bB = "B")
    { //$mode == "SI"|"IEC", $bB == "b"|"B"
        $si = array("", "k", "M", "G", "T", "P", "E", "Z", "Y");
        $iec = array("", "Ki", "Mi", "Gi", "Ti", "Pi", "Ei", "Zi", "Yi");
        switch (mb_strtoupper($mode))
        {
            case "SI" : $factor = 1000;
                $symbols = $si;
                break;
            case "IEC" : $factor = 1024;
                $symbols = $iec;
                break;
            default : $factor = 1000;
                $symbols = $si;
                break;
        }
        switch ($bB)
        {
            case "b" : $val *= 8;
                break;
            default : $bB = "B";
                break;
        }
        for ($i = 0; $i < count($symbols) - 1 && $val >= $factor; $i++)
            $val /= $factor;
        $p = mb_strpos((string) $val, ".");
        if ($p !== false && $p > $digits)
            $val = round($val);
        elseif ($p !== false)
            $val = round($val, $digits - $p);
        return round($val, $digits) . " " . $symbols[$i] . $bB;
    }    
  
    /**
     * Remplace tous les tags wiki d'un texte par des balises HTML
     * ==texte== -> h2
     * Retrurn -> br
     * '''texte''' -> b
     * ''texte'' -> i
     * ---- -> hr
     * http ou www -> a href
     * @param  string $temp Texte avec les balises wiki
     * @return string Texte avec balises HTML
     */
    public static function wikiToHtml(string $temp): string
    {

        //$temp = preg_replace("/'''(('?[^\n'])*)'''/", "<strong>\\1</strong>", $temp);

        $temp = preg_replace("/([^*]{2}|)\n/", "\\1<br />", $temp);

        //$temp = preg_replace("/''(('?[^\n'])*)''/", "<em>\\1</em>", $temp);
        //$temp = preg_replace("/\*\*(.*?)\*\*/", "<blockquote>\\1</blockquote>", $temp);
        //$temp = str_replace("----", "<hr />", $temp);

        $temp = preg_replace("/(([^[]|^)(http)+(s)?:(\/\/)|([^\[\/]|^)(www\.))((\w|\.|\-|_)+)(\/)?(\S+)?/i", "\\2\\6<a href=\"http\\4://\\7\\8\\10\\11\" title=\"\\0\">\\7\\8</a>", $temp);
        //[
        $temp = preg_replace("/\[(http[s]?:\/\/)([-a-z0-9_]{2,}\.[-a-z0-9.]{2,}[-a-z0-9\/&\?=.;~_%]*) (.+?)\]/i",
                "<a href=\"\\1\\2\" title=\"\\1\\2\">\\3</a>", $temp);

        $temp = preg_replace("/\[www\.([-a-z0-9.]{2,}[-a-z0-9\/&\?=.~_%]*) (.+?)\]/i",
                "<a href=\"http://www.\\1\" title=\"www.\\1\">\\2</a>", $temp);

        return $temp;
    }



    public static function wikiToText($temp)
    {

        $temp = preg_replace("/'''(('?[^\n'])*)'''/", "\\1", $temp);
        $temp = preg_replace("/(\r|\n)*==(('?[^\n'])*)==( |\n|\r)*/", "\\2 ", $temp);
        $temp = preg_replace("/([^*]{2}|)\r\n/", "\\1 <br />", $temp);
        //$temp = preg_replace("/([^*]{2}|)(\r|\n)/", "\\1 ", $temp);

        $temp = preg_replace("/''(('?[^\n'])*)''/", "<i>\\1</i>", $temp);

        //$temp = preg_replace("/\*\*(.*?)\*\*/", "<blockquote>\\1</blockquote>", $temp);
        //$temp = str_replace("----", " ", $temp);

        $temp = preg_replace("/(([^[]|^)(http)+(s)?:(\/\/)|([^\[\/]|^)(www\.))((\w|\.|\-|_)+)(\/)?(\S+)?/i", "\\2\\6<a href=\"http\\4://\\7\\8\\10\\11\" title=\"\\0\">\\7\\8</a>", $temp);
        //[
        $temp = preg_replace("/\[(http[s]?:\/\/)([-a-z0-9_]{2,}\.[-a-z0-9.]{2,}[-a-z0-9\/&\?=.;~_%]*) (.+?)\]/i",
                "<a href=\"\\1\\2\" title=\"\\1\\2\">\\3</a>", $temp);

        $temp = preg_replace("/\[www\.([-a-z0-9.]{2,}[-a-z0-9\/&\?=.~_%]*) (.+?)\]/i",
                "<a href=\"http://www.\\1\" title=\"www.\\1\">\\2</a>", $temp);

        return $temp;
    }

    /**
     * D?termine le nombre de caract?res max d'un texte selon le nombre moyen de charact?re
     * des lignes du texte et le nombre maximal de lignes accept?es.
     *
     * @param string $texte Texte ? ?valuer
     * @param int $charsLigne Nombre moyen de charact?res par ligne
     * @param int $maxLignes Nombre max de lignes du texte
     * @return int $i Nombre maximal de car. pour ce texte dans l'espaces $charsLignes * $maxLignes
     * @see function texteHtmlReduit
     * @todo Tenir compte des autres balises wiki
     */
    public static function trouveMaxChar($texte, $charsLigne, $maxLignes)
    {

        $i = 0;
        $j = 0;
        $lignes = 1;
        $tailleTexte = mb_strlen($texte);

        /*
         * Compte jusqu'? la fin du texte ou si le nombre max de lignes a ?t? atteint
         */
        while ($i < $tailleTexte && $lignes < $maxLignes)
        {

            //si la fin d'une ligne a ?t? atteinte ou si un saut de ligne est lu
            if ($j == $charsLigne || $texte[$i] == "\n")
            {
                $lignes++;
                $j = 0;
            }

            //si une balise wiki de titre h2 est lue, compte pour une ligne
            if ($i != ($tailleTexte - 1) && ($texte[$i] == '=' && $texte[$i + 1] == '='))
            {
                $lignes++;
                $j = 0;
            }

            /*
              if ($i != ($tailleTexte - 1) && ($texte[$i] == '\'' && $texte[$i+1] == '\'')) {
              $i++;
              continue;
              } */

            $i++;
            $j++;
        }

        return $i;
    }

    /**
     * R?duit un texte selon un nombre max de caract?res, ?vite la coupure du dernier mot,
     * ajoute un lien vers la suite du texte
     *
     * @param string $texteHtml Texte avec balises html ? r?duire
     * @param int $limChar Nombre max de car. calcul? par trouveMaxChar
     * @param string $lienSuite Lien Html
     * @see trouveMaxChar, index.php, lieux.php
     * @return string Texte reduit avec $lienSuite
     */
    public static function texteHtmlReduit($texteHtml, $limChar, $lienSuite = "")
    {

        //"-13" pour tenir compte du lien "lire la suite"
        //$limChar -= 13;
        //recoit le nouveau texte raccourci
        $texteHtmlCourt = "";

        //compteur
        $i = 0;
        //compteur des caract?res seulement, sans le html
        $t = 0;
        //1 si une balise html vient d'etre ouverte, 0 sinon
        $ouvert = 0;
        //pile stockant les tags htmls rencontre
        $pileTags = array();
        $nivPile = 0;

        while ($t < $limChar)
        {

            //echo $texteHtml[$i];


            if (isset($texteHtml[$i]) && isset($texteHtml[$i + 1]))
            {
                //si une balise ouvrante est trouve
                if ($texteHtml[$i] == "<" && $texteHtml[$i + 1] != "/")
                {
                    $tag = "";
                    $m = 0;

                    //pour trouver quelle balise c'est, parcours du mot jusqu'a '>'
                    for ($j = $i + 1; $texteHtml[$j] != " " && $texteHtml[$j] != ">"; $j++)
                    {
                        $tag[$m] = $texteHtml[$j];
                        $m++;
                    }

                    //ajoute la balise ouvrante a la pile
                    $pileTags[$nivPile] = $tag;
                    //echo "Tag ajoute";
                    //print_r($tag);
                    $nivPile++;
                    $ouvert = 1;
                }

                //si une balise fermante est trouve ('</' ou '/>')
                if (($texteHtml[$i] == "<" && $texteHtml[$i + 1] == "/") || ($texteHtml[$i] == "/" && $texteHtml[$i + 1] == ">"))
                {
                    //la balise du dessus du tas est retiree, puisque fermee
                    $nivPile--;
                    //print_r($pileTags[$nivPile]);
                    //echo " enleve";
                    unset($pileTags[$nivPile]);

                    //si c'est une balise fermante complete </ ...> et non <... />, ce sera du html ensuite
                    if ($texteHtml[$i] == "<" && $texteHtml[$i + 1] == "/")
                    {
                        $ouvert = 1;
                    }
                }
            }
            //si un car. fermant est rencontre
            if ($ouvert && $texteHtml[$i] == ">")
            {
                $ouvert = 0;
            }

            //si le car. evalue n'est pas du Html
            if (!$ouvert)
                $t++;


            //ajout du car. au texte reduit
            if (isset($texteHtml[$i]))
                $texteHtmlCourt .= $texteHtml[$i];


            $i++;
        }

        //echo "nivpile:".$nivPile;
        //print_r($pileTags);
        /*
         * Continue le parcours du texte html jusqu'au prochain espace, la prochaine balise html ou la fin du texte
         * et l'ajoute au texte reduit
         */
        $texteTaille = mb_strlen($texteHtml);
        $t = 0;
        $k = $i;
    //	echo $texteTaille;
    //	echo " ".$i;
        while (isset($texteHtml[$k]) && $texteHtml[$k] != " " && $k < ($texteTaille - 1) && $texteHtml[$k] != "<")
        {
            $texteHtmlCourt .= $texteHtml[$k];
            $t++;

            $k++;

            //echo "<p>".$k.":".$texteHtml[$k]."</p>";
        }

        $cloture = "...";

        //verifie la pile de balises html et ajoute les balises fermantes manquantes
        //echo "countpiletags:".count($pileTags);
        $hauteur = count($pileTags) - 1;
        //print_r($pileTags);
        while ($hauteur >= 0)
        {

            $cloture .= "</";

            //parcours le mot de balise courante de la pile et l'ajoute a $cloture
            for ($n = 0, $pileTaille = count($pileTags[$hauteur]); $n < $pileTaille; $n++)
            {
                if (isset($pileTags[$hauteur][$n]))
                    $cloture .= $pileTags[$hauteur][$n];
            }

            $cloture .= ">";
            //descent a la balise plus ancienne
            $hauteur--;
        }

        //renvoie le texte reduit, les balises fermantes et le lien vers la suite
        return $texteHtmlCourt . $cloture . $lienSuite;
    }    


    /**
     * @param $posttext texte
     * @param $minimum_length Longeur minimum souhait?e du texte r?duit
     * @param $length_offset Marge
     * @param $cut_words
     * @param $dots
     */
    public static function html_substr($posttext, $minimum_length = 200, $length_offset = 20, $cut_words = FALSE, $dots = TRUE)
    {

        // $minimum_length:
        // The approximate length you want the concatenated text to be
        // $length_offset:
        // The variation in how long the text can be in this example text
        // length will be between 200 and 200-20=180 characters and the
        // character where the last tag ends
        // Reset tag counter & quote checker
        $tag_counter = 0;
        $quotes_on = FALSE;
        // Check if the text is too long
        if (mb_strlen($posttext) > $minimum_length)
        {
            // Reset the tag_counter and pass through (part of) the entire text
            $c = 0;
            for ($i = 0; $i < mb_strlen($posttext); $i++)
            {
                // Load the current character and the next one
                // if the string has not arrived at the last character
                $current_char = mb_substr($posttext, $i, 1);
                if ($i < mb_strlen($posttext) - 1)
                {
                    $next_char = mb_substr($posttext, $i + 1, 1);
                }
                else
                {
                    $next_char = "";
                }
                // First check if quotes are on
                if (!$quotes_on)
                {
                    // Check if it's a tag
                    // On a "<" add 3 if it's an opening tag (like <a href...)
                    // or add only 1 if it's an ending tag (like </a>)
                    if ($current_char == '<')
                    {
                        if ($next_char == '/')
                        {
                            $tag_counter += 1;
                        }
                        else
                        {
                            $tag_counter += 3;
                        }
                    }
                    // Slash signifies an ending (like </a> or ... />)
                    // substract 2
                    if ($current_char == '/' && $tag_counter <> 0)
                        $tag_counter -= 2;
                    // On a ">" substract 1
                    if ($current_char == '>')
                        $tag_counter -= 1;
                    // If quotes are encountered, start ignoring the tags
                    // (for directory slashes)
                    if ($current_char == '"')
                        $quotes_on = TRUE;
                }
                else
                {
                    // IF quotes are encountered again, turn it back off
                    if ($current_char == '"')
                        $quotes_on = FALSE;
                }

                // Count only the chars outside html tags
                if ($tag_counter == 2 || $tag_counter == 0)
                {
                    $c++;
                }

                // Check if the counter has reached the minimum length yet,
                // then wait for the tag_counter to become 0, and chop the string there
                if ($c > $minimum_length - $length_offset && $tag_counter == 0 && ($next_char == ' ' || $cut_words == TRUE))
                {
                    $posttext = mb_substr($posttext, 0, $i + 1);
                    if ($dots)
                    {
                        $posttext .= '...';
                    }
                    return $posttext;
                }
            }
        }
        return $posttext;
    }

    public static function reverseMbStrrchr($haystack, $needle)
    {
        return mb_strrpos($haystack, $needle) ? mb_substr($haystack, 0, mb_strrpos($haystack, $needle)) : false;
    }

    
}