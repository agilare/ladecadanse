<?php
namespace Ladecadanse;

class Separator
{
    private ?int $hour;
    private bool $is_autres_horaires;

    public function __construct(?int $hour, bool $is_autres_horaires)
    {
        $this->hour = $hour;
        $this->is_autres_horaires = $is_autres_horaires;
    }

    public function getLabel(string $day_label, string $genre): string
    {
        if ($this->is_autres_horaires)
        {
            return ucfirst($day_label) . ", autres horaires, " . $genre;
        }

        if ($this->hour !== null)
        {
            return ucfirst($day_label) . " " . $this->hour . "h, " . $genre;
        }

        return ucfirst($day_label) . ", " . $genre;
    }
}

