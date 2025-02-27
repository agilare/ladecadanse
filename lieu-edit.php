<?php

require_once("app/bootstrap.php");

use Ladecadanse\Security\SecurityToken;
use Ladecadanse\LieuEdition;
use Ladecadanse\Utils\Text;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\HtmlShrink;

if (!$videur->checkGroup(8))
{
	header("Location: index.php"); die();
}

$page_titre = "ajouter/éditer un lieu";
$extra_css = ["formulaires", "lieu_inc"];

$tab_actions = ["ajouter", "insert", "editer", "update"];
$get['action'] = "ajouter";
$get['idL'] = "idL";
if (isset($_GET['action']))
{
	$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", "ajouter", $actions);
}

if (isset($_GET['idL']))
{
	$get['idL'] = Validateur::validateUrlQueryValue($_GET['idL'], "int", 1);
}

/* VERIFICATION POUR MODIFICATION
* Si ce n'est pas un ajout et que la personne n'est pas l'auteur ni admin ou 'auteur'
*/
if ($get['action'] != "ajouter" && $get['action'] != "insert")
{
	if (!(isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']))))
	{
		HtmlShrink::msgErreur("Vous ne pouvez pas modifier ce lieu");
		exit;
	}
}
else if ($_SESSION['Sgroupe'] > 6)
{
    HtmlShrink::msgErreur("Vous ne pouvez pas ajouter de lieu");
    exit;
}

$champs = ['idpersonne' => '', 'statut' => '', 'nom' => '', 'determinant' => '', 'adresse' => '', 'localite_id' => '', 'region' => '', 'horaire_general' => '', 'organisateurs' => '', 'categorie' => '', 'URL' => '', "dateAjout" => ""];
$fichiers = ['logo' => '', 'photo1' => '', 'image_galerie' => ''];
$supprimer = ['image_galerie' => ''];

$afficher_form = true;
$message_ok = '';
$form = new LieuEdition('form', $champs, $fichiers);

$form->setAction($get['action']);
if (isset($_POST['formulaire']) && $_POST['formulaire'] == 'ok')
{
    if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
    {
        echo "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer";
        exit;
    }

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

	if ($_SESSION['Sgroupe'] <= 6 || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']))
	{
		//Menu d'actions
		if ($_SESSION['Sgroupe'] < 2)
		{
			$titre_actions = '<ul class="entete_contenu_menu">';
			$titre_actions .= "<li class=\"action_supprimer\">
			<a href=\"/multi-suppr.php?type=lieu&amp;id=".$get['idL']."&token=".SecurityToken::getToken()."\">Supprimer</a></li>";
			$titre_actions .= '</ul>';
		}

	}
	else
	{
		HtmlShrink::msgErreur("Vous ne pouvez pas éditer ce lieu");
		exit;
	}
}
else
{
	$act = 'ajouter';
	$titre_form = "Ajouter";
}

include("_header.inc.php");
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

<form  method="post" enctype="multipart/form-data" id="ajouter_editer" class="js-submit-freeze-wait" action="<?php echo basename(__FILE__)."?action=".$act; ?>">

<p>* indique un champ obligatoire</p>
<p>Si vous souhaitez modifier le nom du lieu, ses catégories, ses organisateurs ou les photos de la galerie, merci de nous <a href="/contacteznous.php">contacter</a></p>
<fieldset>

    <legend>Infos pratiques</legend>

    <p>
        <label for="nom">Nom du lieu*</label>
            <input type="text" name="nom" id="nom" size="40" maxlength="60" value="<?php echo sanitizeForHtml($form->getValeur('nom')) ?>" required <?php if ($_SESSION['Sgroupe'] > 6)
    { ?>readonly class="read-only"<?php } ?> />
    <?php
        echo $form->getHtmlErreur("nom");
        echo $form->getHtmlErreur("nom_existant");
        ?>
    </p>

    <?php if ($_SESSION['Sgroupe'] <= 6) { ?>
        <p>
            <label for="determinant">Préposition du nom</label>
                    <input type="text" name="determinant" id="determinant" size="15" maxlength="60" value="<?php echo sanitizeForHtml($form->getValeur('determinant')) ?>"  />
                    <?php
            echo $form->getHtmlErreur("determinant");
            ?>
        </p>
    <?php } else { ?>
                <input type="hidden" name="determinant" value="<?php echo sanitizeForHtml($form->getValeur('determinant')) ?>"  />
            <?php } ?>
    <p>
        <label for="adresse">Adresse*</label>
            <input type="text" name="adresse" id="adresse" size="40" maxlength="80" title="numéro et rue" value="<?php echo sanitizeForHtml($form->getValeur('adresse')) ?>" required />
            <?php
        echo $form->getHtmlErreur("adresse");
        ?>
    </p>

<p>
<label for="localite">Localité/quartier*</label>&nbsp;<select name="localite_id" id="localite" class="chosen-select" style="max-width:300px;" required data-placeholder="Tapez le nom...">
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
    <p>
        <label for="horaire_general">Jours et heures d’ouverture habituels</label>
            <textarea name="horaire_general" id="horaire_general" cols="25" rows="3" tabindex="4" title="Quels sont les horaires typiques d'une soirée ?"><?php echo sanitizeForHtml($form->getValeur('horaire_general')) ?></textarea>
            <?php
        echo $form->getHtmlErreur("horaire_general");
        ?>
    </p>
        <div class="spacer"></div>

    <!-- URL (text) -->
    <p>
    <label for="URL">Site web</label>
    <input type="url" name="URL" id="URL" size="50" maxlength="80" tabindex="7" title="Page web du lieu"
           value="<?php echo sanitizeForHtml($form->getValeur('URL')) ?>"  />
        <?php
    echo $form->getHtmlErreur("URL");
    ?>
        </p>

    <?php
    $lieu_organisateurs = [];
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
                $lieu_organisateurs[] = $tab['idOrganisateur'];
    /* 			echo "<tr><td><a href=\"/organisateur.php?idO=".$tab['idOrganisateur']."\">"
                .$tab['nom']."</a>
                </td>
                <td><input type=\"checkbox\" name=\"sup_organisateur[]\" value=\"".$tab['idOrganisateur']."\" /></td></tr>"; */
            }
            //echo "</table>";
        }

    }
    ?>
    <?php
    if ($_SESSION['Sgroupe'] <= 6)
    {
    ?>

    <p>
        <label for="organisateurs">Organisateur(s)</label>
        <select name="organisateurs[]" id="organisateurs" data-placeholder="Choisissez un ou plusieurs organisateurs" class="chosen-select" multiple title="Un organisateur dans base de données de La décadanse" readonly style="max-width:400px;">
        <?php
        echo "<option value=\"0\">&nbsp;</option>";
        $req = $connector->query("
        SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
         );

        while ($tab = $connector->fetchArray($req))
        {
            echo "<option ";

            if ((isset($_POST['organisateurs']) && in_array($tab['idOrganisateur'], $_POST['organisateurs'])) || in_array($tab['idOrganisateur'], $lieu_organisateurs))
            {
                echo 'selected="selected" ';

            }

            echo "value=\"" . $tab['idOrganisateur'] . "\">" . sanitizeForHtml($tab['nom']) . "</option>";
        }
        ?>
        </select>
        <div class="guideChamp">Les personnes membres de ces organisateurs pourront modifier ce lieu ainsi que tous les événements s’y déroulant</div>
    </p>
    <?php echo $form->getHtmlErreur("doublon_organisateur"); ?>
    <?php } else {

        foreach ($lieu_organisateurs as $lo) {
        ?>
        <input type="hidden" name="organisateurs[]" value="<?php echo $lo ?>">
    <?php
    } } ?>
    </fieldset>

    <?php
    $cat_readonly = '';
    $cat_class = '';
    if ($_SESSION['Sgroupe'] > 6)
    {
        foreach ($glo_categories_lieux as $cat => $cat_nom)
        {
            $tab_cat_lieu = $form->getValeur('categorie');
            if (in_array($cat, $tab_cat_lieu))
            {
                 echo '<input type="hidden" name="categorie[]" value="'.$cat.'" '.$cat_class.' >';
            }
        }

    }
    else
    {
    ?>
    <fieldset>
        <legend>Catégorie(s)</legend>
        <ul class="checkbox" style="list-style-type: none">
        <?php
            foreach ($glo_categories_lieux as $cat => $cat_nom)
            {
                echo '<li class="checkbox"><label for="categorie_'.Text::stripAccents($cat).'" class="checkbox">'.$cat_nom.'</label>' .
                        '<input type="checkbox" id="categorie_'.Text::stripAccents($cat).'" name="categorie[]" value="'.$cat.'" class="checkbox '.$cat_class.'" ';
                $tab_cat_lieu = $form->getValeur('categorie');
                if (in_array($cat, $tab_cat_lieu))
                {
                    echo  'checked="checked" ';
                }
                echo " $cat_readonly /></li>";
            }
        ?>

        </ul>
                <?php
                echo $form->getHtmlErreur("categorie");
        ?>
    </fieldset>
    <?php } ?>



    <fieldset>
        <legend>Images</legend>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php UPLOAD_MAX_FILESIZE ?>" />
        <div style="margin-left: 0.8em;font-weight: bold">Formats JPEG, PNG ou GIF; max. 2 Mo</div>

        <p>
            <label for="Logo">Logo</label>
            <input type="file" name="logo" id="Logo" class="js-file-upload-size-max" tabindex="18" title="Logo du lieu qui s'affichera à gauche du titre" size="25" />

            <?php
            echo $form->getHtmlErreur("logo");


            if (isset($get['idL']) && !empty($form->getValeur('logo')) && $form->getErreur("logo") == '')
            {
                $imgInfo = getimagesize($rep_uploads_lieux.$form->getValeur('logo'));

                $checked = '';
                $tab_sup = $form->getSupprimer();
                if (in_array('logo', $tab_sup) && $form->getNbErreurs() > 0)
                {
                    $checked = ' checked="checked"';
                }
                ?>

                <input type="hidden" name="logo_existant" value="<?php echo $form->getValeur('logo'); ?>" />
                <div class="supImg">
                            <?php echo "<img src=\"" . $url_uploads_lieux . "s_" . $form->getValeur('logo') . "?" . filemtime($rep_uploads_lieux . $form->getValeur('logo')) . "\" />"; ?>
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
        <input type="file" name="photo1" id="photo1" class="js-file-upload-size-max" tabindex="16" title="Photo qui s'affichera en haut à droite" size="25" />


        <?php
        echo $form->getHtmlErreur("photo1");

        //affichage de l'image existante
        if (isset($get['idL']) && !empty($form->getValeur('photo1')) && $form->getErreur("logo") == '')
        {
            $imgInfo = getimagesize($rep_uploads_lieux . $form->getValeur('photo1'));
        $checked = '';
            $tab_sup = $form->getSupprimer();
            if (in_array('photo1', $tab_sup) && $form->getNbErreurs() > 0)
            {
                $checked = ' checked="checked"';
            }
            ?>

            <input type="hidden" name="photo1_existant" value="<?php echo $form->getValeur('photo1'); ?>" />
            <div class="supImg">
                        <?php echo "<img src=\"" . $url_uploads_lieux . "s_" . $form->getValeur('photo1') . "?" . filemtime($rep_uploads_lieux . $form->getValeur('photo1')) . "\" />"; ?>
                        <div>
                <label for="supprimer_photo1" class="continu">Supprimer</label>
                <input type="checkbox" name="supprimer[]" id="supprimer_photo1" value="photo1" class="checkbox" <?php echo $checked; ?> />

                </div>
            </div>

            <?php
        }
        ?>
        </p>

            <?php
        if ($_SESSION['Sgroupe'] <= 6)
        {
        ?>
        <p>
            <label for="image_galerie">Galerie</label>
            <input type="file" name="image_galerie" id="image_galerie" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif"  class="fichier" />
        </p>
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
                    echo "<tr><td><img src=\"".$url_uploads_lieux_galeries."s_".$nom_fichier."\" /></td>
                    <td>".date_iso2app($tab_galerie['dateAjout'])."</td>
                    <td><input type=\"checkbox\" name=\"supprimer_galerie[]\" value=\"".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension']."\" /></td></tr>";
                }
                echo "</table>";
            }

        }
        ?>
        <?php } ?>

    </fieldset>


    <fieldset>
    <?php

    //menu d'actions (activation et suppression)  pour l'auteur > 6 ou l'admin
    if (($get['action'] == 'editer' || $get['action'] == 'update') &&
    (($authorization->isAuthor("lieu", $_SESSION['SidPersonne'], $get['idL']) && $_SESSION['Sgroupe'] < 6) || $_SESSION['Sgroupe'] <= 4))
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

    <input type="hidden" name="dateAjout" value="<?php echo $form->getValeur('dateAjout') ?>" />
    <input type="hidden" name="idPersonne" value="<?php  echo $form->getValeur('idpersonne') ?>" />
    <input type="hidden" name="idLieu" value="<?php echo $get['idL'] ?>" />

<p class="piedForm">
<input type="hidden" name="formulaire" value="ok" />
<input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
<input type="submit" value="Enregistrer" tabindex="20" title="Enregistrer le lieu" class="submit submit-big" />
</p>
</form>

<?php
} // if action_terminee
?>


</div>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">

<?php include("_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonne gauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
