<?php

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;

class Authorization
{

    public function __construct(private readonly AuthorizationRepository $repository)
    {
    }

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
        if (!isset($_SESSION['user'])) {
            return false;
        }

        return $this->repository->personneExistsInGroup($_SESSION['user'], $groupe);
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
     * Qui peut modifier la fiche d'un organisateur :
     * - EDITOR (AUTHOR) et au-dessus, sur n'importe laquelle ;
     * - un ACTOR, sur celles dont il est membre (personne_organisateur) ;
     * - l'auteur de la fiche (organisateur.idPersonne), quel que soit son niveau.
     *
     * Posée ici pour que la page d'édition et le lien « Modifier cet organisateur » de
     * organisateur.php répondent à la même question : ils la posaient chacun à leur
     * façon, et le formulaire, plus permissif, ouvrait toutes les fiches à tout ACTOR.
     */
    public function isPersonneAllowedToEditOrganisateur(array $sessionToReadonly, int $idOrganisateur): bool
    {
        if (!isset($sessionToReadonly['Sgroupe']))
        {
            return false;
        }

        $idPersonne = (int) ($sessionToReadonly['SidPersonne'] ?? 0);

        return $this->isPersonneEditor($sessionToReadonly)
            || ($sessionToReadonly['Sgroupe'] <= UserLevel::ACTOR && $this->isPersonneInOrganisateur($idPersonne, $idOrganisateur))
            || $this->isAuthor("organisateur", $idPersonne, $idOrganisateur);
    }

    /**
     * Qui peut modifier la fiche d'un lieu :
     * - EDITOR (AUTHOR) et au-dessus, sur n'importe laquelle ;
     * - une personne affiliée au lieu (table `affiliation`) ;
     * - une personne membre d'un organisateur rattaché au lieu.
     *
     * L'auteur de la fiche n'y figure pas, contrairement à ce que fait
     * isPersonneAllowedToEditOrganisateur() : c'est la règle qu'appliquait déjà
     * lieu-edit.php, et l'ajouter ouvrirait des fiches à des comptes qui n'y ont pas
     * accès aujourd'hui.
     *
     * Posée ici pour que le formulaire d'édition et le lien « Modifier ce lieu » de
     * lieu.php répondent à la même question : le formulaire la posait deux fois, dans
     * deux blocs qui se recopiaient.
     */
    public function isPersonneAllowedToEditLieu(array $sessionToReadonly, int $idLieu): bool
    {
        if (!isset($sessionToReadonly['Sgroupe']))
        {
            return false;
        }

        $idPersonne = (int) ($sessionToReadonly['SidPersonne'] ?? 0);

        return $this->isPersonneEditor($sessionToReadonly)
            || $this->isPersonneAffiliatedWithLieu($idPersonne, $idLieu)
            || $this->isPersonneInLieuByOrganisateur($idPersonne, $idLieu);
    }

    /**
     * Créer un lieu reste réservé aux éditeurs : une fiche de lieu est partagée par tous
     * les événements qui s'y déroulent, un doublon se paie donc cher.
     */
    public function isPersonneAllowedToAddLieu(array $sessionToReadonly): bool
    {
        return $this->isPersonneEditor($sessionToReadonly);
    }

    /**
     * Vérifie dans la base si une personne est bien l'auteur d'un événement, d'un lieu
     * ou d'un organisateur.
     *
     * @param string $table (evenement, lieu, organisateur) ; toute autre valeur renvoie false
     * @param int $idP ID utilisateur à vérifier
     * @param int $id ID entité dont l'auteur est à vérifier
     */
    public function isAuthor(string $table, int $idP = 0, int $id = 0): bool
    {
        return $this->repository->isAuthor($table, $idP, $id);
    }

    public function isPersonneInOrganisateur(int $idP, int $idO): bool
    {
        return $this->repository->isPersonneInOrganisateur($idP, $idO);
    }

    public function isPersonneInLieuByOrganisateur(int $idP, int $idL): bool
    {
        return $this->repository->isPersonneInLieuByOrganisateur($idP, $idL);
    }

    public function isPersonneInEvenementByOrganisateur(int $idP = 0, int $idE = 0): bool
    {
        return $this->repository->isPersonneInEvenementByOrganisateur($idP, $idE);
    }

    public function isPersonneAffiliatedWithLieu(int $idP, int $idL): bool
    {
        return $this->repository->isPersonneAffiliatedWithLieu($idP, $idL);
    }

}
