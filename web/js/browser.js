export function SetCookie(name, value, days, path)
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



export function responsiveSetup()
{
    const MAX_WIDTH_FOR_MOBILE_INTERFACE = 800;
    let viewportWidthPrev = $(window).width();
    let viewportHeightPrev = $(window).height();
    let viewportWidth = $(window).width();
    let viewportHeight = $(window).height();

    var mode_viewport;
    if (viewportWidth < MAX_WIDTH_FOR_MOBILE_INTERFACE)
    {
        mode_viewport = 'mobile';
        setupMobile();
    }

    $(window).resize(function setupInterfaceForWidth() 
    {
        viewportWidth = $(window).width();
        viewportHeight = $(window).height();

        if (viewportWidth < MAX_WIDTH_FOR_MOBILE_INTERFACE && viewportWidthPrev > MAX_WIDTH_FOR_MOBILE_INTERFACE)
        {
            //console.log('mode mobile');
            mode_viewport = 'mobile';
            setupMobile();
        }
        else if (viewportWidth > MAX_WIDTH_FOR_MOBILE_INTERFACE && viewportWidthPrev < MAX_WIDTH_FOR_MOBILE_INTERFACE)
        {
            //console.log('mode desktop');
            mode_viewport = 'desktop';
            setupDesktop();
        }

        viewportWidthPrev = $(window).width();
        viewportHeightPrev = $(window).height();
    });

}

function setupMobile()
{
    $('#navigation_calendrier').hide();
    $('#contenu').prepend($('#navigation_calendrier'));
    $('#menu_lieux').hide();
    $('#btn_listelieux').after($('#menu_lieux'));
    $('#btn_listeorganisateurs').after($('#menu_lieux'));
}

function setupDesktop()
{
    $('#navigation_calendrier').show();
    $('#colonne_gauche').prepend($('#navigation_calendrier'));
}