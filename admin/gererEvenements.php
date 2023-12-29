<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\EvenementCollection;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Utils;
use Ladecadanse\HtmlShrink;

if (!$videur->checkGroup(4))
{
	header("Location: /user-login.php"); die();
}


$page_titre = "gérer les événements";
$extra_css = array("formulaires", "gerer", "chosen.min");
require_once '../_header.inc.php';

$tab_listes = array("evenement" => "Événements", "lieu" => "Lieux", "description" => "Descriptions", "personne" => "Personnes");
$tab_nblignes = [100, 250, 500];

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

$get['tri_gerer'] = "dateAjout";
if (isset($_GET['tri_gerer']))
{
	$get['tri_gerer'] = Validateur::validateUrlQueryValue($_GET['tri_gerer'], "enum", 1, ["dateAjout", "date_derniere_modif", "statut", "date_debut","id", "titre", "genre"]);
}

$tab_ordre = array("asc", "desc");
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

$get['nblignes'] = 100;
if (!empty($_GET['nblignes']))
{
	$get['nblignes'] = Validateur::validateUrlQueryValue($_GET['nblignes'], "int", 1);
}


$where = "";

if  ((!empty($_GET['filtre_genre']) && $_GET['filtre_genre'] != 'tous') || !empty($_GET['terme']) || !empty($_SESSION['region_admin']))
{
	$where = " WHERE ";
}

$get['terme'] = '';
if (!empty($_GET['terme']))
{
	$get['terme'] = $_GET['terme'];
	$where .= " ( LOWER(titre) like LOWER('%".$connector->sanitize($get['terme'])."%')) ";
}


$get['filtre_genre'] = "tous";
if (isset($_GET['filtre_genre']) && $_GET['filtre_genre'] != 'tous')
{
	$get['filtre_genre'] = $_GET['filtre_genre'];


	if (!empty($_GET['terme']))
		$where .= " AND ";

	$where .= " genre='".$connector->sanitize($_GET['filtre_genre'])."' ";
}

$verif = new Validateur();

$sql_region = '';
$titre_region = '';
if (!empty($_SESSION['region_admin']))
{
    	if ((!empty($_GET['filtre_genre']) && $_GET['filtre_genre'] != 'tous') || !empty($_GET['terme']))
		$where .= " AND ";

        $where .=  " region='".$connector->sanitize($_SESSION['region_admin'])."' ";

        $titre_region = " - ".$glo_regions[$_SESSION['region_admin']];
}
?>



<!-- Deb Contenu -->
<div id="contenu" class="colonne">

	<div id="entete_contenu">
		<h2>Gérer les événements <?php echo $titre_region ?></h2>
        <div class="spacer"></div>
	</div>

<?php
$evenements = array();

$champs = array("genre" => "", "idLieu" => "", "idSalle" => "", "nomLieu" => "", "adresse" => "", "quartier" => "",  "localite_id" => "", "region" => "", "urlLieu" => "", "titre" => "", "description" => "", "ref" => "", "horaire_debut" => "", "horaire_fin" => "", "horaire_complement" => "", "prix" => "", "prelocations" => "", "statut" => "");

$fichiers = array('flyer' => '', 'image' => '');

$action_terminee = false;

