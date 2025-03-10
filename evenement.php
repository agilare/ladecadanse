<?php

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Evenement;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Validateur;

if (isset($_GET['idE']))
{
    try {
        $get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
    }
    catch (Exception)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }
}
else
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$even = new Evenement();
$even->setId($get['idE']);
$even->load();

$even_status = '';

// si idE ne correspond à aucune entrée dans la table
if (!$even->getValues() || in_array($even->getValue('statut'), ['inactif', 'propose']) )
 {
    // le staff, ainsi que l'auteur et les personnes liées par organisateur peuvent voir l'even dépublié
	if (
	isset($_SESSION['Sgroupe']) &&
	(
	$_SESSION['Sgroupe'] <= 6
	||
	$authorization->isAuthor("evenement", $_SESSION['SidPersonne'], $get['idE']) ||
	$authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $get['idE'])
	)

	)
	{
		$even_status = " <span class='even-statut-badge ".$even->getValue('statut')."'>".$statuts_evenement[$even->getValue('statut')]."</span>";
	}
	else
	{
		header("HTTP/1.1 404 Not Found");
		echo file_get_contents("articles/404.php");
		exit;
	}
}

$determinant_lieu = "- ";
if (!empty($even->getValue('idLieu'))) {
    $req_deter = $connector->query("SELECT determinant FROM lieu WHERE idLieu=" . (int) $even->getValue('idLieu'));
    $tab_deter = $connector->fetchArray($req_deter);

    if ($connector->getNumRows($req_deter) && !empty($tab_deter['determinant'])) {
        $determinant_lieu = $tab_deter['determinant'];
        if ($tab_deter['determinant'] != trim("l'") && $tab_deter['determinant'] != trim("à l'")) {
            $determinant_lieu .= " ";
        }
    }
}

