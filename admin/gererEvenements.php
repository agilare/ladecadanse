<?php

global $connector, $glo_regions, $mimes_images_acceptes, $glo_tab_ailleurs, $glo_tab_quartiers2;
require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\EvenementCollection;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Utils;
use Ladecadanse\HtmlShrink;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\Lieu;
use Ladecadanse\Organisateur;

if (!$videur->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

$page_titre = "Gérer les événements";
$extra_css = ["formulaires", "admin/tables"];
require_once '../_header.inc.php';

$tab_listes = ["evenement" => "Événements", "lieu" => "Lieux", "description" => "Descriptions", "personne" => "Personnes"];

$get = [];

$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion'])) {
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}

$get['element'] = "evenement";

$get['page'] = 1;
if (isset($_GET['page']))
{
	$get['page'] = Validateur::validateUrlQueryValue($_GET['page'], "int", 1);
}

$th_evenements = ["titre" => "Titre", "idLieu" => "Lieu", "dateEvenement" => "Date", "genre" => "Catég.", "horaire" => "Horaire", "organisateurs" => "Orga.", "statut" => "Statut", "dateAjout" => "Ajouté", "pseudo" => "par"];

$orders = ["dateAjout", "date_derniere_modif", "statut", "dateEvenement", "titre", "genre"];

$get['tri_gerer'] = "dateAjout";
if (isset($_GET['tri_gerer']) && in_array($_GET['tri_gerer'], $orders))
{
	$get['tri_gerer'] = $_GET['tri_gerer'];
}

$tab_ordre = ["asc", "desc"];
$get['ordre'] = "desc";
$ordre_inverse = "asc";
if (isset($_GET['ordre']))
{
	$get['ordre'] = Validateur::validateUrlQueryValue($_GET['ordre'], "enum", 1, $tab_ordre);
	if ($get['ordre'] == "asc")
	{
		$ordre_inverse = "desc";
	}
	else if ($get['ordre'] == "desc")
	{
		$ordre_inverse = "asc";
	}
}

$get['nblignes'] = $tab_nblignes[0];
if (!empty($_GET['nblignes']))
{
	$get['nblignes'] = Validateur::validateUrlQueryValue($_GET['nblignes'], "int", 1);
}


$where = "";

if  ((!empty($_GET['filtre_genre']) && $_GET['filtre_genre'] != 'tous') || !empty($_GET['terme']) || !empty($_SESSION['region_admin']))
{
	$where = " WHERE ";
}

$query_params = [];

$get['terme'] = '';
if (!empty($_GET['terme']))
{
	$get['terme'] = $_GET['terme'];
	$where .= " ( LOWER(e.titre) LIKE LOWER(?)) ";
    $query_params[] = "%".$get['terme']. "%";
}

$get['filtre_genre'] = "tous";
if (isset($_GET['filtre_genre']) && $_GET['filtre_genre'] != 'tous')
{
	$get['filtre_genre'] = $_GET['filtre_genre'];

	if (!empty($_GET['terme']))
		$where .= " AND ";

	$where .= " e.genre=? ";
    $query_params[] = $_GET['filtre_genre'];
}

$verif = new Validateur();

$sql_region = '';
$titre_region = '';
if (!empty($_SESSION['region_admin']))
{
    if ((!empty($_GET['filtre_genre']) && $_GET['filtre_genre'] != 'tous') || !empty($_GET['terme']))
    {
        $where .= " AND ";
    }

    $where .=  " e.region=? ";
    $query_params[] = $_SESSION['region_admin'];
    $titre_region = " - ".$glo_regions[$_SESSION['region_admin']];
}
?>

<main id="contenu" class="colonne">

	<header id="entete_contenu">
		<h1>Gérer les événements <?= sanitizeForHtml($titre_region) ?></h1>
        <div class="spacer"></div>
	</header>

<?php
$evenements = [];

$champs = ["genre" => "", "idLieu" => "", "idSalle" => "", "nomLieu" => "", "adresse" => "", "quartier" => "",  "localite_id" => "", "region" => "", "urlLieu" => "", "titre" => "", "description" => "", "ref" => "", "horaire_debut" => "", "horaire_fin" => "", "horaire_complement" => "", "prix" => "", "prelocations" => "", "statut" => ""];

$fichiers = ['flyer' => '', 'image' => ''];

$action_terminee = false;

