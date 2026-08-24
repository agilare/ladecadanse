<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Formulaire d'ajout/édition d'un événement (evenement-edit.php).
 *
 * Verrouille ce que le refactoring du fichier peut casser sans que rien ne le signale :
 * le rattachement des salles à leur lieu dans le select (les salles sont désormais
 * chargées en une requête puis indexées par lieu, au lieu d'une requête par lieu),
 * et la bascule entre le formulaire public et le formulaire connecté.
 *
 * Suite read-only : ces tests ne font que des GET.
 */
class EvenementEditFormulaireCest
{
    private const SELECT_LIEUX = '//select[@name="idLieu"]';
    private const SELECT_LOCALITES = '//select[@name="localite_id"]';

    /**
     * Chaque salle active est proposée une fois et une seule, sous un lieu, au format
     * « idLieu_idSalle » que le traitement du POST re-découpe (preg_match "^[0-9]+_[0-9]+$").
     *
     * L'unicité est ce qui rend ce test utile : les salles sont désormais chargées en une
     * requête puis lues dans un tableau indexé par lieu. Une clé d'indexation fautive donne
     * soit un select sans aucune salle, soit les mêmes salles répétées sous chaque lieu —
     * les deux symptômes sont attrapés ici.
     *
     * Ce qui n'est PAS vérifiable depuis le HTML : qu'une salle soit sous le bon lieu.
     * La value comme le libellé sont réécrits à partir du lieu en cours de boucle, donc
     * ils concordent toujours, même si la salle vient d'ailleurs.
     */
    public function chaqueSalleEstProposeeUneSeuleFois(SiteTester $I)
    {
        $I->amOnPage('/evenement-edit.php?action=ajouter');
        $I->seeResponseCodeIs(HttpCode::OK);

        $idSallesVues = [];

        foreach ($this->optionsDuSelectLieux($I) as [$valeur, $texte])
        {
            // les options « lieu » portent un id nu, les options « salle » un id composé
            if (!str_contains($valeur, '_'))
            {
                continue;
            }

            $I->assertMatchesRegularExpression(
                '/^[0-9]+_[0-9]+$/',
                $valeur,
                "La value « $valeur » n'est pas au format idLieu_idSalle attendu par le POST"
            );

            [, $idSalle] = explode('_', $valeur);

            $I->assertNotContains(
                $idSalle,
                $idSallesVues,
                "La salle $idSalle (« $texte ») est proposée plusieurs fois dans le select"
            );

            $idSallesVues[] = $idSalle;

            // libellé « <nom du lieu> – <nom de la salle> » : la partie salle doit exister
            $I->assertMatchesRegularExpression(
                '/\S+\s+–\s+\S+/u',
                $texte,
                "Le libellé « $texte » ne nomme pas la salle après son lieu"
            );
        }

        $I->assertNotEmpty(
            $idSallesVues,
            'Aucune salle dans le select : le test ne vérifierait rien. '
            . "Il faut au moins un lieu ayant une salle au statut 'actif' sur l'instance testée."
        );
    }

    /**
     * Les lieux sont groupés par canton sous un libellé lisible, pas sous le code brut
     * stocké en base ('ge', 'vd'...). L'option vide reste en tête, hors de tout groupe,
     * pour que le select puisse n'avoir aucun lieu sélectionné.
     */
    public function lieuxSontGroupesParCantonLisible(SiteTester $I)
    {
        $I->amOnPage('/evenement-edit.php?action=ajouter');
        $I->seeResponseCodeIs(HttpCode::OK);

        $document = $this->chargerPage($I);
        $xpath = new DOMXPath($document);

        $groupes = $xpath->query(self::SELECT_LIEUX . '/optgroup');

        $I->assertGreaterThan(0, $groupes->length, 'Le select des lieux ne rend aucun optgroup');

        foreach ($groupes as $groupe)
        {
            $label = $groupe->getAttribute('label');

            $I->assertNotSame('', trim($label), 'Un optgroup du select des lieux a un label vide');
            $I->assertNotContains(
                $label,
                ['ge', 'vd', 'fr', 'rf', 'hs'],
                "L'optgroup affiche le code brut « $label » au lieu du nom du canton"
            );
            $I->assertGreaterThan(0, $xpath->query('option', $groupe)->length, "L'optgroup « $label » est vide");
        }

        $I->assertSame(
            '',
            $xpath->query(self::SELECT_LIEUX . '/option')->item(0)?->getAttribute('value'),
            'La première option du select des lieux doit être vide et hors optgroup'
        );
    }

    /**
     * Les localités sont groupées par canton sous un libellé lisible, et chaque option porte
     * un id de localité — plus jamais un code de région.
     *
     * La France ('rf') et « Autre » ('hs') étaient greffées en dur sur ce select, avec leur
     * code de région pour value ; ce sont maintenant des localités comme les autres. Une
     * value non numérique signalerait le retour de cette exception, qu'un seul des trois
     * formulaires partageant le select suffirait à réintroduire.
     *
     * Suppose la migration v3-12-0 passée sur l'instance testée : sans elle, la localité 1 a
     * encore un canton vide, donc un optgroup sans libellé.
     */
    public function localitesSontGroupeesParCantonLisible(SiteTester $I)
    {
        $I->amOnPage('/evenement-edit.php?action=ajouter');
        $I->seeResponseCodeIs(HttpCode::OK);

        $xpath = new DOMXPath($this->chargerPage($I));

        $groupes = $xpath->query(self::SELECT_LOCALITES . '/optgroup');
        $I->assertGreaterThan(0, $groupes->length, 'Le select des localités ne rend aucun optgroup');

        foreach ($groupes as $groupe)
        {
            $label = $groupe->getAttribute('label');

            $I->assertNotSame('', trim($label), 'Un optgroup du select des localités a un label vide');
            $I->assertNotContains(
                $label,
                ['ge', 'vd', 'fr', 'rf', 'hs'],
                "L'optgroup affiche le code brut « $label » au lieu du nom du canton"
            );
            $I->assertGreaterThan(0, $xpath->query('option', $groupe)->length, "L'optgroup « $label » est vide");
        }

        $I->assertSame(
            '',
            $xpath->query(self::SELECT_LOCALITES . '/option')->item(0)?->getAttribute('value'),
            'La première option du select des localités doit être vide et hors optgroup'
        );

        // « 44_Pâquis » pour un quartier de Genève, un id nu partout ailleurs : c'est ce que
        // re-découpe le traitement du POST pour en tirer la localité, le quartier et la région
        foreach ($xpath->query(self::SELECT_LOCALITES . '/optgroup/option') as $option)
        {
            $value = $option->getAttribute('value');

            $I->assertMatchesRegularExpression(
                '/^[0-9]+(_.+)?$/u',
                $value,
                "L'option « $value » du select des localités n'est pas un id de localité"
            );
        }
    }

    /**
     * Formulaire public (visiteur non connecté) : l'e-mail est demandé et obligatoire,
     * le choix du statut n'est pas offert, et le bouton annonce un envoi pour validation
     * plutôt qu'un enregistrement direct.
     *
     * Ces trois éléments dépendent tous de la même bascule « utilisateur connecté ou non »
     * dans le fichier.
     */
    public function formulairePublicDemandeEmailEtNOffrePasLeStatut(SiteTester $I)
    {
        $I->amOnPage('/evenement-edit.php?action=ajouter');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeElement('input[type=email][name=user_email][required]');
        $I->dontSeeElement('input[type=radio][name=statut]');
        $I->seeElement('input[type=hidden][name=statut]');
        $I->seeElement('input[type=submit][name=submit][value=Envoyer]');
    }

    /**
     * En édition, l'aperçu du flyer déjà enregistré reste affiché avec sa case
     * « Supprimer » — y compris quand le champ est en erreur, sans quoi on ne pourrait
     * plus retirer l'ancien fichier tant que le nouveau est refusé.
     *
     * Le lien et la vignette doivent viser la même image : ils sont construits à partir
     * d'une seule valeur, et les voir diverger signalerait une régression de ce bloc.
     */
    public function apercuDuFlyerEstAfficheAvecSonBoutonSupprimer(SiteTester $I)
    {
        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS',
            'LADECADANSE_TEST_EVENT_ID_WITH_FLYER'
        );

        $I->loginAsAdmin();
        $I->amOnPage('/evenement-edit.php?action=editer&idE=' . TestEnv::getInt('LADECADANSE_TEST_EVENT_ID_WITH_FLYER'));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeElement('.supImg a.magnific-popup img');
        $I->seeElement('.supImg input[type=checkbox][name=sup_flyer]');
        $I->seeElement('.supImg label[for=sup_flyer]');

        $I->assertSame(
            $I->grabAttributeFrom('.supImg a.magnific-popup', 'href'),
            $I->grabAttributeFrom('.supImg a.magnific-popup img', 'src'),
            'Le lien et la vignette de l’aperçu doivent viser la même image'
        );
    }

    /**
     * Liste de paires [value, libellé] plutôt qu'un tableau indexé par value : PHP
     * convertirait « 1002 » en entier, et les comparaisons de chaînes s'effondreraient.
     *
     * @return list<array{0: string, 1: string}> dans l'ordre du document
     */
    private function optionsDuSelectLieux(SiteTester $I): array
    {
        $xpath = new DOMXPath($this->chargerPage($I));
        $options = [];

        foreach ($xpath->query(self::SELECT_LIEUX . '//option') as $option)
        {
            $options[] = [
                $option->getAttribute('value'),
                // &nbsp; côté HTML : le normaliser pour pouvoir comparer les libellés
                trim(str_replace("\u{a0}", ' ', $option->textContent)),
            ];
        }

        return $options;
    }

    private function chargerPage(SiteTester $I): DOMDocument
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($I->grabPageSource());
        libxml_clear_errors();

        return $document;
    }
}
