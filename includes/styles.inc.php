<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/normalize.css" />
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

<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/global.css?<?php echo time() ?>" title="Normal" />
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/calendrier.css" media="screen" />
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/<?php echo $nom_page; ?>.css" media="screen"  />
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>css/diggstyle.css" media="screen" />
<link rel="stylesheet" type="text/css" href="<?php echo $url_site ?>librairies/chosen/chosen.css" media="screen" />

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
<link rel="stylesheet" href="<?php echo $url_site ?>librairies/zebra_datepicker/css/default/zebra_datepicker.min.css">
