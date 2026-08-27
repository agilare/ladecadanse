<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

use RuntimeException;

/**
 * Rend la première page d'un PDF en WebP, côté serveur, avec Imagick.
 *
 * Ce chemin ne sert qu'à l'import par URL d'evenement-edit.php, **réservé aux
 * comptes connectés**. Les PDF déposés par le champ fichier, lui ouvert aux
 * visiteurs anonymes, sont convertis par le navigateur
 * (web/js/pdf-to-image.js) et n'arrivent jamais ici : le décodage PDF
 * d'Imagick passe par Ghostscript, principale source historique d'exécution de
 * code à distance de cet écosystème, et l'exposer à des envois anonymes n'a pas
 * le même prix que de l'ouvrir à des comptes identifiés.
 *
 * Le navigateur ne peut pas prendre ce cas en charge : il lui faudrait lire une
 * URL d'un autre domaine, ce que CORS et le `connect-src 'self'` de la CSP
 * interdisent.
 *
 * Imagick est présent en production mais absent du poste de développement :
 * tout appelant doit passer par estDisponible() et prévoir le refus.
 */
final class PdfToImage
{
    /** Au-delà, l'image est réduite : le serveur la ramènera de toute façon à 600 px. */
    private const LARGEUR_MAX = 1600;

    /** 150 dpi : une page A4 donne ~1240 px de large, assez pour un flyer. */
    private const RESOLUTION_DPI = 150;

    private const QUALITE_WEBP = 85;

    /** Bornes opposées à un PDF conçu pour épuiser la machine. */
    private const LIMITE_MEMOIRE_OCTETS = 268435456; // 256 Mo, sur les 640 disponibles
    private const LIMITE_SECONDES = 20; // max_execution_time vaut 60 en production

    /**
     * Ce que voit l'utilisateur quand le serveur ne sait pas rendre les PDF —
     * extension absente, Ghostscript absent, ou politique ImageMagick qui le
     * refuse. Trois causes distinctes, mais un seul remède de son côté : l'autre
     * voie, qui ne dépend de rien.
     */
    private const MESSAGE_INDISPONIBLE = "La conversion des PDF par URL n'est pas disponible sur ce serveur. "
        . "Envoyez le PDF avec le bouton « Envoyer », votre navigateur s'en chargera.";

    /**
     * L'acceptation des PDF est-elle demandée ? (PDF_CONVERSION_ENABLED)
     *
     * Passe par une méthode plutôt que par une lecture de la constante sur
     * place : un seul endroit connaît son nom, alors que trois fichiers en
     * dépendent, et le `defined()` qui la protège — la constante manque des
     * app/env.php antérieurs à cette fonctionnalité — n'est écrit qu'une fois.
     *
     * Le drapeau figure aussi dans les `dynamicConstantNames` de phpstan.neon,
     * sans quoi l'analyse fige la valeur du poste et tient pour mortes toutes
     * les branches PDF.
     */
    public static function estActive(): bool
    {
        return defined('PDF_CONVERSION_ENABLED') && PDF_CONVERSION_ENABLED;
    }

    /**
     * Signature d'un PDF. Les octets font foi : ni l'extension, ni le type MIME
     * annoncé par un serveur distant ne sont vérifiables.
     */
    public static function estUnPdf(string $octets): bool
    {
        return str_starts_with($octets, '%PDF-');
    }

    /**
     * Imagick est-il là, avec un décodeur PDF déclaré ?
     *
     * Réponse optimiste, et volontairement : queryFormats() annonce PDF dès que
     * le coder est compilé, sans rien savoir de ce qui le fait réellement
     * marcher. Deux démentis possibles, tous deux constatés — Ghostscript
     * absent, qu'Imagick appelle en sous-main (« FailedToExecuteCommand `gs` »,
     * le cas d'un poste où l'on vient d'installer l'extension seule), et une
     * policy.xml qui refuse le coder depuis CVE-2018-16509 (« not authorized »).
     *
     * Les vérifier ici coûterait une conversion à chaque affichage du
     * formulaire. C'est donc convertirPremierePage() qui tranche, en rendant ces
     * deux échecs au même message que celui d'une extension absente : de là où
     * se tient l'utilisateur, c'est la même chose.
     */
    public static function estDisponible(): bool
    {
        return extension_loaded('imagick') && \Imagick::queryFormats('PDF') !== [];
    }

