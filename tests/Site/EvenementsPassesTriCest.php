<?php

use Tests\Support\SiteTester;
use Tests\Support\TestEnv;

use Codeception\Util\HttpCode;

/**
 * Menu de tri des événements passés des fiches lieu et organisateur.
 *
 * Ces deux pages n'avaient aucun test. Ce Cest ne couvre que la fonctionnalité ajoutée : le menu de
 * tri proposé sous l'onglet « Passés », son sens de tri et sa mémorisation en session.
 *
 * Trois choses ne se voient pas dans le HTML rendu et sont pourtant tout l'enjeu :
 *
 * - le sens du tri vient d'un ORDER BY dont la direction est interpolée dans le SQL ; seule la
 *   suite des dates rendues prouve qu'il a bougé ;
 * - la page d'atterrissage change avec le sens (dernière page en ascendant, première en
 *   descendant), faute de quoi le lecteur qui bascule en anti-chronologique tombe sur les
 *   événements les plus *anciens* — exactement l'inverse de ce qui est demandé ;
 * - la préférence est unique pour les deux pages, comme user_prefs_agenda_order l'est entre
 *   l'accueil et la fiche événement.
 *
 * Lecture seule : uniquement des GET, aucune écriture, aucune connexion nécessaire (les fiches sont
 * publiques).
 */
class EvenementsPassesTriCest
{
    /**
     * La date ISO de chaque ligne est portée par l'abbr hCalendar, pas par le texte affiché
     * (« sam 15 »), qui ne dit ni le mois ni l'année.
     */
    private const DATES_DES_LIGNES = '#prochains_evenements abbr.dtstart';

    private string $urlLieu = '';

    public function _before(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_LIEU_ID_WITH_PAST_EVENTS');

        $this->urlLieu = '/lieu/lieu.php?idL=' . TestEnv::getInt('LADECADANSE_TEST_LIEU_ID_WITH_PAST_EVENTS');
    }

    /**
     * Les dates des événements de la page courante, dans l'ordre du rendu.
     *
     * Réduites à leur partie calendaire : l'abbr porte aussi l'horaire quand l'événement en a un, et
     * deux événements du même jour ne sont pas ordonnés entre eux par un ORDER BY sur dateEvenement.
     * Comparer les chaînes complètes ferait donc échouer un tri pourtant correct.
     *
     * @return string[]
     */
    private function grabEventDates(SiteTester $I): array
    {
        return array_map(
            static fn (string $iso): string => mb_substr($iso, 0, 10),
            $I->grabMultiple(self::DATES_DES_LIGNES, 'title')
        );
    }

    /**
     * Le menu n'a de sens que sur les événements passés : les prochains sont chronologiques par
     * nature, et l'anti-chronologique y mettrait le plus lointain en tête.
     */
    public function sortMenuIsOfferedOnPastEventsOnly(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#order_navigation a[href*="ordre=asc"]');
        $I->seeElement('#order_navigation a[href*="ordre=desc"]');

        $I->amOnPage($this->urlLieu . '&periode=futur');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeElement('#order_navigation');
    }

    /**
     * Les boutons n'ont pas de libellé : sans title ni aria-label, deux icônes voisines ne se
     * distinguent ni au survol ni au lecteur d'écran.
     */
    public function sortButtonsCarryAnAccessibleName(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien');

        foreach (['asc', 'desc'] as $ordre)
        {
            $lien = '#order_navigation a[href*="ordre=' . $ordre . '"]';
            $I->assertNotEmpty($I->grabAttributeFrom($lien, 'title'), "Le bouton $ordre doit avoir un title");
            $I->assertNotEmpty($I->grabAttributeFrom($lien, 'aria-label'), "Le bouton $ordre doit avoir un aria-label");
            $I->seeElement($lien . ' i.fa');
        }
    }

    /**
     * Le tri par défaut est le comportement historique de la page : chronologique.
     *
     * Repose sur le fait que PhpBrowser repart avec des cookies neufs à chaque test, la session est
     * donc vierge ici. C'est aussi pourquoi ce test est déclaré avant ceux qui posent « desc ».
     */
    public function defaultOrderIsAscending(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien');
        $I->seeElement('#order_navigation a.selected[href*="ordre=asc"]');

        $dates = $this->grabEventDates($I);

        if (count($dates) < 2)
        {
            return;
        }

        $attendues = $dates;
        sort($attendues);
        $I->assertSame($attendues, $dates, 'Les événements passés doivent être rendus par date croissante');
    }

