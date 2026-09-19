<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Jeton CSRF des formulaires d'édition réservés aux personnes connectées
 * (Ladecadanse\Security\SecurityToken), éprouvé sur lieu/edit.php : les huit pages qui
 * s'en servent passent au contrôle les mêmes valeurs.
 *
 * Le jeton de session n'est créé qu'au rendu d'une page qui en a besoin : un de ces
 * formulaires, ou un lien « Dépublier ». Une session qui vient de s'ouvrir n'en a donc pas,
 * et un envoi sans jeton y comparait deux chaînes vides : il passait le contrôle.
 *
 * L'affichage du refus dépend de chaque page : il est vérifié là où il manquait, sur le
 * formulaire d'événement et sur le profil d'un compte qui n'est pas superadmin.
 *
 * Suite read-only : chaque envoi porte une erreur de validation garantie (nom du lieu et
 * catégorie inconnue, titre de l'événement, e-mail du profil). Même un jeton accepté à tort ne
 * mènerait qu'à des erreurs de validation, jamais à une écriture.
 */
class SecurityTokenCest
{
    private const URL_FORMULAIRE = '/lieu/edit.php?action=ajouter';

    private const URL_ENVOI = '/lieu/edit.php?action=insert';

    private const REFUS_DU_JETON = "Le système de sécurité du site n'a pu authentifier votre action";

    /*
     * Rendue par LieuEdition::validate() : la voir prouve que l'envoi a passé le contrôle du
     * jeton. Le second test la fait apparaître, faute de quoi les dontSee() qui la visent ne
     * prouveraient rien le jour où son libellé changerait.
     */
    private const ERREUR_DE_VALIDATION = "La catégorie zz-codeception n'est pas valable";

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');
    }

    /**
     * Juste après la connexion, la session n'a pas de jeton, pourvu qu'aucune page n'en ait
     * créé un entre-temps. La redirection qui conclut la connexion n'est donc pas suivie : pour
     * tout compte non superadmin, elle mène à l'accueil, dont les liens « Dépublier » créent le
     * jeton, et le test passerait alors même contre un contrôle qui accepte une session sans
     * jeton. login() vérifie ensuite la session sur une page qui n'en crée pas.
     */
    public function unEnvoiSansJetonEstRefuseAvantToutFormulaire(SiteTester $I)
    {
        $I->stopFollowingRedirects();
        $I->loginAsAdmin();
        $I->startFollowingRedirects();

        $I->sendAjaxPostRequest(self::URL_ENVOI, self::envoiInvalide());

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see(self::REFUS_DU_JETON);
        $I->dontSee(self::ERREUR_DE_VALIDATION);
    }

    /**
     * Une fois le formulaire affiché, le même envoi reste refusé sans jeton, et atteint la
     * validation avec celui du formulaire.
     */
    public function seulLEnvoiPortantLeJetonAtteintLaValidation(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage(self::URL_FORMULAIRE);
        $jeton = $I->grabValueFrom('#ajouter_editer input[name=token]');

        $I->sendAjaxPostRequest(self::URL_ENVOI, self::envoiInvalide());

        $I->see(self::REFUS_DU_JETON);
        $I->dontSee(self::ERREUR_DE_VALIDATION);

        $I->sendAjaxPostRequest(self::URL_ENVOI, self::envoiInvalide() + ['token' => $jeton]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSee(self::REFUS_DU_JETON);
        $I->see(self::ERREUR_DE_VALIDATION);
    }

    /**
     * Le formulaire d'événement rangeait son refus sous la clé « genres », qu'aucun champ ne
     * lit : on voyait le décompte des erreurs, jamais leur cause.
     */
    public function leRefusSAfficheSurLeFormulaireDEvenement(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/evenement-edit.php?action=ajouter');
        $I->seeElement('#ajouter_editer input[name=token]');

        $I->submitForm('#ajouter_editer', [
            'titre' => '', // obligatoire : erreur garantie, rien n'est enregistré
            'token' => '',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see(self::REFUS_DU_JETON);
    }

    /**
     * Le profil rangeait son refus avec les erreurs du login, que seuls les superadmins voient :
     * il faut un compte d'un autre niveau pour le voir manquer.
     */
    public function leRefusSAfficheSurLeProfilDUnActeur(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ACTOR_USER', 'LADECADANSE_SITE_ACTOR_PASS');

        $I->loginAsActor();
        $this->amOnMyProfileEdit($I);

        $I->submitForm('#ajouter_editer', [
            'email' => '', // obligatoire : erreur garantie, rien n'est enregistré
            'token' => '',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see(self::REFUS_DU_JETON);
    }

    /**
     * Par navigation plutôt que par une URL construite, comme dans UserEventDefaultsCest :
     * l'identifiant du compte dépend de l'instance testée.
     */
    private function amOnMyProfileEdit(SiteTester $I): void
    {
        $I->amOnPage('/articles/apropos.php');
        $I->click('a[href^="/user/dashboard.php?idP="]');
        $I->click('a[href*="/user-edit.php"]');
        $I->seeElement('#ajouter_editer input[name=token]');
    }

    /**
     * @return array<string, string|string[]>
     */
    private static function envoiInvalide(): array
    {
        return [
            'form_submitted' => '1',
            'nom' => '', // obligatoire : erreur garantie, rien n'est enregistré
            'categories' => ['zz-codeception'], // inconnue : seconde erreur garantie
        ];
    }
}
