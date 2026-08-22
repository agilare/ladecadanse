<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;
use Ladecadanse\Utils\DateHelper;

/**
 * Colonnes mensuelles des listes de lieux et d'organisateurs (#178, point 1).
 *
 * Ce que ces tests protègent : la garde d'affichage. Les compteurs disent qui a cessé d'annoncer
 * et à partir de quand — une information de suivi interne, qui n'a rien à faire dans la page
 * publique. Le rendu des cellules, lui, est du ressort des CSS et ne s'assure pas ici.
 *
 * Lecture seule : ces tests ne font que charger deux pages.
 */
class ListesActiviteMensuelleCest
{
    private const MONTHS_NB = 12;

    /** @var list<string> */
    private const LISTINGS = ['/lieu/lieux.php', '/organisateur/organisateurs.php'];

    public function colonnesMensuellesAbsentesPourUnVisiteur(SiteTester $I)
    {
        foreach (self::LISTINGS as $url)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->dontSeeElement('th.mois');
            $I->dontSeeElement('td.mois');
        }
    }

    public function colonnesMensuellesVisiblesPourUnAdministrateur(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        foreach (self::LISTINGS as $url)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeNumberOfElements('table#derniers_lieux thead th.mois', self::MONTHS_NB);

            // la fenêtre glisse : elle doit se terminer sur le mois en cours, sinon les colonnes
            // décrivent une période révolue sans que rien ne le signale
            $moisCourant = DateHelper::monthName((int) date('n')) . ' ' . date('Y');
            $I->seeElement('table#derniers_lieux thead th.mois[title="' . $moisCourant . '"]');
        }
    }

    /**
     * Le rappel de l'an passé occupe une deuxième ligne dans chaque cellule, y compris quand il
     * est vide : sans ce saut de ligne inconditionnel, les cellules n'auraient pas toutes la même
     * hauteur et les compteurs cesseraient de se lire sur une ligne de base commune.
     */
    public function chaqueCelluleMensuelleReserveLaLigneDuRappel(SiteTester $I)
    {
        $I->skipUnlessConfigured('LADECADANSE_SITE_ADMIN_USER', 'LADECADANSE_SITE_ADMIN_PASS');

        $I->loginAsAdmin();

        foreach (self::LISTINGS as $url)
        {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(HttpCode::OK);

            $cellulesNb = count($I->grabMultiple('table#derniers_lieux tbody td.mois'));
            $I->assertGreaterThan(0, $cellulesNb, "aucune cellule mensuelle sur $url");
            $I->seeNumberOfElements('table#derniers_lieux tbody td.mois br', $cellulesNb);
        }
    }
}
