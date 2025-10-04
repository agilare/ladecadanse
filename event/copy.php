<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Validateur;

$get['action'] = "";
if (isset($_GET['action']))
{
	$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", 0, ["coller"]);
}

if (empty($_GET['idE']) || !is_numeric($_GET['idE']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}
$get['idE'] = (int) $_GET['idE'];

// EVENT AND APPENDIXES
$sql_event = "SELECT
  e.genre AS e_genre,
  e.idEvenement AS e_idEvenement,
  e.titre AS e_titre,
  e.statut AS e_statut,
  e.idPersonne AS e_idPersonne,
  e.dateEvenement AS e_dateEvenement,
  e.ref AS e_ref,
  e.flyer AS e_flyer,
  e.image AS e_image,
  e.description AS e_description,
  e.ref AS e_ref,
  e.horaire_debut AS e_horaire_debut,
  e.horaire_fin AS e_horaire_fin,
  e.horaire_complement AS e_horaire_complement,
  e.prix AS e_prix,
  e.prelocations AS e_prelocations,
  e.idLieu AS e_idLieu,
  e.idSalle AS e_idSalle,
  e.nomLieu AS e_nomLieu,
  e.adresse AS e_adresse,
  e.quartier AS e_quartier,
  loc.localite AS e_localite,
  e.region AS e_region,
  e.urlLieu AS e_urlLieu,
  e.dateAjout AS e_dateAjout,

  l.nom AS l_nom,
  l.determinant AS l_determinant,
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.lat AS l_lat,
  l.lng AS l_lng,
  l.URL AS l_URL,
  lloc.localite AS lloc_localite,
  l.region AS l_region,

  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
WHERE e.idEvenement = :idE";

$stmt = $connectorPdo->prepare($sql_event);
$stmt->execute([':idE' => $get['idE']]);
$tab_even = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($tab_even))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}

