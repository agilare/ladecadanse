<?php

use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;

?>

<!doctype html>
<html lang="fr">

<head>

    <meta charset="utf-8" />

    <?php
    if ($nom_page == 'evenement-agenda' && isset($_GET['courant']) && ($_GET['courant'] < date("Y-m-d") || (isset($total_even) && $total_even == 0 && $_GET['courant'] > date('Y-m-d', strtotime('+1 year')))))
    {
        ?>
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
            echo sanitizeForHtml($page_titre) . " — La décadanse";
        }
        else
        {
            echo "La décadanse — " . sanitizeForHtml($page_titre);
        }
        ?>
	</title>

        <meta name="description" content="<?php echo sanitizeForHtml(($page_description ?? '')) ?>" />

    <meta property="og:site_name" content="La décadanse" />
    <meta property="og:logo" content="/web/interface/apple-icon-152x152.png" />
    <meta property="og:type" content="article" />
    <meta property="og:locale" content="fr" />
    <meta property="og:title" content="<?php echo sanitizeForHtml($page_titre) . " — La décadanse"; ?>" />
    <meta property="og:description" content="<?php echo sanitizeForHtml(($page_description ?? '')) ?>" />
    <?php
    if (!empty($page_url))
    {
        ?>
        <meta property="og:url" content="<?php echo $site_full_url . $page_url; ?>" />
    <?php } ?>

    <?php
    if (!empty($page_image))
    {
        ?>
        <meta property="og:image" content="<?php echo $site_full_url . $page_image ?>" />
            <meta property="og:image:alt" content="Flyer" />
        <?php } ?>

        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">

    <link rel="stylesheet" type="text/css" href="/web/css/normalize.css" />
    <link rel="stylesheet" type="text/css" href="/web/css/imprimer.css" media="print" />
    <link rel="stylesheet" type="text/css" href="/web/css/global.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="/web/css/calendrier.css" media="screen" />
    <?php
    $cssFilePath = "/web/css/{$nom_page}.css";
    if (file_exists(__ROOT__ . $cssFilePath))
    {
        ?>
        <link rel="stylesheet" type="text/css" href="<?php echo $cssFilePath; ?>" media="screen"  />
    <?php } ?>
        <link rel="stylesheet" type="text/css" href="/web/css/diggstyle.css" media="screen" />
        <link href="/vendor/select2/select2/dist/css/select2.min.css" rel="stylesheet">
    <?php
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
    <link rel="stylesheet" type="text/css" href="/web/js/libs/Zebra_datepicker/css/default/zebra_datepicker.min.css">

    <?php HtmlShrink::showLinkRss($nom_page); ?>

	<link rel="shortcut icon" href="/web/interface/favicon.png" />
    <link rel="apple-touch-icon" href="/web/interface/apple-icon.png" />
    <link rel="apple-touch-icon" sizes="57x57" href="/web/interface/apple-icon-57x57.png" />
    <link rel="apple-touch-icon" sizes="76x76" href="/web/interface/apple-icon-76x76.png" />
    <link rel="apple-touch-icon" sizes="152x152" href="/web/interface/apple-icon-152x152.png" />

    <?php
    if (GLITCHTIP_ENABLED)
    {
        ?>
    <script src="https://browser.sentry-cdn.com/9.14.0/bundle.min.js" crossorigin="anonymous"></script>
        <script nonce="<?php echo CSP_NONCE ?>">
                Sentry.init({
              dsn: "<?php echo GLITCHTIP_DSN ?>",
              tracesSampleRate: 0.01,
        });
        </script>
        <?php
    }
    ?>

        <?php
        if (MATOMO_ENABLED) {
    ?>
        <!-- Matomo -->
        <script nonce="<?php echo CSP_NONCE ?>">
            'use strict';
              var _paq = window._paq = window._paq || [];
                <?php
                if (isset($_SESSION['SidPersonne']))
                {
                    ?>
        _paq.push(['setUserId', <?php echo json_encode($_SESSION['user']) ?>]);
                    <?php
                }
                ?>
                <?php
                if (!isset($_SESSION['SidPersonne']) && !empty($_COOKIE['just_logged_out']))
                {
                    ?>
        _paq.push(['resetUserId']);
                    <?php
                }
                ?>
                      /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
              _paq.push(['trackPageView']);
              _paq.push(['enableLinkTracking']);
              (function() {
                    var u="//<?php echo MATOMO_URL; ?>";
                    _paq.push(['setTrackerUrl', u+'matomo.php']);
                    _paq.push(['setSiteId', '<?php echo MATOMO_SITE_ID; ?>']);
                    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
                g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
              })();
            </script>
    <noscript><p><img referrerpolicy="no-referrer-when-downgrade" src="https://<?php echo MATOMO_URL; ?>matomo.php?idsite=<?php echo MATOMO_SITE_ID; ?>&amp;rec=1" style="border:0;" alt="" /></p></noscript>
    <!-- End Matomo Code -->
        <?php
    }
    ?>
    <?php
    if (DARKVISITORS_ENABLED)
    {
        ?>
        <script src="https://darkvisitors.com/tracker.js?project_key=<?php echo DARKVISITORS_PROJECT_KEY ?>"></script>
        <?php
    }
    ?>
