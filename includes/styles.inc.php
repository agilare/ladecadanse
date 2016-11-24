<?php 

//if (0) {
$tab_pages_mobiles = array('index', 'evenement', 'agenda', 'apropos', 'login', 'contacteznous', 'liens', 'lieux', 'lieu', 'organisateurs', 'organisateur', '404', 'recherche','signaler_erreur', 'action_favori', 'inscription','ajouterEvenement','copierEvenement','email_evenement','ajouterLieu','ajouterOrganisateur', 'supprimer', 'ajouterSalle', 'ajouterDescription','personne', 'ajouterPersonne', 'annoncerEvenement', 'motdepasse_demande', 'motdepasse_reset', 'faireUnDon');

if (in_array($nom_page, $tab_pages_mobiles)) { ?>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
<?php } ?>
<style type="text/css">
html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,font,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td{border:0;outline:0;font-weight:inherit;font-style:inherit;font-size:100%;font-family:inherit;vertical-align:baseline;margin:0;padding:0;}:focus{outline:0;}body{line-height:1;color:#000;background:#FFF;}ol,ul{list-style:none;}table{border-collapse:separate;border-spacing:0;}caption,th,td{text-align:left;font-weight:400;}blockquote:before,blockquote:after,q:before,q:after{content:"";}
</style>
<!--<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/reset.css" title="Reset" />-->




<?php
if (isset($_GET['style']) && $_GET['style'] == "imprimer")
{
?>
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/imprimer.css" title="Normal" />
<?php
}
else
{
?>

<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/global.css" title="Normal" />

<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/calendrier.css" media="screen" title="Normal" />
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/<?php echo $nom_page; ?>.css" media="screen" title="Normal" />
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/diggstyle.css" media="screen" title="Normal" />



<?php
}

if (isset($extra_css) && is_array($extra_css))
{
	foreach ($extra_css as $import)
	{
?>
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/<?php echo $import ?>.css?update2" media="screen" title="Normal" />
<?php
	}
}
?>

<link rel="stylesheet" media="screen and (min-width:800px)"  href="<?php echo $url_site ?>css/desktop.css" type="text/css">
<link rel="stylesheet" media="screen and (max-width:800px)"  href="<?php echo $url_site ?>css/mobile.css" type="text/css">
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/imprimer.css" media="print" title="Imprimer" />

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
<link rel="stylesheet" href="<?php echo $url_site ?>librairies/magnific-popup/magnific-popup.css">
