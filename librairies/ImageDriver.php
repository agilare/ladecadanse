<? /**/ ?>
<?php
/**
 * Traite des images envoyées par upload via un champ 'file' de formulaire et 
 * les stocke dans images/
 * Ce sont des flyers, photos et logos envoyés par les membres pour illustrer 
 * un événement, une brêve ou un lieu
 * 
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see        ajouterBreve.php, ajouterEvenement.php, ajouterLieu.php
 */

require_once ('SystemComponent.php');

class ImageDriver extends SystemComponent {

/**
 * La liste des formats d'images acceptés
 * 
 * @var array string
 */
var $formats;

/**
 * Nom du répertoire des images du site
 * 
 * @var string
 */
var $IMGracine;

/**
 * Nom du répertoire des images des lieux
 * 
 * @var string
 */
var $IMGlieux;


/**
* Constructeur
* Crée le nom des répertoires d'images devant reçevoir les images envoyées par les visiteurs
* Établit les formats d'image acceptés 
*
* @access public
* @see config/reglages.php
*/
function ImageDriver()
{
	global $rep_images;
	$this->IMGracine = $rep_images;
	$this->IMGlieux = $rep_images."lieux/";
	$this->IMGlieuxGaleries = $this->IMGlieux."galeries/";
	$this->formats = array('image/jpeg', 'image/pjpeg','image/gif','image/png', 'image/x-png');
}

/**
* Renvoye la list des formats acceptés
* 
* @access public
* @return array La liste des formats acceptés
*/	
function getFormats()
{
	return $this->formats;
}