if (!empty($_POST['formulaire']) && empty($_POST['evenements']))
{
	$verif->setErreur('evenements', "Aucun événement sélectionné");
}
else if (!empty($_POST['formulaire']) && !empty($_POST['supprimerSerie']))
{

	$supprimerSerie = $_POST['supprimerSerie'];

	$evenements = $_POST['evenements'];

	$erreurs = array();

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

	foreach ($champs as $c => $v)
	{
		$champs[$c] = trim($_POST[$c]);
	}

	$evenements = $_POST['evenements'];


	$champs['organisateurs'] = array();
	if (isset($_POST['organisateurs']))
		$champs['organisateurs'] = $_POST['organisateurs'];

	//TEST
	//printr($_FILES);
	//
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


	if ($champs['idLieu'] != 0 && ($champs['nomLieu'] != "" || $champs['adresse'] != "") )
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

	$mimes_acceptes = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");

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

                /*
		if ($champs['idLieu'] != "" && $champs['idLieu'] != 0)
		{
			$sql_lieu = "SELECT nom, adresse, quartier, URL FROM lieu WHERE idLieu=".$champs['idLieu'];
			$req_lieu = $connector->query($sql_lieu);
			$tab_lieu = $connector->fetchArray($req_lieu);
			$champs['nomLieu'] = $tab_lieu['nom'];
			$champs['adresse'] = $tab_lieu['adresse'];
			$champs['quartier'] = $tab_lieu['quartier'];
			$champs['urlLieu'] = $tab_lieu['URL'];
			$lieu_modifie = true;
		}
		else if ($champs['nomLieu'] != "")
		{
			$champs['idLieu'] = "0";
			$lieu_modifie = true;
		}
                */

		//dedoublonne
		if (count($champs['organisateurs']) > 0)
		{
			$champs['organisateurs'] = array_unique($champs['organisateurs']);
		}


		$compteur_evenements = 0;

		foreach ($evenements as $idEven_courant)
		{

			// echo $compteur_evenements."<br>";


			$modifFlyerSQL = ""; // champ SQL pour le flyer

			$req_even = $connector->query("SELECT * FROM evenement WHERE idEvenement =".$idEven_courant);

			$tab_even = $connector->fetchArray($req_even);

			$nouv_genre = $tab_even['genre'];

			$champs['horaire_debut'] = $_POST['horaire_debut'];

			/*  Adaptation pour horaire_debut */
			$lendemain_evenement = date_lendemain($tab_even['dateEvenement']);

			if (!empty($champs['horaire_debut']))
			{
				$tab_horaire_debut = explode(":", $champs['horaire_debut']);

				$sec_horaire_debut = $tab_horaire_debut[0] * 3600 + $tab_horaire_debut[1] * 60;
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
				$tab_horaire_fin = explode(":", $champs['horaire_fin']);

				$sec_horaire_fin = $tab_horaire_fin[0] * 3600 + $tab_horaire_fin[1] * 60;
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

			/*
			$req_even_jg = $connector->query("
			SELECT titre, idLieu, idSalle, nomLieu, adresse, dateEvenement
			FROM evenement
			WHERE dateEvenement='".$tab_even['dateEvenement']."' AND genre='".$nouv_genre."'");

			$erreursEv = '';

			while ($tab_even_jg = $connector->fetchArray($req_even_jg))
			{

				if ($tab_even_jg['titre'] == $champs['titre'])
				{
					$erreursEv = "L'événement s'appelant '".$tab_even_jg['titre']."' du même genre a lieu à la même date";
				}

				if ($tab_even_jg['idLieu'] != 0 && $champs['idLieu'] == $tab_even_jg['idLieu'] && $champs['idSalle'] == $tab_even_jg['idSalle'])
				{
					$erreursEv = "L'événement \"".$tab_even_jg['titre'] ."\" a déjà lieu à cette endroit";
				}


			}
			*/

			if (!empty($erreursEv))
			{
				HtmlShrink::msgErreur($erreursEv);
				continue;
			}

			if (!empty($fichiers['flyer']['name']))
			{
				$champs['flyer'] = $idEven_courant.$tab_even['dateEvenement'].strrchr($fichiers['flyer']['name'], '.');
			}

			if (!empty($fichiers['image']['name']))
			{
				$champs['image'] = $idEven_courant.$tab_even['dateEvenement']."_img".strrchr($fichiers['image']['name'], '.');
			}


			$sql_flyer = ""; // champ SQL pour le flyer

			//si un nouveau flyer a été uploadé
			if (!empty($champs['flyer']))
			{

				$sql_flyer = ", flyer='".$champs['flyer']."'";
				$req_flyer = $connector->query("SELECT flyer FROM evenement WHERE idEvenement=".$idEven_courant);

				if ($req_flyer)
				{
					$affFly = $connector->fetchArray($req_flyer);

					//si  un ancien flyer a été effectivement trouvé suppression des fichiers
					if (!empty($affFly['flyer']))
					{
							unlink($rep_images_even.$affFly['flyer']);
							unlink($rep_images_even . "s_" . $affFly['flyer']);
                        echo "<div class=\"msg\">Ancien flyer ".$affFly['flyer']." supprimé</div>";
					}


				}
				else
				{
					HtmlShrink::msgErreur("La requête SELECT flyer a échoué");
				}

			//si le champ "supprimer le flyer" est coché sans qu'un nouveau flyer soit remplacant
			}

			if (!empty($supprimer['flyer']))
			{

				$sql_flyer = ", flyer=''";
				$req_flyer = $connector->query("SELECT flyer FROM evenement WHERE idEvenement=".$idEven_courant);

				//si  un ancien flyer a été effectivement trouvé suppression des fichiers
				if ($req_flyer)
				{
					$affFly = $connector->fetchArray($req_flyer);

					if (!empty($affFly['flyer']))
					{
						unlink($rep_images_even.$affFly['flyer']);
						unlink($rep_images_even . "s_" . $affFly['flyer']);
                        //echo "<div class=\"msg\">Ancien flyer ".$affFly['flyer']." supprimÃ©</div>";
					}
				}
				else
				{
					HtmlShrink::msgErreur("La requête SELECT flyer a échoué");
				}

			} //elseif supprimer flyer

			$sql_image = ""; // champ SQL pour l'image

			//si un nouveau flyer
			if (!empty($champs['image']))
			{

				$sql_image = ", image='".$champs['image']."'";
				$req_image = $connector->query("SELECT image FROM evenement WHERE idEvenement=".$idEven_courant);

				if ($req_image)
				{
					$affImg = $connector->fetchArray($req_image);

					//si  un ancien flyer a êµ© effectivement trouvé¡³uppression des fichiers
					if (!empty($affImg['image']))
					{
							unlink($rep_images_even.$affImg['image']);
							unlink($rep_images_even."s_".$affImg['image']);
							//echo "<div class=\"msg\">Ancienne image ".$affImg['image']." supprimÃ©e</div>";
					}
				}
				else
				{
					HtmlShrink::msgErreur("La requÃªte SELECT image a Ã©chouÃ©");
				}

			//si le champ "supprimer le flyer" est coché¡³ans qu'un nouveau flyer soit remplacant
			}

			if (!empty($supprimer['image']))
			{

				$sql_image = ", image=''";
				$req_image = $connector->query("SELECT image FROM evenement WHERE idEvenement=".$idEven_courant);

				//si  un ancien flyer a êµ© effectivement trouvé¡³uppression des fichiers
				if ($req_image)
				{
					$affimage= $connector->fetchArray($req_image);

					if (!empty($affimage['image']))
					{
						unlink($rep_images_even.$affimage['image']);
						unlink($rep_images_even."s_".$affimage['image']);
						//echo "<div class=\"msg\">Ancien image ".$affimage['image']." supprimÃ©e</div>";
					}
				}
				else
				{
					HtmlShrink::msgErreur("La requête SELECT image a Ã©chouÃ©");
				}

			} //if supprimer image

			$sql_update = "UPDATE evenement SET ";

			foreach ($champs as $c => $v)
			{
                            if ((!empty($v) && $c != "idPersonne" && $c != "organisateurs") || (($c == "idLieu" || $c == "urlLieu" || $c == "quartier" || $c == "localite_id" || $c == "region") && $lieu_modifie == true )
                            )
                            {
                                    $sql_update .= $c."='".$connector->sanitize($v)."', ";
                            }
			}


                        //(($v == "" && ($c == "urlLieu" || $c == "quartier")) || $v != "") &&

			$sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
			$sql_update .= $sql_flyer.$sql_image."
			WHERE idEvenement=".$idEven_courant;


				//echo "<p>".$sql_update."</p>";


			$req_update = $connector->query($sql_update);

			/*
			* MAJ réussie, message OK, et RAZ de l'action
			*/
			if ($req_update)
			{
				HtmlShrink::msgOk('Mise à jour de <a href="/evenement.php?idE='.$idEven_courant.'">'.$tab_even['titre'].'</a> le <a href="/evenement-agenda.php?courant='.$tab_even['dateEvenement'].'">'.date_fr($tab_even['dateEvenement'], "annee").'</a> réussie');

				$sql = "DELETE FROM evenement_organisateur WHERE idEvenement=".$idEven_courant;
				$req = $connector->query($sql);

				$action_terminee = true;
            }
			else
			{

				HtmlShrink::msgErreur("La requête UPDATE de la table evenement a échoué");
			}

			/*
			* TRAITEMENT DE L'IMAGE UPLOADEE
			*/
			if (!empty($fichiers['flyer']['name']) && $compteur_evenements == 0)
			{

				$imD2 = new ImageDriver2("evenement");
				$erreur_image = array();
				$erreur_image[] = $imD2->processImage($_FILES['flyer'], $champs['flyer'], 400, 400);
				$erreur_image[] = $imD2->processImage($_FILES['flyer'], "s_".$champs['flyer'], 120, 190, 0, 1);

				if (!empty($erreur_image))
				{
					//printr($erreur_image);
				}

				if (!empty($msg2))
					$champs['flyer'] = '';

				$srcFlyer = $champs['flyer'];
			}
			elseif (!empty($fichiers['flyer']['name']))
			{

				$src = $rep_images_even.$srcFlyer;
				$des = $rep_images_even.$champs['flyer'];

				if (!copy($src, $des))
					HtmlShrink::msgErreur("La copie du fichier taille normale ".$champs['flyer']." n'a pas réussi...");


				$src = $rep_images_even."s_".$srcFlyer;
				$des = $rep_images_even."s_".$champs['flyer'];

				if (!copy($src, $des))
					HtmlShrink::msgErreur("La copie du fichier taille small " . $champs['flyer'] . " n'a pas réussi...");
            }

			if (!empty($fichiers['image']['name']) && $compteur_evenements == 0)
			{

				$imD2 = new ImageDriver2("evenement");
				$erreur_image = array();
				$erreur_image[] = $imD2->processImage($_FILES['image'], $champs['image'], 400, 400);
				$erreur_image[] = $imD2->processImage($_FILES['image'], "s_".$champs['image'], 120, 190, 0, 1);
				if (!empty($erreur_image))
				{
					//printr($erreur_image);
				}

				if (!empty($msg2))
					$champs['image'] = '';

				$src_image = $champs['image'];
			}
			elseif (!empty($fichiers['image']['name']))
			{

				$src = $rep_images_even.$src_image;
				$des = $rep_images_even.$champs['image'];

				if (!copy($src, $des))
					HtmlShrink::msgErreur("La copie du fichier taille normale ".$champs['image']." n'a pas réussi...");

				$src = $rep_images_even."s_".$src_image;
				$des = $rep_images_even."s_".$champs['image'];

				if (!copy($src, $des))
					HtmlShrink::msgErreur("La copie du fichier taille small ".$champs['image']." n'a pas réussi...");

			}

				foreach ($champs['organisateurs'] as $no => $idOrg)
				{
					if ($idOrg != 0)
					{
						$sql = "INSERT INTO evenement_organisateur (idEvenement, idOrganisateur) VALUES (".$idEven_courant.", ".$idOrg.")";
						//echo $sql;

						if ($connector->query($sql))
						{

						}
					}
				}


			$compteur_evenements++;
		}

		unset($_POST);
		unset($_FILES);
		foreach ($champs as $c => $v)
		{
			$champs[$c] = '';
		}
	}


}

