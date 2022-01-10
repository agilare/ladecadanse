<?php

require_once("../app/bootstrap.php");


use Ladecadanse\Security\Sentry;
use Ladecadanse\OrganisateurCollection;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Text;

$videur = new Sentry();

$page_titre = "Organisateurs d'événements culturels à Genève et Lausanne : associations, labels, collectifs";
$page_description = "Derniers organisateurs ajoutés";
$extra_css = array("menu_lieux");
include("_header.inc.php");

$get['idO'] = "";
if (isset($_GET['idO']))
{
	$get['idO'] = Validateur::validateUrlQueryValue($_GET['idO'], "int", 1);
}

/**
* Récupère les dernières description + infos sur lieux et utilisateurs
*/
$col = new OrganisateurCollection();
$col->loadFiches();
$pair = 0;
?>

<div id="contenu" class="colonne">
    
    <div id="entete_contenu">
        <h2  style="font-size:1.6em; width: 30%;">Organisateurs</h2><?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6) { ?><a href="/pages/organisateur-edit.php?action=ajouter" style="float: right;padding: 5px 1px;"><img src="/web/interface/icons/add.png" alt="" style="vertical-align:bottom" /> Ajouter un organisateur</a><?php } ?>
        <div class="spacer"></div>
        <p class="mobile" id="btn_listelieux">
            <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des organisateurs</button>
        </p>        
    </div>

    <div class="spacer"></div>
    
    <ol id="dernieres_descriptions">

    <?php
    foreach ($col->getElements() as $id => $fiche)
    {

        $photo_principale = '';
        if ($fiche->getValue('logo') != "")
        {
            $logo_time = @filemtime($rep_images_organisateurs.$fiche->getValue('logo'));
            $photo_principale = "<a href=\"/pages/organisateur.php?idO=".$fiche->getValue('idOrganisateur')."\" title=\"Voir la fiche de l'organisateur : ".sanitizeForHtml($fiche->getValue('nom'))."\">
            <img src=\"".$url_images_organisateurs.$fiche->getValue('logo')."?".$logo_time."\" width=\"100\" alt=\"".sanitizeForHtml($fiche->getValue('nom'))."\" /></a>\n";
        }

        //Réduction du descriptif
        $maxChar = Text::trouveMaxChar($fiche->getValue('presentation'), 36, 8);
        $tailleCont = mb_strlen($fiche->getValue('presentation'));

        $apercu = $fiche->getValue('presentation');
        if ($tailleCont > $maxChar)
        {
            $apercu = Text::html_substr($fiche->getValue('presentation'), $maxChar, 2);
        }
        ?>

        <!-- Début vignette -->
        <li class="vignette<?php if ($pair % 2 != 0){echo " ici";} ?>">

            <div class="icone">
            <?php echo $photo_principale; ?>
            </div>
            <h3><a href="/pages/organisateur.php?idO=<?php echo $fiche->getValue('idOrganisateur'); ?>" title="Voir la fiche de l'organisateur : <?php echo sanitizeForHtml($fiche->getValue('nom')); ?>"><?php echo sanitizeForHtml($fiche->getValue('nom')); ?></a></h3>
            <span class="qui"><?php echo date_fr($fiche->getValue('date_ajout'), "annee", "non", "non"); ?></span>
            <div class="spacer"></div>
            <div class="apercu">
            <?php echo $apercu; ?>
            </div>
            <div class="continuer">
                <a href="/pages/organisateur.php?idO=<?php echo $fiche->getValue('idOrganisateur'); ?>" title="Voir la fiche de l'organisateur  : <?php echo sanitizeForHtml($fiche->getValue('nom')); ?>">
            Voir la fiche complète</a>
            </div>
        </li>
        <!-- FIN vignette -->
    <?php
        $pair++;

    } // while
    ?>

	</ol>
	<!-- Fin dernieres_descriptions -->

</div>
<!-- fin Contenu -->

<div id="colonne_gauche" class="colonne">

<?php
include("_navigation_calendrier.inc.php");
?>



</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">

<?php include($rep_absolu."pages/_menuorganisateurs.inc.php");
echo $aff_menulieux; ?>

</div>
<!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
