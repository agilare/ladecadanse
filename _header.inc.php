<?php

use Ladecadanse\HtmlShrink;

/* DATE COURANTE : _navigation_calendrier, agenda, evenement-edit, index */
$get['courant'] = "";
if (!empty($_GET['courant']))
{
	if (preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", trim($_GET['courant'])))
	{
		$get['courant'] = $_GET['courant'];
	}
	else if (preg_match("/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}$/", trim($_GET['courant'])))
	{
		$get['courant'] = date_app2iso($_GET['courant']);

	}
	else
	{
		$get['courant'] = $glo_auj_6h;
	}
}
else if (isset($_GET['auj']))
{
	$get['courant'] = $_GET['auj'];
}
else
{
	$get['courant'] = $glo_auj_6h;
}

/* SEMAINE : _navigation_calendrier, agenda */
if (!empty($_GET['sem']))
{
	if (is_numeric($_GET['sem']))
	{
		$get['sem'] = $_GET['sem'];
	}
	else
	{
		//trigger_error("sem non valable", E_USER_WARNING);
		exit;
	}
}
else
{
	$get['sem'] = 0;
}

/* TRI : index, _navigation_calendrier, agenda */
$tab_tri_agenda = array("dateAjout", "horaire_debut");
if (!empty($_GET['tri_agenda']))
{
	if (in_array($_GET['tri_agenda'], $tab_tri_agenda))
	{
		$get['tri_agenda'] = $_GET['tri_agenda'];
	}
	else
	{

		//trigger_error("GET tri_agenda non valable : ".$_GET['tri_agenda'], E_USER_WARNING);
		exit;
	}
}
else
{
	$get['tri_agenda'] = "dateAjout";
}
?>

<!doctype html>
<html lang="fr">

<head>

    <meta charset="utf-8" />

    <?php if (HtmlShrink::getHeadMetaRobots($nom_page)) { ?>
        <meta name="robots" content="noindex, nofollow" />
    <?php } ?>

	<title>
        <?php
        if (ENV !== 'prod')
        {
            echo '['.ENV.'] ';
        }

        if ($nom_page != "index")
        {
            echo $page_titre." — La décadanse";
        }
        else
        {
            echo "La décadanse — ".$page_titre;
        }
        ?>
	</title>

	<?php if (!empty($page_description)) { ?>
        <meta name="description" content="<?php echo $page_description ?>" />
	<?php }	?>

        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
        <link rel="stylesheet" type="text/css" href="/web/css/normalize.css" />
        <?php
        if (isset($_GET['style']) && $_GET['style'] == "imprimer") {
            ?>
            <link rel="stylesheet" type="text/css" href="/web/css/imprimer.css" title="Normal" />
            <?php }
            else { ?>

            <link rel="stylesheet" type="text/css" href="/web/css/global.css?<?php echo time() ?>" title="Normal" />
                <link rel="stylesheet" type="text/css" href="/web/css/calendrier.css" media="screen" />
                <link rel="stylesheet" type="text/css" href="/web/css/<?php echo $nom_page; ?>.css" media="screen"  />
                <link rel="stylesheet" type="text/css" href="/web/css/diggstyle.css" media="screen" />
                <link rel="stylesheet" type="text/css" href="/vendor/harvesthq/chosen/chosen.css" media="screen" />

            <?php
        }

        if (isset($extra_css) && is_array($extra_css)) {
    foreach ($extra_css as $import)
    {
        ?>
        <link rel="stylesheet" type="text/css" href="/web/css/<?php echo $import ?>.css" media="screen" title="Normal" />
                <?php
            }
        }
        ?>

                <link rel="stylesheet" type="text/css" media="screen and (min-width:800px)"  href="/web/css/desktop.css">
                <link rel="stylesheet" type="text/css" media="screen and (max-width:800px)"  href="/web/css/mobile.css">
                <link rel="stylesheet" type="text/css" media="print" href="/web/css/imprimer.css" title="Imprimer" />
                <link rel="stylesheet" type="text/css" href="/vendor/fortawesome/font-awesome/css/font-awesome.min.css">
                <link rel="stylesheet" type="text/css" href="/vendor/dimsemenov/magnific-popup/dist/magnific-popup.css">
                <link rel="stylesheet" type="text/css" href="/web/js/zebra_datepicker/css/default/zebra_datepicker.min.css">

    <?php HtmlShrink::showLinkRss($nom_page); ?>

	<link rel="shortcut icon" href="/web/interface/favicon.png" />
    <link rel="apple-touch-icon" href="/web/interface/apple-icon.png" />
    <link rel="apple-touch-icon" sizes="57x57" href="/web/interface/apple-icon-57x57.png" />
    <link rel="apple-touch-icon" sizes="76x76" href="/web/interface/apple-icon-76x76.png" />
    <link rel="apple-touch-icon" sizes="152x152" href="/web/interface/apple-icon-152x152.png" />

    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script async defer
      src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_API_KEY; ?>&callback=initMap">
    </script>

    <?php
    if (MATOMO_ENABLED) {
    ?>
    <!-- Matomo -->
    <script type="text/javascript">
      var _paq = _paq || [];
      // tracker methods like "setCustomDimension" should be called before "trackPageView"
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function() {
        var u="//ladecadanse.darksite.ch/piwik/";
        _paq.push(['setTrackerUrl', u+'piwik.php']);
        _paq.push(['setSiteId', '1']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
      })();
    </script>
    <!-- End Matomo Code -->
    <?php
    }
    ?>
    <?php
    if (GOOGLE_ANALYTICS_ENABLED) {
    ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo GOOGLE_ANALYTICS_ID; ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', '<?php echo GOOGLE_ANALYTICS_ID; ?>');
        </script>
    <?php
    }
    ?>
</head>

<body>

    <div id="global">

        <a name="haut" id="haut"></a>

        <header id="entete">

            <div id="titre_site">
                <a href="/<?php echo $url_query_region_1er ?>"><img src="/web/interface/logo_titre.jpg" alt="La décadanse" width="180" height="35" /></a>
            </div>

            <div id="entete_haut">

                <a id="btn_menu_pratique" href="#" title="Menu"><i class="fa fa-bars" aria-hidden="true"></i></a>

                <nav id="menu_pratique">

                    <ul>
                       <li><a href="https://github.com/agilare/ladecadanse/" aria-label="Watch agilare/ladecadanse on GitHub" style="font-size:1em" target="_blank"><i class="fa fa-github" aria-hidden="true"></i>
            </a>
                       </li>
                    <?php
                    foreach ($glo_menu_pratique as $nom => $lien)
                    {
                        $menu_pratique_li = '';
                        if ($nom == "Faire un don")
                             $menu_pratique_li = ' style="background: #ffe771;border-radius: 0 0 3px 3px;padding:2px 0;" ';

                            $ici = '';
                        if (strstr($_SERVER['PHP_SELF'], $lien) )
                        {
                            $ici = " class=\"ici\"";
                        }
                        ?>
                        <li <?php echo $ici; ?> <?php echo $menu_pratique_li; ?>><a href="/<?php echo $lien; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
                        <?php
                        }

                        $ici = '';
                        if (!isset($_SESSION['SidPersonne']))
                        {
                            if ( strstr($_SERVER['PHP_SELF'], "annoncerEvenement.php") )
                            {
                                $ici = ' ici ';
                            }
                        ?>

                        <li class="<?php echo $ici; ?>" ><a href="/articles/annoncerEvenement.php"  >Annoncer un événement</a></li>
                        <li class="<?php echo $ici; ?> only-mobile" ><a href="/articles/charte-editoriale.php">Charte éditoriale</a></li>

                    <?php
                    }

                    if (!isset($_SESSION['SidPersonne']))
                    {
                        $ici = '';
                        $ici_login = '';
                        if (strstr($_SERVER['PHP_SELF'], "user-login.php") )
                        {
                            $ici_login = " class=\"ici\"";
                        }

                        if ( strstr($_SERVER['PHP_SELF'], "user-register.php"))
                        {
                            $ici = " class=\"ici\"";
                        }
                        ?>

                        <li <?php echo $ici; ?>><a href="/user-register.php" title="Créer un compte"><strong>Inscription</strong></a></li>
                        <li <?php echo $ici_login; ?> rel="nofollow"><a href="/user-login.php" title="Se connecter au site">Connexion</a></li>

                    <?php
                    }
                    else
                    {
                        if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 10))
                        {
                            $ici = '';
                            if (strstr($_SERVER['PHP_SELF'], "evenement-edit.php") )
                            {
                                $ici = " class=\"ici\"";
                            }
                            ?>

                            <li <?php echo $ici; ?>><a href="/evenement-edit.php?action=ajouter">Ajouter un événement</a></li>

                            <?php
                        }

                        $ici = '';
                        if (strstr($_SERVER['PHP_SELF'], "user.php") )
                        {
                            $ici = " class=\"ici\"";
                        }
                        ?>
                        <li <?php echo $ici; ?>><a href="/user.php?idP=<?php echo $_SESSION['SidPersonne']; ?>"><?php echo $_SESSION['user']; ?></a></li>
                        <li><a href="/user-logout.php" title="Fermer la session">Sortir</a></li>

                        <?php
                        if ($_SESSION['Sgroupe'] <= 4)
                        {
                            echo '<li><a href="/admin/index.php" title="Administration" >Admin</a></li>';
                        }
                    }
                    ?>
                    </ul>

                </nav> <!-- menu pratique -->

            </div> <!-- Fin entete_haut -->

            <div class="spacer"><!-- --></div>

            <nav id="menu">

                <ul>
                    <?php
                    $menu_principal = array("Agenda" => "agenda.php",  "Lieux" => "lieux.php", "Organisateurs" => "organisateurs.php");

                    foreach ($menu_principal as $nom => $lien)
                    {
                        $ici = '';
                        if (strstr($_SERVER['PHP_SELF'], $lien)
                        || ($lien == "/lieux.php" && strstr($_SERVER['PHP_SELF'], "lieu.php"))
                        || ($lien == "/organisateurs.php" && strstr($_SERVER['PHP_SELF'], "organisateur.php"))
                        || ($lien == "/agenda.php" && strstr($_SERVER['PHP_SELF'], "agenda.php"))
                        )
                        {
                            $ici = ' class="ici" ';
                        }
                    ?>

                        <li <?php  echo $ici; ?>

                      <?php
                        if ($nom == "Agenda")
                        {
                        ?>
                            id="bouton_agenda">
                            <?php
                            echo "<a href=\"/".$lien."?".$url_query_region_et."courant=".$get['courant']."&amp;sem=".$get['sem']."&amp;tri_agenda=".$get['tri_agenda']."\">".$nom."</a>";
                            ?>
                            </li>
                            <li id="bouton_calendrier">
                            <a href="#" id="btn_calendrier" class="mobile"><img src="<?php echo $url_images_interface_icons ?>calendar_view_week.png" alt="Calendrier" width="16" height="16" /></a>
                            </li>
                    <?php
                        }
                        else
                        {
                        ?>
                        ><a href="/<?php echo $lien."?".$url_query_region; ?>"><?php echo $nom; ?></a></li>
                        <?php
                        }
                    }
                    ?>

                    <li class="form_recherche">
                        <a href="#" id="btn_search"><i class="fa fa-search" aria-hidden="true"></i></a>
                        <form class="recherche" action="/evenement-search.php" method="get" enctype="application/x-www-form-urlencoded">
                            <input type="text" class="mots" name="mots" size="22" maxlength="100" placeholder="Rechercher un événement" /><input type="submit" class="submit" name="formulaire" value=" " /><input type="text" name="name_as" value="" class="name_as" id="name_as" />
                        </form>
                    </li>

                </ul>

                <div class="clear_mobile"></div>

                <form class="recherche_mobile" action="/evenement-search.php" method="get" enctype="application/x-www-form-urlencoded">
                    <input type="text" class="mots" name="mots" size="35" maxlength="100" placeholder="Rechercher un événement" /><input type="submit" class="submit" name="formulaire" value="OK" /><input type="text" name="name_as" value="" class="name_as" id="name_as" />
                </form>

            </nav>
            <!-- Fin Menu-->

        </header>
        <!-- Fin entete -->

        <div class="spacer"><!-- --></div>

        <!-- Début Conteneur -->
        <div id="conteneur" <?php if (strstr(dirname($_SERVER['PHP_SELF']), 'admin')) { ?>style="padding-right: 0" <?php } ?> >

