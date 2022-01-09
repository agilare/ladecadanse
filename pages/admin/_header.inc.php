<?php
$tab_pages_dc = array("/pages/agenda.php", "/pages/evenement.php");

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
/*
		trigger_error("genre non valable : ".$_SERVER['PHP_SELF']." ".$_GET['genre'], E_USER_WARNING);
*/
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
		//trigger_error("date non valable", E_USER_WARNING);
		exit;
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


if (mb_strstr($_SERVER['PHP_SELF'], "evenement.php") && isset($_GET['idE']))
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
		//trigger_error("tri_agenda non valable", E_USER_WARNING);
		exit;
	}
}
else
{
	$get['tri_agenda'] = "dateAjout";
}


$pages_post = array("ajouterBreve", "multi-comment", "multi-description", "evenement-edit", "lieu-edit",
"user-edit", "evenement-copy", "contacteznous", "user-login");



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

	$page_titre .= " à Genève";
}



?>

<!doctype html>
<html lang="fr">

<head>
	<meta http-equiv="Content-language" content="fr" />
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />

	<title>
	<?php
		echo $page_titre." - La décadanse";	
	?>
	</title>
	<?php 
	if (!empty($page_description))
	{
	?>
	<meta name="description" content="<?php echo $page_description ?>" />
	<?php
	}
	?>
	<?php

	include("../_styles.inc.php");
	?>
	<link rel="shortcut icon" href="/web/interface/favicon.png" />	
    <link rel="apple-touch-icon" href="/web/interface/apple-icon.png" />    
    <link rel="apple-touch-icon" sizes="57x57" href="/web/interface/apple-icon-57x57.png" />       
    <link rel="apple-touch-icon" sizes="76x76" href="/web/interface/apple-icon-76x76.png" />     
    <link rel="apple-touch-icon" sizes="152x152" href="/web/interface/apple-icon-152x152.png" />  
	<link rel="shortcut icon" href="<?php echo $url_images ?>interface/favicone.gif" />
     <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>  
   <script async defer
      src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_API_KEY; ?>&callback=initMap">
    </script>      
</head>

<body>

<div id="global">
<a name="haut" id="haut"></a>
<div id="entete">

<div id="titre_site">
<a href="<?php echo $url_site ?>"><img src="<?php echo $url_site ?>web/interface/logo_titre.jpg" alt="La décadanse"  width="180" height="35" /></a>
</div>


<div id="entete_haut">
	<div id="menu_pratique">
	<ul>
<?php
foreach ($glo_menu_pratique as $nom => $lien)
{
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], $lien) )
	{
		$ici = " class=\"ici\"";
	}
    ?>
	<li><a href="/pages/<?php echo $lien; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
    
   
<?php
}

if (!isset($_SESSION['SidPersonne']))
{
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], "user-login.php") )
	{
		$ici = " class=\"ici\"";
	}
	?>
    
    <li><a href="/pages/user-register.php" title="S'inscrire pour devenir membre">Inscrivez-vous</a></li>
<li <?php echo $ici; ?>><a href="/pages/user-login.php" title="Se connecter au site">Connexion</a></li>

<?php
}
else
{
	if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 10))
	{
		$ici = '';
		if (mb_strstr($_SERVER['PHP_SELF'], "evenement-edit.php") )
		{
			$ici = " class=\"ici\"";
		}
        ?>
        
        <li <?php echo $ici; ?>><a href="/pages/evenement-edit.php?action=ajouter">Ajouter un événement</a></li>
        
        <?php
	}
	
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], "user.php") )
	{
		$ici = " class=\"ici\"";
	}
    ?>
   <li <?php echo $ici; ?>><a href="/pages/user.php?idP=<?php echo $_SESSION['SidPersonne']; ?>"><?php echo $_SESSION['user']; ?></a></li>
	<li><a href="/pages/user-logout.php" title="Fermer la session">Sortir</a></li>
    
    <?php
	if ($_SESSION['Sgroupe'] <= 4)
	{
			echo '<li class=\"ici\"><a href="/pages/admin/index.php" title="Administration" >Admin</a></li>';
	}
}


?>
	</ul>

	</div>

</div>
<!-- Fin entete_haut -->

<div class="spacer"><!-- --></div>

<!-- Debut Menu -->
<div id="menu">

<ul>
<?php

$menu_principal = array("Agenda" => "/pages/agenda.php",  "Lieux" => "/pages/lieux.php", "Organisateurs" => "/pages/organisateurs.php");



foreach ($menu_principal as $nom => $lien)
{
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], $lien) 
	|| ($lien == "/pages/lieux.php" && mb_strstr($_SERVER['PHP_SELF'], "lieu.php"))
	|| ($lien == "/pages/organisateurs.php" && mb_strstr($_SERVER['PHP_SELF'], "organisateur.php"))
	)
	{
		$ici = " class=\"ici\" ";
	}
?>
    
    <li
    
  <?php  
	if ($nom == "Agenda")
	{
    ?>
		id="bouton_agenda">
        <?php
		echo "<a href=\"".$url_site.$lien."?courant=".$get['courant']."&amp;sem=".$get['sem']."&amp;tri_agenda=".$get['tri_agenda']."\">".$nom."</a></li>"; 
        ?>
		<li>
<?php
	}
	else
	{
        echo $ici;
        ?>
		><a href="<?php echo $url_site.$lien; ?>"><?php echo $nom; ?></a>
        <?php
	}

	echo "</li>";

}

?>
	<li class="form_recherche">
        <form class="recherche" action="/pages/evenement-search.php" method="get" enctype="application/x-www-form-urlencoded">
        <input type="text" class="mots" name="mots" size="22" maxlength="50" placeholder="Rechercher un événement" /><input type="submit" class="submit" name="formulaire" value="" />

        </form>
	</li>

	</ul>


</div>
<!-- Fin Menu-->

</div>
<!-- Fin entete -->

<div class="spacer"><!-- --></div>

<!-- Début Conteneur -->
<div id="conteneur" style="padding-left:5px">