    /**
     * Le sens descendant renverse effectivement la requête, il ne se contente pas de marquer le
     * bouton comme sélectionné.
     */
    public function descendingOrderReversesTheDates(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien&ordre=desc');
        $I->seeElement('#order_navigation a.selected[href*="ordre=desc"]');

        $dates = $this->grabEventDates($I);

        if (count($dates) < 2)
        {
            return;
        }

        $attendues = $dates;
        rsort($attendues);
        $I->assertSame($attendues, $dates, 'En tri descendant les événements passés doivent être rendus par date décroissante');
    }

    /**
     * Le cœur de la fonctionnalité : quel que soit le sens choisi, on atterrit sur les événements
     * passés les plus récents — ceux qui intéressent le lecteur d'une fiche.
     *
     * En ascendant ils sont en dernière page, en descendant en première : c'est ce test qui attrape
     * un $default_page laissé inchangé, lequel enverrait le tri descendant sur la dernière page,
     * donc sur les événements les plus anciens du lieu. Il vaut aussi quand tout tient sur un seul
     * écran, la pagination ne s'affichant alors pas du tout.
     */
    public function bothOrdersLandOnTheMostRecentPastEvent(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien');
        $datesAsc = $this->grabEventDates($I);

        $I->amOnPage($this->urlLieu . '&periode=ancien&ordre=desc');
        $datesDesc = $this->grabEventDates($I);

        if ($datesAsc === [] || $datesDesc === [])
        {
            return;
        }

        $I->assertSame(
            max($datesAsc),
            max($datesDesc),
            "Les deux sens de tri doivent atterrir sur la page qui porte l'événement passé le plus récent"
        );
        $I->assertSame(
            max($datesDesc),
            $datesDesc[0],
            "En tri descendant l'événement passé le plus récent doit être en tête de page"
        );
    }

    /**
     * La préférence survit à la navigation : sans elle, il faudrait rechoisir le sens à chaque page
     * de la pagination, dont les liens ne portent pas le paramètre.
     */
    public function chosenOrderIsRememberedInSession(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien&ordre=desc');
        $datesChoisies = $this->grabEventDates($I);

        // sans le paramètre : seule la session peut encore porter le choix
        $I->amOnPage($this->urlLieu . '&periode=ancien');
        $I->seeElement('#order_navigation a.selected[href*="ordre=desc"]');
        $I->assertSame($datesChoisies, $this->grabEventDates($I));
    }

    /**
     * Une seule préférence pour les deux fiches, à l'image de user_prefs_agenda_order : un lecteur
     * qui a choisi son sens sur un lieu le retrouve sur un organisateur.
     */
    public function chosenOrderIsSharedWithTheOrganisateurPage(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_TEST_ORGA_ID_WITH_PAST_EVENTS');

        $I->amOnPage($this->urlLieu . '&periode=ancien&ordre=desc');
        $I->seeElement('#order_navigation a.selected[href*="ordre=desc"]');

        $I->amOnPage('/organisateur/organisateur.php?idO=' . TestEnv::getInt('LADECADANSE_TEST_ORGA_ID_WITH_PAST_EVENTS') . '&periode=ancien');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#order_navigation a.selected[href*="ordre=desc"]');
    }

    /**
     * La direction du tri est interpolée dans le SQL : une valeur hors liste blanche ne doit ni
     * l'atteindre, ni écraser la préférence en session.
     *
     * L'assertion porte sur le pied de page autant que sur le code de statut : une requête qui
     * échoue en base rend quand même un 200, mais sur un document interrompu au milieu du tableau.
     */
    public function unknownOrderLeavesThePreferenceUntouched(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien&ordre=desc');
        $datesChoisies = $this->grabEventDates($I);

        $I->amOnPage($this->urlLieu . '&periode=ancien&ordre=nimportequoi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#pied');
        $I->seeElement('#order_navigation a.selected[href*="ordre=desc"]');
        $I->assertSame($datesChoisies, $this->grabEventDates($I));
    }

    /**
     * Les liens du menu abandonnent le numéro de page : sans cela, basculer de sens depuis la page 3
     * garderait la page 3, c'est-à-dire une tranche arbitraire au lieu des événements les plus
     * récents.
     */
    public function sortLinksDropThePageParameter(SiteTester $I)
    {
        $I->amOnPage($this->urlLieu . '&periode=ancien&page=1');
        $I->seeElement('#order_navigation a[href*="ordre=asc"]');
        $I->dontSeeElement('#order_navigation a[href*="page="]');
    }
}
