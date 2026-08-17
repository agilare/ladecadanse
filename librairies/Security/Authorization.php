<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;

class Authorization
{

    /**
     * Le visiteur courant appartient-il à un groupe au moins aussi privilégié que $groupe ?
     *
     * Vient de Sentry, qui mêlait authentification et autorisation (#156). La requête est
     * conservée telle quelle : elle revalide à chaque appel que le compte existe toujours
     * et qu'il est actif, ce dont Sentry::checkSession() ne se charge pas (sa valeur de
     * retour est ignorée par le constructeur).
     */
    public function checkGroup(int $groupe = UserLevel::MEMBER): bool
    {
        global $connector;

        if (!isset($_SESSION['user'])) {
            return false;
        }

        $getUser = $connector->query("
        SELECT idPersonne, pseudo, mot_de_passe, cookie, groupe, email, gds
        FROM personne
        WHERE pseudo = '" . $connector->sanitize($_SESSION['user']) . "' AND groupe <= " . (int) $groupe . " AND statut='actif'");

        return $connector->getNumRows($getUser) == 1;
    }

    public function isPersonneEditor(array $sessionToReadonly): bool
    {
        return (isset($sessionToReadonly['Sgroupe']) && $sessionToReadonly['Sgroupe'] <= UserLevel::AUTHOR);
    }
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
                        || (!empty($eventWidthIds['e_idPersonne']) && $sessionToReadonly['SidPersonne'] == $eventWidthIds['e_idPersonne'])
                        || (!empty($eventWidthIds['e_idLieu']) && isset($sessionToReadonly['Saffiliation_lieu']) && $eventWidthIds['e_idLieu'] == $sessionToReadonly['Saffiliation_lieu'])
                        || (!empty($eventWidthIds['e_idPersonne']) && isset($sessionToReadonly['SidPersonne']) && $this->isPersonneInEvenementByOrganisateur($sessionToReadonly['SidPersonne'], $eventWidthIds['e_idEvenement']))
                        || (!empty($eventWidthIds['e_idLieu']) && isset($sessionToReadonly['SidPersonne']) && $this->isPersonneInLieuByOrganisateur($sessionToReadonly['SidPersonne'], $eventWidthIds['e_idLieu']))
                )
            );
    }

    /**
     * Droit d'édition effectif : droit de principe, et événement pas encore archivé.
     *
     * Un événement passé est une archive : le rouvrir pour en changer la date reviendrait
     * à écraser l'événement d'origine. Seuls les éditeurs (Sgroupe <= AUTHOR) peuvent
     * encore le modifier ; les autres gardent Copier — le geste correct pour reprogrammer —
     * et Dépublier.
     *
     * À ne pas confondre avec isPersonneAllowedToEditEvenement(), qui gouverne aussi la
     * visibilité des événements 'propose'/'inactif' et l'accès à la copie : ces deux
     * usages ne doivent PAS être restreints par la date.
     *
     * @param array $eventWidthIds en plus des clés attendues par isPersonneAllowedToEditEvenement() :
     *                             e_dateEvenement, et e_horaire_fin si disponible
     */
    public function isPersonneAllowedToEditEvenementNow(array $sessionToReadonly, array $eventWidthIds): bool
    {
        if (!$this->isPersonneAllowedToEditEvenement($sessionToReadonly, $eventWidthIds))
        {
            return false;
        }

        return $this->isPersonneEditor($sessionToReadonly)
            || !DateHelper::isEvenementPast($eventWidthIds['e_dateEvenement'], $eventWidthIds['e_horaire_fin'] ?? null);
    }

    public function isPersonneAllowedToManageEvenement(array $sessionToReadonly, array $eventWidthIds): bool
    {
        return ($this->isPersonneEditor($sessionToReadonly) && !empty($eventWidthIds['e_idPersonne']));
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
