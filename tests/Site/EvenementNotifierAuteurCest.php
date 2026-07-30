<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Fieldset « E-mail à l'auteur » de evenement-edit.php (issue #149) :
 * qui le voit, et quelles règles de validation le serveur impose.
 *
 * Aucun cas n'écrit en base ni n'envoie de mail : les POST sont volontairement
 * invalides, donc `$verif->nbErreurs() > 0` et l'UPDATE n'est jamais atteint.
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
     * En création, il n'y a pas d'auteur à prévenir : le fieldset ne doit pas
     * apparaître ($can_notify_auteur exige action=editer|update, evenement-edit.php:124).
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
     * Règle métier non évidente (evenement-edit.php:346) : quand l'admin peut
     * notifier l'auteur, il doit soit cocher un motif, soit écrire un message —
     * il ne peut pas enregistrer en silence. Facile à casser lors d'un refactor
     * de la validation, d'où ce test explicite.
     */
    public function messageRequiredWhenNoMotif(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));
        $I->seeElement('#notif_message');

        // resoumission à l'identique, sans motif ni message
        $I->submitForm('#ajouter_editer', ['notif_message' => '']);

        $this->seeNotifMessageIsRequired($I);
    }

    /**
     * Contrôle anti-contournement du cas précédent : une clé de motif forgée est
     * filtrée par l'array_intersect sur le catalogue (evenement-edit.php:340),
     * donc le message redevient obligatoire. Sans ce filtre, le POST passerait.
     */
    public function unknownMotifKeysAreRejected(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->editUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));
        $I->seeElement('#notif_message');

        $I->submitForm('#ajouter_editer', [
            'notif_motifs' => ['cle_forgee'],
            'notif_message' => '',
        ]);

        $this->seeNotifMessageIsRequired($I);
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
            'titre' => '', // titre est obligatoire (evenement-edit.php:225) : erreur garantie
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
     * Le formulaire est réaffiché avec l'erreur portée par notif_message :
     * la validation a échoué, donc rien n'a été enregistré ni envoyé.
     */
    private function seeNotifMessageIsRequired(SiteTester $I): void
    {
        // formulaire réaffiché => l'UPDATE n'a pas eu lieu
        $I->seeElement('#ajouter_editer');

        // l'astérisque du label n'est rendue que si aucun motif n'a été retenu
        $I->see('Message *', 'label[for=notif_message]');
        $I->dontSeeElement('#notif_motifs option[selected]');

        // XPath plutôt que "#notif_message + .msg" : le <div class="msg"> est écrit
        // dans le <p> du champ, mais un <div> ferme implicitement un <p>, donc le
        // parseur HTML le remonte au niveau du fieldset
        $I->seeElement('//fieldset[.//textarea[@id="notif_message"]]//div[@class="msg"]');
        $I->see('Ce champ est obligatoire');
    }
}
