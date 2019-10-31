<?php

require_once 'SystemComponent.php';


class DbConnector extends SystemComponent
{

    /**
     * Contient une requête SQL
     * 
     * @var string
     */
    var $laRequete;

    /**
     * Ressource vers la BD
     * 
     * @var resource
     */
    var $lien;

    /**
    * Récupère les paramètres de la classe mère : host, db, user, pass
    * Établi une connexion à la base de données
    * 
    * @access public
    */
    function __construct ($host, $db, $user, $pass)
    {
        //connection à la base de données
        $this->lien = mysqli_connect($host, $user, $pass, $db);

        mysqli_set_charset($this->lien, 'utf8mb4');
        //enregistre la méthode "close" pour qu'elle soit executée une fois le script terminé
        register_shutdown_function(array(&$this, 'close'));
    }

    /**
    * Execute une requête SQL, affiche l'erreur MySQL en cas de problème
    * 
    * @access public
    * @param string $requete Requête SQL à executer
    * @result resource $result Ressource contenant le résultat de la requête SQL
    */
    function query($requete)
    {
        $this->laRequete = $requete;
        $result = mysqli_query($this->lien, $requete) or die(mysqli_error($this->lien));
        return $result;
    }

    /**
    * Récupérer un tableau de résultats d'une requête
    * 
    * @access public
    * @param resource $resulte Ressource contenant le résultat d'une requête SQL
    * @return array Tableau associatif contenant la liste des enregistrements de la requête
    */
    function fetchArray($result)
    {
        return mysqli_fetch_array($result);
    }


    /**
    * Récupérer un tableau de résultats d'une requête
    * 
    * @access public
    * @param resource $resulte Ressource contenant le résultat d'une requête SQL
    * @return array Tableau associatif contenant la liste des enregistrements de la requête
    */
    function fetchAssoc($result)
    {
        return mysqli_fetch_assoc($result);
    }


    function fetchAll($result)
    {
        $return = array();

       while($row = mysqli_fetch_array($result))
       {
           $return[] = $row;
       }
       return $return;
    }

    /**
        * Ferme la connexion à MySQL
        * 
       * @access public
       */
    function close()
    {
        mysqli_close($this->lien);
    }

     /**
      * Donne la quantité d'enregistrements issus de la requête SQL
      * 
      * @access public
      * @param resource $resulte Ressource contenant le résultat d'une requête SQL
      * @return int Nombre d'enregistrements
      */
    function getNumRows($result)
    {
        return mysqli_num_rows($result);
    }

    function getInsertId()
    {
        return mysqli_insert_id($this->lien);
    }

    function getAffectedRows()
    {
        return mysqli_affected_rows($this->lien);
    }


     /**
      * 
      * 
      * @access public
      * @return array string
      * @see 
      */
    function sanitize($escapestr)
    {
        return mysqli_real_escape_string ($this->lien, $escapestr );
    }


}