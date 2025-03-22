<?php

global $connector;
require_once("app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Utils;

$page_titre = "Agenda";
$page_description = "Événements culturels et festifs à Genève et Lausanne : concerts, soirées, films, théâtre, expos...";

/* DATE COURANTE : _navigation_calendrier, agenda, evenement-edit */
$get['courant'] = $glo_auj_6h;
if (!empty($_GET['courant'])) {
    if (preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", trim((string) $_GET['courant']))) {
        $get['courant'] = $_GET['courant'];
    }
    else if (preg_match("/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}$/", trim((string) $_GET['courant']))) {
        $get['courant'] = date_app2iso($_GET['courant']);
    }
}

$get['sem'] = 0;
if (!empty($_GET['sem']) && is_numeric($_GET['sem'])) {
    $get['sem'] = $_GET['sem'];
}

$page_titre .= " du " . date_fr($get['courant'], "annee", "", "", false);
if ($get['sem'] == 1) {
    $lundim = date_iso2lundim($get['courant']);
    $page_titre .= " du " . date_fr($lundim[0], "annee", "", "", false) . " au " . date_fr($lundim[1], "annee", "", "", false);
}

$page_titre .= " à Genève";
if ($_SESSION['region'] == 'vd') {
    $page_titre .= " à Lausanne";
}
elseif ($_SESSION['region'] == 'fr') {
    $page_titre .= " à Fribourg";
}

$get['genre'] = "";
if (!empty($_GET['genre']) && array_key_exists(urldecode((string) $_GET['genre']), $glo_tab_genre)) {
    $get['genre'] = urldecode((string) $_GET['genre']);
}

if (empty($get['genre'])) {
    $get['genre'] = '';
    $genre_titre = 'Tout';
}
else {
    $genre_titre = $glo_tab_genre[$get['genre']];
}

/* TRI : index, _navigation_calendrier, agenda */
$get['tri_agenda'] = "dateAjout";
if (!empty($_GET['tri_agenda']) && in_array($_GET['tri_agenda'], $tab_tri_agenda)) {

    $get['tri_agenda'] = $_GET['tri_agenda'];
}

// build SQL
$sql_tri_agenda = $get['tri_agenda'] . " DESC";
if ($get['tri_agenda'] == "horaire_debut")
{
	$sql_tri_agenda = "horaire_debut ASC";
}

$sql_date_evenement = "LIKE '" . $get['courant'] . "%'";
if (isset($get['date_deb']) && isset($get['date_fin']))
{
	$sql_date_evenement = ">= '".date_app2iso($get['date_deb'])."' AND dateEvenement <= '".date_app2iso($get['date_fin'])."'";
}
else if ($get['sem'] == 1)
{
	$lundim = date_iso2lundim($get['courant']);
    $sql_date_evenement = ">= '" . $lundim[0] . "' AND dateEvenement <= '" . $lundim[1] . "'";
}


$entete_contenu = "";
if ($genre_titre != 'Tout')
	$entete_contenu =  ucfirst((string) $genre_titre)." du ";

[$annee_courant, $mois_courant, $jour_courant] = explode('-', (string) $get['courant']);

$lien_precedent = '';
$lien_suivant = '';

if (is_numeric($annee_courant) && is_numeric($mois_courant) && is_numeric($jour_courant))
{
    if ($get['sem'] == 0)
    {
        $entete_contenu .= date_fr($get['courant'], "annee");

        if ($genre_titre == 'Tout')
            $entete_contenu = ucfirst($entete_contenu);

        $precedent = date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant, (int) $jour_courant - 1, (int) $annee_courant));
        $lien_precedent = "<a href=\"/evenement-agenda.php?".Utils::urlQueryArrayToString($get)."&amp;courant=".$precedent."\" style=\"border-radius:3px 0 0 3px;\">".$iconePrecedent."&nbsp;</a>";
        $suivant = date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant, (int) $jour_courant + 1, (int) $annee_courant));

        $suivant_nomjour_parts = explode(" ", (string) date_fr($suivant, "tout", "non", ""));
        $lien_suivant = "<a href=\"/evenement-agenda.php?".Utils::urlQueryArrayToString($get)."&amp;courant=".$suivant."\" style=\"border-radius:0 3px 3px 0;background:#e4e4e4\" title=\"".$suivant_nomjour_parts[1]."\">".ucfirst($suivant_nomjour_parts[0])."<span class=desktop> ".$suivant_nomjour_parts[1]."</span>"."&nbsp;".$iconeSuivant."</a>";
    }
    else if ($get['sem'] == 1)
    {
        if ($genre_titre == 'Tout')
            $entete_contenu = ucfirst($entete_contenu);

        $entete_contenu .= date_fr($lundim[0], "non", "", "non") . " au " . date_fr($lundim[1], "annee", "", "non");
        $precedent = date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant, (int) ($jour_courant - 7), (int) $annee_courant));
        $lien_precedent = "<a href=\"/evenement-agenda.php?courant=".$precedent."&amp;sem=1&amp;genre=".$get['genre']."\" style=\"border-radius:3px 0 0 3px;background:#e4e4e4\">".$iconePrecedent."</a>";

        $suivant = date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant, (int) $jour_courant + 7, (int) $annee_courant));
        $lien_suivant = "<a href=\"/evenement-agenda.php?courant=" . $suivant . "&amp;sem=1&amp;genre=" . $get['genre'] . "\" style=\"border-radius:0 3px 3px 0;\">" . $iconeSuivant . "</a>";
    }
}

