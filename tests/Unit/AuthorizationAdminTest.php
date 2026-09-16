<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\AuthorizationRepository;
use Ladecadanse\UserLevel;

/**
 * Seuil de la modération — `Authorization::isPersonneAdmin()`.
 *
 * C'est lui qui réserve aux administrateurs les personnes rattachées à une fiche : les affiliés
 * d'un lieu, que voyait tout auteur, et les membres d'un organisateur, que voyaient l'auteur de
 * la fiche et chacun des membres.
 *
 * Le dépôt est bouchonné : la méthode ne lit que la session.
 */
final class AuthorizationAdminTest extends Unit
{
    /**
     * @dataProvider fournirNiveaux
     */
    public function testSeulsLesAdministrateursPassentLeSeuil(int $groupe, bool $attendu): void
    {
        $this->assertSame($attendu, $this->authorization()->isPersonneAdmin(['SidPersonne' => 42, 'Sgroupe' => $groupe]), "groupe $groupe");
    }

    /** @return iterable<string, array{int, bool}> */
    public static function fournirNiveaux(): iterable
    {
        yield 'superadmin' => [UserLevel::SUPERADMIN, true];
        yield 'admin'      => [UserLevel::ADMIN, true];
        yield 'author'     => [UserLevel::AUTHOR, false];
        yield 'actor'      => [UserLevel::ACTOR, false];
        yield 'member'     => [UserLevel::MEMBER, false];
    }

    /** Un visiteur n'a pas de niveau : le refus est le défaut. */
    public function testUneSessionSansNiveauNePassePas(): void
    {
        $this->assertFalse($this->authorization()->isPersonneAdmin([]));
    }

    private function authorization(): Authorization
    {
        return new Authorization($this->createStub(AuthorizationRepository::class));
    }
}
