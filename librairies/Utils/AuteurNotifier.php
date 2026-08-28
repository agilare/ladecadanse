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
 * Les morceaux de texte fixes (objet, phrase d'introduction, formules du
 * template) sont exposés séparément pour que evenement-edit.php puisse en
 * afficher un aperçu sans risquer de diverger du mail réellement envoyé.
 *
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class AuteurNotifier
{
    private const TEMPLATE = 'event-notify-auteur-mail-body';

    /** Marqueur temporaire pour découper le template autour de %corps% */
    private const CORPS_SENTINEL = '@@CORPS@@';

    public function __construct(
        private readonly TemplateEngine $templateEngine,
        private readonly Mailing $mailing,
    ) {
    }

    /**
     * Objet du mail.
     */
    public static function buildSubject(string $eventTitre, string $eventDateEvenement): string
    {
        $dateFr = self::dateFr($eventDateEvenement);

        return $dateFr === ''
            ? "La décadanse : votre événement \"{$eventTitre}\""
            : "La décadanse : votre événement \"{$eventTitre}\" du {$dateFr}";
    }

    /**
     * Phrase d'introduction du corps, celle que suivent les motifs puis le message.
     */
    public static function buildIntro(string $eventTitre, string $eventUrl, string $eventDateCreation): string
    {
        $dateFr = self::dateFr($eventDateCreation);
        $ajouteLe = $dateFr === '' ? '' : " que vous avez ajouté le {$dateFr}";

        return "Concernant l'événement \"{$eventTitre}\" {$eventUrl}{$ajouteLe} :";
    }

    /**
     * Texte du template situé avant et après %corps% (« Bonjour, » et la
     * formule de salutation), pour l'aperçu côté formulaire.
     *
     * @return array{0: string, 1: string}
     */
    public static function buildTemplateParts(TemplateEngine $templateEngine): array
    {
        $rendered = $templateEngine->render(self::TEMPLATE, ['corps' => self::CORPS_SENTINEL]);
        $parts = explode(self::CORPS_SENTINEL, $rendered, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Date en toutes lettres, tolérante : le formulaire peut être réaffiché
     * avec une date vide ou invalide (erreur de validation), et dans ce cas
     * mieux vaut ne rien afficher que la date du jour.
     */
    private static function dateFr(string $date): string
    {
        if (trim($date) === '')
        {
            return '';
        }

        try
        {
            return DateHelper::isoToFr($date, 'annee', true, false, false);
        }
        catch (\Exception $e)
        {
            return '';
        }
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

        $subject = self::buildSubject($eventTitre, $eventDateEvenement);
        $corps = self::buildIntro($eventTitre, $eventUrl, $eventDateCreation);

        if (count($motifLabels) > 0)
        {
            $corps .= "\n";
        }

        foreach ($motifLabels as $label)
        {
            $corps .= "\n - {$label}";
        }

        $message = trim($message);
        if ($message !== '')
        {
            $corps .= "\n\n{$message}";
        }

        $body = $this->templateEngine->render(self::TEMPLATE, [
            'corps' => $corps,
        ]);

        return $this->mailing->toUser($authorEmail, $subject, $body, ['email' => $adminEmail, 'name' => $adminName]);
    }
}
