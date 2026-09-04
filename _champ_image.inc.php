<?php
/**
 * Un champ image d'un formulaire de fiche : l'envoi, puis l'aperçu de l'image déjà
 * enregistrée avec sa case « Supprimer ».
 *
 * Logo et photo n'en différaient que par leurs libellés ; le bloc était recopié deux fois
 * dans le formulaire d'organisateur et deux fois de plus dans celui de lieu, les quatre
 * copies ayant divergé — l'une gardait un getimagesize() sans emploi, une autre décidait
 * d'afficher la photo d'après l'erreur du logo.
 *
 * Attend, définies par la page :
 *
 * @var Ladecadanse\FicheEdition $image_form  le formulaire, lieu ou organisateur
 * @var class-string<Ladecadanse\Lieu|Ladecadanse\Organisateur> $image_entity  entité dont
 *      dépend le répertoire d'uploads
 * @var string $image_field  nom du champ ('logo', 'photo', 'photo1')
 * @var string $image_label
 * @var string $image_title
 */

use Ladecadanse\Utils\Validateur;

// Les formats proposés au navigateur sont ceux que la validation accepte (app/config.php),
// et le poids annoncé est la limite qu'elle applique : les deux étaient recopiés en dur ici
$stored_image = $image_form->getStoredImageName($image_field);
$accepted_mime_types = implode(',', $mimes_images_acceptes);
$max_file_size = Validateur::formaterTaille(UPLOAD_MAX_FILESIZE);
?>

<p>
    <label for="<?= $image_field ?>"><?= $image_label ?></label>
    <input type="file" name="<?= $image_field ?>" id="<?= $image_field ?>" class="js-file-upload-size-max"
        title="<?= sanitizeForHtml($image_title) ?>" size="25"
        accept="<?= sanitizeForHtml($accepted_mime_types) ?>" />
    <?= $image_form->getHtmlErreur($image_field) ?>
</p>

<div class="guideForm">Formats JPEG, PNG, GIF ou WebP, max. <?= sanitizeForHtml($max_file_size) ?></div>

<?php if ($stored_image !== '') : ?>
    <div class="existing_img">
        <img src="<?= $assets->get($image_entity::getAssetPath($image_entity::getFilePath($stored_image))) ?>"
            alt="<?= sanitizeForHtml($image_label . " de " . $image_form->getStoredName()) ?>" />
        <div>
            <input type="checkbox" name="supprimer[]" id="supprimer_<?= $image_field ?>" value="<?= $image_field ?>" class="checkbox"
                <?= $image_form->isImageMarkedForDeletion($image_field) ? 'checked="checked"' : '' ?> />
            <label for="supprimer_<?= $image_field ?>" class="continu">Supprimer</label>
        </div>
    </div>
<?php endif; ?>
