<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\AuthorizationRepository;
use Ladecadanse\Security\CurrentUserEditing;

/**
 * Couvre les deux droits que les formulaires de fiche consultent, et que les pages
 * calculaient chacune de leur côté.
 *
 * Le dépôt est bouchonné : isPersonneEditor() ne lit que la session, aucune requête n'est
 * faite ici.
 */
final class CurrentUserEditingTest extends Unit
{
    private function fromGroupe(int $groupe, int $idPersonne = 42): CurrentUserEditing
    {
        return CurrentUserEditing::fromSession(
            ['SidPersonne' => $idPersonne, 'Sgroupe' => $groupe],
            new Authorization($this->createStub(AuthorizationRepository::class))
        );
    }

    /**
     * Les seuils sont distincts : publier relève de la modération (ADMIN), toucher au nom
     * ou aux catégories d'un lieu relève de l'édition (AUTHOR).
     *
     * @dataProvider fournirNiveaux
     */
    public function testChaqueNiveauRecoitSesDeuxDroits(int $groupe, bool $statut, bool $champsReserves): void
    {
        $user = $this->fromGroupe($groupe);

        $this->assertSame($statut, $user->canChangeStatus, "statut, groupe $groupe");
        $this->assertSame($champsReserves, $user->canEditEditorFields, "champs réservés, groupe $groupe");
    }

    /** @return iterable<string, array{int, bool, bool}> */
    public static function fournirNiveaux(): iterable
    {
        yield 'superadmin' => [1, true, true];
        yield 'admin'      => [4, true, true];
        yield 'author'     => [6, false, true];
        yield 'actor'      => [8, false, false];
        yield 'member'     => [12, false, false];
    }

    public function testIdPersonneEstRepris(): void
    {
        $this->assertSame(42, $this->fromGroupe(4)->idPersonne);
    }

    /**
     * Une session sans niveau ni identifiant — visiteur, tâche hors requête HTTP — ne
     * doit rien ouvrir : le refus est le défaut.
     */
    public function testUneSessionVideNAccordeRien(): void
    {
        $user = CurrentUserEditing::fromSession(
            [],
            new Authorization($this->createStub(AuthorizationRepository::class))
        );

        $this->assertSame(0, $user->idPersonne);
        $this->assertFalse($user->canChangeStatus);
        $this->assertFalse($user->canEditEditorFields);
    }

    /** Ce que porte un formulaire tant que la page ne lui a rien dit. */
    public function testSansDroitsNAccordeRien(): void
    {
        $user = CurrentUserEditing::withoutRights();

        $this->assertSame(0, $user->idPersonne);
        $this->assertFalse($user->canChangeStatus);
        $this->assertFalse($user->canEditEditorFields);
    }
}