if (!$authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

$verif = new Validateur();

//jour de destination
$jour = '';
$mois = '';
$annee = '';
//fin de la tranche de destination si collage sur plusieurs jours
$jour2 = '';
$mois2 = '';
$annee2 = '';

$tab_event_copied = [];
if (!empty($_POST['submit']))
{
    $date_from = strip_tags((string) $_POST['from']);
    $date_to = strip_tags((string) $_POST['to']);

    $date_from_parts = explode(".", $date_from);
    $date_to_parts = explode(".", $date_to);

    $jour2 = $jour = stripslashes($date_from_parts[0]);
    $mois2 = $mois = stripslashes($date_from_parts[1]);
    $annee2 = $annee = stripslashes($date_from_parts[2]);

    if (!empty($date_to))
    {
        $jour2 = stripslashes($date_to_parts[0]);
        $mois2 = stripslashes($date_to_parts[1]);
        $annee2 = stripslashes($date_to_parts[2]);
    }

    //conversion des 2 dates en formats Unix et Y-m-d, -1 pour laisser php appliquer l'horaire d'hiver
	$dateEUnix = mktime(0, 0, 0, $mois, $jour, $annee);
	$dateEUnix2 = mktime(0, 0, 0, $mois2, $jour2, $annee2);
	$dateEvenement = date('Y-m-d', $dateEUnix);
	$dateEvenement2 = date('Y-m-d', $dateEUnix2);
	$date_auj = date('Y-m-d');

	//Vérifie que la date de début existe bien, qu'elle dans le futur et que la date de fin est après la date de début
	if (!checkdate($mois, $jour, $annee))
	{
		$verif->setErreur("dateEvenement", "Cette date n'existe pas");
	}
	elseif ($date_auj > $dateEvenement)
	{
		$verif->setErreur("dateEvenement", "L'événement doit être dans le futur");
	}
	elseif ($dateEUnix > $dateEUnix2)
	{
		$verif->setErreur("dateEvenement", "La première date doit être avant la deuxième");
	}

	// vérifie que la date de fin existe bien et qu'elle dans le futur
	if (!checkdate($mois2, $jour2, $annee2))
	{
		$verif->setErreur("dateEvenement", "La date de fin n'existe pas");
	}
	elseif ($date_auj  > $dateEvenement2)
	{
		$verif->setErreur("dateEvenement", "La date de fin doit être dans le futur");
	}

    if (!SecurityToken::check($_POST['token'], $_SESSION['token']))
    {
        $verif->setErreur("dateEvenement", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

	if ($verif->nbErreurs() === 0)
	{
		$tab_event_copied = $connector->fetchAssoc(($connector->query("SELECT idLieu, idSalle, genre, flyer, dateEvenement, image, titre, nomLieu, adresse, quartier, localite_id, region, urlLieu, description, ref, prix, horaire_debut, horaire_fin, horaire_complement, prelocations FROM evenement WHERE idEvenement=".(int)$get['idE'])));

		$tab_event_copied['idPersonne'] = $_SESSION['SidPersonne'];
        $flyer = "";
		// Initialisation de la date à incrémenter avec la date de début
		$dateIncrUnix = $dateEUnix;
		$dateIncrUnixOld = $dateIncrUnix;

		$_SESSION['copierEvenement_flash_msg']['msg'] = '<p style="margin:4px 0 10px 0; font-size: 1.1em">L\'événement <a href="/event/evenement.php?idE=' .(int)$get['idE'] . '"><strong>' . sanitizeForHtml($tab_event_copied['titre']) . '</strong> du ' . date_fr($tab_even['e_dateEvenement']) . '</a> a été copié vers les dates suivantes :</p>';
        $_SESSION['copierEvenement_flash_msg']['table'] = '';

		// Collage de l'événement entre la date de début et la date de fin
        $i = 0;
		while ($dateIncrUnix <= $dateEUnix2)
		{
            if ($i > 100)
            {
                $_SESSION['copierEvenement_flash_msg']['msg'] .= "<p><strong>La copie a été stoppée à 100 dates (afin de ménager les ressources du systmème), veuillez répéter l'opération pour les dates suivantes</strong></p>";
                break;
            }
			/*
			 *S'il y a un flyer création du nom de sa copie avec
			* l'ID du prochain événement inséré, la date courante et le suffixe
			*/
			$maxId = $connector->fetchArray($connector->query("SELECT MAX(idEvenement) AS max_id FROM evenement"));

			if (!empty($tab_event_copied['flyer']))
			{
				$flyer_orig = $tab_event_copied['flyer'];
				$tab_event_copied['flyer'] = ($maxId['max_id'] + 1) . "_" . date('Y-m-d', $dateIncrUnix) . mb_strrchr((string) $tab_event_copied['flyer'], '.');
            }

			if (!empty($tab_event_copied['image']))
			{

				$image_orig = $tab_event_copied['image'];
				$tab_event_copied['image'] = ($maxId['max_id'] + 1) . "_" . date('Y-m-d', $dateIncrUnix) . "_img" . mb_strrchr((string) $tab_event_copied['image'], '.');
            }

			$date_originale = $tab_event_copied['dateEvenement'];
			$date_prec = date('Y-m-d', $dateIncrUnixOld);
			$tab_event_copied['dateEvenement'] = date('Y-m-d', $dateIncrUnix);
			$tab_event_copied['dateAjout'] = date("Y-m-d H:i:s");
			$tab_event_copied['date_derniere_modif'] = date("Y-m-d H:i:s");
            // dump($tab_champs);
            if (mb_substr((string) $tab_event_copied['horaire_debut'], 11) != "06:00:01" && $tab_event_copied['horaire_debut'] != "0000-00-00 00:00:00")
            {
                if (mb_substr((string) $tab_event_copied['horaire_debut'], 0, 10) > $date_originale)
                {
                    $tab_event_copied['horaire_debut'] = date_lendemain($tab_event_copied['dateEvenement']) . " " . mb_substr((string) $tab_event_copied['horaire_debut'], 11);
                }
                else
                {
                    $tab_event_copied['horaire_debut'] = $tab_event_copied['dateEvenement'] . " " . mb_substr((string) $tab_event_copied['horaire_debut'], 11);
                }
			}
			else
			{
				$tab_event_copied['horaire_debut'] = date_lendemain($tab_event_copied['dateEvenement']) . " 06:00:01";
            }

			//echo date_lendemain($tab_champs['dateEvenement'])." 06:00:01";
			if (mb_substr((string) $tab_event_copied['horaire_fin'], 11) != "06:00:01" && $tab_event_copied['horaire_fin'] != "0000-00-00 00:00:00")
            {   // echo $date_originale;
                if (mb_substr((string) $tab_event_copied['horaire_fin'], 0, 10) > $date_originale)
                {   // echo $tab_champs['horaire_fin'];
                    $tab_event_copied['horaire_fin'] = date_lendemain($tab_event_copied['dateEvenement']) . " " . mb_substr((string) $tab_event_copied['horaire_fin'], 11);
                }
                else
                {
                    $tab_event_copied['horaire_fin'] = $tab_event_copied['dateEvenement'] . " " . mb_substr((string) $tab_event_copied['horaire_fin'], 11);
                }
			}
			else
			{
				$tab_event_copied['horaire_fin'] = date_lendemain($tab_event_copied['dateEvenement']) . " 06:00:01";
            }

			$sql_insert_attributs = "";
			$sql_insert_valeurs  = "";

			foreach ($tab_event_copied as $c => $v)
			{
				$sql_insert_attributs .= $c.", ";
				$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
			}

			$sql_insert_attributs = mb_substr($sql_insert_attributs, 0, -2);
            $sql_insert_valeurs = mb_substr($sql_insert_valeurs, 0, -2);

            $sql_insert = "INSERT INTO evenement (" . $sql_insert_attributs . ") VALUES (" . $sql_insert_valeurs . ")";

			if ($connector->query($sql_insert))
			{
				// lien d'édition de l'événement juste copié pour l'auteur ou les membres
				$edition = "";
				$nouv_id = $connector->getInsertId();

				$edition = " <a href=\"/evenement-edit.php?action=editer&idE=".(int)$nouv_id."\" title=\"Éditer l'événement\">".$iconeEditer."Modifier</a>";

                $hor_compl = '';
                if (!empty($tab_event_copied['horaire_complement']))
                    $hor_compl = "<br>" . sanitizeForHtml($tab_event_copied['horaire_complement']);

                $_SESSION['copierEvenement_flash_msg']['table'] .= '<tr>'
                    . '<td style="max-width:220px">' . sanitizeForHtml($tab_event_copied['titre']) . "</td>"
                    . "<td><strong>" . date_fr(date('Y-m-d', $dateIncrUnix)) . '</strong></td><td>' .  afficher_debut_fin($tab_event_copied['horaire_debut'], $tab_event_copied['horaire_fin'], $tab_event_copied['dateEvenement']) . $hor_compl . '</td>'
                    . '<td>' . $edition . '<a href="/evenement-edit.php?action=editer&idE=' . (int) $nouv_id . '" title="Modifier cet événement" target="_blank">&nbsp;&nbsp;<i class="fa fa-external-link" aria-hidden="true"></i></a>&nbsp;&nbsp;&nbsp;<a href="#" id="btn_event_del_' . (int) $nouv_id . '" class="btn_event_del action_supprimer" data-id=' . (int) $nouv_id . '>Supprimer</a></td>'
                    . '</tr>';

                if (!empty($tab_event_copied['flyer']))
				{
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($flyer_orig)), Evenement::getSystemFilePath(Evenement::getFilePath($tab_event_copied['flyer'])));
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($flyer_orig, "s_")), Evenement::getSystemFilePath(Evenement::getFilePath($tab_event_copied['flyer'], "s_")));
                    $flyer = '';
		        }

				if (!empty($tab_event_copied['image']))
				{
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($image_orig)), Evenement::getSystemFilePath(Evenement::getFilePath($tab_event_copied['image'])));
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($image_orig, "s_")), Evenement::getSystemFilePath(Evenement::getFilePath($tab_event_copied['image'], "s_")));
                }

                $req_orga = $connector->query("SELECT idOrganisateur FROM evenement_organisateur WHERE idEvenement=".(int)$get['idE']);
				while ($tab = $connector->fetchArray($req_orga))
				{
					$sql =  "INSERT INTO evenement_organisateur (idEvenement, idOrganisateur) VALUES (".(int)$nouv_id.", ".(int)$tab['idOrganisateur'].")";
					$connector->query($sql);
				}
			}

            //copie de la date courante, passage au jour suivant, et saut d'une heure en cas de passage à l'heure d'hiver
			$dateIncrUnixOld = $dateIncrUnix;
			$dateIncrUnix += 86400;

			if (date('Y-m-d', $dateIncrUnixOld) == date('Y-m-d', $dateIncrUnix))
			{
				$dateIncrUnix += 3600;
			}

            $i++;
		} //while date

        $logger->log('global', 'activity', "[copierEvenement] event \"" . $tab_event_copied['titre'] . "\" of " . $tab_event_copied['dateEvenement'] . " copied to " . $dateEvenement . " - " . $dateEvenement2, Logger::GRAN_YEAR);

        header("Location: ?idE=".(int)$get['idE']); die();
	} //if nberreur = 0
} // if POST != ""