$sql_genre = '';
$sql_tri_agenda = " dateEvenement, CASE `genre`
   WHEN 'fête' THEN 1
   WHEN 'cinéma' THEN 2
   WHEN 'théâtre' THEN 3
   WHEN 'expos' THEN 4
   WHEN 'divers' THEN 5 END, " . $sql_tri_agenda;
if (isset($get['genre']) && $get['genre'] != '')
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

$get['page'] = 1;
if (!empty($_GET['page']) && is_numeric($_GET['page']))
{
	$get['page'] = (int)$_GET['page'];
}

$sql_rf = "";
if ($_SESSION['region'] == 'ge')
    $sql_rf = " 'rf', ";

$sql_region = " (region IN ('".$connector->sanitize($_SESSION['region'])."', ".$sql_rf." 'hs') OR FIND_IN_SET ('". $connector->sanitize($_SESSION['region']) ."', localite.regions_covered)) ";


$get['nblignes'] = 50;

$limite = " LIMIT ".($get['page'] - 1) * $get['nblignes'].",".$get['nblignes'];

$sql_even = "SELECT idEvenement, idLieu, idSalle, statut, genre, nomLieu, adresse, quartier, localite.localite AS localite,
 titre, idPersonne, dateEvenement, flyer, image, description, horaire_complement, horaire_debut, horaire_fin, price_type, prix, prelocations
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND ".$sql_genre." dateEvenement ".$sql_date_evenement." AND statut NOT IN ('inactif', 'propose') AND ".$sql_region."
 ORDER BY ".$sql_tri_agenda;

$req_nb = $connector->query($sql_even);
$total_even = $connector->getNumRows($req_nb);

$sql_even = $sql_even.$limite;

$req_even = $connector->query($sql_even);
$nb_evenements = $connector->getNumRows($req_even);

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
    JOIN localite ON evenement.localite_id=localite.id
    WHERE ".$sql_genre." dateEvenement ".$sql_date_evenement." AND statut NOT IN ('inactif', 'propose') AND ".$sql_region."
    ORDER BY dateEvenement ASC";

    $req_dateEven = $connector->query($sql_dateEven);
    $tab_date_even = [];
    while ($listeEven = $connector->fetchArray($req_dateEven))
    {
        $tab_date_even[] = $listeEven['dateEvenement'];
    }
}


