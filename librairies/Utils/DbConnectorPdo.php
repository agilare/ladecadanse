<?php

namespace Ladecadanse\Utils;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

class DbConnectorPdo
{
    private static $instances = [];
    private $pdo;

    private function __construct($config)
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['password'], $options);
            $this->pdo->exec("SET SESSION sql_mode = 'IGNORE_SPACE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            // Gérer proprement les erreurs
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance($configName = 'default')
    {
        // Charger une configuration depuis un fichier ou tableau global
        $configs = require __ROOT__ . '/app/db.config.php';
        if (!isset($configs[$configName])) {
            throw new Exception("Configuration '$configName' non trouvée");
        }

        if (!isset(self::$instances[$configName])) {
            self::$instances[$configName] = new self($configs[$configName]);
        }

        return self::$instances[$configName];
    }

    public function query(string $sql): PDOStatement | false
    {
        return $this->pdo->query($sql);
    }

    public function prepare(string $sql): PDOStatement | false
    {
        return $this->pdo->prepare($sql);
    }

    public function execute(array $params)
    {
        return $this->pdo->execute($params);
    }

//    public function fetchAll($sql, $params = [])
//    {
//        $stmt = $this->execute($sql, $params);
//        return $stmt->fetchAll();
//    }
//
//    public function fetchRow($sql, $params = [])
//    {
//        $stmt = $this->execute($sql, $params);
//        return $stmt->fetch();
//    }
//
//    public function fetchColumn($sql, $params = [], $columnIndex = 0)
//    {
//        $stmt = $this->execute($sql, $params);
//        return $stmt->fetchColumn($columnIndex);
//    }
//
//    public function numRows($sql, $params = [])
//    {
//        $stmt = $this->execute($sql, $params);
//        return $stmt->rowCount();
//    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * Génère une clause SQL IN sécurisée avec placeholders et un tableau de paramètres.
     *
     * @param string   $column    Nom de la colonne SQL (ex: "region")
     * @param array    $values    Valeurs à inclure dans le IN
     * @param string   $prefix    Préfixe des placeholders (par défaut : "val")
     * @return array   [string $clauseSQL, array $params] : clause WHERE et tableau pour execute()
     */
    public function buildInClause(string $column, array $values, string $prefix = 'val'): array {
        if (empty($values)) {
            // Pour éviter une clause IN () vide qui planterait
            return ['1=0', []]; // ou "FALSE"
        }

        $placeholders = [];
        $params = [];

        foreach ($values as $i => $value) {
            $ph = ":{$prefix}_$i";
            $placeholders[] = $ph;
            $params[$ph] = $value;
        }

        $clause = "$column IN (" . implode(', ', $placeholders) . ")";
        return [$clause, $params];
    }

}
