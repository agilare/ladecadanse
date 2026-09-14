<?php

namespace Ladecadanse\Utils;

/**
 * Liste de références (sites web) d'un événement.
 *
 * Le champ `evenement.ref` stocke plusieurs URL dans une seule colonne, séparées par des
 * points-virgules. Ce format reste le format canonique : il est exposé tel quel par l'API
 * (event/api.php). La saisie, elle, se fait à raison d'une URL par ligne (evenement-edit.php) ;
 * cette classe assure la conversion entre les deux, dans les deux sens.
 */
class RefList
{
    /**
     * Découpe une liste de références en entrées non vides.
     *
     * Le découpage se fait indifféremment sur les points-virgules et sur les fins de ligne, ce qui
     * permet de traiter avec le même code une valeur déjà stockée, une saisie multi-lignes ou un
     * copier-coller mélangeant les deux.
     *
     * @return list<string>
     */
    public static function split(string $refs): array
    {
        $entrees = preg_split('/[;\r\n]+/', $refs);

        if ($entrees === false)
        {
            return [];
        }

        return array_values(array_filter(array_map('trim', $entrees), static fn(string $ref): bool => $ref !== ''));
    }

    /**
     * Saisie du formulaire (une URL par ligne) => valeur à stocker en base
     */
    public static function toStorage(string $saisie): string
    {
        return implode(';', self::split($saisie));
    }

    /**
     * Valeur stockée en base => contenu du textarea (une URL par ligne)
     */
    public static function toTextarea(string $refCsv): string
    {
        return implode("\n", self::split($refCsv));
    }
}
