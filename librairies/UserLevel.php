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
        return $oClass->getConstants();
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
