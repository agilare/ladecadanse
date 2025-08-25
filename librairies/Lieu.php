<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use PDO;

class Lieu extends Element
{
    static $systemDirPath;
    static $urlDirPath;

    function __construct()
	{
		parent::__construct();
        $this->table = "lieu";
    }

    public static function prepositionToPutInSentence($preposition): string
    {
        $result = $preposition;

        if ($preposition == '')
        {
            return "- ";
        }

        // if "au", "chez", etc. add a separation
        if (!in_array(trim($preposition), ["l'", "à l'"]))
        {
            $result .= " ";
        }
        return $result;
    }

    public static function getLinkNameHtml(string $nom, ?int $idLieu, ?string $salle = null): string
    {
        $result = sanitizeForHtml($nom);

        if ($idLieu)
        {
            $result = '<a href="/lieu.php?idL=' . $idLieu . '">' . $result . '</a>';
            if ($salle)
            {
                $result .= " - " . sanitizeForHtml($salle);
            }
        }

        return $result;
    }

    public static function getFilePath(string $fileName, string $fileNamePrefix = '', string $fileNameSuffix = ''): string
    {
        return $fileNamePrefix . $fileName . $fileNameSuffix;
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

    public static function getLieu(int $idLieu): array
    {
        global $connectorPdo;
        $sql_event = "SELECT

          l.*,
          loc.localite AS loc_localite,
          loc.canton AS loc_canton,
          loc.commune AS loc_commune

        FROM lieu l
        JOIN localite loc ON l.localite_id = loc.id
        WHERE l.idLieu = ?";

        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute([$idLieu]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public static function getActivesSalles(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT * FROM salle WHERE idLieu = ? AND salle.status='actif' ");
        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getActivesOrganisateurs(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT lo.idOrganisateur AS idOrganisateur, o.nom AS nom
            FROM lieu_organisateur lo
            JOIN organisateur o on lo.idOrganisateur = o.idOrganisateur AND o.statut = 'actif'
            WHERE lo.idLieu=?");
        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getImagesUploaded(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT lf.idFichierrecu AS idFichierrecu, description, mime, extension
            FROM lieu_fichierrecu lf
            JOIN fichierrecu f ON lf.idFichierrecu = f.idFichierrecu AND f.type = 'image'
            WHERE lf.idLieu = ?
            ORDER BY dateAjout DESC");
        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getDescriptions(int $idLieu): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT type, dl.idLieu AS idLieu, contenu, dl.dateAjout AS dateAjout, pseudo, groupe, dl.idPersonne AS idPersonne, dl.date_derniere_modif
		 FROM descriptionlieu dl
		 INNER JOIN personne p ON dl.idPersonne = p.idPersonne
		 WHERE dl.idLieu = ?
		 ORDER BY type, dl.dateAjout");

        $stmt->execute([$idLieu]);
        return $stmt->fetchAll(PDO::FETCH_GROUP);
    }
}