if ($verif->nbErreurs() > 0)
{
	HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");

	//print_r($verif->getErreurs());
}


/*
 * AFFICHAGE DE LA TABLE ET SON MENU DE NAVIGATION
 */

$sql_nbeven = "SELECT COUNT(*) AS nbeven FROM evenement ".$where;

$req_nbeven = $connector->query($sql_nbeven);
$tab_nbeven = $connector->fetchArray($req_nbeven);
$tot_elements = $tab_nbeven['nbeven'];

$total_page_max = ceil($tot_elements / $get['nblignes']);
if ($get['page'] > $total_page_max)
	$get['page'] = $total_page_max;


$sql_page = $get['page'];
if ($get['page'] < 1)
    $sql_page = 1;

$sql_evenement = "
SELECT idEvenement, idLieu, idPersonne, statut, idPersonne, genre, titre, dateEvenement, horaire_debut, horaire_fin, nomLieu, flyer, dateAjout,
 date_derniere_modif
FROM evenement ".$where."
ORDER BY ".$get['tri_gerer']." ".$get['ordre']." LIMIT ".($sql_page - 1) * $get['nblignes'].",".$get['nblignes'];

//echo $sql_evenement;
$req_evenement = $connector->query($sql_evenement);
?>

<div style="width:94%;margin:0 auto">

