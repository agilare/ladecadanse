<?php
namespace Ladecadanse;

class Separator
{
    private ?int $hour;
    private bool $is_no_time;

    public function __construct(?int $hour, bool $is_no_time)
    {
        $this->hour = $hour;
        $this->is_no_time = $is_no_time;
    }

    public function getLabel(string $day_label, string $genre): string
    {
        if ($this->is_no_time)
        {
            return ucfirst($day_label) . ", autres horaires, " . $genre;
        }

        if ($this->hour !== null)
        {
            return ucfirst($day_label) . " dès " . $this->hour . "h, " . $genre;
        }

        return ucfirst($day_label) . ", " . $genre;
    }
}

