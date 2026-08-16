<?php

use Ladecadanse\Evenement;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\Text;
use Ladecadanse\Lieu;

/*
 * Validation du paramètre `type` AVANT le chargement de l'application : les scans et les flux
 * retirés représentent la moitié du trafic de ce script, et bootstrap.php ouvre deux connexions
 * à la base, démarre la session et monte les gestionnaires de log. Rien de tout cela n'est
 * nécessaire pour répondre 400 ou 410.
 */

// Flux retirés du site. Le 410 (et non 404) signale une suppression définitive : les lecteurs
// marquent l'abonnement en erreur, les crawlers retirent l'URL de leur index.
// Ajouter un type retiré ne demande qu'une ligne.
const RSS_TYPES_RETIRES = [
    'evenement_commentaires' => "Ce flux a été supprimé en même temps que les commentaires du site.",
];

const RSS_TYPES_VALIDES = ['evenements_auj', 'lieu_evenements', 'organisateur_evenements', 'evenements_ajoutes'];

// Flux portant sur une entité précise, qui attendent un identifiant.
const RSS_TYPES_AVEC_ID = ['lieu_evenements', 'organisateur_evenements'];

// Durée de vie du cache. Le conditional GET ne réduit pas la fréquence d'interrogation des
// lecteurs, il réduit le coût de chaque visite : un 304 sans corps, sans requête SQL, sans rendu.
const RSS_CACHE_TTL = 900;

/**
 * Réponse d'erreur minimale, émise avant tout chargement de l'application.
 *
 * Ne jamais réémettre la valeur reçue : le paramètre `type` est la cible d'un fuzzing constant
 * et un endpoint qui réagit aux sondes continue d'attirer les scanners.
 */
function rssStop(int $code, string $message, int $maxAge = 0): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: ' . ($maxAge > 0 ? "public, max-age=$maxAge" : 'no-store'));
    echo $message . "\n";
    exit;
}

/**
 * Chemin du fichier de cache. Un couple type + identifiant suffit comme clé : depuis que le flux
 * s'appuie sur SITE_CANONICAL_URL, sa sortie ne dépend plus de l'hôte par lequel on l'appelle.
 */
function rssCheminCache(string $type, int $id): string
{
    return __DIR__ . '/../var/cache/rss/' . $type . '-' . $id . '.xml';
}

/**
 * Le lecteur a-t-il déjà cette version ? L'ETag prime sur la date, conformément à la RFC 9110.
 */
function rssClientAJour(string $etag, int $mtime): bool
{
    $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');

    if ($ifNoneMatch !== '')
    {
        $nu = trim($etag, '"');

        foreach (explode(',', $ifNoneMatch) as $candidat)
        {
            // mod_deflate suffixe l'ETag des réponses compressées par « -gzip »
            $candidat = trim(trim($candidat), '"');
            $candidat = preg_replace('/^W\/"?|-(gzip|br)$/', '', $candidat);

            if ($candidat === $nu)
            {
                return true;
            }
        }

        // un ETag fourni mais différent tranche : ne pas retomber sur la date
        return false;
    }

    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

    return $ifModifiedSince !== '' && ($date = strtotime($ifModifiedSince)) !== false && $date >= $mtime;
}

/**
 * Date du contenu, lue dans le pubDate du channel (le premier du document).
 *
 * Volontairement pas filemtime() : le cache est réécrit toutes les quinze minutes même quand
 * rien n'a changé, et un Last-Modified qui avance alors ferait répondre 200 à tous les lecteurs
 * n'envoyant que If-Modified-Since. Adossés au contenu, les deux validateurs restent stables
 * tant que l'agenda ne bouge pas, ce qui est précisément le cas que l'on veut servir en 304.
 */
function rssDateContenu(string $xml): int
{
    if (preg_match('#<pubDate>(.*?)</pubDate>#', $xml, $correspondances))
    {
        $date = strtotime(trim($correspondances[1]));

        if ($date !== false)
        {
            return $date;
        }
    }

    return time();
}

/**
 * Émet le flux, ou un 304 sans corps si le lecteur a déjà cette version.
 */
function rssServir(string $xml, string $type): never
{
    $etag = '"' . hash('xxh128', $xml) . '"';
    $mtime = rssDateContenu($xml);

    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    // bootstrap.php impose no-store à toute réponse, ce qui n'a pas de sens pour un flux public
    header('Cache-Control: public, max-age=' . RSS_CACHE_TTL);
    header_remove('Pragma');

    if (rssClientAJour($etag, $mtime))
    {
        http_response_code(304);
        exit;
    }

    header('Content-Type: text/xml; charset=utf-8');
    header('Content-Disposition: inline; filename=' . $type . '.xml');
    header('Content-Length: ' . strlen($xml));
    echo $xml;
    exit;
}

