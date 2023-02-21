<?php

namespace Ladecadanse;

use Ladecadanse\Element;

class Evenement extends Element
{

    function __construct() {

        parent::__construct();
        $this->table = "evenement";
    }

    public static function nom_genre($nom): string
    {
        if ($nom == 'fête')
        {
            return 'fêtes';
        }
        else if ($nom == 'cinéma')
        {
            return 'ciné';
        }
        else
        {
            return $nom;
        }
    }

    /**
     * Marque le titre de l'?v?nement selon le statut qui lui attribu?
     *
     *
     * @param string $titre Titre de l'?v?nement
     * @param string $statut Statut actuel de l'?v?nement
     * @return string Le titre marqu?
     */
    public static function titre_selon_statut($titre, $statut)
    {
        $titre_avec_statut = $titre;

        if ($statut == "annule")
        {
            $titre_avec_statut = '<strike>' . $titre . '</strike> ANNULÉ';
        }
        if ($statut == "complet")
        {
            $titre_avec_statut = '<em>' . $titre . '</em> COMPLET';
        }

        return $titre_avec_statut;
    }

}
