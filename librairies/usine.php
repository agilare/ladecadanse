<?php
/**
 * Boite à outils de fonctions
 * Chaque page du site l'a inclus
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */


 /**
   * Vérifie dans la base si une personne est bien l'auteur d'un événement,
   * une brêve, une description
   *
   * @param int $didP ID utilisateur à vérifier
   * @param int $id ID entité dont l'auteur est à vérifier
   * @param string $table (breve, evenement, descriptionlieu, lieu) vérifie si $idP est
   * auteur de $id
   * @return boolean Si $idP est auteur ou non
   */
function estAuteur($idP, $id, $table)
{
	global $connector;

	$sql_auteur = "SELECT idPersonne FROM ".$table." WHERE id".ucfirst($table)."=".$id." AND idPersonne=".$idP;

	$getP = $connector->query($sql_auteur);

	if ($connector->getNumRows($getP) > 0)
	{
		return true;
	} else {
		return false;
	}

}


function encoder_utf8(&$valeur)
{
	$valeur = utf8_encode($valeur);
}












function arguments_URI($get, $sauf = "")
{
	$afficher = "";

	if (!is_array($sauf))
	{
		foreach ($get as $nom => $valeur)
		{
			if ($nom != $sauf)
			{
				$afficher .= $nom."=".$valeur."&amp;";
			}
		}

	}
	else
	{
		foreach ($get as $nom => $valeur)
		{
			if (!in_array($nom, $sauf))
			{
				$afficher .= $nom."=".$valeur."&amp;";
			}
		}
	}
	$afficher = mb_substr($afficher, 0, -5);

	return $afficher;

}

function verif_get($get, $type, $statut, $tab = '')
{
	global $iconeErreur;

	$erreur = "";

	if ($get == '')
	{
		if ($statut == 1)
		{
			$erreur = "Ce paramètre est obligatoire";
		}
		else if ($statut != 0)
		{
			return $statut;
		}
		else
		{
			return;
		}

	}
	else
	{
		$get = trim($get);

		if ($type == "int")
		{
			if (is_numeric($get))
			{
				return $get;
			}
			else
			{
				$erreur = "Ce n'est pas un entier";
			}

		}
		else if ($type == "string")
		{
			if (is_string($get))
			{
				return $get;
			}
			else
			{
				$erreur = "Ce n'est pas une chaine";
			}
		}
		else if ($type == "date")
		{

			if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $get))
			{
				return $get;
			}
			else
			{
				$erreur = "Ce n'est pas une date";
			}
		}
		else if ($type == "enum")
		{
			if (in_array($get, $tab))
			{
				return $get;
			}
			else
			{
				$erreur = "Ce n'est pas une valeur acceptée";
			}
		}
		else if ($type == "alpha_numeric")
		{
			if (alpha_numeric($get))
			{
				return $get;
			}
			else
			{
				$erreur = "Ce n'est pas une valeur acceptée";
			}

		}


	}
//
//	trigger_error($iconeErreur.$erreur, E_USER_ERROR);
//	exit;

}



function pw_encode($password)
{
   for ($i = 1; $i <= 10; $i++)
       $seed .= mb_substr('0123456789abcdef', rand(0,15), 1);
   return sha1($seed.$password.$seed).$seed;
}

function pw_check($password, $stored_value)
{
   if (mb_strlen($stored_value) != 50)
      return FALSE;
   $stored_seed = mb_substr($stored_value,40,10);
   if (sha1($stored_seed.$password.$stored_seed).$stored_seed == $stored_value)
     return TRUE;
   else
     return FALSE;
}



function replace_char_spec($name)
{
$name = strtr($name, 'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ',
'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');

$name = preg_replace('/([^.a-z0-9]+)/i', '-', $name);

return $name;
}


