</div>
<!-- fin conteneur -->

<footer id="pied-wrapper">

    <!-- Début pied -->
    <div id="pied">

        <ul class="menu_pied">

            <?php
            foreach ($glo_menu_pratique as $nom => $lien)
            {
                if (strstr($_SERVER['PHP_SELF'], (string) $lien))
                {
                    $ici = " class=\"ici\"";
                }
                ?>

            <li><a href="<?php echo $lien; ?>" title="<?php echo $nom; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
            <?php } ?>


                <li><a href="/articles/charte-editoriale.php">Charte éditoriale</a></li>
                <li><a href="/articles/liens.php">Liens</a></li>
                <li>
                    <form class="recherche" action="/evenement-search.php" method="get">
                        <input type="text" class="mots" name="mots" size="22" maxlength="50" value="" placeholder="Rechercher un événement" />
                        <input type="submit" class="submit" name="formulaire" value=""  />
                        <input type="text" name="name_as" value="" class="name_as"  />
                    </form>
                </li>
        </ul>
    </div>
    <!-- Fin Pied -->

</footer>

</div>
<!-- Fin Global -->


<?php
$pages_formulaires = ["evenement-edit", "evenement-copy", "lieu-edit", "user-register", "gererEvenements", "user-edit", "lieu-text-edit", "organisateur-edit", "lieu-salle-edit"];
$pages_tinymce = ["lieu-text-edit", "organisateur-edit"];
$pages_lieumap = ["lieu", "evenement"];
?>

<!-- used by ZebraDatepicker, MagnificPopup, checkboxes, custom in _footer.inc.php, browser.js, global.js -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="/vendor/dimsemenov/magnific-popup/dist/jquery.magnific-popup.js"></script>

<?php if (in_array($nom_page, $pages_lieumap))
{ ?>

    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_API_KEY; ?>&callback=initLieuMap"></script>
    <script src="/web/js/map.js?<?php echo time() ?>"></script>

<?php } ?>


    <?php if (in_array($nom_page, $pages_formulaires))
    { ?>

        <script src="/vendor/harvesthq/chosen/chosen.jquery.min.js"></script>
        <script src="/web/js/libs/Zebra_datepicker/zebra_datepicker.min.js"></script>
        <script src="/web/js/forms.js?<?php echo time() ?>"></script>

    <?php if (in_array($nom_page, $pages_tinymce))
    { ?>
            <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>;
            <script src="/web/js/edition.js?<?php echo time() ?>"></script>
        <?php } ?>

            <?php if ($nom_page == "gererEvenements")
            { ?>
                <script src="/web/js/libs/jquery.checkboxes-1.2.2.min.js"></script>
                        <script nonce="<?php echo CSP_NONCE ?>">
                        'use strict';
                jQuery(function ($)
                {
            $('.jquery-checkboxes').checkboxes('range', true);
        });
                        </script>
            <?php } ?>

            <?php
            if ($nom_page == "evenement-edit" && !isset($_SESSION['Sgroupe']))
            {
                ?>
                <script src="https://www.google.com/recaptcha/api.js?render=<?php echo GOOGLE_RECAPTCHA_API_KEY_CLIENT; ?>"></script>
                        <script nonce="<?php echo CSP_NONCE ?>">
                                            'use strict';
                                grecaptcha.ready(function ()
                {
                    grecaptcha.execute('<?php echo GOOGLE_RECAPTCHA_API_KEY_CLIENT ?>', {action: 'propose_event'}).then(function (token)
                    {
                        var recaptchaResponse = document.getElementById('g-recaptcha-response');
                        recaptchaResponse.value = token;
                    });
                });
                </script>
            <?php } ?>
        <?php } ?>

            <?php
            if ($nom_page == "contacteznous")
            {
                ?>
                    <script nonce="<?php echo CSP_NONCE ?>">
                        'use strict';
                                  document.getElementById("contacteznous-email-info").innerHTML = atob("<?php echo base64_encode(EMAIL_ADMIN); ?>");
                </script>
            <?php } ?>
            <script type="module" src="/web/js/main.js?<?php echo time() ?>"></script>
</body>

</html>


