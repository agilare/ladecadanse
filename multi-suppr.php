<?php
global $logger;
require_once("app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Logger;

if (!$videur->checkGroup(6)) {
    header("Location: index.php");
    die();
}

$page_titre = "supprimer un élément";
$page_description = "Suppression d'un élément";
$extra_css = ["evenement_inc", "lieu_inc", "descriptionlieu_inc"];
include("_header.inc.php");

$tab_types = ["evenement", "lieu", "descriptionlieu", "personne", "salle", "organisateur"];

/*
 * Vérification et attribution des variables d'URL GET
 */
$get['type'] = "";
if (isset($_GET['type'])) {
    $get['type'] = Validateur::validateUrlQueryValue($_GET['type'], "enum", 1, $tab_types);
}
else {
    HtmlShrink::msgErreur("type obligatoire");
}


$tab_actions = ["confirmation", "suppression"];
$get['action'] = "suppression";
if (isset($_GET['action'])) {
    $get['action'] = Validateur::validateUrlQueryValue($_GET['action'], "enum", 1, $tab_actions);
}

if (isset($_GET['id'])) {
    $get['id'] = Validateur::validateUrlQueryValue($_GET['id'], "int", 1);
}
else {
    HtmlShrink::msgErreur("id obligatoire");
    exit;
}

if (isset($_GET['idP'])) {
    $get['idP'] = Validateur::validateUrlQueryValue($_GET['idP'], "int", 1);
}
?>




<!-- debut Contenu -->
<div id="contenu" class="colonne">

    <?php
    echo '<div id="entete_contenu"><h2>Suppression</h2></div>';
    /* SUPPRESSION CONFIRMEE
     * Vérification si la personne est 'auteur' et l'auteur ou admin
     * Récupération du nom du flyer, suppression des fichiers réduits et mini
     * Déstruction de la brève
     * Message de confirmation
     */
    if ($get['action'] == 'confirmation' && isset($get['id'])) {

        if (!SecurityToken::check($_GET['token'], $_SESSION['token'])) {
            echo "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réessayer";
    }

        if ($get['type'] == "evenement") {

            ///TESTER SI L'EVENEMENT EXISTE ENCORE

            if ((($authorization->isAuthor($get['type'], $_SESSION['SidPersonne'], $get['id']) && $_SESSION['Sgroupe'] <= 6) || $_SESSION['Sgroupe'] < 2))
        {
                $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement
			FROM evenement WHERE idEvenement=" . $get['id']);

                $val_even = $connector->fetchArray($req_im);
                $titreSup = $val_even['titre']; //pour le message apres suppression

                if (!empty($val_even['flyer'])) {
                    Evenement::rmImageAndItsMiniature($val_even['flyer']);
            }

                if (!empty($val_even['image'])) {
                    Evenement::rmImageAndItsMiniature($val_even['image']);
                }

                if ($connector->query("DELETE FROM evenement WHERE idEvenement=" . $get['id'])) {
                    HtmlShrink::msgOk('L\'événement "' . sanitizeForHtml($titreSup) . '" a été supprimé');
                    $logger->log('global', 'activity', "[supprimer] event \"$titreSup\" (" . $get['id'] . ") deleted", Logger::GRAN_YEAR);
                }
            }
            else {
                HtmlShrink::msgErreur("Vous ne pouvez pas supprimer cet événement.");
            }
        }
        else if ($get['type'] == "lieu") {
            /*
             * EN CAS DE SUPPRESSION ou DESACTIVATION affiche dans un tableau les événements à supprimer d'abord
             */
            $req_evLieu = $connector->query("SELECT idEvenement, dateEvenement, idPersonne, titre, dateAjout
		FROM evenement WHERE dateEvenement >= CURDATE() AND idLieu=" . $get['id']);

            $nbEvLieu = $connector->getNumRows($req_evLieu);

            $req_desLieu = $connector->query("SELECT idPersonne,dateAjout,contenu
		FROM descriptionlieu WHERE idLieu=" . $get['id']);

            $nbDesLieu = $connector->getNumRows($req_desLieu);

            if ($nbEvLieu > 0) {

                echo "<div class=\"msgForm\">Vous devez d'abord supprimer les événements suivants :</div>
			<table>
			<tr>
			<th>ID</th>
			<th>Date</th>
			<th>Auteur</th>
			<th>Titre</th>
			<th>Ajouté le</th>
			</tr>";

                while ($tab_even = $connector->fetchArray($req_evLieu))
                {

                    echo "<tr>
				<td>" . $tab_even['idEvenement'] . "</td>
				<td>" . $tab_even['dateEvenement'] . "</td>
				<td><a href=\"//user.php?idP=" . $tab_even['idPersonne'] . "\" title=\"Voir le profile de la personne\">" . $tab_even['idPersonne'] . "</a></td>
				<td>" . sanitizeForHtml($tab_even['titre']) . "</td>
				<td>" . $tab_even['dateAjout'] . "</td>
				</tr>";
                }

                @mysqli_free_result($req_evLieu);

                echo "</table>";
            }
            else if ($nbDesLieu > 0) {

                echo "<div class=\"msg\">Vous devez d'abord supprimer les descriptions suivantes :</div>
			<table>
			<tr>
			<th>Auteur</th>
			<th>Contenu</th>
			<th>Ajouté le</th>
			</tr>";

                while ([$idPersonne, $dateAjout, $contenu] = $connector->fetchArray($req_desLieu))
                {
                    echo "<tr>
				<td><a href=\"/user.php?idP=" . $idPersonne . "\" title=\"Voir le profile de la personne\">" . $idPersonne . "</a></td>";
                    if (strlen($contenu) > 200) {
                        $contenu = mb_substr($contenu, 0, 200) . " [...]";
                    }
                    echo "<td>" . sanitizeForHtml($contenu) . "</td>
				<td>" . $dateAjout . "</td>
				</tr>";
                }

                echo "</table>";
            }
            else {
                if ((($authorization->isAuthor($get['type'], $_SESSION['SidPersonne'], $get['id']) && $_SESSION['Sgroupe'] < 7) || $_SESSION['Sgroupe'] < 2))
            {
                //supression des images
                    $req_imLieu = $connector->query("SELECT nom, photo1, logo FROM lieu WHERE idLieu=" . $get['id']);
                    $im = $connector->fetchArray($req_imLieu);

                    if (!empty($im['photo1'])) {
                        unlink($rep_uploads_lieux . $im['photo1']);
                        unlink($rep_uploads_lieux . "s_" . $im['photo1']);
                    }
                    if (!empty($im['logo'])) {
                        unlink($rep_uploads_lieux . $im['logo']);
                        unlink($rep_uploads_lieux . "s_" . $im['logo']);
                    }


                    $sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension, dateAjout
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=" . $get['id'] . " AND type='image' AND
 fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu";

                    $req_docu = $connector->query($sql_docu);

                    while ($tab_docu = $connector->fetchArray($req_docu))
                    {
                        $nom_fichier = $tab_docu['idFichierrecu'] . "." . $tab_docu['extension'];
                        if (unlink($rep_uploads_lieux_galeries . $nom_fichier)) {
                            echo $nom_fichier . " supprimé<br>";
                        }
                        if (unlink($rep_uploads_lieux_galeries . "s_" . $nom_fichier)) {
                            echo "s_" . $nom_fichier . " supprimé<br>";
                        }
                        $connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=" . $tab_docu['idFichierrecu']);
                    }

                $connector->query("DELETE FROM lieu_fichierrecu WHERE idLieu=" . $get['id']);

                    $connector->query("DELETE FROM salle WHERE idLieu=" . $get['id']);

                    //supression du lieu
                    if ($connector->query("DELETE FROM lieu WHERE idLieu=" . $get['id'])) {
                        HtmlShrink::msgOk("Le lieu a été supprimé");
                    }
                    else {
                        HtmlShrink::msgErreur("La requète DELETE sur 'lieu' a échoué");
                    }
                }
                else {
                    HtmlShrink::msgErreur("Vous ne pouvez pas supprimer ce lieu.");
                }
            }
        }
        else if ($get['type'] == "descriptionlieu") {
            if ($_SESSION['Sgroupe'] < 2) {
                $req_delDes = $connector->query("DELETE FROM descriptionlieu WHERE idLieu=" . $get['id'] . " AND idPersonne=" . $get['idP']);

                if ($req_delDes) {
                    HtmlShrink::msgOk("La description a été supprimée");
                    $logger->log('global', 'activity', "[supprimer] description of lieu (" . $get['id'] . ") deleted", Logger::GRAN_YEAR);
            }
                else {
                    HtmlShrink::msgErreur("La requète DELETE a échoué");
                }
            }
            else {
                HtmlShrink::msgErreur("Vous n'avez pas les droits pour supprimer cette description");
            }
        }
    else if ($get['type'] == "salle") {
            if ($_SESSION['Sgroupe'] <= 6) {
                $req = $connector->query("SELECT idSalle FROM evenement WHERE idSalle=" . $get['id']);

                if ($connector->getNumRows($req) == 0) {
                    if ($connector->query("DELETE FROM " . $get['type'] . " WHERE idSalle=" . $get['id'])) {
                        HtmlShrink::msgOk("La salle a été supprimée");
                        $logger->log('global', 'activity', "[supprimer] " . $get['type'] . " (" . $get['id'] . ") deleted", Logger::GRAN_YEAR);
                        exit;
                    }
                    else {
                        HtmlShrink::msgErreur("La requète a échoué");
                    }
                }
                else {
                    HtmlShrink::msgErreur("Il y a encore " . $connector->getNumRows($req) . " événement(s) se déroulant dans cette salle.");
                }
            }
            else {
                HtmlShrink::msgErreur("Vous n'avez pas les droits pour supprimer cette salle");
            }
        }
        else if ($get['type'] == "organisateur") {

            /*
             * EN CAS DE SUPPRESSION ou DESACTIVATION affiche dans un tableau les événements à supprimer d'abord
             */
            $req_ev = $connector->query("SELECT evenement.idEvenement AS idE
		FROM evenement_organisateur, evenement WHERE evenement.idEvenement=evenement_organisateur.idEvenement AND dateEvenement >= CURDATE() AND idOrganisateur=" . $get['id']);

            $nb_ev = $connector->getNumRows($req_ev);
            echo $nb_ev;
            $req_lieu = $connector->query("SELECT *
		FROM lieu_organisateur WHERE idOrganisateur=" . $get['id']);

            $nb_lieu = $connector->getNumRows($req_lieu);

            if ($nb_ev > 0) {
                HtmlShrink::msgErreur('Il y a encore ' . $nb_ev . ' événement(s) de cet organisateur.');
            }
            else if ($nb_lieu > 0) {
                HtmlShrink::msgErreur('Il y a encore ' . $nb_lieu . ' lieu(x) géré(s) par cet organisateur.');
            }
            else {

                if ($_SESSION['Sgroupe'] <= 6) {

                    if ($connector->query("DELETE FROM " . $get['type'] . " WHERE idOrganisateur=" . $get['id'])) {
                        HtmlShrink::msgOk("L'organisateur a été supprimé");
                        $logger->log('global', 'activity', "[supprimer] organizer " . $get['id'] . " deleted", Logger::GRAN_YEAR);
                        exit;
                    }
                    else {
                        HtmlShrink::msgErreur("La requète a échoué");
                    }
                }
                else {
                    HtmlShrink::msgErreur("Vous n'avez pas les droits pour supprimer cette salle");
                }
            }
        }
    }
else if ($get['action'] == 'suppression') {

        $act = "confirmation&amp;type=" . $get['type'] . "&amp;id=" . $get['id'];
        if (isset($get['idP'])) {
            $act .= "&amp;idP=" . $get['idP'];
        }
    }


/*
      if ((($authorization->estAuteur($_SESSION['SidPersonne'], $get['idE'], "evenement") && $_SESSION['Sgroupe'] <= 6) || $_SESSION['Sgroupe'] == 1) ) {



     * POUR EDITER UN EVENEMENT, ALLER CHERCHER SES VALEURS DANS LA BASE
     * Accessible par un membre
     * Récupération des valeurs de la table et remplissage des champs pour le formulaire
     * Affichage d'un menu d'actions pour l'admin
     */
    if ($get['action'] != 'confirmation' && isset($get['id'])) {
        if ($get['type'] == "evenement") {
            $req_even = $connector->query("SELECT idEvenement, idLieu, idSalle, idPersonne, titre, genre,
		dateEvenement, nomLieu, adresse, quartier, urlLieu, description, flyer, image, prix, horaire_debut, horaire_fin, horaire_complement,
		ref, prelocations FROM " . $get['type'] . " WHERE idEvenement =" . $get['id']);

            if ($tab_even = $connector->fetchArray($req_even)) {
                $evenement = $tab_even;
                include("_evenement.inc.php");
            }
            else {
                HtmlShrink::msgErreur("La requète select a échoué");
                exit;
            }

            @mysqli_free_result($req_even);
        }
        else if ($get['type'] == "lieu") {
            //récolte des détails sur le lieu
            $req_lieu = $connector->query("SELECT idLieu, nom, adresse, quartier, horaire_general, categorie, URL, photo1, logo, dateAjout, date_derniere_modif FROM lieu WHERE idLieu=" . $get['id']);

        if ($tab_lieu = $connector->fetchArray($req_lieu)) {

                $lieu = $tab_lieu;
            }
            else {
                HtmlShrink::msgErreur("La requète select a échoué");
                exit;
            }

            @mysqli_free_result($req_lieu);
        }
        else if ($get['type'] == "descriptionlieu") {
            $req_desc = $connector->query("SELECT descriptionlieu.idLieu, contenu, descriptionlieu.dateAjout, pseudo, groupe, descriptionlieu.idPersonne AS auteur, descriptionlieu.date_derniere_modif
		FROM descriptionlieu
		INNER JOIN personne ON descriptionlieu.idPersonne = personne.idPersonne
		WHERE descriptionlieu.idLieu =" . $get['id'] . " AND personne.idPersonne=" . $get['idP'] . " ORDER BY descriptionlieu.dateAjout");

            if ($res_desc = $connector->fetchArray($req_desc)) {
                $descriptionlieu = $res_desc;
                ?>
                <?php
                if (isset($descriptionlieu['dateAjout'])) {
                    $ajoute = "Ajouté le " . date_fr($descriptionlieu['dateAjout'], 'annee');
                }
                ?>
                <?php
                if ($descriptionlieu['date_derniere_modif'] != "0000-00-00 00:00:00") {
                    $dern_modif = "Dernière modification : " . date_fr($descriptionlieu['date_derniere_modif'], 'annee');
                }
                ?>
                <div id="descriptions">

                                <div class="description">

                                    <p><?php echo Text::wikiToHtml($descriptionlieu['contenu']) ?></p>

                                    <div class="auteur">
                                        <span class="left"><?php echo $ajoute; ?><br /><?php echo $dern_modif; ?></span>
                                        <span class="right action_editer">
                                                        <a href="/lieu-text-edit.php?action=editer&amp;idL=<?php echo $descriptionlieu['idLieu'] ?>&amp;idP=<?php echo $descriptionlieu['auteur'] ?>">
                                                            Modifier</a>
                                        </span>

                                    </div>
                                    <div class="spacer"><!-- --></div>
                                </div>
                                <!-- Fin description -->

                            </div>
                            <?php
                        }

                        @mysqli_free_result($req_desc);
                    }
    else if ($get['type'] == "salle") {
                        $req = $connector->query("SELECT * FROM salle WHERE idSalle =" . $get['id']);

                        if ($salle = $connector->fetchArray($req)) {
                            ?>
                            <ul>
                                <li><?= $salle['idSalle']; ?></li>
                                <li><?= $salle['idLieu']; ?></li>
                                <li><?= $salle['nom']; ?></li>
                                <li><?= $salle['emplacement']; ?></li>
                            </ul>
                            <?php
                        }

                        @mysqli_free_result($req);
                    }
                    else if ($get['type'] == "organisateur") {

                        $req = $connector->query("SELECT idOrganisateur, nom, presentation FROM organisateur WHERE idOrganisateur=" . $get['id']);

                        if ($organisateur = $connector->fetchArray($req)) {
                            ?>

    <ul>
                    <li><?= $organisateur['idOrganisateur']; ?></li>
                    <li><?= $organisateur['nom']; ?></li>
                    <li><?= $organisateur['presentation']; ?></li>
                </ul>
                <?php
            }

            @mysqli_free_result($req);
        }
    }

    if ($get['action'] == "suppression") {
        ?>

    <!-- FORMULAIRE POUR UN EVENEMENT -->
        <form method="post" id="supprimerEvenement" action="?action=<?php echo $act ?>">

                <p>
                    Êtes-vous sûr de vouloir supprimer cet élément ?
                    <button type="submit" name="confirmation" value="oui">Supprimer</button>
                </p>

            </form>

            <?php
        }
?>


</div>
<!-- fin contenu  -->

<div id="colonne_gauche" class="colonne">
<?php
include("_navigation_calendrier.inc.php");
?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>

<?php
include("_footer.inc.php");
?>
