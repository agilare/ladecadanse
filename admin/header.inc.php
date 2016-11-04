<?php
$tab_pages_dc = array("/agenda.php", "/evenement.php");

preg_match(PREG_PATTERN_NOMPAGE, $_SERVER['PHP_SELF'], $matches);
$tab_nom_page = explode("/", $matches[1]);
$nom_page = end($tab_nom_page);

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


/* MODES */
$tab_modes = array("etendu", "condense");
if (!empty($_GET['mode']))
{
	if (in_array($_GET['mode'], $tab_modes))
	{
		$get['mode'] = $_GET['mode'];
	}
	else
	{
		//trigger_error("sem non valable", E_USER_WARNING);
		exit;
	}
}
else
{
	$get['mode'] = "etendu";
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


$pages_post = array("ajouterBreve", "ajouterCommentaire", "ajouterDescription", "ajouterEvenement", "ajouterLieu",
"ajouterPersonne", "copierEvenement", "contacteznous", "login");



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

	include("../includes/styles.inc.php");
	?>

	<link rel="shortcut icon" href="<?php echo $url_images ?>interface/favicone.gif" />
       
</head>

<body>

<div id="global">
<a name="haut" id="haut"></a>
<div id="entete">

<div id="titre_site">
<h1><a href="<?php echo $url_site ?>" title="Retour à la page d'accueil"><img src="<?php echo $url_images."interface/logo_titre.jpg" ?>" alt="La décadanse"  width="180" height="35" /></a></h1>
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
	<li><a href="<?php echo $url_site.$lien; ?>" <?php echo $ici; ?>><?php echo $nom; ?></a></li>
    
   
<?php
}

if (!isset($_SESSION['SidPersonne']))
{
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], "login.php") )
	{
		$ici = " class=\"ici\"";
	}
	?>
    
    <li><a href="<?php echo $url_site; ?>inscription.php" title="S'inscrire pour devenir membre">Inscrivez-vous</a></li>
<li <?php echo $ici; ?>><a href="<?php echo $url_site; ?>login.php" title="Se connecter au site">Connexion</a></li>

<?php
}
else
{
	if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 10))
	{
		$ici = '';
		if (mb_strstr($_SERVER['PHP_SELF'], "ajouterEvenement.php") )
		{
			$ici = " class=\"ici\"";
		}
        ?>
        
        <li <?php echo $ici; ?>><a href="<?php echo $url_site; ?>ajouterEvenement.php?action=ajouter">Ajouter un événement</a></li>
        
        <?php
	}
	
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], "personne.php") )
	{
		$ici = " class=\"ici\"";
	}
    ?>
   <li <?php echo $ici; ?>><a href="../personne.php?idP=<?php echo $_SESSION['SidPersonne']; ?>"><?php echo $_SESSION['user']; ?></a></li>
	<li><a href="<?php echo $url_site; ?>logout.php" title="Fermer la session">Sortir</a></li>
    
    <?php
	if ($_SESSION['Sgroupe'] <= 4)
	{
			echo '<li class=\"ici\"><a href="'.$url_site.'admin/index.php" title="Administration" >Admin</a></li>';
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

$menu_principal = array("Agenda" => "agenda.php",  "Lieux" => "lieux.php", "Organisateurs" => "organisateurs.php");



foreach ($menu_principal as $nom => $lien)
{
    $ici = '';
	if (mb_strstr($_SERVER['PHP_SELF'], $lien) 
	|| ($lien == "lieux.php" && mb_strstr($_SERVER['PHP_SELF'], "lieu.php"))
	|| ($lien == "organisateurs.php" && mb_strstr($_SERVER['PHP_SELF'], "organisateur.php"))
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
		echo "<a href=\"".$url_site.$lien."?courant=".$get['courant']."&amp;sem=".$get['sem']."&amp;tri_agenda=".$get['tri_agenda']."&amp;mode=".$get['mode']."\">".$nom."</a></li>"; 
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
	<form class="recherche" action="<?php echo $url_site ?>recherche.php" method="get" enctype="application/x-www-form-urlencoded">
	<fieldset>
	<input type="text" class="mots" name="mots" size="22" maxlength="50" value="Rechercher un événement" onfocus="if(this.value=='Rechercher un événement') this.value=''" onblur="if(this.value=='') this.value='Rechercher un événement'" /><input type="submit" class="submit" name="formulaire" value="" />
	</fieldset>
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



