<?php

namespace Ladecadanse\Utils;

/**
 * Donne diverses méthodes de vérification pour des données entrées par un formulaire
 */
class Validateur
{

    /**
     * Liste des erreurs rencontrées durant la vie de l'objet
     *
     * @var array string
     */
    var $erreurs = array();
    var $types = array('texte', 'email', 'telephone', 'nombre', 'date', 'url', 'fichier', 'image');

    function valider($valeur_champ, $nom_champ, $type_champ, $longueur_min, $longueur_max, $obligatoire)
    {

        if ($obligatoire && !$this->notEmpty($valeur_champ, $nom_champ))
        {
            return false;
        }

        if ($valeur_champ != "")
        {

            if (!$this->validerLongueurTexte($nom_champ, $valeur_champ, $longueur_min, $longueur_max))
            {
                return false;
            }

            if ($type_champ == "email" && !$this->validerEmail($nom_champ, $valeur_champ))
            {
                return false;
            }
            else if ($type_champ == "telephone" && !$this->validerTelephone($nom_champ, $valeur_champ))
            {
                return false;
            }
            else if ($type_champ == "nombre" && !$this->validerNombre($nom_champ, $valeur_champ))
            {
                return false;
            }
            else if ($type_champ == "date" && !$this->validerDateApp($nom_champ, $valeur_champ))
            {
                return false;
            }
            else if ($type_champ == "url" && !$this->validerURL($nom_champ, $valeur_champ))
            {
                return false;
            }
        }

        return true;
    }

    function notEmpty($theInput, $nom)
    {
        if (!empty($theInput))
        {

            return true;
        }
        else
        {

            $this->erreurs[$nom] = "<span style=\"background:yellow\">Ce champ est obligatoire</span>";
            return false;
        }
    }

