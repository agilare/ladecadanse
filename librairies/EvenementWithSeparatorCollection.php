<?php
namespace Ladecadanse;

class EvenementWithSeparatorCollection implements \IteratorAggregate
{
    private const NO_TIME_VALUE = "0000-00-00 00:00:00";
    private const NO_TIME_SUFFIX = " 06:00:01";

    private array $events;
    private bool $is_chronological_order;
    private string $date_current;
    private string $date_next_day;

    public function __construct(array $events, bool $is_chronological_order, string $date_current)
    {
        $this->events = $events;
        $this->is_chronological_order = $is_chronological_order;
        $this->date_current = $date_current;
        $this->date_next_day = date_lendemain($date_current);
    }

    /**
     * Iterates over events, wrapping each in EventWithSeparator.
     * In chronological mode: inserts a time separator when the hour changes
     * (e.g. 18h, 19h, 20h) and a special separator for events without a start time.
     * In non-chronological mode: inserts a visual separator every 2 events.
     */
    public function getIterator(): \Traversable
    {
        $previous_hour = null;
        $has_shown_no_time_separator = false;
        $event_count = 0;

        foreach ($this->events as $event)
        {
            $event_count++;
            $separator = null;
            $is_no_time = false;

            if (!$this->is_chronological_order)
            {
                if (($event_count % 2 != 0) && $event_count > 1)
                {
                    $separator = new Separator(null, false);
                }

                yield new EventWithSeparator($event, $separator);
                continue;
            }

            $horaire_debut = $event['e_horaire_debut'];
            $is_no_time = ($horaire_debut == self::NO_TIME_VALUE || $horaire_debut == $this->date_next_day . self::NO_TIME_SUFFIX);

            if ($is_no_time)
            {
                if (!$has_shown_no_time_separator)
                {
                    $separator = new Separator(null, true);
                    $has_shown_no_time_separator = true;
                }

                yield new EventWithSeparator($event, $separator);
                continue;
            }

            $hour = $this->getHour($horaire_debut);

            if ($previous_hour !== null && $hour != $previous_hour)
            {
                $separator = new Separator($hour, false);
            }

            $previous_hour = $hour;

            yield new EventWithSeparator($event, $separator);
        }
    }

    /**
     * Extracts the hour from a datetime string.
     * e.g. "2024-01-15 19:30:00" -> 19
     */
    private function getHour(string $horaire_debut): int
    {
        $time_part = mb_substr($horaire_debut, 11, 5);
        $time_parts = explode(":", $time_part);

        return (int) $time_parts[0];
    }
}

