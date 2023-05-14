<?php

use Ladecadanse\UserLevel;
?>

<style>
    #gestion ul li
{
	margin:0.4em 0;
}
</style>

<!-- Deb gestion -->
<div id="gestion">

	<h2 style="font-size:0.95em;margin-top:1em;color:#5c7378">Gérer :</h2>

	<ul style="list-style-type:none">
		<li>
            <a href="/admin/gererEvenements.php"><img src="<?php echo $url_images_interface_icons ?>calendar.png" alt="" />les événements</a>
		</li>
		<li>
            <a href="/admin/gerer.php?element=lieu"><img src="<?php echo $url_images_interface_icons ?>building.png" alt="" />les lieux</a>
		</li>
		<li>
            <a href="/admin/gerer.php?element=organisateur"><img src="<?php echo $url_images_interface_icons ?>group.png" alt="" />les organisateurs</a>
		</li>

        <?php if ($_SESSION['Sgroupe'] == UserLevel::SUPERADMIN) { ?>
            <li>
            <a href="/admin/gerer.php?element=personne"><img src="<?php echo $url_images_interface_icons ?>user.png" alt="" />les personnes</a>
		</li>
        <?php } ?>
    </ul>
    <ul style="list-style-type:none">
        <li>
            <a href="https://tools.ladecadanse.ch/doc/Administration/index.html" class="url lien_ext" target="_blank">Documentation</a>
        </li>
    </ul>
</div>
<!-- Fin gestion -->
