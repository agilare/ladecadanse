<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Security\Sentry;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementCollection;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;

$videur = new Sentry();

$page_titre = "Agenda";
$page_description = "Événements culturels et festifs à Genève et Lausanne : concerts, soirées, films, théâtre, expos...";
include("_header.inc.php");

/* MOMENTS */
$tab_moments = array("journee", "soir", "nuit", "tout");
if (isset($_GET['moment']))
{
	if (in_array($_GET['moment'], $tab_moments))
	{
		$get['moment'] = $_GET['moment'];
	}
	else
	{
		trigger_error("moment non valable : ".$_GET['moment'], E_USER_WARNING);
		exit;
	}
}
else
{
	$get['moment'] = 'tout';
}

if ($get['tri_agenda'] == "horaire_debut")
{
	$sql_tri_agenda = "horaire_debut ASC";
}
else
{
	$sql_tri_agenda = $get['tri_agenda']." DESC";
}

if (isset($get['date_deb']) && isset($get['date_fin']))
{
	$sql_date_evenement = ">= '".date_app2iso($get['date_deb'])."' AND dateEvenement <= '".date_app2iso($get['date_fin'])."'";
}
else if ($get['sem'] == 1)
{
	$lundim = date_iso2lundim($get['courant']);
	$sql_date_evenement = ">= '".$lundim[0]."' AND dateEvenement <= '".$lundim[1]."'";
}
else
{
	$sql_date_evenement = "LIKE '".$get['courant']."%'";
}

if ($get['genre'] == '')
{
	$genre_titre = 'Tout';
}
else
{
	$genre_titre = $glo_tab_genre[$get['genre']];
}

$entete_contenu = "";
if ($genre_titre != 'Tout')
	$entete_contenu =  ucfirst($genre_titre)." du ";

$annee_courant = mb_substr($get['courant'], 0, 4);
$mois_courant = mb_substr($get['courant'], 5, 7);
$jour_courant = mb_substr($get['courant'], 8, 12);

$lien_precedent = '';
$lien_suivant = '';
if ($get['sem'] == 0)
{
	$entete_contenu .= date_fr($get['courant'], "annee");

	if ($genre_titre == 'Tout')	
		$entete_contenu = ucfirst($entete_contenu);
	
	$precedent = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant, $jour_courant - 1, $annee_courant));
	$lien_precedent = "<a href=\"/pages/agenda.php?".Utils::urlQueryArrayToString($get)."&amp;courant=".$precedent."\" style=\"border-radius:3px 0 0 3px;\">".$iconePrecedent."&nbsp;</a>";
	$suivant = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant, $jour_courant + 1, $annee_courant));

    
    $suivant_nomjour_parts = explode(" ", date_fr($suivant, "tout", "non", ""));
	$lien_suivant = "<a href=\"/pages/agenda.php?".Utils::urlQueryArrayToString($get)."&amp;courant=".$suivant."\" style=\"border-radius:0 3px 3px 0;background:#e4e4e4\" title=\"".$suivant_nomjour_parts[1]."\">".ucfirst($suivant_nomjour_parts[0])."<span class=desktop> ".$suivant_nomjour_parts[1]."</span>"."&nbsp;".$iconeSuivant."</a>";
}
else if ($get['sem'] == 1)
{
	if ($genre_titre == 'Tout')	
		$entete_contenu = ucfirst($entete_contenu);
		
	$entete_contenu .= date_fr($lundim[0], "non", "non", "non")." au ".date_fr($lundim[1], "annee", "non", "non");
	$precedent = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant  ,(int)($jour_courant - 7), $annee_courant));
	$lien_precedent = "<a href=\"/pages/agenda.php?courant=".$precedent."&amp;sem=1&amp;genre=".$get['genre']."\" style=\"border-radius:3px 0 0 3px;background:#e4e4e4\">".$iconePrecedent."</a>";

	$suivant = date("Y-m-d", mktime(0, 0, 0, (int)$mois_courant  , $jour_courant + 7, $annee_courant));
	$lien_suivant = "<a href=\"/pages/agenda.php?courant=".$suivant."&amp;sem=1&amp;genre=".$get['genre']."\" style=\"border-radius:0 3px 3px 0;\">".$iconeSuivant."</a>";

}

