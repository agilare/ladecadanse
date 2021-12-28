<?php
namespace Ladecadanse;

use Ladecadanse\Collection;
use Ladecadanse\Description;

class CollectionDescription extends Collection {

	function __construct()
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

	function loadFiches($type = '', $region = null)
	{
		if ($type != '')
		{
			$type = " AND descriptionlieu.type='".$type."'";
		}
                
                $sql_region = '';
                if (!empty($region))
                    $sql_region = "and lieu.region='$region' ";
                
              
		$req = $this->connector->query("SELECT lieu.idLieu, lieu.nom, pseudo, contenu,
		descriptionlieu.dateAjout, photo1, groupe, personne.nom as nomAuteur, prenom, descriptionlieu.date_derniere_modif AS date_derniere_modif
		FROM descriptionlieu, lieu, personne WHERE descriptionlieu.idPersonne=personne.idPersonne AND
		descriptionlieu.idLieu=lieu.idLieu".$type." AND lieu.actif=1 AND lieu.statut='actif' ".$sql_region." ORDER BY descriptionlieu.dateAjout DESC LIMIT 6");


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
