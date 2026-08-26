<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\EvenementCollection;
use Ladecadanse\UserLevel;
use Ladecadanse\HtmlShrink;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\Lieu;
use Ladecadanse\Localite;
use Ladecadanse\Organisateur;
use Ladecadanse\Security\SecurityToken;

if (!$authorization->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    exit;
}

$verif = new Validateur();

/*
 * FILTRES, TRI ET PAGINATION
 *
 * Mémorisés en session comme ceux de admin/users.php : revenir d'une fiche d'événement
 * retrouve la liste telle qu'on l'avait laissée. Seule la page reste dans l'URL, pour que
 * les liens de pagination fonctionnent.
 */
$get = [];
$get['page'] = QueryParamValidator::pageFromQuery($_GET['page'] ?? '');

$filtres = [];
foreach (['terme', 'lieu', 'personne'] as $champ_filtre)
{
    $_SESSION['user_prefs_even_' . $champ_filtre] ??= '';

    if (isset($_GET[$champ_filtre]))
    {
        $_SESSION['user_prefs_even_' . $champ_filtre] = trim((string) $_GET[$champ_filtre]);
    }

    $filtres[$champ_filtre] = $_SESSION['user_prefs_even_' . $champ_filtre];
}

// Un administrateur régional ne voit que sa région ; le superadmin n'en a pas.
$_SESSION['region_admin'] = '';
if ($_SESSION['Sgroupe'] >= UserLevel::ADMIN && !empty($_SESSION['Sregion']))
{
    $_SESSION['region_admin'] = $_SESSION['Sregion'];
}
$filtres['region'] = $_SESSION['region_admin'];
$titre_region = $filtres['region'] !== '' ? " - " . $glo_regions[$filtres['region']] : '';

$_SESSION['user_prefs_even_order_by'] ??= 'dateAjout';
if (isset($_GET['order_by']) && array_key_exists($_GET['order_by'], EvenementCollection::ADMIN_ORDER_COLUMNS))
{
    $_SESSION['user_prefs_even_order_by'] = $_GET['order_by'];
}
$order_by = $_SESSION['user_prefs_even_order_by'];

$_SESSION['user_prefs_even_order_dir'] ??= 'desc';
if (isset($_GET['order_dir']) && in_array($_GET['order_dir'], ['asc', 'desc'], true))
{
    $_SESSION['user_prefs_even_order_dir'] = $_GET['order_dir'];
}
$order_dir = $_SESSION['user_prefs_even_order_dir'];
$order_dir_inverse = $order_dir === 'asc' ? 'desc' : 'asc';

// Le 100 de $tab_nblignes n'apporte rien entre 50 et 250, et la liste d'administration est
// lourde : chaque ligne porte son lieu, ses organisateurs et son auteur.
$tab_nblignes_even = [50, 250, 500];
$_SESSION['user_prefs_even_nblignes'] ??= $tab_nblignes_even[0];
if (!empty($_GET['nblignes']) && in_array((int) $_GET['nblignes'], $tab_nblignes_even, true))
{
    $_SESSION['user_prefs_even_nblignes'] = (int) $_GET['nblignes'];
}
// une session ouverte avant le retrait du 100 en porterait encore la valeur
if (!in_array((int) $_SESSION['user_prefs_even_nblignes'], $tab_nblignes_even, true))
{
    $_SESSION['user_prefs_even_nblignes'] = $tab_nblignes_even[0];
}
$nblignes = (int) $_SESSION['user_prefs_even_nblignes'];

$th_evenements = ["titre" => "Titre", "idLieu" => "Lieu", "dateEvenement" => "Date", "genre" => "Catég.", "horaire" => "Horaire", "organisateurs" => "Orga.", "statut" => "Statut", "dateAjout" => "Ajouté", "pseudo" => "par"];


/*
 * TRAITEMENT DU FORMULAIRE D'ÉDITION GROUPÉE
 */
$champs = ["genre" => "", "idLieu" => "", "idSalle" => "", "nomLieu" => "", "adresse" => "", "quartier" => "",  "localite_id" => "", "region" => "", "urlLieu" => "", "titre" => "", "description" => "", "ref" => "", "horaire_debut" => "", "horaire_fin" => "", "horaire_complement" => "", "prix" => "", "prelocations" => "", "statut" => ""];

