<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

/**
 * Réglages personnels d'un utilisateur, stockés en JSON dans la colonne `personne.settings`.
 *
 * Une seule colonne accueille tous les réglages, regroupés par domaine, plutôt qu'une colonne par
 * préférence : `{"events": {"new_defaults": {...}}}`. Ajouter un réglage plus tard ne demande donc
 * pas de migration.
 *
 * Le premier (et pour l'instant unique) domaine est `events > new_defaults` : les valeurs dont le
 * formulaire d'ajout d'événement est prérempli (evenement-edit.php, action « ajouter »), réglées
 * depuis le profil (user-edit.php, fieldset « Événements »).
 *
 * Cette classe ne touche pas à la base : elle ne fait qu'encoder, décoder et normaliser. Les
 * identifiants de lieu et d'organisateur ne sont donc pas vérifiés en base — c'est délibéré, voir
 * eventNewDefaults().
 */
class UserSettings
{
    /**
     * Les six réglages de `events > new_defaults`, avec leur valeur « non renseigné ».
     *
     * Sert à la fois de liste de référence et de valeur de repli : toute lecture renvoie ces clés,
     * dans cet ordre, quoi que contienne la colonne.
     */
    private const array EVENT_NEW_DEFAULTS_VIDES = [
        'genre' => '',
        'horaire_debut' => '',
        'horaire_fin' => '',
        'idLieu' => 0,
        'idOrganisateurs' => [],
        'prix' => '',
    ];

    /**
     * Longueur maximale du prix, alignée sur la validation du champ `prix` de evenement-edit.php
     * (et sur la colonne `evenement.prix`, varchar(100)).
     */
    private const int PRIX_LONGUEUR_MAX = 100;

    /**
     * Contenu de la colonne => tableau associatif.
     *
     * Une colonne vide, nulle ou contenant du JSON illisible donne un tableau vide : les réglages
     * sont un confort, ils ne doivent jamais faire échouer la page qui les lit.
     *
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '')
        {
            return [];
        }

        $settings = json_decode($json, true);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Contenu de la colonne => les six valeurs par défaut d'ajout d'événement, normalisées.
     *
     * Point d'entrée unique en lecture, utilisé aussi bien par le profil (pour réafficher les
     * réglages) que par le formulaire d'ajout (pour le préremplir). Les clés manquantes ou d'un
     * type inattendu retombent sur EVENT_NEW_DEFAULTS_VIDES, si bien que l'appelant peut se servir
     * du résultat sans le tester.
     *
     * Les identifiants renvoyés ne sont pas garantis exister encore en base : un lieu ou un
     * organisateur désactivé depuis le réglage ne correspondra simplement à aucune <option>, et le
     * champ restera vide. C'est le comportement voulu — il évite une requête de contrôle à chaque
     * affichage, et un réglage devenu caduc dégrade l'aide sans jamais bloquer la saisie.
     *
     * @return array{genre: string, horaire_debut: string, horaire_fin: string, idLieu: int, idOrganisateurs: list<int>, prix: string}
     */
    public static function eventNewDefaults(?string $json): array
    {
        $settings = self::decode($json);
        $stockes = $settings['events']['new_defaults'] ?? [];

        if (!is_array($stockes))
        {
            $stockes = [];
        }

        $defauts = self::EVENT_NEW_DEFAULTS_VIDES;

        foreach (['genre', 'horaire_debut', 'horaire_fin', 'prix'] as $cle)
        {
            if (isset($stockes[$cle]) && is_string($stockes[$cle]))
            {
                $defauts[$cle] = $stockes[$cle];
            }
        }

        if (isset($stockes['idLieu']) && is_numeric($stockes['idLieu']))
        {
            $defauts['idLieu'] = max(0, (int) $stockes['idLieu']);
        }

        if (isset($stockes['idOrganisateurs']) && is_array($stockes['idOrganisateurs']))
        {
            $defauts['idOrganisateurs'] = self::normaliserIds($stockes['idOrganisateurs']);
        }

        return $defauts;
    }

