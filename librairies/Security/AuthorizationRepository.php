<?php

namespace Ladecadanse\Security;

use PDO;

/**
 * Accès base de données pour les vérifications d'autorisation.
 *
 * Isolé de {@see Authorization} (#157), qui ne conserve que la logique sur $_SESSION.
 * Toutes les requêtes passent par des requêtes préparées PDO.
 */
class AuthorizationRepository
{
    /**
     * Tables sur lesquelles isAuthor() sait vérifier une propriété, et leur colonne d'identifiant.
     *
     * Un nom de table ne pouvant pas être un paramètre lié, cette liste blanche remplace
     * l'ancienne concaténation `id . ucfirst($table)` : une table absente renvoie false (accès
     * refusé) au lieu de construire une requête à partir d'une entrée arbitraire. Seules les
     * trois tables réellement passées par les appelants figurent ici ; `descriptionlieu`, citée
     * par l'ancien docblock, n'a jamais fonctionné (sa clé est idLieu, pas idDescriptionlieu) et
     * n'est appelée nulle part.
     */
    private const AUTHOR_TABLES = [
        'evenement'    => 'idevenement',
        'lieu'         => 'idLieu',
        'organisateur' => 'idOrganisateur',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Existe-t-il un compte actif de pseudo $pseudo dans un groupe au moins aussi
     * privilégié que $maxGroupe ?
     */
    public function personneExistsInGroup(string $pseudo, int $maxGroupe): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM personne
             WHERE pseudo = :pseudo AND groupe <= :groupe AND statut = 'actif'
             LIMIT 1"
        );
        $stmt->execute([':pseudo' => $pseudo, ':groupe' => $maxGroupe]);

        return $stmt->fetchColumn() !== false;
    }

    public function isAuthor(string $table, int $idP, int $id): bool
    {
        $idColumn = self::AUTHOR_TABLES[$table] ?? null;
        if ($idColumn === null) {
            return false;
        }

        // $table et $idColumn proviennent de AUTHOR_TABLES, jamais de l'entrée
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM `$table` WHERE `$idColumn` = :id AND idPersonne = :idP LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':idP' => $idP]);

        return $stmt->fetchColumn() !== false;
    }

    public function isPersonneInOrganisateur(int $idP, int $idO): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM personne_organisateur
             WHERE idOrganisateur = :idO AND idPersonne = :idP LIMIT 1"
        );
        $stmt->execute([':idO' => $idO, ':idP' => $idP]);

        return $stmt->fetchColumn() !== false;
    }

    public function isPersonneInLieuByOrganisateur(int $idP, int $idL): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM personne_organisateur po
             JOIN lieu_organisateur lo ON po.idOrganisateur = lo.idOrganisateur
             WHERE lo.idLieu = :idL AND po.idPersonne = :idP LIMIT 1"
        );
        $stmt->execute([':idL' => $idL, ':idP' => $idP]);

        return $stmt->fetchColumn() !== false;
    }

    public function isPersonneInEvenementByOrganisateur(int $idP, int $idE): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM personne_organisateur po
             JOIN evenement_organisateur eo ON po.idOrganisateur = eo.idOrganisateur
             WHERE eo.idEvenement = :idE AND po.idPersonne = :idP LIMIT 1"
        );
        $stmt->execute([':idE' => $idE, ':idP' => $idP]);

        return $stmt->fetchColumn() !== false;
    }

    public function isPersonneAffiliatedWithLieu(int $idP, int $idL): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM affiliation a
             JOIN lieu l ON a.idAffiliation = l.idLieu
             WHERE a.idPersonne = :idP AND a.genre = 'lieu' AND a.idAffiliation = :idL LIMIT 1"
        );
        $stmt->execute([':idP' => $idP, ':idL' => $idL]);

        return $stmt->fetchColumn() !== false;
    }
}
