<?php
namespace Db;

/**
 *
 * @property mixed insert_id
 * @property int num_rows
 * @property int rows_affected
 *          
 */
class Db extends \Ufw\Db\Db
{

    
    const DB_ASSOC = PGSQL_ASSOC;
    
    const DB_NUM = PGSQL_NUM;
    
    const DB_BOTH = PGSQL_BOTH;
    
    
    /**
     *
     * Magic method __get(), specially supported values:
     * - insert_id - returns last inserted id
     * - num_rows - no of rows in resultset (of last select statement)
     * - rows_affected - returns number of affected rows in last insert/update/delete statement 
     *
     * @param string $name
     */
    public function __get($name)
    {
        switch ($name) {
            case 'insert_id':
                list ($value) = pg_fetch_array(pg_query($this->last_used_conn['resource'], "SELECT lastval()"), null, self::DB_NUM);
                break;
            case 'num_rows':
                if (is_resource($this->result))
                    $value = pg_num_rows($this->result);
                else
                    $value = null;
                break;
            case 'rows_affected':
                $value = pg_affected_rows($this->last_used_conn['resource']);
                break;
            default:
                $value = null;
        }
        return $value;
    }

    public function set_connection($conn_ident, $host, $user, $pass, $autoconnect = false, $dbname = false, $newlink = false)
    {
        $this->db_connections[$conn_ident] = array(
            'ident' => $conn_ident,
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'dbname' => $dbname,
            'newlink' => $newlink
        );
        if ($autoconnect) {
            $this->connect($conn_ident);
        }
    }

    /**
     * Konektuje se na bazu ako nije ranije ili ako je postavljen reconnect parametar
     *
     * @param mixed $conn_ident
     *            resource ili string
     * @param boolean $reconnect
     * @return resource
     */
    public function connect($conn_ident, $reconnect = false)
    {
        if (is_resource($conn_ident) && !$reconnect) {
            pg_ping($conn_ident);
            return $conn_ident;
        } else {
            $cn = & $this->db_connections[$conn_ident];
            if ($cn && (!$cn['resource'] || $reconnect)) {
                if (!isset($cn['port'])) {
                    $cn['port'] = '5432';
                }
                $cn['resource'] = pg_connect("host={$cn['host']} port={$cn['port']} dbname={$cn['dbname']} user={$cn['user']} password={$cn['pass']}");
                $this->last_used_conn = & $this->db_connections[$conn_ident];
            }
            return $cn['resource'];
        }
    }

    public function fetch_array($result, $mode = self::DB_ASSOC)
    {
        return pg_fetch_array($result, null, $mode);
    }

    public function fetch_object($result, $class_name = "stdClass", array $params = array())
    {
        if (is_subclass_of($class_name, \Ufw\Model\Record\Base::class)) {
            $data = pg_fetch_array($result, null, self::DB_ASSOC);
            return $data ? $class_name::dbfactory($data)->setIsNewRecord(false) : null;
        } else {
            return pg_fetch_object($result, null);
        }
    }

    public function free()
    {
        pg_free_result($this->result);
    }

    public function simple_insert_update($table_name, $params, $conn_ident = null, $file_invoker = NULL, $line_invoker = NULL, $query_execute = true)
    {
        throw new \Exception('unsupported');
    }


    /**
     *
     * @param string $query
     * @param mixed $conn_ident
     *            string ili resource - identifikacije konekcije ili sam resurs konekcije
     * @param string $file_invoker
     * @param string $line_invoker
     * @param integer $retries
     */
    public function query($query, $conn_ident = null, $file_invoker = null, $line_invoker = null, $retries = 0)
    {
        if ($this->query_log) {
            $this->sw->reset();
        }
        
        if (is_resource($conn_ident)) {
            $dbhandle = $conn_ident;
        } else {
            if (!$conn_ident && !$this->last_used_conn)
                $conn_ident = 'default';
            if (isset($conn_ident) && $conn_ident && strcmp($conn_ident, '__last_used')) {
                $this->last_used_conn = & $this->db_connections[$conn_ident];
            }
            $dbhandle = $this->connect($this->last_used_conn['ident']);
        }
        $this->last_query = $query;
        // echo $query, "<br>";
        $this->result = pg_query($dbhandle, $query);
        $this->errno = pg_last_error($dbhandle);
        if (pg_result_error_field($this->result, PGSQL_DIAG_SQLSTATE) == '23505') { // duplicate entry
            $str = "Date and time: " . date("Y-m-d H:i:s") . "\n" . "Conn: {$this->last_used_conn['ident']}\n" . "Query: " . $query . "\n" . "Error: " . $this->errno . "\n" . ($file_invoker && $line_invoker ? "File: Line $line_invoker in $file_invoker\n" : "") . "http://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}?{$_SERVER['QUERY_STRING']}\n" . ($_SERVER['HTTP_REFERER'] ? "Referer: {$_SERVER['HTTP_REFERER']}\n" : '') . ($_SERVER['HTTP_USER_AGENT'] ? "Browser: {$_SERVER['HTTP_USER_AGENT'] }\n" : '');
            if ($_POST) {
                $str .= "POST: ";
                foreach ($_POST as $key => $value)
                    $str .= "$key=$value&";
                $str .= "\n";
            }
            error_log("$str\n", 3, $this->logfile);
        }
        
        if ($this->query_log) {
            $query_type = self::QT_SELECT;
            if (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
                $query_type = self::QT_UPDATE;
                if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
                    $query_type = self::QT_INSERT;
                }
            }
            
            $this->query_log_data[] = array(
                'query' => $query,
                'rows' => $this->errno ? 'err: ' . $this->errno : ($query_type == QT_SELECT ? $this->num_rows : $this->rows_affected),
                'file' => preg_replace('~^' . preg_quote(APPLICATION_PATH, '~') . '~', '', $file_invoker),
                'line' => $line_invoker,
                'time' => $this->sw->elapsed_hr(),
                'total' => $this->sw_total->elapsed_hr()
            );
        }
        
        return $this->result;
    }

    /**
     * Staticka funkcija
     * Vraca escape-ovan $value, pogodan za upotrebu u sql stejtmentima
     *
     * @param mixed $value
     * @return string
     */
    public static function quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        } elseif (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = static::quote($val);
            }
            return $value;
        } elseif (is_bool($value)) {
            return $value + 0;
        } elseif (!strcmp($value, 'nil') || is_null($value)) {
            return 'NULL';
        } elseif ($value instanceof \Ufw\Db\Expression) {
            return (string) $value;
        }
        if (is_resource(self::instance()->last_used_conn['resource'])) {
            $ret = pg_escape_literal(self::instance()->last_used_conn['resource'], $value);
            if (!$ret && $value) {
                // umrela konekcija, reotvori je
                $resource = self::instance()->connect(self::instance()->last_used_conn['ident'], true);
                if ($resource)
                    $ret = pg_escape_literal($resource, $value);
                else
                    $ret = addslashes($value);
            }
            return "$ret";
        } else {
            return "'" . addslashes($value) . "'";
        }
    }
}
