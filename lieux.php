<?php

require_once("app/bootstrap.php");

use Ladecadanse\DescriptionCollection;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Validateur;

if ($_SESSION['region'] == 'vd')
 {
     $page_titre_region = " à Lausanne";
 }
 elseif ($_SESSION['region'] == 'fr')
 {
     $page_titre_region = " à Fribourg";
 }
 else
 {
     $page_titre_region = " à Genève";
 }

$page_titre = "Lieux de sorties ".$page_titre_region." : bistrots, salles, bars, restaurants, cinémas,
 théâtres, galeries, boutiques, musées...";
$extra_css = array("menu_lieux");
include("_header.inc.php");

$get['idL'] = "";
if (isset($_GET['idL']))
{
try {
	$get['idL'] = Validateur::validateUrlQueryValue($_GET['idL'], "int", 0);
 } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}

$fiches = new DescriptionCollection();
$fiches->loadFiches('description', $_SESSION['region']);
$pair = 0;
?>
<div id="contenu" class="colonne">

    <div id="entete_contenu">
        <h2 style="font-size:1.6em; width: 15%;">Lieux</h2> <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6) { ?><a href="/lieu-edit.php?action=ajouter" style="float: left;padding: 5px 1px;"><img src="/web/interface/icons/building_add.png" alt=""  /> Ajouter un lieu</a><?php } ?><?php HtmlShrink::getMenuRegions($glo_regions, $get); ?>
        <div class="spacer"></div>
        <p class="mobile" id="btn_listelieux">
            <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des lieux</button>
        </p>
    </div>

    <div class="spacer"></div>
    <div style="clear:both"></div>

    <div id="derniers_lieux" style="width:94%;margin:0 auto;">

        <h2>Derniers lieux ajoutés</h2>

	<?php
	$req_lieux_recents = $connector->query("
	SELECT idLieu, nom, adresse, quartier, localite, dateAjout
	FROM lieu, localite WHERE lieu.localite_id=localite.id AND region='".$connector->sanitize($_SESSION['region'])."' ORDER BY dateAjout DESC LIMIT 10");

	// Création de la section si il y a moins un lieu
	if ($connector->getNumRows($req_lieux_recents) > 0)
	{

		while ($tab_lieux_recents = $connector->fetchArray($req_lieux_recents))
		{
		//printr($tab_lieux_recents);
		?>
		<h3><a href="/lieu.php?idL=<?php echo $tab_lieux_recents['idLieu']; ?>" title="Voir la fiche du lieu" ><?php echo $tab_lieux_recents['nom']; ?></a></h3>

		<p><?php

                echo htmlspecialchars(HtmlShrink::getAdressFitted( '', $tab_lieux_recents['localite'], $tab_lieux_recents['quartier'], $tab_lieux_recents['adresse'])); ?></p>
		<?php
		}
	}
	?>

	</div>


<div class="clear_mobile"></div>
</div>
<!-- fin Contenu -->

<div id="colonne_gauche" class="colonne">

<?php
include("_navigation_calendrier.inc.php");
?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
    <?php include("_menulieux.inc.php");echo $aff_menulieux; ?>
</div>
<!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
