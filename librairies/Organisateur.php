<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\Utils\Text;
use PDO;

class Organisateur extends Element
{
    static $systemDirPath;
    static $urlDirPath;

    const int RESULTS_PER_PAGE = 100;

    function __construct()
	{
        parent::__construct();
		$this->table = "organisateur";
	}


    public static function getListLinkedHtml(array $organisateurs): string
    {
        ob_start();
        ?>
        <ul class="event_orga" aria-label="Organisateurs">
            <?php foreach ($organisateurs as $eo) : ?>
                <li>
                    <a href="/organisateur.php?idO=<?= (int) $eo['idOrganisateur']; ?>"><?= sanitizeForHtml($eo['nom']); ?></a><?php if (!empty($eo['url'])) { $organisateurUrl = Text::getUrlWithName($eo['url']); ?> -&nbsp;<a href="<?= sanitizeForHtml($organisateurUrl['url']); ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?= sanitizeForHtml($organisateurUrl['urlName']); ?></a>
                    <?php } ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    public static function getFilePath(string $fileName, string $fileNamePrefix = '', string $fileNameSuffix = ''): string
    {
        return $fileNamePrefix . $fileName . $fileNameSuffix;
    }

    public static function getWebPath(string $filePath, bool $isWithAntiCache = false): string
    {
	    $result = self::$urlDirPath . $filePath;
        $systemFilePath = self::getSystemFilePath($filePath);
        if ($isWithAntiCache && file_exists($systemFilePath))
        {
            $result .= "?" . filemtime($systemFilePath);
        }

	    return $result;
    }

    public static function getSystemFilePath(string $filePath): string
    {
        return self::$systemDirPath . $filePath;
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
}