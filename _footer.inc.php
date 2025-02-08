</div>
<!-- fin conteneur -->

<footer id="pied-wrapper">

    <!-- Début pied -->
    <div id="pied">

        <ul class="menu_pied">

            <?php
            foreach ($glo_menu_pratique as $nom => $lien)
            {
                if (strstr($_SERVER['PHP_SELF'], $lien))
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
                        <input type="text" name="name_as" value="" class="name_as" id="name_as" />
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
<script>
'use strict';
// used everywhere : home, events, lieux, user...
$('.magnific-popup').magnificPopup({
    type: 'image',
    tClose: 'Fermer (Esc)', // Alt text on close button
    tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
    image: {
        tError: "L'image n'a pas pu être chargée" // Error message when image could not be loaded
    }
});

// used in lieu
$('.gallery-item').magnificPopup({
    type: 'image',
    tClose: 'Fermer (Esc)', // Alt text on close button
    tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
    gallery: {
        enabled: true,
        tPrev: 'Pr&eacute;c&eacute;dente (bouton gauche)', // title for left button
        tNext: 'Suivante (bouton droit)', // title for right button
        tCounter: '%curr% de %total%' // markup of counter
    }
});
</script>
<?php
if (in_array($nom_page, $pages_lieumap))
{
    ?>
    <script>
    'use strict';
                let lieuMap;
            function initLieuMap()
            {
                if ($('#lieu-map').length === 0)
                {
                    return;
                }

                var myLatLng = {lat: parseFloat($('#lieu-map').data('lat')), lng: parseFloat($('#lieu-map').data('lng'))};

                lieuMap = new google.maps.Map(document.getElementById('lieu-map'), {
                    center: myLatLng,
                    zoom: 14
                });

                var marker = new google.maps.Marker({
                    position: myLatLng,
                    map: lieuMap
                });

                var infowindow = new google.maps.InfoWindow({
                    content: $('#lieu-map-infowindow').html()
                });

                marker.addListener('click', function ()
                {
                    infowindow.open(lieuMap, marker);
                });

            }

        </script>
        <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_API_KEY; ?>&callback=initLieuMap"></script>
    <?php } ?>


        <?php
        if (in_array($nom_page, $pages_formulaires))
        {
            ?>

            <script src="/vendor/harvesthq/chosen/chosen.jquery.min.js"></script>
                <script>
                    'use strict';
                $('.chosen-select').chosen({
                allow_single_deselect: true,
                no_results_text: 'Aucun &eacute;l&eacute;ment correspondant n’a &eacute;t&eacute; trouv&eacute;',
                include_group_label_in_selected: true,
                search_contains: true
            });
            </script>
                <script src="/web/js/libs/Zebra_datepicker/zebra_datepicker.min.js"></script>
                    <script>
                        'use strict';
                    // users can add events for today, until 06h the day after, in line with the agenda
                const nbHoursAfterMidnightForDay = 6;
                let d = new Date();
                d.setHours(d.getHours() - nbHoursAfterMidnightForDay);
                    const eventEditStartDate = d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear();

                        let ZebraDatepickerBasicConfig = {
                        format: 'd.m.Y',
                    zero_pad: true,
                    days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
                    months: ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
                    show_clear_date: true,
                    lang_clear_date: 'Effacer',
                    show_select_today: 'Aujourd’hui'
                    };

                        const inputDatepickerConfig = {direction: [eventEditStartDate, false]};
                        $('input.datepicker').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerConfig});

                    const inputDatepickerFromConfig = {direction: [eventEditStartDate, false], pair: $('input.datepicker_to'), readonly_element: false};
                    $('input.datepicker_from').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerFromConfig});

                    const inputDatepickerToConfig = {direction: 1, readonly_element: false};
                        $('input.datepicker_to').Zebra_DatePicker({...ZebraDatepickerBasicConfig, ...inputDatepickerToConfig});

                </script>
                <?php
                if (in_array($nom_page, $pages_tinymce))
                {
                    ?>
                            <script
                              src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js"
                              referrerpolicy="origin"
                            ></script>;

                                    <script>
                                'use strict';
                        tinymce.init({
                selector: 'textarea.tinymce',
                height: 500,
                menubar: false,
                        plugins: ['autolink', 'lists', 'link', 'charmap', 'searchreplace', 'visualblocks', 'code', 'help', 'wordcount'],
                        toolbar: 'bold italic link | h4 bullist numlist blockquote | undo redo | visualblocks removeformat code',
                content_css: [
                '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
                '//www.tiny.cloud/css/codepen.min.css'
                ]
                });
                    </script>
                <?php } ?>

                <?php
                if ($nom_page == "gererEvenements")
                {
                    ?>
                            <script src="/web/js/libs/jquery.checkboxes-1.2.2.min.js"></script>
                                    <script>
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
                    <script>
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
                        <script>
                            'use strict';
                                          document.getElementById("email-info").innerHTML = atob("<?php echo base64_encode(EMAIL_ADMIN); ?>");
                        </script>
                    <?php } ?>
                        <script type="module" src="/web/js/main.js?<?php echo time() ?>"></script>
</body>

</html>


