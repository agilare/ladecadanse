<?php

require_once("app/bootstrap.php");

use Ladecadanse\OrganisateurCollection;

$page_titre = "Organisateurs d'événements culturels à Genève et Lausanne : associations, labels, collectifs";
$page_description = "";
$extra_css = array("menu_lieux");
include("_header.inc.php");


$col = new OrganisateurCollection();
$col->loadFiches();
$pair = 0;
?>

<div id="contenu" class="colonne">

    <div id="entete_contenu">
        <h2  style="font-size:1.6em; width: 30%;">Organisateurs</h2><?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6) { ?><a href="/organisateur-edit.php?action=ajouter" style="float: right;padding: 5px 1px;"><img src="/web/interface/icons/add.png" alt="" style="vertical-align:bottom" /> Ajouter un organisateur</a><?php } ?>
        <div class="spacer"></div>
        <p class="mobile" id="btn_listelieux">
            <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des organisateurs</button>
        </p>
    </div>

    <div class="spacer"></div>

    <div id="derniers_lieux" style="width:94%;margin:0 auto;">

        <h2 style="">Derniers organisateurs ajoutés</h2>

        <?php
        foreach ($col->getElements() as $id => $fiche)
        {
            ?>
            <h3><a href="/organisateur.php?idO=<?php echo $fiche->getValue('idOrganisateur'); ?>" title="Voir la fiche de l'organisateur : <?php echo sanitizeForHtml($fiche->getValue('nom')); ?>"><?php echo sanitizeForHtml($fiche->getValue('nom')); ?></a></h3>
            <?php
    } // while
    ?>

    </div>
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

<?php include("_menuorganisateurs.inc.php");
echo $aff_menulieux; ?>

</div>
<!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
