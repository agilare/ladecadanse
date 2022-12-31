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

                    <li><a href="<?php echo $url_site . $lien; ?>" title="<?php echo $nom; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
                <?php } ?>


                <li><a href="/pages/articles/charte-editoriale.php">Charte éditoriale</a></li>
                <li><a href="/pages/articles/liens.php">Liens</a></li>
                <li>
                    <form class="recherche" action="/pages/evenement-search.php" method="get">
                        <input type="text" class="mots" name="mots" size="22" maxlength="50" value="" placeholder="Rechercher un événement" /><input type="submit" class="submit" name="formulaire" value=""  /><input type="text" name="name_as" value="" class="name_as" id="name_as" />
                    </form>
                </li>
            </ul>
        </div>
        <!-- Fin Pied -->

    </footer> 

 </div>
<!-- Fin Global -->

<script src="/vendor/dimsemenov/magnific-popup/dist/jquery.magnific-popup.js"></script>
<script src="/web/js/zebra_datepicker/zebra_datepicker.min.js"></script>
<script src="/vendor/harvesthq/chosen/chosen.jquery.min.js"></script>

<?php
if (isset($extra_js) && is_array($extra_js))
{
	foreach ($extra_js as $src)
	{
		echo '<script type="text/javascript" src="/web/js/'.$src.'.js"></script>';	
	}
}

$nom_page = basename($_SERVER["SCRIPT_FILENAME"], '.php');

$pages_orga = ["evenement-edit", "lieu-edit", "user-edit", 'user-register'];
$pages_formulaires = ["evenement-edit", "evenement-copy", "lieu-edit", "user-register", "gererEvenements", "user-edit", "multi-description", "organisateur-edit", "lieu-salle-edit"];
$pages_tinymce = ["multi-description", "organisateur-edit"];
?>

<?php if ($nom_page == "evenement-edit" && !isset($_SESSION['Sgroupe'])) { ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo GOOGLE_RECAPTCHA_API_KEY_CLIENT; ?>"></script>
    <script>
        grecaptcha.ready(function () {
            grecaptcha.execute('<?php echo GOOGLE_RECAPTCHA_API_KEY_CLIENT ?>', { action: 'propose_event' }).then(function (token) {
                var recaptchaResponse = document.getElementById('g-recaptcha-response');
                recaptchaResponse.value = token;
            });
        });
    </script> 
<?php } ?>

<script type="text/javascript">
<?php
if (in_array($nom_page, $pages_formulaires))
{
?>

$(document).ready(function() {

	$('input.datepicker').Zebra_DatePicker({
	  direction: true,
	  format: 'd.m.Y',
	  zero_pad: true,
	  days : ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
	  months : ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
	  show_clear_date : true,
	  lang_clear_date : "Effacer",
	  show_select_today : "Aujourd’hui"
	});

	$('input.datepicker_from').Zebra_DatePicker({
      direction: true,
      pair: $('input.datepicker_to'),
	  format: 'd.m.Y',
	  zero_pad: true,
	  days : ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
	  months : ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
	  show_clear_date : true,
	  lang_clear_date : "Effacer",
	  show_select_today : "Aujourd’hui",
      readonly_element : false
	});

	$('input.datepicker_to').Zebra_DatePicker({
	  direction: 1,
	  format: 'd.m.Y',
	  zero_pad: true,
	  days : ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
	  months : ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
	  show_clear_date : true,
	  lang_clear_date : "Effacer",
	  show_select_today : "Aujourd’hui",
      readonly_element : false
	});        
	
	$(".chosen-select").chosen({
        allow_single_deselect: true, 
        no_results_text: "Aucun &eacute;l&eacute;ment correspondant n'a &eacute;t&eacute; trouv&eacute;",
        include_group_label_in_selected: true,
        search_contains : true
    })

	
	$('.shiftcheckbox').shiftcheckbox({

		// Options accept selectors, jQuery objects, or DOM
		// elements.

		checkboxSelector : ':checkbox',
		selectAll        : $('#demo1 .all'),
		ignoreClick      : 'a'

	});
    $('.file-upload-size-max').bind('change', function() {
        
        if (this.files[0].size > 2097152)
        {
            alert("La taille du fichier que vous avez sélectionné dépasse la limite autorisée (2 Mo), merci d'en choisir un plus léger" );
        }
    });
    
      });
<?php
}
?>

function SetCookie(name, value, days, path)
{
    /*Valeur par défaut de l'expiration*/ 
    var expires = '';
    /*Si on a spécifié un nombre de jour on le convertit en dae*/
    if (days != undefined && days != 0)
    {
        var date = new Date();
        /*On évite les dates négatives*/
        if (days < 0)
        {
            date.setTime(0);
        }
        else
        {
            date.setTime(date.getTime() + Math.ceil(days * 86400 * 1000));
        }
        expires = '; expires=' + date.toGMTString();
    }
    /*Si on a pas spécifié de path on pose le cookie sur tout le domain*/
    path = path || '/';
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=' + path;
}

function setupMobile()
{
	$("#navigation_calendrier").hide();
	$("#contenu").prepend($("#navigation_calendrier"));
	$("#menu_lieux").hide();
	$("#btn_listelieux").after($("#menu_lieux"));
	$("#btn_listeorganisateurs").after($("#menu_lieux"));	
	
}

function setupDesktop()
{
	$("#navigation_calendrier").show();
	$("#colonne_gauche").prepend($("#navigation_calendrier"));
}

function showhide(show, hide)
{
   $(".type-" + show).fadeIn(100);
   $(".btn-" + show).addClass("ici");
   $(".type-" + hide).fadeOut(100);
   $(".btn-" + hide).removeClass("ici");
   
}

