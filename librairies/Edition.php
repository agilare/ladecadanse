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
    public $firstTime;
	public $id;
    public $supprimer = [];
    public $erreurs = [];
    public $verif;
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
    		$this->fichiers[$nom] = $files[$nom];
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

	function loadValeurs(int $id): void
    {


	}

    function enregistrer()
    {

    }


    function NextWizardPage() {}
    //abstract

    function Set($Name, $Value) {
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
    		return '<div class="msg">'.$this->erreurs[$champ].'</div>';
    	}
    }

    function GetInitialValue($Name) {
      if (isset($this->Values[$Name]))
        return $this->Values[$Name];
      else
        return false;
    }

    function InitialValue($Name) {
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

	function getValeurs()
	{
		return $this->valeurs;
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
