<?php

declare(strict_types=1);

namespace Ladecadanse\Repository;

use Ladecadanse\Dto\RecordDate;
use PDO;

readonly class EvenementRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<RecordDate>
     */
    public function findUpcomingPublicDates(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT idEvenement, date_derniere_modif, dateAjout
             FROM evenement
             WHERE statut NOT IN ('inactif', 'propose') AND dateEvenement >= :date
             ORDER BY dateEvenement, idEvenement"
        );
        $stmt->execute([':date' => $date]);

        return array_map(
            static fn (array $row): RecordDate => RecordDate::fromRow($row, 'idEvenement', 'date_derniere_modif', 'dateAjout'),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