$even_salle = '';
if ($even->getValue('idSalle') != 0)
{
	$req_salle = $connector->query("SELECT nom, emplacement FROM salle
	WHERE idSalle='" . (int) $even->getValue('idSalle') . "'");
    $tab_salle = $connector->fetchArray($req_salle);
	$even_salle = " - ".$tab_salle['nom'];

}


$req_localite = $connector->query("SELECT localite FROM localite WHERE  id='" . (int) $even->getValue('localite_id') . "'");
$tab_localite = $connector->fetchArray($req_localite);

$page_titre_localite = " – ";

$page_titre = $even->getValue('titre')." ".$determinant_lieu.$even->getValue('nomLieu').$even_salle.", ".HtmlShrink::getAdressFitted($even->getValue('region'), $tab_localite['localite'], $even->getValue('quartier'), $even->getValue('adresse'))."; le ".date_fr($even->getValue('dateEvenement'), "annee", "", "", false);
$page_description = $even->getValue('titre')." ".$determinant_lieu.$even->getValue('nomLieu').
" le ".date_fr($even->getValue('dateEvenement'), "annee", "", "", false)." ".
afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement'));

include("_header.inc.php");


// current user agenda order leads prev/next navigation

$get['tri'] = "dateAjout";
if (isset($_GET['tri'])) {
    try
    {
        $get['tri'] = Validateur::validateUrlQueryValue($_GET['tri'], "enum", 1, $tab_tri_agenda);
    }
    catch (Exception)
    {
//        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
//        exit;
    }
}

$req_even = $connector->query("SELECT dateEvenement, genre FROM evenement WHERE idEvenement=" . $get['idE']);

/*
Valeur exacte de la semaine de l'événement
*/
$sem = date2sem($even->getValue('dateEvenement'));

$sql_even = "
 SELECT idEvenement, titre FROM evenement
 WHERE  dateEvenement='" . $even->getValue('dateEvenement') . "'
 AND statut NOT IN ('inactif', 'propose')
     AND region='" . $even->getValue('region') . "'
 ORDER BY dateEvenement,
     CASE `genre`
     WHEN 'fête' THEN 1
     WHEN 'cinéma' THEN 2
     WHEN 'théâtre' THEN 3
     WHEN 'expos' THEN 4
     WHEN 'divers' THEN 5
     END,

 " . $get['tri'] . " DESC"; // genre='".$even->getValue('genre')."' AND
// echo $sql_even;

$req_even = $connector->query($sql_even);

$i = 0;
$courant = "";
$url_prec = "";
$url_suiv = "";
$id_passe = 0;
$titre = '';

while ($tab_even = $connector->fetchArray($req_even))
{
    if ($tab_even['idEvenement'] == $get['idE']) {
        $url_prec = $courant;
        $titre_prec = $titre;
        $id_passe = 1;
    }

    $courant = "/evenement.php?idE=" . $tab_even['idEvenement'] . "&amp;tri=" . $get['tri'];
    $titre = $tab_even['titre'];

    // préc déjà trouvé, suiv pas encore, pas l'actuel, donc c'est le suivant
    if ($id_passe && $url_suiv == "" && $tab_even['idEvenement'] != $get['idE']) {
        if ($i != $connector->getNumRows($req_even)) {
            $url_suiv = $courant;
            $titre_suiv = $titre;
        }
    }
    $i++;
}
?>


<!-- Début Contenu -->
<div id="contenu" class="colonne vevent">

    <?php
    if (!empty($_SESSION['evenement-edit_flash_msg'])) {
        HtmlShrink::msgOk($_SESSION['evenement-edit_flash_msg']);
        unset($_SESSION['evenement-edit_flash_msg']);
    }
    ?>

    <div id="entete_contenu">

        <h2 id="entete_contenu_titre" <?php if ($even->getValue('dateEvenement') < $glo_auj) {
        echo ' class="ancien"';
    } ?>>

            <span class="category">
                <?php echo sanitizeForHtml(ucfirst(Evenement::nom_genre($even->getValue('genre')))); ?></span>, <?php echo '<a href="/evenement-agenda.php?courant=' . $even->getValue('dateEvenement') . '"><time datetime="' . $even->getValue('dateEvenement') . '">' . date_fr($even->getValue('dateEvenement'), "annee", "", "", false) . '</time></a>';
                ?>
</h2>
		<div class="entete_contenu_navigation">
            <?php
            if ($url_prec != "") {
                echo '<a href="' . $url_prec . '" style="border-radius:3px 0 0 3px;" rel="prev">' . $iconePrecedent . '&nbsp;<span class="event-navig-link">' . sanitizeForHtml($titre_prec) . '</span>';

    echo '</a>';
        }
        if ($url_suiv != "") {
            echo '<a href="' . $url_suiv . '" style="border-radius:0 3px 3px 0;margin-left:1px" rel="next"><span class="event-navig-link">' . sanitizeForHtml($titre_suiv) . '</span>&nbsp;' . $iconeSuivant . '</a>';
}
        ?>
            <div class="spacer"></div>
		</div>
		<div class="spacer"></div>
	</div>
	<div class="spacer"><!-- --></div>
	<ul class="menu_actions_evenement">

        <?php
        if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::MEMBER)) {
		echo '<li><a href="/evenement-email.php?idE=' . (int) $get['idE'] . '" title="Envoyer l\'événement par email">' . $icone['envoi_email'] . 'Envoyer à un ami</a></li>';
        }
        if (
		(isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
		|| (isset($_SESSION['SidPersonne'])) && $_SESSION['SidPersonne'] == $even->getValue('idPersonne')
	|| (isset($_SESSION['Saffiliation_lieu']) && $even->getValue('idLieu') != '' && $even->getValue('idLieu') == $_SESSION['Saffiliation_lieu'])
		|| isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $even->getId())
		|| isset($_SESSION['SidPersonne']) && $even->getValue('idLieu') != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $even->getValue('idLieu'))
		)
		{
            ?>
            <li><a href="/evenement-copy.php?idE=<?php echo (int) $get['idE'] ?>"><?php echo $iconeCopier ?>Copier vers d'autres dates</a></li>
            <?php
		}
		if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 6)
		|| (isset($_SESSION['SidPersonne'])) && $_SESSION['SidPersonne'] == $even->getValue('idPersonne')
	|| (isset($_SESSION['Saffiliation_lieu']) && $even->getValue('idLieu') != '' && $even->getValue('idLieu') == $_SESSION['Saffiliation_lieu'])
	|| isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $even->getId())
			|| isset($_SESSION['SidPersonne']) && $even->getValue('idLieu') != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $even->getValue('idLieu'))
		)
		{
		?>
			<li><a href="/evenement-edit.php?action=editer&amp;idE=<?php echo $get['idE'] ?>"><?php echo $iconeEditer ?>Modifier</a></li>
		<?php
		}
		?>

            <li><a href="/evenement_ics.php?idE=<?php echo (int) $get['idE'] ?>" title="Exporter au format iCalendar dans votre agenda"><i class="fa fa-calendar-plus-o fa-lg"></i>
                    iCal</a></li>

    </ul>


	<div class="spacer"></div>

    <div id="evenement">

        <div class="dtstart"><span class="value-title" title="<?php echo $even->getValue('dateEvenement'); ?>T<?php echo mb_substr((string) $even->getValue('horaire_debut'), 11, 5); ?>:00"></span></div>

            <div class="titre">

                <h3 class="left summary"><?php echo Evenement::titre_selon_statut(sanitizeForHtml($even->getValue('titre')), $even->getValue('statut')); ?></h3>

                <?php
                //si le lieu est dans la base, affichage des détails du lieu,
                //$lieu contient un lien vers la fiche du lieu
                $lien_gmaps = "";

                if ($even->getValue('idLieu') != 0) {
                    $req_lieu = $connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, region, URL, lat, lng FROM lieu, localite
                                        WHERE localite_id=localite.id AND idlieu='" . (int) $even->getValue('idLieu') . "'");
    $listeLieu = $connector->fetchArray($req_lieu);
    $lieu = "<a href=\"//lieu.php?idLieu=" . (int) $even->getValue('idLieu') . "\">" . sanitizeForHtml($listeLieu['nom']) . "</a>";

    $nom_lieu = '<a href="/lieu.php?idL=' . (int) $even->getValue('idLieu') . '" >
                                        ' . sanitizeForHtml($even->getValue('nomLieu')) . '</a>';
}
else
{
    $listeLieu['nom'] = sanitizeForHtml($even->getValue('nomLieu'));
    $lieu = sanitizeForHtml($even->getValue('nomLieu'));
    $listeLieu['adresse'] = $even->getValue('adresse');
    $listeLieu['quartier'] = $even->getValue('quartier');

    $req_localite = $connector->query("SELECT  localite FROM localite
                                        WHERE  id='" . (int) $even->getValue('localite_id') . "'");
    $tab_localite = $connector->fetchArray($req_localite);

    $listeLieu['localite'] = $tab_localite[0] ?? "";

    $listeLieu['region'] = $even->getValue('region');
    $listeLieu['URL'] = sanitizeForHtml($even->getValue('urlLieu'));

    $nom_lieu = $lieu;
}

$adresse = HtmlShrink::getAdressFitted($listeLieu['region'], sanitizeForHtml($listeLieu['localite'] ?? ""), sanitizeForHtml($listeLieu['quartier']), sanitizeForHtml($listeLieu['adresse']));
?>


				<div class="right location vcard">
					<h4 class="fn org"><?php echo $nom_lieu ?>
					<?php
					if ($even->getValue('idSalle') != 0)
					{
                        $req_salle = $connector->query("SELECT nom, emplacement FROM salle WHERE idSalle='" . (int) $even->getValue('idSalle') . "'");
    $tab_salle = $connector->fetchArray($req_salle);
					echo '<br><span style="font-size:0.9em">' . sanitizeForHtml($tab_salle['nom']) . "</span>";
}
					?></h4>
					<ul style="list-style-type: none;">
						<li class="adr">

                            <?php echo $adresse; ?></li>
                        <?php
                        if (!empty($listeLieu['lat']) && !empty($listeLieu['lng']))
                        {
                        ?>

                            <li>
                                <a href="#" class="dropdown" data-target="plan"><?php echo $icone['plan']; ?> Voir sur le plan <i class="fa fa-caret-down" aria-hidden="true"></i></a>
                            </li>
                    <?php
                        }
						if (!empty($listeLieu['URL']))
						{?>
                            <li><a class="url lien_ext" href="<?php
                                       if (!preg_match("/^https?:\/\//", (string) $listeLieu['URL']))
						{
							echo 'http://' . $listeLieu['URL'];
    }
    else
						{
							echo $listeLieu['URL'];
						}
						?>" target="_blank"><?php echo $listeLieu['URL'] ?></a></li>
    <?php
}
						?>
                        <?php if ($even->getValue('idLieu') == 13) { // exception pour le Rez ?>
                        <a href="http://kalvingrad.com" class="url lien_ext" target="_blank">kalvingrad.com</a><br>
                            <a href="http://www.ptrnet.ch" class="url lien_ext" target="_blank">ptrnet.ch</a>
                        <?php } ?>
					</ul>
				</div>
			<div class="spacer"></div>
            	<?php
                if (!empty($listeLieu['lat']) && !empty($listeLieu['lng']))
                {
                ?>
                <div id="plan" style="display:none">
                        <div id="lieu-map-infowindow" style="display:none"><?php echo sanitizeForHtml($listeLieu['nom']) ?></div>
                        <div id="lieu-map" data-lat="<?php echo $listeLieu['lat'] ?>" data-lng="<?php echo $listeLieu['lng'] ?>"></div>
                </div>
                <?php } ?>
            </div>
			<!-- Fin titre -->



			<div id="complement">

				<ul id="images">
                    <li id="flyer" >

                        <?php
					$image_pour_flyer = false;
                        if ($even->getValue('flyer') != '')
					{
                            ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('flyer')), true) ?>" class="magnific-popup">
                                <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('flyer')), true) ?>" alt="Flyer de cet événement" width="160" />
                            </a>
                            <?php
                        }
					else if ($even->getValue('image') != '')
					{
						$image_pour_flyer = true;
                            ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('image')), true) ?>" class="magnific-popup">
                                <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('image')), true) ?>" alt="Photo pour cet événement" width="160" />
                            </a>
                            <?php
                        }
                        ?>
                    </li>

                    <li id="photo">
                        <?php
                        if ($even->getValue('image') != '' && !$image_pour_flyer)
                        {
                            ?>
                        <a href="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('image')), true) ?>" class="magnific-popup">
                                <img src="<?php echo Evenement::getFileHref(Evenement::getFilePath($even->getValue('image')), true) ?>" alt="Photo pour cet événement" width="160" />
                            </a>
                            <?php
                        }
                        ?>

                    </li>
				</ul>

			</div>
			<!-- Fin complement -->

			<div id="description">
                <a name="borne_description"></a>
                <?php
                if ($even->getValue('description') != '') {
                    echo Text::wikiToHtml(sanitizeForHtml($even->getValue('description'))) . "\n";
}
                else {
                    echo "&nbsp;";
                }
                ?>
            </div>
			<!-- Fin description -->

			<div class="spacer"></div>


			<div id="pratique">

				<?php
				echo "<ul class=\"left\">";
					$sql = "SELECT organisateur.idOrganisateur, nom, URL
				FROM organisateur, evenement_organisateur
				WHERE evenement_organisateur.idEvenement=" . (int) $get['idE'] . " AND
				 organisateur.idOrganisateur=evenement_organisateur.idOrganisateur
				 ORDER BY nom DESC";

				 $req = $connector->query($sql);
					while ($tab = $connector->fetchArray($req))
					{
						$url_org = $tab['URL'];
                        $nom_url = $tab['URL'];
						if (!preg_match("/^https?:\/\//", (string) $tab['URL']))
						{
							$url_org = 'http://'.$tab['URL'];
						}

						echo '<li><strong><a href="/organisateur.php?idO=' . $tab['idOrganisateur'] . '">' . sanitizeForHtml($tab['nom']) . '</strong></a>';
    if ( $tab['URL'] != '')
						{

							echo ' : <a href="' . sanitizeForHtml($url_org) . '" class="lien_ext" target="_blank">' . sanitizeForHtml($nom_url) . '</a>'; //$icone['url_externe']
    }
						echo '</li>';
					}

					$tab_ref = explode(";", strip_tags((string) $even->getValue('ref')));

