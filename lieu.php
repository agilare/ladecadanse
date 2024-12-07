<?php

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Lieu;
use Ladecadanse\DescriptionCollection;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementCollection;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Text;

if (isset($_GET['idL']) && $_GET['idL'] > 0)
{
    try {
        $get['idL'] = Validateur::validateUrlQueryValue($_GET['idL'], "int", 1);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}
else
{
	header("HTTP/1.1 404 Not Found");
    echo file_get_contents("articles/404.php");
	exit;
}

$tab_genre_even = array("fête", "cinéma", "théâtre", "expos", "divers", "tous");
$get['genre_even'] = "tous";
if (isset($_GET['genre_even']))
{
    try {
        $get['genre_even'] = Validateur::validateUrlQueryValue($_GET['genre_even'], "enum", 0, $tab_genre_even);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}

$tab_complement = array("evenements");
$get['complement'] = "evenements";
if (isset($_GET['complement']))
{
    try {
        $get['complement'] = Validateur::validateUrlQueryValue($_GET['complement'], "enum", 0, $tab_complement);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}

$tab_types_description = array("description", "presentation");
$get['type_description'] = "";
if (isset($_GET['type_description']))
{
    try {
        $get['type_description'] = Validateur::validateUrlQueryValue($_GET['type_description'], "enum", 0, $tab_types_description);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }
}

$lieu = new Lieu();
$lieu->setId($get['idL']);
$lieu->load();

if ($lieu->getValue('statut') == 'inactif' && !((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)))
{
    header("HTTP/1.1 404 Not Found");
    echo file_get_contents("articles/404.php");
    exit;
}



//printr($lieu->getValues());

$sql_lieu_localite = "
SELECT *
FROM localite
WHERE id='".$lieu->getValue('localite_id')."'";

$req_lieu_localite = $connector->query($sql_lieu_localite);

$lieu_localite = $connector->fetchAssoc($req_lieu_localite);

$page_titre = $lieu->getValue('nom').HtmlShrink::getAdressFitted($lieu->getValue('region'), $lieu_localite['localite'], $lieu->getValue('quartier'), '' );


$page_description = $page_titre." : accès, horaires, description, photos et prochains événements";


$extra_css = array("menu_lieux", "element_login");
include("_header.inc.php");

$deb_nom_lieu = mb_strtolower(mb_substr($lieu->getValue('nom'), 0, 1));
if (!isset($_GET['tranche']) && $deb_nom_lieu > "l" && $deb_nom_lieu < "z")
{
	$_GET['tranche'] = "lz";
}

include_once "_menulieux.inc.php";

$lieu_status = '';
if ($lieu->getValue('statut') == 'ancien')
{
	$lieu_status = '<div class="spacer"><!-- --></div>
<p class="info">Ce lieu n\'existe plus</p>';
}
elseif ($lieu->getValue('statut') == 'inactif')
{
	// le staff, ainsi que l'auteur et les personnes liées par organisateur peuvent voir l'even dépublié
	if (
	isset($_SESSION['Sgroupe']) &&
	(
	$_SESSION['Sgroupe'] <= 6
	)

	)
	{
            $lieu_status = '<div class="spacer"><!-- --></div>
<p class="info">Inactif</p>';
	}
	else
	{
        header("HTTP/1.1 404 Not Found");
        echo file_get_contents("articles/404.php");
        exit;
	}
}

$menu_actions = '';
$action_ajouter = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::ACTOR)) {
	$action_ajouter = '<li class="action_ajouter"><a href="/evenement-edit.php?idL='.$get['idL'].'" title="ajouter un événement à ce lieu">Ajouter un événement à ce lieu</a></li>';
}

$action_editer = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6 || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL'])))
{
	$action_editer = '<li class="action_editer"><a href="/lieu-edit.php?action=editer&amp;idL='.$get['idL'].'">Modifier ce lieu</a></li>';
}

$req_nb_des = $connector->query("SELECT idPersonne FROM descriptionlieu WHERE descriptionlieu.idLieu=".$get['idL']);

$class_vide = '';
if ($connector->getNumRows($req_nb_des) == 0)
{
	$class_vide = ' class="vide"';
}

$illustration = "";

if (!empty($lieu->getValue('logo')))
{
	$illustration = "<img src=".$url_uploads_lieux."s_".$lieu->getValue('logo')." style='float:left;margin-right:0.2em' class='logo'  />";
}
else if (!empty($lieu->getValue('photo1')))
{
	$illustration = "<img src=".$url_uploads_lieux."s_".$lieu->getValue('photo1')." height=80 style='float:left;margin-right:0.2em' />";
}

?>

<!-- Début Contenu -->
<div id="contenu" class="colonne">

	<p id="btn_listelieux" class="mobile" >
	<button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des lieux</button>
	</p>

    <?php
    if (!empty($_SESSION['lieu_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['lieu_flash_msg']);
        unset($_SESSION['lieu_flash_msg']);
    }
    ?>

	<div class="vcard">
	<div id="entete_contenu">


<?php
if ($lieu->getValue('logo'))
{
?>
<a href="<?php echo $url_uploads_lieux.$lieu->getValue('logo').'?'.filemtime($rep_uploads_lieux.$lieu->getValue('logo')) ?>" class="magnific-popup">
	<img src="<?php echo $url_uploads_lieux."s_".$lieu->getValue('logo')."?".filemtime($rep_uploads_lieux."s_".$lieu->getValue('logo')); ?>" alt="Logo" class="logo" />
</a>
<?php
}
?>
	<?php //echo $logo ?>
	<?php
	$h2_style = '';
	if (isset($logo))
		$h2_style = "width:48%";
	?>
	<h2 class="fn org" style="<?php echo $h2_style; ?>"><?php echo $lieu->getHtmlValue('nom'); ?></h2>
<?php	echo $lieu_status ?>
	<div class="spacer"></div>
	</div>

	<div class="spacer"><!-- --></div>

	<ul class="menu_actions_lieu desktop">
		<?php
		echo $menu_actions;
		echo $action_ajouter;
		echo $action_editer;
		?>
	</ul>

	<div class="spacer"><!-- --></div>

	<div id="fiche"<?php echo $class_vide; ?>>

		<!-- Deb medias -->
		<div id="medias">

            <div id="photo" <?php echo (!$lieu->getValue('photo1')) ? " style='  background: #eaeaea;'" : ""; ?>>
                <?php
			if ($lieu->getValue('photo1') != '') {
			?>
			<a href="<?php echo $url_uploads_lieux.$lieu->getValue('photo1').'?'.filemtime($rep_uploads_lieux.$lieu->getValue('photo1')); ?>" class="gallery-item"><img src="<?php echo $url_uploads_lieux."s_".$lieu->getValue('photo1').'?'.filemtime($rep_uploads_lieux.$lieu->getValue('photo1')); ?>" alt="Photo du lieu"></a>
			<?php } ?>
            <?php
            if (empty($_SESSION['Sgroupe']))
            {
            ?>
                <?php echo (!$lieu->getValue('photo1')) ? '<p style="font-size:0.9em;padding:2em 0.5em;line-height:1.2em">Vous gérez ce lieu ? <a href="/user-register.php">Inscrivez-vous</a> pour pouvoir ajouter ou modifier les informations et des photos</p>' : ""; ?>
            <?php } ?>
			</div>
			<div class="spacer"><!-- --></div>

			<?php
			/* Galerie d'images */
			$sql_galerie = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension
			FROM fichierrecu, lieu_fichierrecu
			WHERE lieu_fichierrecu.idLieu=".$get['idL']." AND type='image' AND fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
			 ORDER BY dateAjout DESC";

			$req_galerie = $connector->query($sql_galerie);

			$req_galerie = $connector->query($sql_galerie);

			if ($connector->getNumRows($req_galerie) > 0)
			{
					echo '<div class="section">';


					while ($tab_galerie = $connector->fetchArray($req_galerie))
					{
						if (mb_strstr($tab_galerie['mime'], "image"))
						{
							$icone_fichier = $iconeImage;
						}

						$url_fichier = $url_uploads_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$rep_fichier = $rep_uploads_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$rep_fichier_s = $rep_uploads_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						$url_fichier_s = $url_uploads_lieux_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
						?>

                    	<a href="<?php echo $url_fichier."?".filemtime($rep_fichier); ?>" class="gallery-item"><img src="<?php echo $url_fichier_s."?".filemtime($rep_fichier_s); ?>" alt="Photo du lieu"></a>
                        <?php
					}


				echo '</div>';


					echo '<div class="spacer"></div>';
			}
        ?>
		</div>
		<!-- Fin medias -->

		<?php
        $tab_categories = explode(",", str_replace(" ", "", $lieu->getValue('categorie')));
        foreach ($tab_categories as &$c)
        {
            if (isset($glo_categories_lieux[$c]))
            {
                $c = $glo_categories_lieux[$c];
            }
        }

        $adresse = HtmlShrink::getAdressFitted($lieu->getValue('region'), $lieu_localite['localite'], $lieu->getValue('quartier'), $lieu->getValue('adresse') );

		$carte = '';
		if ($lieu->getValue('lat') != 4 && $lieu->getValue('lng') != 4)
{
            $carte = '
            <li>
                <a href="#" class="dropdown" data-target="plan">'.$icone['plan'].' Voir sur le plan <i class="fa fa-caret-down" aria-hidden="true"></i>
</a>

            </li>';
		}

		$URL = '';
		if ($lieu->getValue('URL') != '' )
		{

			if (!preg_match("/^https?:\/\//", $lieu->getValue('URL')))
			{
				$URL .=  "http://".$lieu->getValue('URL');
			}
			else
			{
				$URL .=  $lieu->getValue('URL');
			}

		}

		$salles = '';
		$sql_salle = "SELECT * FROM salle WHERE idLieu=".$get['idL']. " AND salle.status='actif' ";
//		echo $sql_salle;
		$req_salle = $connector->query($sql_salle);

		if ($connector->getNumRows($req_salle) > 0)
		{
			$salles .= '<li>Salles : ';
			$salles .= '<ul class="salles">';
			while ($tab_salle = $connector->fetchArray($req_salle))
			{
				$salles .= '<li>'.$tab_salle['nom'];

				if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
				{
					$salles .= '<a href="/lieu-salle-edit.php?action=editer&amp;idS='.$tab_salle['idSalle'].'">'.$iconeEditer.'</a>';
				}

				$salles .= '</li>';
			}
			$salles .= '</ul></li>';

		}
		if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
		{
			$salles .= '<a href="/lieu-salle-edit.php?idL='.$get['idL'].'">'.$icone['ajouts'].'ajouter une salle</a>';
		}



		$organisateurs = '';
		$sql = "SELECT organisateur.idOrganisateur AS idOrganisateur, nom FROM organisateur, lieu_organisateur

				WHERE organisateur.idOrganisateur=lieu_organisateur.idOrganisateur AND lieu_organisateur.idLieu=".$get['idL'];

		$req = $connector->query($sql);

		if ($connector->getNumRows($req) > 0)
		{
			$organisateurs .= '<li>Organisateur';
			if ($connector->getNumRows($req) > 1)
			{
				$organisateurs .= 's';
			}
			$organisateurs .= ' : ';
			$organisateurs .= '<ul class="salles">';
			while ($tab = $connector->fetchArray($req))
			{
				$organisateurs .= '<li><a href="/organisateur.php?idO='.$tab['idOrganisateur'].'" >';
				$organisateurs .= $tab['nom'];

				$organisateurs .= '</a></li>';
			}
			$organisateurs .= '</ul></li>';

		}
		?>

		<div id="pratique">
			<ul>
				<li><?php echo implode(", ", $tab_categories); ?></li>
                <li class="adr"><?php echo $adresse ?></li>
				<?php echo $carte; ?>
                <?php echo $salles; ?>
                <span class="latitude">
                   <span class="value-title" title="<?php echo $lieu->getValue('lat'); ?>"></span>
                </span>
                <span class="longitude">
                   <span class="value-title" title="<?php echo $lieu->getValue('lng'); ?>"></span>
                </span>
				<li><?php echo Text::wikiToHtml($lieu->getValue('horaire_general')); ?></li>
                <li class="sitelieu">
                    <?php if (!empty($URL)) { ?>
                        <a class="url lien_ext" href="<?php echo $URL; ?>" title="Voir le site web du lieu"  target="_blank"><?php echo $lieu->getValue('URL'); ?></a>
                    <?php } ?>
                    <?php if ($lieu->getId() == 13) { // exception pour le Rez   ?><a href="http://kalvingrad.com" class="url lien_ext"  target="_blank">kalvingrad.com</a><br><a href="http://www.ptrnet.ch" class="url lien_ext" target="_blank">ptrnet.ch</a><?php } ?>
                </li>
                <?php echo $organisateurs; ?>
            </ul>
            <div id="plan" style="display:none">
                <div id="lieu-map-infowindow" style='display:none;width:200px'>
                    <?php echo $illustration; ?><div class=details><p class=adresse><strong><?php echo sanitizeForHtml($lieu->getValue('nom')); ?></strong></p><p class=adresse><?php echo sanitizeForHtml($lieu->getValue('adresse')); ?></p><p class=adresse><?php echo $lieu->getValue('quartier'); ?></p></div>
                </div>
                <div id="lieu-map" data-lat="<?php echo $lieu->getValue('lat') ?>" data-lng="<?php echo $lieu->getValue('lng') ?>"></div>
            </div>
        </div><!-- Fin pratique -->

        <div class="spacer only-mobile"></div>

<?php
$descriptions = new DescriptionCollection();
$type2hide = ['description' => ' style="display:none"', 'presentation' => ' style="display:none"'];

$nb_desc = 0;
$nb_pres = 0;
	/**
	* Recolte les descriptions
	*/
	if ($descriptions->getNumRows($get['idL']))
	{

?>

<ul id="menu_descriptions">
<?php


$nb_desc = $descriptions->getNumRows($get['idL'], 'description');
$nb_pres = $descriptions->getNumRows($get['idL'], 'presentation');

	if ($nb_desc > 0)
	{
		$get['type_description'] = 'description';
        $type2hide['description'] = '';
	}
	else if ($nb_desc == 0 && $nb_pres > 0)
	{
		$get['type_description'] = 'presentation';
        $type2hide['presentation'] = '';
	}


if ($nb_desc)
{
?>

    <li class="btn-description <?php if ($get['type_description'] == 'description') { echo 'ici'; }?>">
        <h3><a href="#description" id="show-description-btn">Description</a></h3>
            </li>
 <?php

 }

if ($nb_pres > 0)
{

?>
    <li class="btn-presentation <?php if ($get['type_description'] == 'presentation') { echo 'ici'; }?>">
                <h3><a href="#presentation" id="show-presentation-btn">Le lieu se présente</a></h3>
            </li>
 <?php

 }
 ?>
  </ul>
<?php
	}
?>
<div id="descriptions">


    <?php

    $types_desc = ['description', 'presentation'];
    foreach ($types_desc as $type)
    {
        $descriptions = new DescriptionCollection();
        ?>
    <div class="type-<?php echo $type; ?>" <?php echo $type2hide[$type]; ?>>
        <?php
        $auteurs_de_desc = array();
        if ($descriptions->loadByType($get['idL'], $type))
        {
            foreach ($descriptions->getElements() as $id => $des)
            {
                $dern_modif = '';
                if ($des->getValue('date_derniere_modif') != "0000-00-00 00:00:00" && $des->getValue('date_derniere_modif') != $des->getValue('dateAjout'))
                {

                    $dern_modif = ", modifié le ".date_fr($des->getValue('date_derniere_modif'), 'annee', '', 'non');
                }

                $editer = '';
                if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4)
                || (isset($_SESSION['Sgroupe']) && $type == 'description' && $_SESSION['Sgroupe'] <= 6 && (isset($_SESSION['SidPersonne'])) && $_SESSION['SidPersonne'] == $des->getValue('idPersonne'))
                || (isset($_SESSION['Sgroupe']) && $type == 'presentation' && ($_SESSION['Sgroupe'] <= 6 || ($_SESSION['Sgroupe'] <= 8 && ($authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL'])))))
                        )
                {
                    $editer = '<span class="right">';
                    $editer .= '<a href="/lieu-text-edit.php?action=editer&amp;type=' . $type . '&amp;idL=' . $get['idL'] . '&amp;idP=' . $des->getValue('idPersonne') . '">' . $iconeEditer . 'Modifier</a>';
                $editer .= '</span>';

                    if ($_SESSION['SidPersonne'] == $des->getValue('idPersonne'))
                        $auteurs_de_desc[] = $des->getValue('idPersonne');
                }
             ?>

            <div class="description">
                <?php
                if (datetime_iso2time($des->getValue('date_derniere_modif')) > datetime_iso2time("2009-10-12 12:00:00"))
                {
                    echo $des->getValue('contenu');
                }
                else
                {
                    echo "<p>".Text::wikiToHtml($des->getHtmlValue('contenu'))."</p>";
                }
                ?>
                <p><?php
                    if ($type == 'description')
                    {
                        echo HtmlShrink::authorSignature($des->getValue('idPersonne'));
                    }
                    ?></p>

                <div class="auteur">
                    <span class="left"><?php echo ucfirst(date_fr($des->getValue('dateAjout'), 'annee','', 'non')) ?><?php echo $dern_modif; ?></span><?php echo $editer;?>
                </div>
                <div class="spacer"><!-- --></div>
            </div>
            <!-- Fin description -->

        <?php
            }
        }
    ?>
            </div>
            <?php

                    }

	// un rédacteur qui n'a pas déjà écrit une description
	if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6 && !in_array($_SESSION['SidPersonne'], $auteurs_de_desc))
	{
		echo "<a href=\"/lieu-text-edit.php?idL=" . $get['idL'] . "&amp;type=description\">" . $icone['ajouter_texte'] . " Ajouter une description (avis)</a><br>";
}

	if (isset($_SESSION['Sgroupe']) &&
            ($_SESSION['Sgroupe'] <= 6 || ($_SESSION['Sgroupe'] == 8 && ($authorization->isPersonneAffiliatedWithLieu($_SESSION['SidPersonne'], $get['idL']) || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL']))))
            && $nb_pres == 0)
	{
		echo "<a href=\"/lieu-text-edit.php?idL=" . $get['idL'] . "&amp;type=presentation\">" . $icone['ajouter_texte'] . " Ajouter une présentation</a>";
}
	?>

	</div>
	<!-- Fin descriptions -->
<div class="spacer"></div>
</div>
<!-- Fin fiche -->
<div class="spacer"><!-- --></div>
</div> <!-- fin vcard -->
<div class="spacer"><!-- --></div>


<h2 style="font-size:1.2em;font-weight:bold;color:#5C7378;width:96%;margin:2em 2% 0.4em 2%;min-height:30px">Prochains événements</h2>

<?php


/* if ($get['complement'] == 'evenements')
{ */

	$date_debut = date("Y-m-d", time() - 21600);

	$genre = "";
	if (isset($get['genre_even']) && $get['genre_even'] != "tous")
	{
		$genre .= $get['genre_even'];
	}

	$evenements = new EvenementCollection($connector);

	$evenements->loadLieu($get['idL'], $date_debut, $genre);

	echo '	<div id="prochains_evenements">';

	/* Construction du menu par genre */
	$menu_genre = '';
	if ($evenements->getNbElements() > 0)
	{
		$menu_genre .= '<ul id="menu_genre">';
		$genres_even = array("tous", "fête", "cinéma", "théâtre", "expos", "divers");

		foreach ($genres_even as $g)
		{

			$genre = "";
			if ($g != "tous")
			{
				$genre = "AND genre='".$g."'";
			}

			$sql_nb_even = "SELECT idEvenement
			 FROM evenement
			 WHERE idLieu=".$get['idL']." AND dateEvenement >= '".$date_debut."' AND statut NOT IN ('inactif', 'propose') ".$genre;


			$req_nb_even = $connector->query($sql_nb_even);
			$nb_even_genre = $connector->getNumRows($req_nb_even);

			$menu_genre .= "<li";
			if ($g == $get['genre_even'])
			{
				$menu_genre .= " class=\"ici\"><a href=\"/lieu.php?idL=".$get['idL']."&amp;genre_even=".urlencode($g)."#prochains_even\" title=\"".$g."\">";
				if ($g == "fête")
				{
					$g .= "s";
				}
				else if ($g == "cinéma")
				{
					$g = "ciné";
				}
				$menu_genre .= $g;
				$menu_genre .= " (".$nb_even_genre.")";

				$menu_genre .= "</a>";
			}
			else if ($nb_even_genre == 0 && $g != "tous")
			{
				if ($g == "fête")
				{
					$g .= "s";
				}
				else if ($g == "cinéma")
				{
					$g = "ciné";
				}
				$menu_genre .= ' class="rien">';
				$menu_genre .= $g;
			}
			else
			{
				$menu_genre .= "><a href=\"/lieu.php?idL=".$get['idL']."&amp;genre_even=".$g."#prochains_even\" title=\"".$g."\">";
				if ($g == "fête")
				{
					$g .= "s";
				}
				else if ($g == "cinéma")
				{
					$g = "ciné";
				}
				$menu_genre .=  $g;
				$menu_genre .= " (".$nb_even_genre.")";
				$menu_genre .= "</a>";
			}

			$menu_genre .= "</li>";


		}
		$menu_genre .= "</ul>";
		echo $menu_genre;
	?>





	<div class="clear_mobile"></div>
	<table>

	<?php

	$nbMois = 0;
	$moisCourant = 0;
	//listage des événements
	foreach ($evenements->getElements() as $id => $even)
	{


		$description = '';
		if ($even->getValue('description') != '')
		{
			$maxChar = Text::trouveMaxChar($even->getValue('description'), 50, 2);

			if (mb_strlen($even->getValue('description')) > $maxChar)
			{
				//$continuer = "<span class=\"continuer\"><a href=\"/evenement.php?idE=".$even->getValue('idEvenement')."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
				$description = Text::texteHtmlReduit(Text::wikiToHtml(htmlspecialchars($even->getValue('description'))), $maxChar);

			}
			else
			{
				$description = Text::wikiToHtml(htmlspecialchars($even->getValue('description')));
			}
		}

		if ($nbMois == 0)
		{
			$moisCourant = date2mois($even->getValue('dateEvenement'));
			echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst(mois2fr($moisCourant))."</td></tr>";
		}

		if (date2mois($even->getValue('dateEvenement')) != $moisCourant)
		{
			echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst(mois2fr(date2mois($even->getValue('dateEvenement'))));

			if (date2mois($even->getValue('dateEvenement')) == "01")
			{
				echo " ".date2annee($even->getValue('dateEvenement'));
			}

			echo "</td></tr>";
		}

		$salle = '';
		$sql_salle = "SELECT nom FROM salle WHERE idSalle=".$even->getValue('idSalle');

		$req_salle = $connector->query($sql_salle);

		if ($connector->getNumRows($req_salle) > 0)
		{
			$tab_salle = $connector->fetchArray($req_salle);
			$salle = $tab_salle['nom'];
		}

                $vcard_starttime = '';
                if (mb_substr($even->getValue('horaire_debut'), 11, 5) != '06:00')
                    $vcard_starttime = "T".mb_substr($even->getValue('horaire_debut'), 11, 5).":00";

	?>

		<tr class="<?php if ($date_debut == $even->getValue('dateEvenement')) { echo "ici"; } ?> vevent evenement">

			<td class="dtstart"><?php echo date2nomJour($even->getValue('dateEvenement')); ?>

                            <span class="value-title" title="<?php echo $even->getValue('dateEvenement').$vcard_starttime; ?>"></span>
			</td>

			<td><?php echo date2jour($even->getValue('dateEvenement'));  ?>

			</td>

			<td class="flyer photo">
			<?php
		if ($even->getValue('flyer') != '')
		{
                            ?>
                <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('flyer')), true) ?>" class="magnific-popup" target="_blank">

                                            <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('flyer'), 's_'), true) ?>" alt="Flyer" width="60" />
                                        </a>

                            <?php


		}
		else if ($even->getValue('image') != '')
		{
                            ?>
                <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('image')), true) ?>" class="magnific-popup" target="_blank">

                                <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('image'), 's_'), true) ?>" alt="Photo" width="60" />
                            </a>
                            <?php

		}
?>

                    </td>

			<td>
			<h3 class="summary">
			<?php
			$titre_url = '<a class="url" href="/evenement.php?idE='.$even->getValue('idEvenement').'" title="Voir la fiche de l\'événement">'.Evenement::titre_selon_statut(sanitizeForHtml($even->getValue('titre')), $even->getValue('statut')).'</a>';
			echo $titre_url; ?>
			</h3>



			<p class="description">
			<?php
			echo $description;

			?></p>
			<div class="location">
			<span class="value-title" title="<?php echo $lieu->getHtmlValue('nom'); ?>"></span>
			</div>
			<p class="pratique"><?php echo afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement'))." ".$even->getValue('prix') ?></p>
			</td>

			<td><?php echo $salle; ?></td>
			<td class="category"><?php echo $glo_tab_genre[$even->getValue('genre')]; ?></td>

			<td class="lieu_actions_evenement">
			<?php
			if (
	 		(isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
			|| $_SESSION['SidPersonne'] == $even->getValue('idPersonne'))
			)
			||  (isset($_SESSION['Saffiliation_lieu']) && !empty($get['idL']) && $get['idL'] == $_SESSION['Saffiliation_lieu'])
			 || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $even->getValue('idEvenement'))
			 || isset($_SESSION['SidPersonne']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $get['idL'])
			)
			{
			?>
			<ul>

				<li><a href="/evenement-copy.php?idE=<?php echo $even->getValue('idEvenement') ?>" title="Copier cet événement"><?php echo $iconeCopier ?></a></li>
				<li><a href="/evenement-edit.php?action=editer&amp;idE=<?php echo $even->getValue('idEvenement') ?>" title="Éditer cet événement"><?php echo $iconeEditer ?></a></li>
                <li class=""><a href="#" id="btn_event_unpublish_<?php echo $even->getValue('idEvenement'); ?>" class="btn_event_unpublish" data-id="<?php echo $even->getValue('idEvenement') ?>"><?php echo $icone['depublier']; ?></a></li>
			</ul>
			<?php
			}
			?>
			</td>
		</tr>

	<?php

		$moisCourant = date2mois($even->getValue('dateEvenement'));
		$nbMois++;
	}
	?>

	</table>

	<?php

	}
	else
	{
		echo "<p>Pas d'événement actuellement annoncé au lieu <strong>".$lieu->getHtmlValue('nom')."</strong></p>";
	}

	if (!empty($tab_lieu['URL']))
	{
		$URLcomplete = $tab_lieu['URL'];

		if (!preg_match("/^(https?:\/\/)/i", $tab_lieu['URL']))
		{
			$URLcomplete = "http://".$tab_lieu['URL'];
		}
		echo "<p>Pour des informations complémentaires veuillez consulter <a href=\"" . $URLcomplete . "\" target='_blank'>" . $tab_lieu['URL'] . "</a></p>\n";
}

	echo '</div>';

/* }  */// if complement
?>



</div>
<!-- fin Contenu -->


<div id="colonne_gauche" class="colonne">
    <?php include("_navigation_calendrier.inc.php");?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">

<?php echo $aff_menulieux; ?>


</div>
<!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
