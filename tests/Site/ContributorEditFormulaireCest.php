<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Profil d'organisateur d'événements (user/contributor-edit.php), fieldset « Affiliation(s) ».
 *
 * Verrouille la présélection de la liste des rattachements au ré-affichage après une erreur de
 * validation. Elle réunissait par un OR la saisie postée et ce que portait la base : un
 * rattachement que l'utilisateur venait de retirer revenait coché à chaque ré-affichage, donc
 * impossible à retirer tant qu'une autre erreur bloquait l'enregistrement.
 *
 * Depuis #102, lieux et organisateurs partagent une seule liste, `affiliations[]`, dont les
 * valeurs portent leur type (`lieu:42`, `orga:17`).
 *
 * Le fieldset « Événements » de la même page a ses propres tests : cf. UserEventDefaultsCest.
 *
 * Suite read-only : les POST de ce fichier portent tous un horaire par défaut invalide, donc la
 * validation échoue et l'UPDATE n'est pas atteint. Ne pas y soumettre de formulaire valide.
 */
class ContributorEditFormulaireCest
{
    private const OPTIONS_SELECTIONNEES = '#affiliations option[selected]';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_USER_ID_WITH_ORGANISATEURS'
        );
    }

    /**
     * Après une erreur de validation, la liste rend la sélection postée, et elle seule.
     *
     * Un horaire invalide garantit l'erreur : rien n'est écrit en base, et le formulaire est
     * ré-affiché au lieu de rediriger vers le tableau de bord.
     */
    public function organisateurRetireNeRevientPasApresUneErreur(SiteTester $I)
    {
        $I->loginAsAdmin();
        $this->amOnUserEdit($I);

        $selectionnes = $I->grabMultiple(self::OPTIONS_SELECTIONNEES, 'value');

        $I->assertGreaterThanOrEqual(
            2,
            count($selectionnes),
            'LADECADANSE_TEST_USER_ID_WITH_ORGANISATEURS doit désigner un compte rattaché à au moins '
            . "deux fiches actives — lieu ou organisateurs, la liste les réunit : sans rattachement "
            . "à retirer, ce test ne vérifierait rien."
        );

        $garde = array_shift($selectionnes);

        $I->submitForm('#ajouter_editer', [
            'ev_defaults_horaire_debut' => 'pas une heure', // erreur garantie, rien n'est enregistré
            'affiliations' => [$garde],
        ]);

        // le formulaire est bien ré-affiché en erreur, et non enregistré puis redirigé
        $I->seeElement('#ajouter_editer');
        $I->seeElement('.msg_erreur');

        $I->seeElement('#affiliations option[value="' . $garde . '"][selected]');

        foreach ($selectionnes as $valeur)
        {
            $I->dontSeeElement('#affiliations option[value="' . $valeur . '"][selected]');
        }

        $I->seeNumberOfElements(self::OPTIONS_SELECTIONNEES, 1);
    }

    /**
     * Cas limite du test précédent : une liste multiple entièrement désélectionnée ne poste
     * aucune clé « affiliations ». Côté serveur, « champ vidé » est alors indiscernable de
     * « premier affichage » sur le seul $_POST['affiliations'] — c'est le témoin
     * « formulaire », posté dans tous les cas, qui les sépare.
     *
     * Sans lui, ce cas-ci retomberait sur les rattachements de la base et resterait cassé alors
     * même que le test précédent passerait.
     */
    public function organisateursTousRetiresNeReviennentPasApresUneErreur(SiteTester $I)
    {
        $I->loginAsAdmin();
        $this->amOnUserEdit($I);

        $I->assertNotEmpty(
            $I->grabMultiple(self::OPTIONS_SELECTIONNEES, 'value'),
            'LADECADANSE_TEST_USER_ID_WITH_ORGANISATEURS doit désigner un compte rattaché à au moins '
            . "une fiche active : sans rien à désélectionner, ce test ne vérifierait rien."
        );

        $I->submitForm('#ajouter_editer', [
            'ev_defaults_horaire_debut' => 'pas une heure', // erreur garantie, rien n'est enregistré
            'affiliations' => [],
        ]);

        $I->seeElement('#ajouter_editer');
        $I->seeElement('.msg_erreur');

        $I->seeElement('#affiliations');
        $I->dontSeeElement(self::OPTIONS_SELECTIONNEES);
    }

    private function amOnUserEdit(SiteTester $I): void
    {
        $I->amOnPage('/user/contributor-edit.php?idP=' . TestEnv::getInt('LADECADANSE_TEST_USER_ID_WITH_ORGANISATEURS'));
        $I->seeResponseCodeIs(HttpCode::OK);

        // la liste n'est rendue qu'aux groupes <= 6, et le compte visé doit être modifiable
        $I->seeElement('#affiliations');
    }
}
