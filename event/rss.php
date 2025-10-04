<?php

global $site_full_url, $glo_auj_6h, $connector, $auj, $glo_tab_genre;
require_once("../app/bootstrap.php");

use Ladecadanse\Evenement;
use Ladecadanse\Utils\Text;
use Ladecadanse\Lieu;

$tab_feeds_types = ["evenements_auj", "lieu_evenements", 'organisateur_evenements', 'evenements_ajoutes'];

$get['type'] = '';
if (!in_array($_GET['type'], $tab_feeds_types))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}
$get['type'] = $_GET['type'];

if (in_array($get['type'], ["lieu_evenements", 'organisateur_evenements']) && empty($_GET['id']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}

$get['id'] = '';
if (isset($_GET['id']))
{
    if (!is_numeric($_GET['id']))
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
    }

    $get['id'] = (int) $_GET['id'];
}


$channel = ['link' => $site_full_url, 'pubDate' => time()];

$join = "";
$where = "";
$params = [];

switch($get['type'])
{
    case "evenements_auj":

        $channel['title'] = "La décadanse : événements aujourd'hui";

        $where = " WHERE dateEvenement=?";
        $params = [$glo_auj_6h];
        $order_by = " ORDER BY CASE e.genre
            WHEN 'fête' THEN 1
            WHEN 'cinéma' THEN 2
            WHEN 'théâtre' THEN 3
            WHEN 'expos' THEN 4
            WHEN 'divers' THEN 5
          END, e.dateAjout DESC";

        break;

    case "lieu_evenements":

        $channel['title'] = 'La décadanse : prochains événements';
        $channel['link'] .= '/lieu/lieu.php?idL='.(int)$get['id'];

        $where = " WHERE e.idLieu = ? AND dateEvenement >= ?";
        $params = [$get['id'], $glo_auj_6h];
        $order_by = " ORDER BY e.dateAjout DESC";

        break;

    case "organisateur_evenements":

        $channel['title'] = 'La décadanse : prochains événements organisateur';
        $channel['link'] .= 'organisateur.php?idO='.(int)$get['id'];

        $join = ' LEFT JOIN evenement_organisateur eo ON e.idEvenement = eo.idEvenement ';
        $where = " WHERE eo.idOrganisateur = ? AND dateEvenement >= ?";
        $params = [$get['id'], $glo_auj_6h];
        $order_by = " ORDER BY e.dateAjout DESC";

        break;

    case "evenements_ajoutes":

        $channel['title'] = "La décadanse : derniers événements ajoutés";

        $order_by = " ORDER BY e.dateAjout DESC LIMIT 0, 20";

        break;

    default:
        header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
        exit;
}

$sql_events = "SELECT

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
  s.nom AS s_nom

FROM evenement e
JOIN localite loc ON e.localite_id = loc.id
LEFT JOIN lieu l ON e.idLieu = l.idLieu
LEFT JOIN localite lloc ON l.localite_id = lloc.id
LEFT JOIN salle s ON e.idSalle = s.idSalle

$join

$where $order_by";

//echo $sql_events;

$stmt = $connectorPdo->prepare($sql_events);
$stmt->execute($params);
$tab_events = $stmt->fetchAll(PDO::FETCH_ASSOC);


$items = [];
$title_lieu = '';
foreach ($tab_events as $tab_even)
{
    $even_lieu = Evenement::getLieu($tab_even);

    $item['title'] = ucfirst((string) date_fr($tab_even['e_dateEvenement'], "", "", "", false))." - ".$tab_even['e_genre']." : ".$tab_even['e_titre'];
    $item['link'] = $site_full_url."event/evenement.php?idE=".(int)$tab_even['e_idEvenement'];

     // item > description
    $item['nom_lieu'] = Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']);
    // complete channel title for feed type "lieu_evenements"
    $title_lieu = $even_lieu['nom'];

    if (!empty($tab_even['e_flyer']))
    {
        $item['image'] = Evenement::getWebPath(Evenement::getFilePath($tab_even['e_flyer']), true);
    }
    elseif (!empty($tab_even['e_image']))
    {
        $item['image'] = Evenement::getWebPath(Evenement::getFilePath($tab_even['e_image']), true);
    }

    $item['description'] = Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['e_description'])), Text::trouveMaxChar($tab_even['e_description'], 60, 5), "");
    $item['horaire'] = afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement'])." ".$tab_even['e_horaire_complement'];
    $item['prix'] =  $tab_even['e_prix'];

    $item['guid'] = (int)$tab_even['e_idEvenement'];
    $item['pubDate'] = date("r", datetime_iso2time($tab_even['e_dateAjout']));

    $items[] = $item;
    $channel['pubDate'] = date_iso2time($tab_even['e_dateAjout']);
}

if ($get['type'] == 'lieu_evenements')
{
    $channel['title'] .= ' de '.$title_lieu;
}

// TODO: $channel['title'] .= ' de '.$organisateur;

header('Content-Disposition: inline; filename=' . $get['type'] . '.xml');
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="utf-8" ?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="<?= sanitizeForHtml($site_full_url.ltrim($_SERVER['REQUEST_URI'], '/')) ?>" rel="self" type="application/rss+xml" />
        <title><?= sanitizeForHtml($channel['title']) ?></title>
        <link><?= sanitizeForHtml($channel['link']) ?></link>
        <description></description>
        <ttl>1440</ttl>
        <pubDate><?= $channel['pubDate'] ?></pubDate>
        <language>fr</language>

        <?php foreach ($items as $item) : ?>
        <item>
            <title><?= sanitizeForHtml($item['title']) ?></title>
            <link><?= sanitizeForHtml($item['link']) ?></link>
            <description>
                <![CDATA[
                    <style>
                        .flyer { float:left; }
                        h1, h2, h3, h4, p { margin: 1em 0.1em 0.6em 0.1em }
                        h2 { font-size:1.2em; padding:0.2em 0.1em;border-bottom:1px solid #aeaeae; }
                        .desc { margin: 0.4em 0.2em }
                        .clean { clear:both; }
                    </style>
                    <h2><?= $item['nom_lieu'] ?></h2> <!-- relative url -->
                    <?php if (!empty($item['image'])) : ?>
                        <figure class="flyer">
                            <img src="<?= $site_full_url.$item['image'] ?>" alt="Affiche ou illustration de <?= sanitizeForHtml($item['title']) ?>" width="300">
                        </figure>
                    <?php endif; ?>
                    <p><?= $item['description'] ?></p>
                    <p><?= sanitizeForHtml($item['horaire']) ?></p>
                    <p><?= sanitizeForHtml($item['prix']) ?></p>
                ]]>
            </description>
            <guid isPermaLink="false"><?= $item['guid'] ?></guid>
        </item>
        <?php endforeach; ?>

    </channel>
</rss>
