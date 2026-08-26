<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Formulaire d'édition d'un lieu (lieu-edit.php).
 *
 * Verrouille la présélection du select des organisateurs au ré-affichage après une erreur de
 * validation. Elle réunissait par un OR la saisie postée et les organisateurs relus en base :
 * un organisateur que l'utilisateur venait de retirer revenait coché à chaque ré-affichage,
 * donc impossible à retirer tant qu'une autre erreur bloquait l'enregistrement.
 *
 * Suite read-only : les POST de ce fichier vident tous le nom du lieu, donc la validation
 * échoue et enregistrer() n'est jamais atteint. Ne pas y soumettre de formulaire valide.
 */
class LieuEditFormulaireCest
{
    private const OPTIONS_ORGANISATEURS_SELECTIONNEES = '#organisateurs option[selected]';

    /*
     * lieu-edit.php n'a pas de bandeau global comme le .msg_erreur d'evenement-edit.php :
     * l'erreur d'un champ est rendue par Edition::getHtmlErreur() dans un <div class="msg">,
     * absent de la page tant que rien n'est en erreur.
     */
    private const ERREUR_DE_CHAMP = '#ajouter_editer .msg';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS'
        );
    }

    /**
     * Après une erreur de validation, le select des organisateurs rend la sélection postée,
     * et elle seule.
     *
     * Le nom est vidé pour garantir l'erreur : rien n'est écrit en base, et le formulaire est
     * ré-affiché au lieu de rediriger vers la fiche du lieu.
     */
    public function organisateurRetireNeRevientPasApresUneErreur(SiteTester $I)
    {
        $I->loginAsAdmin();
        $this->amOnLieuEdit($I);

        $selectionnes = $I->grabMultiple(self::OPTIONS_ORGANISATEURS_SELECTIONNEES, 'value');

        $I->assertGreaterThanOrEqual(
            2,
            count($selectionnes),
            'LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS doit désigner un lieu rattaché à au moins '
            . "deux organisateurs actifs : sans organisateur à retirer, ce test ne vérifierait rien."
        );

        $garde = array_shift($selectionnes);

        $I->submitForm('#ajouter_editer', [
            'nom' => '', // le nom est obligatoire : erreur garantie, rien n'est enregistré
            'organisateurs' => [$garde],
        ]);

        // le formulaire est bien ré-affiché en erreur, et non enregistré puis redirigé
        $I->seeElement('#ajouter_editer');
        $I->seeElement(self::ERREUR_DE_CHAMP);

        $I->seeElement('#organisateurs option[value="' . $garde . '"][selected]');

        foreach ($selectionnes as $idOrganisateur)
        {
            $I->dontSeeElement('#organisateurs option[value="' . $idOrganisateur . '"][selected]');
        }

        $I->seeNumberOfElements(self::OPTIONS_ORGANISATEURS_SELECTIONNEES, 1);
    }

    /**
     * Cas limite du test précédent : un select multiple entièrement désélectionné ne poste
     * aucune clé « organisateurs ». Côté serveur, « champ vidé » est alors indiscernable de
     * « premier affichage » sur le seul $_POST['organisateurs'] — c'est le témoin
     * « formulaire », posté dans tous les cas, qui les sépare.
     *
     * Sans lui, ce cas-ci retomberait sur les organisateurs de la base et resterait cassé
     * alors même que le test précédent passerait.
     */
    public function organisateursTousRetiresNeReviennentPasApresUneErreur(SiteTester $I)
    {
        $I->loginAsAdmin();
        $this->amOnLieuEdit($I);

        $I->assertNotEmpty(
            $I->grabMultiple(self::OPTIONS_ORGANISATEURS_SELECTIONNEES, 'value'),
            'LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS doit désigner un lieu rattaché à au moins '
            . "un organisateur actif : sans rien à désélectionner, ce test ne vérifierait rien."
        );

        $I->submitForm('#ajouter_editer', [
            'nom' => '', // le nom est obligatoire : erreur garantie, rien n'est enregistré
            'organisateurs' => [],
        ]);

        $I->seeElement('#ajouter_editer');
        $I->seeElement(self::ERREUR_DE_CHAMP);

        $I->seeElement('#organisateurs');
        $I->dontSeeElement(self::OPTIONS_ORGANISATEURS_SELECTIONNEES);
    }

    private function amOnLieuEdit(SiteTester $I): void
    {
        $I->amOnPage('/lieu-edit.php?action=editer&idL=' . TestEnv::getInt('LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS'));
        $I->seeResponseCodeIs(HttpCode::OK);

        // le select n'est rendu qu'aux groupes <= 6 ; en dessous, les organisateurs repartent
        // en champs cachés et il n'y a rien à désélectionner
        $I->seeElement('#organisateurs');
    }
}
