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

    public function getIterator(): \Traversable
    {
        $previous_even_hour = null;
        $has_shown_autres_horaires = false;
        $genre_even_nb = 0;
        
        foreach ($this->events as $event)
        {
            $genre_even_nb++;
            $show_separator = false;
            $separator_hour = null;
            $is_autres_horaires = false;
            
            if (!$this->is_chronological_order)
            {
                if (($genre_even_nb % 2 != 0) && $genre_even_nb > 1)
                {
                    $show_separator = true;
                }
                
                yield new EventWithSeparator($event, $show_separator, $separator_hour, $is_autres_horaires);
                continue;
            }
            
            $horaire_debut = $event['e_horaire_debut'];
            $is_no_time = ($horaire_debut == self::NO_TIME_VALUE || $horaire_debut == $this->date_next_day . self::NO_TIME_SUFFIX);
            
            if ($is_no_time)
            {
                if (!$has_shown_autres_horaires)
                {
                    $show_separator = true;
                    $is_autres_horaires = true;
                    $has_shown_autres_horaires = true;
                }
                
                yield new EventWithSeparator($event, $show_separator, $separator_hour, $is_autres_horaires);
                continue;
            }
            
            $even_hour = $this->calculateEvenHour($horaire_debut);
            
            if ($previous_even_hour !== null && $even_hour != $previous_even_hour)
            {
                $show_separator = true;
                $separator_hour = $even_hour;
            }
            
            $previous_even_hour = $even_hour;
            
            yield new EventWithSeparator($event, $show_separator, $separator_hour, $is_autres_horaires);
        }
    }

    private function calculateEvenHour(string $horaire_debut): int
    {
        $time_part = mb_substr($horaire_debut, 11, 5);
        $time_parts = explode(":", $time_part);
        $hour = (int) $time_parts[0];
        
        return $hour - ($hour % 2);
    }
}

