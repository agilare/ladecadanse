<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\AuthorizationRepository;
use Ladecadanse\UserLevel;

/**
 * Droits d'écriture sur les textes d'un lieu — `Authorization::isPersonneAllowedToAddTexteLieu()`
 * et `isPersonneAllowedToEditTexteLieu()`.
 *
 * Ces deux règles étaient écrites à la main dans `lieu/text-edit.php` et, une seconde fois et
 * autrement, dans `lieu/lieu.php`, où les parenthèses se refermaient mal. La table de vérité
 * ci-dessous est ce que les deux versions étaient censées dire.
 *
 * Couvertes ici plutôt que par la suite `site` : elles dépendent d'une personne rattachée à un
 * lieu, ce qu'aucune fixture de la base de test ne porte aujourd'hui.
 */
final class AuthorizationTexteLieuTest extends Unit
{
    private const ID_LIEU = 42;
    private const ID_AUTEUR = 7;

    public function testUnEditeurEcritUneDescriptionSurNimporteQuelLieu(): void
    {
        $authorization = $this->authorization(affilie: false, dansOrganisateur: false);

        $this->assertTrue($authorization->isPersonneAllowedToAddTexteLieu(
            $this->session(UserLevel::AUTHOR), 'description', self::ID_LIEU
        ));
    }

    /**
     * Une description est un avis signé : la tenue du lieu ne donne pas le droit d'en écrire.
     */
    public function testUnActeurNecritPasDeDescriptionMemeSurSonLieu(): void
    {
        $authorization = $this->authorization(affilie: true, dansOrganisateur: true);

        $this->assertFalse($authorization->isPersonneAllowedToAddTexteLieu(
            $this->session(UserLevel::ACTOR), 'description', self::ID_LIEU
        ));
    }

    /**
     * Une présentation parle au nom du lieu : qui tient la fiche l'écrit.
     */
    public function testUnActeurAffilieEcritLaPresentationDeSonLieu(): void
    {
        $authorization = $this->authorization(affilie: true, dansOrganisateur: false);

        $this->assertTrue($authorization->isPersonneAllowedToAddTexteLieu(
            $this->session(UserLevel::ACTOR), 'presentation', self::ID_LIEU
        ));
    }

    public function testUnActeurMembreDunOrganisateurDuLieuEcritSaPresentation(): void
    {
        $authorization = $this->authorization(affilie: false, dansOrganisateur: true);

        $this->assertTrue($authorization->isPersonneAllowedToAddTexteLieu(
            $this->session(UserLevel::ACTOR), 'presentation', self::ID_LIEU
        ));
    }

    /**
     * Le trou que refermait la réécriture : le contrôle de droits, conditionné à un `idL` que
     * l'url ne porte pas à l'ajout, ne s'exécutait pas, et la liste déroulante offrait tous les
     * lieux actifs.
     */
    public function testUnActeurEtrangerAuLieuNecritPasSaPresentation(): void
    {
        $authorization = $this->authorization(affilie: false, dansOrganisateur: false);

        $this->assertFalse($authorization->isPersonneAllowedToAddTexteLieu(
            $this->session(UserLevel::ACTOR), 'presentation', self::ID_LIEU
        ));
    }

    /**
     * Plancher explicite : `isPersonneAllowedToEditLieu()` ne regarde pas le niveau, et une
     * affiliation suffirait sinon à ouvrir le formulaire à un compte que la page refuse d'emblée.
     */
    public function testUnMembreAffilieNecritRien(): void
    {
        $authorization = $this->authorization(affilie: true, dansOrganisateur: true);

        $this->assertFalse($authorization->isPersonneAllowedToAddTexteLieu(
            $this->session(UserLevel::MEMBER), 'presentation', self::ID_LIEU
        ));
    }

    public function testUnEditeurReprendSaPropreDescription(): void
    {
        $authorization = $this->authorization(affilie: false, dansOrganisateur: false);

        $this->assertTrue($authorization->isPersonneAllowedToEditTexteLieu(
            $this->session(UserLevel::AUTHOR, self::ID_AUTEUR), 'description', self::ID_LIEU, self::ID_AUTEUR
        ));
    }

    /**
     * Le formulaire ouvrait toute description à tout éditeur pour peu qu'il change l'`idP`
     * de l'url.
     */
    public function testUnEditeurNeReprendPasLaDescriptionDunAutre(): void
    {
        $authorization = $this->authorization(affilie: false, dansOrganisateur: false);

        $this->assertFalse($authorization->isPersonneAllowedToEditTexteLieu(
            $this->session(UserLevel::AUTHOR, 99), 'description', self::ID_LIEU, self::ID_AUTEUR
        ));
    }

    public function testLaModerationReprendLaDescriptionDunAutre(): void
    {
        $authorization = $this->authorization(affilie: false, dansOrganisateur: false);

        $this->assertTrue($authorization->isPersonneAllowedToEditTexteLieu(
            $this->session(UserLevel::ADMIN, 99), 'description', self::ID_LIEU, self::ID_AUTEUR
        ));
    }

    /**
     * Une présentation se reprend par quiconque pourrait l'écrire : elle parle du lieu, et non
     * de la personne qui l'a rédigée — souvent partie depuis.
     */
    public function testUnActeurAffilieReprendLaPresentationDunAutre(): void
    {
        $authorization = $this->authorization(affilie: true, dansOrganisateur: false);

        $this->assertTrue($authorization->isPersonneAllowedToEditTexteLieu(
            $this->session(UserLevel::ACTOR, 99), 'presentation', self::ID_LIEU, self::ID_AUTEUR
        ));
    }

    public function testUneSessionSansNiveauNecritRien(): void
    {
        $authorization = $this->authorization(affilie: true, dansOrganisateur: true);

        $this->assertFalse($authorization->isPersonneAllowedToAddTexteLieu([], 'presentation', self::ID_LIEU));
        $this->assertFalse($authorization->isPersonneAllowedToEditTexteLieu([], 'description', self::ID_LIEU, self::ID_AUTEUR));
    }

    /**
     * @return array<string, int>
     */
    private function session(int $groupe, int $idPersonne = self::ID_AUTEUR): array
    {
        return ['Sgroupe' => $groupe, 'SidPersonne' => $idPersonne];
    }

    private function authorization(bool $affilie, bool $dansOrganisateur): Authorization
    {
        $repository = $this->createStub(AuthorizationRepository::class);
        $repository->method('isPersonneAffiliatedWithLieu')->willReturn($affilie);
        $repository->method('isPersonneInLieuByOrganisateur')->willReturn($dansOrganisateur);

        return new Authorization($repository);
    }
}