/**
 * Écriture atomique : fichier temporaire puis rename(). Pas de verrou ni d'anti-stampede, à une
 * requête toutes les trente secondes la régénération concurrente est sans conséquence.
 *
 * @return bool false si le cache est indisponible ; l'appelant sert alors sans cache
 */
function rssEcrireCache(string $fichier, string $xml): bool
{
    $repertoire = dirname($fichier);

    if (!is_dir($repertoire) && !@mkdir($repertoire, 0o775, true) && !is_dir($repertoire))
    {
        return false;
    }

    $temporaire = $fichier . '.' . getmypid() . '.tmp';

    if (@file_put_contents($temporaire, $xml) === false)
    {
        return false;
    }

    if (!@rename($temporaire, $fichier))
    {
        @unlink($temporaire);

        return false;
    }

    return true;
}

// `?type[]=x` fournirait un tableau, qui lèverait une erreur en clé de tableau plus bas
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';

if (isset(RSS_TYPES_RETIRES[$type]))
{
    // 30 jours : la réponse ne changera jamais
    rssStop(410, RSS_TYPES_RETIRES[$type], 2592000);
}

if (!in_array($type, RSS_TYPES_VALIDES, true))
{
    rssStop(400, "Paramètre type absent ou inconnu.");
}

$id = 0;
if (in_array($type, RSS_TYPES_AVEC_ID, true))
{
    // is_numeric() acceptait « 1e3 », « 1.9 » ou « 12 » avec une espace initiale
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id)
    {
        rssStop(400, "Paramètre id absent ou invalide.");
    }
}

$get = ['type' => $type, 'id' => $id];

// Cache encore valide : on répond sans charger l'application du tout.
$fichierCache = rssCheminCache($type, $id);
$mtimeCache = @filemtime($fichierCache);

if ($mtimeCache !== false && $mtimeCache + RSS_CACHE_TTL > time())
{
    $xmlCache = @file_get_contents($fichierCache);

    if ($xmlCache !== false)
    {
        rssServir($xmlCache, $type);
    }
}

global $glo_auj_6h, $connector, $auj, $glo_tab_genre;
require_once("../app/bootstrap.php");


// Tout ce qui est émis dans le flux s'appuie sur SITE_CANONICAL_URL et non sur $site_full_url :
// un lecteur enregistre ces URL durablement, elles ne doivent pas dépendre de l'hôte par lequel
// la requête est arrivée.
$channel = ['link' => SITE_CANONICAL_URL . '/', 'description' => ''];

$join = "";
$where = " WHERE e.statut NOT IN ('inactif', 'propose') ";
$params = [];

