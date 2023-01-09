<?php
require_once("../app/bootstrap.php");

use Ladecadanse\Security\Sentry;

$videur = new Sentry();

$nom_page = "404";
$page_titre = "404 - page non trouvée";
$page_description = "Erreur 404 - page non trouvée";
include($rep_absolu . "_header.inc.php");
?>

<div id="contenu" class="colonne">

    <a href="https://ladecadanse.darksite.ch/" title="Retour à la page d'accueil"><img src="https://ladecadanse.darksite.ch/web/interface/logo_titre.jpg" alt="La décadanse" height="35" width="180"></a>

    <div class="rubrique" style="margin-left:20px">
        <h2 style="margin:120px 0 20px 0;font-size:2.4em;font-weight:bold;">404 - page non trouv&eacute;e</h2>
        <p>&nbsp;</p>
        <p><a href="/">Page d'accueil de La d&eacute;cadanse</a></p>
        <p>&nbsp;</p>		

    </div>
    <!-- Fin  -->
    
</div>
<!-- fin Contenu -->

<?php
include($rep_absolu . "_footer.inc.php");
?>
