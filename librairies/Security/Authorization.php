<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;

class Authorization
{
    /**
     * is
     * EDITOR (AUTHOR)
     * author of event
     * lieu manager
     * in organizers
     * or in lieu organizers
     */
    public function isPersonneAllowedToEditEvenement(array $sessionToReadonly, array $eventWidthIds): bool
    {
        // even : idPersonne, idLieu, idEvenement, 
        return (isset($sessionToReadonly['Sgroupe'])
                        && (
                        $sessionToReadonly['Sgroupe'] <= UserLevel::AUTHOR
                        || $sessionToReadonly['SidPersonne'] == $eventWidthIds['e_idPersonne']
                        || (isset($sessionToReadonly['Saffiliation_lieu']) && $eventWidthIds['e_idLieu'] == $sessionToReadonly['Saffiliation_lieu'])
                        || isset($sessionToReadonly['SidPersonne']) && $this->isPersonneInEvenementByOrganisateur($sessionToReadonly['SidPersonne'], $eventWidthIds['e_idEvenement'])
                        || isset($sessionToReadonly['SidPersonne']) && $this->isPersonneInLieuByOrganisateur($sessionToReadonly['SidPersonne'], $eventWidthIds['e_idLieu'])
                )
            );
    }

    public function isPersonneAllowedToManageEvenement(array $sessionToReadonly, array $eventWidthIds): bool
    {
        return (isset($sessionToReadonly['Sgroupe']) && $sessionToReadonly['Sgroupe'] <= UserLevel::AUTHOR && !empty($eventWidthIds['e_idPersonne']));
    }

    /**
     * Vérifie dans la base si une personne est bien l'auteur d'un événement,
     * une brêve, une description
     *
     * @param string $table (evenement, descriptionlieu, lieu) vérifie si $idP est
     * @param int $idP ID utilisateur à vérifier
     * @param int $id ID entité dont l'auteur est à vérifier
     * auteur de $id
     * @return boolean Si $idP est auteur ou non
     */
    function isAuthor(string $table, int $idP = 0, int $id = 0): bool
    {
        global $connector;

        $tableSanitized = $connector->sanitize($table);

        $sql_auteur = "SELECT idPersonne FROM " . $tableSanitized . " WHERE id" . ucfirst($tableSanitized) . "=" . (int) $id . " AND idPersonne=" . (int) $idP;

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

        $sql = "SELECT idPersonne FROM personne_organisateur WHERE idOrganisateur=" . (int) $idO . " AND idPersonne=" . (int) $idP;

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
        lieu_organisateur.idLieu=" . (int) $idL . " AND idPersonne=" . (int) $idP;

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
        evenement_organisateur.idEvenement=" . (int) $idE . " AND idPersonne=" . (int) $idP;

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
         WHERE affiliation.idPersonne=" . (int) $idP . " AND affiliation.genre='lieu' AND affiliation.idAffiliation=" . (int) $idL);

        if ($connector->getNumRows($req_affPers) > 0)
        {
            return true;
        }
        return false;
    }

}
