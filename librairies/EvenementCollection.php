<?php
namespace Ladecadanse;

use Ladecadanse\Collection;
use Ladecadanse\Evenement;
use Ladecadanse\UserLevel;
use Ladecadanse\HtmlShrink;

class EvenementCollection extends Collection
{

    function __construct($connector)
	{
        parent::__construct();
    }

	function loadLieu(int $idL, $date_debut, $genre = ''): bool
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

	function loadOrganisateur(int $idO, $date_debut, $genre = ''): bool
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

    public static function deleteEvenement(int $get_idE): void
    {
        global $authorization;
        global $connector;

        if ((($authorization->isAuthor("evenement", $_SESSION['SidPersonne'], $get_idE) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR) || $_SESSION['Sgroupe'] == UserLevel::SUPERADMIN))
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
                Evenement::rmImageAndItsMiniature($val_even['flyer']);
            }

            if (!empty($val_even['image']))
            {
                Evenement::rmImageAndItsMiniature($val_even['image']);
            }

            if ($connector->query("DELETE FROM evenement WHERE idEvenement=" . $get_idE))
            {
                HtmlShrink::msgOk('L\'événement "' . sanitizeForHtml($titreSup) . '" a été supprimé');
            }
        }
        else
        {
            echo "Vous ne pouvez pas supprimer cet événement.";
        }
    }
}
