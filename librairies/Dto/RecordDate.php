<?php

declare(strict_types=1);

namespace Ladecadanse\Dto;

use DateTimeImmutable;

final readonly class RecordDate
{
    // écarte le « 0000-00-00 » de MySQL : une seule date invalide fait rejeter un sitemap
    private const string DATE_FLOOR = '2000-01-01';

    public function __construct(
        public int $id,
        public ?DateTimeImmutable $modifiedAt,
    ) {
    }

    /**
     * @param array<string, int|string|null> $row
     * @param string ...$dateColumns par ordre de préférence
     */
    public static function fromRow(array $row, string $idColumn, string ...$dateColumns): self
    {
        $floor = new DateTimeImmutable(self::DATE_FLOOR);

        foreach ($dateColumns as $column)
        {
            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row[$column] ?? ''));

            if ($date !== false && $date > $floor)
            {
                return new self((int) $row[$idColumn], $date);
            }
        }

        return new self((int) $row[$idColumn], null);
    }
}
