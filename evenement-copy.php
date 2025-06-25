<?php

require_once("app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Logger;
use Ladecadanse\Utils\Validateur;

if (!$videur->checkGroup(UserLevel::ACTOR)) {
	header("Location: index.php"); die();
}

$page_titre = "copier un événement vers d'autres dates";
$extra_css = ["formulaires", "evenement_inc"];

/*
* action choisie, ID si collage
*/
$tab_actions = ["coller"];
$get['action'] = "";

if (isset($_GET['action']))
{
	$get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", 0, $tab_actions);
}

if (isset($_GET['idE']))
{
	$get['idE'] = Validateur::validateUrlQueryValue($_GET['idE'], "int", 1);
}
else
{
	HtmlShrink::msgErreur("idE obligatoire");
	exit;
}


$req_lieu = $connector->query("SELECT idLieu, dateEvenement FROM evenement WHERE idEvenement=".$get['idE']);
$tab_lieu = $connector->fetchArray($req_lieu);


if ($authorization->isAuthor("evenement", $_SESSION['SidPersonne'], $get['idE']) || $_SESSION['Sgroupe'] <= UserLevel::AUTHOR || (isset($_SESSION['Saffiliation_lieu']) && isset($tab_lieu['idLieu']) && $tab_lieu['idLieu'] == $_SESSION['Saffiliation_lieu'])
|| $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $get['idE'])
|| (isset($tab_lieu['idLieu']) && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $tab_lieu['idLieu']))
 )

{
}
else
{
	HtmlShrink::msgErreur("Vous ne pouvez pas copier cet événement");
	exit;
}

$verif = new Validateur();

/*
* TRAITEMENT DU FORMULAIRE (COLLAGE)
*/

//jour de destination
$jour = '';
$mois = '';
$annee = '';

//fin de la tranche de destination si collage sur plusieurs jours
$jour2 = '';
$mois2 = '';
$annee2 = '';

