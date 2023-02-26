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

$(window).resize(function ()
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

    if (!$('#menu_pratique').is(':visible'))
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
    $.get("/evenement-actions.php?action=delete&id=" + event_id, function (data)
    {
        $("#btn_event_del_" + event_id).closest("tr").fadeOut("fast");
    });

});

$(".btn_event_unpublish").on('click', function (e)
{
    e.preventDefault();
    var event_id = $(this).data('id');
    $.get("/evenement-actions.php?action=unpublish&id=" + event_id, function (data)
    {
        $("#btn_event_unpublish_" + event_id).closest(".evenement").fadeOut();
    });

});


jQuery("#btn_calendrier").click(function ()
{
    $('#navigation_calendrier').toggle();
    return false;
});
jQuery(".dropdown").click(function ()
{
    $("#" + $(this).data('target')).toggle();
    return false;
});

$("#btn_listelieux").on('click', function (e)
{
    e.preventDefault();

    if (!$('#menu_lieux').is(':visible'))
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
    gallery: {
        enabled: true,
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
$(".precisions").change(function ()
{
    if (this.checked && (this.value == 'asyouwish' || this.value == 'chargeable'))
    {
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

$('form.submit-freeze-wait').submit(function ()
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