$sql_genre = '';
if ($get['genre'] != '')
{
	$sql_genre = "genre='".$get['genre']."' AND";
	$sql_tri_agenda = 'dateEvenement, '.$sql_tri_agenda;
}
else if ($get['sem'] == 0)
{
	$sql_tri_agenda = " CASE `genre`
       WHEN 'fête' THEN 1
       WHEN 'cinéma' THEN 2
       WHEN 'théâtre' THEN 3
       WHEN 'expos' THEN 4
       WHEN 'divers' THEN 5 END, dateEvenement, ".$sql_tri_agenda;
}
else
{
	$sql_tri_agenda = " dateEvenement, CASE `genre`
       WHEN 'fête' THEN 1
       WHEN 'cinéma' THEN 2
       WHEN 'théâtre' THEN 3
       WHEN 'expos' THEN 4
       WHEN 'divers' THEN 5 END, ".$sql_tri_agenda;
}

if (isset($_GET['page']))
{
	$get['page'] = (int)$_GET['page'];
}
else
{
	$get['page'] = 1;
}

$sql_rf = "";
if ($_SESSION['region'] == 'ge')
    $sql_rf = " 'rf', ";

$sql_region = " region IN ('".$connector->sanitize($_SESSION['region'])."', ".$sql_rf." 'hs') ";


$get['nblignes'] = 50;

$limite = " LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes'];

$sql_even = "SELECT idEvenement, idLieu, idSalle, statut, genre, nomLieu, adresse, quartier, localite.localite AS localite,
 titre, idPersonne, dateEvenement, URL1, flyer, image, description, horaire_complement, horaire_debut, horaire_fin, price_type, prix, prelocations
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND ".$sql_genre." dateEvenement ".$sql_date_evenement." AND statut NOT IN ('inactif', 'propose') AND ".$sql_region." 
 ORDER BY ".$sql_tri_agenda;
 
$req_nb = $connector->query($sql_even);
$total_even = $connector->getNumRows($req_nb);

$sql_even = $sql_even.$limite;

$req_even = $connector->query($sql_even);
$nb_evenements = $connector->getNumRows($req_even);

$lien_imprimer = '<a href="'.basename(__FILE__).'?'.Utils::urlQueryArrayToString($get).'&amp;style=imprimer" title="Format imprimable">';

if ($get['sem'])
{
    $sql_genre = '';
    if ($get['genre'] != '')
    {
        $sql_genre = "genre='".$get['genre']."' AND ";
    }
    $sql_dateEven = "
    SELECT DISTINCT dateEvenement
    FROM evenement
    WHERE ".$sql_genre." dateEvenement ".$sql_date_evenement." AND statut NOT IN ('inactif', 'propose') AND ".$sql_region." 
    ORDER BY dateEvenement ASC";

    $req_dateEven = $connector->query($sql_dateEven);
    $tab_date_even = array();
    while ($listeEven = $connector->fetchArray($req_dateEven))
    {
        $tab_date_even[] = $listeEven['dateEvenement'];
    }
}
?>

