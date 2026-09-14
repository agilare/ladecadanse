<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Lien ajax « Dépublier », rendu par EvenementRenderer::unpublishLinkHtml().
 *
 * Tests en lecture seule : on vérifie le rendu du lien et qui y a droit,
 * jamais la dépublication elle-même, qui écrirait en base.
 *
 * `LADECADANSE_TEST_EVENT_ID_AUTEUR` doit désigner un événement publié :
 * le lien est volontairement absent d'un événement déjà au statut `inactif`.
 *
 * Sur la fiche événement le lien est réservé au SUPERADMIN, niveau qu'aucun
 * compte de `tests/.env` ne garantit : côté connecté on teste donc
 * `admin/events.php`, ouvert à tout ADMIN.
 */
class EvenementDepublierCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_EVENT_ID_AUTEUR');
    }

    /**
     * Un visiteur non connecté voit la fiche mais aucun lien de dépublication :
     * le lien vit dans le bloc conditionné par $isPersonneAllowedToEdit.
     */
    public function anonymousSeesNoUnpublishLink(SiteTester $I)
    {
        $I->amOnPage($this->evenementUrl(TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_AUTEUR')));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeElement('a.btn_event_unpublish');
    }

    /**
     * Un admin dispose du lien dans le tableau de gestion, avec les attributs
     * attendus par le handler de web/js/global.js : data-id pour l'appel,
     * data-on-success pour la suite (ici `status`, le tableau listant aussi
     * les événements dépubliés).
     */
    public function adminSeesUnpublishLinkOnAdminEvents(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage('/admin/events.php');

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('a.btn_event_unpublish[data-on-success=status]');
    }

    private function evenementUrl(int $idEvenement): string
    {
        return '/event/evenement.php?idE=' . $idEvenement;
    }
}
