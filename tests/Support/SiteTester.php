<?php

namespace Tests\Support;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
*/
class SiteTester extends \Codeception\Actor
{
    use _generated\SiteTesterActions;

    /**
     * Define custom actions here
     */

    /**
     * Ouvre une session sur le site testé.
     *
     * `user-login.php` protège le formulaire par un token CSRF en session
     * (`form_token_user_login`) et par un honeypot `login_as` qui doit rester
     * vide : `submitForm()` renvoie les champs cachés tels quels et PhpBrowser
     * conserve les cookies, il n'y a donc rien de particulier à faire ici.
     */
    public function login(string $user, string $password): void
    {
        $this->amOnPage('/user-login.php');
        $this->submitForm('#ajouter_editer', ['pseudo' => $user, 'motdepasse' => $password]);

        // succès = redirection vers / ; échec = /user-login.php?msg=faux.
        // On vérifie la session sur une page statique plutôt que sur la home,
        // qui peut échouer pour des raisons de données (cf. BotMonitoringCest)
        $this->amOnPage('/articles/apropos.php');
        $this->seeLink('Sortir');
    }

    public function loginAsAdmin(): void
    {
        $this->login(
            TestEnv::get('LADECADANSE_SITE_ADMIN_USER'),
            TestEnv::get('LADECADANSE_SITE_ADMIN_PASS')
        );
    }

    public function loginAsActor(): void
    {
        $this->login(
            TestEnv::get('LADECADANSE_SITE_ACTOR_USER'),
            TestEnv::get('LADECADANSE_SITE_ACTOR_PASS')
        );
    }

    public function logout(): void
    {
        $this->amOnPage('/user-logout.php');
    }

    /**
     * Marque le test « skipped » plutôt qu'en échec quand l'instance testée
     * n'est pas décrite dans `tests/.env` (cf. `tests/.env_model`).
     */
    public function skipUnlessConfigured(string ...$keys): void
    {
        $missing = TestEnv::missing(...$keys);

        if ($missing !== [])
        {
            $this->markTestSkipped('tests/.env incomplet, variable(s) manquante(s) : ' . implode(', ', $missing));
        }
    }
}