<!-- Deb contenu -->
<div id="contenu" class="colonne">
   
	<div id="entete_contenu">
		<h2>Agenda</h2><?php HtmlShrink::getMenuRegions($glo_regions, $get); ?>
        <div class="spacer"></div>
        <div style="margin-top: 0.6em;">
            <h3><?php echo $entete_contenu ?></h3> 
            <ul class="entete_contenu_navigation" style="width:45%;margin-top: 0.5em;">
                <li><?php echo $lien_precedent.$lien_suivant; ?></li>
            </ul>
            <div class="spacer"></div>
        </div>          
	</div>	<!-- entete_contenu -->
	<div class="spacer"></div>
    <div>
	<?php echo HtmlShrink::getPaginationString($get['page'], $total_even, $get['nblignes'], 1, $_SERVER['PHP_SELF'], "?".Utils::urlQueryArrayToString($get, "page")."&page=");?>
        <form action="" method="get" class="queries">
            <div style="display:inline-block;margin-top:0.2em">
                
                <select name="genre" id="select_genre" onChange="javascript:this.form.submit();">
                    <option value="">Filtre</option>
                    <?php
                    foreach ($glo_tab_genre as $na => $la)
                    {
                        echo "<option value=".$na;
                        if ($na == $get['genre'])
                        {
                            echo " selected";
                        }

                        echo ">".ucfirst($la)."</option>";
                    }
                    ?>
                </select>
            </div>
            <div style="display:inline-block;margin-top:0.2em">
                <?php
                foreach ($get as $nom => $valeur)
                {
                    if ($nom != "tri_agenda" && $nom != "genre")
                    {
                    ?>
                    <input type="hidden" name="<?php echo $nom;?>" value="<?php echo $valeur;?>" />
                    <?php
                    }
                }            
                ?>
                <label for="select_order">Tri</label>
                <select name="tri_agenda" id="select_order" onChange="javascript:this.form.submit();">
                    <option value="dateAjout" <?php if ($get['tri_agenda'] == 'dateAjout') { ?>selected<?php } ?>>date d’ajout</option>
                    <option value="horaire_debut" <?php if ($get['tri_agenda'] == 'horaire_debut') { ?>selected<?php } ?>>heure de début</option>
                </select>
            </div>
        </form>
        <div class="spacer"></div>
        </div>
    <?php

	if ($nb_evenements == 0)
	{
	  	echo HtmlShrink::msgInfo("Pas d'événements", "p");
	}
	else
	{
		$dateCourante = '';
		$genre_courant = '';
		$tab_jours_semaine = [];

		$i = 0;
		$nb_even_jour = 1;
	?>

    <div id="evenements">
    <?php

	while ($listeEven = $connector->fetchArray($req_even))
	{
		//ajout d'une bande avec la date et un bouton si ce n'est pas la première
		if ($dateCourante != $listeEven['dateEvenement'] && $get['sem'] == 1)
		{
			$nb_even_jour = 0;
	   		$dateSep = explode("-", $listeEven['dateEvenement']);
			$tab_jours_semaine[] = $listeEven['dateEvenement'];
	   		//lien interne avec le no de jour
			$nomJour = date("l", mktime(0, 0, 0, $dateSep[1], $dateSep[2], $dateSep[0]));
			$menu_date = '<div class="';
			if ($i == 0)
			{
				$menu_date .= 'menu_date_1er';
			}
			else
			{
				$menu_date .= 'menu_date';
			}

			$menu_date .= '">';

			if ($i > 0)
			{
				echo "<ul class=\"menu_ascenseur\">";

				$date_prec = "";
				$date_suiv = "";

				for ($i = 0; $i < count($tab_date_even); $i++)
				{

					/* echo $i;
					echo $tab_date_even[$i]; */
					if ($listeEven['dateEvenement'] == $tab_date_even[$i])
					{
						echo "<li>";
						if ($i > 0)
						{
							echo "<a class=\"vertical\" title=\"Remonter\" href=\"#date_".$tab_date_even[$i - 1]."\">".$icone['monter']."</a>";
						}

						if ($i < count($tab_date_even) - 1)
						{
							echo "<a class=\"vertical\" title=\"Descendre\" href=\"#date_".$tab_date_even[$i + 1]."\">".$icone['descendre']."</a>";
						}
						echo "</li>";
					}
				}
                
                echo "<li class=\"haut2\"><a title=\"Remonter en haut de la page\" href=\"#global\">".$iconeRemonter."</a></li>";
				echo "</ul>";
			}

			echo "\n<h3 class=\"menu_date\">";
			echo "<a name=\"date_".$listeEven['dateEvenement']."\" id=\"date_".$listeEven['dateEvenement']."\"></a>".ucfirst(date_fr($listeEven['dateEvenement']));
			echo "</h3>\n";
		}	

		if ($listeEven['genre'] != $genre_courant && $get['genre'] == '')
		{
			if ($genre_courant != '')
			{
				echo "</div>";
			}
			?>
			
			<div class="spacer"></div>
			<div class="genre">

			<div class="genre-titre">
                <h4 id="<?php echo Text::stripAccents($listeEven['genre']); ?>"><?php echo ucfirst(Evenement::nom_genre($listeEven['genre'])); ?></h4>
                <?php if (0) { //(isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1 && $listeEven['genre'] != 'divers') { ?>
                <a class="genre-jump" href="#<?php echo $proch; ?>"><i class="fa fa-long-arrow-down"></i></a>
                <?php } else { ?>
                <span style="float: right;margin: 0.2em;padding: 0.4em 0.8em;">&nbsp;</span>
                <?php } ?>	

                <?php if (0) { //isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1 && $listeEven['genre'] != 'fête') { ?>
                <a class="genre-jump" href="#<?php echo $genre_prec; ?>"><i class="fa fa-long-arrow-up"></i></a>
                <?php } ?>
                <div class="spacer"></div>		
			</div>
			<?php
		}

		$genre_courant = $listeEven['genre'];	
        
		if ($i != 0 && ($i % 2 != 0 && $get['sem'] == 0) ||
		($get['sem'] == 1 && $nb_even_jour % 2 == 0 && $dateCourante == $listeEven['dateEvenement']))
		{
            $region = $glo_regions[$_SESSION['region']];
            if ($_SESSION['region'] == 'vd')
                $region = "Lausanne";
            if ($_SESSION['region'] == 'fr')
                $region = "Fribourg";            
            
			echo "<h5>".$region.", ".date_fr($dateCourante);
			echo "</h5><div class=\"spacer\"></div>";
		}

		$dateCourante = $listeEven['dateEvenement'];
		$nb_even_jour++;


		//Affichage du lieu selon son existence ou non dans la base
		if (!empty($listeEven['idLieu']))
		{
			$listeLieu = $connector->fetchArray($connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='".$listeEven['idLieu']."'"));

			$infosLieu = "<a href=\"/pages/lieu.php?idL=".$listeEven['idLieu']."\" title=\"Voir la fiche du lieu : ".htmlspecialchars($listeLieu['nom'])."\" >".htmlspecialchars($listeLieu['nom'])."</a>";
			if ($listeEven['idSalle'])
			{
                $req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='".$listeEven['idSalle']."'");
                $tab_salle = $connector->fetchArray($req_salle);
                $infosLieu .= " - ".$tab_salle['nom'];
			}
		}
		else
		{

			$listeLieu['nom'] = htmlspecialchars($listeEven['nomLieu']);
			$infosLieu = htmlspecialchars($listeEven['nomLieu']);
			$listeLieu['adresse'] = htmlspecialchars($listeEven['adresse']);
			$listeLieu['quartier'] = htmlspecialchars($listeEven['quartier']);
			$listeLieu['localite'] = htmlspecialchars($listeEven['localite']);
		}

		$maxChar = Text::trouveMaxChar($listeEven['description'], 70, 8);

		$titre_url = '<a class="url" href="/pages/evenement.php?idE='.$listeEven['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'" title="Voir la fiche complète de l\'événement">'.sanitizeForHtml($listeEven['titre']).'</a>';
		$titre = Evenement::titre_selon_statut($titre_url, $listeEven['statut']);

		$lien_flyer = "";
		if (!empty($listeEven['flyer']))
		{
			$imgInfo = @getimagesize($rep_images.$listeEven['flyer']);
			$lien_flyer = '<a href="'.$url_images_even.$listeEven['flyer']."?".@filemtime($rep_images_even.$listeEven['flyer']).'" class="magnific-popup"><img src="'.$url_images_even."s_".$listeEven['flyer']."?".@filemtime($rep_images_even.$listeEven['flyer']).'"  alt="Flyer" width="100" /></a>';			
		}
		else if ($listeEven['image'] != '')
		{	
			$imgInfo = @getimagesize($rep_images.$listeEven['image']);
			$lien_flyer = '<a href="'.$url_images_even.$listeEven['image']."?".@filemtime($rep_images_even.$listeEven['image']).'" class="magnific-popup"><img src="'.$url_images_even."s_".$listeEven['image']."?".@filemtime($rep_images_even.$listeEven['image']).'" alt="Photo" width="100" /></a>';					
		}

		if (mb_strlen($listeEven['description']) > $maxChar)
		{
			$description = Text::texteHtmlReduit(Text::wikiToHtml($listeEven['description']),
			$maxChar);
			$description .= "<span class=\"continuer\">
			<a href=\"/pages/evenement.php?idE=".$listeEven['idEvenement']."&amp;tri_agenda=".$get['tri_agenda']."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
		}
		else
		{
			$description = Text::wikiToHtml($listeEven['description']);
		}
        
        $sql_event_orga = "SELECT organisateur.idOrganisateur, nom, URL
        FROM organisateur, evenement_organisateur
        WHERE evenement_organisateur.idEvenement=".$listeEven['idEvenement']." AND
         organisateur.idOrganisateur=evenement_organisateur.idOrganisateur
         ORDER BY nom DESC";

        $req_event_orga = $connector->query($sql_event_orga);        

		$adresse = htmlspecialchars(HtmlShrink::getAdressFitted(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']));
		
		$horaire = afficher_debut_fin($listeEven['horaire_debut'], $listeEven['horaire_fin'], $listeEven['dateEvenement']);

		// TODO : marche pas, à corriger (voir valeurs d'even sans début ou fin)
		if (($listeEven['horaire_debut'] != '0000-00-00 00:00:00' || $listeEven['horaire_fin'] != '0000-00-00 00:00:00') && !empty($listeEven['horaire_complement']) )
		{
			$horaire .= " ".lcfirst(htmlspecialchars($listeEven['horaire_complement'])) ;
		}				
		else
		{
			$horaire .= htmlspecialchars($listeEven['horaire_complement']);
		}
        
        if (!empty($listeEven['price_type']) && in_array($listeEven['price_type'], ['gratis', 'asyouwish']))
        { 
            $horaire .= " ".$price_types[$listeEven['price_type']];
        }        
        
        if (!empty($listeEven['prix']))
        {
            if (!empty($listeEven['horaire_debut']) || !empty($listeEven['horaire_fin']) || !empty($listeEven['horaire_complement']))
            {
                $horaire .= ", ";
            }
            $horaire .= htmlspecialchars($listeEven['prix']);		
        }

        
		$sql_dateEven = "
		SELECT idCommentaire
		 FROM commentaire
		 WHERE id='".$listeEven['idEvenement']."' AND statut='actif'";

		// echo $sql_even;
		$commentaires = "";
		$req_dateEven = $connector->query($sql_dateEven);
		$nb_comm = $connector->getNumRows($req_dateEven);
		if ($nb_comm > 0)
		{
			$pluriel = "";
			if ($nb_comm > 1)
				$pluriel = "s";

			$commentaires = '<li>'.$icone['commentaire'].'
			<a href="/pages/evenement.php?idE='.$listeEven['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'#borne_commentaires"
			title="Voir le'.$pluriel.' '.$nb_comm.' commentaires">'.$nb_comm.' commentaire'.$pluriel.'</a>';
			$commentaires .= '</li>';
		}
			
		$actions = "";
		if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 12))
		{
			if ($nb_comm == 0)
			{
			$actions .= '<li>
			<a href="/pages/evenement.php?idE='.$listeEven['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'#commentaires"
			title="Ajouter un commentaire à cet événement">'.$icone['ajouter_commentaire'].'</a></li>';
			}
			$req_nb_favori = $connector->query("SELECT * FROM evenement_favori
			WHERE idEvenement=".$listeEven['idEvenement']." AND idPersonne=".$_SESSION['SidPersonne']);

			$nb_favori = $connector->getNumRows($req_nb_favori);

			$actions .= '<li>';
			if ($nb_favori == 0)
			{
				$actions .= '<a href="/pages/multi-star.php?action=ajouter&amp;element=evenement&amp;idE='.$listeEven['idEvenement'].'"
			title="Ajouter à vos favoris">'.$icone['ajouter_favori'].'</a>';
			}
			else
			{
				$actions .= $icone['favori'].'en favori <a href="/pages/multi-star.php?action=supprimer&amp;element=evenement&amp;idE='.$listeEven['idEvenement'].'"
			title="Enlever de vos favoris">'.$icone['supprimer_favori'].'</a>';
			}

			$actions .= '</li>';
		}
        
        $vcard_starttime = '';
        if (mb_substr($listeEven['horaire_debut'], 11, 5) != '06:00')
            $vcard_starttime = "T".mb_substr($listeEven['horaire_debut'], 11, 5).":00";	
        ?>
                
		<div id="event-<?php echo $listeEven['idEvenement']; ?>" class="evenement vevent">
			<div class="dtstart">
                <span class="value-title" title="<?php echo $listeEven['dateEvenement'].$vcard_starttime; ?>"></span>	
			</div>		
			<div class="titre">
                <span class="left summary"><?php echo $titre; ?></span><span class="right location"><?php echo $infosLieu ?></span>
                <div class="spacer"></div>
			</div>
			<div class="spacer"></div>
            <div class="event-contenu">
            <div class="flyer photo"><?php echo $lien_flyer; ?></div>
                <div class="description" style="position:relative">
                    <?php echo $description;?>
                    <ul class="event_orga">
                    <?php
                    while ($tab = $connector->fetchArray($req_event_orga))
                    {
                        $org_url = $tab['URL'];
                        $org_url_nom = rtrim(preg_replace("(^https?://)", "", $tab['URL']), "/");
                        if (!preg_match("/^https?:\/\//", $tab['URL']))
                        {
                            $org_url = 'http://'.$tab['URL'];
                        }                    

                    ?>
                        <li><a href="/pages/organisateur.php?idO=<?php echo $tab['idOrganisateur']; ?>" title="Voir la fiche de l'organisateur"><?php echo $tab['nom']; ?></a> <a href="<?php echo $org_url; ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?php echo $org_url_nom; ?></a></li>                
                    <?php
                    }             
                    ?>
                    </ul>        
                </div>
            </div>
			<div class="pratique"><span class="left"><?php echo $adresse; ?></span><span class="right"><?php echo $horaire; ?></span>
				<div class="spacer"></div>
			</div> <!-- fin pratique -->
			<div class="spacer"></div>
			<div class="edition">
                <ul class="menu_action">
                    <li><a href="/pages/evenement-report.php?idE=<?php echo $listeEven['idEvenement']; ?>" class="signaler"  title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li><li><a href="/pages/evenement_ics.php?idE=<?php echo $listeEven['idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li><?php echo $commentaires; echo $actions;?></ul>
			
                <?php
                if (isset($_SESSION['Sgroupe'])
                && (
                $_SESSION['Sgroupe'] <= 6
                || $_SESSION['SidPersonne'] == $listeEven['idPersonne']
            || (isset($_SESSION['Saffiliation_lieu']) && !empty($listeEven['idLieu']) && $listeEven['idLieu'] == $_SESSION['Saffiliation_lieu'])
            || $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $listeEven['idEvenement'])
            || $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $listeEven['idLieu'])
                ))
                {
                ?>
                <ul class="menu_edition">
                    <li class="action_copier">
                        <a href="/pages/evenement-copy.php?idE=<?php echo $listeEven['idEvenement']; ?>" title="Copier l'événement">Copier<span class="desktop"> vers d'autres dates</span></a>
                    </li>	
                    <li class="action_editer">
                    <a href="/pages/evenement-edit.php?idE=<?php echo $listeEven['idEvenement']; ?>&amp;action=editer" title="Éditer l'événement">Modifier</a>
                    </li>
                    <li class="action_depublier"><a href="#" id="btn_event_unpublish_<?php echo $listeEven['idEvenement']; ?>" class="btn_event_unpublish" data-id="<?php echo $listeEven['idEvenement']; ?>">Dépublier</a></li>                    
                    <li><a href="/pages/user.php?idP=<?php echo $listeEven['idPersonne']; ?>"><?php echo $icone['personne']; ?></a></li>                    
                </ul>
                <?php
                }
                ?>
            </div>
        </div>

		<?php
		$i++;
	} //while
	
	if ($genre_courant != '' && $get['genre'] == '')
	{
		echo "</div>";
	}
	?>
	</div> <!-- evenements -->
<?php
}

if ($nb_evenements > 5)
{ 
	echo HtmlShrink::getPaginationString($get['page'], $total_even, $get['nblignes'], 1, $_SERVER['PHP_SELF'], "?".Utils::urlQueryArrayToString($get, "page")."&page=");
	?>
	<div id="entete_contenu">
		<h2><?php echo $entete_contenu ?></h2>			
		<ul class="entete_contenu_navigation">
			<li><?php echo $lien_precedent.$lien_suivant; ?></li>
        </ul>
		<div class="spacer"></div>
	</div><!-- entete_contenu -->	
<?php
} //if nb evenement
?>
</div><!-- contenu -->


<div id="colonne_gauche" class="colonne">
    <?php include("_navigation_calendrier.inc.php"); ?>
</div>

<?php
$tri_ajout = '';
if ($get['tri_agenda'] == "dateAjout") $tri_ajout = "ici";
?>

<div id="colonne_droite" class="colonne">

	<div id="selection">
		<?php
		$liste = '';
		if ($get['sem'] == 1 && $nb_evenements > 1)
		{

			for ($i = 1; $i < count($tab_jours_semaine); $i++)
			{
				$liste = "<li class=\"vers_jour\"><a href=\"#date_".$tab_jours_semaine[$i]."\" title=\"".$tab_jours_semaine[$i]."\">".
				date_fr($tab_jours_semaine[$i])."</a>
				</li>";
			}

			?>
			<h2>Aller à</h2>
			<ul class="menu_selection">
			<?php echo $liste ?>
			</ul>
			<?php
		}
			?>
	</div>
	<!-- Fin selection -->

</div>


<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
