<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace Ladecadanse\Security;

/**
 * Description of Authorization
 *
 * @author michel
 */
class Authorization
{

    /**
     * Vérifie dans la base si une personne est bien l'auteur d'un événement,
     * une brêve, une description
     *
     * @param int $didP ID utilisateur à vérifier
     * @param int $id ID entité dont l'auteur est à vérifier
     * @param string $table (evenement, descriptionlieu, lieu) vérifie si $idP est
     * auteur de $id
     * @return boolean Si $idP est auteur ou non
     */
    function estAuteur($idP = 0, $id = 0, $table)
    {
        global $connector;

        $sql_auteur = "SELECT idPersonne FROM " . $table . " WHERE id" . ucfirst($table) . "=" . $id . " AND idPersonne=" . $idP;

        $getP = $connector->query($sql_auteur);

        if ($connector->getNumRows($getP) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }


    public function isPersonneInOrganisateur($idP, $idO): bool
    {
        global $connector;

        $sql = "SELECT idPersonne FROM personne_organisateur WHERE idOrganisateur=" . $idO . " AND idPersonne=" . $idP;

        $getP = $connector->query($sql);

        if ($connector->getNumRows($getP) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function isPersonneInLieuByOrganisateur($idP, $idL): bool
    {
        global $connector;

        $sql = "SELECT idPersonne FROM personne_organisateur, lieu_organisateur
        WHERE personne_organisateur.idOrganisateur=lieu_organisateur.idOrganisateur AND
        lieu_organisateur.idLieu=" . $idL . " AND idPersonne=" . $idP;

        $getP = $connector->query($sql);

        if ($connector->getNumRows($getP) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function isPersonneInEvenementByOrganisateur($idP = 0, $idE = 0): bool
    {
        global $connector;

        $sql = "SELECT idPersonne FROM personne_organisateur, evenement_organisateur
        WHERE personne_organisateur.idOrganisateur=evenement_organisateur.idOrganisateur AND
        evenement_organisateur.idEvenement=" . $idE . " AND idPersonne=" . $idP;

        $getP = $connector->query($sql);

        if ($connector->getNumRows($getP) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function isPersonneAffiliatedWithLieu(int $idP, int $idL): bool
    {
        global $connector;

        $req_affPers = $connector->query("SELECT lieu.idLieu, lieu.nom
        FROM affiliation INNER JOIN lieu ON affiliation.idAffiliation=lieu.idLieu
         WHERE affiliation.idPersonne=" . $idP . " AND affiliation.genre='lieu' AND affiliation.idAffiliation=" . $idL);

        if ($connector->getNumRows($req_affPers) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

}
