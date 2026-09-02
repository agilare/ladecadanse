<?php
/**
 * Un champ image du formulaire d'organisateur : l'envoi, puis l'aperçu de
 * l'image déjà enregistrée avec sa case « Supprimer ».
 *
 * Logo et photo n'en différaient que par leurs libellés ; le bloc était recopié
 * deux fois, l'une des copies ayant gardé un getimagesize() sans emploi.
 *
 * Attend, définies par la page : $form (OrganisateurEdition), $champImage
 * ('logo' ou 'photo'), $libelleImage, $titreImage.
 *
 * @var Ladecadanse\OrganisateurEdition $form
 * @var string $champImage
 * @var string $libelleImage
 * @var string $titreImage
 */

use Ladecadanse\Organisateur;

$imageEnBase = $form->getImageEnBase($champImage);
?>

<p>
    <label for="<?= $champImage ?>"><?= $libelleImage ?></label>
    <input type="file" name="<?= $champImage ?>" id="<?= $champImage ?>" class="js-file-upload-size-max"
        title="<?= sanitizeForHtml($titreImage) ?>" size="25"
        accept="image/jpeg,image/pjpeg,image/png,image/x-png,image/gif,image/webp" />
    <?= $form->getHtmlErreur($champImage) ?>
</p>

<div class="guideForm">Formats JPEG, PNG, GIF ou WebP, max. 5 Mo</div>

<?php if ($imageEnBase !== '') : ?>
    <div class="supImg">
        <img src="<?= $assets->get(Organisateur::getAssetPath(Organisateur::getFilePath($imageEnBase))) ?>"
            alt="<?= sanitizeForHtml($libelleImage . " de " . $form->getNomEnBase()) ?>" />
        <div>
            <input type="checkbox" name="supprimer[]" id="supprimer_<?= $champImage ?>" value="<?= $champImage ?>" class="checkbox"
                <?= in_array($champImage, $form->getSupprimer(), true) ? 'checked="checked"' : '' ?> />
            <label for="supprimer_<?= $champImage ?>" class="continu">Supprimer</label>
        </div>
    </div>
<?php endif; ?>
