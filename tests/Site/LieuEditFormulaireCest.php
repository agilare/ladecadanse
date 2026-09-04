<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Formulaire d'édition d'un lieu (lieu/edit.php).
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
     * Bandeau global rendu par HtmlShrink::msgErreur() quand la validation a rejeté la saisie,
     * comme sur organisateur/edit.php. Il est absent de la page tant que rien n'est en erreur,
     * ce qui en fait le témoin de « le formulaire a été refusé, rien n'a été écrit ».
     */
    private const ERREUR_GLOBALE = '.msg_erreur';

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
        $I->seeElement(self::ERREUR_GLOBALE);

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
     * « premier affichage » si la lecture est conditionnée à isset($_POST[...]) — d'où la
     * lecture inconditionnelle de LieuEdition::lireChampsPostes().
     *
     * Sans elle, ce cas-ci retomberait sur les organisateurs de la base et resterait cassé
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
        $I->seeElement(self::ERREUR_GLOBALE);

        $I->seeElement('#organisateurs');
        $I->dontSeeElement(self::OPTIONS_ORGANISATEURS_SELECTIONNEES);
    }

    /**
     * Un identifiant de lieu inconnu répond 404, au lieu d'un formulaire vide sous un titre
     * sans nom, suivi d'un UPDATE qui ne touche aucune ligne et annonce une réussite.
     */
    public function unLieuInconnuRepond404(SiteTester $I)
    {
        $I->loginAsAdmin();

        $I->amOnPage('/lieu/edit.php?action=editer&idL=99999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->dontSeeElement('#ajouter_editer');
    }

    private function amOnLieuEdit(SiteTester $I): void
    {
        $I->amOnPage('/lieu/edit.php?action=editer&idL=' . TestEnv::getInt('LADECADANSE_TEST_LIEU_ID_WITH_ORGANISATEURS'));
        $I->seeResponseCodeIs(HttpCode::OK);

        // le select est désactivé hors des éditeurs ; le test se connecte en administrateur,
        // il doit donc bien y avoir des organisateurs à désélectionner
        $I->seeElement('#organisateurs');
    }
}
