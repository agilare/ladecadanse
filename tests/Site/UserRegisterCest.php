<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Inscription publique (user/register.php) : les gardes du formulaire et les
 * règles de validation qui, si elles sautent, ouvrent la porte aux robots ou
 * cassent la création de compte sans bruit.
 *
 * Lecture seule par construction : chaque POST porte une faute délibérée, donc
 * `$verif->nbErreurs() > 0` et l'INSERT n'est jamais atteint — sauf pour
 * `potDeMielRempliBloqueInscription`, voir l'avertissement sur ce test.
 *
 * Non couvert ici : le cas « email déjà pris », qui rend le message de succès
 * sans rien insérer (anti-énumération). Il exigerait une adresse réellement
 * présente en base ; une fixture périmée transformerait le test en création de
 * compte doublée d'un envoi de mail.
 */
class UserRegisterCest
{
    private const URL = '/user/register.php';

    /**
     * Mot de passe conforme aux règles (10 caractères, un chiffre, absent de
     * resources/bad_p.txt) : les tests qui doivent échouer ailleurs ne doivent
     * pas échouer sur le mot de passe.
     */
    private const MDP_VALIDE = 'Revue2026Test';

    /**
     * Le formulaire sert ses trois gardes : jeton CSRF en session, pot de miel,
     * et les deux listes d'affiliation (dont le select multiple `organisateurs[]`,
     * dont dépend la boucle d'insertion).
     */
    public function formulaireEstServiAvecSesGardes(SiteTester $I)
    {
        $I->amOnPage(self::URL);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
        $I->seeElement('input[type=hidden][name=form_token_user_register]');
        $I->seeElement('input[name=username_as]');
        $I->seeElement('#login[required]');
        $I->seeElement('#email[required]');
        $I->seeElement('select[name=lieu]');
        $I->seeElement('select[name="organisateurs[]"]');

        // le jeton doit être une valeur, pas un attribut vide hérité d'une session perdue
        $I->assertNotEmpty($I->grabValueFrom('input[name=form_token_user_register]'));
    }

    /**
     * Un jeton qui ne correspond pas à la session est rejeté avant toute
     * validation : c'est la seule protection CSRF de la page.
     */
    public function jetonInvalideRejetteLaSoumission(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'form_token_user_register' => str_repeat('0', 64),
            'login' => 'zz-codeception-jeton',
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE,
            'email' => 'zz-codeception-jeton@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('le formulaire est expiré');
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Le jeton est à usage unique : rejouer la même soumission (cas du
     * double-clic ou du rechargement de POST) ne doit pas repasser.
     */
    public function jetonNEstValableQuUneFois(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $jeton = $I->grabValueFrom('input[name=form_token_user_register]');

        // première soumission : refusée sur les mots de passe, mais le jeton est consommé
        $I->submitForm('#ajouter_editer', [
            'login' => 'zz-codeception-rejeu',
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE . 'X',
            'email' => 'zz-codeception-rejeu@example.com',
        ]);
        $I->see('Les 2 mots de passe doivent être identiques');

        $I->sendAjaxPostRequest(self::URL, [
            'formulaire' => 'ok',
            'form_token_user_register' => $jeton,
            'login' => 'zz-codeception-rejeu',
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE . 'X',
            'email' => 'zz-codeception-rejeu@example.com',
        ]);

        $I->see('le formulaire est expiré');
    }

    /**
     * Les deux mots de passe doivent concorder, et la longueur minimale (10)
     * est vérifiée côté serveur : `minlength` sur l'input ne protège rien.
     */
    public function motsDePasseInvalidesSontRefuses(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'login' => 'zz-codeception-mdp',
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE . 'X',
            'email' => 'zz-codeception-mdp@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Les 2 mots de passe doivent être identiques');
        $I->dontSee('Votre compte a été créé');
        $I->seeElement('#ajouter_editer');

        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'login' => 'zz-codeception-mdp',
            'motdepasse' => 'court1',
            'motdepasse2' => 'court1',
            'email' => 'zz-codeception-mdp@example.com',
        ]);

        $I->see('erreur(s)');
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Un identifiant déjà pris est signalé — contrairement à l'email, dont la
     * réutilisation reste muette (anti-énumération).
     *
     * Mots de passe volontairement différents : le test resterait en lecture
     * seule même si le contrôle d'unicité venait à disparaître.
     */
    public function loginDejaPrisEstSignale(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ACTOR_USER');

        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'login' => TestEnv::get('LADECADANSE_SITE_ACTOR_USER'),
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE . 'X',
            'email' => 'zz-codeception-login-pris@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('existe déjà');
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Pot de miel : le champ `username_as` doit rester vide. C'est le seul
     * rempart contre les inscriptions automatisées.
     *
     * ATTENTION : c'est le seul test de ce Cest dont la charge est par ailleurs
     * valide — il le faut pour que l'échec ne puisse venir que du pot de miel.
     * Si ce garde disparaît, le test échoue *et* crée un compte
     * `zz-codeception-pot-de-miel` (avec envoi de mail) : c'est le signal, à
     * nettoyer avant de rejouer la suite.
     */
    public function potDeMielRempliBloqueInscription(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'login' => 'zz-codeception-pot-de-miel',
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE,
            'email' => 'zz-codeception-pot-de-miel@example.com',
            'username_as' => 'je-suis-un-robot',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Il y a 1 erreur(s)');
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Une soumission sans le select `organisateurs[]` (robot, ou navigateur qui
     * ne poste pas un select vide) ne doit pas produire de warning PHP : la page
     * lisait `$_POST['organisateurs']` sans garde, et sous ENV=dev le moindre
     * warning devient une page Whoops.
     */
    public function organisateursAbsentDuPostNeCassePasLaPage(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $jeton = $I->grabValueFrom('input[name=form_token_user_register]');

        $I->sendAjaxPostRequest(self::URL, [
            'formulaire' => 'ok',
            'form_token_user_register' => $jeton,
            'login' => 'zz-codeception-sans-orga',
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE . 'X',
            'email' => 'zz-codeception-sans-orga@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Les 2 mots de passe doivent être identiques');
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Un champ scalaire posté sous forme de tableau (`login[]=x`) doit être
     * ignoré, pas transmis au validateur ni à une requête préparée typée.
     */
    public function champsTableauxSontIgnores(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $jeton = $I->grabValueFrom('input[name=form_token_user_register]');

        $I->sendAjaxPostRequest(self::URL, [
            'formulaire' => 'ok',
            'form_token_user_register' => $jeton,
            'login' => ['zz-codeception-tableau'],
            'motdepasse' => self::MDP_VALIDE,
            'motdepasse2' => self::MDP_VALIDE,
            'email' => ['zz-codeception-tableau@example.com'],
            'organisateurs' => [''],
        ]);

        // login et email retombent à leur valeur vide : deux champs obligatoires en défaut
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Il y a 2 erreur(s)');
        $I->dontSee('Votre compte a été créé');
    }
}
