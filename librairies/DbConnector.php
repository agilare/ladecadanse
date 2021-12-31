<?php

namespace Ladecadanse;

use Ladecadanse\SystemComponent;

class DbConnector extends SystemComponent
{
    private string $sql;

    /**
     * Ressource vers la BD
     */
    private $dbConnection;

    public function __construct($host, $db, $user, $pass)
    {
        $this->dbConnection = mysqli_connect($host, $user, $pass, $db);

        mysqli_set_charset($this->dbConnection, 'utf8mb4');
        //enregistre la méthode "close" pour qu'elle soit executée une fois le script terminé
        register_shutdown_function(array(&$this, 'close'));
    }

    public function query($requete)
    {
        $this->sql = $requete;
        $result = mysqli_query($this->dbConnection, $requete) or die(mysqli_error($this->dbConnection));
        return $result;
    }

    public function fetchArray($result)
    {
        return mysqli_fetch_array($result);
    }
    
    public function fetchAssoc($result)
    {
        return mysqli_fetch_assoc($result);
    }

    public function fetchAll($result)
    {
        $return = array();

       while($row = mysqli_fetch_array($result))
       {
           $return[] = $row;
       }
       return $return;
    }

    public function close(): void
    {
        mysqli_close($this->dbConnection);
    }

    public function getNumRows($result): int
    {
        return mysqli_num_rows($result);
    }

    public function getInsertId()
    {
        return mysqli_insert_id($this->dbConnection);
    }

    public function getAffectedRows(): int
    {
        return mysqli_affected_rows($this->dbConnection);
    }

    public function sanitize($escapestr): string
    {
        return mysqli_real_escape_string ($this->dbConnection, $escapestr);
    }
}