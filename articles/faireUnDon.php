<?php
require_once("../app/bootstrap.php");

$page_titre = "Faire un don";
$page_description = "";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Faire un don</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique" style="background:#f4f4f4;border-radius:3px;margin: 1em auto 0;padding:1em 1%;width: 92%;">

<!--        <p>Vous pouvez contribuer (une seule fois ou de manière récurrente, selon vos possibilités) via :</p>-->

        <p style="margin-bottom:0"><b>We make it</b> - Twint, virement, carte de crédit, Postfinance<br>Compte à créer (rapide et permet de soutenir d'autres projets supers) :</p>
        <script src="https://wemakeit.com/static-assets/widgets/donation_box.js" async="async"></script>
        <wemakeit-donation-box color="blue" locale="fr" slug="soutenir-la-decadanse" type="card" nonce="<?php echo CSP_NONCE ?>" style="margin: 1em 1em 1em 1em"></wemakeit-donation-box>
<!--        <p>Carte de crédit(, Paypal)</p>-->
<!--        <script src="https://donorbox.org/widget.js" paypalExpress="true"></script> <iframe src="https://donorbox.org/embed/la-decadanse?default_interval=o" name="donorbox" allowpaymentrequest="allowpaymentrequest" seamless="seamless" frameborder="0" scrolling="no" height="900px" width="100%" style="margin-left:3em;max-width: 500px; min-width: 250px; max-height:none!important" allow="payment"></iframe>-->

        <p>Autres moyens possibles&nbsp;: </p>

        <ul id="payment-modes">
            <li>
                <b style="vertical-align: top"><!--Carte de crédit, compte--> Paypal </b>
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
                </div>
            </li>
            <li style="margin-top:-0.6em"><b>Liberapay</b> (compte requis, dons récurrents)<script src="https://liberapay.com/michelg/widgets/button.js"></script><noscript><a href="https://liberapay.com/michelg/donate"><img alt="Donate using Liberapay" src="https://liberapay.com/assets/widgets/donate.svg"></a></noscript></li>
            <!--            <li><b>Twint, virement</b> (<a href="/misc/contacteznous.php">contactez-moi</a>)</li>-->
        </ul>
        <br>
        <p>La décadanse est un site entièrement gratuit sur un code <a href="https://github.com/agilare/ladecadanse/" rel="external" target="_blank">open source</a>, que je développe et gère bénévolement depuis une vingtaine d'années, avec parfois l'aide d'autres personnes.</p>
        <p>À côté des frais qui sont assez modestes (hébergement, flyers...), le site me demande un certain investissement en temps :</p>
        <ul style="margin-left:0.4em;list-style-type: none">
            <li>✅ <b>maintenance</b> : assurer sa disponibilité, sa sécurité ; garder à jour, chasser les bugs</li>
            <li>✅ <b>amélioration continue</b> : ajout de nouvelles fonctionnalités et optimisations</li>
            <li>✅ <b>gestion régulière</b> : ajout des événements, lieux, etc. et leur mise à jour ; aide aux utilisateurs, modération</li>
        </ul>
        <br>
        <p>Un <b>don</b>, quel que soit son montant, est une manière de <b>montrer votre soutien</b> et m’encourage à poursuivre ce projet !</p>
        <p>Merci pour votre aide ! ❤️</p>
        <p>Michel</p>
        <div class="spacer"></div>
    </article>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
?>