    /**
     * Saisie du formulaire de profil => les six valeurs, normalisées et sûres à stocker.
     *
     * Les selects et les <input type="time"> du formulaire n'offrent que des valeurs valides : une
     * valeur aberrante ne peut venir que d'un POST forgé. On la ramène donc silencieusement à
     * « non renseigné » plutôt que d'afficher une erreur — sauf pour les horaires, dont le format
     * est vérifié séparément par estHoraireValide() afin que le formulaire puisse le signaler.
     *
     * @param array<string, mixed> $saisie          valeurs brutes issues de $_POST
     * @param array<string, string> $genresAutorises $glo_tab_genre (app/config.php)
     * @return array{genre: string, horaire_debut: string, horaire_fin: string, idLieu: int, idOrganisateurs: list<int>, prix: string}
     */
    public static function sanitizeEventNewDefaults(array $saisie, array $genresAutorises): array
    {
        $defauts = self::EVENT_NEW_DEFAULTS_VIDES;

        $genre = isset($saisie['genre']) && is_string($saisie['genre']) ? $saisie['genre'] : '';
        if (array_key_exists($genre, $genresAutorises))
        {
            $defauts['genre'] = $genre;
        }

        foreach (['horaire_debut', 'horaire_fin'] as $cle)
        {
            $horaire = isset($saisie[$cle]) && is_string($saisie[$cle]) ? trim($saisie[$cle]) : '';
            $defauts[$cle] = self::normaliserHoraire($horaire);
        }

        if (isset($saisie['idLieu']) && is_numeric($saisie['idLieu']))
        {
            $defauts['idLieu'] = max(0, (int) $saisie['idLieu']);
        }

        if (isset($saisie['idOrganisateurs']) && is_array($saisie['idOrganisateurs']))
        {
            $defauts['idOrganisateurs'] = self::normaliserIds($saisie['idOrganisateurs']);
        }

        if (isset($saisie['prix']) && is_string($saisie['prix']))
        {
            $defauts['prix'] = mb_substr(trim($saisie['prix']), 0, self::PRIX_LONGUEUR_MAX);
        }

        return $defauts;
    }

    /**
     * Réglages actuels + nouvelles valeurs par défaut => contenu à écrire dans la colonne.
     *
     * La colonne est relue puis fusionnée, et non remplacée : un réglage d'un autre domaine, ajouté
     * plus tard, doit survivre à un enregistrement du fieldset « Événements ».
     *
     * Les valeurs vides ne sont pas stockées, de sorte qu'un utilisateur qui n'a rien réglé — ou
     * qui a tout effacé — laisse une colonne vide plutôt qu'un squelette JSON.
     *
     * @param array<string, mixed> $defauts sortie de sanitizeEventNewDefaults()
     */
    public static function withEventNewDefaults(?string $jsonActuel, array $defauts): string
    {
        $settings = self::decode($jsonActuel);

        $aStocker = array_filter(
            $defauts,
            static fn($valeur): bool => $valeur !== '' && $valeur !== 0 && $valeur !== []
        );

        if ($aStocker === [])
        {
            unset($settings['events']['new_defaults']);

            if (isset($settings['events']) && $settings['events'] === [])
            {
                unset($settings['events']);
            }
        }
        else
        {
            if (!isset($settings['events']) || !is_array($settings['events']))
            {
                $settings['events'] = [];
            }

            $settings['events']['new_defaults'] = $aStocker;
        }

        if ($settings === [])
        {
            return '';
        }

        // JSON_UNESCAPED_UNICODE : les valeurs sont des libellés français (« fête », « Chapeau à la
        // sortie ») ; les échapper en \uXXXX rendrait la colonne illisible en inspection directe.
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }

    /**
     * Un horaire de formulaire est-il exploitable ?
     *
     * Une chaîne vide est valide : les valeurs par défaut sont toutes facultatives.
     */
    public static function estHoraireValide(string $horaire): bool
    {
        return $horaire === '' || self::normaliserHoraire($horaire) !== '';
    }

    /**
     * « 9:5 » ou « 21:00 » => « 09:05 » / « 21:00 » ; tout le reste => ''.
     *
     * Le format accepté en entrée est celui de evenement-edit.php (regex identique), pour qu'un
     * horaire réglable comme défaut soit toujours un horaire saisissable dans le formulaire.
     */
    private static function normaliserHoraire(string $horaire): string
    {
        if (preg_match('/^([0-9]{1,2}):([0-9]{2})$/', $horaire, $parties) !== 1)
        {
            return '';
        }

        $heures = (int) $parties[1];
        $minutes = (int) $parties[2];

        if ($heures > 23 || $minutes > 59)
        {
            return '';
        }

        return sprintf('%02d:%02d', $heures, $minutes);
    }

    /**
     * Liste hétérogène => liste d'identifiants entiers positifs, dédoublonnée et réindexée.
     *
     * @param array<mixed> $ids
     * @return list<int>
     */
    private static function normaliserIds(array $ids): array
    {
        $entiers = [];

        foreach ($ids as $id)
        {
            if (!is_numeric($id))
            {
                continue;
            }

            $entier = (int) $id;

            if ($entier > 0 && !in_array($entier, $entiers, true))
            {
                $entiers[] = $entier;
            }
        }

        return $entiers;
    }
}
