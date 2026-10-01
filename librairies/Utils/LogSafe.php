<?php

namespace Ladecadanse\Utils;

/**
 * Réduction des valeurs qui partent dans les journaux applicatifs (var/logs/).
 *
 * Ces journaux tournent sur plusieurs mois et ne sont couverts par aucune procédure
 * d'effacement : ce qu'on y écrit survit à la suppression d'un compte. Les adresses
 * électroniques n'ont donc rien à y faire en clair — voir 40_Donnees_personnelles.md
 * dans ladecadanse-docs, traitement T10.
 *
 * Le nom d'utilisateur, lui, y reste : il est déjà public sur le site, où il signe
 * chaque événement ajouté, et il rend les journaux lisibles là où un identifiant
 * numérique demanderait une requête à chaque ligne. Attention en revanche à ne jamais
 * journaliser Personne::displayName() : elle retombe sur l'adresse quand le nom manque,
 * et l'adresse reviendrait par cette porte pour les comptes les plus récents.
 */
class LogSafe
{
    /**
     * Ne garde d'une adresse que son domaine : « jean.dupont@example.ch » devient
     * « ***@example.ch ».
     *
     * Le domaine suffit à ce pour quoi ces lignes sont relues — repérer une rafale vers
     * un même hébergeur, un problème de délivrabilité, une série de tentatives de
     * connexion — sans porter de quoi joindre ni reconnaître quelqu'un.
     *
     * Une valeur sans arobase est rendue telle quelle : les champs de connexion et de
     * réinitialisation acceptent indifféremment un nom d'utilisateur ou une adresse, et
     * le nom n'a pas à être masqué.
     *
     * Le dernier arobase fait foi, pas le premier : une saisie forgée peut en porter
     * plusieurs, et c'est ce qui suit le dernier qui est le domaine.
     */
    public static function email(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '')
        {
            return '';
        }

        $at = mb_strrpos($value, '@');

        if ($at === false)
        {
            return $value;
        }

        return '***' . mb_substr($value, $at);
    }
}
