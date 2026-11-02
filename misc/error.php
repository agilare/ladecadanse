<?php
/**
 * Page d'erreur 404 d'Apache (htaccess/60-erreurs.conf), rendue dans l'habillage du site :
 * un visiteur arrivé par un lien périmé y retrouve le menu et la recherche. Les 403, 429 et
 * 500 vont à des pages statiques, sans PHP (misc/forbidden.html, too-many-requests.html,
 * server-error.html) ; cette page ne les traite plus.
 *
 * À ne pas confondre avec _erreur_http.inc.php, qui rend un refus décidé par l'application.
 */
require_once("../app/bootstrap.php");

$statusErrors = [
    404 => ['Document introuvable', "Ce document n'existe pas ou plus"],
];

// REDIRECT_STATUS est posé par Apache sur la redirection interne ; appelée directement,
// la page se comporte comme un 404
$status = (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);
[$label, $message] = $statusErrors[$status] ?? ['Erreur', ''];

$page_titre = "Erreur $status — $label";
$page_description = $message !== '' ? $message : $page_titre;
$page_url = null;
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <div class="rubrique" style="margin-left:20px">
        <h1 style="margin:120px 0 20px 0;font-size:2.4em;color:#5C7378;">Erreur <?= $status ?></h1>
        <h2><?= sanitizeForHtml($label) ?></h2>
        <br>
        <?php if ($message !== '') : ?>
            <p><?= sanitizeForHtml($message) ?></p>
        <?php endif; ?>
        <p>&nbsp;</p>
        <p><a href="/">Revenir à l'accueil de La d&eacute;cadanse</a></p>
        <p>&nbsp;</p>
    </div>
    <!-- .rubrique -->

</main>

<?php
include("../_footer.inc.php");
?>
