<?php
namespace Ladecadanse;

class EventWithSeparator
{
    private array $event;
    private bool $show_separator;
    private ?int $separator_hour;
    private bool $is_autres_horaires;
    private ?Separator $separator = null;

    public function __construct(array $event, bool $show_separator, ?int $separator_hour, bool $is_autres_horaires)
    {
        $this->event = $event;
        $this->show_separator = $show_separator;
        $this->separator_hour = $separator_hour;
        $this->is_autres_horaires = $is_autres_horaires;
    }

    public function shouldDisplaySeparator(): bool
    {
        return $this->show_separator;
    }

    public function getCurrentSeparator(): ?Separator
    {
        if (!$this->show_separator)
        {
            return null;
        }

        if ($this->separator === null)
        {
            $this->separator = new Separator($this->separator_hour, $this->is_autres_horaires);
        }

        return $this->separator;
    }

    public function getEvent(): array
    {
        return $this->event;
    }
}

