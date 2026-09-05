<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Formulaire d'ajout et de modification d'un texte de lieu (lieu-text-edit.php).
 *
 * Verrouille ce que la réécriture a rendu décidable avant le premier octet de HTML : les
 * statuts des refus, et la disparition de la liste déroulante des lieux — c'est elle qui
 * laissait le client désigner le lieu écrit.
 *
 * Suite read-only : ce fichier ne fait que des GET. Ne pas y soumettre le formulaire, un
 * envoi valide écrirait dans `descriptionlieu`.
 */
class LieuTexteEditFormulaireCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS'
        );
    }

    /**
     * Le lieu n'est plus choisi dans une liste : il vient de l'url, dont le droit a été
     * vérifié. Le select le laissait désigner par le client, et le contrôle de droits,
     * conditionné à un `idL` que l'url ne porte pas à l'ajout, ne s'exécutait pas.
     */
    public function leFormulaireNeLaissePlusChoisirLeLieu(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->urlAjout('description'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#ajouter_editer');
        $I->dontSeeElement('#ajouter_editer select[name=idLieu]');
        $I->dontSeeElement('#ajouter_editer input[type=hidden][name=idLieu]');
    }

    /**
     * Témoin de soumission et jeton CSRF : le premier décide seul du ré-affichage (le bouton
     * étant désactivé par `js-submit-freeze-wait`, son nom ne part pas), le second manquait.
     */
    public function leFormulairePorteSonTemoinEtSonJeton(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage($this->urlAjout('presentation'));

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('input[type=hidden][name=form_submitted]');
        $I->seeElement('input[type=hidden][name=token]');
    }

    /**
     * Une description et une présentation n'ont ni les mêmes droits ni la même place dans la
     * fiche : le type absent finissait en page blanche (trigger_error puis exit), le type
     * inconnu en 500 — l'exception de `validateUrlQueryValue()` que rien n'attrapait.
     */
    public function unTypeAbsentOuInconnuRepond400(SiteTester $I)
    {
        $I->loginAsAdmin();

        foreach (['', '&type=nimportequoi'] as $type)
        {
            $I->amOnPage('/lieu-text-edit.php?action=ajouter&idL=' . $this->idLieu() . $type);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            $I->dontSeeElement('#ajouter_editer');
        }
    }

    /**
     * Un lieu inconnu répond 404, au lieu d'un formulaire sous un titre sans nom.
     */
    public function unLieuInconnuRepond404(SiteTester $I)
    {
        $I->loginAsAdmin();

        $I->amOnPage('/lieu-text-edit.php?action=ajouter&type=description&idL=99999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->dontSeeElement('#ajouter_editer');
    }

    /**
     * Un texte inexistant aussi : le champ s'affichait vide, et l'UPDATE qui suivait ne
     * touchait aucune ligne en annonçant une réussite.
     */
    public function unTexteInconnuRepond404(SiteTester $I)
    {
        $I->loginAsAdmin();

        $I->amOnPage('/lieu-text-edit.php?action=editer&type=presentation&idL=' . $this->idLieu() . '&idP=99999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->dontSeeElement('#ajouter_editer');
    }

    private function idLieu(): int
    {
        return TestEnv::getInt('LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS');
    }

    private function urlAjout(string $type): string
    {
        return '/lieu-text-edit.php?action=ajouter&type=' . $type . '&idL=' . $this->idLieu();
    }
}