    /**
     * Vérifie qu'une chaine soit uniquement composée de texte, de chiffres et d'espaces (sans accents)

     * @param string Texte à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerTexte($nom, $theInput, $description = '')
    {

        $result = preg_match("/^[A-Za-z0-9\ ]+$/", $theInput);
        if ($result)
        {
            return true;
        }
        else
        {

            $this->erreurs[$nom] = $description;
            return false;
        }
    }

    /**
     * Vérifie qu'une chaine soit uniquement composée de texte, de chiffres et d'espaces (sans accents)

     * @param string Texte à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerAlpha($nom, $theInput, $description = '')
    {

        $result = preg_match("/^[A-Za-z0-9\ ]+$/", $theInput);
        if ($result)
        {
            return true;
        }
        else
        {

            $this->erreurs[$nom] = $description;
            return false;
        }
    }

    /**
     * Vérifie qu'une chaine soit uniquement composée de texte, de chiffres et d'espaces (sans accents)

     * @param string Texte à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerAlpha2($nom, $theInput, $description = '')
    {

        $result = preg_match("/^[A-Za-z0-9]+$/", $theInput);
        if ($result)
        {
            return true;
        }
        else
        {

            $this->erreurs[$nom] = $description;
            return false;
        }
    }

    /**
     * Vérifie qu'un texte ne dépasse pas un max et soit d'au moins de taille min

     * @param string Texte à vérifier
     * @param int $min Taille minimale que le texte doit avoir
     * @param int $max Taille maximale que le texte doit avoir
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerLongueurTexte($nom, $theInput, $min = 0, $max = 20)
    {
        $theInput = trim($theInput);

        if (mb_strlen($theInput) >= $min && mb_strlen($theInput) <= $max)
        {
            return true;
        }
        elseif (mb_strlen($theInput) < $min)
        {
            $this->erreurs[$nom] = "Le texte est trop court : " . mb_strlen($theInput) . ", min " . $min . " characters";
            return false;
        }
        elseif (mb_strlen($theInput) > $max)
        {
            $this->erreurs[$nom] = "Le texte est trop long : " . mb_strlen($theInput) . ", max " . $max . " characters";
            return false;
        }
    }

    /**
     * Vérifie qu'une adresse email soit au bon format
     *
     * @param string Adresse à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerEmail($nom, $theInput, $description = '')
    {
        $result = preg_match("/^[^@ ]+@[^@ ]+\.[^@ \.]+$/", $theInput);
        if ($result)
        {
            return true;
        }
        else
        {
            $this->erreurs[$nom] = "Le format de l'email n'est pas correct";
            return false;
        }
    }

    /**
     * Vérifie qu'une valeur soit un nombre
     *
     * @param string Valeur à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerNombre($nom, $theInput, $description = '')
    {


        if (is_numeric($theInput))
        {
            return true;
        }
        else
        {

            $this->erreurs[$nom] = "Ce n'est pas un nombre";
            return false;
        }
    }

    /**
     * Vérifie qu'une date est valable, au format américain
     *
     * @param string Adresse à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerDateApp($nom, $date)
    {

        // if (!preg_match("", $date))
        // {
        // $this->erreurs[$nom] = "Format incorrect";
        // return false;
        // }
        // else
        // {
        $tab_date = explode(".", $date);

        if (mb_strlen($tab_date[2]) > 4)
        {
            $this->erreurs[$nom] = "Format incorrect : jourr";
            return false;
        }
        else if ($tab_date[1] < 1 || $tab_date[1] > 12)
        {
            $this->erreurs[$nom] = "Format incorrect : mois";
            return false;
        }
        else if ($tab_date[0] < 1 || $tab_date[0] > 31)
        {
            $this->erreurs[$nom] = "Format incorrect : année";
            return false;
        }


        //}

        return true;
    }

    /**
     * Vérifie qu'une URL est au bon format, accepte les URL complexes
     *
     * @param string Adresse à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerURL($nom, $url, $description = '')
    {

        if (isset($url) && !preg_match("/^(https?:\/\/)/i", $url))
            $url = "http://" . $url;

        $result = preg_match('#^https?\\:\\/\\/[a-z0-9_-]+\.([a-z0-9_-]+\.)?[a-zA-Z]{2,3}#i', $url);

        if ($result)
        {
            return true;
        }
        else
        {
            $this->erreurs[$nom] = "Ce format d'URL n'est pas correct";
            return false;
        }

        //^(ht|f)tp(s?)\:\/\/[a-zA-Z0-9\-\._]+(\.[a-zA-Z0-9\-\._]+){2,}(\/?)([a-zA-Z0-9\-\.\?\,\'\/\\\+&%\$#_]*)?$
        //'^(http|https)\://([[:alnum:]_.]+\.)?[a-zA-Z]{2,3}(:[a-zA-Z0-9]*)?/?([a-zA-Z0-9\-\._\?\,\'/\\\+&%\$#\=~])*[^\.\,\)\(\s]$'
    }

    /**
     * Vérifie qu'un fichier uploadé ne soit pas d'un type dangereux et qu'il a bien été
     * reçu sur le serveur. Si ce n'est pas le cas détecte l'erreur qui a été engendrée
     *
     * @param string Nom du fichier à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerFichier($filename, $nom, $mimes_acceptes, $obligatoire)
    {
        if ($obligatoire && empty($filename['name']))
        {
            $this->erreurs[$nom] = "Ce champ est obligatoire";
        }
        else if (!empty($filename['name']))
        {
            if (!empty($filename['type']) && !in_array($filename['type'], $mimes_acceptes))
            {
                $this->erreurs[$nom] = "Ce format de fichier (" . pathinfo($filename['name'], PATHINFO_EXTENSION) . ") n'est pas accepté";
                return false;
            }

            if (strstr($filename['name'], "php"))
                $this->erreurs[$nom] = "Veuillez ôter 'php' du nom de votre fichier";


            if (is_uploaded_file($filename['tmp_name']))
            {
                return true;
            }
            else
            {
                switch ($filename['error'])
                {

                    case 1: // UPLOAD_ERR_INI_SIZE
                        $this->erreurs[$nom] = "Le fichier dépasse la taille autorisée (2 Mo)";
                        return false;
                        break;

                    case 2: // UPLOAD_ERR_FORM_SIZE
                        $this->erreurs[$nom] = "Le fichier dépasse la limite autorisée dans le formulaire HTML (2 Mo)";
                        return false;
                        break;

                    case 3: // UPLOAD_ERR_PARTIAL
                        $this->erreurs[$nom] = "L\'envoi du fichier a été interrompu pendant le transfert";
                        return false;
                        break;

                    case 4: // UPLOAD_ERR_NO_FILE
                        $this->erreurs[$nom] = "Le fichier envoyé a une taille nulle";
                        return false;
                        break;

                    default:
                        $this->erreurs[$nom] = "Il y a eu un problème de transfert.";
                        return false;
                }
            }
        }

        return true;
    }

    /**
     * Vérifie qu'une image uploadée soit d'un type de fichier autorisé
     *
     * @param string Nom du fichier à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerImage($imageSource, $description = '')
    {
        $allowedmime = array("image/jpeg", "image/pjpeg", "image/gif", "image/png", "image/x-png");

        if (in_array($imageSource['type'], $allowedmime))
        {
            return true;
        }
        else
        {
            $this->erreurs[] = $description;
            return false;
        }
    }

    /**
     * Vérifie qu'une chaine correspond à un no de téléphone valable, c-à-d est composée
     * seulement de nombres et soit de max 8 car.
     *
     * @param string No à vérifier
     * @param string Message d'erreur à afficher en cas d'échec
     * @return boolean Validation réussie ou non
     * @access public
     */
    function validerTelephone($nom, $str)
    {
        //returns 1 if valid phone number (only numeric string), 0 if not

        $new_str = str_replace(" ", "", $str);

        if (is_numeric($new_str) && mb_strlen($new_str) > 4)
        {
            return true;
        }
        else
        {
            $this->erreurs[$nom] = "Ce format de numéro de téléphone n'est pas correct";
            return false;
        }
        //mb_ereg('^[0-9[:space:]]{9,}$', $str)
    }

