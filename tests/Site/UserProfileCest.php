<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Page de profil (user.php).
 *
 * La page n'avait aucun test avant le refactor de l'issue #114, qui l'a réécrite de fond en comble :
 * requêtes préparées, suppression de trois onglets, contrôle d'accès unifié. Ce qui est verrouillé
 * ici, ce sont les invariants qu'une régression casserait en silence.
 *
 * Deux d'entre eux méritent une explication :
 *
 * - le lien « Modifier » : UserEventDefaultsCest y navigue par un clic sur a[href*="/user-edit.php"].
 *   Le dupliquer ou le renommer casserait cette suite-là, pas celle-ci — d'où l'assertion explicite.
 * - la page complète en cas d'erreur : _header.inc.php était inclus avant toute validation, si bien
 *   qu'un identifiant manquant renvoyait un document tronqué, sans </main> ni pied de page.
 *
 * La suite `site` est en lecture seule : aucun test ici n'écrit en base.
 */
class UserProfileCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ACTOR_USER',
            'LADECADANSE_SITE_ACTOR_PASS'
        );
    }

    /**
     * Identifiant de la personne connectée.
     *
     * Par navigation plutôt que par une URL construite : l'identifiant dépend de l'instance testée
     * et tests/.env ne le décrit pas. Le menu du header pointe vers le profil de l'utilisateur
     * connecté.
     */
    private function grabMyProfileId(SiteTester $I): string
    {
        $I->amOnPage('/articles/apropos.php');
        $I->click('a[href^="/user.php?idP="]');
        $I->seeResponseCodeIs(HttpCode::OK);

        return $I->grabFromCurrentUrl('~idP=(\d+)~');
    }

    /**
     * La page est réservée aux membres connectés ; un visiteur anonyme part sur le formulaire de
     * connexion au lieu de voir une adresse e-mail.
     */
    public function anonymousIsSentToLogin(SiteTester $I)
    {
        $I->amOnPage('/user.php?idP=1');
        $I->seeInCurrentUrl('/user-login.php');
    }

    /**
     * Le profil de l'utilisateur connecté s'affiche, avec le lien d'édition.
     */
    public function ownProfileIsReachable(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->see('Profil', 'h1');
        $I->see('Identifiant');
        $I->see('E-mail');
        $I->see('Affiliations');
        $I->seeElement('a[href*="/user-edit.php"]');
    }

    /**
     * Les trois fonctionnalités retirées par l'issue #114 : le menu « Éléments ajoutés » et les
     * onglets Lieux et Organisateurs. Les deux onglets restants, eux, doivent être là.
     */
    public function removedTabsAreGone(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->dontSeeElement('#menu_principal');
        $I->dontSeeElement('a[href*="elements=lieu"]');
        $I->dontSeeElement('a[href*="elements=organisateur"]');

        $I->seeElement('a[href*="elements=evenement"]');
        $I->seeElement('a[href*="elements=description"]');
    }

    /**
     * Le tri était commun aux deux onglets alors que leurs tables n'ont pas les mêmes colonnes :
     * demander un tri par titre sur les descriptions lançait un ORDER BY sur une colonne
     * inexistante. La valeur inconnue doit désormais retomber sur le tri par défaut.
     *
     * L'assertion porte sur #colonne_gauche, rendu tout en bas de la page : le code de réponse ne
     * suffit pas, l'échec SQL était rattrapé et renvoyait quand même un 200 — mais sur un document
     * interrompu au milieu du tableau.
     */
    public function sortUnknownToTheTabFallsBackInsteadOfBreaking(SiteTester $I)
    {
        $I->loginAsActor();
        $idP = $this->grabMyProfileId($I);

        $I->amOnPage('/user.php?idP=' . $idP . '&elements=description&tri=titre');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#colonne_gauche');
    }

    /**
     * Une sortie en erreur rend une page complète, pied de page compris.
     */
    public function missingIdRendersACompletePage(SiteTester $I)
    {
        $I->loginAsActor();

        $I->amOnPage('/user.php');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeElement('#pied');
    }

    /**
     * Un acteur ne peut pas consulter le profil d'autrui — seuls le propriétaire et les
     * administrateurs y accèdent.
     */
    public function foreignProfileIsForbidden(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS'
        );

        $I->loginAsAdmin();
        $idAdmin = $this->grabMyProfileId($I);
        $I->logout();

        $I->loginAsActor();
        $idActor = $this->grabMyProfileId($I);

        if ($idAdmin === $idActor)
        {
            return;
        }

        $I->amOnPage('/user.php?idP=' . $idAdmin);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