$page_titre = "copier un événement vers d'autres dates";
$extra_css = ["formulaires"];

include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1 style="width:100%">Copier un événement vers d’autres dates</h1>
        <div class="spacer"></div>
    </header>

        <?php
        if (!empty($_SESSION['copierEvenement_flash_msg'])) : ?>
            <div class="msg_ok_copy">
                <?= $_SESSION['copierEvenement_flash_msg']['msg']; ?>
                <table class="table" style="width:100%">
                    <thead><tr><th>Événement</th><th>Date</th><th>Horaire</th><th></th></tr></thead>
                    <tbody><?= $_SESSION['copierEvenement_flash_msg']['table'] ?></tbody>
                </table>
            </div>
            <?php
            unset($_SESSION['copierEvenement_flash_msg']);
        endif;
        ?>


    <div style="width:94%;margin:0 auto">
        <?php
    //    if (empty($_POST['jour2'])) {
    //        $jour2 = $mois2 = $annee2 = '';
    //    }

        $date_du = '';
        if ($get['action'] !== 'coller')
        {
            $tab = explode("-", (string) $tab_even['e_dateEvenement']);
            $date_du = date('d.m.Y', mktime(0, 0, 0, $tab[1], $tab[2], $tab[0]) + 86400);
            ?>
            <?= Ladecadanse\EvenementRenderer::eventShortArticleHtml($tab_even); ?>
            </article>
        <?php
        }
        ?>

        <form method="post" id="ajouter_editer" style="margin:0;background:#efefef;border-radius: 4px;" enctype="multipart/form-data" action="<?=  basename(__FILE__) . "?action=coller&amp;idE=" . (int) $get['idE']; ?>">

            <fieldset>
                <legend style="font-size:1em;margin-left:-1em">Copier l’événement ci-dessus vers les dates suivantes (1 par jour)</legend>

                <p style="margin-left:.3em">Dans la page suivante vous pourrez si besoin modifier ou supprimer chaque événement un par un</p>

                <label for="from" style="float:none">du </label><input type="text" name="from" size="9" id="date-from" class="datepicker_from" placeholder="jj.mm.aaaa" required value="<?= $date_du; ?>">

                <span style="position:relative">
                    <label for="date-to" style="float:none">au </label><input type="text" name="to" size="9" id="date-to" class="datepicker_to" placeholder="jj.mm.aaaa">
                </span>&nbsp;<input id="coller" name="submit" type="submit" class="submit" value="Coller" style="width: 80px;margin-left: 0.6em;">
            </fieldset>

            <div style="margin: 0px 0 10px 30px;font-style: italic;color: #777;">Laissez la 2<sup>e</sup> date vide si vous ne collez l'événement que vers un seul jour.</div>
            <?= $verif->getHtmlErreur('dateEvenement') ?>
            <input type="hidden" name="token" value="<?=  SecurityToken::getToken(); ?>" />
        </form>
    </div>
</main> <!-- fin contenu -->

<div id="colonne_gauche" class="colonne">
    <?php include("_navigation_calendrier.inc.php"); ?>
</div>

<?php
include("../_footer.inc.php");
?>
