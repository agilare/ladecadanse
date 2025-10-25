<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\Utils\Text;
use PDO;
use Ladecadanse\HasDocuments;

class Organisateur extends Element
{
    use HasDocuments;

    public static $systemDirPath;
    public static $urlDirPath;

    public const int LOW_ACTIVITY_MONTHS_NB = 6;
    public const int VERY_LOW_ACTIVITY_MONTHS_NB = 12;
    public const int RESULTS_PER_PAGE = 100;

    function __construct()
	{
        parent::__construct();
		$this->table = "organisateur";
	}


    public static function getListLinkedHtml(array $organisateurs, bool $isWithOrganisateurUrl = true): string
    {
        ob_start();
        ?>
        <ul class="event_orga" aria-label="Organisateurs">
            <?php foreach ($organisateurs as $eo) : ?>
                <li>
                    <a href="/organisateur/organisateur.php?idO=<?= (int) $eo['idOrganisateur']; ?>"><?= sanitizeForHtml($eo['nom']); ?></a>
                        <?php if ($isWithOrganisateurUrl && !empty($eo['url'])) { $organisateurUrl = Text::getUrlWithName($eo['url']); ?> -&nbsp;<a href="<?= sanitizeForHtml($organisateurUrl['url']); ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?= sanitizeForHtml($organisateurUrl['urlName']); ?></a>
                    <?php } ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    public static function getActivesLieux(int $idOrga): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT l.idLieu AS idLieu, l.nom AS nom
            FROM lieu_organisateur lo
            JOIN lieu l ON lo.idLieu = l.idLieu AND l.statut = 'actif'
            WHERE lo.idOrganisateur=?");
        $stmt->execute([$idOrga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function getOrganisateurs(array $filters, string $order = 'date_ajout', ?int $page = 1): array
    {
        global $connectorPdo;

        $params = [':statut' => $filters['statut']];

        if (!empty($filters['nom']))
        {
            $params[':nom'] = "%" . $filters['nom'] . "%";
        }

        // build SQL
        $sql_order = "o." . $order . " DESC";
        if ($order == "nom")
        {
            $sql_order = "o.nom ASC";
        }

        $sql_event = "SELECT
          o.*
        FROM organisateur o
        WHERE o.statut = :statut";

        if (!empty($filters['nom']))
        {
            $sql_event .= " AND o.nom LIKE :nom";
        }

        $sql_event .= " ORDER BY $sql_order";

        if (!empty($page))
        {
            $sql_event .= " LIMIT " . (int) (($page - 1) * self::RESULTS_PER_PAGE) . ", " . (int) self::RESULTS_PER_PAGE; // (($page - 1) * self::RESULTS_PER_PAGE +
        }

        //echo $sql_event;
        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}