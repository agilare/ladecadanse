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

            return $this->erreurDeTransfert($fileinfo, $nom);
        }

        return true;
    }

    /**
     * Valide un fichier image sur son contenu réel, et non sur ce que le
     * navigateur en dit.
     *
     * validerFichier() se fie à $_FILES['…']['type'], que le client compose
     * lui-même : il suffit de l'annoncer « image/jpeg » pour passer. Ici le
     * fichier est ouvert, et c'est getimagesize() qui donne le format et les
     * dimensions.
     *
     * Le plafond en mégapixels n'est pas une précaution théorique : GD
     * décompresse à ~4 octets par pixel, si bien qu'un PNG de quelques
     * centaines de kilo-octets — de grands aplats se compressent très bien —
     * peut couvrir assez de pixels pour épuiser la mémoire du processus. Le
     * fatal error qui s'ensuit ne laisse aucun message à l'utilisateur, là où
     * ce refus lui explique quoi faire.
     *
     * @param mixed         $fileinfo       Entrée de $_FILES à vérifier
     * @param array<string> $mimes_acceptes Types MIME autorisés
     */
    public function validerFichierImage(mixed $fileinfo, string $nom, array $mimes_acceptes, bool $obligatoire): bool
    {
        if ($obligatoire && empty($fileinfo['name']))
        {
            $this->erreurs[$nom] = "Ce champ est obligatoire";
            return false;
        }

        if (empty($fileinfo['name']))
        {
            return true;
        }

        if (!empty($fileinfo['error']))
        {
            return $this->erreurDeTransfert($fileinfo, $nom);
        }

        if (strstr((string) $fileinfo['name'], "php"))
        {
            $this->erreurs[$nom] = "Veuillez ôter 'php' du nom de votre fichier";
            return false;
        }

        // Le PDF passe avant tout le reste. Sans ce cas, l'utilisateur lirait
        // « Ce format n'est pas accepté » alors que le formulaire annonce
        // justement accepter les PDF : ils sont convertis par le navigateur
        // (web/js/pdf-to-image.js) et n'arrivent jusqu'ici que lorsque
        // JavaScript n'a pas pu faire son travail.
        if (str_starts_with((string) @file_get_contents($fileinfo['tmp_name'], false, null, 0, 5), '%PDF-'))
        {
            $this->erreurs[$nom] = "Ce PDF n'a pas pu être converti en image par votre navigateur. "
                . "Activez JavaScript, ou convertissez sa 1re page en JPEG ou PNG avant de l'envoyer.";
            return false;
        }

        // Le seul contrôle de taille qui morde réellement : l'hébergement
        // autorise des envois bien plus gros que les nôtres, aucun UPLOAD_ERR_*
        // ne remonte donc à 5 Mo. Quant à MAX_FILE_SIZE, c'est un champ du
        // formulaire, que rien n'oblige un client à respecter.
        if (!empty($fileinfo['size']) && $fileinfo['size'] > UPLOAD_MAX_FILESIZE)
        {
            $this->erreurs[$nom] = "Le fichier dépasse la taille autorisée (" . self::formaterTaille(UPLOAD_MAX_FILESIZE) . ")";
            return false;
        }

        $infos = @getimagesize((string) $fileinfo['tmp_name']);

        if ($infos === false)
        {
            $this->erreurs[$nom] = "Ce fichier n'est pas une image exploitable";
            return false;
        }

        if (!in_array($infos['mime'], $mimes_acceptes))
        {
            $this->erreurs[$nom] = "Ce format d'image (" . $infos['mime'] . ") n'est pas accepté";
            return false;
        }

        if ($infos[0] * $infos[1] > UPLOAD_MAX_MEGAPIXELS * 1000000)
        {
            $this->erreurs[$nom] = "Cette image est trop grande (" . $infos[0] . " × " . $infos[1]
                . " pixels, maximum " . UPLOAD_MAX_MEGAPIXELS . " mégapixels)";
            return false;
        }

        // Dernier verrou : le fichier doit bien venir d'un envoi HTTP. En test
        // unitaire is_uploaded_file() est toujours faux, d'où sa place ici,
        // après les contrôles que l'on veut pouvoir couvrir.
        if (!is_uploaded_file((string) $fileinfo['tmp_name']))
        {
            return $this->erreurDeTransfert($fileinfo, $nom);
        }

        return true;
    }

    /**
     * Message correspondant au code d'erreur d'un envoi qui n'a pas abouti.
     *
     * @param mixed $fileinfo Entrée de $_FILES concernée
     */
    private function erreurDeTransfert(mixed $fileinfo, string $nom): bool
    {
        $taille = self::formaterTaille(UPLOAD_MAX_FILESIZE);

        $this->erreurs[$nom] = match ($fileinfo['error'] ?? null) {
            UPLOAD_ERR_INI_SIZE => "Le fichier dépasse la taille autorisée ($taille)",
            UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la limite autorisée dans le formulaire HTML ($taille)",
            UPLOAD_ERR_PARTIAL => "L'envoi du fichier a été interrompu pendant le transfert",
            UPLOAD_ERR_NO_FILE => "Le fichier envoyé a une taille nulle",
            default => "Il y a eu un problème de transfert.",
        };

        return false;
    }

    /**
     * Formate un nombre d'octets pour un message destiné à l'utilisateur.
     */
    private static function formaterTaille(int $octets): string
    {
        return rtrim(rtrim(number_format($octets / 1048576, 1, ',', ''), '0'), ',') . " Mo";
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
