<?php

namespace Ladecadanse;

use Ladecadanse\Edition;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Organisateur;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\Document;
use Ladecadanse\HtmlShrink;

class OrganisateurEdition extends Edition
{

    public $firstTime;
    public $supprimer = [];
    public $supprimer_document = [];
    public $supprimer_galerie = [];
    public $erreurs = [];
    public $message;
    public $verif;
    public $action;
    public $connector;

    function __construct(public $nom, public $valeurs, public $fichiers)
    {
        global $connector;

        $this->connector = $connector;

        $this->erreurs = array_merge($this->valeurs, $this->fichiers);
        $this->erreurs['nom_existant'] = '';
    }

    function traitement(array $post, array $files): bool
    {
        parent::traitement($post, $files);

        $this->id = $post['idOrganisateur'];

        if (isset($post['logo_existant']))
        {
            $this->valeurs['logo'] = $post['logo_existant'];
        }
        else
        {
            $this->valeurs['logo'] = '';
        }

        if (isset($post['photo_existant']))
        {
            $this->valeurs['photo'] = $post['photo_existant'];
        }
        else
        {
            $this->valeurs['photo'] = '';
        }

        if (isset($post['supprimer']))
        {
            $this->supprimer = $post['supprimer'];
        }

        if ($this->verification())
        {
            $this->enregistrer();
            return true;
        }

        return false;
    }

    function verification(): bool
    {

        global $mimes_images_acceptes;

        $verif = new Validateur();

        $verif->valider($this->valeurs['nom'], "nom", "texte", 1, 80, 1);
        $verif->valider($this->valeurs['adresse'], "adresse", "texte", 1, 80, 0);
        $verif->valider($this->valeurs['URL'], "URL", "url", 2, 100, 0);
        $verif->valider($this->valeurs['email'], "email", "email", 4, 100, 0);
        $verif->valider($this->valeurs['presentation'], "presentation", "texte", 20, 10000, 0);
        $verif->validerFichier($this->fichiers['logo'], "logo", $mimes_images_acceptes, 0);
        $verif->validerFichier($this->fichiers['photo'], "photo", $mimes_images_acceptes, 0);

        /*
         * En cas d'ajout vérification si le lieu n'existe pas déjà
         */
        if ($this->action == 'insert')
        {
            $req = $this->connector->query("SELECT nom FROM organisateur WHERE statut='actif'");

            while ($tab = $this->connector->fetchArray($req))
            {
                //si un lieu a déjà le même nom
                if ($this->valeurs['nom'] != '' && $this->valeurs['nom'] == $tab['nom'])
                {
                    $verif->setErreur('nom_existant', "Le lieu s'appelant <em>" . $this->valeurs['nom'] . "</em> existe déjà.");
                }
            }
        } //if action==ajouter


        $this->erreurs = array_merge($this->erreurs, $verif->getErreurs());

        if ($verif->nbErreurs() == 0)
        {
            return true;
        }

        return false;
    }

    function loadValeurs(int $id): void
    {
        $organisateur = new Organisateur();
        $organisateur->setId($id);
        $organisateur->load();
        $this->id = $id;
        $this->valeurs = $organisateur->getValues();
//		printr($this->valeurs);
    }

