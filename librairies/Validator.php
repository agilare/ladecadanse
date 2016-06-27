<? /**/ ?>
<?php
/**
 * Donne diverses méthodes de vérification pour des données avant de les insérer dans 
 * une table de BD
 *
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see        SystemComponent.php, ajouter*.php
 */

require_once 'SystemComponent.php';
class Validator extends SystemComponent {

/**
 * Liste des erreures rencontrées durant la vie de l'objet
 * 
 * @var array string
 */
var $erreures;


/**
* Enlève les espaces avant et après un texte

* @param string Texte à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateGeneral($theInput, $description = '') {

	if (trim($theInput) != "") {
	
		return true;
		
	} else {
		
		$this->erreures[] = $description;
		return false;
	
	}

} 


/**
* Vérifie qu'une chaine soit uniquement composée de texte, de chiffres et d'espaces (sans accents)

* @param string Texte à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateTextOnly($theInput, $description = '') {

	$result = preg_match("/^[A-Za-z0-9\ ]+$/", $theInput);
	if ($result) {
		return true;
	} else {
	
		$this->erreures[] = $description;
		return false;
	
	}

}

/**
* Vérifie qu'une chaine soit uniquement composée de texte, de chiffres et d'espaces (sans accents)

* @param string Texte à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateTextOnlyNoSpaces($theInput, $description = '') {

	$result = preg_match("/^[A-Za-z0-9]+$/", $theInput);
	if ($result) {
		return true;
	} else {
	
		$this->erreures[] = $description;
		return false;
	
	}

}


/**
* Vérifie qu'un texte ne dépasse pas un max et soit d'au moins de taille min

* @param string Texte à vérifier
* @param int $min Taille minimale que le texte doit avoir
* @param int $max Taille maximale que le texte doit avoir 
* @return boolean Validation réussie ou non 
* @access public
*/
function validateTextSize($theInput, $min = 0, $max = 20) {

	$theInput = trim($theInput);
	if (mb_strlen($theInput) >= $min && mb_strlen($theInput) <= $max) {
		return true;
	} elseif (mb_strlen($theInput) < $min) {
	
		$this->erreures[] = "Le texte est trop court, minimum ".$min." caractères";
		return false;
	
	} elseif (mb_strlen($theInput) > $max) {
	
		$this->erreures[] = "Le texte est trop long, maximum ".$max." caractères";
		return false;
	
	}

}


/**
* Vérifie qu'une adresse email soit au bon format
*
* @param string Adresse à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateEmail($theInput, $description = '') {

	$result = preg_match("/^[^@ ]+@[^@ ]+\.[^@ \.]+$/", $theInput);
	if ($result) {
		return true;
	} else {
	
		$this->erreures[] = $description;
		return false;
	
	}

}


/**
* Vérifie qu'une valeur soit un nombre
*
* @param string Valeur à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateNumber($theInput, $description = '') {


	if (is_numeric($theInput)) {
		return true;
	} else {
	
		$this->erreures[] = $description;
		return false;
	
	}

}


/**
* Vérifie qu'une date est valable, au format américain
*
* @param string Adresse à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateDate($theInput, $description = '') {

	if (strtotime($theInput) === -1 || $theInput == '') {
		$this->erreures[] = $description;
		return false;
	} else {
		return true;
	}

}

/**
* Vérifie qu'une URL est au bon format, accepte les URL complexes
*
* @param string Adresse à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateURL($url, $description = '') {

if (isset($url) && !preg_match("/^(http:\/\/)/i", $url))
	$url = "http://".$url;

$result = preg_match('#^http\\:\\/\\/[a-z0-9_-]+\.([a-z0-9_-]+\.)?[a-zA-Z]{2,3}#i', $url);
		
if ($result) {
	return true;
} else {
	$this->erreures[] = $description;
	return false;
}
	
//^(ht|f)tp(s?)\:\/\/[a-zA-Z0-9\-\._]+(\.[a-zA-Z0-9\-\._]+){2,}(\/?)([a-zA-Z0-9\-\.\?\,\'\/\\\+&%\$#_]*)?$
//'^(http|https)\://([[:alnum:]_.]+\.)?[a-zA-Z]{2,3}(:[a-zA-Z0-9]*)?/?([a-zA-Z0-9\-\._\?\,\'/\\\+&%\$#\=~])*[^\.\,\)\(\s]$'
}



/**
* Vérifie qu'un fichier uploadé ne soit pas d'un type dangereux et qu'il a bien été
* reçu sur le serveur. Si ce n'est pas le cas détecte l'erreur qui a été engendrée 
*
* @param string Nom du fichier à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateFile($filename, $description = '') {

    if (preg_match("/.exe$|.com$|.bat$|.zip$|.doc$|.txt$/i", $filename['name'])){
      $this->erreures[] = "Ce type de fichier n'est pas autorisé.";
    	return false;
    }

	if (is_uploaded_file($filename['tmp_name'])) {
		
		return true;
			 
	} else {
		
  		switch ($filename['error']) {
  		
  			case 1: // UPLOAD_ERR_INI_SIZE
  				$this->erreures[] = "Le fichier dépasse la limite autorisée par le serveur";
  				return false;
  				break;
  				
  			case 2: // UPLOAD_ERR_FORM_SIZE
  				$this->erreures[] = "Le fichier dépasse la limite autorisée dans le formulaire HTML";
  				return false;
  				break;
  				
  			case 3: // UPLOAD_ERR_PARTIAL
  				$this->erreures[] = "L\'envoi du fichier a été interrompu pendant le transfert";
  				return false;
  				break;
  				
  			case 4: // UPLOAD_ERR_NO_FILE
  				$this->erreures[] = "Le fichier envoyé a une taille nulle";
  				return false;
  				break;
  				
  			default:
  				$this->erreures[] = "Il y a eu un problème de transfert.";
  				return false;
		}
	}

}


/**
* Vérifie qu'une image uploadée soit d'un type de fichier autorisé
*
* @param string Nom du fichier à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validateImage($imageSource, $description = '') {

$allowedmime = array("image/jpeg","image/pjpeg","image/gif","image/png","image/x-png");

if (in_array($imageSource['type'], $allowedmime)) {
	return true;
} else {
	$this->erreures[] = $description;
	return false;
}
		

}


/**
* Vérifie qu'une chaine correspond à un no de téléphone valable, c-à-d est composée 
* seulement de nombres et soit de max 8 car.
*
* @param string No à vérifier
* @param string Message d'erreur à afficher en cas d'échec
* @return boolean Validation réussie ou non 
* @access public
*/
function validatePhone($str, $description = '')
{
	//returns 1 if valid phone number (only numeric string), 0 if not
	
	$new_str = str_replace(" ", "" , $str);
	if (is_numeric($new_str) && mb_strlen($new_str) > 9) {
   		return true;
 	} else {
		$this->erreures[] = $description;
   		return false;
		}
		//mb_ereg('^[0-9[:space:]]{9,}$', $str)
}


/*
function validateDate($date) {

	list($d, $m, $y) = mb_split('[/.-]', $date);
	$bidon = date("d/m/Y", mktime(0,0,0,$m,$d,$y));
	$date = mb_ereg_replace('-', '/', $date);
	
	if ($bidon != $date) {
	
		return false;
		
	} else {
	
		return true;
	
	}


}
*/


/**
 * Informe si le tableau des erreurs en contient au moins une
*
* @return boolean Erreur ou non
* @access public
*/
function founderreures() {

	if (count($this->erreures) > 0) {
		return true;
	} else {
		return false;
	}

}

/**
* Réunis les valeurs du tableau erreurs en une chaine de car., séparées par un delim
*
* @param string $delim Séparateur texte pour les valeurs du tableau
* @return boolean Validation réussie ou non 
* @access public
*/
function listerreures($delim = ' ') {

	return implode($delim, $this->erreures);

}


/**
* Renvoie la dernière valeur qui a été ajoutée au tableau des erreures
*
* @return string Erreur au sommet de la pile
* @access public
*/
function lastError() {
	return $this->erreures[count($this->erreures) - 1];
}

} //class

?>
