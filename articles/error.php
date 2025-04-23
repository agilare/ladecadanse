<?php
require_once("../app/bootstrap.php");

$page_titre = "404 - not found";
$page_description = "Erreur 404 - not found";
include("../_header.inc.php");

$statusErrors = [
    404 => ['Document introuvable', "Ce document n'existe pas ou plus"],
    429 => ['Trop de requêtes', "Le serveur reçoit trop de connexions, comme il n'arrive plus à suivre, il doit en refuser. Veuillez réessayer un peu plus tard"],
    500 => ["Erreur dans l'application ou le serveur", " "]
        ]
?>

<div id="contenu" class="colonne">

    <div class="rubrique" style="margin-left:20px">
        <h2 style="margin:120px 0 20px 0;font-size:2.4em;color:#5C7378;font-weight:bold;">Erreur <?php echo $_SERVER["REDIRECT_STATUS"] ?></h2>
        <h3><?php
            if (isset($statusErrors[$_SERVER["REDIRECT_STATUS"]][0]))
            {
                ?>
                <?php echo $statusErrors[$_SERVER["REDIRECT_STATUS"]][0] ?>
            <?php } ?>
            </h3>
            <?php if (isset($statusErrors[$_SERVER["REDIRECT_STATUS"]][1]))
        { ?>
            <p><?php echo $statusErrors[$_SERVER["REDIRECT_STATUS"]][1] ?></p>
        <?php } ?>
        <p>&nbsp;</p>
        <p><a href="/">Revenir à l'accueil de La d&eacute;cadanse</a></p>
        <p>&nbsp;</p>

    </div>
    <!-- Fin  -->

</div>
<!-- fin Contenu -->

<?php
include("../_footer.inc.php");
?>
