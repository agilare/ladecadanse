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

        <div style="float:right">
            <div id="donate-button-container">
                <div id="donate-button"></div>
                <script src="https://www.paypalobjects.com/donate/sdk/donate-sdk.js" charset="UTF-8"></script>
                <script nonce="<?php echo CSP_NONCE ?>">
                PayPal.Donation.Button({
                env:'production',
                hosted_button_id:'4ZA8G8XRLUTC2',
                image: {
                src:'https://www.paypalobjects.com/fr_FR/CH/i/btn/btn_donate_LG.gif',
                alt:'Bouton Faites un don avec PayPal',
                title:'PayPal - The safer, easier way to pay online!',
                }
                }).render('#donate-button');
                </script>
            </div>

            <!--            <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top" >
                            <input type="hidden" name="image_url" value="https://www.darksite.ch/ladecadanse/images/interface/logo_titre.jpg">

                <input type="hidden" name="cmd" value="_s-xclick">
                <input type="hidden" name="hosted_button_id" value="4ZA8G8XRLUTC2">
                <input type="image" src="https://www.paypalobjects.com/fr_FR/CH/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal, le réflexe sécurité pour payer en ligne">
                <img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
                        </form>-->
        </div>
        En faisant un don à La décadanse, vous nous aidez à son entretien et son évolution.
        Merci d'avance !

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
