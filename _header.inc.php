<?php
$tab_pages_dc = array("/agenda.php", "/evenement.php");

$nom_page = basename($_SERVER["SCRIPT_FILENAME"], '.php');

/* GENRE */
$get['genre'] = "";
if (!empty($_GET['genre']))
{
	if (array_key_exists(urldecode($_GET['genre']), $glo_tab_genre))
	{
		$get['genre'] = urldecode($_GET['genre']);
	}
	else
	{
		exit;
	}

}

$get['zone'] = "tout";
$get['moment'] = "tout";
$get['courant'] = "";

/* else if (($nom_page == "agenda" || $nom_page == "agenda2" || $nom_page == "agenda3") && empty($_GET['genre']))
{
	$get['genre'] = "tout";
} */


/* DATE COURANTE */
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
		//trigger_error("date GET 'courant' non valable : ".$_GET['courant'], E_USER_WARNING);
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


if (strstr($_SERVER['PHP_SELF'], "evenement.php") && isset($_GET['idE']))
{
	if (is_numeric($_GET['idE']))
	{
		$get_idE = $_GET['idE'];
		$req_even = $connector->query("
		SELECT dateEvenement, genre
		FROM evenement WHERE idEvenement=".$get_idE);

		$tab_even = $connector->fetchArray($req_even);


		$get['courant'] = $tab_even['dateEvenement'];
		$get['genre'] = $tab_even['genre'];
		//echo "genre evenement ".$get['genre'];
	}
	else
	{
		//trigger_error("idE non valable", E_USER_WARNING);
		exit;
	}
}

/* SEMAINE */
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

/* TRI */
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


$pages_post = array("ajouterBreve", "ajouterCommentaire", "ajouterDescription", "ajouterEvenement", "ajouterLieu",
"ajouterPersonne", "copierEvenement", "contacteznous", "login");


header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if ($nom_page == "agenda" && isset($page_titre))
{

	if ($get['sem'] == 1)
	{
		$lundim = date_iso2lundim($get['courant']);
		$page_titre .= " ".$get['genre']." du ".date_fr($lundim[0], "annee", "", "", false)." au ".date_fr($lundim[1], "annee", "", "", false);
	}
	else
	{
		$page_titre .= " ".$get['genre']." du ".date_fr($get['courant'], "annee", "", "", false);
	}

        if ($_SESSION['region'] == 'vd')
        {
            $page_titre .= " à Lausanne";   
        }
        elseif ($_SESSION['region'] == 'fr')
        {
            $page_titre .= " à Fribourg";   
        }            
        else
        {
            $page_titre .= " à Genève";
        }
	
}


?>

<!doctype html>
<html lang="fr">
<head>
	    <meta charset="utf-8" />
	<?php
	/* les robots doivent ignorer les événements passés */
	if ($nom_page == 'evenement' && isset($_GET['idE']))
	{

$req_de = $connector->query("SELECT dateEvenement FROM evenement WHERE idEvenement=".intval($_GET['idE']));
$tab_de = $connector->fetchArray($req_de);
	if ($tab_de['dateEvenement'] < $glo_auj )
	{
	?>	
	<meta name="robots" content="noindex, nofollow" />	
	<?php
	}
	}

	
	if (isset($_GET['style']) && $_GET['style'] == "imprimer")
	{
	?>
		<meta name="robots" content="noindex, nofollow" />	

	<?php
	}
	
	
	if (isset($_GET['courant']) && $_GET['courant'] < date("Y-m-d"))
	{
	?>
		<meta name="robots" content="noindex, nofollow" />	
<?php } ?>
	<title><?php
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
	<?php 
	if (isset($page_description) && !empty($page_description))
	{
	?>
	<meta name="description" content="<?php echo $page_description ?>" />
	<?php
	}
	?>
	<?php

	include("_styles.inc.php");

	if ($nom_page == "index")
	{
	?>

	<link rel="alternate" type="application/rss+xml" title="Événements du jour" href="<?php echo $url_site; ?>rss.php?type=evenements_auj" />
	<link rel="alternate" type="application/rss+xml" title="Derniers événements ajoutés" href="<?php echo $url_site; ?>rss.php?type=evenements_ajoutes" />


	<?php
	}


	if ($nom_page == "lieu")
	{
	?>

		<link rel="alternate" type="application/rss+xml" title="Prochains événements dans ce lieu" href="<?php echo $url_site.'rss.php?type=lieu_evenements&amp;id='.$get['idL'] ?>" />
	<?php
	}
	else if ($nom_page == "evenement")
	{
	?>
		<link rel="alternate" type="application/rss+xml" title="Commentaires de cette événement" href="<?php echo $url_site.'rss.php?type=evenement_commentaires&amp;id='.$get['idE'] ?>" />

	<?php
	}
	?>
	<link rel="shortcut icon" href="web/interface/favicon.png" />	
    <link rel="apple-touch-icon" href="web/interface/apple-icon.png" />    
    <link rel="apple-touch-icon" sizes="57x57" href="web/interface/apple-icon-57x57.png" />       
    <link rel="apple-touch-icon" sizes="76x76" href="web/interface/apple-icon-76x76.png" />     
    <link rel="apple-touch-icon" sizes="152x152" href="web/interface/apple-icon-152x152.png" />  
    <?php     
    if (GOOGLE_ANALYTICS_ENABLED) {
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
    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>  
   <script async defer
      src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_API_KEY; ?>&callback=initMap">
    </script>
</head>

<body>

<?php
if (isset($_GET['style']) && $_GET['style'] == "imprimer")
{
echo '<a id="bouton_imprimer" title="Imprimer la page" href="javascript:window.print()">Imprimer la page</a>';
}
?>

<div id="global">
<a name="haut" id="haut"></a>

<div id="entete">
   
    <div id="titre_site">
        <a href="<?php echo $url_site.$url_query_region_1er ?>"><img src="/web/interface/logo_titre.jpg" alt="La décadanse" width="180" height="35" /></a>
    </div>

    <div id="entete_haut">
        <a id="btn_menu_pratique" href="#" title="Menu"><i class="fa fa-bars" aria-hidden="true"></i></a>
        <div id="menu_pratique">
        <ul>
           <li><a href="https://github.com/agilare/ladecadanse/" aria-label="Watch agilare/ladecadanse on GitHub" style="font-size:1em" target="_blank"><i class="fa fa-github" aria-hidden="true"></i>
</a></li>
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
            <li <?php echo $ici; ?> <?php echo $menu_pratique_li; ?>><a href="<?php echo $url_site.$lien; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
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


            <li class="<?php echo $ici; ?>" ><a href="<?php echo $url_site; ?>annoncerEvenement.php"  >Annoncer un événement</a></li>
            <li class="<?php echo $ici; ?> only-mobile" ><a href="<?php echo $url_site; ?>charte-editoriale.php">Charte éditoriale</a></li>
               
        <?php
        }

        if (!isset($_SESSION['SidPersonne']))
        {
            $ici = '';
            $ici_login = '';
            if (strstr($_SERVER['PHP_SELF'], "login.php") )
            {
                $ici_login = " class=\"ici\"";
            }

            if ( strstr($_SERVER['PHP_SELF'], "inscription.php"))
            {
                $ici = " class=\"ici\"";
            }
            ?>

            <li <?php echo $ici; ?>><a href="<?php echo $url_site; ?>inscription.php" title="Créer un compte"><strong>Inscription</strong></a></li>
        <li <?php echo $ici_login; ?> rel="nofollow"><a href="<?php echo $url_site; ?>login.php" title="Se connecter au site">Connexion</a></li>

        <?php
        }
        else
        {
            if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 10))
            {
                $ici = '';
                if (strstr($_SERVER['PHP_SELF'], "ajouterEvenement.php") )
                {
                    $ici = " class=\"ici\"";
                }
                ?>

                <li <?php echo $ici; ?>><a href="<?php echo $url_site; ?>ajouterEvenement.php?action=ajouter">Ajouter un événement</a></li>

                <?php
            }

            $ici = '';
            if (strstr($_SERVER['PHP_SELF'], "personne.php") )
            {
                $ici = " class=\"ici\"";
            }
            ?>
           <li <?php echo $ici; ?>><a href="<?php $url_site; ?>personne.php?idP=<?php echo $_SESSION['SidPersonne']; ?>"><?php echo $_SESSION['user']; ?></a></li>
            <li><a href="<?php echo $url_site; ?>logout.php" title="Fermer la session">Sortir</a></li>

            <?php
            if ($_SESSION['Sgroupe'] <= 4)
            {
                    echo '<li><a href="'.$url_site.'admin/index.php" title="Administration" >Admin</a></li>';
            }
        }
        ?>
            </ul>
    </div>
