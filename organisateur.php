<?php

require_once("app/bootstrap.php");


use Ladecadanse\Security\Sentry;
use Ladecadanse\Organisateur;
use Ladecadanse\OrganisateurCollection;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementCollection;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;

$videur = new Sentry();

if (isset($_GET['idO']))
{
    try {     
        $get['idO'] = Validateur::validateUrlQueryValue($_GET['idO'], "int", 1);
    } catch (Exception $e) { header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request"); exit; }     
}
else
{
	trigger_error("id obligatoire", E_USER_WARNING);
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


$get['complement'] = "evenements";
$get['type_description'] = "presentation";

$organisateur = new Organisateur();
$organisateur->setId($get['idO']);
$organisateur->load();

//printr($organisateur->getValues());

$page_titre = $organisateur->getValue('nom');
$page_description = "Page de présentation de ".$organisateur->getValue('nom');
$page_description .= ": informations pratiques, description et prochains événements";

$extra_css = array("menu_lieux");

include("_header.inc.php");
include($rep_absolu."_menuorganisateurs.inc.php");

$action_ajouter = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 10)
|| isset($_SESSION['SidPersonne']) && $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO']))
{
	$action_ajouter = '<li class="action_ajouter"><a href="/evenement-edit.php?idO='.$get['idO'].'" title="ajouter un événement à ce lieu">Ajouter un événement de cet organisateur</a></li>';
}

$action_editer = '';
if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
|| (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO']) && $_SESSION['Sgroupe'] <= 8))
)
{
	$action_editer = '<li class="action_editer"><a href="/organisateur-edit.php?action=editer&amp;idO='.$get['idO'].'" title="Éditer cet organisateur">Modifier cet organisateur</a></li>';
}

$lien_prec = '';
if ($url_prec != "")
{
	$lien_prec = '<a href="'.$url_prec.'" title="Organisateur précédent dans la liste">'.$iconePrecedent.'</a>';
}

$lien_suiv = '';
if ($url_suiv != "")
{
	$lien_suiv = '<a href="'.$url_suiv.'" title="Organisateur dans la liste">'.$iconeSuivant.'</a>';
}
?>

<div id="contenu" class="colonne">

	<p id="btn_listelieux" class="mobile">
        <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des organisateurs</button>
	</p>
    <?php
    if (!empty($_SESSION['organisateur_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['organisateur_flash_msg']);
        unset($_SESSION['organisateur_flash_msg']);
    }
    ?>   	

	<div id="entete_contenu">

	<?php 
	if ($organisateur->getValue('logo') !='')
	{		
	?>
	<a href="<?php echo $url_images_organisateurs.$organisateur->getValue('logo').'?'.filemtime($rep_images_organisateurs.$organisateur->getValue('logo')) ?>" class="magnific-popup">
		<img src="<?php echo $url_images_organisateurs."s_".$organisateur->getValue('logo')."?".filemtime($rep_images_organisateurs."s_".$organisateur->getValue('logo')); ?>" alt="Logo" height="60" class="logo" />
	</a>
	<?php 			
	}	
	
	$h2_style = '';
	if ($organisateur->getValue('logo') !='')
		$h2_style = "width:48%";
	?>
	

	<h2><?php echo $organisateur->getHtmlValue('nom'); ?></h2>
		<div class="spacer"></div>	
	</div>

	<ul class="menu_actions_lieu">
		<?php
		echo $action_ajouter;
		echo $action_editer;
		?>
	</ul>

	<div class="spacer"><!-- --></div>

	<div id="fiche">

		<!-- Deb medias -->
		<div id="medias">
			<div id="photo">
                <?php
                $photo_principale = '';
                if ($organisateur->getValue('photo') != '')
                {
                ?>

                    <a href="<?php echo $url_images_organisateurs.$organisateur->getValue('photo').'?'.filemtime($rep_images_organisateurs.$organisateur->getValue('photo')) ?>" class="magnific-popup">
                        <img src="<?php echo $url_images_organisateurs."s_".$organisateur->getValue('photo')."?".filemtime($rep_images_organisateurs."s_".$organisateur->getValue('photo')); ?>" alt="Photo"  />
                    </a>	


                <?php
                }					
                ?>										
			</div>
			<div class="spacer"><!-- --></div>
		</div>
		<!-- Fin medias -->

		<?php
//		$categories = str_replace(",", ", ", $organisateur->getValue('categorie'));
//		$adresse = $organisateur->getValue('adresse').' - '.$organisateur->getValue('quartier');


		$URL = '';
		if ($organisateur->getValue('URL') != '' )
		{
			if (!preg_match("/^https?:\/\//", $organisateur->getValue('URL')))
			{
				$URL .=  "http://".$organisateur->getValue('URL');
			}
			else
			{
				$URL .=  $organisateur->getValue('URL');
			}
		}

		$sql = "SELECT nom, lieu.idLieu AS idLieu FROM lieu_organisateur, lieu WHERE lieu_organisateur.idLieu=lieu.idLieu AND idOrganisateur=".$get['idO'];
		$req = $connector->query($sql);

		$lieux = '';

		if ($connector->getNumRows($req) > 0)
		{
			$lieux .= '<li>Lieu(x) gérés :';
			$lieux .= '<ul class="salles"> ';

			while ($tab = $connector->fetchArray($req))
			{
				$lieux .= '<li><a href="/lieu.php?idL='.$tab['idLieu'].'">'.$tab['nom']."</a></li>";
			}
			$lieux .= '</ul></li>';
		}


		$sql = "SELECT pseudo, personne.idPersonne AS idPersonne FROM personne_organisateur, personne WHERE personne_organisateur.idPersonne=personne.idPersonne AND idOrganisateur=".$get['idO'];
		$req = $connector->query($sql);

		$membres = '';

		if ($connector->getNumRows($req) > 0)
		{	
			if (isset($_SESSION['SidPersonne']) && 
			(
			$authorization->estAuteur($_SESSION['SidPersonne'], $get['idO'], "organisateur")
			|| $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO'])
			)
			)
			{	
				$membres .= '<li>Membre(s) :';
				$membres .= '<ul class="salles"> ';

				while ($tab = $connector->fetchArray($req))
				{
					$membres .= '<li>'.$tab['pseudo'].'</li>';
				}
				
				$membres .= '</ul></li>';				
			}			
		}
		?>
		
		<!-- Deb pratique -->
		<div id="pratique">
			<ul>
				<li class="siteLieu"><a class="url" href="<?php echo $URL; ?>" onclick="window.open(this.href,'_blank');return false;">
				<?php echo $organisateur->getValue('URL'); ?></a></li>
				<?php echo $lieux; ?>
				<?php echo $membres; ?>
			</ul>

		</div>
		<!-- Fin pratique -->
<?php

	/**
	* Recolte les descriptions
	*/
	if ( mb_strlen($organisateur->getHtmlValue('presentation')) > 0)
	{
        ?>

        <ul id="menu_descriptions">
        <li class="ici"><h3><a href="<?php echo basename(__FILE__); ?>?idO=<?php echo $get['idO'] ?>">L'organisateur se présente</a></h3></li>
        </ul>
        <?php
	}
    ?>
	<div id="descriptions">
		<div class="description">
            <p><?php echo $organisateur->getValue('presentation'); ?></p>
		</div>
	</div>
	<!-- Fin presentations -->
    <div class="spacer"></div>
</div>
<!-- Fin fiche -->

<div class="spacer"></div>

<?php
	$lien_rss_evenements = '<a href="/rss.php?type=organisateur_evenements&amp;id='.$get['idO'].'" title="Flux RSS des prochains événements"><i class="fa fa-rss fa-lg" style="color:#f5b045"></i></a>';
?>

<ul id="menu_complement">
	<li><h3>Prochains événements</h3></li><li class="rss"><?php echo $lien_rss_evenements; ?></li>
</ul>

<?php
$date_debut = date("Y-m-d", time() - 21600);

$genre = "";
if (isset($get['genre_even']) && $get['genre_even'] != "tous")
{
	$genre .= $get['genre_even'];
}

$evenements = new EvenementCollection($connector);

$evenements->loadOrganisateur($get['idO'], $date_debut, $genre);

echo '<div id="prochains_evenements">';

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

		$sql_nb_even = "SELECT evenement.idEvenement
		 FROM evenement, evenement_organisateur
		 WHERE evenement.idEvenement=evenement_organisateur.idEvenement AND idOrganisateur=".$get['idO']." AND dateEvenement >= '".$date_debut."' AND statut NOT IN ('inactif', 'propose') ".$genre;


		$req_nb_even = $connector->query($sql_nb_even);
		$nb_even_genre = $connector->getNumRows($req_nb_even);

		$menu_genre .= "<li";
		if ($g == $get['genre_even'])
		{
			$menu_genre .= " class=\"ici\"><a href=\"/organisateur.php?idO=".$get['idO']."&amp;genre_even=".urlencode($g)."#prochains_even\" title=\"".$g."\">";
			if ($g == "fête")
			{
				$g .= "s";
			}
			$menu_genre .= $g;
			$menu_genre .= " (".$nb_even_genre.")";

			$menu_genre .= "</a>";
		}
		else if ($nb_even_genre == 0 && $g != "tous")
		{
			$menu_genre .= ' class="rien">'.$g;
		}
		else
		{
			$menu_genre .= "><a href=\"/organisateur.php?idO=".$get['idO']."&amp;genre_even=".$g."#prochains_even\" title=\"".$g."\">".$g;
			$menu_genre .= " (".$nb_even_genre.")";
			$menu_genre .= "</a>";
		}

		$menu_genre .= "</li>";


	}
	$menu_genre .= "</ul>";
	echo $menu_genre;

	?>

	<table>

	<?php

	$nbMois = 0;
	$moisCourant = 0;
	//listage des événements
	foreach ($evenements->getElements() as $id => $even)
	{
		$presentation = '';
		if ($even->getValue('description') != '')
		{	
			$maxChar = Text::trouveMaxChar($even->getValue('description'), 50, 2);
			
			if (mb_strlen($even->getValue('description')) > $maxChar)
			{
				//$continuer = "<span class=\"continuer\"><a href=\"/evenement.php?idE=".$even->getValue('idEvenement')."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
				$presentation = Text::texteHtmlReduit(Text::wikiToHtml(htmlspecialchars($even->getValue('description'))), $maxChar);
						
			}
			else
			{
				$presentation = Text::wikiToHtml(htmlspecialchars($even->getValue('description')));
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
	
		$nom_lieu = '';
		if ($even->getValue('idLieu') != 0)
		{
			$tab_lieu = $connector->fetchArray(
			$connector->query("SELECT nom FROM lieu WHERE idlieu='".$even->getValue('idLieu')."'"));

			$nom_lieu = "<a href=\"/lieu.php?idL=".$even->getValue('idLieu')."\" title=\"Voir la fiche du lieu : ".htmlspecialchars($tab_lieu['nom'])."\" >".htmlspecialchars($tab_lieu['nom'])."</a>";
		}
		else
		{
			$nom_lieu = htmlspecialchars($even->getValue('nomLieu'));
		}
		
	?>
	<tr <?php if ($date_debut == $even->getValue('dateEvenement')) { echo "class=\"ici\""; } ?> class="evenement">

		<td><?php echo date2nomJour($even->getValue('dateEvenement')) ?></td>

		<td><?php echo date2jour($even->getValue('dateEvenement')) ?></td>

		<td class="flyer">
            <?php
            if ($even->getValue('flyer') != '')
            {
                $imgInfo = @getimagesize($rep_images_even.$even->getValue('flyer'));

                ?>
                <a href="<?php echo $url_images_even.$even->getValue('flyer').'?'. @filemtime($rep_images_even.$even->getValue('flyer')) ?>" class="magnific-popup">
                    <img src="<?php echo $url_images_even."t_".$even->getValue('flyer')."?". @filemtime($rep_images_even."t_".$even->getValue('flyer')); ?>" alt="Flyer" width="60" />
                </a>			

                <?php


            }
            else if ($even->getValue('image') != '')
            {

                ?>
                <a href="<?php echo $url_images_even.$even->getValue('image').'?'. @filemtime($rep_images_even.$even->getValue('image')) ?>" class="magnific-popup">
                                <img src="<?php echo $url_images_even."s_".$even->getValue('image')."?". @filemtime($rep_images_even."t_".$even->getValue('image')); ?>" alt="Photo" width="60" />
                </a>			

                <?php			

            }
            ?>				
		</td>

		<td>
            <h3>
            <?php
            $titre_url = '<a href="/evenement.php?idE='.$even->getValue('idEvenement').'" title="Voir la fiche de l\'événement">'.Evenement::titre_selon_statut(sanitizeForHtml($even->getValue('titre')), $even->getValue('statut')).'</a>';
            echo $titre_url; ?>
            </h3>
            <p class="description"><?php echo $presentation; ?></p>

            <p class="pratique"><?php echo afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement'))." ".$even->getValue('prix') ?></p>
		</td>

		<td><?php echo $nom_lieu; ?></td>
		<td><?php echo $glo_tab_genre[$even->getValue('genre')] ?></td>

		<td class="lieu_actions_evenement">
            <?php
            if (
            (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
            || $_SESSION['SidPersonne'] == $even->getValue('idPersonne'))
            )
            ||  (isset($_SESSION['Saffiliation_lieu']) && !empty($get['idL']) && $get['idL'] == $_SESSION['Saffiliation_lieu'])
            ||  (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO']))
            || (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $id))
            || (isset($_SESSION['SidPersonne']) && $even->getValue('idLieu') != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $even->getValue('idLieu')))		
            )
            {
            ?>
            <ul>

                <li ><a href="/evenement-copy.php?idE=<?php echo $even->getValue('idEvenement') ?>" title="Copier cet événement"><?php echo $iconeCopier ?></a></li>
                <li ><a href="/evenement-edit.php?action=editer&amp;idE=<?php echo $even->getValue('idEvenement') ?>" title="Éditer cet événement"><?php echo $iconeEditer ?></a></li>
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
	echo "<p>Pas d'événement actuellement annoncé pour <strong>".$organisateur->getHtmlValue('nom')."</strong></p>";
}

if (!empty($tab_lieu['URL']))
{
	$URLcomplete = $tab_lieu['URL'];

	if (!preg_match("/^(https?:\/\/)/i", $tab_lieu['URL']))
	{
		$URLcomplete = "http://".$tab_lieu['URL'];
	}
	echo "<p>Pour des informations complémentaires : <a href=\"".$URLcomplete."\" title=\"Aller sur le site web\" onclick=\"window.open(this.href,'_blank');return false;\">".$tab_lieu['URL']."</a></p>\n";
}

echo '</div>';

?>



</div>
<!-- fin Contenu -->


<div id="colonne_gauche" class="colonne">



<?php
include("_navigation_calendrier.inc.php");
 ?>

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
