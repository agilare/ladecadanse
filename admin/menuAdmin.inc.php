<style>
#gestion ul li
{
	margin:0.4em 0;
}
</style>

<!-- Deb gestion -->
<div id="gestion">

	<h2>Gérer :</h2>

	<ul style="list-style-type:none">
		<li><a href="<?php echo $url_admin ?>gererEvenements.php">
		<img src="<?php echo $IMGicones ?>calendar.png" alt="" />les événements</a>
		</li>
                
                <?php if ($_SESSION['Sgroupe'] == 1) { ?>   
		<li>
		<img src="<?php echo $IMGicones ?>comment.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=commentaire">les commentaires</a>
                </li>
                
                <?php } ?>
                
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
                
                <?php if ($_SESSION['Sgroupe'] == 1) { ?>                
		<li>
		<img src="<?php echo $IMGicones ?>page_white.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=description">les descriptions</a>
		</li>
                
                
		<li>
		<img src="<?php echo $IMGicones ?>user.png" alt="" /><a href="<?php echo $url_admin ?>gerer.php?element=personne">les personnes</a>
		</li>
                <?php } ?>

	</ul>

    <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 1)) { ?>
    <h2>Ajouter :</h2>
    <ul>
        <li><a href="/ajouterPersonne.php?action=ajouter"><img src="/images/interface/icons/user_add.png" alt="" style="vertical-align:bottom" />une personne</a></li>
    </ul>
    <?php } ?>
</div>
<!-- Fin gestion -->
