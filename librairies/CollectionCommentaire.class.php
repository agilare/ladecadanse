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

class CollectionCommentaire extends Collection
{

 /**
   * @access public
   */
	function CollectionCommentaire()
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