<ul class="menu_filtre" style="float:left;width:60%">
<li
<?php
if ($get['filtre_genre'] == 'tous') { echo 'class="ici"'; }

echo '><a href="?'.Utils::urlQueryArrayToString($get, "filtre_genre").'&filtre_genre=tous">Tous</a></li>';

foreach ($glo_tab_genre as $ng => $nl)
{
	echo '<li ';
	if ($get['filtre_genre'] == $ng) { echo 'class="ici"'; }
        $nom = $ng;
        if ($ng == 'cinéma')
            $nom = 'ciné';

	echo '><a href="?'.Utils::urlQueryArrayToString($get, "filtre_genre").'&filtre_genre='.$ng.'">'.ucfirst($nom).'</a></li>';
}
echo '</ul>';
?>
    <div class="spacer"></div>
	<form method="get" action="" id="ajouter_editer" style="margin:0;">

		<input type="hidden" name="filtre_genre" value="<?php echo $get['filtre_genre']; ?>" />
		<input type="hidden" name="page" value="<?php echo $get['page']; ?>" />
		<input type="hidden" name="nblignes" value="<?php echo $get['nblignes']; ?>" />
		<input type="hidden" name="tri_gerer" value="<?php echo $get['tri_gerer']; ?>" />
		<input type="hidden" name="element" value="<?php echo $get['element']; ?>" />
		<input type="hidden" name="ordre" value="<?php echo $get['ordre']; ?>" />

		<input type="text" name="terme" value="<?php echo $get['terme']; ?>" placeholder="Titre" size="30" />
		<input type="submit" name="submit" value="Filtrer" />

	</form>
