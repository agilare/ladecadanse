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

    public static function getToken(): string
    {
        if (!isset($_SESSION['token'])) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['token'] = $token;

        }
        return $_SESSION['token'];
    }
}