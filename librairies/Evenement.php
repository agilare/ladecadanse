<?php

namespace Ladecadanse;

use Ladecadanse\Element;

class Evenement extends Element
{

    /**
     * Démarre la session et inclut un en-tête interdisant de stocker le mot
     * de passe dans le cache de l'utilisateur
     * @access public
     */
    function __construct() {
        global $connector;
        $this->table = "evenement";
        $this->connector = $connector;
    }


    public static function nom_genre($nom)
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
