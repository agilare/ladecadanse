<?php
namespace Ladecadanse;

/**
  * An abstract class implementing generic functionality for processing user's input
  *
  * This class encapsulates generic functions for working
  * with data coming from user forms. Descendants must only override certain
  * functions that perform context-specific tasks, like custom checking of
  * data, storing correct data, etc.

  */
  class Edition
  {
	public $id;
    public $supprimer = [];
    public $erreurs = [];
	public $action;

	public $message;
    public $connector;

    function __construct(public $nom, public $valeurs, public $fichiers)
    {
		global $connector;

		$this->connector = $connector;

      	$this->erreurs = array_merge($this->valeurs, $this->fichiers);
    }

    function traitement(array $post, array $files)
    {
        foreach ($this->valeurs as $nom => $val)
    	{
    		if (isset($post[$nom]))
    		{
                $this->valeurs[$nom] = $post[$nom];
    		}
        }

    	foreach ($this->fichiers as $nom => $val)
    	{
    		// un champ fichier peut ne pas figurer dans $_FILES : le formulaire ne
    		// l'affiche pas pour tous les niveaux d'utilisateur (image_galerie sur
    		// lieu-edit.php). On garde alors la valeur par défaut déclarée.
    		if (isset($files[$nom]))
    		{
    			$this->fichiers[$nom] = $files[$nom];
    		}
    	}

    	if (isset($post['supprimer']))
    	{
    			$this->supprimer[] = $post['supprimer'];
    	}
    }

    function verification()
    {
		/*
		 * Les vérifications par les classes filles se font ici
		 */

    }

	/**
	 * Charge l'enregistrement à modifier.
	 *
	 * @return bool false si l'identifiant ne désigne rien : à la page de répondre 404
	 *              plutôt que d'afficher un formulaire vide.
	 */
	function loadValeurs(int $id): bool
    {
		return false;
	}

    function enregistrer()
    {

    }


    function getErreur(string $champ): string
    {
    	$erreur = $this->erreurs[$champ] ?? '';

    	return is_string($erreur) ? $erreur : '';
    }

    /**
     * Nombre de champs réellement en erreur.
     *
     * $erreurs est initialisé dans le constructeur avec toutes les clés de
     * champs du formulaire, valeur vide ; seules celles que la vérification a
     * remplies d'un message comptent comme des erreurs.
     */
    function getNbErreurs(): int
    {
    	return count(array_filter($this->erreurs, static fn($erreur): bool => !empty($erreur)));
    }

    function getHtmlErreur(string $champ): ?string
    {
    	if (empty($this->erreurs[$champ]))
    	{
    		return null;
    	}

    	return '<div class="msg">'.$this->erreurs[$champ].'</div>';
    }

    function setAction($action)
    {
    	$this->action = $action;
    }

    function getMessage(): ?string
    {
    	return $this->message;

    }

	function getSupprimer()
	{
		return $this->supprimer;
	}

	function getValeur($nom)
	{
		if (isset($this->valeurs[$nom]))
		{
			return $this->valeurs[$nom];
		}
		else
		{
			return NULL;
		}
	}

    function setValeur($nom, $val)
    {
    	$this->valeurs[$nom] = $val;
    }

    /**
     * Supprime un fichier image et sa miniature (préfixe "s_") de manière sécurisée.
     *
     * Neutralise toute tentative de path traversal provenant d'une valeur issue de la BD :
     * - basename() supprime les composants de répertoire du nom de fichier
     * - realpath() + str_starts_with() garantit que le chemin résolu reste dans $dir
     */
    protected function safeUnlinkImageAndThumb(string $dir, string $filename): void
    {
        $safeName = basename($filename);
        if ($safeName === '') {
            return;
        }
        $safeDir = realpath($dir);
        if ($safeDir === false) {
            return;
        }
        foreach ([$safeName, 's_' . $safeName] as $name) {
            $resolvedPath = realpath($safeDir . DIRECTORY_SEPARATOR . $name);
            if ($resolvedPath !== false && str_starts_with($resolvedPath, $safeDir . DIRECTORY_SEPARATOR)) {
                unlink($resolvedPath);
            }
        }
    }

}
