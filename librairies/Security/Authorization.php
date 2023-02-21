<?php

namespace Ladecadanse\Security;


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
    function estAuteur(int $idP = 0, int $id = 0, $table): bool
    {
        global $connector;

        $sql_auteur = "SELECT idPersonne FROM " . $table . " WHERE id" . ucfirst($table) . "=" . $id . " AND idPersonne=" . $idP;

        $getP = $connector->query($sql_auteur);

        if ($connector->getNumRows($getP) > 0)
        {
            return true;
        }

        return false;
    }


    public function isPersonneInOrganisateur(int $idP, int $idO): bool
    {
        global $connector;

        $sql = "SELECT idPersonne FROM personne_organisateur WHERE idOrganisateur=" . $idO . " AND idPersonne=" . $idP;

        $getP = $connector->query($sql);

        if ($connector->getNumRows($getP) > 0)
        {
            return true;
        }

            return false;

    }

    function isPersonneInLieuByOrganisateur(int $idP, int $idL): bool
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

        return false;
    }

    public function isPersonneInEvenementByOrganisateur(int $idP = 0, int $idE = 0): bool
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

        return false;
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
        return false;
    }

}