/*
 * Les colonnes du lieu s'écrivent même vides dès lors que le lieu a changé : passer d'un lieu
 * de la liste à une adresse saisie à la main doit effacer l'idLieu précédent, qu'aucune valeur
 * non vide ne viendrait remplacer.
 */
$champs_du_lieu = ["idLieu", "urlLieu", "quartier", "localite_id", "region"];

$evenements = [];

// un navigateur envoie toujours les champs fichier, même vides ; un client qui ne le fait pas
// (tests fonctionnels, POST scripté) ne doit pas déclencher d'erreur
$fichiers = ['flyer' => ['name' => '', 'size' => 0], 'image' => ['name' => '', 'size' => 0]];
$nouveaux_fichiers = ['flyer' => '', 'image' => ''];

if (!empty($_POST['formulaire']))
{
    if (!SecurityToken::check((string) ($_POST['token'] ?? ''), (string) ($_SESSION['token'] ?? '')))
    {
        $verif->setErreur("token", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

    $evenements = (array) ($_POST['evenements'] ?? []);

    if (count($evenements) === 0)
    {
        $verif->setErreur('evenements', "Aucun événement sélectionné");
    }

    foreach ($evenements as $idEven)
    {
        if (!is_numeric($idEven))
        {
            $verif->setErreur('evenements', "Un des ID d'événements choisi n'est pas un nombre");
            break;
        }
    }

    /*
     * SUPPRESSION DE LA SÉRIE SÉLECTIONNÉE
     */
    if (!empty($_POST['supprimerSerie']))
    {
        if ($verif->nbErreurs() === 0)
        {
            $nb_supprimes = 0;
            foreach ($evenements as $idEven)
            {
                $nb_supprimes += EvenementCollection::deleteEvenement((int) $idEven) ? 1 : 0;
            }

            $logger->info('[admin/events] suppression groupée', ['demandes' => count($evenements), 'supprimes' => $nb_supprimes, 'idP' => (int) ($_SESSION['SidPersonne'] ?? 0)]);

            // deleteEvenement() refuse à qui n'est ni auteur ni superadmin : le compte annoncé
            // est celui des suppressions effectives, pas celui des cases cochées
            $_SESSION['admin_events_flash_msg'] = $nb_supprimes . " événement(s) supprimé(s)"
                . ($nb_supprimes < count($evenements) ? ", " . (count($evenements) - $nb_supprimes) . " refusé(s) faute de droits" : "");

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    /*
     * REMPLACEMENT DES DONNÉES DE LA SÉRIE SÉLECTIONNÉE
     *
     * Seuls les champs non vides écrasent l'existant : c'est ce que la page annonce, et c'est
     * pourquoi la validation ne rend aucun champ obligatoire.
     */
    else
    {
        foreach ($champs as $c => $v)
        {
            if (isset($_POST[$c]))
            {
                $champs[$c] = trim((string) $_POST[$c]);
            }
        }

        $champs['organisateurs'] = array_filter(array_map('intval', (array) ($_POST['organisateurs'] ?? [])));

        $fichiers['flyer'] = $_FILES['flyer'] ?? $fichiers['flyer'];
        $fichiers['image'] = $_FILES['image'] ?? $fichiers['image'];

        /*
         * VERIFICATION DES CHAMPS ENVOYES par POST
         */
        $verif->valider($champs['genre'], "genre", "texte", 1, 200, 0);
        if (!empty($champs['genre']) && !array_key_exists($champs['genre'], $glo_tab_genre))
        {
            $verif->setErreur("genre", "Cette catégorie n'est pas valable");
        }

        if (!empty($champs['statut']) && !array_key_exists($champs['statut'], Evenement::$statuts_evenement))
        {
            $verif->setErreur("statut", "Ce statut n'est pas valable");
        }

        $verif->valider($champs['titre'], "titre", "texte", 1, 80, 0);
        $verif->valider($champs['nomLieu'], "nomLieu", "texte", 1, 80, 0);
        $verif->valider($champs['adresse'], "adresse", "texte", 2, 100, 0);

        if (!empty($champs['nomLieu']) && empty($champs['adresse']))
        {
            $verif->setErreur("adresse", "L'adresse est obligatoire");
        }

        if (!empty($champs['nomLieu']) && empty($champs['localite_id']))
        {
            $verif->setErreur("localite_id", "La localité est obligatoire");
        }

        if (!empty($champs['idLieu']) && ($champs['nomLieu'] != "" || $champs['adresse'] != ""))
        {
            $verif->setErreur('doublonLieux', 'Vous ne pouvez pas choisir 2 lieux');
        }

        // le select des lieux poste « idLieu_idSalle » quand une salle a été choisie
        if ($champs['idLieu'] != '' && preg_match("/^[0-9]+_[0-9]+$/", (string) $champs['idLieu']))
        {
            [$champs['idLieu'], $champs['idSalle']] = explode("_", (string) $champs['idLieu']);
        }
        else
        {
            $champs['idSalle'] = 0;
        }

        $verif->valider($champs['description'], "description", "texte", 4, 10000, 0);

        $verif->validerFichier($fichiers['flyer'], "flyer", $glo_mimes_images_acceptees, 0);
        $verif->validerFichier($fichiers['image'], "image", $glo_mimes_images_acceptees, 0);

        foreach (['horaire_debut', 'horaire_fin'] as $champ_horaire)
        {
            $verif->valider($champs[$champ_horaire], $champ_horaire, "texte", 1, 100, 0);
            if (!empty($champs[$champ_horaire]) && !preg_match("/^[0-9]{1,2}:[0-9]{2}$/", (string) $champs[$champ_horaire]))
            {
                $verif->setErreur($champ_horaire, "Mauvais format");
            }
        }

        $verif->valider($champs['horaire_complement'], "horaire_complement", "texte", 1, 100, 0);
        $verif->valider($champs['prix'], "prix", "texte", 1, 100, 0);
        $verif->valider($champs['prelocations'], "prelocations", "texte", 1, 100, 0);

        if ($verif->nbErreurs() === 0)
        {
            //creation/nettoyage des valeurs à insérer dans la table
            if ($champs['prix'] == "0")
            {
                $champs['prix'] = "entrée libre";
            }

            if ($champs['urlLieu'] != "" && !preg_match("/^https?:\/\//", (string) $champs['urlLieu']))
            {
                $champs['urlLieu'] = "http://".$champs['urlLieu'];
            }

            [$champs, $lieu_modifie] = Evenement::resolveLieuFields($champs);

            /**
             * Efface du disque le fichier que l'UPDATE s'apprête à remplacer.
             * À appeler tant que la base porte encore son nom, sinon il devient introuvable.
             *
             * @param string $colonne Nom de colonne littéral ('flyer' ou 'image'), jamais une saisie
             */
            $supprimerAncienFichier = function (string $colonne, int $idEvenement) use ($connector): void
            {
                $req = $connector->query("SELECT $colonne FROM evenement WHERE idEvenement=" . $idEvenement);

                if (!$req)
                {
                    return;
                }

                $ancien = $connector->fetchArray($req);

                if (!empty($ancien[$colonne]))
                {
                    Evenement::rmImageAndItsMiniature($ancien[$colonne]);
                }
            };

            // les horaires saisis en hh:mm se rapportent à la date de chaque événement traité :
            // ils sont reconvertis dans la boucle, à partir de la saisie conservée ici
            $horaires_saisis = ['horaire_debut' => $champs['horaire_debut'], 'horaire_fin' => $champs['horaire_fin']];

            // le premier événement traité porte les images redimensionnées ; les suivants en
            // reçoivent une copie, pour ne pas retraiter le même fichier N fois
            $sources_copiees = ['flyer' => '', 'image' => ''];
            $compteur_evenements = 0;
            $messages = [];

            foreach ($evenements as $idEven_courant)
            {
                $idEven_courant = (int) $idEven_courant;

                $req_even = $connector->query("SELECT idEvenement, titre, dateEvenement FROM evenement WHERE idEvenement=" . $idEven_courant);
                $tab_even = $connector->fetchArray($req_even);

                if (empty($tab_even))
                {
                    continue;
                }

                foreach ($horaires_saisis as $champ_horaire => $horaire_saisi)
                {
                    $champs[$champ_horaire] = empty($horaire_saisi)
                        ? ''
                        : Evenement::horaireToDatetime((string) $horaire_saisi, $tab_even['dateEvenement']);
                }

                $sql_fichiers = "";
                foreach (array_keys($nouveaux_fichiers) as $colonne)
                {
                    if (empty($fichiers[$colonne]['name']))
                    {
                        continue;
                    }

                    $suffixe = $colonne === 'image' ? "_img" : "";
                    $nouveaux_fichiers[$colonne] = $idEven_courant . "_" . $tab_even['dateEvenement'] . $suffixe . strrchr((string) $fichiers[$colonne]['name'], '.');

                    $supprimerAncienFichier($colonne, $idEven_courant);
                    $sql_fichiers .= ", " . $colonne . "='" . $connector->sanitize($nouveaux_fichiers[$colonne]) . "'";
                }

                $sql_update = "UPDATE evenement SET ";

                foreach ($champs as $c => $v)
                {
                    if ($c === "organisateurs")
                    {
                        continue;
                    }

                    if (!empty($v) || (in_array($c, $champs_du_lieu, true) && $lieu_modifie))
                    {
                        $sql_update .= $c."='".$connector->sanitize($v)."', ";
                    }
                }

                $sql_update .= "date_derniere_modif='".date("Y-m-d H:i:s")."'";
                $sql_update .= $sql_fichiers . " WHERE idEvenement=" . $idEven_courant;

                if (!$connector->query($sql_update))
                {
                    $messages[] = "La mise à jour de l'événement " . $idEven_courant . " a échoué";
                    continue;
                }

                /*
                 * Les organisateurs ne sont touchés que si le champ a été rempli : le vider
                 * n'est pas une demande de les retirer, c'est l'absence de demande — comme
                 * pour tous les autres champs de ce formulaire.
                 */
                if ($champs['organisateurs'] !== [])
                {
                    $connector->query("DELETE FROM evenement_organisateur WHERE idEvenement=" . $idEven_courant);

                    foreach (array_unique($champs['organisateurs']) as $idOrg)
                    {
                        $connector->query("INSERT INTO evenement_organisateur (idEvenement, idOrganisateur) VALUES (" . $idEven_courant . ", " . (int) $idOrg . ")");
                    }
                }

                /*
                 * TRAITEMENT DES IMAGES UPLOADEES
                 */
                foreach ($nouveaux_fichiers as $colonne => $nom_fichier)
                {
                    if (empty($fichiers[$colonne]['name']))
                    {
                        continue;
                    }

                    if ($compteur_evenements === 0)
                    {
                        $imD2 = new ImageDriver2("evenement");
                        $imD2->processImage($_FILES[$colonne], $nom_fichier, 600, 600);
                        $imD2->processImage($_FILES[$colonne], "s_" . $nom_fichier, 120, 190, '', 0);

                        $sources_copiees[$colonne] = $nom_fichier;
                    }
                    else
                    {
                        Evenement::safeCopyWithMiniature($sources_copiees[$colonne], $nom_fichier);
                    }
                }

                $messages[] = 'Mise à jour de <a href="/event/evenement.php?idE='.$idEven_courant.'">'.sanitizeForHtml($tab_even['titre']).'</a> le <a href="/index.php?courant='.sanitizeForHtml($tab_even['dateEvenement']).'">'.DateHelper::isoToFr($tab_even['dateEvenement'], 'annee').'</a> réussie';

                $compteur_evenements++;
            } // foreach

            $logger->info('[admin/events] remplacement groupé', ['nb' => $compteur_evenements, 'statut' => $champs['statut'], 'genre' => $champs['genre'], 'idLieu' => (int) $champs['idLieu'], 'idP' => (int) ($_SESSION['SidPersonne'] ?? 0)]);

            // redirection après traitement : un F5 ne rejoue plus le remplacement
            $_SESSION['admin_events_flash_msg'] = implode('<br>', $messages);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}


/*
 * LECTURE DE LA LISTE
 */
$tot_elements = EvenementCollection::countForAdmin($filtres);

$total_page_max = (int) ceil($tot_elements / $nblignes);
if ($total_page_max > 0 && $get['page'] > $total_page_max)
{
    $get['page'] = $total_page_max;
}

$tab_events = EvenementCollection::getForAdmin($filtres, $order_by, $order_dir, $get['page'], $nblignes);

// les organisateurs de tous les événements de la page, en une requête
$events_orgas = EvenementCollection::getOrganisateursParEvenement(array_column($tab_events, 'e_idEvenement'));


/*
 * RENDU
 *
 * Rien n'a été écrit jusqu'ici : c'est ce qui permet au traitement ci-dessus de rediriger
 * après un remplacement, plutôt que de laisser un F5 le rejouer.
 */
$page_titre = "Gérer les événements";
$extra_css = ["formulaires", "admin/tables"];
require_once '../_header.inc.php';

$erreurs = $verif->getErreurs();
?>

<main id="contenu" class="colonne">

	<header id="entete_contenu">
		<h1>Gérer les événements <?= sanitizeForHtml($titre_region) ?></h1>
        <div class="spacer"></div>
	</header>

    <?php if (!empty($_SESSION['admin_events_flash_msg'])) : ?>
        <?php // rendu sans échappement : le message porte des liens vers les événements traités,
              // dont les titres sont échappés à la construction ?>
        <?php HtmlShrink::msgOk($_SESSION['admin_events_flash_msg']); ?>
        <?php unset($_SESSION['admin_events_flash_msg']); ?>
    <?php endif; ?>

    <?php if ($erreurs !== []) : ?>
    <?php // les messages sont écrits ici, jamais repris d'une saisie : ils peuvent porter du HTML ?>
    <div class="msg_erreur" role="alert">
        <?php if (count($erreurs) === 1) : ?>
        <?= current($erreurs) ?>
        <?php else : ?>
        <p class="msg_erreur_titre"><?= $verif->getMsgNbErreurs() ?></p>
        <ul>
            <?php foreach ($erreurs as $message) : ?>
            <li><?= $message ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<section id="default">

    <div id="filters">

        <?php // les trois filtres se combinent : un même formulaire les poste ensemble ?>
        <form method="get" action="" id="events-filters">

            <span class="search-field">
                <input type="search" name="terme" value="<?= sanitizeForHtml($filtres['terme']) ?>" placeholder="Titre" size="20" />
                <button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
            </span>
            <span class="search-field">
                <input type="search" name="lieu" value="<?= sanitizeForHtml($filtres['lieu']) ?>" placeholder="Lieu" size="16" />
                <button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
            </span>
            <span class="search-field">
                <input type="search" name="personne" value="<?= sanitizeForHtml($filtres['personne']) ?>" placeholder="Pseudo ou email" size="16" />
                <button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
            </span>
            <input type="submit" name="submit" value="Filtrer" />

        </form>

        <ul class="menu_nb_res">
            <?php foreach ($tab_nblignes_even as $nbl) : ?>
            <li <?php if ($nblignes === $nbl) : ?>class="ici"<?php endif; ?>>
                <a href="?nblignes=<?= (int) $nbl ?>"><?= (int) $nbl ?></a>
            </li>
            <?php endforeach; ?>
        </ul>

    </div> <!-- #filters -->

    <?php // filtres, tri et nombre de lignes sont en session : la pagination n'a que la page à porter ?>
    <?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $nblignes, 1, "", "?page=") ?>

    <?php // l'avertissement autrefois affiché en tête de formulaire est posé ici : il arrive
          // au moment où il compte, et ne pousse plus les champs vers le bas du reste du temps ?>
    <form method="post" id="form-events-bulk" class="js-submit-freeze-wait" enctype="multipart/form-data" action=""
          data-confirm="Attention : toutes les données existantes seront écrasées. Seuls les champs non vides écrasent les champs existants.">

        <table id="ajouts" class="jquery-checkboxes table-hauteur-figee">

            <tr>
                <th></th>
                <?php foreach ($th_evenements as $field => $label) : ?>
                <th <?php if ($field === $order_by) : ?>class="ici"<?php endif; ?> <?php if ($field == 'horaire') : ?>style="width:100px"<?php endif; ?>>
                    <?php if (array_key_exists($field, EvenementCollection::ADMIN_ORDER_COLUMNS)) : ?>
                        <?php // cliquer sur la colonne déjà triée inverse le sens ?>
                        <a href="?order_by=<?= $field ?>&amp;order_dir=<?= $field === $order_by ? $order_dir_inverse : $order_dir ?>"><?= sanitizeForHtml($label) ?></a>
                        <?php if ($field === $order_by) : echo $icone[$order_dir]; endif; ?>
                    <?php else : ?>
                        <?= sanitizeForHtml($label) ?>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>

                <th></th>
            </tr>

            <?php foreach ($tab_events as $tab_even) :
                $even_lieu = Evenement::getLieu($tab_even);
                $datetime_dateajout = DateHelper::isoToApp($tab_even['e_dateAjout']);
                $tab_datetime_dateajout = explode(" ", (string) $datetime_dateajout);
                ?>
            <tr>
                <td style="text-align:center"><input type="checkbox" name="evenements[]" value="<?= (int) $tab_even['e_idEvenement'] ?>" /></td>
                <td style="text-align:left"><a href="/event/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" class='titre'><?= sanitizeForHtml($tab_even['e_titre']) ?></a></td>
                <td><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?><br><span style="color:lightsteelblue"><?= $even_lieu['localite'] ?></span></td>
                <td><a href="/index.php?courant=<?= sanitizeForHtml($tab_even['e_dateEvenement']) ?>"><?= DateHelper::isoToApp($tab_even['e_dateEvenement']) ?></a></td>
                <td><?= ucfirst(Evenement::genreLabel($tab_even['e_genre'])) ?></td>
                <td>
                    <?= EvenementRenderer::schedulesToHhMm($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']) ?>
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
                <td><a href="/user/dashboard.php?idP=<?= (int)$tab_even['idPersonne'] ?>"><?= sanitizeForHtml($tab_even['pseudo']) ?></a></td>
                <?php if ($_SESSION['Sgroupe'] <= UserLevel::ADMIN) : ?>
                    <td class="actions-even">
                        <?php if ($tab_even['e_statut'] != 'inactif') : ?>
                            <?= EvenementRenderer::unpublishLinkHtml((int) $tab_even['e_idEvenement'], $icone['depublier'], EvenementRenderer::UNPUBLISH_THEN_STATUS) ?>
                        <?php endif; ?>
                        <a href="/evenement-edit.php?action=editer&idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Modifier cet événement" aria-label="Modifier cet événement"><?= $iconeEditer ?></a>
                    </td>
                <?php endif; ?>
            </tr>

            <?php endforeach; ?>

        </table>

        <?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $nblignes, 1, "", "?page=") ?>

        <?= $verif->getHtmlErreur("evenements") ?>

        <h2 id="events-bulk-titre">Remplacer les données des événements sélectionnés ci-dessus par :</h2>

        <?php // l'avertissement d'écrasement n'est plus un pavé au-dessus du formulaire :
              // il est rappelé au moment où il compte, dans le confirm posé par data-confirm ?>

        <div id="ajouter_editer">

        <fieldset>
            <legend>Statut</legend>
            <ul class="radio mobile-vertical">
                <?php
                $statuts = ['actif' => 'publié', 'complet' => 'complet', 'annule' => 'annulé', 'inactif' => 'dépublié'];
                foreach ($statuts as $s => $n)
                {
                    $coche = $s === $champs['statut'] ? ' checked="checked"' : '';
                    echo '<li class="listehoriz">'
                        . '<input type="radio" name="statut" value="' . $s . '"' . $coche . ' id="statut_' . $s . '" title="statut de l\'événement" class="radio_horiz" />'
                        . '<label class="continu" for="statut_' . $s . '"><strong>' . $n . '</strong></label></li>';
                }
                ?>
                <li class="listehoriz events-bulk-supprimer">
                    <input type="checkbox" name="supprimerSerie" id="supprimerSerie" value="ok" class="checkbox"
                           data-confirm="Supprimer définitivement les événements sélectionnés, avec leurs images ? Cette action est irréversible." />
                    <label class="continu" for="supprimerSerie"><strong>Supprimer</strong></label>
                </li>
            </ul>
            <?= $verif->getHtmlErreur("statut") ?>
        </fieldset>

        <fieldset>
            <legend>Catégorie</legend>
            <ul class="radio mobile-vertical">
            <?php
            foreach ($glo_tab_genre as $na => $la)
            {
                $coche = $na === $champs['genre'] ? ' checked="checked"' : '';
                echo '<li class="listehoriz">'
                    . '<input type="radio" name="genre" value="' . $na . '"' . $coche . ' id="genre_' . $na . '" class="radio_horiz" />'
                    . '<label class="continu" for="genre_' . $na . '">' . sanitizeForHtml($la) . '</label></li>';
            }
            ?>
            </ul>
            <?= $verif->getHtmlErreur("genre") ?>
        </fieldset>

    <fieldset>
    <legend>Lieu</legend>
    <p>
    <label for="idLieu">Dans la liste :</label>

    <select name="idLieu" id="idLieu" class="js-select2-options-with-style" data-placeholder=""  style="max-width:350px">
        <?php
        // Les lieux fribourgeois restent proposés : l'administration édite aussi des
        // événements qui y sont rattachés.
        echo Lieu::getOptionsHtml($champs['idLieu'], $champs['idSalle'], exclureFribourg: false);
        ?>
    </select>
    <?php
    echo $verif->getHtmlErreur("idLieu");
    echo $verif->getHtmlErreur("dejaPresent");
    ?>
    </p>

    <?php // un lieu saisi à la main est l'exception : replié par défaut, déplié si la saisie
          // précédente en portait un, pour que l'erreur reste visible ?>
    <details class="events-bulk-repliable"<?= (!empty($champs['nomLieu']) || !empty($champs['adresse']) || !empty($champs['urlLieu'])) ? ' open' : '' ?>>
        <summary><strong>sinon</strong>, saisir un lieu absent de la liste</summary>

        <p>
        <?php
        $tab_nomLieu_label = ["for" => "nomLieu"];
        echo HtmlShrink::formLabel($tab_nomLieu_label, "Nom du lieu :");
        echo $verif->getHtmlErreur("nomLieuIdentique");

        $tab_nomLieu = ["type" => "text", "name" => "nomLieu", "id" => "nomLieu", "size" => "40", "maxlength" => "80", "value" => ""];
        if (empty($champs['idLieu']))
        {
            $tab_nomLieu['value'] = sanitizeForHtml($champs['nomLieu']);
        }
        echo HtmlShrink::formInput($tab_nomLieu);
        echo $verif->getHtmlErreur("nomLieu");
        ?>
        </p>

        <p>
        <label for="adresse">Adresse</label>
        <?php echo $verif->getHtmlErreur("adresseIdentique"); ?>
        <?php // pas de blanc entre l'attribut et la valeur : il repartirait dans la saisie ?>
        <input type="text" name="adresse" id="adresse" size="60" maxlength="100" title="rue, no" value="<?php if (empty($champs['idLieu'])) { echo sanitizeForHtml($champs['adresse']); } ?>" />
        <?php
        echo $verif->getHtmlErreur("adresse");
        echo $verif->getHtmlErreur("doublonLieux");
        ?>
        </p>

        <p>
        <label for="localite">Localité/quartier</label>
        <select name="localite_id" id="localite" class="js-select2-options-with-style" data-placeholder="" style="max-width:300px;">
            <?php
            // Le lieu choisi dans la liste porte déjà sa localité : comme le nom et l'adresse saisis
            // à la main, le select reste alors vide. Après une erreur de saisie, on réaffiche le
            // choix posté. Les localités fribourgeoises restent proposées : l'administration édite
            // aussi des événements qui y sont rattachés.
            $localite_selectionnee = (isset($_POST['localite_id']) || empty($champs['idLieu'])) ? $champs['localite_id'] : '';
            echo Localite::getOptionsHtml($localite_selectionnee, $champs['quartier'], exclureFribourg: false);
            ?>
        </select>
        <?php
        echo $verif->getHtmlErreur("localite_id");
        echo Localite::getAideChoixHtml();
        ?>
        </p>

        <p>
        <label for="urlLieu">URL</label>
        <input type="text" name="urlLieu" id="urlLieu" size="60" maxlength="80" title="url du lieu" value="<?php if (empty($champs['idLieu'])) { echo sanitizeForHtml($champs['urlLieu']); } ?>" />
        <?php echo $verif->getHtmlErreur("urlLieu"); ?>
        </p>

    </details>
    </fieldset>

    <fieldset>
    <legend>Horaire</legend>
    <p>
        <label for="horaire_debut">Début :</label>
        <input type="text" name="horaire_debut" id="horaire_debut" size="6" maxlength="100" title="début" value="<?php echo sanitizeForHtml($champs['horaire_debut']) ?>"  placeholder="hh:mm" />
        <?php echo $verif->getHtmlErreur('horaire_debut'); ?>
        <label for="horaire_fin" class="continu">Fin :</label>
        <input type="text" name="horaire_fin" id="horaire_fin" size="6" maxlength="100" title="fin" value="<?php echo sanitizeForHtml($champs['horaire_fin']) ?>" placeholder="hh:mm" />
        <?php echo $verif->getHtmlErreur('horaire_fin'); ?>
    </p>

    <p>
        <label for="horaire_complement">Complément :</label>
        <input type="text" name="horaire_complement" id="horaire_complement" size="60" maxlength="100" title="Précisions" value="<?php echo sanitizeForHtml($champs['horaire_complement']) ?>" />
        <?php echo $verif->getHtmlErreur('horaire_complement'); ?>
    </p>
    <div class="guideChamp">hh:mm (jusqu'à 06:00, le début sera considéré faisant partie du jour de l'événement)</div>

    </fieldset>

    <fieldset>
    <legend>Organisateur(s)</legend>
    <p>
        <label for="organisateurs">Organisateur(s)</label>
        <select name="organisateurs[]" id="organisateurs" class="js-select2-options-with-complement" multiple data-placeholder="Choisissez un ou plusieurs organisateurs" style="max-width:400px;">
        <?php echo Organisateur::getOptionsHtml($champs['organisateurs'] ?? []); ?>
        </select>
    </p>
    <div class="guideChamp">Laissé vide, ce champ ne touche pas aux organisateurs déjà rattachés.</div>
    </fieldset>

    <details class="events-bulk-repliable"<?= (!empty($champs['titre']) || !empty($champs['description']) || !empty($champs['ref']) || !empty($champs['prix']) || !empty($champs['prelocations'])) ? ' open' : '' ?>>
        <summary>Contenu de l'événement</summary>
        <fieldset>
        <p>
        <label for="titre">Titre</label>
        <input type="text" name="titre" id="titre" size="60" maxlength="80" title="titre de l'événement" value="<?php echo sanitizeForHtml($champs['titre']) ?>" />
        <?php echo $verif->getHtmlErreur("titre"); ?>
        </p>

        <p>
            <label for="description">Description</label>
            <?php // pas de saut de ligne entre la balise ouvrante et la valeur : un textarea rend son contenu littéralement ?>
            <textarea name="description" id="description" cols="50" rows="8" title="description de l'événement"><?php echo sanitizeForHtml($champs['description']) ?></textarea>
            <?php echo $verif->getHtmlErreur('description'); ?>
        </p>

        <p>
            <label for="ref">Références</label>
            <input type="text" name="ref" id="ref" size="60" maxlength="100" title="Organisateur, site web de l'événement, contact..." value="<?php echo sanitizeForHtml($champs['ref']); ?>" />
        </p>
        <div class="guideChamp">Indiquez ici les sites web de l'événement ou des organisateurs.</div>

        <p>
            <label for="prix">Prix :</label>
            <input type="text" name="prix" id="prix" size="60" title="Tarifs d'entrée" value="<?php echo sanitizeForHtml($champs['prix']) ?>" />
            <?php echo $verif->getHtmlErreur('prix'); ?>
        </p>
        <div class="guideChamp">Vous pouvez mettre <b>0</b> si l'entrée est libre.</div>

        <p>
            <label for="prelocations">Prélocs :</label>
            <input type="text" name="prelocations" id="prelocations" size="60" maxlength="100" title="Où acheter les billets" value="<?php echo sanitizeForHtml($champs['prelocations']) ?>" />
            <?php echo $verif->getHtmlErreur('prelocations'); ?>
        </p>
        </fieldset>
    </details>

    <details class="events-bulk-repliable">
        <summary>Fichiers</summary>
        <fieldset>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo UPLOAD_MAX_FILESIZE ?>" />

        <p>
        <label for="flyer">Flyer :</label>
        <input type="file" name="flyer" id="flyer" class="js-file-upload-size-max fichier" size="25"
        accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" />
        </p>
        <?php
        // Aucun aperçu ni case « Supprimer » ici, contrairement à evenement-edit.php : le
        // formulaire porte sur N événements, qui n'ont pas le même flyer à montrer.
        echo $verif->getHtmlErreur("flyer");
        ?>

        <p>
        <label for="image">Image :</label>
        <input type="file" name="image" id="image" class="js-file-upload-size-max fichier" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" />
        </p>
        <?php echo $verif->getHtmlErreur("image"); ?>
        <div class="guideChamp">Formats JPEG, PNG, GIF ou WebP; max. 2 Mo. La même image est posée sur tous les événements sélectionnés.</div>
        </fieldset>
    </details>




        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="hidden" name="token" value="<?= sanitizeForHtml(SecurityToken::getToken()) ?>" />
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