    /**
     * Rend la première page et renvoie les octets WebP.
     *
     * @throws RuntimeException message destiné à l'utilisateur
     */
    public static function convertirPremierePage(string $octetsPdf): string
    {
        if (!self::estUnPdf($octetsPdf))
        {
            throw new RuntimeException("Ce fichier n'est pas un PDF");
        }

        if (!self::estDisponible())
        {
            throw new RuntimeException(self::MESSAGE_INDISPONIBLE);
        }

        // Imagick lit une page précise depuis un chemin, jamais depuis un blob :
        // readImageBlob() rasteriserait le document entier, cent pages comprises.
        // Le PDF est donc écrit dans le répertoire temporaire du système — hors
        // du docroot, jamais sous web/uploads — et retiré aussitôt.
        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'ldd_pdf_');

        if ($cheminTemporaire === false)
        {
            throw new RuntimeException("Le PDF n'a pas pu être traité");
        }

        try
        {
            file_put_contents($cheminTemporaire, $octetsPdf);

            return self::rendre($cheminTemporaire);
        }
        finally
        {
            @unlink($cheminTemporaire);
        }
    }

    /**
     * @throws RuntimeException
     */
    private static function rendre(string $cheminPdf): string
    {
        $imagick = new \Imagick();

        // Les bornes se posent avant toute lecture : passé readImage(), le mal
        // est fait.
        $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, self::LIMITE_MEMOIRE_OCTETS);
        $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MAP, self::LIMITE_MEMOIRE_OCTETS);
        $imagick->setResourceLimit(\Imagick::RESOURCETYPE_TIME, self::LIMITE_SECONDES);

        // La résolution doit être posée avant la lecture : après, la
        // rasterisation a déjà eu lieu et la changer ne fait plus qu'étirer.
        $imagick->setResolution(self::RESOLUTION_DPI, self::RESOLUTION_DPI);

        try
        {
            // « [0] » restreint au premier cadre. Le préfixe « pdf: » impose le
            // décodeur : sans lui Imagick devine d'après le contenu, et un
            // fichier maquillé emprunterait un autre coder.
            $imagick->readImage('pdf:' . $cheminPdf . '[0]');

            if ($imagick->getNumberImages() < 1)
            {
                throw new RuntimeException('Ce PDF ne contient aucune page');
            }

            // Une page de PDF n'a pas de fond : sans aplatissement sur du blanc,
            // tout ce que l'affiche laisse vide ressort transparent, puis noir
            // dans un format qui ignore la transparence.
            $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
            $aplati = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            if ($aplati->getImageWidth() > self::LARGEUR_MAX)
            {
                $aplati->resizeImage(self::LARGEUR_MAX, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            $aplati->setImageFormat('webp');
            $aplati->setImageCompressionQuality(self::QUALITE_WEBP);

            $octets = $aplati->getImageBlob();

            $aplati->clear();

            return $octets;
        }
        catch (\ImagickException $e)
        {
            // Deux familles d'échec, que l'utilisateur ne doit surtout pas
            // confondre : ce qui vient du serveur, sur quoi il ne peut rien, et
            // ce qui vient de son fichier. Accuser le PDF alors que Ghostscript
            // manque enverrait chercher un mot de passe inexistant.
            $message = $e->getMessage();

            if (str_contains($message, 'FailedToExecuteCommand') || str_contains($message, 'not authorized'))
            {
                throw new RuntimeException(self::MESSAGE_INDISPONIBLE, 0, $e);
            }

            throw new RuntimeException(
                "Ce PDF n'a pas pu être converti en image. Vérifiez qu'il n'est pas protégé par un mot de passe.",
                0,
                $e
            );
        }
        finally
        {
            $imagick->clear();
        }
    }
}
