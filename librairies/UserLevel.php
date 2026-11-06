<?php

namespace Ladecadanse;

class UserLevel
{

    /** @var int BDFL */
    public const SUPERADMIN = 1;

    /** @var int administrateurs régionaux */
    public const ADMIN = 4;

    /** @var int accès à tous les contenus */
    public const AUTHOR = 6;

    /** @var int ajout et modif de ses even, éventuellement de sa fiche organisateur */
    public const ACTOR = 8;

    /** @var int */
    public const MEMBER = 12;

    /**
     * @return array<string, int>
     */
    static function getConstants()
    {
        $oClass = new \ReflectionClass(self::class);
        // les seuls niveaux : DESCRIPTIONS, privée, n'en est pas un
        return $oClass->getConstants(\ReflectionClassConstant::IS_PUBLIC);
    }

    /**
     * Ce que permet chaque niveau, en une ligne, pour qui attribue les groupes (user/edit.php).
     * Un niveau permet aussi tout ce que permettent les niveaux de nombre plus élevé.
     */
    private const DESCRIPTIONS = [
        self::SUPERADMIN => "Tous les droits, dont l'attribution des groupes, la modification et l'effacement des comptes",
        self::ADMIN => "Administration : gestion des événements, des comptes en lecture, envois groupés, suivi des robots",
        self::AUTHOR => "Modifie tous les événements, lieux et organisateurs, et rattache les comptes à leurs fiches",
        self::ACTOR => "Ajoute des événements sans modération ; modifie les siens et les fiches de son lieu et de ses organisateurs",
        self::MEMBER => "Favoris ; n'ajoute pas d'événements",
    ];

    /**
     * Description du niveau, ou chaîne vide pour une valeur hors barème (le groupe 10 hérité).
     */
    public static function getDescription(int $groupe): string
    {
        return self::DESCRIPTIONS[$groupe] ?? '';
    }

    /**
     * Libellé lisible du niveau, pour les écrans qui le montrent (user/dashboard.php).
     */
    private const LABELS = [
        self::SUPERADMIN => "Super-administrateur",
        self::ADMIN => "Administrateur",
        self::AUTHOR => "Éditeur",
        self::ACTOR => "Contributeur",
        self::MEMBER => "Membre",
    ];

    /**
     * Libellé du niveau, ou chaîne vide pour une valeur hors barème (le groupe 10 hérité).
     */
    public static function getLabel(int $groupe): string
    {
        return self::LABELS[$groupe] ?? '';
    }

    /**
     * Nom du niveau, tel qu'affiché aux administrateurs : « ACTOR », « ADMIN »…
     *
     * Une valeur hors barème est rendue telle quelle plutôt que masquée : un groupe inattendu en
     * base est une information, pas un cas à taire.
     */
    public static function getName(int $groupe): string
    {
        $nom = array_search($groupe, self::getConstants(), true);

        return $nom === false ? (string) $groupe : $nom;
    }

}
