<? /**/ ?>
<?php
		echo '<div id="fiche"';
		$req_nb_des = $connector->query("
		SELECT idPersonne FROM descriptionlieu WHERE descriptionlieu.idLieu=".$lieu['idLieu']);

		if ($connector->getNumRows($req_nb_des) == 0)
		{
			echo ' class="vide"';
		}
		echo '>';
	?>
	


		<!-- Deb medias -->
		<div id="medias">
		
			<div id="photo">
			
			<?php
			if (!empty($lieu['photo1']))
			{
				echo "<img src=\"images/lieux/s_".$lieu['photo1']."\" alt=\"image de ".$lieu['nom']."\" />";
			}
			?>
			
			</div>
			<div class="spacer"><!-- --></div>	

<?php

$sql_galerie = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=".$lieu['idLieu']." AND type='image' AND fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
 ORDER BY dateAjout DESC";

$req_galerie = $connector->query($sql_galerie);

$req_galerie = $connector->query($sql_galerie);

	if ($connector->getNumRows($req_galerie) > 0)
	{
			echo '<div class="section">
			<h3>Galerie</h3>';
		if ($connector->getNumRows($req_galerie) == 1)
		{
			$tab_galerie = $connector->fetchArray($req_galerie);
			$url_fichier = $url_images_lieu_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
			$rep_fichier = $rep_images_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
			$url_fichier_s = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
			$imgsize = getimagesize($rep_fichier);

			echo lien_popup($url_fichier, "images", $imgsize[0], $imgsize[1], "<img src=\"".$url_fichier_s."\" />");
		}
		else
		{
			while ($tab_galerie = $connector->fetchArray($req_galerie))
			{
				if (strstr($tab_galerie['mime'], "image"))
				{
					$icone_fichier = $iconeImage;
				}

				$chemin_fichier = $rep_images_lieux_galeries.$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];
				$url_fichier = $url_images_lieu_galeries."s_".$tab_galerie['idFichierrecu'].".".$tab_galerie['extension'];

				echo lien_popup($url_site."galerielieu.php?idL=".$lieu['idLieu']."&amp;idI=".$tab_galerie['idFichierrecu'], "galerie d\'images", 700, 500, "<img class=\"galerie\" src=\"".$url_fichier."\" />");
			}
		}
		echo '</div>
			<div class="spacer"></div>';
}


?>



			<?php

$sql_docu = "SELECT fichierrecu.idFichierrecu AS idFichierrecu, description, mime, extension
FROM fichierrecu, lieu_fichierrecu
WHERE lieu_fichierrecu.idLieu=".$lieu['idLieu']." AND type='document' AND
 fichierrecu.idFichierrecu=lieu_fichierrecu.idFichierrecu
 ORDER BY dateAjout DESC";

$req_docu = $connector->query($sql_docu);

	if ($connector->getNumRows($req_docu) > 0)
	{

		echo '<div class="section">
		<h3>Fichiers</h3>
		<ul>';


		while ($tab_docu = $connector->fetchArray($req_docu))
		{
			$chemin_fichier = $rep_fichiers_lieu.$tab_docu['idFichierrecu'].".".$tab_docu['extension'];
			$url_fichier = $url_fichiers_lieu.$tab_docu['idFichierrecu'].".".$tab_docu['extension'];
			echo "<li><a href=\"".$url_fichier."\" >".$icone[$tab_docu['extension']].$tab_docu['description']." (".formatbytes(filesize($chemin_fichier)).", ".$tab_docu['extension'].")</a></li>";
		}
		echo "</ul>
			</div>";
	}
?>


		</div>
		<!-- Fin medias -->
		<!-- Deb pratique -->
		<div id="pratique">

			<ul>
				<li><?php echo str_replace(",", ", ", $lieu['categorie']) ?></li>
				<li><?php echo $lieu['adresse'] ?> - <?php echo $lieu['quartier'] ?></li>
				<li><?php echo $lieu['acces_tpg'] ?></li>
				<li><?php echo $lieu['horaire_general'] ?></li>


			<?php
			$sql_even = "SELECT lieu.nom AS nom, idLieu2 
			FROM lieux_associes, lieu WHERE idLieu1=".$lieu['idLieu']." AND idLieu2=lieu.idLieu";


			$req_even = $connector->query($sql_even);
			if ($connector->getNumRows($req_even))
			{
				echo "<li id=\"lieux_lies\">Voir aussi :
					<ul>";
				while ($tab_des = $connector->fetchArray($req_even))
				{
					echo "<li><a href=\"".$url_site."lieu.php?idL=".$tab_des['idLieu2']."\" >".$tab_des['nom']."</a></li>";

				}
				echo "</ul>";
			}

			?>


	

	</ul>

