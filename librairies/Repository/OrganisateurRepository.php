<?php

declare(strict_types=1);

namespace Ladecadanse\Repository;

use Ladecadanse\Dto\RecordDate;
use PDO;

readonly class OrganisateurRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<RecordDate>
     */
    public function findPublicDates(): array
    {
        $stmt = $this->pdo->query(
            "SELECT idOrganisateur, date_derniere_modif, date_ajout
             FROM organisateur
             WHERE statut IN ('actif', 'ancien')
             ORDER BY idOrganisateur"
        );

        return array_map(
            static fn (array $row): RecordDate => RecordDate::fromRow($row, 'idOrganisateur', 'date_derniere_modif', 'date_ajout'),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
