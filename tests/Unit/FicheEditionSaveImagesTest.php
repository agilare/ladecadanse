<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\FicheEdition;
use Ladecadanse\Utils\DbConnectorPdo;
use PDOStatement;

/**
 * Couvre FicheEdition::saveImages() : quels champs image sont écrits, sous quel nom,
 * et ce que l'UPDATE final porte.
 *
 * La régression que ces tests verrouillent : le passage décidait d'écrire d'après le
 * nom de fichier calculé, comparé au nom enregistré. Ce nom étant déterministe
 * ({id}_{champ}.{extension}), remplacer un logo par un autre PNG le laissait
 * inchangé — l'ancien fichier était effacé, le nouveau jamais écrit, et la fiche
 * désignait ensuite un fichier absent.
 *
 * L'écriture sur le disque et le décodage GD sont hors sujet ici : writeImageFiles()
 * et safeUnlinkImageAndThumb() sont remplacées par des journaux.
 */
final class FicheEditionSaveImagesTest extends Unit
{
    private const array IMAGES = [
        'logo'   => ['maxWidth' => 200, 'maxHeight' => 200, 'fitOn' => 'h', 'crop' => 0],
        'photo1' => ['maxWidth' => 300, 'maxHeight' => 300, 'fitOn' => 'w', 'crop' => 1],
    ];

    /** Paramètres de l'UPDATE des colonnes image, null si aucun n'a été émis. */
    private ?array $updateParams = null;

    private ?string $updateSql = null;

    /**
     * @param array<string, string> $storedValues ce que la base porte déjà
     * @param array<string, mixed> $fichiers entrées de $_FILES
     * @param list<string> $markedForDeletion
     */
    private function makeForm(array $storedValues, array $fichiers, array $markedForDeletion = []): object
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willReturnCallback(function (array $params): bool {
            $this->updateParams = $params;

            return true;
        });

        $pdo = $this->createMock(DbConnectorPdo::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($statement): PDOStatement {
            $this->updateSql = $sql;

            return $statement;
        });

        $form = new class([], $fichiers, '/tmp/uploads/', $pdo) extends FicheEdition {
            /** @var list<string> Noms passés à writeImageFiles(), dans l'ordre */
            public array $written = [];

            /** @var list<string> Noms passés à safeUnlinkImageAndThumb() */
            public array $unlinked = [];

            public bool $writeSucceeds = true;

            public function setUp(array $storedValues, array $markedForDeletion, int $recordId): void
            {
                $this->storedValues = $storedValues;
                $this->imagesMarkedForDeletion = $markedForDeletion;
                $this->recordId = $recordId;
            }

            public function saveImagesForTest(): void
            {
                $this->saveImages();
            }

            #[\Override]
            protected function writeImageFiles(array $uploadedFile, string $fileName, string $uploadsSubdir, array $thumbnail, int $maxDisplayedSize = 600): bool
            {
                // Même garde que la vraie méthode : une image retirée n'a rien à écrire
                if (empty($uploadedFile['name']) || $fileName === '')
                {
                    return true;
                }

                $this->written[] = $fileName;

                return $this->writeSucceeds;
            }

            #[\Override]
            protected function safeUnlinkImageAndThumb(string $dir, string $filename): void
            {
                $this->unlinked[] = $filename;
            }

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
                return ['nom', 'statut', 'logo', 'photo1'];
            }

            #[\Override]
            protected function imageFields(): array
            {
                return FicheEditionSaveImagesTest::imageFields();
            }

            #[\Override]
            protected function uploadsSubdir(): string
            {
                return 'lieux';
            }

            #[\Override]
            protected function insert(): bool
            {
                return true;
            }