switch($get['type'])
{
    case "evenements_auj":

        $channel['title'] = "La décadanse : événements aujourd'hui";
        $channel['description'] = "Les événements culturels de la journée à Genève et alentours.";

        $where .= " AND dateEvenement=?";
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
        $channel['description'] = "Les prochains événements annoncés dans ce lieu.";
        $channel['link'] .= 'lieu/lieu.php?idL='.$get['id'];

        $where .= " AND e.idLieu = ? AND dateEvenement >= ?";
        $params = [$get['id'], $glo_auj_6h];
        $order_by = " ORDER BY e.dateAjout DESC";

        break;

    case "organisateur_evenements":

        $channel['title'] = 'La décadanse : prochains événements organisateur';
        $channel['description'] = "Les prochains événements annoncés par cet organisateur.";
        $channel['link'] .= 'organisateur/organisateur.php?idO='.$get['id'];

        $join = ' LEFT JOIN evenement_organisateur eo ON e.idEvenement = eo.idEvenement ';
        $where .= " AND eo.idOrganisateur = ? AND dateEvenement >= ?";
        $params = [$get['id'], $glo_auj_6h];
        $order_by = " ORDER BY e.dateAjout DESC";

        break;

    case "evenements_ajoutes":

        $channel['title'] = "La décadanse : derniers événements ajoutés";
        $channel['description'] = "Les derniers événements ajoutés à l'agenda de La décadanse.";

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
$derniere_maj = null;
foreach ($tab_events as $tab_even)
{
    // réinitialisation indispensable : sans elle, un événement sans flyer ni image héritait de
    // l'illustration de l'événement précédent
    $item = [];

    $even_lieu = Evenement::getLieu($tab_even);

    $item['title'] = ucfirst((string) DateHelper::isoToFr($tab_even['e_dateEvenement'], html: false))." - ".$tab_even['e_genre']." : ".$tab_even['e_titre'];
    $item['link'] = SITE_CANONICAL_URL."/event/evenement.php?idE=".(int)$tab_even['e_idEvenement'];

     // item > description
    $item['nom_lieu'] = Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle'], SITE_CANONICAL_URL);
    // complete channel title for feed type "lieu_evenements"
    $title_lieu = $even_lieu['nom'];

    if (!empty($tab_even['e_flyer']))
    {
        $item['image'] = $assets->get(Evenement::getAssetPath(Evenement::getFilePath($tab_even['e_flyer'])));
    }
    elseif (!empty($tab_even['e_image']))
    {
        $item['image'] = $assets->get(Evenement::getAssetPath(Evenement::getFilePath($tab_even['e_image'])));
    }

    $item['description'] = Text::shortenToHtml($tab_even['e_description'], 60 * 5);
    $item['horaire'] = EvenementRenderer::schedulesToHhMm($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement'])." ".$tab_even['e_horaire_complement'];
    $item['prix'] =  $tab_even['e_prix'];

    $item['guid'] = (int)$tab_even['e_idEvenement'];
    $date_ajout = new \DateTime($tab_even['e_dateAjout']);
    $item['pubDate'] = $date_ajout->format(\DateTime::RFC2822);

    // le plus récent des ajouts, et non le dernier parcouru : l'affectation avait lieu dans la
    // boucle, la date du channel valait donc celle de l'item le plus ancien
    if ($derniere_maj === null || $date_ajout > $derniere_maj)
    {
        $derniere_maj = $date_ajout;
    }

    $items[] = $item;
}

// un flux vide reste daté de l'instant : le champ est obligatoire et doit rester en RFC 822
$channel['pubDate'] = ($derniere_maj ?? new \DateTime())->format(\DateTime::RFC2822);

if ($get['type'] == 'lieu_evenements')
{
    $channel['title'] .= ' de '.$title_lieu;
}

// TODO: $channel['title'] .= ' de '.$organisateur;

// URL canonique du flux, reconstruite à partir des paramètres validés. $_SERVER['REQUEST_URI']
// reflétait tout paramètre parasite, donnant un rel="self" différent par variante d'URL.
$channel['self'] = SITE_CANONICAL_URL . '/event/rss.php?type=' . $get['type']
    . (in_array($get['type'], RSS_TYPES_AVEC_ID, true) ? '&amp;id=' . $get['id'] : '');

$xml = rssGenerer($channel, $items);

// un cache indisponible (répertoire non inscriptible en mutualisé, par exemple) ne doit pas
// empêcher de répondre : on sert quand même, le conditional GET restant opérant puisque ses deux
// validateurs sont tirés du contenu et non du fichier
rssEcrireCache($fichierCache, $xml);

rssServir($xml, $get['type']);

/**
 * Rend le flux et le retourne, au lieu de l'émettre : le résultat doit pouvoir être mis en cache.
 */
function rssGenerer(array $channel, array $items): string
{
    ob_start();
    echo '<?xml version="1.0" encoding="utf-8" ?>';
    ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="<?= $channel['self'] ?>" rel="self" type="application/rss+xml" />
        <title><?= sanitizeForHtml($channel['title']) ?></title>
        <link><?= sanitizeForHtml($channel['link']) ?></link>
        <description><?= sanitizeForHtml($channel['description']) ?></description>
        <ttl>1440</ttl>
        <pubDate><?= $channel['pubDate'] ?></pubDate>
        <lastBuildDate><?= $channel['pubDate'] ?></lastBuildDate>
        <language>fr</language>

        <?php foreach ($items as $item) : ?>
        <item>
            <title><?= sanitizeForHtml($item['title']) ?></title>
            <link><?= sanitizeForHtml($item['link']) ?></link>
            <description>
                <![CDATA[
                    <h2><?= $item['nom_lieu'] ?></h2>
                    <?php if (!empty($item['image'])) : ?>
                        <figure class="flyer" style="float:left">
                            <img src="<?= SITE_CANONICAL_URL.$item['image'] ?>" alt="Affiche ou illustration de <?= sanitizeForHtml($item['title']) ?>" width="300">
                        </figure>
                    <?php endif; ?>
                    <p><?= $item['description'] ?></p>
                    <p><?= sanitizeForHtml($item['horaire']) ?></p>
                    <p><?= sanitizeForHtml($item['prix']) ?></p>
                ]]>
            </description>
            <pubDate><?= $item['pubDate'] ?></pubDate>
            <guid isPermaLink="false"><?= $item['guid'] ?></guid>
        </item>
        <?php endforeach; ?>

    </channel>
</rss>
    <?php
    return (string) ob_get_clean();
}
