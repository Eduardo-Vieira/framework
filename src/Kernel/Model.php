<?php

namespace Kernel;

use \Database\Database;

class Model
{
    public static $connection = false;
    public static $all        = false;

    protected static $db = null;

    // validacao do model automatico.
    public $validate = true;

    public function validateAccess()
    {
        return $this->validate;
    }

    public static function dbConnection()
    {
        $config = autoload_config();

        if (!self::$db) {
            self::$db = new Database();
        }

        !self::$connection ? self::$connection = $config['database']['DB_DEFAULT_CONN'] : false;

        return self::$db;
    }

    public static function getColumns($data, $where = true, $ignore = [])
    {
        // ser verdadeiro, forca a perda de filtros
        if ($data['filter']) {
            unset($data['filter']);
            return false;
        } else {
            unset($data['filter']);
        }

        // remove campos do filtro
        if($ignore) {
          foreach($data as $key => $value) {
            foreach($ignore as $k) {
              if($key === $k) {
                unset($data[$key]);
              }
            }
          }
        }

        foreach ($data as $key => $value) {

            // da prioridade de pesquisa na versao mobile
            if(in_array('TX_PESQUISA', array_keys($data))) {
                if($value) {
                    $value_all = $value;
                }
                unset($data['TX_PESQUISA']);
                continue;
            }

            // se nao tiver nenhum valor passa pro proximo campo
            if (!$value_all && !$value) {
                continue;
            }

            // ignora paginacao
            if ($key == 'p') {
                continue;
            }

            $k = explode(':', $key);

            // prepara as regras de pesquisa por campo
            switch ($k[1]) {
                case 'ANY':
                    $value = '%'.$value.'%';
                break;

                case 'FIRST':
                    $value = '%'.$value;
                break;

                case 'LAST':
                    $value = $value.'%';
                break;

                case 'THIS':
                    $value = $value;
                break;

                default:
                    $value = $value.'%';
            }

            if($where === false) {
            // pesquisa por todos os campos
            if($value_all) {
                $value = '%'. $value_all .'%';
                $opt   = 'OR';
            } else {
                // pesquisa por campos combinados
                $opt = 'AND';
            }
        } else {
            $value = (!$value_all) ? $value : '%'. $value_all .'%';
            $opt = ' WHERE ';
            $where = false;
        }

            // monta a query
            $columns[] = " $opt (UPPER($k[0]) LIKE '$value') ";
        }

        return ($columns) ? implode(' ', $columns) : false;
    }

    public static function getColumnsKey($keys = [], $value, $type = 'AND')
    {
        if (self::$all) {
            return false;
        }

        foreach ($keys as $index => $key) {
            $k = explode(':', $key);

            switch ($k[1]) {
                case 'ANY':
                    $val = '%'.$value.'%';
                break;

                case 'FIRST':
                    $val = '%'.$value;
                break;

                case 'LAST':
                    $val = $value.'%';
                break;

                case 'THIS':
                    $val = $value;
                break;

                default:
                    $val = $value.'%';
            }

            ($key && $value) ? $search[] = " $type (UPPER($k[0]) LIKE '$val') " : false;

            unset($val);
        }

        return ($search) ? implode(' ', $search) : false;
    }

    public static function getError()
    {
        return implode('<br/>', self::dbConnection()->getInstance(self::$connection)->getError());
    }

    public static function query($sql, $type = null)
    {
        return self::dbConnection()->getInstance(self::$connection)->query($sql, $type);
    }

    public static function find($table, $where = null)
    {
        return self::dbConnection()->getInstance(self::$connection)->find($table, $where);
    }

    public static function insert($table, $data)
    {
        return self::dbConnection()->getInstance(self::$connection)->insert($table, $data);
    }

    public static function update($table, $data, $where)
    {
        return self::dbConnection()->getInstance(self::$connection)->update($table, $data, $where);
    }

    public static function delete($table, $where)
    {
        return self::dbConnection()->getInstance(self::$connection)->delete($table, $where);
    }

    public static function execute($sql)
    {
        return self::dbConnection()->getInstance(self::$connection)->execute($sql);
    }
}
