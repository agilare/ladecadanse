<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Page de profil (user/dashboard.php).
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
    /** Le compteur d'éléments est porté par l'onglet lui-même, plus par un titre de liste. */
    private const COMPTEUR_ONGLET_EVENEMENTS = 'nav.tabs a[href*="elements=evenement"] sup';

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
        $I->click('a[href^="/user/dashboard.php?idP="]');
        $I->seeResponseCodeIs(HttpCode::OK);

        return $I->grabFromCurrentUrl('~idP=(\d+)~');
    }

    /**
     * La page est réservée aux membres connectés ; un visiteur anonyme part sur le formulaire de
     * connexion au lieu de voir une adresse e-mail.
     */
    public function anonymousIsSentToLogin(SiteTester $I)
    {
        $I->amOnPage('/user/dashboard.php?idP=1');
        $I->seeInCurrentUrl('/user/login.php');
    }

    /**
     * Le profil de l'utilisateur connecté s'affiche, avec le lien d'édition.
     */
    public function ownProfileIsReachable(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->see('Compte de', 'h1');
        $I->see('Identifiant');
        $I->see('E-mail');
        $I->see('Affiliations');
        $I->seeElement('a[href*="/user-edit.php"]');
    }

    /**
     * Les lignes ajoutées au tableau de profil. Celle des valeurs par défaut n'est pas testée : elle
     * est masquée quand le compte n'en a aucune, et la suite ne peut pas en enregistrer.
     */
    public function profileTableShowsSignatureAndRegistration(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->see('Votre signature des événements ajoutés');
        $I->see('Inscription');
    }

    /**
     * Le niveau du compte n'a de sens que pour qui peut le changer : il est réservé aux
     * administrateurs, y compris sur son propre profil.
     */
    public function groupIsShownToAdminsOnly(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);
        $I->dontSeeElement('.profil-groupe');

        $I->skipUnlessConfigured(
            'LADECADANSE_SITE_ADMIN_USER',
            'LADECADANSE_SITE_ADMIN_PASS'
        );

        $I->logout();
        $I->loginAsAdmin();
        $this->grabMyProfileId($I);
        $I->seeElement('.profil-groupe');
    }

    /**
     * La colonne de droite, vide sur cette page, réservait une gouttière de 180 px. Sa suppression
     * ne tient que si 'user' figure aussi dans la liste de _header.inc.php qui retire le
     * padding-right du conteneur : sans cela le contenu reste décalé face à du vide.
     */
    public function rightColumnIsGoneAndGutterReclaimed(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->dontSeeElement('#colonne_droite');
        $I->seeElement('#conteneur[style*="padding-right: 5px"]');
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

        $I->seeElement('nav.tabs a[href*="elements=evenement"]');
        $I->seeElement('nav.tabs a[href*="elements=description"]');
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

        $I->amOnPage('/user/dashboard.php?idP=' . $idP . '&elements=description&tri=titre');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeElement('#colonne_gauche');
    }

    /**
     * Une sortie en erreur rend une page complète, pied de page compris.
     */
    public function missingIdRendersACompletePage(SiteTester $I)
    {
        $I->loginAsActor();

        $I->amOnPage('/user/dashboard.php');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeElement('#pied');
    }

    /**
     * L'onglet Événements : compteur porté par l'onglet, filtre par titre, et les colonnes ajoutées.
     *
     * L'ordre des colonnes est vérifié parce que c'est une demande explicite de l'issue et que rien
     * d'autre ne le protège : réordonner $colonnes sans réordonner les <td> passerait inaperçu, les
     * cellules restant simplement décalées.
     */
    public function eventsTabHasCountFilterAndNewColumns(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->seeElement(self::COMPTEUR_ONGLET_EVENEMENTS);
        $I->seeElement('form.filtre-liste input[type=search][name=terme]');
        $I->seeElement('form.filtre-liste button.js-clear-search-field');

        if ((int) $I->grabTextFrom(self::COMPTEUR_ONGLET_EVENEMENTS) === 0)
        {
            return;
        }

        $entetes = array_map('trim', $I->grabMultiple('table#ajouts thead th'));
        $I->assertSame(['Titre', 'Lieu', 'Date', 'Catégorie', 'Horaire'], array_slice($entetes, 0, 5));

        // les actions reprennent les icônes globales, partagées avec les autres écrans de gestion
        $I->seeElement('table#ajouts td.actions img');
    }

    /**
     * Le champ de recherche est habillé par un formulaire, pas par le navigateur : hors de
     * form#ajouter_editer, aucune règle ne donne de bordure aux champs, et le filtre s'affichait
     * avec les bords natifs. Le nom « submit » est proscrit sur le bouton : il masque
     * form.submit(), et le bouton de vidage ne relançait alors plus la recherche.
     */
    public function filterFieldIsStyledAndClearableByScript(SiteTester $I)
    {
        $I->loginAsActor();
        $this->grabMyProfileId($I);

        $I->seeElement('form.filtre-liste input[type=submit]');
        $I->dontSeeElement('form.filtre-liste input[name=submit]');
    }

    /**
     * Le filtre atteint bien la requête, et le décompte de la pagination le suit — sans quoi elle
     * annoncerait des pages vides. Le compteur des onglets, lui, reste le total du compte : il
     * annonce ce que l'onglet contient, pas ce que le filtre en retient.
     */
    public function titleFilterReachesTheQuery(SiteTester $I)
    {
        $I->loginAsActor();
        $idP = $this->grabMyProfileId($I);

        $total = (int) $I->grabTextFrom(self::COMPTEUR_ONGLET_EVENEMENTS);

        $I->amOnPage('/user/dashboard.php?idP=' . $idP . '&elements=evenement&terme=zzz-aucun-titre-ne-contient-ceci');
        $I->see('Aucun événement ne correspond à ce titre');
        $I->see((string) $total, self::COMPTEUR_ONGLET_EVENEMENTS);

        if ($total === 0)
        {
            return;
        }

        // preuve que le filtre a bien atteint le COUNT : sans lui, $tot_elements vaudrait le total
        // du compte et la page afficherait encore son tableau et son menu du nombre de lignes
        $I->dontSeeElement('table#ajouts');
        $I->dontSeeElement('#order_navigation');
    }

    /**
     * Le choix du nombre de lignes et le filtre sont propres aux événements : la liste des
     * descriptions, courte par nature, s'en passe.
     */
    public function descriptionsTabHasNeitherRowCountMenuNorFilter(SiteTester $I)
    {
        $I->loginAsActor();
        $idP = $this->grabMyProfileId($I);

        $I->amOnPage('/user/dashboard.php?idP=' . $idP . '&elements=description');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeElement('#order_navigation');
        $I->dontSeeElement('form.filtre-liste');
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

        $I->amOnPage('/user/dashboard.php?idP=' . $idAdmin);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
