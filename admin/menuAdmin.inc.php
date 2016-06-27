<style>
#gestion ul li
{
	margin:0.4em 0;
}
</style>

<!-- Deb gestion -->
<div id="gestion">

	<h2 style="font-weight:bold;margin:0.4em 0;">G&eacute;rer</h2>

	<ul>
		<li><a href="<?php echo $url_admin ?>gererEvenements.php">
		<img src="<?php echo $IMGicones ?>calendar.png" alt="" />les &eacute;v&eacute;nements</a>
		</li>
		<li>
		<img src="<?php echo $IMGicones ?>comment.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=commentaire">les commentaires</a></li>
		<?php /*
		<li>
		<img src="<?php echo $IMGicones ?>newspaper.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=breve">les br?ves</a>
		</li>*/ ?>
		<li>
		<img src="<?php echo $IMGicones ?>building.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=lieu">les lieux</a>
		</li>
		<li>
		<img src="<?php echo $IMGicones ?>group.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=organisateur">les organisateurs</a>
		</li>
		<li>
		<img src="<?php echo $IMGicones ?>page_white.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=description">les descriptions</a>
		</li>
		<li>
		<img src="<?php echo $IMGicones ?>user.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=personne">les personnes</a>
		</li>
		<!--<li>
		<img src="<?php echo $IMGicones ?>user_star.png" alt="" /><a href="<?php echo $url_admin ?>gerer_inscriptions.php">les inscriptions</a>
		</li>-->
	</ul>


</div>
<!-- Fin gestion -->