$tab_champs = [];

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

	/*
	 * VERIFICATION DES CHAMPS ENVOYES par POST
	 */

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
		$tab_champs = $connector->fetchAssoc(($connector->query("
SELECT idLieu, idSalle, genre, flyer, dateEvenement, image, titre, nomLieu, adresse, quartier, localite_id, region, urlLieu, description, ref, prix,
 horaire_debut, horaire_fin, horaire_complement, prelocations
FROM evenement WHERE idEvenement=".(int)$get['idE'])));

		$tab_champs['idPersonne'] = $_SESSION['SidPersonne'];

        $hor_debfin = afficher_debut_fin($tab_champs['horaire_debut'], $tab_champs['horaire_fin'], $tab_champs['dateEvenement']);

        $flyer = "";

		//Initialisation de la date à incrémenter avec la date de début
		$dateIncrUnix = $dateEUnix;
		$dateIncrUnixOld = $dateIncrUnix;

		$_SESSION['copierEvenement_flash_msg']['msg'] = '<p style="margin:4px 0">L\'événement <a href="/evenement.php?idE=' .(int)$get['idE'] . '"><strong>' . sanitizeForHtml($tab_champs['titre']) . '</strong> du ' . date_fr($tab_lieu['dateEvenement']) . '</a> a été copié vers les dates suivantes :</p>';
        $_SESSION['copierEvenement_flash_msg']['table'] = '';

		/*
		 * Collage de l'événement entre la date de début et la date de fin
		 */
		while ($dateIncrUnix <= $dateEUnix2)
		{
			/*
			 *S'il y a un flyer création du nom de sa copie avec
			* l'ID du prochain événement inséré, la date courante et le suffixe
			*/
			$maxId = $connector->fetchArray($connector->query("SELECT MAX(idEvenement) AS max_id FROM evenement"));

			if (!empty($tab_champs['flyer']))
			{
				$flyer_orig = $tab_champs['flyer'];
				$tab_champs['flyer'] = ($maxId['max_id'] + 1) . "_" . date('Y-m-d', $dateIncrUnix) . mb_strrchr((string) $tab_champs['flyer'], '.');
            }

			if (!empty($tab_champs['image']))
			{

				$image_orig = $tab_champs['image'];
				$tab_champs['image'] = ($maxId['max_id'] + 1) . "_" . date('Y-m-d', $dateIncrUnix) . "_img" . mb_strrchr((string) $tab_champs['image'], '.');
            }

			$date_originale = $tab_champs['dateEvenement'];
			$date_prec = date('Y-m-d', $dateIncrUnixOld);
			$tab_champs['dateEvenement'] = date('Y-m-d', $dateIncrUnix);
			$tab_champs['dateAjout'] = date("Y-m-d H:i:s");
			$tab_champs['date_derniere_modif'] = date("Y-m-d H:i:s");
            // dump($tab_champs);
            if (mb_substr((string) $tab_champs['horaire_debut'], 11) != "06:00:01" && $tab_champs['horaire_debut'] != "0000-00-00 00:00:00")
            {
                if (mb_substr((string) $tab_champs['horaire_debut'], 0, 10) > $date_originale)
                {
                    $tab_champs['horaire_debut'] = date_lendemain($tab_champs['dateEvenement']) . " " . mb_substr((string) $tab_champs['horaire_debut'], 11);
                }
                else
                {
                    $tab_champs['horaire_debut'] = $tab_champs['dateEvenement'] . " " . mb_substr((string) $tab_champs['horaire_debut'], 11);
                }
			}
			else
			{
				$tab_champs['horaire_debut'] = date_lendemain($tab_champs['dateEvenement']) . " 06:00:01";
            }


			//echo date_lendemain($tab_champs['dateEvenement'])." 06:00:01";
			if (mb_substr((string) $tab_champs['horaire_fin'], 11) != "06:00:01" && $tab_champs['horaire_fin'] != "0000-00-00 00:00:00")
            {   // echo $date_originale;
                if (mb_substr((string) $tab_champs['horaire_fin'], 0, 10) > $date_originale)
                {   // echo $tab_champs['horaire_fin'];
                    $tab_champs['horaire_fin'] = date_lendemain($tab_champs['dateEvenement']) . " " . mb_substr((string) $tab_champs['horaire_fin'], 11);
                }
                else
                {
                    $tab_champs['horaire_fin'] = $tab_champs['dateEvenement'] . " " . mb_substr((string) $tab_champs['horaire_fin'], 11);
                }
			}
			else
			{
				$tab_champs['horaire_fin'] = date_lendemain($tab_champs['dateEvenement']) . " 06:00:01";
            }

			$sql_insert_attributs = "";
			$sql_insert_valeurs  = "";

			foreach ($tab_champs as $c => $v)
			{
				$sql_insert_attributs .= $c.", ";
				$sql_insert_valeurs .= "'".$connector->sanitize($v)."', ";
			}

			$sql_insert_attributs = mb_substr($sql_insert_attributs, 0, -2);
            $sql_insert_valeurs = mb_substr($sql_insert_valeurs, 0, -2);

            $sql_insert = "INSERT INTO evenement (" . $sql_insert_attributs . ") VALUES (" . $sql_insert_valeurs . ")";

            /*
			* Insertion réussie, message OK, RAZ des champs, copie du flyer (réduit et mini)
			*/
			if ($connector->query($sql_insert))
			{
				//lien d'édition de l'événement juste copié pour l'auteur ou les membres
				$edition = "";
				$nouv_id = $connector->getInsertId();

				$edition = " <a href=\"/evenement-edit.php?action=editer&idE=".(int)$nouv_id."\" title=\"Éditer l'événement\">".$iconeEditer."</a>";

                $hor_compl = '';
                if (!empty($tab_champs['horaire_complement']))
                    $hor_compl = "<br>" . sanitizeForHtml($tab_champs['horaire_complement']);

                $_SESSION['copierEvenement_flash_msg']['table'] .= '<tr><td><a href="/evenement.php?idE=' . (int) $nouv_id . '">' . sanitizeForHtml($tab_champs['titre']) . "<br>" . date_fr(date('Y-m-d', $dateIncrUnix)) . '</a></td><td>' . $hor_debfin . $hor_compl . '</td><td><a class="action_editer" href="/evenement-edit.php?action=editer&idE=' . (int) $nouv_id . '" title="Modifier cet événement">Modifier</a>&nbsp;&nbsp;<a href="/evenement-edit.php?action=editer&idE=' . (int) $nouv_id . '" title="Modifier cet événement dans un nouvel onglet" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i></a>&nbsp;&nbsp;&nbsp;<a href="#" id="btn_event_del_' . (int) $nouv_id . '" class="btn_event_del action_supprimer" data-id=' . (int) $nouv_id . '>Supprimer</a></td></tr>';

                if (!empty($tab_champs['flyer']))
				{
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($flyer_orig)), Evenement::getSystemFilePath(Evenement::getFilePath($tab_champs['flyer'])));
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($flyer_orig, "s_")), Evenement::getSystemFilePath(Evenement::getFilePath($tab_champs['flyer'], "s_")));
                    $flyer = '';
		        }

				if (!empty($tab_champs['image']))
				{
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($image_orig)), Evenement::getSystemFilePath(Evenement::getFilePath($tab_champs['image'])));
                    copy(Evenement::getSystemFilePath(Evenement::getFilePath($image_orig, "s_")), Evenement::getSystemFilePath(Evenement::getFilePath($tab_champs['image'], "s_")));
                }

                $req_orga = $connector->query("SELECT idOrganisateur FROM evenement_organisateur WHERE idEvenement=".(int)$get['idE']);

				while ($tab = $connector->fetchArray($req_orga))
				{
					$sql =  "INSERT INTO evenement_organisateur
					(idEvenement, idOrganisateur) VALUES (".(int)$nouv_id.", ".(int)$tab['idOrganisateur'].")";
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
		} //while date

        $logger->log('global', 'activity', "[copierEvenement] event \"" . $tab_champs['titre'] . "\" of " . $tab_champs['dateEvenement'] . " copied to " . $dateEvenement . " - " . $dateEvenement2, Logger::GRAN_YEAR);

        header("Location: ?idE=".$get['idE']); die();
	} //if nberreur = 0
} // if POST != ""

