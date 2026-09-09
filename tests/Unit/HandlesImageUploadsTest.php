<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\HandlesImageUploads;

/**
 * Couvre la décision qui commande l'enregistrement d'un champ image : le champ
 * a-t-il bougé ?
 *
 * FicheEdition::saveImages() la prenait sur le nom de fichier calculé, comparé au
 * nom enregistré. Or ce nom est déterministe — {id}_{champ}.{extension} — donc
 * identique quand une image est remplacée par une autre du même format : l'ancien
 * fichier était effacé, le nouveau jamais écrit, et la fiche désignait ensuite un
 * fichier absent.
 */
final class HandlesImageUploadsTest extends Unit
{
    private string $uploadsDir;

    /** @var object&\stdClass */
    private object $form;

    protected function _before(): void
    {
        $this->uploadsDir = sys_get_temp_dir() . '/ladecadanse-uploads-' . uniqid() . '/';
        mkdir($this->uploadsDir, 0777, true);

        $this->form = new class {
            use HandlesImageUploads;

            /** @var array<string, mixed> */
            public array $fichiers = [];

            /**
             * @param array<string, mixed> $uploadedFile
             */
            public function nameAfterEdit(string $field, array $uploadedFile, string $currentName, bool $markedForDeletion, int $id, string $dir): string
            {
                return $this->imageNameAfterEdit($field, $uploadedFile, $currentName, $markedForDeletion, $id, $dir);
            }

            public function isTouched(string $field, bool $markedForDeletion): bool
            {
                return $this->isImageFieldTouched($field, $markedForDeletion);
            }
        };
    }

    protected function _after(): void
    {
        array_map('unlink', glob($this->uploadsDir . '*') ?: []);
        rmdir($this->uploadsDir);
    }

    /**
     * Le fichier temporaire d'un envoi, avec le contenu qui donne son type mime :
     * imageNameAfterEdit() tire l'extension du contenu, pas du nom d'origine.
     *
     * @return array{name: string, tmp_name: string, size: int}
     */
    private function uploadedPng(string $originalName = 'mon-logo.png'): array
    {
        $tmp = $this->uploadsDir . 'php_upload_' . uniqid();
        $image = imagecreatetruecolor(4, 4);
        imagepng($image, $tmp);

        return ['name' => $originalName, 'tmp_name' => $tmp, 'size' => (int) filesize($tmp)];
    }

    private function storedImage(string $name): void
    {
        file_put_contents($this->uploadsDir . $name, 'image');
        file_put_contents($this->uploadsDir . 's_' . $name, 'miniature');
    }

    public function testFieldIsTouchedWhenAFileIsUploaded(): void
    {
        $this->form->fichiers = ['logo' => $this->uploadedPng()];

        $this->assertTrue($this->form->isTouched('logo', false));
    }

    public function testFieldIsTouchedWhenDeletionIsAsked(): void
    {
        $this->form->fichiers = ['logo' => ['name' => '', 'tmp_name' => '', 'size' => 0]];

        $this->assertTrue($this->form->isTouched('logo', true));
    }

    public function testFieldIsUntouchedWithoutUploadNorDeletion(): void
    {
        $this->form->fichiers = ['logo' => ['name' => '', 'tmp_name' => '', 'size' => 0]];

        $this->assertFalse($this->form->isTouched('logo', false));
    }

    /**
     * Le champ « photo » d'un formulaire où seul le logo est envoyé : rien à écrire.
     */
    public function testOtherFieldStaysUntouchedWhenOnlyTheLogoIsUploaded(): void
    {
        $this->form->fichiers = [
            'logo'   => $this->uploadedPng(),
            'photo1' => ['name' => '', 'tmp_name' => '', 'size' => 0],
        ];

        $this->assertTrue($this->form->isTouched('logo', false));
        $this->assertFalse($this->form->isTouched('photo1', false));
    }

    /**
     * Le cas de la régression : remplacer un PNG par un PNG rend le même nom, alors
     * que l'ancien fichier vient d'être effacé. Le nom ne peut donc pas dire si le
     * champ a bougé — c'est isImageFieldTouched() qui le sait.
     */
    public function testReplacingAnImageBySameFormatKeepsTheNameButErasesTheFile(): void
    {
        $this->storedImage('42_logo.png');
        $this->form->fichiers = ['logo' => $this->uploadedPng()];

        $name = $this->form->nameAfterEdit('logo', $this->form->fichiers['logo'], '42_logo.png', false, 42, $this->uploadsDir);

        $this->assertSame('42_logo.png', $name);
        $this->assertFileDoesNotExist($this->uploadsDir . '42_logo.png');
        $this->assertFileDoesNotExist($this->uploadsDir . 's_42_logo.png');
        $this->assertTrue($this->form->isTouched('logo', false));
    }

    public function testReplacingAJpegByAPngChangesTheExtension(): void
    {
        $this->storedImage('42_logo.jpg');
        $this->form->fichiers = ['logo' => $this->uploadedPng()];

        $name = $this->form->nameAfterEdit('logo', $this->form->fichiers['logo'], '42_logo.jpg', false, 42, $this->uploadsDir);

        $this->assertSame('42_logo.png', $name);
        $this->assertFileDoesNotExist($this->uploadsDir . '42_logo.jpg');
    }

    public function testDeletionEmptiesTheNameAndTheDisk(): void
    {
        $this->storedImage('42_logo.png');
        $this->form->fichiers = ['logo' => ['name' => '', 'tmp_name' => '', 'size' => 0]];

        $name = $this->form->nameAfterEdit('logo', $this->form->fichiers['logo'], '42_logo.png', true, 42, $this->uploadsDir);

        $this->assertSame('', $name);
        $this->assertFileDoesNotExist($this->uploadsDir . '42_logo.png');
        $this->assertFileDoesNotExist($this->uploadsDir . 's_42_logo.png');
    }
}
