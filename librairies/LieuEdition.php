<?php

namespace Ladecadanse;

use Ladecadanse\Edition;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Lieu;
use Ladecadanse\Utils\ImageDriver2;
use Ladecadanse\Utils\Text;
use Ladecadanse\Document;
use Ladecadanse\HtmlShrink;

class LieuEdition extends Edition
{

    public $supprimer = [];
    public $supprimer_document = [];
    public $supprimer_galerie = [];
    public $supprimer_organisateur = [];
    public $erreurs = [];
    public $organisateurs = [];
    public $message;
    public $action;
    public $connector;

    function __construct(public $nom, public $valeurs, public $fichiers)
    {
        global $connector;

        $this->connector = $connector;
        $this->valeurs['categorie'] = [];

        $this->erreurs = array_merge($this->valeurs, $this->fichiers);
        $this->erreurs['nom_existant'] = '';
        $this->erreurs['doublon_organisateur'] = '';
    }

    #[\Override]
    function traitement(array $post, array $files)
    {
        parent::traitement($post, $files);

        unset($this->valeurs['organisateurs']);
        $this->id = $post['idLieu'];

        if (isset($post['organisateurs']))
            $this->organisateurs = $post['organisateurs'];

        if (!empty($post['categorie']))
            $this->valeurs['categorie'] = $post['categorie'];

        if (isset($post['logo_existant']))
        {
            $this->valeurs['logo'] = $post['logo_existant'];
        }
        else
        {
            $this->valeurs['logo'] = '';
        }

        if (isset($post['photo1_existant']))
        {
            $this->valeurs['photo1'] = $post['photo1_existant'];
        }
        else
        {
            $this->valeurs['photo1'] = '';
        }

        if (isset($post['supprimer']))
        {
            $this->supprimer = $post['supprimer'];
        }


        if (isset($post['supprimer_document']))
        {
            $this->supprimer_document = $post['supprimer_document'];
        }

        if (isset($post['supprimer_galerie']))
        {
            $this->supprimer_galerie = $post['supprimer_galerie'];
        }

        if ($this->verification())
        {
            $this->enregistrer();
            return true;
        }
        else
        {
            return false;
        }
    }