include("_header.inc.php");
?>

<div id="contenu" class="colonne">

    <div id="entete_contenu"><h2 style="width:100%">Copier un événement vers d'autres dates</h2><div class="spacer"></div></div>

    <div style="width:94%;margin:0 auto">
        <?php
        if (!empty($_SESSION['copierEvenement_flash_msg'])) {
            ?>

            <div class="msg_ok_copy">
                <?php echo $_SESSION['copierEvenement_flash_msg']['msg']; ?>
                <table class="table">
                    <thead><tr><th>Date</th><th>Horaire</th><th></th></tr></thead>
                    <tbody><?php echo $_SESSION['copierEvenement_flash_msg']['table']; ?></tbody>
                </table>
            </div>

            <?php
            unset($_SESSION['copierEvenement_flash_msg']);
        }
        ?>
    </div>
    <?php
    if (empty($_POST['jour2'])) {
        $jour2 = $mois2 = $annee2 = '';
    }

    $date_du = '';

/*
     * Récupérations des détails de l'événement à copier, affichage dans une boîte
     */
    if (isset($get['idE'])) {
        $req_getEven = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre, dateEvenement,
             nomLieu, adresse, quartier, localite, region, urlLieu, description, flyer, prix, horaire_debut,horaire_fin, horaire_complement, ref, prelocations,statut
              FROM evenement, localite WHERE evenement.localite_id=localite.id AND idEvenement =" . (int) $get['idE']);

        if ($affEven = $connector->fetchArray($req_getEven)) {
            //si le formulaire est chargé pour la 1ère fois, on prend la date extraite de la base
            if ($get['action'] != "coller") {
                $tab = explode("-", (string) $affEven['dateEvenement']);
                //$date_du = $tab[2].".".$tab[1].".".$tab[0];
                $date_du = date('d.m.Y', mktime(0, 0, 0, $tab[1], $tab[2], $tab[0]) + 86400);
            }

            $evenement = $affEven;

            include("_evenement.inc.php");
        }
        else {
            HtmlShrink::msgErreur("Aucun événement n'est associé à " . (int) $get['idE']);
            exit;
        }
    } // if isset idE
    ?>

    <form method="post" id="ajouter_editer" style="width: 94%;margin: 0em auto 0em auto;background:#efefef;padding: 1em 0;border-radius: 4px;" enctype="multipart/form-data" action="<?php echo basename(__FILE__) . "?action=coller&amp;idE=" . (int) $get['idE']; ?>">
        <h3 style="font-size:1em;margin-left:.3em">Copier l'événement ci-dessus vers les dates suivantes (1 par jour)</h3><p style="margin-left:.3em" >Dans la page suivante vous pourrez si besoin modifier ou supprimer chaque événement un par un</p>
        <label for="from" style="float:none">du </label><input type="text" name="from" size="9" id="date-from" class="datepicker_from" placeholder="jj.mm.aaaa" required value="<?php echo $date_du; ?>">
        <span style="position:relative"><label for="date-to" style="float:none">au </label><input type="text" name="to" size="9" id="date-to" class="datepicker_to" placeholder="jj.mm.aaaa"></span>
        &nbsp;<input id="coller" name="submit" type="submit" class="submit" value="Coller" style="width: 80px;margin-left: 0.6em;">
        <div style="margin: 15px 0 0px 30px;font-style: italic;color: #777;">Laissez la 2<sup>e</sup> date vide si vous ne collez l'événement que vers un seul jour.</div>
        <?php
        echo $verif->getHtmlErreur('dateEvenement');
        ?>
        <input type="hidden" name="token" value="<?php echo SecurityToken::getToken(); ?>" />
    </form>

</div> <!-- fin contenu -->

<div id="colonne_gauche" class="colonne">
    <?php include("_navigation_calendrier.inc.php"); ?>
</div>

<?php
include("_footer.inc.php");
?>
