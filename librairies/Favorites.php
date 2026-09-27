<?php

declare(strict_types=1);

namespace Ladecadanse;

/**
 * Favoris personnels (#98), ouverts en bêta à un panel.
 *
 * L'accès ne passe pas par FeatureFlag : sa préversion est réservée aux administrateurs,
 * alors que le panel compte aussi des visiteurs non connectés. Le lien secret
 * ?favoris_beta=<FAVORITES_BETA_SECRET> pose un cookie, ?favoris_beta=off le retire ;
 * les deux sont traités dans app/bootstrap.php.
 */
final class Favorites
{
    public static function isEnabled(): bool
    {
        if (isset($_GET['favoris_beta']) && $_GET['favoris_beta'] === FAVORITES_BETA_SECRET)
        {
            return true;
        }
        return !empty($_COOKIE['favoris_beta']);
    }
}
