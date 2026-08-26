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

$get['page'] = QueryParamValidator::pageFromQuery($_GET['page'] ?? '');

$th_evenements = ["titre" => "Titre", "idLieu" => "Lieu", "dateEvenement" => "Date", "genre" => "Catég.", "horaire" => "Horaire", "organisateurs" => "Orga.", "statut" => "Statut", "dateAjout" => "Ajouté", "pseudo" => "par"];

$orderByColumns = [
	"dateAjout"           => "e.dateAjout",
	"date_derniere_modif" => "e.date_derniere_modif",
	"statut"              => "e.statut",
	"dateEvenement"       => "e.dateEvenement",
	"titre"               => "e.titre",
	"genre"               => "e.genre",
];

$get['tri_gerer'] = "dateAjout";
if (isset($_GET['tri_gerer']) && array_key_exists($_GET['tri_gerer'], $orderByColumns))
{
	$get['tri_gerer'] = $_GET['tri_gerer'];
}

$orderDirections = ["asc" => "ASC", "desc" => "DESC"];
$get['ordre'] = "desc";
$ordre_inverse = "asc";
if (isset($_GET['ordre']))
{
	$get['ordre'] = QueryParamValidator::validateUrlQueryValue($_GET['ordre'], "enum", 1, array_keys($orderDirections));
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
	$get['nblignes'] = QueryParamValidator::validateUrlQueryValue($_GET['nblignes'], "int", 1);
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
            foreach ($evenements as $idEven)
            {
                EvenementCollection::deleteEvenement((int) $idEven);
            }

            $logger->info('[admin/events] suppression groupée', ['nb' => count($evenements), 'idP' => (int) ($_SESSION['SidPersonne'] ?? 0)]);

            HtmlShrink::msgOk(count($evenements) . " événement(s) supprimé(s)");
            $evenements = [];
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
                    HtmlShrink::msgErreur("La mise à jour de l'événement " . $idEven_courant . " a échoué");
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

                HtmlShrink::msgOk('Mise à jour de <a href="/event/evenement.php?idE='.$idEven_courant.'">'.sanitizeForHtml($tab_even['titre']).'</a> le <a href="/index.php?courant='.sanitizeForHtml($tab_even['dateEvenement']).'">'.DateHelper::isoToFr($tab_even['dateEvenement'], 'annee').'</a> réussie');

                $compteur_evenements++;
            } // foreach

            $logger->info('[admin/events] remplacement groupé', ['nb' => $compteur_evenements, 'statut' => $champs['statut'], 'genre' => $champs['genre'], 'idLieu' => (int) $champs['idLieu'], 'idP' => (int) ($_SESSION['SidPersonne'] ?? 0)]);

            // le formulaire se réaffiche vide : les valeurs viennent d'être appliquées
            $champs = array_fill_keys(array_keys($champs), '');
            $evenements = [];
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
ORDER BY ".$orderByColumns[$get['tri_gerer']]." ".($orderDirections[$get['ordre']] ?? 'DESC')." LIMIT ".(int)($sql_page - 1) * (int)$get['nblignes'].",".(int)$get['nblignes'];

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

            <span class="search-field">
                <input type="search" name="terme" value="<?= sanitizeForHtml($get['terme']); ?>" placeholder="Titre" size="24" />
                <button type="button" class="js-clear-search-field" aria-label="Vider et relancer la recherche" title="Vider et relancer la recherche"></button>
            </span>
            <input type="submit" name="submit" value="Filtrer" />

        </form>

        <ul class="menu_filtre" style="float:left;width:50%;margin:0">
            <li <?php if ($get['filtre_genre'] == 'tous') : ?>class="ici"<?php endif; ?>>
                <a href="?<?= HtmlShrink::urlQueryArrayToString($get, ['filtre_genre', 'page'])?>&amp;filtre_genre=tous">Tous</a></li>
            <?php foreach ($glo_tab_genre as $ng => $nl) : ?>
                <li <?php if ($get['filtre_genre'] == $ng) : ?> class="ici"<?php endif; ?>>
                    <a href="?<?= HtmlShrink::urlQueryArrayToString($get, ['filtre_genre', 'page']) ?>&amp;filtre_genre=<?= $ng ?>"><?= ucfirst($nl) ?></a>
                </li>
              <?php endforeach; ?>
        </ul>

        <div class="spacer"></div>


        <div id="gerer-even-pagination">
            <?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&filtre_genre=" . $get['filtre_genre'] . "&terme=" . $get['terme'] . "&page=") ?>

                <ul class="menu_nb_res" style="float:right;margin: 1em auto 1.4em;width:35%;text-align:right">
                <?php foreach ($tab_nblignes as $nbl) : ?>
                <li <?php if ($get['nblignes'] == $nbl) { echo 'class="ici"'; } ?>>
                    <a href="?<?= HtmlShrink::urlQueryArrayToString($get, "nblignes")?>&amp;nblignes=<?= (int)$nbl ?>"><?= (int)$nbl ?></a>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="spacer"></div>
        </div>

        <div class="spacer"></div>

    </div>

    <form method="post" id="formGererEvenements" class='js-submit-freeze-wait' enctype="multipart/form-data" action="">

        <table id="ajouts" class="jquery-checkboxes table-hauteur-figee">

            <tr>
                <th></th>
                <?php foreach ($th_evenements as $field => $label) : ?>
                <th <?php if ($field == $get['tri_gerer']) : ?>class="ici"<?php endif; ?> <?php if ($field == 'horaire') : ?>style="width:100px"<?php endif; ?>>
                    <?php if (array_key_exists($field, $orderByColumns)) : ?>
                        <a href="?<?= HtmlShrink::urlQueryArrayToString($get, ['tri_gerer', 'ordre'])."&amp;tri_gerer=".$field."&amp;ordre=".$ordre_inverse ?>"><?= sanitizeForHtml($label) ?></a>
                        <?php if ($field == $get['tri_gerer']) : echo $icone[$get['ordre']]; endif; ?>
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

        <?= HtmlShrink::getPaginationString($tot_elements, $get['page'], $get['nblignes'], 1, "", "?element=" . $get['element'] . "&tri_gerer=" . $get['tri_gerer'] . "&ordre=" . $get['ordre'] . "&nblignes=" . $get['nblignes'] . "&filtre_genre=" . $get['filtre_genre'] . "&terme=" . $get['terme'] . "&page=") ?>

        <?= $verif->getHtmlErreur("evenements") ?>

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
            echo $verif->getHtmlErreur("statut");
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
                if ($na === $champs['genre'])
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
            echo $verif->getHtmlErreur("genre");
            ?>
        </fieldset>

    <fieldset>
    <legend>Lieu*</legend>
    <p>
    <label for="lieu">Dans la liste :</label>

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

    <p class="entreLabels"><strong>sinon</strong></p>
    <div class="spacer"></div>

    <p>
    <?php
    $tab_nomLieu_label = ["for" => "nomLieu"];
    echo HtmlShrink::formLabel($tab_nomLieu_label, "Nom du lieu :");
    echo $verif->getHtmlErreur("nomLieuIdentique");

    $tab_nomLieu = ["type" => "text", "name" => "nomLieu", "id" => "nomLieu", "size" => "40", "maxlength" => "80", "tabindex" => "9", "value" => ""];
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
    <?php
    echo $verif->getHtmlErreur("adresseIdentique");
    ?>

    <input type="text" name="adresse" id="adresse" size="60" maxlength="100" title="rue, no" tabindex="10" value="
           <?php if (empty($champs['idLieu']))
           {
               echo sanitizeForHtml($champs['adresse']);
           } ?>" />
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
    <input type="text" name="urlLieu" id="urlLieu" size="60" maxlength="80" title="url du lieu" tabindex="9" value="
           <?php if (empty($champs['idLieu']))
           {
               echo sanitizeForHtml($champs['urlLieu']);
           } ?>" />
    <?php
    echo $verif->getHtmlErreur("urlLieu");
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
    echo $verif->getHtmlErreur("titre");
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
        <?php echo Organisateur::getOptionsHtml($champs['organisateurs'] ?? []); ?>
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
    <input type="file" name="flyer" id="flyer" class="js-file-upload-size-max fichier" size="25"
    accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" tabindex="12" />
    </p>

    <div class="spacer"></div>
    <?php
    // Aucun aperçu ni case « Supprimer » ici, contrairement à evenement-edit.php : le
    // formulaire porte sur N événements, qui n'ont pas le même flyer à montrer.
    echo $verif->getHtmlErreur("flyer");
    ?>

        <p>
        <label for="image">Image :</label>
        <input type="file" name="image" id="image" class="js-file-upload-size-max fichier" size="25" accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" />
        </p>
        <div class="guideChamp">Formats JPEG, PNG, GIF ou WebP; max. 2 Mo</div>
    <div class="spacer"></div>
    <?php
    echo $verif->getHtmlErreur("image");
    ?>
    </fieldset>




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
