<?php
require_once("../app/bootstrap.php");

$page_titre = "Faire un don";
$page_description = "";
include("../_header.inc.php");
?>

<div id="contenu" class="colonne">

    <div id="entete_contenu">
        <h2>Faire un don</h2>
        <div class="spacer"></div>
    </div>

    <div class="rubrique" style="background:#f4f4f4;border-radius:3px;margin: 1em auto 0;padding:1em 1%;width: 92%;">

        <p>La décadanse est un site entièrement gratuit sur un code <a href="https://github.com/agilare/ladecadanse/" target="_blank">open source</a>, que je développe et gère bénévolement depuis une vingtaine d'années, avec parfois l'aide d'autres personnes.</p>

        <p>En dehors des frais qui sont assez modestes (hébergement, flyers...), le site me demande un certain investissement en temps :</p>
        <ul style="margin-left:0.4em;list-style-type: none">
            <li>✅ <b>maintenance</b> : assurer sa disponibilité, sa sécurité ; garder à jour, chasser les bugs</li>
            <li>✅ <b>amélioration continue</b> : ajout de nouvelles fonctionnalités et optimisations</li>
            <li>✅ <b>gestion régulière</b> : ajout des événements, lieux, etc. et leur mise à jour ; aide aux utilisateurs, modération</li>
        </ul>
        <br>
        <p>Un <b>don</b>, quel que soit son montant, est une manière de <b>montrer votre soutien</b> et m’encourage à poursuivre ce projet !</p>

        <p>Vous pouvez contribuer (une seule fois ou de manière récurrente, selon vos possibilités) via :</p>
        <ul style="margin-left:0.4em;list-style-type: none">
            <li><b style="vertical-align: top">Carte de crédit, compte Paypal </b>
                <div style="display:inline-block">
                    <div id="donate-button-container">
                        <div id="donate-button"></div>
                        <script src="https://www.paypalobjects.com/donate/sdk/donate-sdk.js" charset="UTF-8"></script>
                        <script nonce="<?php echo CSP_NONCE ?>">
                            PayPal.Donation.Button({
                                env:'production',
                                hosted_button_id:'<?php echo PAYPAL_HOSTED_BUTTON_ID; ?>',
                                image: {
                                    src:'https://www.paypalobjects.com/fr_FR/CH/i/btn/btn_donate_SM.gif',
                                    alt:'Bouton Faites un don avec PayPal',
                                    title:'PayPal - The safer, easier way to pay online!',
                                }
                            }).render('#donate-button');
                        </script>
                    </div>


                </div></li>
            <li><b>Twint, virement</b> (<a href="/contacteznous.php">contactez-moi</a>)</li>
        </ul>
<br>
        <p>Merci pour votre soutien ! ❤️</p>
        <p>Michel</p>

        <div class="spacer"></div>

    </div>
    <!-- Fin  -->





</div>
<!-- fin Contenu -->



<div id="colonne_gauche" class="colonne">
    <?php include("../_navigation_calendrier.inc.php"); ?>
</div>
<!-- Fin Colonnegauche -->

<div id="colonne_droite" class="colonne">
</div>


<?php
include("../_footer.inc.php");
?>
