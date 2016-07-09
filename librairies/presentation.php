<?php
/**
 * Boite ? outils de fonctions
 * Chaque page du site l'a inclus
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */


 /**
   * Outil pour pr?senter du texte dans des balises HTML de base
   *
   * @param string $texte Texte ? formater
   * @param string $format (h1, h2, h3, etc.) Choix du formatage en terme de tags HTML
   */
function formaterTexte($texte, $format)
{
	switch ($format)
	{
		case "h1":
			$texte = "<h1>".$texte."</h1>";
			break;

		case "h2":
			$texte = "<h2>".$texte."</h2>";
			break;

		case "h3":
			$texte = "<h3>".$texte."</h3>";
			break;

		case "h4":
			$texte = "<h4>".$texte."</h4>";
			break;

		case "h5":
			$texte = "<h5>".$texte."</h5>";
			break;

		case "p":
			$texte = "<p>".$texte."</p>";
			break;

		case "italic":
			$texte = "<italic>".$texte."</italic>";
			break;

		case "bold":
			$texte = "<em>".$texte."</em>";
			break;
	}

	echo $texte;

}


 /**
   * Parcours un texte ? afficher dans du HTML et remplace les caract?res sp?ciaux en HTML
   * ainsi que les espaces avant et apr?s
   *
   * @param string $texte Texte ? parser
   * @return chaine trait?es
   */
