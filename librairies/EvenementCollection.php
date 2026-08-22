<?php
namespace Ladecadanse;

use Ladecadanse\Evenement;
use Ladecadanse\UserLevel;
use Ladecadanse\HtmlShrink;

class EvenementCollection
{

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