</head>

<body>

    <div id="global">

        <a id="haut"></a>

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
                        if (strstr((string) $_SERVER['PHP_SELF'], (string) $lien) )
                        {
                            $ici = " class=\"ici\"";
                        }
                        ?>
                        <li <?php echo $ici; ?> <?php echo $menu_pratique_li; ?>><a href="<?php echo $lien; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
                        <?php
                        }

                        $ici = '';
                        if (!isset($_SESSION['SidPersonne']))
                        {
                            if ( strstr((string) $_SERVER['PHP_SELF'], "annoncerEvenement.php") )
                            {
                                $ici = ' ici ';
                            }
                        ?>

                            <li class="<?php echo $ici; ?>" ><a href="/articles/annoncerEvenement.php">Annoncer un événement</a></li>
                            <li class="<?php echo $ici; ?> only-mobile" ><a href="/articles/charte-editoriale.php">Charte éditoriale</a></li>

                    <?php
                    }

                    if (!isset($_SESSION['SidPersonne']))
                    {
                        $ici = '';
                        $ici_login = '';
                        if (strstr((string) $_SERVER['PHP_SELF'], "user-login.php") )
                        {
                            $ici_login = " class=\"ici\"";
                        }

                        if ( strstr((string) $_SERVER['PHP_SELF'], "user-register.php"))
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
                        if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::ACTOR)) {
        $ici = '';
                                if (strstr((string) $_SERVER['PHP_SELF'], "evenement-edit.php") )
                            {
                                $ici = " class=\"ici\"";
                            }
                            ?>

                            <li <?php echo $ici; ?>><a href="/evenement-edit.php?action=ajouter">Ajouter un événement</a></li>

                            <?php
                        }

                        $ici = '';
                        if (strstr((string) $_SERVER['PHP_SELF'], "user.php") )
                        {
                            $ici = " class=\"ici\"";
                        }
                        ?>
                                <li <?php echo $ici; ?>><a href="/user.php?idP=<?php echo $_SESSION['SidPersonne']; ?>"><?php echo sanitizeForHtml($_SESSION['user']); ?></a></li>
                                <li><a href="/user-logout.php" >Sortir</a></li>

                        <?php
                        if ($_SESSION['Sgroupe'] <= 4) {
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
                    $menu_principal = ["Agenda" => "evenement-agenda.php", "Lieux" => "lieux.php", "Organisateurs" => "organisateurs.php"];

                    foreach ($menu_principal as $nom => $lien)
                    {
                        $ici = '';
                        if (strstr((string) $_SERVER['PHP_SELF'], $lien)
                        || ($lien == "/lieux.php" && strstr((string) $_SERVER['PHP_SELF'], "lieu.php"))
                        || ($lien == "/organisateurs.php" && strstr((string) $_SERVER['PHP_SELF'], "organisateur.php"))
                        || ($lien == "/evenement-agenda.php" && strstr((string) $_SERVER['PHP_SELF'], "agenda.php"))
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
                            echo "<a href=\"/" . $lien . "?" . $url_query_region_et . "\">" . $nom . "</a>";
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
                            <input type="text" class="mots" name="mots" size="22" maxlength="100" placeholder="Rechercher un événement" />
                            <input type="submit" class="submit" name="formulaire" value=" " />
                            <input type="text" name="name_as" value="" class="name_as"  />
                        </form>
                    </li>

                </ul>

                <div class="clear_mobile"></div>

                <form class="recherche_mobile" action="/evenement-search.php" method="get" enctype="application/x-www-form-urlencoded">
                    <input type="text" class="mots" name="mots" size="35" maxlength="100" placeholder="Rechercher un événement" />
                    <input type="submit" class="submit" name="formulaire" value="OK" />
                    <input type="text" name="name_as" value="" class="name_as"  />
                </form>

            </nav>
            <!-- Fin Menu-->

        </header>
        <!-- Fin entete -->

        <div class="spacer"><!-- --></div>

        <!-- Début Conteneur -->
        <div id="conteneur" <?php if (strstr(dirname((string) $_SERVER['PHP_SELF']), 'admin') || in_array($nom_page, ['evenement-edit', 'evenement-report', 'lieu-edit', 'lieu-text-edit', 'organisateur-edit', 'contacteznous', 'user-login', 'user-edit', 'user-register'])) { ?>style="padding-right: 5px" <?php } ?> >