</div>
<div class="spacer"></div>

<div id="gerer-even-pagination">

<?php
echo HtmlShrink::getPaginationString($get['page'], $tot_elements, $get['nblignes'], 1, $_SERVER['PHP_SELF'], "?element=".$get['element']."&tri_gerer=".$get['tri_gerer']."&ordre=".$get['ordre']."&nblignes=".$get['nblignes']."&filtre_genre=".$get['filtre_genre']."&terme=".$get['terme']."&page=");
?>



<?php

echo '<ul class="menu_nb_res" style="float:right;margin: 1em auto 1.4em;width:35%;text-align:right">';
foreach ($tab_nblignes as $nbl)
{
	echo '<li ';
	if ($get['nblignes'] == $nbl) { echo 'class="ici"'; }

	echo '><a href="/admin/gererEvenements.php?'.Utils::urlQueryArrayToString($get, "nblignes").'&nblignes='.$nbl.'">'.$nbl.'</a></li>';
}
echo '</ul>';
?>

</div>

<?php

echo '<div class="spacer"></div>';
$th_evenements = array("titre" => "Titre", "idLieu" => "Lieu", "dateEvenement" => "Date", "genre" => "Catégorie", "horaire" => "Horaire", "statut" => "Statut",
"dateAjout" => "Ajouté");

echo "<form method=\"post\" id=\"formGererEvenements\" class='js-submit-freeze-wait' enctype=\"multipart/form-data\" action=\"/admin/gererEvenements.php\">";

echo "<table id=\"ajouts\" class=\"jquery-checkboxes\"><tr>";