</div>
<!-- Fin pratique -->

<div id="descriptions">
<?php
	/**
	* Recolte les descriptions
	*/
	$req_des = $connector->query("
	 SELECT contenu, descriptionlieu.dateAjout, pseudo, nom, prenom, groupe, descriptionlieu.idPersonne AS auteur, descriptionlieu.date_derniere_modif
	 FROM descriptionlieu
	 INNER JOIN personne ON descriptionlieu.idPersonne = personne.idPersonne
	 WHERE descriptionlieu.idLieu =".$lieu['idLieu']." ORDER BY descriptionlieu.dateAjout");

	if ($connector->getNumRows($req_des) > 0)
	{

		/**
		* Liste les descriptions du lieu
		*/
		while ($tab_des = $connector->fetchArray($req_des))
		{

		 ?>

			<div class="description">

			<p><?php echo textToHtml($tab_des['contenu']) ?></p>
				<p>

			<?php
				$signature_auteur = "";
				$sql_auteur = "SELECT pseudo, nom, prenom, signature, affiliation, avec_affiliation FROM personne WHERE idPersonne=".$tab_des['auteur']."";

				$req_auteur = $connector->query($sql_auteur);
				$tab_auteur = $connector->fetchArray($req_auteur);
				if ($tab_auteur['signature'] == 'pseudo')
				{
					$signature_auteur = $tab_auteur['pseudo'];
				}
				else if ($tab_auteur['signature'] == 'prenom')
				{
					$signature_auteur = $tab_auteur['prenom'];
				}
				else if ($tab_auteur['signature'] == 'nomcomplet')
				{
					$signature_auteur = $tab_auteur['prenom']." ".$tab_auteur['nom'];
				}

				if ($tab_auteur['avec_affiliation'] == 'oui')
				{
					$nom_affiliation = "";
					$req_aff = $connector->query("SELECT idAffiliation FROM affiliation WHERE
	 idPersonne=".$tab_des['auteur']." AND genre='lieu'");
					if (!empty($tab_auteur['affiliation']))
					{
						$nom_affiliation = $tab_auteur['affiliation'];
					}
					else if ($tab_aff = $connector->fetchArray($req_aff))
					{
						$req_lieu_aff = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab_aff['idAffiliation']);
						$tab_lieu_aff = $connector->fetchArray($req_lieu_aff);
						$nom_affiliation = $tab_lieu_aff['nom'];
					}

					$signature_auteur .= " (".$nom_affiliation.") ";
				}
				echo $signature_auteur;
			?>


				</p>

				<div class="auteur">
					<span class="left">Ajouté le <?php echo date_fr($tab_des['dateAjout'], 'annee') ?>
					<?php
					if ($tab_des['date_derniere_modif'] != "0000-00-00 00:00:00")
					{
						echo "Modifié le ".date_fr($tab_des['date_derniere_modif'], 'annee');
					}
					?>
					</span>
					<?php

					if ((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 4)
					|| (isset($_SESSION['SidPersonne'])) && $_SESSION['SidPersonne'] == $tab_des['auteur'])
					{
					?>
					<span class="right">
					<a href="<?php echo $url_site ?>ajouterDescription.php?action=editer&amp;idL=<?php echo $lieu['idLieu'] ?>&amp;idP=<?php echo $tab_des['auteur'] ?>"><?php echo $iconeEditer ?>Éditer</a></span>
					<?php
					}
					?>
				</div>
				<div class="spacer"><!-- --></div>
			</div>
			<!-- Fin description -->

	<?php
		}

	}
	else if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 8)
	{
		echo "<a href=\"".$url_site."ajouterDescription.php?idL=".$lieu['idLieu']."\" title=\"Ajouter une description de ce lieu\">".$iconeEditer." Ajouter une description</a>";
	}
		?>



		</div>
		<!-- Fin descriptions -->




	</div>
	<!-- Fin fiche -->