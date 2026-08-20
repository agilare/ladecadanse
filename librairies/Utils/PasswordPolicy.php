<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

/**
 * Règles du mot de passe choisi par un membre, et liste des mots de passe refusés.
 *
 * Les trois formulaires qui fixent un mot de passe (inscription, réinitialisation,
 * profil) recopiaient les mêmes règles et relisaient `resources/bad_p.txt` chacun à
 * sa façon, par un chemin relatif au script — qui casse dès qu'une page change de
 * répertoire.
 *
 * La liste vient de tarraschk/richelieu (CC BY 4.0), mots de passe français issus de
 * fuites publiques ; voir l'en-tête du fichier.
 */
final class PasswordPolicy
{
    public const LONGUEUR_MIN = 10;
    public const LONGUEUR_MAX = 100;

    private const FICHIER_REFUSES = '/resources/bad_p.txt';

    /**
     * Racine du projet déduite de l'emplacement de la classe (librairies/Utils/) plutôt
     * que de la constante __ROOT__ : les tests unitaires ne chargent que l'autoloader,
     * pas app/config.php.
     */
    private static function cheminFichier(): string
    {
        return dirname(__DIR__, 2) . self::FICHIER_REFUSES;
    }

    /**
     * Liste chargée une fois par requête : le fichier fait 20 000 lignes.
     *
     * @var string[]|null
     */
    private static ?array $refuses = null;

    /**
     * Vérifie un mot de passe et sa confirmation.
     *
     * @return array<string, string> erreurs indexées par nom de champ, vide si tout va bien.
     *                               Les clés sont celles attendues par les formulaires
     *                               (`motdepasse`, `motdepasse_inegaux`).
     */
    public static function erreurs(string $motdepasse, string $confirmation): array
    {
        $erreurs = [];

        if ($motdepasse === '')
        {
            $erreurs['motdepasse'] = "Veuillez saisir un mot de passe.";
        }
        else if (mb_strlen($motdepasse) < self::LONGUEUR_MIN || mb_strlen($motdepasse) > self::LONGUEUR_MAX)
        {
            $erreurs['motdepasse'] = "Votre mot de passe fait entre " . self::LONGUEUR_MIN
                . " et " . self::LONGUEUR_MAX . " caractères.";
        }
        else if (!preg_match("/[0-9]/", $motdepasse))
        {
            $erreurs['motdepasse'] = "Le mot de passe doit comporter au moins 1 chiffre.";
        }
        // la liste ne dit rien de plus que les règles ci-dessus tant qu'elles ne sont pas
        // satisfaites : inutile de la charger pour un mot de passe déjà refusé
        else if (self::estRefuse($motdepasse))
        {
            $erreurs['motdepasse'] = "Ce mot de passe est trop courant, veuillez en choisir un autre.";
        }

        if ($confirmation !== $motdepasse)
        {
            $erreurs['motdepasse_inegaux'] = "Les 2 mots de passe doivent être identiques.";
        }

        return $erreurs;
    }

    /**
     * Le mot de passe figure-t-il parmi les plus courants ?
     */
    public static function estRefuse(string $motdepasse): bool
    {
        return in_array($motdepasse, self::listeRefuses(), true);
    }

    /**
     * @return string[]
     */
    private static function listeRefuses(): array
    {
        if (self::$refuses !== null)
        {
            return self::$refuses;
        }

        $lignes = @file(self::cheminFichier(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // fichier absent ou illisible : les autres règles s'appliquent toujours, une
        // inscription ne doit pas échouer là-dessus
        if ($lignes === false)
        {
            error_log("PasswordPolicy : " . self::cheminFichier() . " illisible");
            $lignes = [];
        }

        self::$refuses = array_values(array_filter(
            $lignes,
            static fn (string $ligne): bool => $ligne !== '' && !str_starts_with($ligne, '#')
        ));

        return self::$refuses;
    }
}
