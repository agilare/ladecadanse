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
 * Réservé aux classes qui étendent Edition : safeUnlinkImageAndThumb() en vient.
 */
trait HandlesImageUploads
{
    /**
     * Entrée de $_FILES d'un champ image, ramenée à un tableau.
     *
     * Les formulaires déclarent leurs champs fichier avec une chaîne vide pour
     * valeur par défaut, qu'Edition::traitement() ne remplace que si le client a
     * envoyé quelque chose. Un champ laissé vide arrive donc ici en chaîne, sur
     * laquelle le code lisait ['name'] — un offset de chaîne, qui rendait ''
     * moyennant un avertissement.
     *
     * @return array{name: string, tmp_name: string, size: int}|array<string, mixed>
     */
    protected function fichierEnvoye(string $champ): array
    {
        $fichier = $this->fichiers[$champ] ?? null;

        return is_array($fichier) ? $fichier : ['name' => '', 'tmp_name' => '', 'size' => 0];
    }

    /**
     * Nom de fichier à enregistrer pour un champ image, une fois pris en compte
     * le fichier envoyé et la case « Supprimer ».
     *
     * L'ancien fichier et sa miniature sont effacés du disque dès qu'ils sont
     * remplacés ou retirés.
     *
     * @param array{name?: string, tmp_name?: string} $fichier Entrée de $_FILES pour ce champ
     * @param string $nomActuel Nom enregistré en base, '' s'il n'y en a pas
     * @return string Le nouveau nom, '' si l'image est retirée, $nomActuel si rien ne change
     */
    protected function nomImageApresEdition(
        string $champ,
        array $fichier,
        string $nomActuel,
        bool $suppressionDemandee,
        int $id,
        string $repUploads
    ): string
    {
        $envoi = !empty($fichier['name']);

        if (!$envoi && !$suppressionDemandee)
        {
            return $nomActuel;
        }

        if ($nomActuel !== '')
        {
            $this->safeUnlinkImageAndThumb($repUploads, $nomActuel);
        }

        if (!$envoi)
        {
            return '';
        }

        // L'extension suit le format réel du fichier et non celle de son nom
        // d'origine : ImageDriver2 écrit d'après le contenu, si bien qu'un PNG
        // envoyé sous le nom « logo.jpg » produisait un .jpg contenant du PNG.
        return $id . '_' . $champ . Document::extensionPourMime((string) mime_content_type((string) $fichier['tmp_name']));
    }

    /**
     * Écrit la miniature (préfixe « s_ ») puis l'image affichée.
     *
     * Un échec est journalisé et signalé à l'appelant sans interrompre la
     * requête : l'enregistrement en base a déjà eu lieu, une sortie brutale ne
     * laisserait que la page blanche.
     *
     * @param array{name?: string, tmp_name?: string, size?: int} $fichier
     * @param string $typeUpload Sous-répertoire d'uploads, au sens d'ImageDriver2 ("organisateurs", "lieux")
     * @param array{maxLargeur: int, maxHauteur: int, selon: string, rognage: int} $miniature
     */
    protected function ecrireImageEtMiniature(
        array $fichier,
        string $nomFichier,
        string $typeUpload,
        array $miniature,
        int $tailleMaxAffichee = 600
    ): bool
    {
        if (empty($fichier['name']) || $nomFichier === '')
        {
            return true;
        }

        $imageDriver = new ImageDriver2($typeUpload);

        $ecritures = [
            ["s_" . $nomFichier, $miniature['maxLargeur'], $miniature['maxHauteur'], $miniature['selon'], $miniature['rognage']],
            [$nomFichier, $tailleMaxAffichee, $tailleMaxAffichee, '', 0],
        ];

        foreach ($ecritures as [$nom, $largeur, $hauteur, $selon, $rognage])
        {
            if (!$imageDriver->processImage($fichier, $nom, $largeur, $hauteur, $selon, $rognage))
            {
                trigger_error($imageDriver->getErreur(), E_USER_WARNING);
                return false;
            }
        }

        return true;
    }
}
