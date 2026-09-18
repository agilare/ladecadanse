<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * event/actions.php, qui supprime ou dépublie un événement à la demande des liens
 * « Supprimer » et « Dépublier » : POST seulement, avec le jeton CSRF de la session que ces
 * liens portent dans data-token.
 *
 * Suite read-only : toutes les requêtes visent un événement qui ne peut exister, l'identifiant
 * étant négatif. Même acceptée à tort, une requête n'y trouverait rien à supprimer ni à
 * dépublier, et s'arrêterait au contrôle des droits : c'est ce 403 qui prouve qu'elle a passé
 * ceux de la méthode (405) et du jeton (400).
 */
class EvenementActionsCest
{
    private const URL = '/event/actions.php';

    private const ACTIONS = ['delete', 'unpublish'];

    private const ID_INEXISTANT = '-1';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');
    }

    /**
     * Un GET est refusé avant tout traitement, même pour un admin connecté.
     */
    public function unGetEstRefuse(SiteTester $I)
    {
        $I->loginAsAdmin();

        foreach (self::ACTIONS as $action)
        {
            $I->amOnPage(self::URL . '?action=' . $action . '&id=' . self::ID_INEXISTANT);

            $I->seeResponseCodeIs(HttpCode::METHOD_NOT_ALLOWED);
        }
    }

    /**
     * Le jeton que porte le lien « Dépublier » passe le contrôle ; sans jeton, ou avec un
     * autre, la requête est refusée avant d'atteindre celui des droits.
     */
    public function seulLeJetonDuLienOuvreLAction(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/admin/events.php');
        $jeton = $I->grabAttributeFrom('a.btn_event_unpublish', 'data-token');

        foreach (self::ACTIONS as $action)
        {
            $envoi = ['action' => $action, 'id' => self::ID_INEXISTANT];

            $I->sendAjaxPostRequest(self::URL, $envoi);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

            $I->sendAjaxPostRequest(self::URL, $envoi + ['token' => str_repeat('0', 64)]);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

            $I->sendAjaxPostRequest(self::URL, $envoi + ['token' => $jeton]);
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
    }
}
