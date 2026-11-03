<?php

declare(strict_types=1);

namespace Ladecadanse\Repository;

use Ladecadanse\Dto\RecordDate;
use PDO;

readonly class LieuRepository
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
            "SELECT idLieu, date_derniere_modif, dateAjout
             FROM lieu
             WHERE statut IN ('actif', 'ancien')
             ORDER BY idLieu"
        );

        return array_map(
            static fn (array $row): RecordDate => RecordDate::fromRow($row, 'idLieu', 'date_derniere_modif', 'dateAjout'),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
