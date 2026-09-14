<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Fieldset « E-mail à l'auteur » de evenement-edit.php (issue #149) :
 * qui le voit, et quelles règles de validation le serveur impose.
 *
 * Aucun cas n'écrit en base ni n'envoie de mail : chaque POST vide le titre,
 * donc `$verif->nbErreurs() > 0` et l'UPDATE n'est jamais atteint. Ne pas
 * soumettre de formulaire valide ici : la suite `site` est en lecture seule.
 */
class EvenementNotifierAuteurCest
{
    /**
     * Catalogue de app/config.php ($glo_motifs_notification_auteur), volontairement
     * recopié : c'est justement le contrat que ces tests verrouillent.
     */
    private const MOTIFS = [
        'depublie_charte',
        'depublie_doublon',
        'categorie_deplacee',
        'erreurs_corrigees',
        'lieu_remplace',
        'organisateur_ajoute',
        'image_ajoutee',
    ];

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_EVENT_ID_AUTEUR'
        );
    }

    /**
     * Un admin qui édite l'événement d'un auteur voit le fieldset complet :
     * le catalogue de motifs fait autorité côté serveur, et l'adresse de
     * l'auteur original est rappelée (et non celle de la session courante).
     */
    public function fieldsetVisibleForAdmin(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#notif_motifs');
        $I->seeElement('#notif_message');

        foreach (self::MOTIFS as $motif)
        {
            $I->seeElement('#notif_motifs option[value="' . $motif . '"]');
        }

        $I->seeElement('a[href^="mailto:"]');
    }

    /**
     * L'aperçu du mail encadre les champs de saisie : objet non modifiable,
     * puis le texte fixe du début, puis (après le message) la signature.
     * Le contenu vient de AuteurNotifier, donc ces assertions vérifient
     * surtout que l'aperçu est bien rendu et reste non soumissible.
     */
    public function apercuDuMailEstAffiche(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));

        $I->seeResponseCodeIs(HttpCode::OK);

        // objet : affiché, désactivé, et sans name pour ne jamais être posté
        $I->seeElement('#notif_objet[disabled]');
        $I->dontSeeElement('#notif_objet[name]');
        $I->seeElement('#notif_objet[value^="La décadanse : votre événement"]');

        // début avant le select des motifs, fin après le textarea du message
        $I->see('Bonjour,', '(//p[@class="notif-apercu"])[1]');
        $I->see("Concernant l'événement", '(//p[@class="notif-apercu"])[1]');
        $I->see('Meilleures salutations', '(//p[@class="notif-apercu"])[2]');
    }

    /**
     * En création, il n'y a pas d'auteur à prévenir : le fieldset ne doit pas
     * apparaître ($can_notify_auteur exige action=editer|update, evenement-edit.php:133).
     */
    public function fieldsetHiddenOnAjouter(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/evenement-edit.php?action=ajouter');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
        $I->dontSeeElement('#notif_motifs');
        $I->dontSeeElement('#notif_message');
    }

    /**
     * Un acteur qui édite son propre événement ne doit pas pouvoir s'envoyer
     * une notification : le fieldset est réservé au groupe <= ADMIN.
     */
    public function fieldsetHiddenForAuthorHimself(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_EVENT_ID_ACTOR_OWN'
        );

        $I->loginAsActor();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_ACTOR_OWN')));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
        $I->dontSeeElement('#notif_motifs');
        $I->dontSeeElement('#notif_message');
    }

    /**
     * Notifier l'auteur est facultatif : l'admin peut enregistrer sans cocher de
     * motif ni écrire de message (evenement-edit.php:367, obligatoire = 0).
     * Une version antérieure rendait le message obligatoire quand aucun motif
     * n'était coché ; ce test verrouille l'abandon de cette règle.
     *
     * Le titre est vidé pour garantir une erreur ailleurs dans le formulaire :
     * on observe la validation des champs de notification sans rien enregistrer.
     */
    public function notifIsOptional(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));
        $I->seeElement('#notif_message');

        $I->submitForm('#ajouter_editer', [
            'titre' => '', // titre est obligatoire (evenement-edit.php:247) : erreur garantie
            'notif_message' => '',
        ]);

        $I->seeElement('#ajouter_editer');

        // pas d'astérisque : le champ n'est jamais signalé comme obligatoire
        $I->see('Message', 'label[for=notif_message]');
        $I->dontSee('*', 'label[for=notif_message]');

        // et aucune erreur portée par le fieldset, malgré motif et message vides
        $this->dontSeeNotifError($I);
    }

    /**
     * Le <select> des motifs est rendu depuis le catalogue serveur
     * ($glo_motifs_notification_auteur), jamais depuis le POST : une clé forgée
     * n'est ni proposée, ni sélectionnée, ni réfléchie ailleurs dans la page.
     * Le motif valide soumis en même temps prouve que le <select> se repeuple
     * bien, donc que l'absence de la clé forgée est significative.
     *
     * Portée : ce test ne couvre PAS l'array_intersect d'evenement-edit.php:362.
     * Vérifié par mutation le 2026-08-07 — retirer ce filtre ne change rien à la
     * réponse HTTP : les motifs retenus ne servent qu'à composer le mail, envoyé
     * seulement sur un enregistrement valide, hors de portée d'une suite en
     * lecture seule. Couvrir ce filtre demanderait de l'extraire dans une classe
     * testable unitairement (AuteurNotifier).
     */
    public function forgedMotifKeyIsNeverEchoedBack(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));
        $I->seeElement('#notif_message');

        $I->submitForm('#ajouter_editer', [
            'titre' => '', // titre est obligatoire (evenement-edit.php:247) : erreur garantie
            'notif_motifs' => ['erreurs_corrigees', 'cle_forgee'],
            'notif_message' => '',
        ]);

        $I->seeElement('#ajouter_editer');

        // la clé forgée est absente du catalogue rendu, et n'est pas sélectionnée
        $I->dontSeeElement('#notif_motifs option[value="cle_forgee"]');
        $I->seeElement('#notif_motifs option[value="erreurs_corrigees"][selected]');
        $I->seeNumberOfElements('#notif_motifs option[selected]', 1);

        // ni réfléchie ailleurs (attribut, script, message d'erreur…)
        $I->dontSee('cle_forgee');
    }

    /**
     * Une erreur de validation ailleurs dans le formulaire ne doit pas faire
     * perdre à l'admin ce qu'il a saisi pour l'auteur.
     */
    public function notifFieldsArePreservedOnValidationError(SiteTester $I)
    {
        $message = 'Message de test, non enregistré (titre volontairement vide).';

        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));
        $I->seeElement('#notif_message');

        $I->submitForm('#ajouter_editer', [
            'titre' => '', // titre est obligatoire (evenement-edit.php:247) : erreur garantie
            'notif_motifs' => ['erreurs_corrigees'],
            'notif_message' => $message,
        ]);

        $I->seeElement('#ajouter_editer');
        $I->seeElement('#notif_motifs option[value="erreurs_corrigees"][selected]');
        $I->seeInField('#notif_message', $message);
    }

    private function editUrl(int $idEvenement): string
    {
        return '/evenement-edit.php?action=editer&idE=' . $idEvenement;
    }

    /**
     * Aucune erreur de validation n'est portée par le fieldset de notification.
     *
     * XPath plutôt que "#notif_message + .msg" : le <div class="msg"> est écrit
     * dans le <p> du champ, mais un <div> ferme implicitement un <p>, donc le
     * parseur HTML le remonte au niveau du fieldset.
     */
    private function dontSeeNotifError(SiteTester $I): void
    {
        $I->dontSeeElement('//fieldset[.//textarea[@id="notif_message"]]//div[@class="msg"]');
    }
}
