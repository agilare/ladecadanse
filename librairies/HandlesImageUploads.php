<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2026 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use Ladecadanse\Utils\ImageDriver2;

/**
 * Cycle de vie des champs image d'un formulaire d'édition (logo, photo…) :
 * nommage d'après l'id de l'enregistrement, remplacement, suppression, écriture
 * de l'image et de sa miniature « s_ ».
 *
 * OrganisateurEdition et LieuEdition répétaient ce bloc pour chaque champ, avec
 * les mêmes défauts recopiés de l'un à l'autre : le nom du fichier était bâti
 * sur MAX(id) + 1 plutôt que sur l'id réellement inséré, et l'extension de la
 * seconde image était reprise de celle du logo.
 *
 * Autonome : safeUnlinkImageAndThumb() vivait sur Edition, dont seul ce trait se
 * servait, et l'y remonter dispensait ses utilisateurs d'en hériter.
 */
trait HandlesImageUploads
{
    /**
     * Entrée de $_FILES d'un champ image, ramenée à un tableau.
     *
     * Les formulaires déclarent leurs champs fichier avec une chaîne vide pour
     * valeur par défaut, que readPostedFiles() ne remplace que si le client a
     * envoyé quelque chose. Un champ laissé vide arrive donc ici en chaîne, sur
     * laquelle le code lisait ['name'] — un offset de chaîne, qui rendait ''
     * moyennant un avertissement.
     *
     * @return array{name: string, tmp_name: string, size: int}|array<string, mixed>
     */
    protected function uploadedFileFor(string $imageField): array
    {
        $file = $this->fichiers[$imageField] ?? null;

        return is_array($file) ? $file : ['name' => '', 'tmp_name' => '', 'size' => 0];
    }

    /**
     * Ce champ image a-t-il quelque chose à enregistrer : un fichier envoyé, ou
     * une suppression demandée ?
     *
     * C'est la seule question qui distingue un champ qui a bougé d'un champ
     * laissé tel quel. Le nom de fichier, lui, ne dit rien : il est bâti sur
     * {id}_{champ}.{extension}, donc identique quand une image est remplacée par
     * une autre du même format.
     */
    protected function isImageFieldTouched(string $imageField, bool $isImageMarkedForDeletion): bool
    {
        return !empty($this->uploadedFileFor($imageField)['name']) || $isImageMarkedForDeletion;
    }

    /**
     * Nom de fichier à enregistrer pour un champ image, une fois pris en compte
     * le fichier envoyé et la case « Supprimer ».
     *
     * L'ancien fichier et sa miniature sont effacés du disque dès qu'ils sont
     * remplacés ou retirés.
     *
     * @param array{name?: string, tmp_name?: string} $uploadedFile Entrée de $_FILES pour ce champ
     * @param string $currentName Nom enregistré en base, '' s'il n'y en a pas
     * @return string Le nouveau nom, '' si l'image est retirée, $currentName si rien ne change
     */
    protected function imageNameAfterEdit(
        string $imageField,
        array $uploadedFile,
        string $currentName,
        bool $isImageMarkedForDeletion,
        int $entityId,
        string $uploadsDir
    ): string
    {
        $isUploaded = !empty($uploadedFile['name']);

        if (!$isUploaded && !$isImageMarkedForDeletion)
        {
            return $currentName;
        }

        if ($currentName !== '')
        {
            $this->safeUnlinkImageAndThumb($uploadsDir, $currentName);
        }

        if (!$isUploaded)
        {
            return '';
        }

        // L'extension suit le format réel du fichier et non celle de son nom
        // d'origine : ImageDriver2 écrit d'après le contenu, si bien qu'un PNG
        // envoyé sous le nom « logo.jpg » produisait un .jpg contenant du PNG.
        return $entityId . '_' . $imageField . Document::extensionPourMime((string) mime_content_type((string) $uploadedFile['tmp_name']));
    }

    /**
     * Écrit la miniature (préfixe « s_ ») puis l'image affichée.
     *
     * Un échec est journalisé et signalé à l'appelant sans interrompre la
     * requête : l'enregistrement en base a déjà eu lieu, une sortie brutale ne
     * laisserait que la page blanche.
     *
     * @param array{name?: string, tmp_name?: string, size?: int} $uploadedFile
     * @param string $uploadsSubdir Sous-répertoire d'uploads, au sens d'ImageDriver2 ("organisateurs", "lieux")
     * @param array{maxWidth: int, maxHeight: int, fitOn: string, crop: int} $thumbnail
     */
    protected function writeImageFiles(
        array $uploadedFile,
        string $fileName,
        string $uploadsSubdir,
        array $thumbnail,
        int $maxDisplayedSize = 600
    ): bool
    {
        if (empty($uploadedFile['name']) || $fileName === '')
        {
            return true;
        }

        $imageDriver = new ImageDriver2($uploadsSubdir);

        $writes = [
            ["s_" . $fileName, $thumbnail['maxWidth'], $thumbnail['maxHeight'], $thumbnail['fitOn'], $thumbnail['crop']],
            [$fileName, $maxDisplayedSize, $maxDisplayedSize, '', 0],
        ];

        foreach ($writes as [$name, $width, $height, $fitOn, $crop])
        {
            if (!$imageDriver->processImage($uploadedFile, $name, $width, $height, $fitOn, $crop))
            {
                trigger_error($imageDriver->getErreur(), E_USER_WARNING);
                return false;
            }
        }

        return true;
    }

    /**
     * Supprime un fichier image et sa miniature (préfixe "s_") de manière sécurisée.
     *
     * Neutralise toute tentative de path traversal provenant d'une valeur issue de la BD :
     * - basename() supprime les composants de répertoire du nom de fichier
     * - realpath() + str_starts_with() garantit que le chemin résolu reste dans $dir
     */
    protected function safeUnlinkImageAndThumb(string $dir, string $filename): void
    {
        $safeName = basename($filename);
        if ($safeName === '') {
            return;
        }
        $safeDir = realpath($dir);
        if ($safeDir === false) {
            return;
        }
        foreach ([$safeName, 's_' . $safeName] as $name) {
            $resolvedPath = realpath($safeDir . DIRECTORY_SEPARATOR . $name);
            if ($resolvedPath !== false && str_starts_with($resolvedPath, $safeDir . DIRECTORY_SEPARATOR)) {
                unlink($resolvedPath);
            }
        }
    }
}
