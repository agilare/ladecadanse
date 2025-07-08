<?php
// declare(strict_types=1);

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\Utils\Text;

class Evenement extends Element
{

    static $systemDirPath;
    static $urlDirPath;
    static $statuts_evenement = ['propose' => 'Proposé', 'actif' => '', 'complet' => 'Complet', 'annule' => 'Annulé', 'inactif' => 'Dépublié'];

    function __construct() {

        parent::__construct();
        $this->table = "evenement";
    }

    /**
     * Affichage du lieu selon son existence ou non dans la base
     * @param array $event
     * @return array
     */
    public static function getLieu(array $event): array
    {
        if ($event['e_idLieu'] != 0)
        {
            return [
                'idLieu' => $event['e_idLieu'],
                'nom' => $event['l_nom'],
                'adresse' => $event['l_adresse'],
                'quartier' => $event['l_quartier'],
                'lat' => $event['l_lat'] ?? "",
                'lng' => $event['l_lng'] ?? "",
                'localite' => $event['lloc_localite'],
                'region' => $event['l_region'] ?? "",
                'url' => $event['l_URL'],
                'salle' => $event['s_nom'] ?? "",
            ];
        }

        return [
                'idLieu' => null,
                'nom' => $event['e_nomLieu'],
                'adresse' => $event['e_adresse'],
                'quartier' => $event['e_quartier'],
                'lat' => '',
                'lng' => '',
                'localite' => $event['e_localite'],
                'region' => $event['e_region'] ?? "",
                'url' => $event['e_urlLieu'],
                'salle' => ""
            ];
    }

    public static function nom_genre(string $nom): string
    {
        if ($nom == 'fête')
        {
            return 'fêtes';
        }
        else if ($nom == 'cinéma')
        {
            return 'ciné';
        }

        return $nom;
    }

    public static function titreSelonStatutHtml(string $titreHtml, string $statut, bool $isPersonneAllowedToEdit = false): string
    {
        $result = $titreHtml;
        $badge = ' <span class="even-statut-badge ' . $statut . '">' . mb_strtoupper(self::$statuts_evenement[$statut]) . '</span>';

        if ($statut == 'actif' || (in_array($statut, ['inactif', 'propose']) && !$isPersonneAllowedToEdit))
        {
            $badge = '';
        }

        if ($statut == "annule")
        {
            $result = '<strike>' . $titreHtml . '</strike>';
        }

        if ($statut == "complet")
        {
            $result = '<em>' . $titreHtml . '</em>';
        }

        return $result . $badge;
    }

    public static function getRefListHtml(string $refCsv): string
    {
        ob_start();
        $tab_ref = explode(";", strip_tags($refCsv));
        foreach ($tab_ref as $r)
        {
            $r = trim($r);
            if (mb_substr($r, 0, 3) == "www")
            {
                $r = "http://".$r;
            }
            ?>
            <li>
                <?php
                // it's an URL
                if (preg_match('#^(https?\\:\\/\\/)[a-z0-9_-]+\.([a-z0-9_-]+\.)?[a-zA-Z]{2,3}#i', $r))
                {
                    $url_with_name = Text::getUrlWithName($r);
                ?>
                    <i class="fa fa-hand-o-right" aria-hidden="true"></i>&nbsp;<a href="<?= sanitizeForHtml($url_with_name['url']) ?>" target='_blank' class="lien_ext"><?= sanitizeForHtml($url_with_name['urlName']) ?></a>
                <?php
                }
                else
                {
                    echo sanitizeForHtml($r);
                }
                ?>
            </li>
            <?php
        }
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    public static function mainFigureHtml(string $flyer, string $image, string $titre, ?int $smallWidth = null): string
    {
        ob_start();

        // by default display small version
        $imgSmallFilePathPrefix = "s_";
        // 120 : max width when saving small version of uploaded flyers
        // if container width exceeds width of small version, choose big version
        if (empty($smallWidth) || (!empty($smallWidth) && $smallWidth > 120))
        {
            $imgSmallFilePathPrefix = '';
        }

        if (empty($flyer) && empty($image))
        {
            return '';
        }

        if (!empty($flyer))
        {
            $href = self::getFileHref(self::getFilePath($flyer));
            $imgSrc = self::getFileHref(self::getFilePath($flyer, $imgSmallFilePathPrefix), true);
            $imgAlt = "Flyer de ". sanitizeForHtml($titre);
            //$imgHeight = ImageDriver2::getProportionalHeightFromGivenWidth(self::getSystemFilePath(self::getFilePath($flyer, $imgSmallFilePathPrefix)), $smallWidth);
        }
        elseif (!empty($image))
        {
            $href = self::getFileHref(self::getFilePath($image));
            $imgSrc = self::getFileHref(self::getFilePath($image, $imgSmallFilePathPrefix), true);
            $imgAlt = "Illustration de ". sanitizeForHtml($titre);
            //$imgHeight = ImageDriver2::getProportionalHeightFromGivenWidth(self::getSystemFilePath(self::getFilePath($image, $imgSmallFilePathPrefix)), $smallWidth);
        }
        ?>

        <a href="<?= $href ?>" class="magnific-popup">
            <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>"
                 <?php if (!empty($smallWidth)) : ?>
                 width="<?= $smallWidth ?>" height="<?= $imgHeight ?>" <?php endif; ?>
                 >
        </a>

        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    public static function rmImageAndItsMiniature(string $fileName): void
    {
        unlink(self::getSystemFilePath(self::getFilePath($fileName)));
        unlink(self::getSystemFilePath(self::getFilePath($fileName, "s_")));
    }

    public static function getFilePath(string $fileName, string $fileNamePrefix = '', string $fileNameSuffix = ''): string
    {
        $filePath = $fileNamePrefix . $fileName . $fileNameSuffix;

        // extract year from $fileName : 12345_2024-11-18.jpg or 12345_2024-11-18_img.jpg
        //$dateMatches = [];
        if (!preg_match('/(\d{4}-\d{2}-\d{2})/', $fileName, $dateMatches))
        {
            return $filePath;
        }

        $eventYear = substr($dateMatches[1], 0, 4);
        if ((new \DateTime('now'))->format('Y') > $eventYear)
        {
            $filePath = $eventYear . "/" . $filePath;
        }
	    return $filePath;
    }

    public static function getFileHref(string $filePath, bool $isWithAntiCache = false): string
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
}
