<?php
namespace Ladecadanse;

use Ladecadanse\Collection;
use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;

class EvenementCollection extends Collection {

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

    public static function deleteEvenement($get_idE)
    {

        global $connector;
        global $rep_images_even;
        global $rep_fichiers_even;
        //TESTER SI L'EVENEMENT EXISTE ENCORE

        if ((($authorization->estAuteur($_SESSION['SidPersonne'], $get_idE, "evenement") && $_SESSION['Sgroupe'] <= 6) || $_SESSION['Sgroupe'] < 2))
        {
            /*
             * Suppression du flyer
             */
            $req_im = $connector->query("SELECT titre, flyer, image, idLieu, genre, dateEvenement FROM evenement
        WHERE idEvenement=" . $get_idE);

            $val_even = $connector->fetchArray($req_im);
            $titreSup = $val_even['titre']; //pour le message apr?suppression

            if (!empty($val_even['flyer']))
            {
                unlink($rep_images_even . $val_even['flyer']);
                unlink($rep_images_even . "s_" . $val_even['flyer']);
            }

            if (!empty($val_even['image']))
            {
                unlink($rep_images_even . $val_even['image']);
                unlink($rep_images_even . "s_" . $val_even['image']);
            }

            $req_docu = $connector->query("SELECT * FROM fichierrecu
        WHERE idElement=" . $get_idE . " AND type_element='evenement' AND type='document'");

            while ($tab_docu = $connector->fetchArray($req_docu))
            {
                //printr($tab_docu);
                unlink($rep_fichiers_even . $tab_docu['idFichierrecu'] . "." . $tab_docu['extension']);
                $connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=" . $tab_docu['idFichierrecu']);
            }

            if ($connector->query("DELETE FROM evenement WHERE idEvenement=" . $get_idE))
            {
                HtmlShrink::msgOk('L\'événement "' . sanitizeForHtml($titreSup) . '" a été supprimé');
            }
            else
            {
                HtmlShrink::msgErreur("La requête DELETE a échoué");
            }
        }
        else
        {
            echo "Vous ne pouvez pas supprimer cet événement.";
        }
    }
}
