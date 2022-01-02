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

    var $nom;
    var $firstTime;
    var $valeurs = array();
    var $fichiers = array();
    var $supprimer = array();
    var $supprimer_document = array();
    var $supprimer_galerie = array();
    var $erreurs = array();
    var $document_description;
    var $message;
    var $verif;
    var $action;
    var $connector;

    function __construct($nom, $champs, $fichiers)
    {
        global $connector;

        $this->connector = $connector;
        $this->nom = $nom;
        //$this->wizardPage = $wPage;

        $this->valeurs = $champs;
        $this->fichiers = $fichiers;

        $this->erreurs = array_merge($champs, $fichiers);
        $this->erreurs['nom_existant'] = '';
    }

    function traitement($post, $files)
    {
        parent::traitement($post, $files);

        $this->id = $post['idOrganisateur'];

        //TEST
        //echo "post:";
        //printr($post);
        //
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


//		echo "Réc:";
//		printr($this->valeurs);
        /* 		echo "réc.";
          echo "fichiers:";
          printr($this->fichiers); */
        if ($this->verification())
        {
            $this->enregistrer();
            return true;
        }
        else
        {
            return false;
        }

        //$GLOBALS['wizardPage'] = $this->NextWizardPage();
    }

    function IsCompleted()
    {
        return (!$this->FirstTime && count($this->Errors) <= 0);
    }

    function verification()
    {

        global $mimes_images_acceptes;

        $verif = new Validateur();

        $verif->valider($this->valeurs['nom'], "nom", "texte", 1, 80, 1);
        $verif->valider($this->valeurs['adresse'], "adresse", "texte", 1, 80, 0);
        $verif->valider($this->valeurs['telephone'], "telephone", "texte", 2, 80, 0);
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
        else
        {
            return false;
        }
    }

    function loadValeurs($id)
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
        global $rep_images_organisateurs;
        global $rep_templates;
        global $url_site;

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
                $organisateur->setValue('logo', $nouvel_id . '_logo' . strrchr($this->fichiers['logo']['name'], '.'));
            }

            if (!empty($this->fichiers['photo']['name']))
            {
                $organisateur->setValue('photo', $nouvel_id . '_photo' . strrchr($this->fichiers['logo']['name'], '.'));
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
                    unlink($rep_images_organisateurs . $organisateur->getValue('logo'));
                    unlink($rep_images_organisateurs . "s_" . $organisateur->getValue('logo'));

                    //echo "<div class=\"msg\">Ancienne image supprimée</div>";
                }

                $organisateur->setValue('logo', Document::getFilename($organisateur->getId(), 'logo', '', $this->fichiers['logo']['name']));
            }
            elseif (in_array('logo', $this->supprimer))
            {
                // suppression des fichiers de l'image, s'il elle est effectivement enregistrée
                if ($organisateur->getValue('logo') != '')
                {
                    unlink($rep_images_organisateurs . $organisateur->getValue('logo'));
                    unlink($rep_images_organisateurs . "s_" . $organisateur->getValue('logo'));
                }

                $organisateur->setValue('logo', '');
            }

            if ($this->fichiers['photo']['name'] != '')
            {
                // suppression des fichiers de l'ancienne image
                if ($organisateur->getValue('photo') != '')
                {
                    unlink($rep_images_organisateurs . $organisateur->getValue('photo'));
                    unlink($rep_images_organisateurs . "s_" . $organisateur->getValue('photo'));

                    //echo "<div class=\"msg\">Ancienne image supprimée</div>";
                }

                $organisateur->setValue('photo', Document::getFilename($organisateur->getId(), 'photo', '', $this->fichiers['photo']['name']));
            }
            /*
             * Si on a seulement choisi de supprimer l'image existante
             */
            else if (in_array('photo', $this->supprimer))
            {
                // suppression des fichiers de l'image, s'il elle est effectivement enregistrée
                if ($organisateur->getValue('photo') != '')
                {
                    unlink($rep_images_organisateurs . $organisateur->getValue('photo'));
                    unlink($rep_images_organisateurs . "s_" . $organisateur->getValue('photo'));
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


        /* 		$breve = $breve->getValues();
          include($rep_templates."breve.inc.php");

          foreach ($champs as $c)
          {
          $champs[$c] = "";
          } */
    }

    function loadValues($id)
    {
        $organisateur = new Organisateur();
        $organisateur->setId($id);
        $organisateur->load();
        $champs = $organisateur->getValues();

//		printr($champs);

        $this->valeurs = $champs;
    }

    function NextWizardPage()
    {
        
    }

    //abstract

    function Additional()
    {
        if ($this->wizardPage) :
            ?>
            <input type="Hidden" name="wizardPage" value="<?php echo $this->wizardPage ?>">
        <?php
        endif;
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

    function getNbErreurs()
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

    function ErrorReport($Name)
    {
        if (isset($this->Errors[$Name]))
            printf($this->ErrorMessageFormat, $this->Errors[$Name]);
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
?>