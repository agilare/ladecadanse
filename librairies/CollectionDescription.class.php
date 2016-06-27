<? /**/ ?>
<?php
/**
 * Lance la session et vérifie le login du visiteur
 *
 *
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see        SystemComponent.php
 */

require_once("Description.class.php");
require_once("Collection.class.php");

class CollectionDescription extends Collection {

   /**
 *
 * @var string
 */


 /**
 	 * Démarre la session et inclut un en-tête interdisant de stocker le mot
	 * de passe dans le cache de l'utilisateur
   * @access public
   */
	function CollectionDescription()
	{
		global $connector;
		$this->connector = $connector;
	}

	function load($idL)
	{
		$req = $this->connector->query("
		 SELECT descriptionlieu.idLieu AS idLieu, contenu, descriptionlieu.dateAjout AS dateAjout, pseudo, nom,
		 		 prenom, groupe, descriptionlieu.idPersonne AS idPersonne, descriptionlieu.date_derniere_modif
		 FROM descriptionlieu
		 INNER JOIN personne ON descriptionlieu.idPersonne = personne.idPersonne
		 WHERE descriptionlieu.idLieu =".$idL." ORDER BY descriptionlieu.dateAjout");

		if ($this->connector->getNumRows($req) == 0)
		{
		  	return false;
		}

		while ($tab = $this->connector->fetchArray($req))
		{
			$des = new Description();
			$des->setValues($tab);
			$id = $des->getValue('idPersonne').'_'.$des->getValue('idLieu');
			$this->elements[$id] = $des;
		}

		return true;
	}

	function loadByType($idL, $type)
	{
		$sql = "
		 SELECT descriptionlieu.idLieu AS idLieu, type, contenu, descriptionlieu.dateAjout AS dateAjout, pseudo, nom,
		 		 prenom, groupe, descriptionlieu.idPersonne AS idPersonne, descriptionlieu.date_derniere_modif
		 FROM descriptionlieu
		 INNER JOIN personne ON descriptionlieu.idPersonne = personne.idPersonne
		 WHERE descriptionlieu.idLieu =".$idL." AND type='".$type."' ORDER BY descriptionlieu.dateAjout";

		$req = $this->connector->query($sql);

		if ($this->connector->getNumRows($req) == 0)
		{
		  	return false;
		}

		while ($tab = $this->connector->fetchArray($req))
		{
			$des = new Description();
			$des->setValues($tab);
			$id = $des->getValue('idPersonne').'_'.$des->getValue('idLieu').'_'.$des->getValue('type');
			$this->elements[$id] = $des;
		}

		return true;
	}

	function loadFiches($type = '')
	{
		if ($type != '')
		{
			$type = " AND descriptionlieu.type='".$type."'";
		}
		$req = $this->connector->query("SELECT lieu.idLieu, lieu.nom, pseudo, contenu,
		descriptionlieu.dateAjout, photo1, groupe, personne.nom as nomAuteur, prenom, descriptionlieu.date_derniere_modif AS date_derniere_modif
		FROM descriptionlieu, lieu, personne WHERE descriptionlieu.idPersonne=personne.idPersonne AND
		descriptionlieu.idLieu=lieu.idLieu".$type." AND lieu.actif=1 AND lieu.statut='actif' ORDER BY descriptionlieu.dateAjout DESC LIMIT 6");


		if ($this->connector->getNumRows($req) == 0)
		{
		  	return false;
		}

		while ($tab = $this->connector->fetchArray($req))
		{
			$des = new Description();
			$des->setValues($tab);
			$id = $des->getValue('idLieu');
			$this->elements[$id] = $des;
		}

		return true;
	}

	function getNumRows($idL, $type = '')
	{
		 if ($type == 'description' || $type == 'presentation')
		 {
		 	$type = " AND type='".$type."'";
		 }
		$req = $this->connector->query("
		 SELECT descriptionlieu.idLieu AS idLieu, contenu, descriptionlieu.dateAjout AS dateAjout, pseudo, nom,
		 		 prenom, groupe, descriptionlieu.idPersonne AS idPersonne, descriptionlieu.date_derniere_modif
		 FROM descriptionlieu
		 INNER JOIN personne ON descriptionlieu.idPersonne = personne.idPersonne
		 WHERE descriptionlieu.idLieu =".$idL.$type." ORDER BY descriptionlieu.dateAjout");

		  	return $this->connector->getNumRows($req);


	}
}

?>