<?php
/**
 * Un champ image du formulaire d'organisateur : l'envoi, puis l'aperçu de
 * l'image déjà enregistrée avec sa case « Supprimer ».
 *
 * Logo et photo n'en différaient que par leurs libellés ; le bloc était recopié
 * deux fois, l'une des copies ayant gardé un getimagesize() sans emploi.
 *
 * Attend, définies par la page : $organisateur_form, $image_field ('logo' ou
 * 'photo'), $image_label, $image_title.
 *
 * @var Ladecadanse\OrganisateurEdition $organisateur_form
 * @var string $image_field
 * @var string $image_label
 * @var string $image_title
 */

use Ladecadanse\Organisateur;
use Ladecadanse\Utils\Validateur;

// Les formats proposés au navigateur sont ceux que la validation accepte (app/config.php),
// et le poids annoncé est la limite qu'elle applique : les deux étaient recopiés en dur ici
$stored_image = $organisateur_form->getStoredImageName($image_field);
$accepted_mime_types = implode(',', $mimes_images_acceptes);
$max_file_size = Validateur::formaterTaille(UPLOAD_MAX_FILESIZE);
?>

<p>
    <label for="<?= $image_field ?>"><?= $image_label ?></label>
    <input type="file" name="<?= $image_field ?>" id="<?= $image_field ?>" class="js-file-upload-size-max"
        title="<?= sanitizeForHtml($image_title) ?>" size="25"
        accept="<?= sanitizeForHtml($accepted_mime_types) ?>" />
    <?= $organisateur_form->getHtmlErreur($image_field) ?>
</p>

<div class="guideForm">Formats JPEG, PNG, GIF ou WebP, max. <?= sanitizeForHtml($max_file_size) ?></div>

<?php if ($stored_image !== '') : ?>
    <div class="existing_img">
        <img src="<?= $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($stored_image))) ?>"
            alt="<?= sanitizeForHtml($image_label . " de " . $organisateur_form->getStoredName()) ?>" />
        <div>
            <input type="checkbox" name="supprimer[]" id="supprimer_<?= $image_field ?>" value="<?= $image_field ?>" class="checkbox"
                <?= $organisateur_form->isMarkedForDeletion($image_field) ? 'checked="checked"' : '' ?> />
            <label for="supprimer_<?= $image_field ?>" class="continu">Supprimer</label>
        </div>
    </div>
<?php endif; ?>