$(document).ready(function()
{
	var vitesse_fondu = 400;
	var maxWidthMobile = 800;
	//console.log("width : " + viewportWidth + ", height :" + viewportHeight);
	
	var viewportWidthPrev = $(window).width();
	var viewportHeightPrev = $(window).height();
	
	
	var viewportWidth = $(window).width();
	var viewportHeight = $(window).height();

	
	if (viewportWidth < maxWidthMobile)
	{
		mode_viewport = 'mobile';
		setupMobile();
	}

	$(window).resize(function()
	{
		viewportWidth = $(window).width();
		viewportHeight = $(window).height();
		
	
		if (viewportWidth < maxWidthMobile && viewportWidthPrev > maxWidthMobile)
		{
			//console.log('mode mobile');
			mode_viewport = 'mobile';
			setupMobile();
		}
		else if (viewportWidth > maxWidthMobile && viewportWidthPrev < maxWidthMobile)
		{
			//console.log('mode desktop');
			mode_viewport = 'desktop';
			setupDesktop();
		}
		
	    viewportWidthPrev = $(window).width();
	    viewportHeightPrev = $(window).height();		
		

	});


	$("#btn_menu_pratique").on('click', function (e)
	{
		e.preventDefault();

			if(!$('#menu_pratique').is(':visible'))
			{
				$("#menu_pratique").fadeIn(vitesse_fondu);
				//$("#main_menu").toggle(vitesse_fondu);
			}
			else
			{
				$("#menu_pratique").fadeOut(vitesse_fondu);
				//$("#main_menu").toggle(vitesse_fondu);		
			}	
		
	}); 

	$(".btn_event_del").on('click', function (e)
	{
		e.preventDefault();
        var event_id = $(this).data('id')
        $.get( "/pages/event.php?action=delete&id=" + event_id, function( data ) {
            $( "#btn_event_del_" + event_id).closest( "tr" ).fadeOut( "fast" );
        });
						
	});         

    $(".btn_event_unpublish").on('click', function (e)
	{
		e.preventDefault();
        var event_id = $(this).data('id');
        $.get( "/pages/event.php?action=unpublish&id=" + event_id, function( data ) {
            $( "#btn_event_unpublish_" + event_id).closest( ".evenement" ).fadeOut();
        });
						
	});         
    

	jQuery("#btn_calendrier").click( function() {$('#navigation_calendrier').toggle();return false;} );
	jQuery(".dropdown").click( function() {
        $("#" + $(this).data('target')).toggle();return false;
    } );

	$("#btn_listelieux").on('click', function (e)
	{
		e.preventDefault();

		if(!$('#menu_lieux').is(':visible'))
		{
			$("#menu_lieux").fadeIn(vitesse_fondu);
			//$("#main_menu").toggle(vitesse_fondu);

		}
		else
		{
			$("#menu_lieux").fadeOut(vitesse_fondu);
			//$("#main_menu").toggle(vitesse_fondu);		
		}	
		
	});	
	
	$('.magnific-popup').magnificPopup({
		type: 'image',
		tClose: 'Fermer (Esc)', // Alt text on close button
		tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total
		image: {
			tError: '<a href="%url%">L&#039;image</a> n&#039;a pas pu &ecirc;tre charg&eacute;e.' // Error message when image could not be loaded
		}	  
	});

	$('.gallery-item').magnificPopup({
	  type: 'image',
		tClose: 'Fermer (Esc)', // Alt text on close button
		tLoading: 'Chargement...', // Text that is displayed during loading. Can contain %curr% and %total	  
	  gallery:{
		enabled:true,
		tPrev: 'Pr&eacute;c&eacute;dente (bouton gauche)', // title for left button
		tNext: 'Suivante (bouton droit)', // title for right button
		tCounter: '<span class="mfp-counter">%curr% de %total%</span>' // markup of counter		
	  }
	});

 	$(".btn_toggle").on('click', function (e)
	{	
		$(".element_toggle").toggle();
		//return false;
	});
    
    //$("#prix-precisions").hide();   
    $(".precisions").change(function() {
        if(this.checked && (this.value == 'asyouwish' || this.value == 'chargeable')) {
           $("#prix-precisions").show();
           
  
               $("#prix-precisions #prix").focus();
           
        }
        else
        {
            $("#prix-precisions").hide();
            $("#prix-precisions #prix, #prix-precisions #prelocations").val('');
            this.focus();
        }
});


    
    $('form.submit-freeze-wait').submit(function()
    {
       $("input[type='submit']", this)
         .val("Envoi...")
         .attr('disabled', 'disabled');

       return true;
     });
     
 	$("#btn_search").on('click', function (e)
	{	
		$(".recherche_mobile").toggle(400);
		//return false;
	});     
     
});    
</script>

<?php if (in_array($nom_page, $pages_tinymce)) { ?>
<script src="https://cloud.tinymce.com/5/tinymce.min.js?apiKey=7g39i0lvspz7m6s04eo2hvjji73rjk8tf0b62fkl7dn7p5bw"></script>
<script>
tinymce.init({
  selector: 'textarea.tinymce',
  height: 500,
  menubar: false,
  plugins: [
    'autolink lists link charmap',
    'searchreplace visualblocks code',
    'paste code help wordcount'
  ],
  toolbar: 'bold italic link | h4 bullist numlist blockquote | undo redo | visualblocks removeformat code',
  content_css: [
    '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
    '//www.tiny.cloud/css/codepen.min.css'
  ]
});</script>
<?php } ?>



</body>

</html>


