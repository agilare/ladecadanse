<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;

class EvenementCalendarRenderer
{
    private string $title;
    private string $location;
    private string $description;
    private string $eventUrl;
    private string $startCompact;
    private string $startIso;
    private string $endCompact;
    private string $endIso;
    private int $eventId;

    public function __construct(array $event, string $site_full_url)
    {
        $even_lieu = Evenement::getLieu($event);

        $this->eventId = (int) $event['e_idEvenement'];
        $this->title = $event['e_titre'];
        $this->location = $even_lieu['nom'] . " - " . HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse']);
        $this->description = mb_substr(strip_tags($event['e_description']), 0, 500);
        $this->eventUrl = $site_full_url . "event/evenement.php?idE=" . $this->eventId;

        $hasStart = ($event['e_horaire_debut'] != "0000-00-00 00:00:00");
        $hasEnd = ($event['e_horaire_fin'] != "0000-00-00 00:00:00");

        $startRaw = $hasStart ? $event['e_horaire_debut'] : $event['e_dateEvenement'];
        $this->startCompact = date('Ymd\THis', strtotime($startRaw));
        $this->startIso = date('Y-m-d\TH:i:s', strtotime($startRaw));

        $this->endCompact = '';
        $this->endIso = '';
        if ($hasEnd)
        {
            $this->endCompact = date('Ymd\THis', strtotime($event['e_horaire_fin']));
            $this->endIso = date('Y-m-d\TH:i:s', strtotime($event['e_horaire_fin']));
        }
    }

    /**
     * @return array<string, string> URLs keyed by provider name
     */
    public function getLinks(): array
    {
        return [
            'google' => $this->googleUrl(),
            'outlook' => $this->outlookUrl(),
            'office365' => $this->office365Url(),
            'yahoo' => $this->yahooUrl(),
            'ical' => $this->icalUrl(),
        ];
    }

    public function googleUrl(): string
    {
        $dates = $this->endCompact
            ? ($this->startCompact . '/' . $this->endCompact)
            : ($this->startCompact . '/' . $this->startCompact);

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . rawurlencode($this->title)
            . '&dates=' . rawurlencode($dates)
            . '&location=' . rawurlencode($this->location)
            . '&details=' . rawurlencode($this->details());
    }

    public function outlookUrl(): string
    {
        return 'https://outlook.live.com/calendar/0/action/compose?' . $this->outlookParams();
    }

    public function office365Url(): string
    {
        return 'https://outlook.office.com/calendar/0/action/compose?' . $this->outlookParams();
    }

    public function yahooUrl(): string
    {
        return 'https://calendar.yahoo.com/?v=60'
            . '&title=' . rawurlencode($this->title)
            . '&st=' . rawurlencode($this->startCompact)
            . ($this->endCompact ? '&et=' . rawurlencode($this->endCompact) : '')
            . '&in_loc=' . rawurlencode($this->location)
            . '&desc=' . rawurlencode($this->details());
    }

    public function icalUrl(): string
    {
        return '/event/to-ics.php?idE=' . $this->eventId;
    }

    private function outlookParams(): string
    {
        return 'subject=' . rawurlencode($this->title)
            . '&startdt=' . rawurlencode($this->startIso)
            . ($this->endIso ? '&enddt=' . rawurlencode($this->endIso) : '')
            . '&location=' . rawurlencode($this->location)
            . '&body=' . rawurlencode($this->details());
    }

    private function details(): string
    {
        return $this->description . "\n\n" . $this->eventUrl;
    }
}
