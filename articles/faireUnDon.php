<?php
require_once("../app/bootstrap.php");

$page_titre = "Faire un don";
$page_description = "";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu" style="margin-bottom:0">
        <h1>Faire un don</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique" style="border-radius:3px;margin: 0em auto 0;padding:0em 1%;width: 92%;">

<!--        <p>Vous pouvez contribuer (une seule fois ou de manière récurrente, selon vos possibilités) via :</p>-->

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
        <hr>
        <br>
        <p>La décadanse est un site entièrement gratuit sur un code <a href="https://github.com/agilare/ladecadanse/" rel="external" target="_blank">open source</a>, que je développe et gère bénévolement depuis une vingtaine d'années, avec parfois l'aide d'autres personnes.</p>
        <p>À côté des frais courants (hébergement, contribution aux outils utilisés...), le site me demande un investissement significatif&nbsp;:</p>
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

        <h2 style="margin-bottom:0.6em;">Soutiens</h2>

        <p>Je remercie ces organisateurs pour leur soutien à La décadanse&nbsp;:</p>

        <br>

        <ul class="thumbnails">
            <li><a href="/lieu/lieu.php?idL=222"><img src="/web/content/logocave12.jpg" alt="Cave12" width="150"></a></li>
            <li><a href="/lieu/lieu.php?idL=578"><img src="/web/uploads/lieux/578_logo.png" alt="La traboule" width="139" height="50"></a></li>
            <li><a href="/lieu/lieu.php?idL=255"><img src="/web/uploads/lieux/255_logo.png" alt="Am Stram Gram" width="160"></a></li>
            <div class="spacer"></div>

            <li><a href="/lieu/lieu.php?idL=41"><img src="/web/uploads/lieux/41_logo.jpg" alt="La parfumerie" width="172" height="50"></a></li>
            <li><a href="/lieu/lieu.php?idL=640"><img src="/web/content/Gallery-Brulhart_Logo.png" alt="Gallery Brulhart" width="150" height="150"></a></li>
            <li><a href="https://www.perejakob.ch/"><img src="/web/content/pere-jakob.png" alt="Père Jakob" width="120"></a></li>
            <div class="spacer"></div>

            <li><a href="/organisateur/organisateur.php?idO=99"><img src="/web/uploads/organisateurs/s_99_logo.png" alt="Archipel" width="160"></a></li>
            <li><a href="/organisateur/organisateur.php?idO=154"><img src="/web/uploads/organisateurs/s_154_logo.png" alt="Orchestre de chambre de Genève" width="160"></a></li>
            <li><a href="https://assemblages.ch/"><img src="/web/content/assemblages.png" alt="Assemblage's festival"  width="120"></a></li>
            <div class="spacer"></div>

            <li><a href="/organisateur/organisateur.php?idO=269"><img src="/web/uploads/organisateurs/s_269_logo.jpg?1731690307" alt="Les Créateliers" width="150"></a></li>
            <li><a href="/lieu/lieu.php?idL=642"><img src="/web/uploads/lieux/642_logo.jpg" alt="Café de la pointe" width="150"></a></li>
            <li><a href="/lieu/lieu.php?idL=130"><img src="/web/uploads/lieux/s_130_logo.jpg?1765049481" alt="Centre photo Genève" width="170"></a></li>
        </ul>

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