    /*
      function validateDate($date) {

      list($d, $m, $y) = mb_split('[/.-]', $date);
      $bidon = date("d/m/Y", mktime(0,0,0,$m,$d,$y));
      $date = mb_ereg_replace('-', '/', $date);

      if ($bidon != $date) {

      return false;

      } else {

      return true;

      }


      }
     */

    function nbErreurs()
    {
        return count($this->erreurs);
    }

    function getMsgNbErreurs()
    {
        if ($this->nbErreurs() == 0)
        {
            return;
        }
        else if ($this->nbErreurs() == 1)
        {
            return "Il y a une erreur";
        }
        else if ($this->nbErreurs() > 1)
        {
            return "Il y a " . $this->nbErreurs() . " erreurs";
        }
    }

    /**
     * Réunis les valeurs du tableau erreurs en une chaine de car., séparées par un delim
     *
     * @param string $delim Séparateur texte pour les valeurs du tableau
     * @return boolean Validation réussie ou non
     * @access public
     */
    function listErreurs($delim = ' ')
    {
        return implode($delim, $this->erreurs);
    }

    /**
     * Renvoie la dernière valeur qui a été ajoutée au tableau des erreurs
     *
     * @return string Erreur au sommet de la pile
     * @access public
     */
    function lastError()
    {
        return $this->erreurs[count($this->erreurs) - 1];
    }

    function getErreur($champ)
    {
        if (array_key_exists($champ, $this->erreurs))
        {
            return $this->erreurs[$champ];
        }
        else
        {
            return false;
        }
    }

    function setErreur($nom_champ, $description)
    {
        $this->erreurs[$nom_champ] = $description;
    }

    function getErreurs()
    {
        return $this->erreurs;
    }

    function getHtmlErreur($champ)
    {
        if (array_key_exists($champ, $this->erreurs))
        {
            return '<div class="msg">' . $this->erreurs[$champ] . '</div>';
        }
        else
        {
            return '';
        }
    }

    function getHtmlTotalErreurs()
    {
        if ($this->nbErreurs() == 1)
        {
            return '<div class="msg_erreur">Il y a une erreur</div>';
        }
        else if ($this->nbErreurs() > 1)
        {
            return '<div class="msg_erreur">Il y a ' . $this->nbErreurs() . ' erreurs</div>';
        }
        else
        {
            return;
        }
    }

    public static function validateUrlQueryValue($get, $type, $statut, $tab = '')
    {
        $erreur = "";

        if ($get == '')
        {
            if ($statut == 1)
            {
                $erreur = "Ce paramètre est obligatoire";
            }
            else if ($statut != 0)
            {
                return $statut;
            }
            else
            {
                return;
            }
        }
        else
        {
            $get = trim($get);

            if ($type == "int")
            {
                if (is_numeric($get))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas un numeric";
                }
            }
            else if ($type == "string")
            {
                if (is_string($get))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas une chaine";
                }
            }
            else if ($type == "date")
            {

                if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $get))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas une date";
                }
            }
            else if ($type == "enum")
            {
                if (in_array($get, $tab))
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas une valeur acceptée";
                }
            }
            else if ($type == "alpha_numeric")
            {
                if (!preg_match("/^\w+$/i", $get) )
                {
                    return $get;
                }
                else
                {
                    $erreur = $get." n'est pas un alpha_numeric";
                }
            }
        }

        throw new \Exception($erreur);
    }    
    
}

//class
