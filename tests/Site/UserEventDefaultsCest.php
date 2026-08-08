<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Fieldset « Événements » du profil (user-edit.php) et champs qu'il préremplit (evenement-edit.php).
 *
 * La suite `site` est en lecture seule : on ne peut donc pas enregistrer des valeurs par défaut puis
 * vérifier qu'elles ressortent dans le formulaire d'ajout. Ce qui est verrouillé ici est la moitié
 * statique du dispositif — les noms des champs des deux côtés — et c'est justement ce qui casse en
 * silence : le préremplissage écrit dans $champs['horaire_debut'], $champs['idLieu']… et dans
 * $tab_organisateurs_even ; renommer un de ces champs ne produit aucune erreur, seulement un
 * formulaire qui cesse d'être prérempli.
 *
 * user-edit.php n'avait jusqu'ici aucun test.
 */
class UserEventDefaultsCest
{
    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS'
        );
    }

    /**
     * Ouvre l'édition du profil de l'utilisateur connecté.
     *
     * Par navigation plutôt que par une URL construite : l'identifiant de la personne dépend de
     * l'instance testée, et tests/.env ne le décrit pas. Le menu du header pointe vers /user.php de
     * l'utilisateur connecté, d'où part le lien « Modifier ».
     */
    private function amOnMyProfileEdit(SiteTester $I): void
    {
        $I->amOnPage('/articles/apropos.php');
        $I->click('a[href^="/user.php?idP="]');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->click('a[href*="/user-edit.php"]');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * Les six réglages sont rendus, avec les types attendus : un select pour la catégorie et le
     * lieu, des <input type="time"> pour les horaires (leur format est validé côté serveur sur
     * hh:mm), un select multiple pour les organisateurs et un texte libre pour le prix.
     */
    public function eventDefaultsFieldsetIsRenderedOnProfileEdit(SiteTester $I)
    {
        $I->loginAsAdmin();
        $this->amOnMyProfileEdit($I);

        $I->see('Valeurs par défaut à l\'ajout d\'un événement');

        $I->seeElement('select[name=ev_defaults_genre]#ev_defaults_genre');
        $I->seeElement('input[type=time][name=ev_defaults_horaire_debut]#ev_defaults_horaire_debut');
        $I->seeElement('input[type=time][name=ev_defaults_horaire_fin]#ev_defaults_horaire_fin');
        $I->seeElement('select[name=ev_defaults_idLieu]#ev_defaults_idLieu');
        $I->seeElement('select[name="ev_defaults_organisateurs[]"][multiple]#ev_defaults_organisateurs');
        $I->seeElement('input[type=text][name=ev_defaults_prix]#ev_defaults_prix');
    }

    /**
     * Le fieldset Affiliation, plus haut dans la même page, occupe déjà les noms « lieu » et
     * « organisateurs[] » : les réglages doivent rester des champs distincts. Deux <select> de même
     * name dans un seul formulaire feraient gagner le dernier, et enregistrer les valeurs par
     * défaut écraserait l'affiliation.
     */
    public function eventDefaultsDoNotCollideWithAffiliationFields(SiteTester $I)
    {
        $I->loginAsAdmin();
        $this->amOnMyProfileEdit($I);

        $I->seeElement('#lieu');
        $I->seeElement('#ev_defaults_idLieu');
        $I->seeElement('select[name="organisateurs[]"]#organisateurs');
        $I->seeElement('select[name="ev_defaults_organisateurs[]"]#ev_defaults_organisateurs');
    }

    /**
     * Les champs du formulaire d'ajout dans lesquels le préremplissage écrit.
     *
     * Aucune assertion sur les valeurs : elles dépendent des réglages du compte de test, que cette
     * suite ne peut pas fixer sans écrire en base.
     */
    public function prefilledFieldsExistOnEventAddForm(SiteTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPage('/evenement-edit.php?action=ajouter');

        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeElement('input[type=radio][name=genre]');
        $I->seeElement('input[type=time][name=horaire_debut]');
        $I->seeElement('input[type=time][name=horaire_fin]');
        $I->seeElement('select[name=idLieu]');
        $I->seeElement('select[name="organisateurs[]"]');
        $I->seeElement('input[name=prix]');
    }
}
