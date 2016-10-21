<?php

namespace Database\Driver;

use \PDOException;
use \Service\Debug\Debug;

class Oci implements \Database\Interfaces\PersistenceDatabase
{
    protected static $config = [];
    protected static $error  = [];
    protected $connection    = [];

    protected static $queries = [];

    public function __construct($connection, $database, $host, $port, $username, $password)
    {
        $this->connect($connection, $database, $host, $port, $username, $password);
    }

    public static function getError()
    {
        return self::$error;
    }

    public function connect($connection, $database, $host, $port, $username, $password)
    {
        self::$config = autoload_config();

        // rename
        $current = $connection;

        try {
            $tns = "(DESCRIPTION =
                        (ADDRESS_LIST =
                            (ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))
                        )
                        (CONNECT_DATA =
                            (SERVICE_NAME = $database)
                        )
                    )";

            $this->connection[$current] = new \PDO("oci:dbname=$tns;charset=UTF8", $username, $password);
            $this->connection[$current]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            if($nls = self::$config['database']['DB_OCI_NLS']) {
              foreach($nls as $key => $value) {
                self::$config['database']['DB_OCI_NLS'][$key] ?
                  $this->connection[$current]->exec("ALTER SESSION set $key='$value'")
                : false;
              }
            }

            Debug::collectorPDO($this->connection[$current]);
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }

        return $this;
    }

    public function getQueries() {
        return self::$queries;
    }

    public function setQuery($sql) {
        $sql = self::getError() ? self::getError() : $sql;

        if(!is_array($sql)) {
            if(preg_match("/select/i", $sql)) {
                $reg = 'SELECT';
            } else if(preg_match("/insert/i", $sql)) {
                $reg = 'INSERT';
            } else if(preg_match("/delete/i", $sql)) {
                $reg = 'DELETE';
            } else if(preg_match("/update/i", $sql)) {
                $reg = 'UPDATE';
            } else {
                $reg = 'SQL';
            }
        } else {
            $reg = 'ERRO';
        }
        self::$queries[date('d/m/Y G:i:s')." ($reg)"] = $sql;
    }

    public function query($sql, $type = null)
    {
        $this->setQuery($sql);

        foreach ($this->connection as $connection);

        if(!$connection) {
            return [['ERRO' => translate('app', 'database.erro1', [
                1 => self::getError(),
                ])]];
        }

        try {
            $sth = $connection->prepare($sql);
            $sth->execute();

            switch ($type) {
                case 'json':
                    return json_encode($sth->fetchAll(self::$config['database']['DB_FETCH']));
                break;

                default:
                    return $sth->fetchAll(self::$config['database']['DB_FETCH']);
            }
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }
    }

    public function find($table, $where = null)
    {
        foreach ($this->connection as $connection);

        if(!$connection) {
            return [['ERRO' => translate('app', 'database.erro1', [
    1 => self::getError(),
    ])]];
        }

        try {
            if (!empty($table)) {
                $table = rtrim($table);
            }
            if (!is_null($where)) {
                $where = rtrim("WHERE $where");
            }

            $sql = "SELECT * FROM $table $where";

            $this->setQuery($sql);

            $sth = $connection->prepare($sql);
            $sth->execute();

            return $sth->fetchAll(self::$config['database']['DB_FETCH']);
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }
    }

    public function insert($table, $data)
    {
        foreach ($this->connection as $connection);

        if(!$connection) {
            return [['ERRO' => translate('app', 'database.erro1', [
    1 => self::getError(),
    ])]];
        }

        try {
            $fieldNames = implode(',', array_keys($data));

            foreach ($data as $key => $value) {
                $fieldValues .= ":$key,";
            }

            $fieldValues = rtrim($fieldValues, ',');

            $sql = "INSERT INTO $table ($fieldNames) VALUES ($fieldValues)";

            $sth = $connection->prepare($sql);

            foreach ($data as $key => $value) {
                $sth->bindValue(":$key", $value);
                $values .= "'$value',";
            }

            $values = rtrim($values, ',');

            $this->setQuery("INSERT INTO $table ($fieldNames) VALUES ($values)");

            return $sth->execute();
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }
    }

    public function update($table, $data, $where)
    {
        foreach ($this->connection as $connection);

        if(!$connection) {
            return [['ERRO' => translate('app', 'database.erro1', [
    1 => self::getError(),
    ])]];
        }

        try {
            ksort($data);

            $fieldDetails = null;

            foreach ($data as $key => $value) {
                $fieldDetails .= "$key=:$key,";
                $values .= "$key='$value',";
            }

            $fieldDetails = rtrim($fieldDetails, ',');
            $values       = rtrim($value, ',');

            $sql = "UPDATE $table SET $fieldDetails WHERE $where";

            $sth = $connection->prepare($sql);

            foreach ($data as $key => $value) {
                $sth->bindValue(":$key", $value);
            }

            $this->setQuery("UPDATE $table SET $values");

            return $sth->execute();
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }
    }

    public function delete($table, $where)
    {
        foreach ($this->connection as $connection);

        if(!$connection) {
            return [['ERRO' => translate('app', 'database.erro1', [
    1 => self::getError(),
    ])]];
        }

        try {
            $sql = "DELETE FROM $table WHERE $where";

            $this->setQuery($sql);

            return $connection->exec($sql);
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }

        if (self::$error) {
            return self::$error;
        } else {
            return false;
        }
    }

    public function execute($sql)
    {
        foreach ($this->connection as $connection);

        if(!$connection) {
            return [['ERRO' => translate('app', 'database.erro1', [
    1 => self::getError(),
    ])]];
        }

        try {
            return $connection->exec($sql);
        } catch (PDOException $e) {
            self::$error[] = $e->getMessage();
            Debug::getInstance('exceptions')->addException($e);
        }
    }
}
