<?php

namespace Ladecadanse\Utils;

use Ladecadanse\Utils\SystemComponent;
use mysqli_result;

class DbConnector extends SystemComponent
{
    private string $sql;
    private $dbConnection;

    public function __construct($host, $db, $user, $pass)
    {
        $this->dbConnection = mysqli_connect($host, $user, $pass, $db);

        mysqli_set_charset($this->dbConnection, 'utf8mb4');
        mysqli_query($this->dbConnection, "SET SESSION sql_mode = 'IGNORE_SPACE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
//        $result = mysqli_query($this->dbConnection, "SELECT @@SESSION.sql_mode");
//        $row = mysqli_fetch_row($result);
//        echo "SQL_MODE actif : " . $row[0];
        //enregistre la méthode "close" pour qu'elle soit executée une fois le script terminé
        register_shutdown_function([&$this, 'close']);
    }

    public function query(string $requete)
    {
        $this->sql = $requete;
        $result = mysqli_query($this->dbConnection, $requete);
        if ($result === false) {
            // Le message détaillé va dans les logs PHP, jamais affiché
            throw new \RuntimeException("Erreur SQL : " . mysqli_error($this->dbConnection) . " | Query : " . $requete);
        }
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
        $return = [];

       while($row = mysqli_fetch_array($result))
       {
           $return[] = $row;
       }
       return $return;
    }

    public function fetchAllAssoc(mysqli_result $result): array
    {
       return $result->fetch_all(MYSQLI_ASSOC);
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

    public function sanitize(string $escapestr): string
    {
        return mysqli_real_escape_string ($this->dbConnection, $escapestr);
    }
}