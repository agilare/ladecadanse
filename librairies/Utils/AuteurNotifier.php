<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse\Utils;

use Ladecadanse\TemplateEngine;

/**
 * Sends the "l'administrateur a modifié votre événement" notification to an
 * event's author. Takes plain scalars (not an Evenement/session object) so it
 * can be reused by other callers (quick-edit, bulk-edit) that resolve the
 * target author differently.
 *
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class AuteurNotifier
{
    public function __construct(
        private readonly TemplateEngine $templateEngine,
        private readonly Mailing $mailing,
    ) {
    }

    /**
     * @param string[]             $motifKeys      keys already whitelisted against $motifsCatalogue by the caller
     * @param array<string,string> $motifsCatalogue key => French label, e.g. $glo_motifs_notification_auteur
     */
    public function notify(
        string $authorEmail,
        string $authorName,
        string $eventTitre,
        string $eventDateEvenement,
        string $eventDateCreation,
        string $eventUrl,
        string $adminEmail,
        string $adminName,
        array $motifKeys,
        string $message,
        array $motifsCatalogue
    ): bool {
        $motifLabels = array_values(array_intersect_key($motifsCatalogue, array_flip($motifKeys)));

        $dateFr = DateHelper::isoToFr($eventDateEvenement, 'annee', true, false, false);
        $subject = "La décadanse : votre événement \"{$eventTitre}\" du {$dateFr}";

        $corps = "Concernant l'événement \"{$eventTitre}\" {$eventUrl} que vous avez ajouté le "
            . DateHelper::isoToFr($eventDateCreation, 'annee', true, false, false) . " :";

        foreach ($motifLabels as $label)
        {
            $corps .= "\n - {$label}";
        }

        $message = trim($message);
        if ($message !== '')
        {
            $corps .= "\n\n{$message}";
        }

        $body = $this->templateEngine->render('event-notify-auteur-mail-body', [
            'corps' => $corps,
        ]);

        return $this->mailing->toUser($authorEmail, $subject, $body, ['email' => $adminEmail, 'name' => $adminName]);
    }
}
