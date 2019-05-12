<?php
/**
 * Permet d'ajouter un lieu avec ses détails, 2 photos et un logo, et rédiger sa description
 *
 *
 * Le traitement de suppression est suivi par le traitement d'ajout/edition et le formulaire
 * est à la fin
 *
 * @category   modification d'une table de la base
 * @see lieu.php
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 */

if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}

require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

if (!$videur->checkGroup(6))
{
	header("Location: index.php"); die();
}

require_once($rep_librairies."EditionLieu.class.php");

/* header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");

$cache_lieu = $rep_cache."lieu/";
$cache_lieux = $rep_cache."lieux/";
$cache_even = $rep_cache."evenement/";
$cache_index = $rep_cache."index/"; */

$page_titre = "ajouter/éditer un lieu";
$page_description = "ajouter/éditer un lieu";
$extra_css = array("formulaires", "ajouterLieu_formulaire", "lieu_inc");
$extra_js = array("zebra_datepicker", "jquery.shiftcheckbox");

/*
* action choisie, ID si édition, val pour (dés)activer l'événement
* action "ajouter" par défaut
*/
$tab_actions = array("ajouter", "insert", "editer", "update");
$get['action'] = "ajouter";
$get['idL'] = "idL";
if (isset($_GET['action']))
{
	$get['action'] = verif_get($_GET['action'], "enum", "ajouter", $actions);
}

if (isset($_GET['idL']))
{
	$get['idL'] = verif_get($_GET['idL'], "int", 1);
}

/* VERIFICATION POUR MODIFICATION
* Si ce n'est pas un ajout et que la personne n'est pas l'auteur ni admin ou 'auteur'
*/
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{
	if (!estAuteur($_SESSION['SidPersonne'], $get['idL'], "lieu") && $_SESSION['Sgroupe'] > 6)
	{
		msgErreur("Vous ne pouvez pas modifier ce lieu");
		exit;
	}
}


$champs = array('idpersonne' => '', 'statut' => '', 'nom' => '', 'determinant' => '', 'adresse' => '', 'localite_id' => '', 'region' => '', 'horaire_general' => '',
 'horaire_evenement' => '', 'entree' => '', 'organisateurs' => '', 'categorie' => '', 'telephone' => '', 'URL' => '',
 'email' => '', 'acces_tpg' => '',  "dateAjout" => "");
$fichiers = array('logo' => '', 'photo1' => '', 'image_galerie' => '');
$supprimer = array('image_galerie' => '');

$afficher_form = true;
$message_ok = '';
$form = new EditionLieu('form', $champs, $fichiers, $get);

//TEST
//printr($_POST);
//

$form->setAction($get['action']);
if (isset($_POST['formulaire']) && $_POST['formulaire'] == 'ok')
{
	if ($form->traitement($_POST, $_FILES))
	{
		$_SESSION['lieu_flash_msg']  = $form->getMessage();
        header("Location: lieu.php?idL=".$form->id); die();
	}
}
else if ($get['action'] == 'editer')
{
  	$form->loadValues($get['idL']);
}



$titre_form = "";
$titre_actions = '';


//menu d'actions (activation et suppression)  pour l'auteur > 6 ou l'admin
if (($get['action'] == 'editer' || $get['action'] == 'update'))
{
	$act = "editer&amp;idL=".$get['idL'];
	$titre_form = "Modifier";
	$nom_submit = "Modifier";

	if (estAuteur($_SESSION["SidPersonne"], $get['idL'], "lieu") || $_SESSION['Sgroupe'] < 7)
	{
		//Menu d'actions
		if ($_SESSION['Sgroupe'] < 2)
		{
			$titre_actions = '<ul class="entete_contenu_menu">';
			$titre_actions .= "<li class=\"action_supprimer\">
			<a href=\"".$url_site."supprimer.php?type=lieu&amp;id=".$get['idL']."\">Supprimer</a></li>";
			$titre_actions .= '</ul>';
		}

	}
	else
	{
		msgErreur("Vous ne pouvez pas éditer ce lieu");
		exit;
	}
}
else
{
	$act = 'ajouter';
	$titre_form = "Ajouter";
}

