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
 * Depuis que le formulaire n'a plus qu'un champ de mot de passe, cette faute ne
 * peut plus être une confirmation discordante : elle porte selon les cas sur
 * l'adresse, sur le mot de passe ou sur le nom d'utilisateur.
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
     * Mot de passe conforme aux règles (10 caractères au moins, absent de
     * resources/bad_p.txt) : les tests qui doivent échouer ailleurs ne doivent
     * pas échouer sur le mot de passe.
     */
    private const MDP_VALIDE = 'Revue2026Test';

    /**
     * Le formulaire sert ses gardes — jeton CSRF en session, pot de miel — et les
     * deux visages de l'inscription : un compte de base qui ne demande que
     * l'adresse et le mot de passe, et le cadre du contributeur que la case
     * déplie.
     */
    public function formulaireEstServiAvecSesGardes(SiteTester $I)
    {
        $I->amOnPage(self::URL);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
        $I->seeElement('input[type=hidden][name=form_token_user_register]');
        $I->seeElement('input[name=username_as]');
        $I->seeElement('#email[required]');
        $I->seeElement('#motdepasse[required]');
        $I->seeElement('#contributor');
        $I->seeElement('#contributor-fields');
        $I->seeElement('#login');
        $I->seeElement('select[name=affiliation_selected]');
        $I->seeElement('#affiliation');

        // le jeton doit être une valeur, pas un attribut vide hérité d'une session perdue
        $I->assertNotEmpty($I->grabValueFrom('input[name=form_token_user_register]'));
    }

    /**
     * Le nom d'utilisateur est facultatif : l'attribut `required` le rendrait
     * obligatoire pour tout le monde, et bloquerait l'envoi sans message lisible
     * puisque son cadre est masqué tant que la case n'est pas cochée. C'est le
     * traitement qui l'exige, et seulement pour un contributeur.
     */
    public function nomDUtilisateurNEstPasRequisParLeNavigateur(SiteTester $I)
    {
        $I->amOnPage(self::URL);

        $I->dontSeeElement('#login[required]');
        $I->dontSeeElement('#affiliation[required]');
        // un seul champ de mot de passe : la confirmation a disparu
        $I->dontSeeElement('#motdepasse2');
        $I->seeElement('.js-toggle-password[data-target=motdepasse]');
    }

    /**
     * La case pré-cochée par l'url : articles/annoncerEvenement.php y envoie les
     * gens qui ont des événements à annoncer.
     */
    public function laCasePeutEtreCocheeParLUrl(SiteTester $I)
    {
        $I->amOnPage(self::URL . '?contributor=1');

        $I->seeCheckboxIsChecked('#contributor');
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
            'motdepasse' => self::MDP_VALIDE,
            'email' => 'zz-codeception-jeton@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("Le formulaire a expiré");
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

        // première soumission : refusée sur l'adresse, mais le jeton est consommé
        $I->submitForm('#ajouter_editer', [
            'motdepasse' => self::MDP_VALIDE,
            'email' => 'zz-codeception-rejeu',
        ]);
        $I->see("Cette adresse e-mail n'est pas valable");

        $I->sendAjaxPostRequest(self::URL, [
            'formulaire' => 'ok',
            'form_token_user_register' => $jeton,
            'motdepasse' => self::MDP_VALIDE,
            'email' => 'zz-codeception-rejeu',
        ]);

        $I->see("Le formulaire a expiré");
    }

    /**
     * La longueur minimale et la liste des mots de passe fuités sont vérifiées
     * côté serveur : `minlength` sur l'input ne protège rien. La règle « au moins
     * un chiffre », elle, a été retirée — c'est la liste qui filtre.
     */
    public function motsDePasseInvalidesSontRefuses(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'motdepasse' => 'court1',
            'email' => 'zz-codeception-mdp@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("Votre mot de passe doit faire entre");
        $I->dontSee('Votre compte a été créé');
        $I->seeElement('#ajouter_editer');

        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'motdepasse' => 'marseille13',
            'email' => 'zz-codeception-mdp@example.com',
        ]);

        $I->see("Ce mot de passe est trop courant");
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Un nom d'utilisateur déjà pris est signalé — contrairement à l'email, dont
     * la réutilisation reste muette (anti-énumération).
     *
     * L'adresse est volontairement invalide : le test resterait en lecture seule
     * même si le contrôle d'unicité venait à disparaître.
     */
    public function loginDejaPrisEstSignale(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ACTOR_USER');

        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'contributor' => 1,
            'login' => TestEnv::get('LADECADANSE_SITE_ACTOR_USER'),
            'affiliation' => 'zz-codeception-affiliation',
            'motdepasse' => self::MDP_VALIDE,
            'email' => 'zz-codeception-login-pris',
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
     * zz-codeception-pot-de-miel@example.com (avec envoi de mail) : c'est le
     * signal, à nettoyer avant de rejouer la suite.
     */
    public function potDeMielRempliBloqueInscription(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $I->submitForm('#ajouter_editer', [
            'motdepasse' => self::MDP_VALIDE,
            'email' => 'zz-codeception-pot-de-miel@example.com',
            'username_as' => 'je-suis-un-robot',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        // une seule erreur : l'encart rend le message seul, sans le décompte
        $I->see("Veuillez laisser vide le champ réservé aux robots");
        $I->dontSee('Il y a');
        $I->dontSee('Votre compte a été créé');
    }

    /**
     * Case cochée, cadre vide : les champs du contributeur sont exigés par le
     * traitement, seul juge — leur cadre est masqué en CSS, et un navigateur ne
     * validerait rien de ce qu'il ne montre pas.
     */
    public function caseContributeurCocheeExigeNomEtAffiliation(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $jeton = $I->grabValueFrom('input[name=form_token_user_register]');

        $I->sendAjaxPostRequest(self::URL, [
            'formulaire' => 'ok',
            'form_token_user_register' => $jeton,
            'contributor' => 1,
            'motdepasse' => self::MDP_VALIDE,
            'email' => 'zz-codeception-contributeur@example.com',
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("Veuillez choisir un nom d'utilisateur");
        $I->see("Veuillez choisir votre lieu ou organisateur");
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
            'contributor' => 1,
            'login' => ['zz-codeception-tableau'],
            'affiliation' => ['zz-codeception-tableau'],
            'motdepasse' => self::MDP_VALIDE,
            'email' => ['zz-codeception-tableau@example.com'],
        ]);

        // adresse, nom d'utilisateur et affiliation retombent à leur valeur vide :
        // trois champs obligatoires en défaut
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("Il y a 3 erreurs");
        $I->dontSee('Votre compte a été créé');
    }
}
