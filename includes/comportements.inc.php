<script type="text/javascript" src="<?php echo $url_site; ?>js/jquery-1.10.2.js"></script>
<script src="<?php echo $url_site; ?>librairies/magnific-popup/jquery.magnific-popup.js"></script>
<?php
if (isset($extra_js) && is_array($extra_js))
{

	foreach ($extra_js as $src)
	{
		echo '<script type="text/javascript" src="'.$url_site.'js/'.$src.'.js"></script>';
		
		
	}

}

preg_match(PREG_PATTERN_NOMPAGE, $_SERVER['PHP_SELF'], $matches);
$nom_page = 'index';
if (!empty( $matches[1] ))
{
	$tab_nom_page = explode("/", $matches[1]);
	$nom_page = end($tab_nom_page);
}

$pages_orga = array("ajouterEvenement", "ajouterLieu", "ajouterPersonne", 'inscription');

$pages_formulaires = array("ajouterEvenement", "ajouterLieu", "inscription", "gererEvenements", "ajouterPersonne", "ajouterDescription", "ajouterOrganisateur");
$pages_tinymce = ["ajouterDescription", "ajouterOrganisateur"];
?>
<script type="text/javascript">
<?php
if (in_array($nom_page, $pages_formulaires))
{
?>

$(document).ready(function() {

	//console.log('ready');
    // assuming the controls you want to attach the plugin to 
    // have the "datepicker" class set
	$('input.datepicker').Zebra_DatePicker({
	  direction: true,
	  format: 'd.m.Y',
	  zero_pad: true,
	  days : ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
	  months : ['Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre'],
	  show_clear_date : true,
	  lang_clear_date : "Effacer",
	  show_select_today : "Aujourd'hui"
	});
	
	
	$(".chosen-select").chosen({allow_single_deselect: true, no_results_text: "Aucun &eacute;l&eacute;ment correspondant n'a &eacute;t&eacute; trouv&eacute;"})

	
	$('.shiftcheckbox').shiftcheckbox({

		// Options accept selectors, jQuery objects, or DOM
		// elements.

		checkboxSelector : ':checkbox',
		selectAll        : $('#demo1 .all'),
		ignoreClick      : 'a'

	});
	
	//jQuery("#inscription_organisateur").click( function() {console.log("ok");$('#inscription_references').toggle();return false;} );
	
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
        $.get( "event.php?action=delete&id=" + event_id, function( data ) {
            $( "#btn_event_del_" + event_id).closest( "tr" ).fadeOut( "slow" );
        });
						
	});         
    

	jQuery("#btn_calendrier").click( function() {$('#navigation_calendrier').toggle();return false;} );

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
	
	$("#btn_listeorganisateurs").on('click', function (e)
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