    #[\Override]
    function verification(): bool
    {
        global $glo_tab_quartiers;
        global $glo_regions;
        global $glo_categories_lieux;
        global $mimes_images_acceptes;
        global $mimes_documents_acceptes;

        $verif = new Validateur();

        $verif->valider($this->valeurs['nom'], "nom", "texte", 1, 60, 1);
        $verif->valider($this->valeurs['determinant'], "determinant", "texte", 1, 30, 0);
        $verif->valider($this->valeurs['adresse'], "adresse", "texte", 1, 80, 1);
        $verif->valider($this->valeurs['localite_id'], "localite_id", "texte", 1, 80, 1);
        $verif->valider($this->valeurs['horaire_general'], "horaire_general", "texte", 2, 200, 0);
        $verif->valider($this->valeurs['URL'], "URL", "url", 2, 100, 0);
        /*
         * Coordonnées (latitude, longitude) : facultatives, mais les deux ensemble,
         * le plan n'étant affiché que si les deux sont renseignées
         */
        $lat = self::normaliserCoordonnee($this->valeurs['lat']);
        $lng = self::normaliserCoordonnee($this->valeurs['lng']);

        if (($lat === '') !== ($lng === ''))
        {
            $verif->setErreur(($lat === '') ? 'lat' : 'lng', "Veuillez indiquer la latitude et la longitude, ou laisser les deux champs vides");
        }

        if ($lat !== '' && (!is_numeric($lat) || abs((float) $lat) > 90))
        {
            $verif->setErreur('lat', "La latitude doit être un nombre entre -90 et 90 (ex. 46.2043907)");
        }

        if ($lng !== '' && (!is_numeric($lng) || abs((float) $lng) > 180))
        {
            $verif->setErreur('lng', "La longitude doit être un nombre entre -180 et 180 (ex. 6.1431577)");
        }

        /*
         * Catégorie (salle, cinéma, bistrot, etc.)
         */
        if (!empty($this->valeurs['categorie']))
        {
            foreach ($this->valeurs['categorie'] as $cat)
            {
                if (!array_key_exists($cat, $glo_categories_lieux))
                {
                    $verif->setErreur('categorie', "La catégorie " . $cat . " n'est pas valable");
                }
            }
        }
        else
        {
            $verif->setErreur('categorie', "Veuillez choisir au moins une catégorie");
        }

        $verif->validerFichier($this->fichiers['logo'], "logo", $mimes_images_acceptes, 0);
        $verif->validerFichier($this->fichiers['photo1'], "photo1", $mimes_images_acceptes, 0);

        $verif->validerFichier($this->fichiers['image_galerie'], "image_galerie", $mimes_images_acceptes, 0);

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

    /**
     * Normalise une coordonnée saisie : espaces superflus et virgule décimale,
     * produite par les pavés numériques de la plupart des claviers européens
     */
    private static function normaliserCoordonnee($valeur): string
    {
        return str_replace(',', '.', trim((string) $valeur));
    }

    function enregistrer()
    {
        global $rep_uploads_lieux;
        global $rep_fichiers_lieu;
        global $rep_uploads_lieux_galeries;
        global $glo_tab_quartiers2;

        $lieu = new Lieu();
        $lieu->setValues($this->valeurs);

        $lieu->setValue('idpersonne', $_SESSION['SidPersonne']);

        /*
         * Colonnes DECIMAL NOT NULL : un champ laissé vide doit être enregistré comme 0
         * (= pas de coordonnées), une chaine vide ferait échouer la requête
         */
        foreach (['lat', 'lng'] as $coordonnee)
        {
            $valeur = self::normaliserCoordonnee($this->valeurs[$coordonnee] ?? '');
            $lieu->setValue($coordonnee, is_numeric($valeur) ? $valeur : '0');
        }

        $loc_qua = explode("_", (string) $this->valeurs['localite_id']);

        if (count($loc_qua) > 1)
        {
            $lieu->setValue('localite_id', $loc_qua[0]);
            $lieu->setValue('quartier', $loc_qua[1]);
            $lieu->setValue('region', 'ge');
        }
        else
        {
            $lieu->setValue('quartier', '');

            if ($this->valeurs['localite_id'] == 'rf' || $this->valeurs['localite_id'] == 'hs')
            {
                $lieu->setValue('region', $this->valeurs['localite_id']);
                $lieu->setValue('localite_id', 1); // autre
            }
            elseif ($this->valeurs['localite_id'] == 529) // Nyon
            {
                $lieu->setValue('region', 'ge');
                $lieu->setValue('localite_id', 529);
            }
            else
            {
                // hors quotes, sanitize() ne neutralise rien d'une charge utile numérique :
                // c'est le cast qui protège, l'échappement ne faisait qu'entretenir l'illusion
                $sql_lieu = "SELECT canton FROM localite WHERE id=" . (int) $this->valeurs['localite_id'];
                $req_lieu = $this->connector->query($sql_lieu);
                $tab_lieu = $this->connector->fetchArray($req_lieu);
                $champs['region'] = $tab_lieu['canton'];

                $lieu->setValue('region', $tab_lieu['canton']);
            }
        }

        $lieu->setValue('idpersonne', $_SESSION['SidPersonne']);

        if (count($this->valeurs['categorie']) > 0)
        {
            $lieu->setValue('categorie', implode(",", $this->valeurs['categorie']));
        }

        if ($this->action == 'ajouter')
        {

            $nouvel_id = $lieu->getMaxId() + 1;
            if (!empty($this->fichiers['logo']['name']))
            {
                $lieu->setValue('logo', $nouvel_id . '_logo' . mb_strrchr((string) $this->fichiers['logo']['name'], '.'));
            }

            if (!empty($this->fichiers['photo1']['name']))
            {
                $lieu->setValue('photo1', $nouvel_id . '_photo1' . mb_strrchr((string) $this->fichiers['logo']['name'], '.'));
            }

            $lieu->setValue('dateAjout', date("Y-m-d H:i:s"));
            $lieu->setValue('date_derniere_modif', date("Y-m-d H:i:s"));

            /*
             * Insertion réussie, message OK, aperçu, et RAZ des champs
             */
            if ($lieu->insert())
            {
                $this->id = $this->connector->getInsertId();
                $this->message = 'Lieu ajouté';
            }
            else
            {
                HtmlShrink::msgErreur("Erreur lors de l'insertion dans la table");
                return false;
            }
        }
        else if ($this->action == 'editer')
        {
            $lieu->setValue('date_derniere_modif', date("Y-m-d H:i:s"));
            $lieu->setId($this->id);

            if ($this->fichiers['logo']['name'] != '')
            {
                // suppression des fichiers de l'ancienne image
                if (!empty($lieu->getValue('logo')))
                {
                    $this->safeUnlinkImageAndThumb($rep_uploads_lieux, $lieu->getValue('logo'));
                }

                $lieu->setValue('logo', Document::getFilename($this->fichiers['logo']['name'], $lieu->getId(), 'logo', ''));
            }



            /*
             * Si on a seulement choisi de supprimer l'image existante
             */
            elseif (in_array('logo', $this->supprimer))
            {
                // suppression des fichiers de l'image, s'il elle est effectivement enregistrée
                if (!empty($lieu->getValue('logo')))
                {
                    $this->safeUnlinkImageAndThumb($rep_uploads_lieux, $lieu->getValue('logo'));
                }

                $lieu->setValue('logo', '');
            }

            if ($this->fichiers['photo1']['name'] != '')
            {
                // suppression des fichiers de l'ancienne image
                if ($lieu->getValue('photo1') != '')
                {
                    $this->safeUnlinkImageAndThumb($rep_uploads_lieux, $lieu->getValue('photo1'));
                }

                $lieu->setValue('photo1', Document::getFilename($this->fichiers['photo1']['name'], $lieu->getId(), 'photo1', ''));
            }
            /*
             * Si on a seulement choisi de supprimer l'image existante
             */
            else if (in_array('photo1', $this->supprimer))
            {
                // suppression des fichiers de l'image, s'il elle est effectivement enregistrée
                if ($lieu->getValue('photo1') != '')
                {
                    $this->safeUnlinkImageAndThumb($rep_uploads_lieux, $lieu->getValue('photo1'));
                }

                $lieu->setValue('photo1', '');
            }

            foreach ($this->supprimer_galerie as $nom_fichier)
            {
                // Le formulaire poste « <id>.<extension> », mais la valeur vient du POST et rien
                // ne garantit sa forme : non castée, elle ouvrait une injection dans les deux
                // DELETE ci-dessous.
                $idF = (int) Text::reverseMbStrrchr($nom_fichier, '.');

                if ($idF <= 0)
                {
                    continue;
                }

                $this->connector->query("DELETE FROM lieu_fichierrecu WHERE idLieu=" . $lieu->getId() . " AND idFichierrecu=" . $idF);
                $this->connector->query("DELETE FROM fichierrecu WHERE idFichierrecu=" . $idF);
                $this->safeUnlinkImageAndThumb($rep_uploads_lieux_galeries, $nom_fichier);
            }


            $sql = "DELETE FROM lieu_organisateur WHERE idLieu=" . $lieu->getId();
            $req = $this->connector->query($sql);

            if ($lieu->update())
            {
                $this->message = 'Lieu modifié';
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
        if (!empty($this->fichiers['logo']['name']))
        {
            $imD2 = new ImageDriver2("lieux");

            if (!$imD2->processImage($this->fichiers['logo'], "s_" . $lieu->getValue('logo'), 200, 50, 'h', 0))
            {
                trigger_error($imD2->getErreur());
                exit;
            }

            if (!$imD2->processImage($this->fichiers['logo'], $lieu->getValue('logo'), 600, 600, '', 0))
            {
                trigger_error($imD2->getErreur());
                exit;
            }
        }

        if (!empty($this->fichiers['photo1']['name']))
        {
            $imD2 = new ImageDriver2("lieux");

            if (!$imD2->processImage($this->fichiers['photo1'], "s_" . $lieu->getValue('photo1'), 200, 300, 'w', 1))
            {
                trigger_error($imD2->getErreur());
                exit;
            }

            if (!$imD2->processImage($this->fichiers['photo1'], $lieu->getValue('photo1'), 600, 600, '', 0))
            {
                trigger_error($imD2->getErreur());
                exit;
            }
        }

        if (!empty($this->fichiers['image_galerie']['name']))
        {

            $extension = mb_strrchr((string) $this->fichiers['image_galerie']['name'], '.');

            $sql_insert = "INSERT INTO fichierrecu (idElement, type_element, description, mime, extension, type, dateAjout)
			VALUES ('" . $lieu->getId() . "', 'lieu',
			'',
			'image',
			'" . $this->connector->sanitize(mb_substr($extension, 1)) . "', 'image', '" . date("Y-m-d H:i:s") . "')";

            $this->connector->query($sql_insert);

            $id_nouveau_fichier = $this->connector->getInsertId();

            $sql_ins_ef = "INSERT INTO lieu_fichierrecu (idLieu, idFichierrecu)
			VALUES ('" . $lieu->getId() . "', '" . $id_nouveau_fichier . "')";

            $this->connector->query($sql_ins_ef);

            $nom_image_galerie = $id_nouveau_fichier . $extension;
            $imD = new ImageDriver2("lieux/galeries");

            $erreur_image[] = $imD->processImage($this->fichiers['image_galerie'], "s_" . $nom_image_galerie, 60, 60, '', 1);
            $erreur_image[] = $imD->processImage($this->fichiers['image_galerie'], $nom_image_galerie, 600, 600, '', 0);

            $champs['image_galerie'] = '';
        }

        foreach ($this->organisateurs as $idOrg)
        {
            // Valeurs d'un <select multiple>, donc entièrement forgeables : la comparaison lâche
            // à 0 laissait passer n'importe quelle chaine jusqu'à la concaténation SQL.
            $idOrg = is_scalar($idOrg) ? (int) $idOrg : 0;

            if ($idOrg > 0)
            {
                $sql = "INSERT INTO lieu_organisateur (idLieu, idOrganisateur) VALUES (" . $lieu->getId() . ", " . $idOrg . ")";
                $this->connector->query($sql);
            }
        }
    }

    function loadValues(int $id): void
    {
        $lieu = new Lieu();
        $lieu->setId($id);
        $lieu->load();
        $champs = $lieu->getValues();

        $champs['categorie'] = explode(',', (string) $champs['categorie']);

        $this->valeurs = $champs;
    }

}
