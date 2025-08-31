        </div> <!-- fin conteneur -->

        <footer id="pied-wrapper">
            <nav id="pied">

                <ul class="menu_pied">
                    <?php
                    foreach ($glo_menu_pratique as $nom => $lien)
                    {
                        $highlightLink = '';
                        if (strstr((string) $_SERVER['PHP_SELF'], (string) $lien))
                        {
                            $ici = " class=\"ici\"";
                        }

                        if ($nom == "Faire un don") {
                            $highlightLink = ' style="background: #ffe771;border-radius: 0 0 3px 4px;padding: 5px 4px;" ';
                        }
                        ?>

                    <li <?= $highlightLink; ?>><a href="<?= $lien; ?>" title="<?= $nom; ?>" <?= $ici; ?>><?= $nom; ?></a></li>
                    <?php } ?>
                    <li><a href="/articles/charte-editoriale.php">Charte éditoriale</a></li>
                    <li><a href="/articles/liens.php">Liens</a></li>
                </ul>
            </nav> <!-- Fin Pied -->
        </footer>

    </div> <!-- Fin Global -->


    <?php
    $pages_formulaires = ["evenement-edit", "event/copy", "lieu-edit", "lieu-salle-edit", "user-register", "gererEvenements", "user-edit", "lieu/lieux", "lieu-text-edit", "organisateur-edit"];
    $pages_tinymce = ["lieu-text-edit", "organisateur-edit"];
    $pages_lieumap = ["lieu/lieu", "event/evenement"];
    ?>

    <!-- used by ZebraDatepicker, MagnificPopup, checkboxes, custom in _footer.inc.php, browser.js, global.js -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="/vendor/dimsemenov/magnific-popup/dist/jquery.magnific-popup.js"></script>
    <script src="/vendor/select2/select2/dist/js/select2.min.js"></script>
    <script src="/vendor/select2/select2/dist/js/i18n/fr.js"></script>
    <script src="https://unpkg.com/read-smore@2.0.4/dist/index.umd.js"></script>

    <?php if (in_array($nom_page, $pages_lieumap)) { ?>
        <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_API_KEY; ?>&callback=initLieuMap"></script>
        <script src="<?= $assets->get("js/map.js"); ?>"></script>
    <?php } ?>

    <?php if (in_array($nom_page, $pages_formulaires)) : ?>

        <script src="/web/js/libs/Zebra_datepicker/zebra_datepicker.min.js"></script>
        <script src="<?= $assets->get("js/forms.js"); ?>"></script>

        <?php if (in_array($nom_page, $pages_tinymce)) : ?>
            <script src="https://cdn.tiny.cloud/1/<?= TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>;
            <script src="<?= $assets->get("js/edition.js"); ?>"></script>
        <?php endif; ?>

        <?php if ($nom_page == "gererEvenements") : ?>
            <script src="/web/js/libs/jquery.checkboxes-1.2.2.min.js"></script>
            <script nonce="<?= CSP_NONCE ?>">
                'use strict';
                jQuery(function ($)
                {
                    $('.jquery-checkboxes').checkboxes('range', true);
                });
            </script>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($nom_page == "contacteznous"): ?>
        <script nonce="<?= CSP_NONCE ?>">
            'use strict';
            document.getElementById("contacteznous-email-info").innerHTML = atob("<?= base64_encode(EMAIL_ADMIN); ?>");
        </script>
    <?php endif; ?>
    <script type="module" src="<?= $assets->get("js/main.js"); ?>"></script>

</body>

</html>