</div> <!-- Fin entete_haut -->

<div class="spacer"><!-- --></div>

<div id="menu">
<ul>
    <?php

    $menu_principal = array("Agenda" => "agenda.php",  "Lieux" => "lieux.php", "Organisateurs" => "organisateurs.php");

    foreach ($menu_principal as $nom => $lien)
    {
        $ici = '';
        if (strstr($_SERVER['PHP_SELF'], $lien) 
        || ($lien == "lieux.php" && strstr($_SERVER['PHP_SELF'], "lieu.php"))
        || ($lien == "organisateurs.php" && strstr($_SERVER['PHP_SELF'], "organisateur.php"))
        || ($lien == "agenda.php" && strstr($_SERVER['PHP_SELF'], "agenda.php"))
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
            echo "<a href=\"".$url_site.$lien."?".$url_query_region_et."courant=".$get['courant']."&amp;sem=".$get['sem']."&amp;tri_agenda=".$get['tri_agenda']."\">".$nom."</a>"; 
            ?>
            </li>
            <li id="bouton_calendrier">
            <a href="#" id="btn_calendrier" class="mobile"><img src="<?php echo $IMGicones ?>calendar_view_week.png" alt="Calendrier" width="16" height="16" /></a>
            </li>
    <?php
        }
        else
        {
        ?>
        ><a href="<?php echo $url_site.$lien."?".$url_query_region; ?>"><?php echo $nom; ?></a></li>
        <?php
        }
    }
    ?>	

