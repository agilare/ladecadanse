<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Liste des événements et formulaire d'édition groupée, `admin/events.php` (issue #125).
 *
 * Tests en lecture seule : on n'envoie jamais le formulaire d'édition groupée, qui écrirait
 * en base — et qui supprimerait, si la case l'était. Seuls les filtres et le tri, qui
 * passent en GET, sont exercés.
 *
 * Les filtres étant mémorisés en session, chaque test qui en pose un le remet à blanc :
 * une valeur oubliée viderait la liste des tests suivants, qui passeraient alors sans rien
 * vérifier.
 */
class AdminEventsCest
{
    private const URL = '/admin/events.php';

    /**
     * La page est fermée aux non-admins. Contrairement à `admin/bots.php`, elle envoie un
     * 403 sec, sans `Location:` : le client voit donc bien le 403.
     */
    public function anonymousGetsForbidden(SiteTester $I)
    {
        $I->amOnPage(self::URL);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function adminSeesTheList(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('table#ajouts');
        $I->seeElement('#filters');
        $I->seeElement('table#ajouts td a.titre');
    }

    /**
     * Trois filtres, postés ensemble par un seul formulaire.
     */
    public function theThreeFiltersAreOffered(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->seeElement('#events-filters input[name=terme]');
        $I->seeElement('#events-filters input[name=lieu]');
        $I->seeElement('#events-filters input[name=personne]');
    }

    /**
     * Le menu de filtre par catégorie a disparu : c'est le tri et la recherche qui servent.
     */
    public function categoryFilterMenuIsGone(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->dontSeeElement('ul.menu_filtre');
    }

    /**
     * Le menu du nombre de lignes propose 50, 250 et 500 — plus 100, qui n'apportait rien
     * entre les deux premiers.
     */
    public function rowsPerPageMenuDropsHundred(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->seeElement('ul.menu_nb_res a[href="?nblignes=50"]');
        $I->seeElement('ul.menu_nb_res a[href="?nblignes=250"]');
        $I->seeElement('ul.menu_nb_res a[href="?nblignes=500"]');
        $I->dontSeeElement('ul.menu_nb_res a[href="?nblignes=100"]');
    }

    /**
     * Un filtre posé une fois survit à un chargement suivant sans query string : c'est tout
     * l'intérêt de la mémorisation en session, revenir d'une fiche d'événement retrouve la
     * liste telle qu'on l'avait laissée.
     */
    public function filtersAreRememberedInSession(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        $I->amOnPage(self::URL . '?terme=zzz-aucun-evenement');
        $I->seeInField('#events-filters input[name=terme]', 'zzz-aucun-evenement');

        $I->amOnPage(self::URL);
        $I->seeInField('#events-filters input[name=terme]', 'zzz-aucun-evenement');

        // remise à blanc : sans elle, les tests suivants liraient une liste vide
        $I->amOnPage(self::URL . '?terme=');
        $I->seeInField('#events-filters input[name=terme]', '');
    }

    /**
     * Un filtre sans résultat vide bien le tableau — sans quoi le test précédent ne
     * prouverait rien : il vérifierait que le champ se souvient d'une valeur qui ne filtre pas.
     */
    public function anUnmatchedFilterEmptiesTheTable(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        $I->amOnPage(self::URL . '?terme=zzz-aucun-evenement');
        $I->dontSeeElement('table#ajouts td a.titre');

        $I->amOnPage(self::URL . '?terme=');
        $I->seeElement('table#ajouts td a.titre');
    }

    /**
     * Les en-têtes triables pointent sur order_by/order_dir, et la colonne courante porte
     * l'inverse du sens en cours : c'est ce qui permet de basculer en recliquant.
     */
    public function sortableHeadersToggleDirection(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        $I->amOnPage(self::URL . '?order_by=titre&order_dir=asc');
        $I->seeElement('table#ajouts th.ici a[href="?order_by=titre&order_dir=desc"]');

        $I->amOnPage(self::URL . '?order_by=dateAjout&order_dir=desc');
        $I->seeElement('table#ajouts th.ici a[href="?order_by=dateAjout&order_dir=asc"]');
    }

    /**
     * Le formulaire d'édition groupée supprime : il porte un jeton CSRF, et une confirmation
     * au moment de l'envoi.
     */
    public function bulkFormIsProtected(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->seeElement('form#form-events-bulk[data-confirm]');
        $I->seeElement('form#form-events-bulk input[type=hidden][name=token]');
        $I->seeElement('input[type=checkbox][name=supprimerSerie][data-confirm]');
    }

    /**
     * Les champs rarement utilisés en édition groupée sont repliés, les autres visibles.
     */
    public function bulkFormFoldsRarelyUsedFields(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->seeNumberOfElements('details.events-bulk-repliable', 3);

        // visibles : statut, catégorie, lieu, horaires, organisateurs
        $I->seeElement('#ajouter_editer > fieldset select#idLieu');
        $I->seeElement('#ajouter_editer > fieldset input#horaire_debut');
        $I->seeElement('#ajouter_editer > fieldset select#organisateurs');

        // repliés : les autres champs du lieu, le contenu de l'événement, les fichiers
        $I->seeElement('details.events-bulk-repliable input#nomLieu');
        $I->seeElement('details.events-bulk-repliable textarea#description');
        $I->seeElement('details.events-bulk-repliable input#flyer');
    }

    /**
     * Un seul bouton « Remplacer », en pied de formulaire.
     */
    public function onlyOneSubmitButtonRemains(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();
        $I->amOnPage(self::URL);

        $I->seeNumberOfElements('form#form-events-bulk input[type=submit][value=Remplacer]', 1);
    }
}
