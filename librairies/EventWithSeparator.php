<?php
namespace Ladecadanse;

/**
 * Wraps an event array with an optional Separator for display in the agenda view.
 * Used by EvenementWithSeparatorCollection to pair each event with its separator
 * when iterating over a day's events grouped by time blocks.
 */
class EventWithSeparator
{
    private array $event;
    private ?Separator $separator;

    public function __construct(array $event, ?Separator $separator = null)
    {
        $this->event = $event;
        $this->separator = $separator;
    }

    public function shouldDisplaySeparator(): bool
    {
        return $this->separator !== null;
    }

    public function getSeparator(): ?Separator
    {
        return $this->separator;
    }

    public function getEvent(): array
    {
        return $this->event;
    }
}