<?php if (0) { ?>
<li style="float:right">
    <form action="index.php" method="get" >
        <select name="region" onchange="this.form.submit()">
    <?php
    foreach ($glo_regions as $code => $nom)
    {
        if ($code == 'ge' || $code == 'vd')
        {
        $selected = '';
        if ($code == $_SESSION['region'])
            $selected = ' selected';
        ?>
    <option value="<?php echo $code; ?>" <?php echo $selected; ?>><?php echo $nom; ?></option>
        <?php
        }
    }
    ?>
</select></form>
<?php } ?>
</li>
	<li class="form_recherche">
        <a href="#" id="btn_search"><i class="fa fa-search" aria-hidden="true"></i></a>
        <form class="recherche" action="recherche.php" method="get" enctype="application/x-www-form-urlencoded">
            <input type="text" class="mots" name="mots" size="22" maxlength="100" placeholder="Rechercher un événement" /><input type="submit" class="submit" name="formulaire" value=" " /><input type="text" name="name_as" value="" class="name_as" id="name_as" />
        </form>
	</li>

	</ul>
	<div class="clear_mobile"></div>
    <form class="recherche_mobile" action="recherche.php" method="get" enctype="application/x-www-form-urlencoded">
        <input type="text" class="mots" name="mots" size="35" maxlength="100" placeholder="Rechercher un événement" /><input type="submit" class="submit" name="formulaire" value="OK" /><input type="text" name="name_as" value="" class="name_as" id="name_as" />
    </form>
</div>
<!-- Fin Menu-->

</div>
<!-- Fin entete -->

<div class="spacer"><!-- --></div>

<!-- Début Conteneur -->
<div id="conteneur">



