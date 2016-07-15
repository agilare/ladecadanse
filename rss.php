<?php if (is_file("config/reglages.php"))
{
	require_once("config/reglages.php");
}



$tab_types = array("breves", "evenements_auj", "lieu_evenements", "evenement_commentaires", "lieux_descriptions", 'organisateur_evenements', 'evenements_ajoutes');
$get['type'] = "breves";

if (isset($_GET['type']))
{
	$get['type'] =  verif_get($_GET['type'], "enum", 1, $tab_types);
}

$get['id'] = '';
if (isset($_GET['id']))
{
	$get['id'] =  verif_get($_GET['id'], "int", 1);
}

$xml = '<?xml version="1.0" encoding="utf-8" ?><rss version="2.0">';
$xml .= "<channel>";

$items = "";

if ($get['type'] == "breves")
{
	$der_dateAjout = time();

	$channel = '<title>La décadanse : brèves</title>';
	$channel .= '<link></link>';
	$channel .= '<description>Brèves publiées sur le site, actualité culturelle à Genève</description>';
	$channel .= "<pubDate>".date("r",  mktime(0, 0, 0, date("m")  , date("d"), date("Y")))."</pubDate>\n";
	$channel .= "<language>fr</language>\n";

	$items = "";

	$css = "<style>img { border:none; } </style>";

	$req_breves = $connector->query("
	SELECT idBreve, titre, contenu, img_breve, idPersonne, dateAjout FROM breve
	WHERE
	statut='actif'
	AND (
	(date_debut <= '".$glo_auj."' AND date_fin >= '".$glo_auj."') OR (date_debut='0000-00-00' AND date_fin='0000-00-00')
	OR (date_debut <= '".$glo_auj."' AND date_fin='0000-00-00') OR (date_fin >= '".$glo_auj."' AND date_fin='0000-00-00')
	)
	 ORDER BY dateAjout DESC");


	$dateAvant = '';

	while($tab_breve = $connector->fetchArray($req_breves))
	{

		$req_pers = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_breve['idPersonne']);
		$tab_pers = $connector->fetchArray($req_pers);

		$der_dateAjout = $tab_breve['dateAjout'];
		

		$items .= "<item>\n";
		$items .= "<title>".htmlspecialchars($tab_breve['titre'])."</title>\n";
		$items .= "<link>".$url_site."breves.php</link>\n";
		$items .= "<description><![CDATA[";
		$items .= $css;
		if (!empty($tab_breve['img_breve']))
		{
			//$imgInfo = @getimagesize($rep_images."breves/".$tab_breve['img_breve']);
			$items .= "<div style=\"float:left\">
			<a href=\"".$url_site."breves.php\" title=\"Brève\"><img src=\"".$IMGbreves."s_".$tab_breve['img_breve']."\" alt=\"image pour ".$tab_breve['titre']."\" /></a>
			</div>";
		}

		$items .= "<p>".textToHtml(htmlspecialchars($tab_breve['contenu']))."</p>";
		$items .= "<div style=\"clear:both\"></div>]]></description>\n";
		$items .= "<author>".$tab_pers['pseudo']."</author>\n";
		$items .= "<pubDate>".date("r", datetime_iso2time($tab_breve['dateAjout']))."</pubDate>\n";
		$items .= "<guid isPermaLink=\"false\">".$tab_breve['idBreve']."</guid>\n";
		//$items .= "<enclosure url=\"".$IMGbreves.$tab_breve['img_breve']."\" type="image/jpeg" length="2441"></enclosure>
		$items .= "</item>\n";

	}

}
else if ($get['type'] == "evenements_auj")
{

	$genres_c = array("fête", "cinéma", "théâtre", "expos", "divers");

	$channel = '<title>La décadanse : événements du jour</title>';
	$channel .= '<link>'.$url_site.'</link>';
	$channel .= '<description>Événements quotidiens, actualité culturelle à Genève</description>';
	$channel .= '<ttl>1440</ttl>';
	$channel .= "<pubDate>".date("r",  mktime(0, 0, 0, date("m")  , date("d") , date("Y")))."</pubDate>\n";
	$channel .= "<language>fr</language>\n";
	$der_dateAjout = time();
	$items = "";
	$sem_derniere = time() - (7 * 24 * 60 * 60);

	$sql = "SELECT idEvenement, idLieu, idSalle, genre, nomLieu, adresse, quartier, urlLieu,
	 titre, idPersonne, dateEvenement, URL1, ref, flyer, description, horaire_debut, horaire_fin, horaire_complement,
	 prix, prelocations, dateAjout, date_derniere_modif
	 FROM evenement
	 WHERE dateEvenement='".$glo_auj_6h."' AND statut!='inactif'
	 ORDER BY CASE `genre`
       WHEN 'fête' THEN 1
       WHEN 'cinéma' THEN 2
       WHEN 'théâtre' THEN 3
       WHEN 'expos' THEN 4
       WHEN 'divers' THEN 5 END, dateAjout DESC";
	
	$req_even = $connector->query($sql);


	$prec_genre = "";
	$prec_date = "";

	while($tab_even = $connector->fetchArray($req_even))
	{
		$items .= "<item>\n";

		$genre_even = $tab_even['genre'];
		if ($tab_even['genre'] == "fête")
		{
			$genre_even = "fêtes";
		}
		else if ($tab_even['genre'] == "cinéma")
		{
			$genre_even = "ciné";
		}
		$items .= "<title>".ucfirst(date_fr($tab_even['dateEvenement'], "", "", "", false))." - ".$genre_even." : ".securise_string($tab_even['titre'])."</title>\n";
		$items .= "<link>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."</link>\n";
		$items .= "<comments>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."#commentaires</comments>\n";
		$items .= "<description><![CDATA[";

		if ($tab_even['idLieu'] != 0)
		{
			$nom_lieu = "<a href=\"".$url_site."lieu.php?idL=".$tab_even['idLieu']."\"
			title=\"Voir la fiche du lieu : ".securise_string($tab_even['nomLieu'])."\" >
			".securise_string($tab_even['nomLieu'])."</a>";

			if ($tab_even['idSalle'])
			{
				$sql_salle = "SELECT nom FROM salle WHERE idSalle=".$tab_even['idSalle'];
				$req = $connector->query($sql_salle);
				$tab = $connector->fetchArray($req);
				$nom_lieu .= " - ".$tab['nom'];
			}
		}
		else
		{
			$nom_lieu = securise_string($tab_even['nomLieu']);
		}

		$items .= '<h3>'.$nom_lieu.'</h3>';
			//si un flyer existe
		if (!empty($tab_even['flyer']))
		{
			$items .= "<div class=\"flyer\"><img src=\"".$IMGeven."s_".$tab_even['flyer']."\"  alt=\"Flyer\" /></div>";
		}

		$maxChar = trouveMaxChar($tab_even['description'], 60, 5);
		if (mb_strlen($tab_even['description']) > $maxChar)
		{
			$items .= texteHtmlReduit(textToHtml(securise_string($tab_even['description'])), $maxChar, "");
		}
		else
		{
			$items .= textToHtml(securise_string($tab_even['description']));
		}


		$items .= "<p>".afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])."
		".securise_string($tab_even['horaire_complement'])."</p>";
		$items .= "<p>".securise_string($tab_even['prix'])."</p>";

		$items .= "]]></description>\n";
		//$items .= "<enclosure url=\"".$IMGeven.$tab_even['flyer']."\" type="image/jpeg" length="2441"></enclosure>
		$items .= "<guid isPermaLink=\"false\">".$tab_even['idEvenement']."</guid>\n";
		$items .= "<pubDate>".date("r")."</pubDate>\n";
		$items .= "</item>\n";
	
		$der_dateAjout = date_iso2time($tab_even['dateAjout']);
		
	} //while

	//$channel .= "<pubDate>".date("r")."</pubDate>\n";
}
else if ($get['type'] == "lieu_evenements")
{
	$req_lieu = $connector->query("SELECT nom, determinant FROM lieu WHERE idLieu=".$get['id']);
	$tab_lieu = $connector->fetchArray($req_lieu);

	$channel = '<title>La décadanse : Événements '.$tab_lieu['determinant'].' '.$tab_lieu['nom'].'</title>';
	$channel .= '<link>'.$url_site.'lieu.php?idL='.$get['id'].'</link>';
	$channel .= '<description>Événements '.$tab_lieu['determinant'].' '.$tab_lieu['nom'].'</description>';
	//$channel .= "<pubDate>".date("r",  mktime(0, 0, 0, date("m")  , date("d") - 7, date("Y")))."</pubDate>\n";
	$channel .= "<language>fr</language>\n";

	$der_dateAjout = time();

	$req_even = $connector->query("SELECT idEvenement, idPersonne, idSalle, genre, titre, dateEvenement,
		 nomLieu, description, flyer, horaire_debut, horaire_fin, horaire_complement, prix, dateAjout
		 FROM evenement WHERE idLieu=".$get['id']." AND dateEvenement >= '".$auj."' AND statut!='inactif' ORDER BY dateAjout DESC");

	$items = "";
	$css = "<style> .flyer { float:left; } h1, h2, h3, h4, p { margin: 1em 0.1em 0.6em 0.1em} .desc { margin: 0.4em 0.1em } .clean {clear:both;}</style>";


	while ($tab_even = $connector->fetchArray($req_even))
	{

		$items .= "<item>\n";
		$items .= "<title>".securise_string($tab_even['titre'])."</title>\n";
		$items .= "<link>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."</link>\n";
		$items .= "<comments>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."#commentaires</comments>\n";
		$items .= "<description><![CDATA[";
		$items .= $css;
		 $items .= '<h2 style="padding:0.2em 0.1em;border-bottom:1px solid #aeaeae;">'.date_fr($tab_even['dateEvenement']).'</h2>';

		 $items .= "<h3>".$tab_even['genre']."</h3>";

		if ($tab_even['idSalle'])
		{
			$sql_salle = "SELECT nom FROM salle WHERE idSalle=".$tab_even['idSalle'];
			$req = $connector->query($sql_salle);
			$tab = $connector->fetchArray($req);
			$items .= "<h4>".$tab['nom']."</h4>";
		}
			//si un flyer existe
		if (!empty($tab_even['flyer']))
		{
			$items .= "<div class=\"flyer\"><img src=\"".$IMGeven."s_".$tab_even['flyer']."\"  alt=\"Flyer\" /></div>";
		}

		$maxChar = trouveMaxChar($tab_even['description'], 60, 5);
		if (mb_strlen($tab_even['description']) > $maxChar)
		{
			$items .= texteHtmlReduit(textToHtml(htmlspecialchars($tab_even['description'])), $maxChar, "");
		}
		else
		{
			$items .= textToHtml(htmlspecialchars($tab_even['description']));
		}


		$items .= "<p>".afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])." ".securise_string($tab_even['horaire_complement'])."</p>";
		$items .= "<p>".securise_string($tab_even['prix'])."</p>";

		$items .= "]]></description>\n";
		//$items .= "<enclosure url=\"".$IMGeven.$tab_even['flyer']."\" type="image/jpeg" length="2441"></enclosure>
		$items .= "<guid isPermaLink=\"false\">".$tab_even['idEvenement']."</guid>\n";
		$items .= "<pubDate>".date("r", datetime_iso2time($tab_even['dateAjout']))."</pubDate>\n";
		$items .= "</item>\n";


		$der_dateAjout = date_iso2time($tab_even['dateAjout']);
		
	}
	
	$channel .= "<pubDate>".date("r", $der_dateAjout)."</pubDate>\n";
}
else if ($get['type'] == "evenement_commentaires")
{

	$req_even = $connector->query("SELECT titre FROM evenement WHERE idEvenement=".$get['id']);

	$tab_even = $connector->fetchArray($req_even);

	$channel = '<title>La décadanse : commentaires pour '.$tab_even['titre'].'</title>';
	$channel .= '<link>'.$url_site.'evenement.php?idE='.$get['id'].'</link>';
	$channel .= '<description>Commentaires de l\'événement '.$tab_even['titre'].'</description>';
	$channel .= "<pubDate>".date("r",  mktime(0, 0, 0, date("m")  , date("d") - 7, date("Y")))."</pubDate>\n";
	$channel .= "<language>fr</language>\n";

	$der_dateAjout = time();

	$req_comm = $connector->query("SELECT idPersonne, idCommentaire, id AS idEvenement, contenu, dateAjout FROM commentaire WHERE id=".$get['id']." AND element='evenement' ORDER BY dateAjout ASC");


	while($tab_comm = $connector->fetchArray($req_comm))
	{

		$req_pers = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_comm['idPersonne']);
		$tab_pers = $connector->fetchArray($req_pers);

		$items .= "<item>\n";
		$items .= "<title>".mb_substr($tab_comm['contenu'], 0, 20)."</title>\n";
		$items .= "<link>".$url_site."evenement.php?idE=".$tab_comm['idEvenement']."</link>\n";

		$items .= "<description><![CDATA[";
		$items .= textToHtml(htmlspecialchars($tab_comm['contenu']));
		$items .= "]]></description>\n";
		$items .= "<author>".$tab_pers['pseudo']."</author>";
		$items .= "<guid isPermaLink=\"false\">".$tab_comm['idCommentaire']."</guid>\n";
		$items .= "<pubDate>".date("r", datetime_iso2time($tab_comm['dateAjout']))."</pubDate>\n";
		$items .= "</item>\n";

	
		$der_dateAjout = date_iso2time($tab_comm['dateAjout']);
		
	}

	$channel .= "<lastBuildDate>".date("r", $der_dateAjout)."</lastBuildDate>\n";


}
else if ($get['type'] == "lieux_descriptions")
{

	$channel = '<title>La décadanse : dernières descriptions de lieux</title>';
	$channel .= '<link>'.$url_site.'lieux.php</link>';
	$channel .= '<description>Dernières descriptions d\'événements</description>';
	$channel .= "<pubDate>".date("r",  mktime(0, 0, 0, date("m")  , date("d") - 7, date("Y")))."</pubDate>\n";
	$channel .= "<language>fr</language>\n";

	$der_dateAjout = time();

	$req = $connector->query("SELECT * FROM descriptionlieu WHERE type='description' ORDER BY dateAjout DESC");


	while($tab = $connector->fetchArray($req))
	{

		$req_pers = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab['idPersonne']);
		$tab_pers = $connector->fetchArray($req_pers);

		$req_lieu = $connector->query("SELECT nom FROM lieu WHERE idLieu=".$tab['idLieu']);
		$tab_lieu = $connector->fetchArray($req_lieu);

		$items .= "<item>\n";
		$items .= "<title>".$tab_lieu['nom']."</title>\n";
		$items .= "<link>".$url_site."lieu.php?idL=".$tab['idLieu']."</link>\n";

		$items .= "<description><![CDATA[";
		$items .= textToHtml(htmlspecialchars($tab['contenu']));
		$items .= "]]></description>\n";
		$items .= "<author>".$tab_pers['pseudo']."</author>";
		$items .= "<pubDate>".date("r", datetime_iso2time($tab['dateAjout']))."</pubDate>\n";
		$items .= "</item>\n";


		$der_dateAjout = date_iso2time($tab['dateAjout']);
	
	}

	$channel .= "<lastBuildDate>".date("r", $der_dateAjout)."</lastBuildDate>\n";


}
else if ($get['type'] == "organisateur_evenements")
{
	$req = $connector->query("SELECT nom FROM organisateur WHERE idOrganisateur=".$get['id']);
	$tab = $connector->fetchArray($req);

	$channel = '<title>La décadanse : événements pour '.$tab['nom'].'</title>';
	$channel .= '<link>'.$url_site.'organisateur.php?idO='.$get['id'].'</link>';
	$channel .= '<description>'.$tab['nom'].'</description>';
	$channel .= "<language>fr</language>\n";

	$der_dateAjout = time();

	$req_even = $connector->query("SELECT evenement.idEvenement AS idEvenement, idPersonne, idLieu, nomLieu, adresse, quartier, idSalle, genre, titre, dateEvenement,
		 nomLieu, description, flyer, horaire_debut, horaire_fin, horaire_complement, prix, dateAjout
		 FROM evenement, evenement_organisateur WHERE evenement.idEvenement=evenement_organisateur.idEvenement AND idOrganisateur=".$get['id']." AND dateEvenement >= '".$auj."' AND statut!='inactif' ORDER BY dateAjout DESC");

	$items = "";
	$css = "<style> .flyer { float:left; } h1, h2, h3, h4, p { margin: 1em 0.1em 0.6em 0.1em} .desc { margin: 0.4em 0.1em } .clean {clear:both;}</style>";


	while ($tab_even = $connector->fetchArray($req_even))
	{

		$items .= "<item>\n";
		$items .= "<title>".securise_string($tab_even['titre'])."</title>\n";
		$items .= "<link>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."</link>\n";
		$items .= "<comments>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."#commentaires</comments>\n";
		$items .= "<description><![CDATA[";
		$items .= $css;
		 $items .= '<h2 style="padding:0.2em 0.1em;border-bottom:1px solid #aeaeae;">'.date_fr($tab_even['dateEvenement']).'</h2>';

		 $items .= "<h3>".$tab_even['genre']."</h3>";

		if ($tab_even['idLieu'] != 0)
		{
			$nom_lieu = "<a href=\"".$url_site."lieu.php?idL=".$tab_even['idLieu']."\"
			title=\"Voir la fiche du lieu : ".securise_string($tab_even['nomLieu'])."\" >
			".securise_string($tab_even['nomLieu'])."</a>";

			if ($tab_even['idSalle'])
			{
				$sql_salle = "SELECT nom FROM salle WHERE idSalle=".$tab_even['idSalle'];
				$req = $connector->query($sql_salle);
				$tab = $connector->fetchArray($req);
				$nom_lieu .= " - ".$tab['nom'];
			}
		}
		else
		{
			$nom_lieu = securise_string($tab_even['nomLieu']);
		}

		$items .= $nom_lieu;
			//si un flyer existe
		if (!empty($tab_even['flyer']))
		{
			$items .= "<div class=\"flyer\"><img src=\"".$IMGeven."s_".$tab_even['flyer']."\"  alt=\"Flyer\" /></div>";
		}

		$maxChar = trouveMaxChar($tab_even['description'], 60, 5);
		if (mb_strlen($tab_even['description']) > $maxChar)
		{
			$items .= texteHtmlReduit(textToHtml(htmlspecialchars($tab_even['description'])), $maxChar, "");
		}
		else
		{
			$items .= textToHtml(htmlspecialchars($tab_even['description']));
		}


		$items .= "<p>".afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])." ".securise_string($tab_even['horaire_complement'])."</p>";
		$items .= "<p>".securise_string($tab_even['prix'])."</p>";

		$items .= "]]></description>\n";
		//$items .= "<enclosure url=\"".$IMGeven.$tab_even['flyer']."\" type="image/jpeg" length="2441"></enclosure>
		$items .= "<guid isPermaLink=\"false\">".$tab_even['idEvenement']."</guid>\n";
		$items .= "<pubDate>".date("r", datetime_iso2time($tab_even['dateAjout']))."</pubDate>\n";
		$items .= "</item>\n";

		$der_dateAjout = datetime_iso2time($tab_even['dateAjout']);
	}
	$channel .= "<pubDate>".date("r", $der_dateAjout)."</pubDate>\n";
}
else if ($get['type'] == "evenements_ajoutes")
{

	$channel = '<title>La décadanse : derniers événements ajoutés</title>';
	$channel .= '<link>'.$url_site.'</link>';
	$channel .= '<description>Derniers événements ajoutés</description>';
	$channel .= "<pubDate>".date("r")."</pubDate>\n";
	$channel .= "<language>fr</language>\n";

	
	$der_dateAjout = time();

	$req_even = $connector->query("SELECT idEvenement, idPersonne, idSalle, genre, titre, dateEvenement,
		 idLieu, nomLieu, adresse, quartier, description, flyer, image, horaire_debut, horaire_fin, horaire_complement, prix, dateAjout
		 FROM evenement WHERE statut!='inactif' ORDER BY dateAjout DESC LIMIT 0, 20");

	$items = "";
	$css = "<style> .flyer { float:left; } h1, h2, h3, h4, p { margin: 1em 0.1em 0.6em 0.1em} .desc { margin: 0.4em 0.1em } .clean {clear:both;}</style>";


	while ($tab_even = $connector->fetchArray($req_even))
	{

		$items .= "<item>\n";
		$items .= "<title>".securise_string($tab_even['titre'])."</title>\n";
		$items .= "<link>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."</link>\n";
		$items .= "<comments>".$url_site."evenement.php?idE=".$tab_even['idEvenement']."#commentaires</comments>\n";
		$items .= "<description><![CDATA[";
		$items .= $css;
		 $items .= '<h2 style="padding:0.1em 0.1em;border-bottom:1px dotted #aeaeae;">'.ucfirst(date_fr($tab_even['dateEvenement'], 'annee')).'</h2>';

		 
	
		if ($tab_even['idLieu'] != 0)
		{
			$nom_lieu = "<a href=\"".$url_site."lieu.php?idL=".$tab_even['idLieu']."\"
			title=\"Voir la fiche du lieu : ".securise_string($tab_even['nomLieu'])."\" >
			".securise_string($tab_even['nomLieu'])."</a>";

			if ($tab_even['idSalle'])
			{
				$sql_salle = "SELECT nom FROM salle WHERE idSalle=".$tab_even['idSalle'];
				$req = $connector->query($sql_salle);
				$tab = $connector->fetchArray($req);
				$nom_lieu .= " - ".$tab['nom'];
			}
		}
		else
		{
			$nom_lieu = securise_string($tab_even['nomLieu']);
		}
		
		$items .= "<h3>".$nom_lieu."</h3>";
		$items .= "<p>".$tab_even['adresse']." - ".$tab_even['quartier']."</p>";
		$items .= "<h4>".ucfirst($glo_tab_genre[$tab_even['genre']])."</h4>";
		
			//si un flyer existe
		if (!empty($tab_even['flyer']))
		{
			$items .= "<div class=\"flyer\"><img src=\"".$IMGeven."s_".$tab_even['flyer']."\"  alt=\"Flyer\" /></div>";
		}
		else if (!empty($tab_even['image']))
		{
			$items .= "<div class=\"flyer\"><img src=\"".$IMGeven."s_".$tab_even['image']."\"  alt=\"Photo\" /></div>";

		}



		$items .= "<div class=\"desc\">";
		$maxChar = trouveMaxChar($tab_even['description'], 60, 8);
		if (mb_strlen($tab_even['description']) > $maxChar)
		{
			$items .= texteHtmlReduit(textToHtml(htmlspecialchars($tab_even['description'])), $maxChar, "");
		}
		else
		{
			$items .= textToHtml(htmlspecialchars($tab_even['description']));
		}
		$items .= "</div>";

		$items .= "<p>".afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement'])." ".securise_string($tab_even['horaire_complement'])."</p>";
		$items .= "<p>".securise_string($tab_even['prix'])."</p>";

		$items .= "]]></description>\n";
		//$items .= "<enclosure url=\"".$IMGeven.$tab_even['flyer']."\" type="image/jpeg" length="2441"></enclosure>
		$items .= "<guid isPermaLink=\"false\">".$tab_even['idEvenement']."</guid>\n";
		$items .= "<pubDate>".date("r", datetime_iso2time($tab_even['dateAjout']))."</pubDate>\n";
		$items .= "</item>\n";


			
		$der_dateAjout = datetime_iso2time($tab_even['dateAjout']);
	
	}
	
}



header('Content-Disposition: inline; filename='.$get['type'].'.xml');



header('Content-Type: text/xml');
echo $xml.$channel.$items."</channel></rss>";

?>
