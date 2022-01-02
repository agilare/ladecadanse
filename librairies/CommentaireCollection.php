<?php
namespace Ladecadanse;

use Ladecadanse\Collection;
use Ladecadanse\Commentaire;

class CommentaireCollection extends Collection
{

 /**
   * @access public
   */
	function __construct()
	{
		global $connector;
		$this->connector = $connector;
	}

	function load($idL)
	{
		$req = $this->connector->query("SELECT idPersonne, idCommentaire, contenu, dateAjout FROM commentaire
	WHERE id=".$idL." AND element='lieu' AND statut='actif' ORDER BY dateAjout ASC");

		if ($this->connector->getNumRows($req) == 0)
		{
		  	return false;
		}

		while ($tab = $this->connector->fetchArray($req))
		{
			$com = new Commentaire();
			$com->setValues($tab);
			$com->setId($tab['idCommentaire']);
			$id = $com->getId();
			$this->elements[$id] = $com;
		}

		return true;
	}


}

?>