function supprimerEvenement($get_idE)
{

global $connector;
global $rep_absolu;
global $rep_images;
global $rep_fichiers_even;
global $rep_librairies;
global $rep_cache;


///TESTER SI L'EVENEMENT EXISTE ENCORE

if (((estAuteur($_SESSION['SidPersonne'], $get_idE, "evenement") && $_SESSION['Sgroupe'] <= 6) || $_SESSION['Sgroupe'] < 2))
{
	/*
	 * Suppression du flyer
	 */
	$req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement FROM evenement
	WHERE idEvenement=".$get_idE);

	$val_even = $connector->fetchArray($req_im);
	$titreSup = $val_even['titre']; //pour le message apr?suppression

	if (!empty($val_even['flyer']))
	{
		unlink($rep_images.$val_even['flyer']);
		unlink($rep_images."s_".$val_even['flyer']);
		unlink($rep_images."t_".$val_even['flyer']);
	}

	if (!empty($val_even['image']))
	{
		unlink($rep_images.$val_even['image']);
		unlink($rep_images."s_".$val_even['image']);
	}

	$req_docu = $connector->query("SELECT * FROM fichierrecu
	WHERE idElement=".$get_idE." AND type_element='evenement' AND type='document'");

	while ($tab_docu = $connector->fetchArray($req_docu))
	{
		//printr($tab_docu);
		unlink($rep_fichiers_even.$tab_docu['idFichierrecu'].".".$tab_docu['extension']);
		$connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=".$tab_docu['idFichierrecu']);
	}


	/*
	 * Suppression du cache si l'?nement a lieu dans un lieu pr?nt dans la base
	 */
	/* if (!empty($val_even['idLieu']))
		@unlink($cache_lieu.$val_even['idLieu'].".php");

	@unlink($cache_even.$get_idE.".php");

	if ($rc = opendir($cache_index)) {
		while ($fichierIndex = readdir($rc)) {
			if (preg_match('/^'.urlencode($val_even['genre']).'_'.date2sem($val_even['dateEvenement']).'/', $fichierIndex))
				@unlink($cache_index.$fichierIndex);
		}
		closedir($rc);
	} */


	if ($connector->query("DELETE FROM evenement WHERE idEvenement=".$get_idE))
	{
		msgOk('L\'événement "'.securise_string($titreSup).'" a été supprimé');
	}
	else
	{
		msgErreur("La requête DELETE a échoué");
	}
}
else
{
	echo "Vous ne pouvez pas supprimer cet événement.";
}


}

function replace_accents($str)
{
  $str = htmlentities($str, ENT_COMPAT | ENT_HTML401, "UTF-8");
  $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/','$1',$str);
  return html_entity_decode($str);
}

function generatePassword($length=9, $strength=0) {
    $vowels = 'aeuy';
    $consonants = 'bdghjmnpqrstvz';
    if ($strength & 1) {
        $consonants .= 'BDGHJLMNPQRSTVWXZ';
    }
    if ($strength & 2) {
        $vowels .= "AEUY";
    }
    if ($strength & 4) {
        $consonants .= '23456789';
    }
    if ($strength & 8) {
        $consonants .= '@#$%';
    }

    $password = '';
    $alt = time() % 2;
    for ($i = 0; $i < $length; $i++) {
        if ($alt == 1) {
            $password .= $consonants[(rand() % mb_strlen($consonants))];
            $alt = 0;
        } else {
            $password .= $vowels[(rand() % mb_strlen($vowels))];
            $alt = 1;
        }
    }
    return $password;
}


function alpha_numeric ( $str )
{
	return ( ! preg_match ( "/^([-a-z0-9])+$/i", $str ) ) ? FALSE : TRUE;
}


function est_membre_organisateur($idP, $idO)
{
	global $connector;

	$sql = "SELECT idPersonne FROM personne_organisateur WHERE idOrganisateur=".$idO." AND idPersonne=".$idP;

	$getP = $connector->query($sql);

	if ($connector->getNumRows($getP) > 0)
	{
		return true;
	}
	else
	{
		return false;
	}

}

function est_organisateur_lieu($idP, $idL)
{
	global $connector;

	$sql = "SELECT idPersonne FROM personne_organisateur, lieu_organisateur 
	WHERE personne_organisateur.idOrganisateur=lieu_organisateur.idOrganisateur AND
	lieu_organisateur.idLieu=".$idL." AND idPersonne=".$idP;

	$getP = $connector->query($sql);

	if ($connector->getNumRows($getP) > 0)
	{
		return true;
	}
	else
	{
		return false;
	}

}

function est_organisateur_evenement($idP, $idE)
{
	global $connector;

	$sql = "SELECT idPersonne FROM personne_organisateur, evenement_organisateur
	WHERE personne_organisateur.idOrganisateur=evenement_organisateur.idOrganisateur AND
	evenement_organisateur.idEvenement=".$idE." AND idPersonne=".$idP;

	$getP = $connector->query($sql);

	if ($connector->getNumRows($getP) > 0)
	{
		return true;
	}
	else
	{
		return false;
	}

}
	function creer_nom_fichier($id, $type ='', $date_time = '', $nom_original)
	{
		$suffixe = mb_strrchr($nom_original, '.');

		$date = '';
		if ($date_time != '')
		{
			$dateAjoutTab = explode(" ", $date_time);
			$date = $dateAjoutTab[0];
		}

		return $id."_".$type.$date.$suffixe;
	}


/* crée lien html pour urls (http, www et email) mais pas si elles sont déjà dans un <a> */
function linkify($input){
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

    return preg_replace_callback($re, function($m){
		//print_r($m);
        if($m[1]) return $m[1];
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


/* crée lien html pour urls (http, www et email) mais pas si elles sont déjà dans un <a> */
function linkify_original($input){
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

    return preg_replace_callback($re, function($m){
		//print_r($m);
        if($m[1]) return $m[1];

		if ($m[2])
		{
			$url = $m[2];
		}
		else if ($m[3])
		{
			$url = "http://$m[3]";
		}
		else if ($m[4])
		{
			$url = "mailto:$m[4]";
		}
        $url = htmlspecialchars($url);
        $text = htmlspecialchars("$m[2]$m[3]$m[4]");
        return "<a href='$url'>$text</a>";
    },
    $input);
}


function get_adresse($region, $localite, $quartier, $adr)
{
    $adresse = '';
    
   if (!empty($adr))
        $adresse .= $adr;
    
    if (!empty($quartier) && $quartier != 'autre')
        $adresse .= " (".$quartier.") ";
    
    if (!empty($localite) && $localite != 'Autre' && $localite != $quartier)
        $adresse .= " - ".$localite;

    
    if ($localite != 'Genève' && $region == 'ge')
        $adresse .= " - Genève";
    
    if ($region == 'vd')
        $adresse .= " - Vaud"; 
    
    
    if ($region == 'rf')
        $adresse .= " - France";     
    
    return $adresse;
    
}

function getMenuRegions($glo_regions)
{
    
   
    $html = '';
    ob_start();
    //if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4) 
?>
    <ul class="menu_region">
            <?php 
        foreach ($glo_regions as $n => $v)
        {
            if ($n == 'ge' || $n == 'vd' ) //|| $n == 'fr'
            {
                if ($n == 'vd')
                {
                    $v = 'Lausanne';
                }  

                if ($n == 'fr')
                {
                    $v = 'Fr';
                }                          
            $ici = '';
            if ($n == $_SESSION['region'])
                $ici = ' class="ici" ';
        ?><li><a href="?region=<?php echo $n; ?>" <?php echo $ici; ?>><?php echo $v; ?></a></li><?php
            }
        }
        ?></ul>
   <?php         

    return ob_get_contents();
}