include("_header.inc.php");
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
        <?php echo HtmlShrink::getPaginationString($total_even, $get['page'], $get['nblignes'], 1, "", "?" . Utils::urlQueryArrayToString($get, "page") . "&page="); ?>
        <form action="" method="get" class="queries">
            <div style="display:inline-block;margin-top:0.2em">

                <select name="genre" id="select_genre" class="js-auto-submiter">
                    <option value="">Filtre</option>
                    <?php
                    foreach ($glo_tab_genre as $na => $la)
                    {
                        echo "<option value=".$na;
                        if (isset($get['genre']) && $na == $get['genre'])
                        {
                            echo " selected";
                        }

                        echo ">".ucfirst((string) $la)."</option>";
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
                <select name="tri_agenda" id="select_order" class="js-auto-submiter">
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
	  	HtmlShrink::msgInfo("Pas d'événements");
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
	   		$dateSep = explode("-", (string) $listeEven['dateEvenement']);
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
			echo "<a name=\"date_".$listeEven['dateEvenement']."\" id=\"date_".$listeEven['dateEvenement']."\"></a>".ucfirst((string) date_fr($listeEven['dateEvenement']));
			echo "</h3>\n";
		}

		if (isset($listeEven['genre']) && $listeEven['genre'] != $genre_courant && isset($get['genre']) && $get['genre'] == '' )
		{
			if ($genre_courant != '')
			{
				echo "</div>";
                }
                ?>

			<div class="spacer"></div>
            <div class="genre">
                            <h4 class="genre-titre"><?php echo ucfirst((string) $glo_tab_genre[$listeEven['genre']]); ?></h4>
                            <div class="spacer"></div>
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
			$listeLieu = $connector->fetchArray($connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='" . (int) $listeEven['idLieu'] . "'"));

            $infosLieu = "<a href=\"/lieu.php?idL=" . (int) $listeEven['idLieu'] . "\">" . sanitizeForHtml($listeLieu['nom']) . "</a>";
            if ($listeEven['idSalle'])
			{
                $req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='" . (int) $listeEven['idSalle'] . "'");
                $tab_salle = $connector->fetchArray($req_salle);
                $infosLieu .= " - " . sanitizeForHtml($tab_salle['nom']);
            }
		}
		else
		{

			$listeLieu['nom'] = $listeEven['nomLieu'];
            $infosLieu = sanitizeForHtml($listeEven['nomLieu']);
            $listeLieu['adresse'] = $listeEven['adresse'];
            $listeLieu['quartier'] = $listeEven['quartier'];
            $listeLieu['localite'] = $listeEven['localite'];
        }

		$maxChar = Text::trouveMaxChar($listeEven['description'], 70, 8);

		$titre_url = '<a class="url" href="/evenement.php?idE=' . $listeEven['idEvenement'] . '&amp;tri_agenda=' . $get['tri_agenda'] . '&amp;courant=' . $get['courant'] . '">' . sanitizeForHtml($listeEven['titre']) . '</a>';
        $titre = Evenement::titre_selon_statut($titre_url, $listeEven['statut']);

		$lien_flyer = "";
		if (!empty($listeEven['flyer']))
		{
			$lien_flyer = '<a href="'.Evenement::getFileHref(Evenement::getFilePath($listeEven['flyer']), true).'" class="magnific-popup"><img src="'.Evenement::getFileHref(Evenement::getFilePath($listeEven['flyer'], 's_'), true).'"  alt="Flyer" width="100" /></a>';
		}
		else if ($listeEven['image'] != '')
		{
			$lien_flyer = '<a href="'.Evenement::getFileHref(Evenement::getFilePath($listeEven['image']), true).'" class="magnific-popup"><img src="'.Evenement::getFileHref(Evenement::getFilePath($listeEven['image'], 's_'), true).'" alt="Photo" width="100" /></a>';
		}

		if (mb_strlen((string) $listeEven['description']) > $maxChar)
		{
			$description = Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($listeEven['description'])), $maxChar);
            $description .= "<span class=\"continuer\">
			<a href=\"/evenement.php?idE=" . $listeEven['idEvenement'] . "&amp;tri_agenda=" . $get['tri_agenda'] . "\"> Lire la suite</a></span>";
        }
		else
		{
			$description = Text::wikiToHtml(sanitizeForHtml($listeEven['description']));
        }

        $sql_event_orga = "SELECT organisateur.idOrganisateur, nom, URL
        FROM organisateur, evenement_organisateur
        WHERE evenement_organisateur.idEvenement=" . (int) $listeEven['idEvenement'] . " AND
         organisateur.idOrganisateur=evenement_organisateur.idOrganisateur
         ORDER BY nom DESC";

        $req_event_orga = $connector->query($sql_event_orga);

		$adresse = HtmlShrink::getAdressFitted(null, sanitizeForHtml($listeLieu['localite'] ?? ""), sanitizeForHtml($listeLieu['quartier']), sanitizeForHtml($listeLieu['adresse']));

        $horaire = afficher_debut_fin($listeEven['horaire_debut'], $listeEven['horaire_fin'], $listeEven['dateEvenement']);

		// TODO : marche pas, à corriger (voir valeurs d'even sans début ou fin)
		if (($listeEven['horaire_debut'] != '0000-00-00 00:00:00' || $listeEven['horaire_fin'] != '0000-00-00 00:00:00') && !empty($listeEven['horaire_complement']) )
		{
			$horaire .= " " . sanitizeForHtml(lcfirst($listeEven['horaire_complement']));
        }
		else
		{
			$horaire .= sanitizeForHtml($listeEven['horaire_complement']);
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
            $horaire .= sanitizeForHtml($listeEven['prix']);
        }

        $actions = "";

        $vcard_starttime = '';
        if (mb_substr((string) $listeEven['horaire_debut'], 11, 5) != '06:00')
            $vcard_starttime = "T".mb_substr((string) $listeEven['horaire_debut'], 11, 5).":00";
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
                        $org_url_nom = rtrim(preg_replace("(^https?://)", "", (string) $tab['URL']), "/");
                        if (!preg_match("/^https?:\/\//", (string) $tab['URL']))
                        {
                            $org_url = 'http://'.$tab['URL'];
                        }

                    ?>
                        <li><a href="/organisateur.php?idO=<?php echo $tab['idOrganisateur']; ?>"><?php echo sanitizeForHtml($tab['nom']); ?></a> <a href="<?php echo sanitizeForHtml($org_url); ?>" class="lien_ext" target="_blank"><?php echo sanitizeForHtml($org_url_nom); ?></a></li>
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
                            <li><a href="/evenement-report.php?idE=<?php echo $listeEven['idEvenement']; ?>" class="signaler"  title="Signaler une erreur"><i class="fa fa-flag-o fa-lg"></i></a></li><li><a href="/evenement_ics.php?idE=<?php echo $listeEven['idEvenement']; ?>" class="ical" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i></a></li><?php echo $actions; ?></ul>

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
                        <a href="/evenement-copy.php?idE=<?php echo $listeEven['idEvenement']; ?>" title="Copier l'événement">Copier<span class="desktop"> vers d'autres dates</span></a>
                    </li>
                    <li class="action_editer">
                    <a href="/evenement-edit.php?idE=<?php echo $listeEven['idEvenement']; ?>&amp;action=editer" title="Éditer l'événement">Modifier</a>
                    </li>
                    <li class="action_depublier"><a href="#" id="btn_event_unpublish_<?php echo $listeEven['idEvenement']; ?>" class="btn_event_unpublish" data-id="<?php echo $listeEven['idEvenement']; ?>">Dépublier</a></li>
                    <li><a href="/user.php?idP=<?php echo $listeEven['idPersonne']; ?>"><?php echo $icone['personne']; ?></a></li>
                </ul>
                <?php
                }
                ?>
            </div>
        </div>

		<?php
		$i++;
	} //while

	if ($genre_courant != '' && isset($get['genre']) && $get['genre'] == '')
	{
		echo "</div>";
	}
	?>
	</div> <!-- evenements -->
<?php
}

if ($nb_evenements > 5)
{
	echo HtmlShrink::getPaginationString($total_even, $get['page'], $get['nblignes'], 1, "", "?" . Utils::urlQueryArrayToString($get, "page") . "&page=");
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

</div>


<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
