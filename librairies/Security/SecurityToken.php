<?php

namespace Ladecadanse\Security;

class SecurityToken
{
    public static function check($received, $session): bool
    {
        if (hash_equals($received, $session) === false){
            return false;
        }
        return true;
    }

    /**
     * La valeur rendue sort de bin2hex(), donc de [0-9a-f] : elle ne peut porter ni balise ni
     * guillemet, quoi qu'ait mis en session une requête précédente. Les annotations ne sont
     * utiles que depuis que .psalm/src/SessionTaintPlugin.php tient $_SESSION pour une source.
     *
     * Rien ne doit suivre ces deux lignes dans ce docblock : le parseur de Psalm coupe
     * l'argument sur l'espace, pas sur le saut de ligne, et une description placée après
     * l'annotation lui est rattachée — l'échappement cesse alors silencieusement de mordre.
     *
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     */
    public static function getToken(): string
    {
        if (!isset($_SESSION['token'])) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['token'] = $token;

        }
        return $_SESSION['token'];
    }
}