foreach ($th_evenements as $att => $th)
{
	if ($att == "idLieu" || $att == "flyer")
	{
		echo "<th>".$th."</th>";
	}
	else
	{
		if ($att == $get['tri_gerer'])
		{
			echo "<th class=\"ici\">".$icone[$get['ordre']];
		}
		else
		{
			echo "<th>";
		}

		echo "<a href=\"?".Utils::urlQueryArrayToString($get, "ordre")."&ordre=".$ordre_inverse."\">".$th."</a></th>";
	}
}

echo "<th colspan=2></th></tr>";

$pair = 0;

while ($tab_even = $connector->fetchArray($req_evenement))
{

	$nomLieu = sanitizeForHtml($tab_even['nomLieu']);

	if ($tab_even['idLieu'] != 0)
	{
		$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_even['idLieu']);
		$tabLieu = $connector->fetchArray($req_lieu);
		$nomLieu = "<a href=\"/lieu.php?idL=".$tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".sanitizeForHtml($tabLieu['nom'])." \">".sanitizeForHtml($tabLieu['nom'])."</a>";
	}


	if ($pair % 2 == 0)
	{
		echo "<tr>";
    }
	else
	{
		echo "<tr class=\"impair\" >";
    }

	echo "	<td><a href=\"/evenement.php?idE=".$tab_even['idEvenement']."\" title=\"Voir la fiche de l'événement\" class='titre'>".sanitizeForHtml($tab_even['titre'])."</a></td>	<td>".$nomLieu."</td>
	<td>".date_iso2app($tab_even['dateEvenement'])."</td>
	<td>".ucfirst($glo_tab_genre[$tab_even['genre']])."</td>


	<td>";

	echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement']);


	echo "</td><td>".$tab_icones_statut[$tab_even['statut']]."</td>";
	$datetime_dateajout = date_iso2app($tab_even['dateAjout']);
	$tab_datetime_dateajout = explode(" ", $datetime_dateajout);
	echo "<td>".$tab_datetime_dateajout[1]." ".$tab_datetime_dateajout[0]."</td>";


	if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) {
		echo '<td style="text-align:center"><a href="/evenement-edit.php?action=editer&idE='.$tab_even['idEvenement'].'" title="Éditer l\'événement">'.$iconeEditer.'</a></td>';
	}
	echo '<td style="text-align:center"><input type="checkbox" name="evenements[]" value="'.$tab_even['idEvenement'].'" /></td></tr>';

	$pair++;

} // fin while


echo "</table>";
echo $verif->getErreur("evenements");
//printr($verif->getErreurs());
?>
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
foreach ($statuts_evenement as $s => $v)
{
	$coche = '';
	if (strcmp($s,$champs['statut']) == 0)
	{
		$coche = 'checked="1"';
	}
	echo '<li class="listehoriz"><input type="radio" name="statut" value="'.$s.'" '.$coche.' id="genre_'.$s.'" title="statut de l\'événement" class="radio_horiz" /><label class="continu" for="genre_'.$s.'">'.$v.'</label></li>';
}
?>
</ul>
-->
<ul class="radio">
<?php

$statuts = array('actif' => '<strong>publié</strong> (visible sur le site)',  'complet' => '<strong>complet</strong> (visible sur le site mais marqué comme étant complet)', 'annule' => '<strong>annulé</strong> (visible sur le site mais marqué comme étant annulé)', 'inactif' => '<strong>dépublié</strong> (non visible sur le site)');
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
	<label class="continu" for="genre_'.$na.'">'.$la.'</label></li>';
}
?>
</ul>

<?php
echo $verif->getErreur("genre");
?>
</fieldset>



<!-- DEB LIEU -->
<fieldset>
<legend>Lieu*</legend>
<p>
<label for="lieu">Dans la liste :</label>