            #[\Override]
            protected function update(): bool
            {
                return true;
            }
        };

        $form->setUp($storedValues, $markedForDeletion, 42);

        return $form;
    }

    /**
     * @return array<string, array{maxWidth: int, maxHeight: int, fitOn: string, crop: int}>
     */
    public static function imageFields(): array
    {
        return self::IMAGES;
    }

    /**
     * @return array{name: string, tmp_name: string, size: int}
     */
    private function uploadedPng(): array
    {
        $tmp = sys_get_temp_dir() . '/ladecadanse-png-' . uniqid();
        imagepng(imagecreatetruecolor(4, 4), $tmp);
        register_shutdown_function(static fn () => @unlink($tmp));

        return ['name' => 'nouveau-logo.png', 'tmp_name' => $tmp, 'size' => (int) filesize($tmp)];
    }

    /**
     * @return array{name: string, tmp_name: string, size: int}
     */
    private function noFile(): array
    {
        return ['name' => '', 'tmp_name' => '', 'size' => 0];
    }

    /** Le cas de la régression. */
    public function testReplacingALogoByAPngOfTheSameExtensionRewritesTheFiles(): void
    {
        $form = $this->makeForm(
            ['logo' => '42_logo.png', 'photo1' => '42_photo1.jpg'],
            ['logo' => $this->uploadedPng(), 'photo1' => $this->noFile()]
        );

        $form->saveImagesForTest();

        $this->assertSame(['42_logo.png'], $form->unlinked, "l'ancien logo doit être effacé");
        $this->assertSame(['42_logo.png'], $form->written, 'le nouveau logo doit être écrit à sa place');
        $this->assertSame([':id' => 42, 'logo' => '42_logo.png'], $this->paramsByField());
    }

    public function testUploadingALogoLeavesThePhotoAlone(): void
    {
        $form = $this->makeForm(
            ['logo' => '', 'photo1' => '42_photo1.jpg'],
            ['logo' => $this->uploadedPng(), 'photo1' => $this->noFile()]
        );

        $form->saveImagesForTest();

        $this->assertSame([], $form->unlinked, "aucune image n'est à remplacer");
        $this->assertSame(['42_logo.png'], $form->written);
        $this->assertArrayNotHasKey('photo1', $this->paramsByField(), "la photo n'a pas à figurer dans l'UPDATE");
        $this->assertStringNotContainsString('photo1', (string) $this->updateSql);
    }

    public function testUntouchedFieldsEmitNoUpdate(): void
    {
        $form = $this->makeForm(
            ['logo' => '42_logo.png', 'photo1' => '42_photo1.jpg'],
            ['logo' => $this->noFile(), 'photo1' => $this->noFile()]
        );

        $form->saveImagesForTest();

        $this->assertSame([], $form->written);
        $this->assertSame([], $form->unlinked);
        $this->assertNull($this->updateSql, "rien n'a bougé, il n'y a pas d'UPDATE à faire");
    }

    public function testDeletionEmptiesTheColumn(): void
    {
        $form = $this->makeForm(
            ['logo' => '42_logo.png', 'photo1' => '42_photo1.jpg'],
            ['logo' => $this->noFile(), 'photo1' => $this->noFile()],
            ['logo']
        );

        $form->saveImagesForTest();

        $this->assertSame(['42_logo.png'], $form->unlinked);
        $this->assertSame([], $form->written, "il n'y a rien à écrire quand l'image est retirée");
        $this->assertSame([':id' => 42, 'logo' => ''], $this->paramsByField());
    }

    /** Une écriture manquée vide la colonne : le fichier, lui, a déjà disparu. */
    public function testAFailedWriteEmptiesTheColumn(): void
    {
        $form = $this->makeForm(
            ['logo' => '42_logo.png', 'photo1' => '42_photo1.jpg'],
            ['logo' => $this->uploadedPng(), 'photo1' => $this->noFile()]
        );
        $form->writeSucceeds = false;

        $form->saveImagesForTest();

        $this->assertSame([':id' => 42, 'logo' => ''], $this->paramsByField());
    }

    /**
     * Les marqueurs de l'UPDATE, sans leurs deux-points, pour une comparaison lisible.
     * `:id` garde le sien : il ne désigne pas une colonne image.
     *
     * @return array<string, mixed>
     */
    private function paramsByField(): array
    {
        $params = [];

        foreach ($this->updateParams ?? [] as $marker => $value) {
            $params[$marker === ':id' ? ':id' : ltrim($marker, ':')] = $value;
        }

        return $params;
    }
}
