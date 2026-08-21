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
     * `user/login.php` protège le formulaire par un token CSRF en session
     * (`form_token_user_login`) et par un honeypot `login_as` qui doit rester
     * vide : `submitForm()` renvoie les champs cachés tels quels et PhpBrowser
     * conserve les cookies, il n'y a donc rien de particulier à faire ici.
     */
    public function login(string $user, string $password): void
    {
        $this->amOnPage('/user/login.php');
        $this->submitForm('#ajouter_editer', ['pseudo' => $user, 'motdepasse' => $password]);

        // succès = redirection vers / ; échec = /user/login.php?msg=faux.
        // On vérifie la session sur une page statique plutôt que sur la home,
        // qui peut échouer pour des raisons de données (cf. BotMonitoringCest)
        $this->amOnPage('/articles/apropos.php');
        $this->seeElement('#menu_pratique form.deconnexion');
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

    /**
     * Ferme la session ouverte sur le site testé.
     *
     * `user/logout.php` n'accepte que POST, avec le jeton CSRF déposé en session : il
     * faut donc afficher une page rendue en session pour y trouver le bouton « Sortir »,
     * dont `submitForm()` renvoie le champ caché tel quel.
     */
    public function logout(): void
    {
        $this->amOnPage('/articles/apropos.php');
        $this->submitForm('#menu_pratique form.deconnexion', []);
    }

    /**
     * Lit un en-tête de la dernière réponse.
     *
     * PhpBrowser sait poser des en-têtes (`haveHttpHeader`) mais pas en lire : il n'expose ni
     * `grabHttpHeader` ni `seeHttpHeader`, réservés au module REST. On passe donc par la réponse
     * interne du client, seule voie pour tester la validation de cache (ETag, Last-Modified).
     */
    public function grabResponseHeader(string $name): string
    {
        /** @var \Codeception\Module\PhpBrowser $browser */
        $browser = $this->getScenario()->current('modules')['PhpBrowser'];

        $value = $browser->client->getInternalResponse()->getHeader($name);

        return is_array($value) ? (string) reset($value) : (string) $value;
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
