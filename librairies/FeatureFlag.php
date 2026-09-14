<?php

declare(strict_types=1);

namespace Ladecadanse;

/**
 * Drapeaux de fonctionnalité, avec un état intermédiaire pour les déploiements
 * progressifs.
 *
 * Une constante de app/env.php prend trois valeurs :
 *
 *   false       la fonctionnalité n'existe pas — rien ne s'affiche, rien ne
 *               s'accepte, et le code qui la sert n'est pas atteint
 *   'preview'   réservée aux administrateurs : on l'éprouve en conditions
 *               réelles, sur le site en ligne, sans l'exposer au public
 *   true        ouverte à tout le monde
 *
 * L'étape 'preview' est celle que l'on répète à chaque fonctionnalité
 * conséquente. Sans elle, on n'a le choix qu'entre garder le travail hors ligne
 * et le livrer d'un coup à tous les visiteurs.
 *
 * Une constante absente vaut false : les app/env.php antérieurs à une
 * fonctionnalité ne connaissent pas son drapeau, et ne doivent pas l'activer par
 * accident.
 *
 * Tout drapeau doit aussi figurer dans les `dynamicConstantNames` de
 * phpstan.neon, sans quoi l'analyse fige la valeur du poste et tient pour mortes
 * les branches qu'il commande.
 */
final class FeatureFlag
{
    /** Valeur qui réserve une fonctionnalité aux administrateurs. */
    public const PREVIEW = 'preview';

    /**
     * La fonctionnalité est-elle accessible à l'utilisateur courant ?
     *
     * C'est la question que pose le code qui l'implémente. En préversion, elle
     * répond vrai pour un administrateur et faux pour tous les autres, si bien
     * qu'un même appel suffit à couvrir les trois états.
     */
    public static function estActive(string $constante): bool
    {
        $valeur = self::valeur($constante);

        if ($valeur === self::PREVIEW)
        {
            return self::estAdministrateur();
        }

        return $valeur === true;
    }

    /**
     * La fonctionnalité est-elle montrée à titre de préversion ?
     *
     * À poser en complément d'estActive(), là où l'interface doit signaler que
     * ce qu'on voit n'est pas encore public — sans quoi une préversion
     * s'oublie, et l'on croit la fonctionnalité livrée.
     */
    public static function estEnPreview(string $constante): bool
    {
        return self::valeur($constante) === self::PREVIEW && self::estAdministrateur();
    }

    /**
     * La fonctionnalité est-elle ouverte à tous ?
     *
     * Utile aux traitements sans utilisateur — tâche planifiée, script de
     * maintenance —, où « accessible à l'utilisateur courant » n'a pas de sens.
     */
    public static function estOuverteATous(string $constante): bool
    {
        return self::valeur($constante) === true;
    }

    private static function valeur(string $constante): mixed
    {
        return defined($constante) ? constant($constante) : false;
    }

    /**
     * Le niveau lu en session, et non Authorization::checkGroup() : celui-ci
     * interroge la base à chaque appel, ce qu'un drapeau consulté à chaque
     * affichage de formulaire ne peut pas se permettre.
     */
    private static function estAdministrateur(): bool
    {
        return isset($_SESSION['Sgroupe']) && (int) $_SESSION['Sgroupe'] <= UserLevel::ADMIN;
    }
}