function securise_string($chaine)
{
	//$chaine = trim($chaine);
	//return $chaine;
	//return trim(str_replace(array("&gt;", "&lt;", "&quot;"), array(">", "<", "\""), $chaine));
	return trim(htmlspecialchars($chaine));
//enlev? "&" "&amp;"
//$chaine = str_replace(';','&#x3B',$chaine);
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
function textToHtml($temp)
{

	$temp = preg_replace("/'''(('?[^\n'])*)'''/", "<strong>\\1</strong>", $temp);

    $temp = preg_replace("/([^*]{2}|)\n/","\\1<br />", $temp);

    $temp = preg_replace("/''(('?[^\n'])*)''/", "<em>\\1</em>", $temp);

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


function wiki2text($temp)
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
   * Affiche un texte dans une balise SPAN de la classe "msg"
   *
   * @param string $message Texte ? afficher
   */
function msgForm($message)
{
	echo "<div class=\"msg\">".$message."</div>";
}


 /**
   * Affiche un texte dans une balise DIV de la classe "msg" et une icone d'erreur
   *
   * @param string $message Texte ? afficher
   */
function msgErreur($message)
{
	echo '<div class="msg_erreur">'.$message.'</div>';
}


 /**
   * Affiche un texte dans une balise  de la classe "msg" et une icone OK
   *
   * @param string $message Texte ? afficher
   */
function msgOk($message)
{
	echo '<div class="msg_ok">'.$message.'</div>';
}

 /**
   * Affiche un texte dans une balise div de la classe "msg" et une icone
   *
   * @param string $message Texte ? afficher
   */
function msgInfo($message)
{
	echo '<div class="msg_info">'.$message.'</div>';
}

 /**
   * Affiche un texte dans une balise div de la classe "msg" et une icone
   *
   * @param string $message Texte ? afficher
   */
function msgEmail($message)
{
	echo '<div class="msg_email">'.$message.'</div>';
}

function messageOk($message)
{
	return '<div class="msg_ok">'.$message.'</div>';

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
function trouveMaxChar($texte, $charsLigne, $maxLignes)
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
		if ($i != ($tailleTexte - 1) && ($texte[$i] == '=' && $texte[$i+1] == '='))
		{
			$lignes++;
			$j = 0;
		}

		/*
		if ($i != ($tailleTexte - 1) && ($texte[$i] == '\'' && $texte[$i+1] == '\'')) {
			$i++;
			continue;
		}*/

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
function texteHtmlReduit($texteHtml, $limChar, $lienSuite = "")
{

	//"-13" pour tenir compte du lien "lire la suite"
	//$limChar -= 13;

	//recoit le nouveau texte raccourci
	$texteHtmlCourt	= "";

	//compteur
	$i = 0;
	//compteur des caract?res seulement, sans le html
	$t = 0;
	//1 si une balise html vient d'etre ouverte, 0 sinon
	$ouvert = 0;
	//pile stockant les tags htmls rencontre
	$pileTags = array();
	$nivPile = 0;

	while($t < $limChar)
	{

		//echo $texteHtml[$i];

		
		if (isset($texteHtml[$i]) && isset($texteHtml[$i+1]))
		{
			//si une balise ouvrante est trouve
			if ($texteHtml[$i] == "<" &&  $texteHtml[$i+1] != "/" )
			{
				$tag = "";
				$m = 0;

				//pour trouver quelle balise c'est, parcours du mot jusqu'a '>'
				for ($j = $i+1; $texteHtml[$j] != " " && $texteHtml[$j] != ">"; $j++)
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
			if (($texteHtml[$i] == "<" && $texteHtml[$i+1] == "/") || ($texteHtml[$i] == "/" && $texteHtml[$i+1] == ">"))
			{
				//la balise du dessus du tas est retiree, puisque fermee
				$nivPile--;
				//print_r($pileTags[$nivPile]);
				//echo " enleve";
				unset($pileTags[$nivPile]);

				//si c'est une balise fermante complete </ ...> et non <... />, ce sera du html ensuite
				if ($texteHtml[$i] == "<" && $texteHtml[$i+1] == "/")
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
			$texteHtmlCourt	.= $texteHtml[$i];
		
		
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
		$texteHtmlCourt	.= $texteHtml[$k];
		$t++;

		$k++;

		//echo "<p>".$k.":".$texteHtml[$k]."</p>";
	}

	$cloture = "...";

	//verifie la pile de balises html et ajoute les balises fermantes manquantes
	//echo "countpiletags:".count($pileTags);
	$hauteur = count($pileTags) - 1;
	//print_r($pileTags);
	while($hauteur >= 0)
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
	return $texteHtmlCourt.$cloture.$lienSuite;
}




//$x is the string, $var is the text to be highlighted
function highlight($x, $var)
{
    if ($var != "")
	{
        $xtemp = "";
        $i=0;
        while($i<mb_strlen($x))
		{
            if((($i + mb_strlen($var)) <= mb_strlen($x)) && (strcasecmp($var, mb_substr($x, $i, mb_strlen($var))) == 0))
			{
//this version bolds the text. you can replace the html tags with whatever you like.
                $xtemp .= "<strong>" . mb_substr($x, $i , mb_strlen($var)) . "</strong>";
                $i += mb_strlen($var);
            }
            else
			{
                $xtemp .= $x{$i};
                $i++;
            }
        }
        $x = $xtemp;
    }
    return $x;
}

//function to return the pagination string
function getPaginationString($page = 1, $totalitems, $limit = 15, $adjacents = 1, $targetpage = "/", $pagestring = "?page=")
{
	//defaults
	if(!$adjacents) $adjacents = 1;
	if(!$limit) $limit = 15;
	if(!$page) $page = 1;
	if(!$targetpage) $targetpage = "/";

	//other vars
	$prev = $page - 1;									//previous page is page - 1
	$next = $page + 1;									//next page is page + 1
	$lastpage = ceil($totalitems / $limit);				//lastpage is = total items / items per page, rounded up.
	$lpm1 = $lastpage - 1;

	$margin = 2;
	$padding = 2;
	//last page minus 1

	/*
		Now we apply our rules and draw the pagination object.
		We're actually saving the code to a variable in case we want to draw it more than once.
	*/
	$pagination = "";
	if($lastpage > 1)
	{
		$pagination .= "<div class=\"pagination\"";
		if($margin || $padding)
		{
			$pagination .= " style=\"";
			if($margin)
				$pagination .= "margin: $margin;";
			if($padding)
				$pagination .= "padding: $padding;";
			$pagination .= "\"";
		}

		$pagination .= ">";

		//previous button
		if ($page > 1)
			$pagination .= "<a id=\"prec\" href=\"$targetpage$pagestring$prev\">préc</a>";
		else
			$pagination .= "<span class=\"disabled\">préc</span>";

		//pages
		if ($lastpage < 7 + ($adjacents * 2))	//not enough pages to bother breaking it up
		{
			for ($counter = 1; $counter <= $lastpage; $counter++)
			{
				if ($counter == $page)
					$pagination .= "<span class=\"current\">$counter</span>";
				else
					$pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
			}
		}
		elseif($lastpage >= 7 + ($adjacents * 2))	//enough pages to hide some
		{
			//close to beginning; only hide later pages
			if($page < 1 + ($adjacents * 3))
			{
				for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"current\">$counter</span>";
					else
						$pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
				}
				$pagination .= "...";
				$pagination .= "<a href=\"$targetpage$pagestring$lpm1\">$lpm1</a>";
				$pagination .= "<a href=\"$targetpage$pagestring$lastpage\">$lastpage</a>";
			}
			//in middle; hide some front and some back
			elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
			{
				$pagination .= "<a href=\"".$targetpage.$pagestring."1\">1</a>";
				$pagination .= "<a href=\"".$targetpage.$pagestring."2\">2</a>";
				$pagination .= "...";
				for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"current\">$counter</span>";
					else
						$pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
				}
				$pagination .= "...";
				$pagination .= "<a href=\"$targetpage$pagestring$lpm1\">$lpm1</a>";
				$pagination .= "<a href=\"$targetpage$pagestring$lastpage\">$lastpage</a>";
			}
			//close to end; only hide early pages
			else
			{
				$pagination .= "<a href=\"".$targetpage.$pagestring."1\">1</a>";
				$pagination .= "<a href=\"".$targetpage.$pagestring."2\">2</a>";
				$pagination .= "...";
				for ($counter = $lastpage - (1 + ($adjacents * 3)); $counter <= $lastpage; $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"current\">$counter</span>";
					else
						$pagination .= "<a href=\"$targetpage$pagestring$counter\">$counter</a>";
				}
			}
		}

		//next button
		if ($page < $counter - 1)
			$pagination .= "<a id=\"suiv\"  href=\"$targetpage$pagestring$next\">suiv</a>";
		else
			$pagination .= "<span class=\"disabled\">suiv</span>";
		$pagination .= "</div>\n";
	}

	return $pagination;

}


function form_input($tab_att)
{
	$aff = "<input ";

	foreach ($tab_att as $att => $val)
	{
		if (!empty($val))
		{
			$aff .= $att."=\"".$val."\" ";
		}
	}

	$aff .= "/>";

	return $aff;
}

function form_label($tab_att, $nom)
{
	$aff = "<label ";

	foreach ($tab_att as $att => $val)
	{
		if (!empty($val))
		{
			$aff .= $att."=\"".$val."\" ";
		}
	}

	$aff .= ">".$nom."</label>";

	return $aff;
}

function formatbytes($val, $digits = 3, $mode = "SI", $bB = "B"){ //$mode == "SI"|"IEC", $bB == "b"|"B"
        $si = array("", "k", "M", "G", "T", "P", "E", "Z", "Y");
        $iec = array("", "Ki", "Mi", "Gi", "Ti", "Pi", "Ei", "Zi", "Yi");
        switch(mb_strtoupper($mode)) {
            case "SI" : $factor = 1000; $symbols = $si; break;
            case "IEC" : $factor = 1024; $symbols = $iec; break;
            default : $factor = 1000; $symbols = $si; break;
        }
        switch($bB) {
            case "b" : $val *= 8; break;
            default : $bB = "B"; break;
        }
        for($i=0;$i<count($symbols)-1 && $val>=$factor;$i++)
            $val /= $factor;
        $p = mb_strpos($val, ".");
        if($p !== false && $p > $digits) $val = round($val);
        elseif($p !== false) $val = round($val, $digits-$p);
        return round($val, $digits) . " " . $symbols[$i] . $bB;
    }

   // reverse mb_strrchr()
function reverse_mb_strrchr($haystack, $needle)
{
                return mb_strrpos($haystack, $needle) ? mb_substr($haystack, 0, mb_strrpos($haystack, $needle) ) : false;
}

function lien_popup($uri, $nom, $largeur, $hauteur, $lien)
{
	return "<a href=\"#\" onclick=\"window.open('".$uri."','".$nom."','height=".$hauteur."px,width=".$largeur."px,toolbar=no,menuBar=yes,location=no,directories=0,status=no,scrollbars=yes,resizable=yes,left=10,top=10');return(false)\" title=\"".$nom."\">".$lien."</a>";
}

function printr($array)
{

	echo '<div>';
   static $indentation = '';
   static $array_key = '';
   $cst_indentation = '&nbsp;&nbsp;&nbsp;&nbsp;';

   echo $indentation . $array_key . '<b>array(</b><br />';
   reset($array);
   while (list($k, $v) = each($array))
   {
      if (is_array($v))
      {
         $indentation .= $cst_indentation;
         $array_key = '\'<i style="color: #334499 ;">' . addslashes(htmlspecialchars($k)) . '</i>\' => ';
         printr($v);
         $indentation = mb_substr($indentation, 0, mb_strlen($indentation) - mb_strlen($cst_indentation));
      }
      else
      {
         echo $indentation . $cst_indentation . '\'<i style="color: #334499 ;">' .
 addslashes(htmlspecialchars($k)) . '</i>\' => \'' . addslashes(htmlspecialchars($v)) . '\',<br />';
      }
   }
   echo $indentation . '<b>)</b>' . (($indentation === '') ? ';' : ',') . '<br />';
   echo '</div>';
}


function signature_auteur($idPersonne)
{

	global $connector;

	$signature_auteur = "";
	$sql_auteur = "SELECT pseudo, nom, prenom, affiliation, signature, avec_affiliation
	FROM personne WHERE idPersonne=".$idPersonne."";

	$req_auteur = $connector->query($sql_auteur);
	$tab_auteur = $connector->fetchArray($req_auteur);

	if ($tab_auteur['signature'] == 'pseudo')
	{
		$signature_auteur = "<strong>".$tab_auteur['pseudo']."</strong>";
	}
	else if ($tab_auteur['signature'] == 'prenom')
	{
		$signature_auteur = "<strong>".$tab_auteur['prenom']."</strong>";
	}
	else if ($tab_auteur['signature'] == 'nomcomplet')
	{
		$signature_auteur = "<strong>".$tab_auteur['prenom']." ".$tab_auteur['nom']."</strong>";
	}

	if ($tab_auteur['avec_affiliation'] == 'oui')
	{
		$nom_affiliation = "";
		$req_aff = $connector->query("
		SELECT idAffiliation FROM affiliation
		WHERE idPersonne=".$idPersonne." AND genre='lieu'");

		if (!empty($tab_auteur['affiliation']))
		{
			$nom_affiliation = $tab_auteur['affiliation'];
		}
		else if ($tab_aff = $connector->fetchArray($req_aff))
		{
			$req_lieu_aff = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_aff['idAffiliation']);
			$tab_lieu_aff = $connector->fetchArray($req_lieu_aff);
			$nom_affiliation = $tab_lieu_aff['nom'];
		}

		$signature_auteur .= " (".$nom_affiliation.")";
	}

	return $signature_auteur;
}


 /**
	* Marque le titre de l'?v?nement selon le statut qui lui attribu?
	*
   *
   * @param string $titre Titre de l'?v?nement
   * @param string $statut Statut actuel de l'?v?nement
   * @return string Le titre marqu?
   */
function titre_selon_statut($titre, $statut)
{
	$titre_avec_statut = $titre;

	if ($statut == "annule")
	{
		$titre_avec_statut = '<strike>'.$titre.'</strike> ANNULÉ';
	}
	if ($statut == "complet")
	{
		$titre_avec_statut = '<em>'.$titre.'</em> COMPLET';
	}

	return $titre_avec_statut;
}


/**
 * @param $posttext texte
 * @param $minimum_length Longeur minimum souhait?e du texte r?duit
 * @param $length_offset Marge
 * @param $cut_words
 * @param $dots
 */
function html_substr($posttext, $minimum_length = 200, $length_offset = 20, $cut_words = FALSE, $dots = TRUE) {

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
    if (mb_strlen($posttext) > $minimum_length) {
        // Reset the tag_counter and pass through (part of) the entire text
        $c = 0;
        for ($i = 0; $i < mb_strlen($posttext); $i++) {
            // Load the current character and the next one
            // if the string has not arrived at the last character
            $current_char = mb_substr($posttext,$i,1);
            if ($i < mb_strlen($posttext) - 1) {
                $next_char = mb_substr($posttext,$i + 1,1);
            }
            else {
                $next_char = "";
            }
            // First check if quotes are on
            if (!$quotes_on) {
                // Check if it's a tag
                // On a "<" add 3 if it's an opening tag (like <a href...)
                // or add only 1 if it's an ending tag (like </a>)
                if ($current_char == '<') {
                    if ($next_char == '/') {
                        $tag_counter += 1;
                    }
                    else {
                        $tag_counter += 3;
                    }
                }
                // Slash signifies an ending (like </a> or ... />)
                // substract 2
                if ($current_char == '/' && $tag_counter <> 0) $tag_counter -= 2;
                // On a ">" substract 1
                if ($current_char == '>') $tag_counter -= 1;
                // If quotes are encountered, start ignoring the tags
                // (for directory slashes)
                if ($current_char == '"') $quotes_on = TRUE;
            }
            else {
                // IF quotes are encountered again, turn it back off
                if ($current_char == '"') $quotes_on = FALSE;
            }

            // Count only the chars outside html tags
            if($tag_counter == 2 || $tag_counter == 0){
                $c++;
            }

            // Check if the counter has reached the minimum length yet,
            // then wait for the tag_counter to become 0, and chop the string there
            if ($c > $minimum_length - $length_offset && $tag_counter == 0 && ($next_char == ' ' || $cut_words == TRUE)) {
                $posttext = mb_substr($posttext,0,$i + 1);
                if($dots){
                   $posttext .= '...';
                }
                return $posttext;
            }
        }
    }
    return $posttext;
}

function lien_organisateur($arg, $nom, $att = '', $title='')
{
	global $url_site;

	return '<a href="'.$url_site.'organisateur.php" title="'.$title.'">'.$nom.'</a>';

}

function nom_genre($nom)
{
	if ($nom == 'fête')
	{
		return 'fêtes';
	}
	else if ($nom == 'cinéma')
	{
		return 'ciné';
	}
	else
	{
		return $nom;
	}	


}


?>