    function enregistrer()
    {
        global $rep_uploads_organisateurs;
        global $rep_templates;


        $organisateur = new Organisateur();
        $organisateur->setValues($this->valeurs);

        $organisateur->setValue('idpersonne', $_SESSION['SidPersonne']);

//		echo "enreg:";
//		printr($lieu->getValues());

        if ($this->action == 'ajouter')
        {

            $nouvel_id = $organisateur->getMaxId() + 1;
            if (!empty($this->fichiers['logo']['name']))
            {
                $organisateur->setValue('logo', $nouvel_id . '_logo' . strrchr((string) $this->fichiers['logo']['name'], '.'));
            }

            if (!empty($this->fichiers['photo']['name']))
            {
                $organisateur->setValue('photo', $nouvel_id . '_photo' . strrchr((string) $this->fichiers['logo']['name'], '.'));
            }

            $organisateur->setValue('date_ajout', date("Y-m-d H:i:s"));
            $organisateur->setValue('date_derniere_modif', date("Y-m-d H:i:s"));

            /*
             * Insertion réussie, message OK, aperçu, et RAZ des champs
             */
            if ($organisateur->insert())
            {
                $this->id = $this->connector->getInsertId();
                $this->message = 'Organisateur ajouté';
            }
            else
            {
                HtmlShrink::msgErreur("Erreur lors de l'insertion dans la table");
                return false;
            }
        }
        else if ($this->action == 'editer')
        {
            $organisateur->setValue('date_derniere_modif', date("Y-m-d H:i:s"));
            //echo $this->id;
            $organisateur->setId($this->id);

            //echo "<p>supprimer :</p>";
            //TEST
            //printr($this->supprimer);
            //
            //echo 'logo value :'.$organisateur->getValue('logo');

            if ($this->fichiers['logo']['name'] != '')
            {
                // suppression des fichiers de l'ancienne image
                if ($organisateur->getValue('logo') != '')
                {
                    unlink($rep_uploads_organisateurs . $organisateur->getValue('logo'));
                    unlink($rep_uploads_organisateurs . "s_" . $organisateur->getValue('logo'));

                    //echo "<div class=\"msg\">Ancienne image supprimée</div>";
                }

                $organisateur->setValue('logo', Document::getFilename($this->fichiers['logo']['name'], $organisateur->getId(), 'logo', ''));
            }
            elseif (in_array('logo', $this->supprimer))
            {
                // suppression des fichiers de l'image, s'il elle est effectivement enregistrée
                if ($organisateur->getValue('logo') != '')
                {
                    unlink($rep_uploads_organisateurs . $organisateur->getValue('logo'));
                    unlink($rep_uploads_organisateurs . "s_" . $organisateur->getValue('logo'));
                }

                $organisateur->setValue('logo', '');
            }

            if ($this->fichiers['photo']['name'] != '')
            {
                // suppression des fichiers de l'ancienne image
                if ($organisateur->getValue('photo') != '')
                {
                    unlink($rep_uploads_organisateurs . $organisateur->getValue('photo'));
                    unlink($rep_uploads_organisateurs . "s_" . $organisateur->getValue('photo'));

                    //echo "<div class=\"msg\">Ancienne image supprimée</div>";
                }

                $organisateur->setValue('photo', Document::getFilename($this->fichiers['photo']['name'], $organisateur->getId(), 'photo', ''));
            }
            /*
             * Si on a seulement choisi de supprimer l'image existante
             */
            else if (in_array('photo', $this->supprimer))
            {
                // suppression des fichiers de l'image, s'il elle est effectivement enregistrée
                if ($organisateur->getValue('photo') != '')
                {
                    unlink($rep_uploads_organisateurs . $organisateur->getValue('photo'));
                    unlink($rep_uploads_organisateurs . "s_" . $organisateur->getValue('photo'));
                }

                $organisateur->setValue('photo', '');
            }


            /* echo "avant update:";
              printr($organisateur->getValues()); */

            if ($organisateur->update())
            {
                $this->message = 'Organisateur modifié';
                $action_terminee = true;
            }
            else
            {
                HtmlShrink::msgErreur("Erreur lors de la mise à jour de la table");
            }
        }

        /*
         * TRAITEMENT DES FICHIERS UPLOADES
         */
        //echo "f:";
//		printr($this->fichiers);
        if (!empty($this->fichiers['logo']['name']))
        {
            //echo "ok img";
            $imD2 = new ImageDriver2("organisateurs");

            if (!$imD2->processImage($this->fichiers['logo'], "s_" . $organisateur->getValue('logo'), 200, 100, 'h', 0))
            {
                trigger_error($imD2->getErreur());
                exit;
            }

            if (!$imD2->processImage($this->fichiers['logo'], $organisateur->getValue('logo'), 500, 500, '', 0))
            {
                trigger_error($imD2->getErreur());
                exit;
            }
        }

        if (!empty($this->fichiers['photo']['name']))
        {
            //echo "ok img";
            $imD2 = new ImageDriver2("organisateurs");

            if (!$imD2->processImage($this->fichiers['photo'], "s_" . $organisateur->getValue('photo'), 200, 400, 'w', 1))
            {
                trigger_error($imD2->getErreur());
                exit;
            }

            if (!$imD2->processImage($this->fichiers['photo'], $organisateur->getValue('photo'), 600, 600, '', 0))
            {
                trigger_error($imD2->getErreur());
                exit;
            }
        }
    }

    function loadValues($id)
    {
        $organisateur = new Organisateur();
        $organisateur->setId($id);
        $organisateur->load();
        $champs = $organisateur->getValues();

        $this->valeurs = $champs;
    }

    function NextWizardPage()
    {

    }

    function Set($Name, $Value)
    {
        $this->$Name = $Value;
    }

    function getErreur($champ)
    {
        $erreur = $this->erreurs[$champ];
        return $erreur;
    }

    function getNbErreurs(): int
    {
        return count($this->erreurs);
    }

    function getHtmlErreur($champ)
    {
        if ($this->erreurs[$champ] != '')
        {
            return '<div class="msg">' . $this->erreurs[$champ] . '</div>';
        }
    }

    function GetInitialValue($Name)
    {
        if (isset($this->Values[$Name]))
            return $this->Values[$Name];
        else
            return false;
    }

    function InitialValue($Name)
    {
        echo $this->GetInitialValue($Name);
    }

    function setAction($action)
    {
        $this->action = $action;
    }

    function getAction()
    {
        return $this->action;
    }

    function setMessage($message)
    {
        $this->message = $message;
    }

    function getMessage()
    {
        return $this->message;
    }

    function getSupprimer()
    {
        return $this->supprimer;
    }

    function setSupprimer($sup)
    {
        $this->supprimer = $sup;
    }

}
