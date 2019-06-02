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

require_once("Evenement.class.php");
require_once("Collection.class.php");

class CollectionEvenement extends Collection {

 /**
   * @access public
   */
	function __construct($connector)
	{

		$this->connector = $connector;
	}

	function loadLieu($idL, $date_debut, $genre = '')
	{
		$sql_genre = '';
		if ($genre != '')
		{
			$sql_genre = " AND genre='".$genre."'";
		}

		$sql = "SELECT idEvenement, idSalle, idPersonne, genre, titre, dateEvenement, nomLieu, description, flyer, image, horaire_debut, horaire_fin, horaire_complement, prix, dateAjout, statut
	 FROM evenement
	 WHERE idLieu=".$idL." AND dateEvenement >= '".$date_debut."' ".$sql_genre." AND statut NOT IN ('inactif', 'propose')
	 ORDER BY dateEvenement";

		$req = $this->connector->query($sql);

		if ($this->connector->getNumRows($req) == 0)
		{
		  	return false;
		}

		while ($tab = $this->connector->fetchArray($req))
		{
			$e = new Evenement();
			$e->setValues($tab);
			$e->setId($tab['idEvenement']);
			$this->elements[$e->getId()] = $e;
		}

		return true;
	}

	function loadOrganisateur($idO, $date_debut, $genre = '')
	{
		$sql_genre = '';
		if ($genre != '')
		{
			$sql_genre = " AND genre='".$genre."'";
		}

		$sql = "SELECT evenement.idEvenement, idSalle, evenement.idPersonne, genre, titre, dateEvenement,
	idLieu, nomLieu, description, flyer, image, horaire_debut, horaire_fin, horaire_complement, prix, dateAjout, statut
	 FROM evenement, evenement_organisateur
	 WHERE evenement.idEvenement=evenement_organisateur.idEvenement AND idOrganisateur=".$idO." AND dateEvenement >= '".$date_debut."' ".$sql_genre." AND statut NOT IN ('inactif', 'propose')
	 ORDER BY dateEvenement";

		$req = $this->connector->query($sql);

		if ($this->connector->getNumRows($req) == 0)
		{
		  	return false;
		}

		while ($tab = $this->connector->fetchArray($req))
		{
			$e = new Evenement();
			$e->setValues($tab);
			$e->setId($tab['idEvenement']);
			$this->elements[$e->getId()] = $e;
		}

		return true;
	}

}

?>