if (!empty($_POST['formulaire']) && empty($_POST['evenements']))
{
	$verif->setErreur('evenements', "Aucun événement sélectionné");
}
else if (!empty($_POST['formulaire']) && !empty($_POST['supprimerSerie']))
{

	$supprimerSerie = $_POST['supprimerSerie'];

	$evenements = $_POST['evenements'];

	$erreurs = [];

	$totalEv = count($evenements);
	for ($i = 0; $i < $totalEv; $i++)
	{
		if (!is_numeric($evenements[$i]))
			$erreurs['typeEvenement'] = "Un des ID d'événements choisi n'est pas un nombre";

	}

	if (count($erreurs) === 0)
	{
		foreach($evenements as $even)
        {
            EvenementCollection::deleteEvenement($even);
        }
	}

	unset($_POST);

}
elseif (!empty($_POST['formulaire']))
{

	foreach ($champs as $i => $v)
	{
        if (isset($_POST[$i]))
        {
            $champs[$i] = trim((string) $_POST[$i]);
        }
    }

	$evenements = $_POST['evenements'];


	$champs['organisateurs'] = [];
	if (isset($_POST['organisateurs']))
		$champs['organisateurs'] = $_POST['organisateurs'];

	$fichiers['flyer'] = $_FILES['flyer'];
	$fichiers['image'] = $_FILES['image'];

	/*
	 * VERIFICATION DES CHAMPS ENVOYES par POST
	 */
	$totalEv = count($evenements);
	foreach ($evenements as $idEv)
	{
		if (!is_numeric($idEv))
		{
			$verif->setErreur('evenements', "Un des ID d'événements choisi n'est pas un nombre");
		}
	}

	$verif->valider($champs['genre'], "genre", "texte", 1, 200, 0);
	if (!empty($champs['genre']) && !array_key_exists($champs['genre'], $glo_tab_genre))
	{
		$verif->setErreur("genres", "Cette catégorie n'est pas valable");
	}

	$verif->valider($champs['titre'], "titre", "texte", 1, 80, 0);

	$verif->valider($champs['nomLieu'], "nomLieu", "texte", 1, 80, 0);


	$verif->valider($champs['adresse'], "adresse", "texte", 2, 100, 0);
	if (empty($champs['lien']) && !empty($champs['nomLieu']) && empty($champs['adresse']))
	{
		$verif->setErreur("adresse", "L'adresse est obligatoire");
	}

	if (empty($champs['lien']) && !empty($champs['nomLieu']) && empty($champs['localite_id']))
	{
		$verif->setErreur("localite_id", "La localité est obligatoire");
	}

	if (!empty($champs['idLieu']) && ($champs['nomLieu'] != "" || $champs['adresse'] != ""))
    {
		$verif->setErreur('doublonLieux', 'Vous ne pouvez pas choisir 2 lieux');
	}

	if ($champs['idLieu'] != '' && preg_match("/^[0-9]+_[0-9]+$/", $champs['idLieu']))
	{

		$tab_idLieu = explode("_", $champs['idLieu']);
		$champs['idLieu'] = $tab_idLieu[0];
		$champs['idSalle'] = $tab_idLieu[1];
	}
	else
	{
		$champs['idSalle'] = 0;
	}

	$verif->valider($champs['description'], "description", "texte", 4, 10000, 0);

	$mimes_acceptes = ["image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png"];

    $verif->validerFichier($fichiers['flyer'], "flyer", $mimes_images_acceptes, 0);
	$verif->validerFichier($fichiers['image'], "image", $mimes_images_acceptes, 0);

	$verif->valider($champs['horaire_debut'], "horaire_debut", "texte", 1, 100, 0);
	if (!empty($champs['horaire_debut']) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", $champs['horaire_debut']))
	{
		$verif->setErreur('horaire_debut', "Mauvais format");
	}

	$verif->valider($champs['horaire_fin'], "horaire_fin", "texte", 1, 100, 0);
	if (!empty($champs['horaire_fin']) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", $champs['horaire_fin']))
	{
		$verif->setErreur('horaire_fin', "Mauvais format");
	}

	$verif->valider($champs['horaire_complement'], "horaire_complement", "texte", 1, 100, 0);
	$verif->valider($champs['prix'], "prix", "texte", 1, 100, 0);
	$verif->valider($champs['prelocations'], "prelocations", "texte", 1, 100, 0);


	if ($verif->nbErreurs() === 0)
	{

		//creation/nettoyage des valeurs à insérer dans la table
		$descriptionOrig = $champs['description'];
		if ($champs['prix'] == "0")
		{
			$champs['prix'] = "entrée libre";
		}

		if ($champs['urlLieu'] != "" && !preg_match("/^https?:\/\//", $champs['urlLieu']))
		{
			$champs['urlLieu'] = "http://".$champs['urlLieu'];
		}

		$lieu_modifie = false;


		// pour remplir les champs nomLieu, adresse, etc. de la table evenement
		if (!empty($champs['idLieu']))
		{
			$sql_lieu = "SELECT nom, adresse, quartier, localite_id, region, URL FROM lieu WHERE idLieu=".$connector->sanitize($champs['idLieu']);
			$req_lieu = $connector->query($sql_lieu);
			$tab_lieu = $connector->fetchArray($req_lieu);
			$champs['nomLieu'] = $tab_lieu['nom'];
			$champs['adresse'] = $tab_lieu['adresse'];
			$champs['quartier'] = $tab_lieu['quartier'];
			$champs['localite_id'] = $tab_lieu['localite_id'];
			$champs['region'] = $tab_lieu['region'];
			$champs['urlLieu'] = $tab_lieu['URL'];
                        $lieu_modifie = true;
		}
                elseif (!empty($champs['localite_id']))
                {
                    $loc_qua = explode("_", $champs['localite_id']);
                    if (count($loc_qua) > 1)
                    {
                        $champs['localite_id'] =  $loc_qua[0];
                        $champs['quartier'] = $loc_qua[1];
                        $champs['region'] = 'ge';
                    }
                    else
                    {
                        $champs['quartier'] = '';

                        if ($champs['localite_id'] == 'vd' || $champs['localite_id'] == 'rf' || $champs['localite_id'] == 'hs')
                        {
                            $champs['region'] = $champs['localite_id'];
                            $champs['localite_id'] = 1;
                        }
                        elseif ($champs['localite_id'] == 529 )
                        {
                            $champs['region'] = 'ge';


                        }
                        else
                        {
                            $sql_lieu = "SELECT canton FROM localite WHERE id=".$connector->sanitize($champs['localite_id']);
                            $req_lieu = $connector->query($sql_lieu);
                            $tab_lieu = $connector->fetchArray($req_lieu);
                            $champs['region'] = $tab_lieu['canton'];
                        }
                    }

                    $champs['idLieu'] = "0";
                    $lieu_modifie = true;
                }

		//dedoublonne
		if (count($champs['organisateurs']) > 0)
		{
			$champs['organisateurs'] = array_unique($champs['organisateurs']);
		}

		$compteur_evenements = 0;
        $srcFlyer = '';
        $src_image = '';
		foreach ($evenements as $idEven_courant)
		{
			$modifFlyerSQL = ""; // champ SQL pour le flyer

			$req_even = $connector->query("SELECT * FROM evenement WHERE idEvenement =".(int) $idEven_courant);

			$tab_even = $connector->fetchArray($req_even);

			$nouv_genre = $tab_even['genre'];

			$champs['horaire_debut'] = $_POST['horaire_debut'];

			/*  Adaptation pour horaire_debut */
			$lendemain_evenement = date_lendemain($tab_even['dateEvenement']);

			if (!empty($champs['horaire_debut']))
			{
				$tab_horaire_debut = explode(":", (string) $champs['horaire_debut']);

				$sec_horaire_debut = (int) $tab_horaire_debut[0] * 3600 + (int) $tab_horaire_debut[1] * 60;
                //echo "sec_H:".$sec_horaire_debut;

				if ($sec_horaire_debut >= 0 && $sec_horaire_debut <= 21600)
				{
					$champs['horaire_debut'] = $lendemain_evenement." ".$champs['horaire_debut'];
				}
				else
				{
					$champs['horaire_debut'] = $tab_even['dateEvenement']." ".$champs['horaire_debut'];
				}
			}

			$champs['horaire_fin'] = $_POST['horaire_fin'];

			if (!empty($champs['horaire_fin']))
			{
				$tab_horaire_fin = explode(":", (string) $champs['horaire_fin']);

				$sec_horaire_fin = (int) $tab_horaire_fin[0] * 3600 + (int) $tab_horaire_fin[1] * 60;
                //echo "sec_H:".$sec_horaire_fin;

				if ($sec_horaire_fin >= 0 && $sec_horaire_fin <= 21600)
				{
					$champs['horaire_fin'] = $lendemain_evenement." ".$champs['horaire_fin'];
				}
				else
				{
					$champs['horaire_fin'] = $tab_even['dateEvenement']." ".$champs['horaire_fin'];
				}
			}

			if (!empty($erreursEv))
			{
				HtmlShrink::msgErreur($erreursEv);
				continue;
			}

			if (!empty($fichiers['flyer']['name']))
			{
				$champs['flyer'] = $idEven_courant . "_" . $tab_even['dateEvenement'] . strrchr((string) $fichiers['flyer']['name'], '.');
            }

			if (!empty($fichiers['image']['name']))
			{
				$champs['image'] = $idEven_courant . "_" . $tab_even['dateEvenement'] . "_img" . strrchr((string) $fichiers['image']['name'], '.');
            }


			$sql_flyer = ""; // champ SQL pour le flyer

			//si un nouveau flyer a été uploadé
			if (!empty($champs['flyer']))
			{

				$sql_flyer = ", flyer='".$champs['flyer']."'";
				$req_flyer = $connector->query("SELECT flyer FROM evenement WHERE idEvenement=".(int)$idEven_courant);

				if ($req_flyer)
				{
					$affFly = $connector->fetchArray($req_flyer);

					//si  un ancien flyer a été effectivement trouvé suppression des fichiers
					if (!empty($affFly['flyer']))
					{
                        Evenement::rmImageAndItsMiniature($affFly['flyer']);
					}
				}
                //si le champ "supprimer le flyer" est coché sans qu'un nouveau flyer soit remplacant
			}

			if (!empty($supprimer['flyer']))
			{
				$sql_flyer = ", flyer=''";
				$req_flyer = $connector->query("SELECT flyer FROM evenement WHERE idEvenement=".(int)$idEven_courant);

				//si  un ancien flyer a été effectivement trouvé suppression des fichiers
				if ($req_flyer)
				{
					$affFly = $connector->fetchArray($req_flyer);

					if (!empty($affFly['flyer']))
					{
                        Evenement::rmImageAndItsMiniature($affFly['flyer']);
					}
				}
			} //elseif supprimer flyer

			$sql_image = ""; // champ SQL pour l'image

			//si un nouveau flyer
			if (!empty($champs['image']))
			{
				$sql_image = ", image='".$champs['image']."'";
				$req_image = $connector->query("SELECT image FROM evenement WHERE idEvenement=".(int)$idEven_courant);

				if ($req_image)
				{
					$affImg = $connector->fetchArray($req_image);

					//si  un ancien flyer a êµ© effectivement trouvé¡³uppression des fichiers
					if (!empty($affImg['image']))
					{
                        Evenement::rmImageAndItsMiniature($affImg['image']);
					}
				}
    			//si le champ "supprimer le flyer" est coché¡³ans qu'un nouveau flyer soit remplacant
			}

			if (!empty($supprimer['image']))
			{
				$sql_image = ", image=''";
				$req_image = $connector->query("SELECT image FROM evenement WHERE idEvenement=".(int)$idEven_courant);

				//si  un ancien flyer a êµ© effectivement trouvé¡³uppression des fichiers
				if ($req_image)
				{
					$affimage= $connector->fetchArray($req_image);

					if (!empty($affimage['image']))
					{
                        Evenement::rmImageAndItsMiniature($affImg['image']);
					}
				}
			} //if supprimer image

			$sql_update = "UPDATE evenement SET ";

			foreach ($champs as $i => $v)
			{
                if ((!empty($v) && $i != "idPersonne" && $i != "organisateurs") || (($i == "idLieu" || $i == "urlLieu" || $i == "quartier" || $i == "localite_id" || $i == "region") && $lieu_modifie == true )
                )
                {
                        $sql_update .= $i."='".$connector->sanitize($v)."', ";
                }
			}

			$sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
			$sql_update .= $sql_flyer.$sql_image."
			WHERE idEvenement=".(int) $idEven_courant;

			$req_update = $connector->query($sql_update);

			/*
			* MAJ réussie, message OK, et RAZ de l'action
			*/
			if ($req_update)
			{
				HtmlShrink::msgOk('Mise à jour de <a href="/event/evenement.php?idE='.(int)$idEven_courant.'">'.$tab_even['titre'].'</a> le <a href="/index.php?courant='.$tab_even['dateEvenement'].'">'.date_fr($tab_even['dateEvenement'], "annee").'</a> réussie');

				$sql = "DELETE FROM evenement_organisateur WHERE idEvenement=".(int) $idEven_courant;
				$req = $connector->query($sql);

				$action_terminee = true;
            }

			/*
			* TRAITEMENT DE L'IMAGE UPLOADEE
			*/
			if (!empty($fichiers['flyer']['name']) && $compteur_evenements == 0)
			{
				$imD2 = new ImageDriver2("evenement");
				$erreur_image = [];
				$erreur_image[] = $imD2->processImage($_FILES['flyer'], $champs['flyer'], 400, 400);
				$erreur_image[] = $imD2->processImage($_FILES['flyer'], "s_" . $champs['flyer'], 120, 190, '', 1);

				$srcFlyer = $champs['flyer'];
			}
			elseif (!empty($fichiers['flyer']['name']))
			{
                copy(Evenement::getSystemFilePath(Evenement::getFilePath($srcFlyer)), Evenement::getSystemFilePath(Evenement::getFilePath($champs['flyer'])));
                copy(Evenement::getSystemFilePath(Evenement::getFilePath($srcFlyer, "s_")), Evenement::getSystemFilePath(Evenement::getFilePath($champs['flyer'], "s_")));
            }

            if (!empty($fichiers['image']['name']) && $compteur_evenements == 0)
			{

				$imD2 = new ImageDriver2("evenement");
				$erreur_image = [];
				$erreur_image[] = $imD2->processImage($_FILES['image'], $champs['image'], 400, 400);
				$erreur_image[] = $imD2->processImage($_FILES['image'], "s_" . $champs['image'], 120, 190, '', 1);

				$src_image = $champs['image'];
			}
			elseif (!empty($fichiers['image']['name']))
			{
                copy(Evenement::getSystemFilePath(Evenement::getFilePath($src_image)), Evenement::getSystemFilePath(Evenement::getFilePath($champs['image'])));
                copy(Evenement::getSystemFilePath(Evenement::getFilePath($src_image, "s_")), Evenement::getSystemFilePath(Evenement::getFilePath($champs['image'], "s_")));
            }

            foreach ($champs['organisateurs'] as $no => $idOrg)
            {
                if (!empty($idOrg))
                {
                    $sql = "INSERT INTO evenement_organisateur (idEvenement, idOrganisateur) VALUES (" . (int) $idEven_courant . ", " . (int) $idOrg . ")";
                    $connector->query($sql);
                }
            }

			$compteur_evenements++;
		} // foreach

		unset($_POST);
		unset($_FILES);
		foreach ($champs as $i => $v)
		{
			$champs[$i] = '';
		}
	}
}


/*
 * AFFICHAGE DE LA TABLE ET SON MENU DE NAVIGATION
 */
$sql_nbeven = "SELECT COUNT(*) AS nbeven FROM evenement e ".$where;

$stmt = $connectorPdo->prepare($sql_nbeven);
$stmt->execute($query_params);
$tab_nbeven = $stmt->fetchAll(PDO::FETCH_ASSOC);

//dump($tab_nbeven);
//$req_nbeven = $connector->query($sql_nbeven);
//$tab_nbeven = $connector->fetchArray($req_nbeven);
$tot_elements = $tab_nbeven[0]['nbeven'];

$total_page_max = ceil($tot_elements / $get['nblignes']);
if ($get['page'] > $total_page_max)
	$get['page'] = $total_page_max;

$sql_page = $get['page'];
if ($get['page'] < 1)
    $sql_page = 1;

$sql_evenement = "
SELECT

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
  l.adresse AS l_adresse,
  l.quartier AS l_quartier,
  l.URL AS l_URL,
  lloc.localite AS lloc_localite,
  l.region AS l_region,
  s.nom AS s_nom,

  p.idPersonne AS idPersonne,
  p.pseudo AS pseudo

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle
LEFT JOIN personne p ON e.idPersonne = p.idPersonne
 ".$where."
ORDER BY e.".$get['tri_gerer']." ".$get['ordre']." LIMIT ".(int)($sql_page - 1) * (int)$get['nblignes'].",".(int)$get['nblignes'];

$stmt = $connectorPdo->prepare($sql_evenement);
$stmt->execute($query_params);
$tab_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
//  dump($tab_events);

// from all events ids build an array of their organizers
$events_ids = array_column($tab_events, 'e_idEvenement');
$events_orgas = [];
if (!empty($events_ids))
{
    list($eventsIdsInClause, $eventsIdsParams) = $connectorPdo->buildInClause('eo.idEvenement', $events_ids);

    $stmt = $connectorPdo->prepare("SELECT

    eo.idEvenement AS idEvenement,
    o.idOrganisateur AS o_idOrganisateur,
    o.nom AS o_nom,
    o.URL AS o_URL

    FROM evenement_organisateur eo
    JOIN organisateur o ON eo.idOrganisateur = o.idOrganisateur AND $eventsIdsInClause
    ORDER BY nom DESC");

    $stmt->execute($eventsIdsParams);

    $tab_orgas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tab_orgas AS $eo)
    {
        $events_orgas[$eo['idEvenement']][] = [
            'idOrganisateur' => $eo['o_idOrganisateur'],
            'nom' => $eo['o_nom'],
            'url' => $eo['o_URL']
        ];
    }
}
?>


<?php
if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
}
?>

<section id="default">

    <div>

        <form method="get" action="" id="ajouter_editer" style="float:left;width:35%;margin:0;">

            <input type="hidden" name="filtre_genre" value="<?= sanitizeForHtml($get['filtre_genre']); ?>" />
            <input type="hidden" name="nblignes" value="<?= (int)$get['nblignes']; ?>" />
            <input type="hidden" name="tri_gerer" value="<?= sanitizeForHtml($get['tri_gerer']); ?>" />
            <input type="hidden" name="element" value="<?= sanitizeForHtml($get['element']); ?>" />
            <input type="hidden" name="ordre" value="<?= sanitizeForHtml($get['ordre']); ?>" />

            <input type="search" name="terme" value="<?= sanitizeForHtml($get['terme']); ?>" placeholder="Titre" size="20" />
            <input type="submit" name="submit" value="Filtrer" />

        </form>

        <ul class="menu_filtre" style="float:left;width:50%;margin:0">
            <li <?php if ($get['filtre_genre'] == 'tous') : ?>class="ici"<?php endif; ?>>
                <a href="?<?= Utils::urlQueryArrayToString($get, ['filtre_genre', 'page'])?>&amp;filtre_genre=tous">Tous</a></li>
            <?php foreach ($glo_tab_genre as $ng => $nl) : ?>
                <li <?php if ($get['filtre_genre'] == $ng) : ?> class="ici"<?php endif; ?>>
                    <a href="?<?= Utils::urlQueryArrayToString($get, ['filtre_genre', 'page']) ?>&amp;filtre_genre=<?= $ng ?>"><?= ucfirst($nl) ?></a>
                </li>
              <?php endforeach; ?>
        </ul>

        <div class="spacer"></div>


        <div id="gerer-even-pagination">
            <?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&filtre_genre=" . $get['filtre_genre'] . "&terme=" . $get['terme'] . "&page=") ?>

                <ul class="menu_nb_res" style="float:right;margin: 1em auto 1.4em;width:35%;text-align:right">
                <?php foreach ($tab_nblignes as $nbl) : ?>
                <li <?php if ($get['nblignes'] == $nbl) { echo 'class="ici"'; } ?>>
                    <a href="?<?= Utils::urlQueryArrayToString($get, "nblignes")?>&amp;nblignes=<?= (int)$nbl ?>"><?= (int)$nbl ?></a>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="spacer"></div>
        </div>

        <div class="spacer"></div>

    </div>

    <form method="post" id="formGererEvenements" class='js-submit-freeze-wait' enctype="multipart/form-data" action="">

        <table id="ajouts" class="jquery-checkboxes">

            <tr>
                <?php foreach ($th_evenements as $field => $label) : ?>
                <th <?php if ($field == $get['tri_gerer']) : ?>class="ici"<?php endif; ?> <?php if ($field == 'horaire') : ?>style="width:100px"<?php endif; ?>>
                    <?php if (in_array($field, $orders)) : ?>
                        <a href="?<?= Utils::urlQueryArrayToString($get, ['tri_gerer', 'ordre'])."&amp;tri_gerer=".$field."&amp;ordre=".$ordre_inverse ?>"><?= sanitizeForHtml($label) ?></a>
                        <?php if ($field == $get['tri_gerer']) : echo $icone[$get['ordre']]; endif; ?>
                    <?php else : ?>
                        <?= sanitizeForHtml($label) ?>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>

                <th colspan=2></th>
            </tr>

            <?php foreach ($tab_events as $tab_even) :
                $even_lieu = Evenement::getLieu($tab_even);
                $datetime_dateajout = date_iso2app($tab_even['e_dateAjout']);
                $tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
                ?>
            <tr>
                <td><a href="/event/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" class='titre'><?= sanitizeForHtml($tab_even['e_titre']) ?></a></td>
                <td><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?><br><span style="color:lightsteelblue"><?= $even_lieu['localite'] ?></span></td>
                <td><a href="/index.php?courant=<?= sanitizeForHtml($tab_even['e_dateEvenement']) ?>"><?= date_iso2app($tab_even['e_dateEvenement']) ?></a></td>
                <td><?= ucfirst((string) $glo_tab_genre[$tab_even['e_genre']]) ?></td>
                <td>
                    <?= afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']) ?>
                    <?php
                    if (!empty($tab_even['e_horaire_complement']))
                    {
                        echo "<br><i>".sanitizeForHtml(substr($tab_even['e_horaire_complement'], 0, 20))."</i>";
                    }
                    ?>
                </td>
                <td>
                    <?php if (!empty($events_orgas[$tab_even['e_idEvenement']])): ?>
                        <?= Organisateur::getListLinkedHtml($events_orgas[$tab_even['e_idEvenement']], isWithOrganisateurUrl: false) ?>
                    <?php endif; ?>
                </td>
                <td><?= EvenementRenderer::$iconStatus[$tab_even['e_statut']] ?></td>
                <td><?= $tab_datetime_dateajout[1]." ".substr($tab_datetime_dateajout[0], 0, -3) ?></td>
                <td><a href="/user.php?idP=<?= (int)$tab_even['idPersonne'] ?>"><?= sanitizeForHtml($tab_even['pseudo']) ?></a></td>
                <?php if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) : ?>
                    <td style="text-align:center"><a href="/evenement-edit.php?action=editer&idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= $iconeEditer ?></a></td>
                <?php endif; ?>
                <td style="text-align:center"><input type="checkbox" name="evenements[]" value="<?= (int) $tab_even['e_idEvenement'] ?>" /></td>
            </tr>

            <?php endforeach; ?>

        </table>

        <?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&filtre_genre=" . $get['filtre_genre'] . "&terme=" . $get['terme'] . "&page=") ?>

        <?= $verif->getErreur("evenements") ?>

        <div style="margin: 0 auto;width: 94%;">
            <h2 style="font-size:1.3em;margin:10px 0;">Remplacer les données des événements sélectionnés ci-dessus par :</h2>
            <p><span style="background:yellow">Attention :</span><b>toutes</b> les données existantes seront écrasées</p>
            <p>Seuls les champs non vides écrasent les champs existants</p>
        </div>
        <!--
        <p class="piedForm">
        <input type="submit" value="Remplacer" tabindex="19" class="submit" />
        </p>
        -->
        <div id="ajouter_editer">
            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok" />
                <input type="submit" value="Remplacer" tabindex="19" class="submit" />
            </p>

        <!-- DEB STATUT -->
        <fieldset>
            <legend>Statut</legend>
            <!--
            <ul class="radio">
            <?php
            foreach (Evenement::$statuts_evenement as $s => $v)
            {
                $coche = '';
                if (strcmp((string) $s, $champs['statut']) == 0)
                {
                    $coche = 'checked="1"';
                }
                echo '<li class="listehoriz"><input type="radio" name="statut" value="'.sanitizeForHtml($s).'" '.$coche.' id="genre_'.sanitizeForHtml($s).'" class="radio_horiz" /><label class="continu" for="genre_'.sanitizeForHtml($s).'">'.sanitizeForHtml($v).'</label></li>';
            }
            ?>
            </ul>
            -->
            <ul class="radio">
                <?php

                $statuts = ['actif' => '<strong>publié</strong> (visible sur le site)',  'complet' => '<strong>complet</strong> (visible sur le site mais marqué comme étant complet)', 'annule' => '<strong>annulé</strong> (visible sur le site mais marqué comme étant annulé)', 'inactif' => '<strong>dépublié</strong> (non visible sur le site)'];
                foreach ($statuts as $s => $n)
                {
                    $coche = '';
                    if (strcmp($s, $champs['statut']) == 0)
                    {
                        $coche = 'checked="checked"';
                    }
                    echo '<li style="display:block">
                    <input type="radio" name="statut" value="'.$s.'" '.$coche.' id="statut_'.$s.'" title="statut de l\'événement" class="radio_horiz" />
                    <label class="continu" for="statut_'.$s.'">'.$n.'</label></li>';
                }
                ?>
            </ul>

            <?php
            echo $verif->getErreur("statut");
            ?>

            <p><input type="checkbox" name="supprimerSerie" value="ok" /><label><strong>Supprimer</strong></label></p>

        </fieldset>

        <fieldset>
            <legend>Catégorie</legend>
            <ul class="radio">
            <?php
            foreach ($glo_tab_genre as $na => $la)
            {
                $coche = '';
                if ($na == $get['filtre_genre'])
                {
                    $coche = 'checked="1"';
                }
                echo '<li class="horiz">
                <input type="radio" name="genre" value="'.$na.'" '.$coche.' id="genre_'.$na.'" title="" class="radio_horiz" />
                <label class="continu" for="genre_'.$na.'">'.sanitizeForHtml($la).'</label></li>';
            }
            ?>
            </ul>

            <?php
            echo $verif->getErreur("genre");
            ?>
        </fieldset>

    <fieldset>
    <legend>Lieu*</legend>
    <p>
    <label for="lieu">Dans la liste :</label>

    <select name="idLieu" id="idLieu" class="js-select2-options-with-style" data-placeholder=""  style="max-width:350px">
        <?php
    //Menu des lieux actifs de la base
    echo "<option value=\"\">&nbsp;</option>";
    $req_lieux = $connector->query("
    SELECT idLieu, nom FROM lieu
    WHERE statut='actif'
    ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom)))))))
    COLLATE utf8mb4_unicode_ci"
     );


    /* while ($lieuTrouve = $connector->fetchArray($req_lieux))
    {
        echo "<option ";
        echo "value=\"".$lieuTrouve['idLieu']."\">".$lieuTrouve['nom']."</option>";

        $sql_salle = "select * from salle where idLieu=".$lieuTrouve['idLieu'];
        $req_salle = $connector->query($sql_salle);
        while ($tab_salle = $connector->fetchArray($req_salle))

        {
            echo "<option ";
            echo " style=\"font-style:italic;padding-left:1em;\" value=".$lieuTrouve['idLieu']."_".$tab_salle['idSalle'].">".$tab_salle['nom']."</option>";

        }
    } */

    while ($lieuTrouve = $connector->fetchArray($req_lieux))
    {

        echo "<option ";

        $nom_lieu = $lieuTrouve['nom'];
        if (preg_match("/^(Le |La |Les |L')(.*)/", (string) $lieuTrouve['nom'], $matches))
        {
            $nom_lieu = $matches[2].', '.$matches[1];

        }

        if ($lieuTrouve['idLieu'] == $champs['idLieu'])
        {
            echo "selected=\"selected\" ";
        }

        echo "value=\"" . (int)$lieuTrouve['idLieu'] . "\">" . sanitizeForHtml($nom_lieu) . "</option>";

        $sql_salle = "select * from salle where idLieu=".(int)$lieuTrouve['idLieu']. " AND salle.status='actif' ";
        $req_salle = $connector->query($sql_salle);
        while ($tab_salle = $connector->fetchArray($req_salle))

        {
            echo "<option ";
            if ($champs['idSalle'] != 0 && $tab_salle['idSalle'] == $champs['idSalle'])
            {
                echo "selected=\"selected\" ";
            }
            echo " style=\"font-style:italic;color:#444;\" value=".(int)$lieuTrouve['idLieu']."_".(int)$tab_salle['idSalle'].">".sanitizeForHtml($nom_lieu)."&nbsp;– ".sanitizeForHtml($tab_salle['nom'])."</option>";

        }


    }
    ?>
    ?>
    </select>
    <?php
    echo $verif->getErreur("idLieu");
    echo $verif->getErreur("dejaPresent");
    ?>
    </p>

    <p class="entreLabels"><strong>sinon</strong></p>
    <div class="spacer"></div>

    <p>
    <?php
    $tab_nomLieu_label = ["for" => "nomLieu"];
    echo HtmlShrink::formLabel($tab_nomLieu_label, "Nom du lieu :");
    echo $verif->getErreur("nomLieuIdentique");

    $tab_nomLieu = ["type" => "text", "name" => "nomLieu", "id" => "nomLieu", "size" => "40", "maxlength" => "80", "tabindex" => "9", "value" => ""];
        if (empty($champs['idLieu']))
    {
        $tab_nomLieu['value'] = sanitizeForHtml($champs['nomLieu']);
    }
    echo HtmlShrink::formInput($tab_nomLieu);
    echo $verif->getErreur("nomLieu");
    ?>
    </p>

    <p>
    <label for="adresse">Adresse</label>
    <?php
    echo $verif->getErreur("adresseIdentique");
    ?>

    <input type="text" name="adresse" id="adresse" size="60" maxlength="100" title="rue, no" tabindex="10" value="
           <?php if (empty($champs['idLieu']))
           {
               echo sanitizeForHtml($champs['adresse']);
           } ?>" />
    <?php
    echo $verif->getHtmlErreur("adresse");
    echo $verif->getErreur("doublonLieux");
    ?>
    </p>


    <p>
    <label for="localite">Localité/quartier</label>
    <select name="localite_id" id="localite" class="js-select2-options-with-style" data-placeholder="" style="max-width:300px;">
        <?php
    echo "<option value=\"\">&nbsp;</option>";
    $req = $connector->query("
    SELECT id, localite, canton FROM localite WHERE id!=1 ORDER BY canton, localite "
     );



    $select_canton = '';
    while ($tab = $connector->fetchArray($req))
    {

        if ($tab['canton'] != $select_canton)
        {
            if (!empty($select_canton))
                echo "</optgroup>";

            echo "<optgroup label=''>"; // ".$glo_regions[strtolower($tab['canton'])]."
        }

        echo "<option ";

        if (empty($champs['idLieu']) && ($champs['localite_id'] == $tab['id'] && empty($champs['quartier'])) || ((isset($_POST['localite_id']) && $tab['id'] == $_POST['localite_id'])))
        {
            echo 'selected="selected" ';
        }

        echo "value=\"".(int)$tab['id']."\">".sanitizeForHtml($tab['localite'])."</option>";

        // Genève quartiers
        if ($tab['id'] == 44)
        {

            // si erreur formulaire
            $champs_quartier = '';
            $loc_qua = explode("_", (string) $champs['localite_id']);
            if (!empty($loc_qua[1]))
               $champs_quartier = $loc_qua[1];

            // si chargement even existant
            if (!empty($champs['quartier']))
                $champs_quartier = $champs['quartier'];

            foreach ($glo_tab_quartiers2['ge'] as $no => $quartier)
            {
                   echo "<option ";

                   if (empty($champs['idLieu']) && $champs_quartier == $quartier)
                   {
                           echo 'selected="selected" ';
                   }

                   echo " value=\"44-".$quartier."\">Genève - ".$quartier."</option>";
            }
        }

         $select_canton = $tab['canton'];
    }
    ?>
        <optgroup label="Ailleurs">

        <?php
        foreach ($glo_tab_ailleurs as $id => $nom)
       {
               echo "<option ";

               if (empty($champs['idLieu']) && (($champs['region'] == $id) || ((isset($_POST['localite_id']) && $id == $_POST['localite_id'])))
                      ) // $form->getValeur('quartier')
               {
                       echo ' selected="selected" ';
               }

               echo " value=\"" . (int)$id . "\">" . sanitizeForHtml($nom) . "</option>";
        }
        ?>



        </optgroup>


    </select>
    <?php
    echo $verif->getHtmlErreur("localite_id");

    ?>
    </p>



    <p>
    <label for="urlLieu">URL</label>
    <input type="text" name="urlLieu" id="urlLieu" size="60" maxlength="80" title="url du lieu" tabindex="9" value="
           <?php if (empty($champs['idLieu']))
           {
               echo sanitizeForHtml($champs['urlLieu']);
           } ?>" />
    <?php
    echo $verif->getErreur("urlLieu");
    ?>
    </p>

    </fieldset>
    <!-- FIN LIEU -->




    <!-- DEB EVENEMENT -->
    <fieldset>
    <legend>L'événement</legend>

    <p>
    <label for="titre">Titre</label>
    <input type="text" name="titre" id="titre" size="60" maxlength="80" title="titre de l'événement" tabindex="11" value="<?php echo sanitizeForHtml($champs['titre']) ?>" />
    <?php
    echo $verif->getErreur("titre");
    ?>
    </p>
    <!-- DESCRIPTION -->

    <p>
        <label for="description">Description </label>
        <textarea name="description" id="description" cols="50" rows="16" title="description de l'événement" tabindex="13">
        <?php echo sanitizeForHtml($champs['description']) ?></textarea>

        <?php
        echo $verif->getHtmlErreur('description');
        ?>
    </p>

    <p>
        <label for="ref">Références</label>
        <input type="text" name="ref" id="ref" size="60" maxlength="100" title="Organisateur, site web de l'Ã©vÃ©nement, contact..." tabindex="14" value="
        <?php echo sanitizeForHtml($champs['ref']); ?>" />
    </p>
    <div class="guideChamp">Indiquez ici les sites web de l'événement ou des organisateurs.</div>

    <p>
        <label for="organisateurs">Organisateur(s)</label>
        <select name="organisateurs[]" id="organisateurs" class="js-select2-options-with-complement" multiple data-placeholder="Choisissez un ou plusieurs organisateurs" style="max-width:400px;">
        <?php

        /*
         * Si l'ajout d'événement se fait depuis une page 'lieu', le formulaire est
         * pré-complété pour l'horaire et le prix
         */

            //Menu des lieux actifs de la base
            echo "<option value=\"0\">&nbsp;</option>";
            $req = $connector->query("
            SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci"
             );


            while ($tab = $connector->fetchArray($req))
            {

                echo "<option ";

                echo "value=\"" . (int)$tab['idOrganisateur'] . "\">" . sanitizeForHtml($tab['nom']) . "</option>";
        }
        ?>
    </select>

    </p>


    </fieldset>
    <!-- FIN EVENEMENT -->

    <div class="spacer"></div>


    <!-- DEB HORAIRE -->
    <fieldset>
    <legend>Horaire*</legend>
    <p>
        <label for="horaire_debut">Début :</label>
        <input type="text" name="horaire_debut" id="horaire_debut" size="6" maxlength="100" title="début" tabindex="16" value="<?php echo sanitizeForHtml($champs['horaire_debut']) ?>"  placeholder="hh:mm" />
        <?php
        echo $verif->getHtmlErreur('horaire_debut');
        ?>
        <label for="horaire_fin" class="continu">Fin :</label>
        <input type="text" name="horaire_fin" id="horaire_fin" size="6" maxlength="100" title="fin" tabindex="16" value="<?php echo sanitizeForHtml($champs['horaire_fin']) ?>" placeholder="hh:mm" />
        <?php
        echo $verif->getHtmlErreur('horaire_fin');
        ?>
    </p>

    <p>
        <label for="horaire_complement">Complément :</label>
        <input type="text" name="horaire_complement" id="horaire_complement" size="60" maxlength="100" title="PrÃ©cisions" tabindex="17" value="<?php echo sanitizeForHtml($champs['horaire_complement']) ?>" />
        <?php
        echo $verif->getHtmlErreur('horaire_complement');
        ?>
    </p>
    <div class="guideChamp">hh:mm (jusqu'à 06:00, le début sera considéré faisant partie du jour de l'événement)</div>

    </fieldset>
    <!-- FIN HORAIRE -->

    <!-- DEB HORAIRE -->
    <fieldset>
        <legend>Entrée</legend>
        <p>
            <label for="prix">Prix :</label>
            <input type="text" name="prix" id="prix" size="60" title="Tarifs d'entrÃ©e" tabindex="17" value="<?php echo sanitizeForHtml($champs['prix']) ?>" />
            <?php
            echo $verif->getHtmlErreur('prix');
            ?>
            <div class="guideChamp">Vous pouvez mettre <b>0</b> si l'entrée est libre.</div>
        </p>
        <p>
            <label for="prelocations" class="continu">Prélocs :</label>
            <input type="text" name="prelocations" id="prelocations" size="60" maxlength="100" title="OÃ¹ acheter les billets" tabindex="18" value="<?php echo sanitizeForHtml($champs['prelocations']) ?>" />

            <?php
            echo $verif->getHtmlErreur('prelocations');
            ?>
        </p>
    </fieldset>
    <!-- FIN HORAIRE -->

    <fieldset>
    <legend>Fichiers</legend>

    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo UPLOAD_MAX_FILESIZE ?>" /> <!-- 2 Mo -->

    <p>
    <label for="flyer">Flyer :</label>
    <input type="file" name="flyer" id="flyer" class="js-file-upload-size-max" size="25"
    accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif" tabindex="12" class="fichier" />
    </p>

    <div class="spacer"></div>
    <?php
    echo $verif->getErreur("flyer");


    //affichage du flyer  et du bouton pour supprimer
    if (isset($get_idE) && !empty($champs['flyer']) && !$verif->getErreur($champs['flyer']))
    {
        echo '<div class="supImg">';
        $iconeImage = '<img src="' . Evenement::getWebPath(Evenement::getFilePath($champs['flyer'], "s_")) . ' " alt="Flyer" />';
        ?>

        <div><label for="sup_flyer" class="continu">Supprimer</label>
        <input type="checkbox" name="sup_flyer" id="sup_flyer" value="flyer" class="checkbox" ";

        <?php
        if (!empty($supprimer['flyer']) && $verif->nbErreurs() > 0)
        {
            echo "checked ";
        }
        echo "/></div></div>";
    }
    ?>

        <p>
        <label for="image">Image :</label>
        <input type="file" name="image" id="flyer" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif" class="fichier" />
        </p>
        <div class="guideChamp">Seul les formats JPEG, PNG et GIF sont acceptés.</div>
    <div class="spacer"></div>
    <?php
    echo $verif->getErreur("image");


    //affichage du flyer, et du bouton pour supprimer
    if (isset($get_idE) && !empty($champs['image']) && !$verif->getErreur('image'))
    {
        $iconeImage = "<img src=\"" . Evenement::getWebPath(Evenement::getFilePath($champs['image'], "s_")) . "\"  alt=\"Photo\" />";

        echo "<div><label for=\"sup_image\" class=\"continu\">Supprimer</label><input type=\"checkbox\" name=\"sup_image\" id=\"sup_image\" value=\"image\" class=\"checkbox\" ";

        if (!empty($supprimer['image']) && $verif->nbErreurs() == 0)
        {
            echo "checked ";
        }
        echo "/></div></div>";
    }
    ?>
    </fieldset>




        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="submit" value="Remplacer" tabindex="19" class="submit" />
        </p>
    </div>
    </form>

    </section>

</main>
<!-- Fin contenu -->

<div id="colonne_gauche" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
