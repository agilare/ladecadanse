<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Formulaire d'ajout et de modification d'un organisateur (organisateur/edit.php).
 *
 * Couvre ce que la reprise de la page (issue #115) a changé de visible : le titre qui
 * renvoie à la fiche, les libellés de statut, le fait que le statut n'est proposé
 * qu'aux administrateurs, et le refus d'éditer la fiche d'un autre — jusque-là ouverte
 * à tout compte de niveau ACTOR.
 *
 * Suite read-only : les POST de ce fichier vident le nom, donc la validation échoue et
 * enregistrer() n'est jamais atteint. Ne pas y soumettre de formulaire valide.
 */
class OrganisateurEditFormulaireCest
{
    /** Erreur de champ rendue par Validateur::getHtmlErreur() ; absente tant que rien n'est en erreur. */
    private const ERREUR_DE_CHAMP = '#ajouter_editer .msg';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_ORGA_ID_ACTOR_OWN'
        );
    }

    /**
     * Le formulaire d'ajout rend les champs dont dépendent le JS et le traitement :
     * le témoin `form_submitted` (sans lui, js-submit-freeze-wait fait perdre la
     * soumission), le jeton CSRF, et les deux champs fichier.
     */
    public function formulaireAjoutRendSesChamps(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/organisateur/edit.php?action=ajouter');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("Ajouter un organisateur", 'h1');
        $I->seeElement('input[type=hidden][name=form_submitted]');
        $I->seeElement('input[type=hidden][name=token]');
        $I->seeElement('#nom');
        $I->seeElement('input[type=file]#logo');
        $I->seeElement('input[type=file]#photo');
        $I->seeElement('textarea#presentation.tinymce');
    }

    /**
     * En modification, le titre nomme la fiche et y renvoie, au lieu du « Modifier un
     * organisateur » qui laissait l'éditeur deviner sur quoi il travaillait.
     */
    public function titreDeModificationRenvoieALaFiche(SiteTester $I)
    {
        $idO = TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN');

        $I->loginAsAdmin();
        $I->amOnPage('/organisateur/edit.php?action=editer&idO=' . $idO);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("Modifier l'organisateur", 'h1');
        $I->seeElement('h1 a[href="/organisateur/organisateur.php?idO=' . $idO . '"]');
    }

    /**
     * Les statuts sont montrés comme ce qu'ils décident — la visibilité de la fiche —
     * et non plus en « actif / inactif », qui parlait de l'organisateur.
     */
    public function statutsSontLibellesPublieEtDepublie(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/organisateur/edit.php?action=editer&idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('input[type=radio][name=statut][value=actif]');
        $I->seeElement('label[for=statut_actif]');
        $I->see("Publié", 'label[for=statut_actif]');
        $I->see("Dépublié", 'label[for=statut_inactif]');
    }

    /**
     * Publier ou dépublier reste une décision de modération : le fieldset n'est pas
     * rendu pour un acteur, et la page n'accepte plus de statut posté de sa part.
     */
    public function acteurNeSeVoitPasProposerLeStatut(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ACTOR_USER', 'LADECADANSE_SITE_ACTOR_PASS');

        $I->loginAsActor();
        $I->amOnPage('/organisateur/edit.php?action=editer&idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN'));

        $I->seeResponseCodeIs(HttpCode::OK);

        // Sans ce garde, une fixture qui ne correspond plus au compte acteur ferait échouer
        // le test sur un « #nom introuvable » qui n'oriente vers rien
        $I->assertStringNotContainsString(
            "Vous ne pouvez pas modifier cet organisateur",
            $I->grabPageSource(),
            "LADECADANSE_TEST_ORGA_ID_ACTOR_OWN doit désigner une fiche que LADECADANSE_SITE_ACTOR_USER "
            . "peut modifier : dont il est l'auteur (organisateur.idPersonne) ou membre (personne_organisateur)."
        );

        $I->seeElement('#nom');
        $I->dontSeeElement('input[name=statut]');
    }

    /**
     * La fiche d'un autre organisateur n'est plus modifiable : le contrôle admettait
     * tout compte de niveau ACTOR, c'est-à-dire n'importe quel organisateur sur la
     * fiche de n'importe quel autre.
     */
    public function acteurNeModifiePasLaFicheDunAutre(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS',
            'LADECADANSE_TEST_ORGA_ID_FOREIGN'
        );

        $I->loginAsActor();
        $I->amOnPage('/organisateur/edit.php?action=editer&idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_FOREIGN'));

        // Le refus est un 403, et il est rendu dans la page du site : un message HTML nu
        // au-dessus d'une page vide ne disait rien au client ni ne ramenait nulle part
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->see("Vous ne pouvez pas modifier cet organisateur");
        $I->seeElement('#menu_pratique');
        $I->dontSeeElement('#ajouter_editer');
    }

    /**
     * Une modification sans organisateur désigné est une requête malformée, pas un
     * formulaire vide : elle vaut 400, dans la page du site elle aussi.
     */
    public function modificationSansIdentifiantEstUneRequeteMalformee(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/organisateur/edit.php?action=editer');

        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->see("Aucun organisateur n'est désigné");
        $I->dontSeeElement('#ajouter_editer');
    }

    /**
     * Un nom vide est refusé côté serveur, et le formulaire est ré-affiché avec la
     * saisie plutôt que redirigé vers la fiche. C'est aussi ce qui garde ce test
     * read-only : la validation échoue avant tout enregistrement.
     */
    public function nomVideEstRefuse(SiteTester $I)
    {
        $idO = TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_ACTOR_OWN');

        $I->loginAsAdmin();
        $I->amOnPage('/organisateur/edit.php?action=editer&idO=' . $idO);
        $I->submitForm('#ajouter_editer', ['nom' => '', 'adresse' => 'Rue du test 1']);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement(self::ERREUR_DE_CHAMP);
        $I->seeElement('#ajouter_editer');
        $I->seeInField('#adresse', 'Rue du test 1');
    }
}
