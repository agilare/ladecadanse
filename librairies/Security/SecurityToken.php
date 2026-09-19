<?php

namespace Ladecadanse\Security;

class SecurityToken
{
    /**
     * Les deux valeurs arrivent telles quelles de $_POST et de $_SESSION, d'où mixed : un
     * paramètre string ferait d'un « token[]=x » forgé une TypeError, donc une page 500, au
     * lieu d'un refus.
     *
     * Le jeton de session n'existe qu'une fois qu'une page l'a rendu (getToken(), dans un
     * formulaire ou un lien « Dépublier ») : vide ou absent, il ne doit rien valider, sans quoi
     * un envoi sans jeton passerait.
     */
    public static function check(mixed $received, mixed $session): bool
    {
        return is_string($session)
            && $session !== ''
            && is_string($received)
            && hash_equals($session, $received);
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
