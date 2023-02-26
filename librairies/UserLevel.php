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

    static function getConstants()
    {
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

}