<select name="idLieu" id="idLieu" class="chosen-select" style="max-width:300px">
<?php
//Menu des lieux actifs de la base
echo "<option value=\"0\">&nbsp;</option>";
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
	if (preg_match("/^(Le |La |Les |L')(.*)/", $lieuTrouve['nom'], $matches))
	{
		$nom_lieu = $matches[2].', '.$matches[1];

	}

	if ($lieuTrouve['idLieu'] == $champs['idLieu'])
	{
		echo "selected=\"selected\" ";
	}

	echo "value=\"".$lieuTrouve['idLieu']."\">".$nom_lieu."</option>";


	$sql_salle = "select * from salle where idLieu=".$lieuTrouve['idLieu']. " AND salle.status='actif' ";
	$req_salle = $connector->query($sql_salle);
	while ($tab_salle = $connector->fetchArray($req_salle))

	{
		echo "<option ";
		if ($champs['idSalle'] != 0 && $tab_salle['idSalle'] == $champs['idSalle'])
		{
			echo "selected=\"selected\" ";
		}
		echo " style=\"font-style:italic;color:#444;\" value=".$lieuTrouve['idLieu']."_".$tab_salle['idSalle'].">".$nom_lieu."&nbsp;– ".$tab_salle['nom']."</option>";

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
$tab_nomLieu_label = array("for" => "nomLieu");
echo HtmlShrink::formLabel($tab_nomLieu_label, "Nom du lieu :");
echo $verif->getErreur("nomLieuIdentique");

$tab_nomLieu = array("type" => "text", "name" => "nomLieu", "id" => "nomLieu", "size" => "40", "maxlength" => "80", "tabindex" => "9", "value" => "",  "onfocus" => "this.className='focus';", "onblur" => "this.className='normal';");
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
<?php if (empty($champs['idLieu'])) { echo sanitizeForHtml($champs['adresse']); } ?>" onfocus="this.className='focus';" onblur="this.className='normal';" />
<?php
echo $verif->getHtmlErreur("adresse");
echo $verif->getErreur("doublonLieux");
?>
</p>


<p>
<label for="localite">Localité/quartier</label>
<select name="localite_id" id="localite" class="chosen-select" style="max-width:300px;">
<?php
echo "<option value=\"0\">&nbsp;</option>";
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

	echo "value=\"".$tab['id']."\">".$tab['localite']."</option>";

    // Genève quartiers
    if ($tab['id'] == 44)
    {

        // si erreur formulaire
        $champs_quartier = '';
        $loc_qua = explode("_", $champs['localite_id']);
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

           echo " value=\"".$id."\">".$nom."</option>";

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
<?php if (empty($champs['idLieu'])) { echo sanitizeForHtml($champs['urlLieu']); } ?>" onfocus="this.className='focus';" onblur="this.className='normal';" />
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
<select name="organisateurs[]" id="organisateurs" class="chosen-select" multiple data-placeholder="Choisissez un ou plusieurs organisateurs" style="max-width:400px;">
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

		echo "value=\"".$tab['idOrganisateur']."\">".$tab['nom']."</option>";
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
	$imgInfo = getimagesize($rep_images_even.$champs['flyer']);
	$iconeImage = '<img src="' . $url_uploads_events . "s_" . $champs['flyer'] . '" alt="image pour ' . sanitizeForHtml($champs['titre']) . '" />';
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
    <input type="file" name="image" id="flyer" class="js-file-upload-size-max" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif" title="Choisissez une image pour illustrer l'ê·©nement" tabindex="12" class="fichier" />
    </p>
    <div class="guideChamp">Seul les formats JPEG, PNG et GIF sont acceptés.</div>
<div class="spacer"></div>
<?php
echo $verif->getErreur("image");


//affichage du flyer, et du bouton pour supprimer
if (isset($get_idE) && !empty($champs['image']) && !$verif->getErreur('image'))
{
	$imgInfo = getimagesize($rep_images_even.$champs['image']);
	$iconeImage = "<img src=\"".$url_uploads_events."s_".$champs['image']."\"  alt=\"image pour ".sanitizeForHtml($champs['titre'])."\" />";

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

</div>
<!-- Fin contenu -->

<div id="colonne_gauche" class="colonne">
    <?php
    include("_menuAdmin.inc.php");
    ?>
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>