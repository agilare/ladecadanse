<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\FicheEdition;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Security\AuthorizationRepository;
use Ladecadanse\Security\CurrentUserEditing;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DbConnectorPdo;
use PDOStatement;

/**
 * Couvre la note d'administration d'une fiche, telle que processSubmission() la remet à
 * l'INSERT ou à l'UPDATE — voir FicheEdition::adminNoteToWrite().
 *
 * Le risque verrouillé ici : le formulaire ne rend pas le champ sous le niveau ADMIN, donc
 * rien n'est posté. Écrire la valeur du formulaire effacerait la note au premier
 * enregistrement d'un auteur ou d'un acteur, qui ne l'a jamais vue.
 *
 * La base est bouchonnée : la relecture de l'état enregistré rend STORED, et l'écriture se
 * contente de relever la note qu'elle aurait écrite.
 */
final class FicheEditionAdminNoteTest extends Unit
{
    private const array STORED = ['nom' => 'Le Lieu', 'statut' => 'actif', 'admin_note' => 'Relancé le 12.03'];

    /**
     * @dataProvider fournirSoumissions
     *
     * @param array<string, string> $post
     */
    public function testLaNoteEcriteDependDuNiveau(int $groupe, string $action, array $post, string $attendue): void
    {
        $form = $this->makeForm($groupe, $action);

        $this->assertTrue($form->processSubmission($post, []));
        $this->assertSame($attendue, $form->noteEcrite);
    }

    /** @return iterable<string, array{int, string, array<string, string>, string}> */
    public static function fournirSoumissions(): iterable
    {
        yield 'un admin remplace la note' => [UserLevel::ADMIN, 'update', ['nom' => 'Le Lieu', 'admin_note' => 'Nouveau contact'], 'Nouveau contact'];

        // un textarea vidé poste une chaîne vide, que LieuEdition et OrganisateurEdition écrivent NULL
        yield 'un admin efface la note' => [UserLevel::ADMIN, 'update', ['nom' => 'Le Lieu', 'admin_note' => ''], ''];

        yield 'un admin annote une fiche à sa création' => [UserLevel::ADMIN, 'insert', ['nom' => 'Le Lieu', 'admin_note' => 'Doublon ?'], 'Doublon ?'];

        yield 'un auteur enregistre sans toucher la note' => [UserLevel::AUTHOR, 'update', ['nom' => 'Le Lieu'], 'Relancé le 12.03'];

        yield 'la note forgée d\'un acteur est ignorée' => [UserLevel::ACTOR, 'update', ['nom' => 'Le Lieu', 'admin_note' => 'forgée'], 'Relancé le 12.03'];

        yield 'la note forgée d\'un auteur ne crée rien' => [UserLevel::AUTHOR, 'insert', ['nom' => 'Le Lieu', 'admin_note' => 'forgée'], ''];
    }

    private function makeForm(int $groupe, string $action): object
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $statement->method('fetch')->willReturn(self::STORED);

        $pdo = $this->createStub(DbConnectorPdo::class);
        $pdo->method('prepare')->willReturn($statement);

        $form = new class(['nom' => '', 'admin_note' => ''], [], '/tmp/uploads/', $pdo) extends FicheEdition {
            /** Note remise à l'INSERT ou à l'UPDATE ; null tant que rien n'a été écrit. */
            public ?string $noteEcrite = null;

            #[\Override]
            public function validate(): bool
            {
                return true;
            }

            #[\Override]
            protected function table(): string
            {
                return 'lieu';
            }

            #[\Override]
            protected function idColumn(): string
            {
                return 'idLieu';
            }

            #[\Override]
            protected function storedColumns(): array
            {
                return ['nom', 'statut', 'admin_note'];
            }

            #[\Override]
            protected function imageFields(): array
            {
                return [];
            }

            #[\Override]
            protected function uploadsSubdir(): string
            {
                return 'lieux';
            }

            #[\Override]
            protected function insert(): bool
            {
                $this->noteEcrite = (string) $this->valeurs['admin_note'];

                return true;
            }

            #[\Override]
            protected function update(): bool
            {
                $this->noteEcrite = (string) $this->valeurs['admin_note'];

                return true;
            }
        };

        $form->setAction($action);
        $form->setRecordId(42);
        $form->setCurrentUser(CurrentUserEditing::fromSession(
            ['SidPersonne' => 7, 'Sgroupe' => $groupe],
            new Authorization($this->createStub(AuthorizationRepository::class))
        ));

        return $form;
    }
}