 /**
	* Fonction principale, redimensionne une image reçue et crée deux nouvelles images
   * une réduite, une mini qui sont ensuite stockée dans le répertoire images du site
   *
   * @access public
   * @param string $imageSource Adresse de l'image uploadée
   * @param string $imageCreated Nom à donner à l'image réduite crée
   * @param string $typeIMG (breves, evenements, lieux) Genre d'utilisation de l'image
   * @param int $maxWidth Largeur maximale voulue pour l'image réduite
   * @param int $maxHeight Hauteur maximale voulue pour l'image réduite
   * @param int $maxWidth_s Largeur maximale voulue pour l'image mini
   * @param int $maxWidth_s Hauteur maximale voulue pour l'image mini
   * @param string $selon ('', w, h) Pour imposer la largeur ou la hauteur de l'image réduite selon $maxWidth ou $maxHeigth
   * @param string $selon_s ('', w, h) Pour imposer la largeur ou la hauteur de l'image mini selon $maxWidth ou $maxHeigth
   * @return string $msgErreur Messag s'il y a eu une erreur
   * @see        ajouterBreve.php, ajouterEvenement.php, ajouterLieu.php
   */
function processImage($imageSource, $imageCreated, $typeIMG, 
 $maxWidth, $maxHeigth,
 $maxWidth_s = 100, $maxHeigth_s = 150, 
 $selon = '', $selon_s = '')
{
	
	/*
	* Si c'est un flyer, le répertoire de sauvegarde est à la racine de 'image'
	*/
	if ($typeIMG == "evenements")
	{
		$typeIMG = '';
	}
	else
	{
		$typeIMG .= '/';
	}
	
	/*
	* Création de l'image au format repéré à partir du fichier uploadé
	*/
	if (strstr($imageSource['type'], "jpeg"))
	{
		$img = ImageCreateFromJpeg($imageSource['tmp_name']);
	} 
	elseif (strstr($imageSource['type'], "gif"))
	{
		$img = ImageCreateFromGif($imageSource['tmp_name']);
	} 
	elseif (strstr($imageSource['type'], "png"))
	{
		$img = ImageCreateFrompng($imageSource['tmp_name']);
	} 
	else 
	{
		return "Le format de l'image '".$imageSource['type']."' n'est pas accepté";
	}
	
	//Initialisation des variables qui recevront les nouvelles dimensions
	//Image mini = image réduite = image d'origine
	$imgX2_t = $imgX2_s = $imgX2 = $imgX = imagesx($img);
	$imgY2_t = $imgY2_s = $imgY2 = $imgY = imagesy($img);
	
	//si l'image est en "portrait" ou si une largeur est imposée
	if ($selon == 'w' || ($imgY > $imgX && empty($selon)))
	{	
		//IMAGE REDUITE : si une largeur max a été définie et si la largeur originale dépasse effectivement
		//la largeur max, réduction vers maxWidth et la hauteur proportionnellement
		if ($maxWidth > 0 && ($imgX - $maxWidth) > 0)
		{	
			$imgX2 = $maxWidth;
				
			$diffX = $imgX - $maxWidth; //diminution
			$propX = $diffX / $imgX; //proportion de la diminution par rapport à la taille originale
			$imgY2 = (int)($imgY * (1 - $propX)); //rédution de Y selon la proportion
	
		}
		
		//IMAGE MINI : par défaut ou si la reduction selon la largeur est choisie
		if ((empty($selon_s) || $selon_s == 'w') && ($imgX - $maxWidth_s) > 0)
		{
			$imgX2_s = $maxWidth_s;
			
			$diffX_s = $imgX - $maxWidth_s;
			$propX_s = $diffX_s / $imgX;
			$imgY2_s = (int)($imgY * (1 - $propX_s));			
		 }
		 elseif ($selon_s == 'h' && ($imgY - $maxHeigth_s) > 0)
		 {
			$imgY2_s = $maxHeigth_s;
			
			$diffY_s = $imgY - $maxHeigth_s;
			$propY_s = $diffY_s / $imgY;
			$imgX2_s = (int)($imgX * (1 - $propY_s));	
		}
		
	//si l'image est en "paysage" ou si une hauteur est imposée
	} 
	elseif ($selon == 'h' || ($imgY <= $imgX && empty($selon)))
	{
	
		//IMAGE REDUITE
		if ($maxHeigth > 0 && ($imgY - $maxHeigth) > 0)
		{	
			$imgY2 = $maxHeigth;
			
			$diffY = $imgY - $maxHeigth;
			$propY = $diffY / $imgY;
			$imgX2 = (int)($imgX * (1 - $propY));
		
		}
		
		//IMAGE MINI
		//la largeur est suivie si demandé
		if ($selon_s == 'w' && ($imgX - $maxWidth_s) > 0)
		{
		
			$imgX2_s = $maxWidth_s;
			
			$diffX_s = $imgX - $maxWidth_s;
			$propX_s = $diffX_s / $imgX;
			$imgY2_s = (int)($imgY * (1 - $propX_s));
				
		//par défaut ou si la reduction selon la largeur est choisie
		 } 
		 elseif ((empty($selon_s) || $selon_s == 'h') && ($imgY - $maxHeigth_s) > 0)
		 {
		
			$imgY2_s = $maxHeigth_s;
			$diffY_s = $imgY - $maxHeigth_s;
			$propY_s = $diffY_s / $imgY;
			
			$imgX2_s = (int)($imgX * (1 - $propY_s));		

		}
	}
	
	/**
	* Création des images réduite et mini selon les dimensions souhaitées
	*/
	$img2 = ImageCreateTrueColor($imgX2, $imgY2);
	$img2_s = ImageCreateTrueColor($imgX2_s, $imgY2_s);
	$img2_t = ImageCreateTrueColor($imgX2_t, $imgY2_t);
	
	//REDIMENSION
	//Réduite : si maxWidth ou maxHeigth valent -1, l'image originale est copiée sans redimensionnement
	if ($maxWidth > 0 && $maxHeigth > 0)
	{
		ImageCopyResampled($img2, $img, 0, 0, 0, 0, $imgX2, $imgY2, $imgX, $imgY);
	}
	else
	{
		$img2 = $img;
	}
	
	//MINI
	ImageCopyResampled($img2_s, $img, 0, 0, 0, 0, $imgX2_s, $imgY2_s, $imgX, $imgY);
	
	
	//Pour les FLYERS, rognage de l'image mini, à gauche et à droite si l'image était trop large
	//en haut et en bas si l'image était trop haute
	$imageMini = $img2_s;
	if (!strstr($typeIMG, "lieux"))
	{
		if ($imgX2_s > $maxWidth_s)
		{
			$img2_sc = ImageCreateTrueColor($maxWidth_s, $imgY2_s);
			ImageCopy($img2_sc, $img2_s, 0, 0, ($imgX2_s/4), 0, $maxWidth_s, $imgY2_s);
			$imageMini = $img2_sc;	
		}
		elseif ($imgY2_s > $maxHeigth_s)
		{
			$img2_sc = ImageCreateTrueColor($imgX2_s, $maxHeigth_s);
			ImageCopy($img2_sc, $img2_s, 0, 0, 0, 0, $imgX2_s, $maxHeigth_s);
			$imageMini = $img2_sc;
		}
	}
	
	//echo $this->IMGracine.$typeIMG."s_".$imageCreated;
	
	//CREATION DU FICHIER MINI au nom de par ex. "s_2006-02-20.jpg"
	$fp = fopen($this->IMGracine.$typeIMG."s_".$imageCreated, "w");
	fclose($fp);
	
	//ENVOI DES IMAGES créées précédement vers :
	//Réduite -> fichier uploadé temporaire
	//Mini -> fichier créé avec fopen
    $messageErreur = "Échec dans la création des images";
    
	if (strstr($imageSource['type'], "jpeg")) {
	
		imagejpeg($img2, $imageSource['tmp_name']);	
		imagejpeg($imageMini, $this->IMGracine.$typeIMG."s_".$imageCreated);
			
	} elseif (function_exists("imagegif") && strstr($imageSource['type'], "gif")) {
	
		imagegif ($img2, $imageSource['tmp_name']);
		imagegif ($imageMini, $this->IMGracine.$typeIMG."s_".$imageCreated);
		
	} elseif (strstr($imageSource['type'], "png")) {
	
		imagepng($img2, $imageSource['tmp_name']);	
		imagepng($imageMini, $this->IMGracine.$typeIMG."s_".$imageCreated);	
		
	} elseif (!function_exists("imagegif")) {
        $messageErreur = $messageErreur.", le format GIF n'est pas supporté.";
		return $messageErreur;
		
	}
	
	//déplacement du fichier temporaire uploadé et redimensionné vers le fichier final
	move_uploaded_file($imageSource['tmp_name'], $this->IMGracine.$typeIMG.$imageCreated);
}




} //class
?>
