<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

/**
 * Validation des données saisies dans un formulaire.
 *
 * Valideur à état : les échecs sont accumulés dans $erreurs, indexés par nom
 * de champ, et récupérés via getErreur()/getHtmlErreur()/nbErreurs()… La
 * validation des paramètres d'URL (query string) a été extraite dans
 * QueryParamValidator (issue #153).
 */
class Validateur
{
    /**
     * Erreurs rencontrées durant la vie de l'objet, indexées par nom de champ.
     *
     * @var array<string, string>
     */
    public array $erreurs = [];

    /**
     * Valide un champ : obligation, longueur, puis format selon $type_champ.
     *
     * @param mixed $valeur_champ Valeur saisie (peut être null)
     */
    public function valider(mixed $valeur_champ, string $nom_champ, string $type_champ, int $longueur_min, int $longueur_max, bool $obligatoire): bool
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
            else if ($type_champ == "nombre" && !$this->validerNombre($nom_champ, $valeur_champ))
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

    /**
     * Vérifie que le champ n'est pas vide.
     *
     * @param mixed $theInput Valeur à vérifier
     */
    public function notEmpty(mixed $theInput, string $nom): bool
    {
        if (!empty($theInput))
        {
            return true;
        }

        $this->erreurs[$nom] = "<span style=\"background:yellow\">Ce champ est obligatoire</span>";
        return false;
    }

    /**
     * Vérifie qu'un texte a une longueur comprise entre $min et $max.
     *
     * @param mixed $theInput Texte à vérifier
     */
    public function validerLongueurTexte(string $nom, mixed $theInput, int $min = 0, int $max = 20): bool
    {
        $theInput = trim((string) $theInput);

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

        return false;
    }

    /**
     * Vérifie qu'une adresse email est au bon format.
     *
     * @param mixed $theInput Adresse à vérifier
     */
    public function validerEmail(string $nom, mixed $theInput): bool
    {
        if (filter_var((string) $theInput, FILTER_VALIDATE_EMAIL) !== false)
        {
            return true;
        }

        $this->erreurs[$nom] = "Le format de l'email n'est pas correct";
        return false;
    }

    /**
     * Vérifie qu'une valeur est un nombre.
     *
     * @param mixed $theInput Valeur à vérifier
     */
    public function validerNombre(string $nom, mixed $theInput): bool
    {
        if (is_numeric($theInput))
        {
            return true;
        }

        $this->erreurs[$nom] = "Ce n'est pas un nombre";
        return false;
    }

    /**
     * Vérifie qu'une URL est au bon format (préfixe http:// ajouté si absent).
     *
     * @param mixed $url Adresse à vérifier
     */
    public function validerURL(string $nom, mixed $url): bool
    {
        $url = (string) $url;

        if ($url !== "" && !preg_match("/^https?:\/\//i", $url))
        {
            $url = "http://" . $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) !== false)
        {
            return true;
        }

        $this->erreurs[$nom] = "Ce format d'URL n'est pas correct";
        return false;
    }

    /**
     * Vérifie qu'un fichier uploadé est d'un type autorisé et bien reçu.
     * Ne déplace pas le fichier : détecte seulement les erreurs de transfert.
     *
     * @param mixed         $fileinfo       Entrée de $_FILES à vérifier
     * @param array<string> $mimes_acceptes Types MIME autorisés
     */
    public function validerFichier(mixed $fileinfo, string $nom, array $mimes_acceptes, bool $obligatoire): bool
    {
        if ($obligatoire && empty($fileinfo['name']))
        {
            $this->erreurs[$nom] = "Ce champ est obligatoire";
        }
        else if (!empty($fileinfo['name']))
        {
            if (!empty($fileinfo['type']) && !in_array($fileinfo['type'], $mimes_acceptes))
            {
                $this->erreurs[$nom] = "Ce format de fichier (" . pathinfo((string) $fileinfo['name'], PATHINFO_EXTENSION) . ") n'est pas accepté";
                return false;
            }

            if (strstr((string) $fileinfo['name'], "php"))
            {
                $this->erreurs[$nom] = "Veuillez ôter 'php' du nom de votre fichier";
                return false;
            }

            if (is_uploaded_file($fileinfo['tmp_name']))
            {
                return true;
            }

            switch ($fileinfo['error'])
            {
                case UPLOAD_ERR_INI_SIZE:
                    $this->erreurs[$nom] = "Le fichier dépasse la taille autorisée (2 Mo)";
                    return false;

                case UPLOAD_ERR_FORM_SIZE:
                    $this->erreurs[$nom] = "Le fichier dépasse la limite autorisée dans le formulaire HTML (2 Mo)";
                    return false;

                case UPLOAD_ERR_PARTIAL:
                    $this->erreurs[$nom] = "L'envoi du fichier a été interrompu pendant le transfert";
                    return false;

                case UPLOAD_ERR_NO_FILE:
                    $this->erreurs[$nom] = "Le fichier envoyé a une taille nulle";
                    return false;

                default:
                    $this->erreurs[$nom] = "Il y a eu un problème de transfert.";
                    return false;
            }
        }

        return true;
    }

    public function nbErreurs(): int
    {
        return count($this->erreurs);
    }

    public function getMsgNbErreurs(): ?string
    {
        $nb = $this->nbErreurs();

        if ($nb === 0)
        {
            return null;
        }

        if ($nb === 1)
        {
            return "Il y a une erreur";
        }

        return "Il y a " . $nb . " erreurs";
    }

    /**
     * Renvoie la dernière erreur ajoutée (compatible avec les clés textuelles).
     */
    public function lastError(): ?string
    {
        if ($this->erreurs === [])
        {
            return null;
        }

        return $this->erreurs[array_key_last($this->erreurs)];
    }

    /**
     * @return string|false Le message d'erreur du champ, ou false si aucun
     */
    public function getErreur(string $champ): string|false
    {
        if (array_key_exists($champ, $this->erreurs))
        {
            return $this->erreurs[$champ];
        }

        return false;
    }

    public function setErreur(string $nom_champ, string $description): void
    {
        $this->erreurs[$nom_champ] = $description;
    }

    /**
     * @return array<string, string>
     */
    public function getErreurs(): array
    {
        return $this->erreurs;
    }

    public function getHtmlErreur(string $champ): string
    {
        if (array_key_exists($champ, $this->erreurs))
        {
            return '<div class="msg">' . $this->erreurs[$champ] . '</div>';
        }

        return '';
    }
}