include("includes/header.inc.php");
?>

<div id="contenu" class="colonne">

<div id="entete_contenu">
<h2><?php echo $titre_form; ?> un lieu</h2>
<?php echo $titre_actions; ?>
<div class="spacer"></div>
</div>



<?php
echo $message_ok;

if ($afficher_form)
{
?>

<!-- FORMULAIRE POUR UN LIEU -->

<form  method="post" enctype="multipart/form-data" id="ajouter_editer" class="submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>">

<p>* indique un champ obligatoire</p>

<?php
//echo $form->getValeurs();
?>
<fieldset>
<input type="hidden" name="dateAjout" value="<?php echo $form->getValeur('dateAjout') ?>" />
<input type="hidden" name="idPersonne" value="<?php  echo $form->getValeur('idpersonne') ?>" />
<input type="hidden" name="idLieu" value="<?php echo $get['idL'] ?>" />
<legend>Infos pratiques</legend>

<!-- Nom (text) -->
<p>
<label for="nom">Nom du lieu*</label>
<input type="text" name="nom" id="nom" size="40" maxlength="60" title="Nom du lieu" value="<?php echo $form->getValeur('nom') ?>" required />
<?php
echo $form->getHtmlErreur("nom");
echo $form->getHtmlErreur("nom_existant");
?>
</p>

<p>
<label for="determinant">Déterminant du nom</label>
<input type="text" name="determinant" id="determinant" size="15" maxlength="60" title="Déterminant" value="<?php echo $form->getValeur('determinant') ?>" />
<?php
echo $form->getHtmlErreur("determinant");
?>
</p>

<!-- Adresse (text) -->
<p>
<label for="adresse">Adresse*</label>
<input type="text" name="adresse" id="adresse" size="40" maxlength="80" title="Veuillez indiquer le numéro et la rue" value="<?php echo $form->getValeur('adresse') ?>" required />
<?php
echo $form->getHtmlErreur("adresse");
?>
</p>

<p>
<label for="localite">Localité/quartier</label>
<select name="localite_id" id="localite" class="chosen-select" style="max-width:300px;" required>
<?php
echo "<option value=\"\"></option>";

$sql_prov = '';
if ($get['action'] == 'ajouter' || $get['action'] == 'insert') 
{ 
    $sql_prov = " AND canton != 'fr' ";     
}


$req = $connector->query("
SELECT id, localite, canton FROM localite WHERE id!=1 ".$sql_prov." ORDER BY canton, localite "
 );



$select_canton = '';
while ($tab = $connector->fetchArray($req))
{
    
    if ($tab['canton'] != $select_canton)
    {       
        if (!empty($select_canton))
            echo "</optgroup>"; 
        
        echo "<optgroup label=''>"; // ".$glo_regions[strtolower($tab['canton'])]."
    }
    
    

    
	echo "<option ";
	
	if (($form->getValeur('localite_id') == $tab['id'] && empty($form->getValeur('quartier'))) || ((isset($_POST['localite_id']) && $tab['id'] == $_POST['localite_id'])))
	{
		echo 'selected="selected" ';
	}	
	
	echo "value=\"".$tab['id']."\">".$tab['localite']."</option>";

    // Genève quartiers    
    if ($tab['id'] == 44)
    {
        
       // si erreur formulaire
        $champs_quartier = '';
        $loc_qua = explode("_", $form->getValeur('localite_id'));
        if (!empty($loc_qua[1]))
           $champs_quartier = $loc_qua[1];
        
        // si chargement even existant
        if (!empty($form->getValeur('quartier')))
            $champs_quartier = $form->getValeur('quartier');        
        
        foreach ($glo_tab_quartiers2['ge'] as $no => $quartier)
       {  
               echo "<option ";

               if ($champs_quartier == $quartier)
               {
                       echo 'selected="selected" ';
               }	

               echo " value=\"44_".$quartier."\">Genève - ".$quartier."</option>";

       }       

    }        
        
     $select_canton = $tab['canton'];
}
?>
    <optgroup label="Ailleurs">    
<?php
    foreach ($glo_tab_ailleurs as $id => $nom)
   {  
           echo "<option ";

           if (($form->getValeur('region') == $id) || ((isset($_POST['localite_id']) && $id == $_POST['localite_id']))
                  ) // $form->getValeur('quartier') 
           {
                   echo ' selected="selected" ';
           }	

           echo " value=\"".$id."\">".$nom."</option>";

   }  
?>
    

   
    </optgroup>    
    

</select>
<?php
echo $form->getHtmlErreur("localite_id");
?>
</p>


<?php if (0) { //$form->getValeur('region') == 'ge') { ?>
<p>
<label for="quartier">Quartier</label>
<select name="quartier" id="quartier" class="chosen-select" style="max-width:300px;" required>
<?php
$m = 1;
echo "<option></option><optgroup label=\"Genève\">";
while ($glo_tab_quartiers[$m] != "communes")
{
    echo "<option ";
	if ($glo_tab_quartiers[$m] == $form->getValeur('quartier')) { echo "selected=\"selected\"";}
	echo " value=\"".$glo_tab_quartiers[$m]."\">".$glo_tab_quartiers[$m]."</option>";
	$m++;
}
echo "</optgroup>
<optgroup label=\"Communes\">";
$m++;
while ($glo_tab_quartiers[$m] != "ailleurs")
{
      echo "<option ";
	  if ($glo_tab_quartiers[$m] == $form->getValeur('quartier')) { echo "selected=\"selected\""; }
	  echo " value=\"".$glo_tab_quartiers[$m]."\">".$glo_tab_quartiers[$m]."</option>";
	$m++;
}

echo "</optgroup>
<optgroup label=\"Ailleurs\">";
$m++;
while ($m < sizeof($glo_tab_quartiers))
{
      echo "<option ";

	  if ($glo_tab_quartiers[$m] == $form->getValeur('quartier'))
	  {
		echo "selected=\"selected\"";
	  }
	  echo " value=\"".$glo_tab_quartiers[$m]."\">".$glo_tab_quartiers[$m]."</option>";
	$m++;
}
echo "</optgroup>";

?>
</select>
<?php
echo $form->getHtmlErreur("quartier");
?>
</p>
<?php } ?>





<!-- Horaire général(textarea) -->
<p>
<label for="horaire_general">Horaire général</label>
<textarea name="horaire_general" id="horaire_general" cols="25" rows="3" tabindex="4" title="Quels sont les horaires typiques d'une soirée ?">
<?php echo $form->getValeur('horaire_general') ?></textarea>
<?php
echo $form->getHtmlErreur("horaire_general");
?>
</p>
<div class="guideChamp">Jours et heures d'ouverture habituels</div>

<!--
<p>
<label for="horaire_evenement">Horaire d'un événement</label>
<input type="text" name="horaire_evenement" id="horaire_evenement" size="30" maxlength="60" tabindex="5" title="Prix d'entrée habituel" value="" />
</p>
-->
<div class="spacer"></div>

<?php if (0) { ?>
<!-- Entrée (text) -->
<p>
<label for="entree">Entrée d'un événement</label>
<input type="text" name="entree" id="entree" size="15" maxlength="60" tabindex="5"
title="Prix d'entrée habituel" value="<?php echo $form->getValeur('entree') ?>" />
<?php
echo $form->getHtmlErreur("entree");
?>
</p>
<div class="guideChamp">Écrivez ici quels sont les tarifs habituels d'un événement dans ce lieu.</div>
<div class="spacer"></div>
<?php } ?>

<!-- Telephone (text) -->
<p>
<label for="telephone">Téléphone</label>
<input type="text" name="telephone" id="telephone" size="15" maxlength="40" tabindex="6"
title="Numéro de téléphone" value="<?php echo $form->getValeur('telephone') ?>" onblur="validerTelephone('telephone', 'false');" />
<?php
echo $form->getHtmlErreur("telephone");
?>
</p>

<!-- URL (text) -->
<p>
<label for="URL">Site web</label>
<input type="text" name="URL" id="URL" size="40" maxlength="80" tabindex="7" title="Page web du lieu"
value="<?php echo $form->getValeur('URL') ?>"  onblur="validerURL('URL', 'false');" />
<?php
echo $form->getHtmlErreur("URL");
?>
</p>

<p>
<label for="email">E-mail</label>
<input type="text" name="email" id="email" size="40" maxlength="40" tabindex="8" title="Adresse e-mail du lieu"
 value="<?php echo $form->getValeur('email') ?>" onblur="validerEmail('email', 'false');" />
<?php
echo $form->getHtmlErreur("email");
?>
</p>

<?php
$tab_organisateurs_even = array();
if ($get['action'] == "editer" || $get['action'] == "update")
{

	$sql = "SELECT organisateur.idOrganisateur, nom
FROM organisateur, lieu_organisateur
WHERE lieu_organisateur.idLieu=".$get['idL']." AND
 organisateur.idOrganisateur=lieu_organisateur.idOrganisateur
 ORDER BY date_ajout DESC";

 $req = $connector->query($sql);

	if ($connector->getNumRows($req))
	{
		//echo "<table class=\"fichiers_associes\"><tr><th>nom</th><th>".$iconeSupprimer."</th></tr>";
		while ($tab = $connector->fetchArray($req))
		{
			$tab_organisateurs_even[] = $tab['idOrganisateur'];
/* 			echo "<tr><td><a href=\"".$url_site."organisateur.php?idO=".$tab['idOrganisateur']."\">"
			.$tab['nom']."</a>
			</td>
			<td><input type=\"checkbox\" name=\"sup_organisateur[]\" value=\"".$tab['idOrganisateur']."\" /></td></tr>"; */
		}
		//echo "</table>";
	}

}
?>
<p>
<label for="organisateurs">Organisateur(s)</label>
<select name="organisateurs[]" id="organisateurs" data-placeholder="Choisissez un ou plusieurs organisateurs" class="chosen-select" multiple title="Un organisateur dans base de données de La décadanse" style="max-width:400px;">
<?php
echo "<option value=\"0\">&nbsp;</option>";
$req = $connector->query("
SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8_general_ci"
 );

while ($tab = $connector->fetchArray($req))
{
	echo "<option ";
	
	if ((isset($_POST['organisateurs']) && in_array($tab['idOrganisateur'], $_POST['organisateurs'])) || in_array($tab['idOrganisateur'], $tab_organisateurs_even))
	{
		echo 'selected="selected" ';

	}	
	
	echo "value=\"".$tab['idOrganisateur']."\">".$tab['nom']."</option>";
}
?>
</select>
<div class="guideChamp">Les personnes membres de ces organisateurs pourront modifier <strong>tous</strong> les événements se déroulant dans ce lieu</div>
</p>
<?php echo $form->getHtmlErreur("doublon_organisateur"); ?>




</fieldset>

<!-- Catégorie (checkbox) -->
<fieldset>
<legend>Catégorie(s)</legend>
<ul class="checkbox" style="list-style-type: none">
<?php
	foreach ($glo_categories_lieux as $cat => $cat_nom)
	{
		echo '<li class="checkbox"><label for="categorie_'.replace_accents($cat).'" class="checkbox">'.$cat_nom.'</label>' .
				'<input type="checkbox" id="categorie_'.replace_accents($cat).'" name="categorie[]" value="'.$cat.'" class="checkbox" ';
		$tab_cat_lieu = $form->getValeur('categorie');
		if (in_array($cat, $tab_cat_lieu))
		{
			echo  'checked="checked" ';
		}
		echo "/></li>";
	}
?>

</ul>
<?php
echo $form->getHtmlErreur("categorie");
?>
</fieldset>




<fieldset>
<legend>Images</legend>
<input type="hidden" name="MAX_FILE_SIZE" value="2097152" /> 
<div class="guideChamp">Format JPEG, PNG et GIF; max. 2 Mo</div>

<!-- Logo (file) -->
<p>
<label for="Logo">Logo</label>
<input type="file" name="logo" id="Logo" tabindex="18" title="Logo du lieu qui s'affichera à gauche du titre" size="25" />

<?php
echo $form->getHtmlErreur("logo");


if (isset($get['idL']) && !empty($form->getValeur('logo')) && $form->getErreur("logo") == '')
{
	$imgInfo = getimagesize($rep_images_lieux.$form->getValeur('logo'));

	$lien_popup = lien_popup($IMGlieux.$form->getValeur('logo')."?".filemtime($rep_images_lieux.$form->getValeur('logo')), "Logo", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$IMGlieux."s_".$form->getValeur('logo')."?".filemtime($rep_images_lieux.$form->getValeur('logo'))."\" alt=\"Logo pour ".securise_string($form->getValeur('nom'))."\" />"
	);
	$checked = '';
	$tab_sup = $form->getSupprimer();
	if (in_array('logo', $tab_sup) && $form->getNbErreurs() > 0)
	{
		$checked = ' checked="checked"';
	}
	?>

	<input type="hidden" name="logo_existant" value="<?php echo $form->getValeur('logo'); ?>" />
	<div class="supImg">
	<?php echo $lien_popup; ?>
	<div>
		<label for="supprimer_logo" class="continu">Supprimer</label>
		<input type="checkbox" name="supprimer[]" id="supprimer_logo" value="logo" class="checkbox" <?php echo $checked; ?> />

		</div>
	</div>

	<?php
}
?>
</p>

<!-- Photo1 (file) -->
<p>
<label for="photo1">Photo</label>
<input type="file" name="photo1" id="photo1" tabindex="16" title="Photo qui s'affichera en haut à droite" size="25" />


<?php
echo $form->getHtmlErreur("photo1");

//affichage de l'image existante
if (isset($get['idL']) && !empty($form->getValeur('photo1')) && $form->getErreur("logo") == '')
{
	$imgInfo = getimagesize($rep_images_lieux.$form->getValeur('photo1'));

	$lien_popup = lien_popup($IMGlieux.$form->getValeur('photo1')."?".filemtime($rep_images_lieux.$form->getValeur('photo1')), "Photo 1", $imgInfo[0]+20, $imgInfo[1]+20,
	"<img src=\"".$IMGlieux."s_".$form->getValeur('photo1')."?".filemtime($rep_images_lieux.$form->getValeur('photo1'))."\" alt=\"photo pour ".securise_string($form->getValeur('nom'))."\" />"
	);
	$checked = '';
	$tab_sup = $form->getSupprimer();
	if (in_array('photo1', $tab_sup) && $form->getNbErreurs() > 0)
	{
		$checked = ' checked="checked"';
	}
	?>

	<input type="hidden" name="photo1_existant" value="<?php echo $form->getValeur('photo1'); ?>" />
	<div class="supImg">
	<?php echo $lien_popup; ?>
	<div>
		<label for="supprimer_photo1" class="continu">Supprimer</label>
		<input type="checkbox" name="supprimer[]" id="supprimer_photo1" value="photo1" class="checkbox" <?php echo $checked; ?> />

		</div>
	</div>

	<?php
}
?>
</p>


<?php if (0) { ?>
<p>
<label for="document">Document</label>
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $CONF_maxfilesize ?>" />
<input type="file" name="document" id="document" size="25" accept="<?php echo implode(", ", $glo_mimes_documents_acceptes) ?>" title="Choisissez une image pour illustrer l'événement" tabindex="12" class="fichier" />
<?php echo $form->getHtmlErreur("document"); ?>
</p>
<div class="guideChamp">JPG, GIF, PNG, DOC, PDF</div>

<p>
<label for="document_description">Nom du document</label>
<input type="text" name="document_description" id="document_description" size="20" value="<?php echo securise_string($champs['document_description']) ?>" />
<?php
echo $form->getHtmlErreur("document_description");
?>
</p>
<div class="spacer"></div>

<?php } ?>


<?php


if ($get['action'] == "editer")
{

	$sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension, dateAjout
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=".$get['idL']." AND type='document' AND
 fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
 ORDER BY dateAjout DESC";

 $req_docu = $connector->query($sql_docu);
	if ($connector->getNumRows($req_docu))
	{
		echo "<table class=\"fichiers_associes\"><tr><th>nom</th><th>ajouté le</th><th>".$iconeSupprimer."</th></tr>";
		while ($tab_docu = $connector->fetchArray($req_docu))
		{
			$nom_fichier = $tab_docu['idFichierrecu'].".".$tab_docu['extension'];
			echo "<tr><td><a href=\"".$url_fichiers_even.$nom_fichier."\">"
			.$tab_docu['description']."</a></td><td>".date_iso2app($tab_docu['dateAjout'])."</td>
			<td><input type=\"checkbox\" name=\"supprimer_document[]\" value=\"".$tab_docu['idFichierrecu'].".".$tab_docu['extension']."\" /></td></tr>";
		}
		echo "</table>";
	}

}
?>

<p>
<label for="image_galerie">Galerie</label>
<input type="hidden" name="MAX_FILE_SIZE" value="<?php $CONF_maxfilesize ?>" />
<input type="file" name="image_galerie" id="image_galerie" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif"  class="fichier" />
</p>
<div class="guideChamp">Seul les formats JPEG, PNG et GIF sont acceptés</div>
<div class="spacer"></div>
<?php
echo $form->getHtmlErreur("image_galerie");

if ($get['action'] == "editer")
{

	$sql_galerie = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension, dateAjout
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=".$get['idL']." AND type='image' AND fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
 ORDER BY dateAjout DESC";

	$req_galerie = $connector->query($sql_galerie);

	if ($connector->getNumRows($req_galerie))
	{
		echo "<table class=\"fichiers_associes\"><tr><th>nom</th><th>ajouté le</th><th>".$iconeSupprimer."</th></tr>";
		while ($tab_galerie = $connector->fetchArray($req_galerie))
		{
			$nom_fichier = $tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
			echo "<tr><td><img src=\"".$url_images_lieu_galeries."s_".$nom_fichier."\" /></td>
			<td>".date_iso2app($tab_galerie['dateAjout'])."</td>
			<td><input type=\"checkbox\" name=\"supprimer_galerie[]\" value=\"".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension']."\" /></td></tr>";
		}
		echo "</table>";
	}

}
?>

</fieldset>


<fieldset>
<?php

//menu d'actions (activation et suppression)  pour l'auteur > 6 ou l'admin
if (($get['action'] == 'editer' || $get['action'] == 'update') &&
((estAuteur($_SESSION['SidPersonne'], $get['idL'], "lieu") && $_SESSION['Sgroupe'] < 6) || $_SESSION['Sgroupe'] <= 4))
{
?>


<legend>Statut</legend>
<ul class="radio">
<?php
foreach ($statuts_lieu as $s)
{
	$coche = '';
	$statut = $form->getValeur('statut');

	if ($s == $statut)
	{
		$coche = 'checked="checked"';
	}
	echo '<li class="listehoriz"><input type="radio" name="statut" value="'.$s.'" '.$coche.' id="genre_'.$s.'" title="statut de l\'événement" class="radio_horiz" /><label class="continu" for="genre_'.$s.'">'.$s.'</label></li>';
}
?>
</ul>
<?php
echo $form->getHtmlErreur("statut");
?>

<?php
}
else
{
?>

<input type="hidden" name="statut" value="actif" id="statut_actif" title="statut" />

<?php
}
?>
</fieldset>

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" value="Enregistrer" tabindex="20" title="Enregistrer le lieu" class="submit" />
</p>
</form>

<?php
} // if action_terminee
?>


</div>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">

<?php include("includes/navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("includes/footer.inc.php");
?>