foreach ($tab_ref as $r)
					{
						$r = trim($r);
						$r_aff = $r;

						if (mb_substr($r, 0, 3) == "www")
						{

							$r = "http://".$r;

						//echo "ok";
						}

						if (preg_match('#^(https?\\:\\/\\/)[a-z0-9_-]+\.([a-z0-9_-]+\.)?[a-zA-Z]{2,3}#i', $r))
						{
							echo "<li><a href=\"" . sanitizeForHtml($r) . "\" target='_blank' class=\"lien_ext\">";
        if (preg_match('/^https?:\/\/www/', $r))
							{
								echo wordwrap($r_aff, 30, "<br />", true);
        }
							else if (!empty($r))
							{
								echo wordwrap($r_aff, 30, "<br />", true);
        }
							echo "</a></li>";
						}
						else
						{
							echo "<li>" . sanitizeForHtml($r) . "<!-- --></li>";
    }
					}
					echo "</ul>";
					?>

					<table class="right" summary="Informations pratiques">
						<tr>
						<th><i class="fa fa-clock-o fa-lg"></i></th>
						<td>
						<?php
						echo afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement')) . "<br />" . sanitizeForHtml($even->getValue('horaire_complement'));
                            ?>

						</td>

						</tr>
						<tr>
                            <th><i class="fa fa-money fa-lg"></i></i></th><td><?php echo sanitizeForHtml($even->getValue('prix')) ?></td>
                        </tr>
						<tr>
                            <th><i class="fa fa-ticket fa-lg"></th><td><?php echo Text::linkify(sanitizeForHtml($even->getValue('prelocations'))); ?></td>
                        </tr>

					</table>
					<div class="spacer"></div>
			</div>
			<!-- Fin pratique -->


			<div class="spacer"><!-- --></div>

			<div id="auteur">

                <a class="signaler" href="/evenement-report.php?idE=<?php echo $get['idE'] ?>" ><i class="fa fa-flag-o fa-lg"></i> Signaler une erreur</a>


		Ajouté
		<?php

				$signature_auteur = "";
				$sql_auteur = "SELECT pseudo, affiliation, signature, avec_affiliation FROM personne WHERE idPersonne=" . (int) $even->getValue('idPersonne') . "";

$req_auteur = $connector->query($sql_auteur);
        $tab_auteur = $connector->fetchArray($req_auteur);

                if (!empty($tab_auteur))
                {
                    if ($tab_auteur['signature'] == 'pseudo')
                    {
                        $signature_auteur = " par <strong>" . sanitizeForHtml($tab_auteur['pseudo']) . "</strong> ";
            }

                    if ($tab_auteur['avec_affiliation'] == 'oui')
            {
                        $nom_affiliation = "";
                        $req_aff = $connector->query("SELECT idAffiliation FROM affiliation WHERE
         idPersonne=" . (int) $even->getValue('idPersonne') . " AND genre='lieu'");

        if (!empty($tab_auteur['affiliation']))
                        {
                            $nom_affiliation = $tab_auteur['affiliation'];
                        }
                        else if ($tab_aff = $connector->fetchArray($req_aff))
                        {
                            $req_lieu_aff = $connector->query("SELECT nom FROM lieu WHERE idLieu=" . (int) $tab_aff['idAffiliation']);
            $tab_lieu_aff = $connector->fetchArray($req_lieu_aff);
                            $nom_affiliation = $tab_lieu_aff['nom'];
                        }

                        $signature_auteur .= " (" . sanitizeForHtml($nom_affiliation) . ") ";
            }
                }


			if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4)
			{
			?>
            <a href="/user.php?idP=<?php echo $even->getValue('idPersonne') ?>"><?php echo $signature_auteur; ?></a>
            <?php
			}
			else
			{
				 echo $signature_auteur;
}
			?>
            le&nbsp;<?php echo date_fr($even->getValue('dateAjout'), "annee", "", "non") ?>
            </div>

		</div>
		<div style="color: #5c7378;    margin: 0em auto 0;    vertical-align: middle;width: 94%;">
            <div class="entete_contenu_navigation only-mobile" >
            <?php
            if ($url_prec != "")
            {
                echo '<a href="' . $url_prec . '" rel="prev" style="border-radius:3px 0 0 3px;">' . $iconePrecedent;

    echo '&nbsp;<span class="event-navig-link" style="width:110px">' . sanitizeForHtml($titre_prec) . '</span>';

    echo '</a>';
            }
            if ($url_suiv != "")
            {
                echo '<a href="' . $url_suiv . '" rel="next" style="border-radius:0 3px 3px 0;margin-left:1px">';

    echo '<span class="event-navig-link" style="width:110px">' . sanitizeForHtml($titre_suiv) . '</span>&nbsp;';

    echo $iconeSuivant.'</a>';
            }

            ?>
                <div class="spacer"></div>
            </div>
		</div>

		<!-- Fin Evenement -->

        <div class="spacer"><!-- --></div>

</div>
<!-- fin contenu -->

<div id="colonne_gauche" class="colonne">

    <?php
    $get['courant'] = $even->getValue('dateEvenement');
    include("_navigation_calendrier.inc.php");
    ?>

</div>

<div class="spacer"><!-- --></div>

<?php
include("_footer.inc.php");
?>
