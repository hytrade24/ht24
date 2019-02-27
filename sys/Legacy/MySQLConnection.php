<?php

class Legacy_MySQLConnection {

    /**
     * @var PDO $handle 
     */
    protected static $handle = null;

    /**
     * @var PDOStatement $statement
     */
    protected static $statement = null;
    protected static $statementCursor = 0;
    
    public static function connect($server = null, $user = null, $password = null, $new_link = false, $client_flags = 0) {
        if ($server === null) {
            $server = ini_get("mysql.default_host");
        }
        if ($user === null) {
            $user = ini_get("mysql.default_user");
        }
        if ($password === null) {
            $password = ini_get("mysql.default_password");
        }
        self::$handle = new PDO("mysql:host=".$server, $user, $password);
        return self::$handle;
    }
    
    public static function select_db($database, $connection = null) {
        // TODO: Find a proper way for escaping (no security issue, since injections here would require file access anyway)
        return self::pdoQuery("USE `".$database."`", PDO::FETCH_NUM, $connection);
    }
    
    public static function set_charset($charset, $connection = null) {
        return self::pdoQuery("SET NAMES ".self::pdoQuote($charset), PDO::FETCH_NUM, $connection);
    }
    
    public static function error($connection = null, $statement = null) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        if ($statement === null) {
            $statement = self::$statement;
        }
        if ($statement instanceof Legacy_MySQLQuery) {
            return $statement->error();
        } else {
            list($codeState, $codeMySQL, $strError) = $connection->errorInfo();
            return $strError;
        }
    }
    
    public static function errno($connection = null, $statement = null) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        if ($statement === null) {
            $statement = self::$statement;
        }
        if ($statement instanceof Legacy_MySQLQuery) {
            return $statement->errno();
        } else {
            list($codeState, $codeMySQL, $strError) = $connection->errorInfo();
            return $codeMySQL;
        }
    }
    
    public static function query($query, $connection = null, $cursorScroll = false) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        $queryOptions = [];
        if ($cursorScroll) {
            $queryOptions[ PDO::ATTR_CURSOR ] = PDO::CURSOR_SCROLL;
        }
        $query = new Legacy_MySQLQuery($query, $cursorScroll, [], $connection);
        self::$statement = $query;
        if (!$query->execute()) {
            return false;
        }
        if ($query->num_cols() > 0) {
            return $query;
        } else {
            return true;
        }
    }
    
    public static function escape_string($string) {
        return substr(self::pdoQuote($string), 1, -1);
    }
    
    public static function num_rows(Legacy_MySQLQuery $result) {
        return $result->num_rows();
    }
    
    public static function affected_rows($connection = null) {
        return self::num_rows(self::$statement);
    }
    
    public static function insert_id($connection = null) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        return $connection->lastInsertId();
    }
    
    public static function data_seek(Legacy_MySQLQuery $result, $row_number) {
        $result->data_seek($row_number);
    }
    
    public static function fetch(Legacy_MySQLQuery $result, $type = PDO::FETCH_BOTH) {
        return $result->fetch($type);
    }
    
    public static function fetch_array(Legacy_MySQLQuery $result, $type = PDO::FETCH_BOTH) {
        return self::fetch($result, $type);
    }
    
    public static function fetch_assoc(Legacy_MySQLQuery $result) {
        return self::fetch_array($result, PDO::FETCH_ASSOC);
    }
    
    public static function fetch_object(Legacy_MySQLQuery $result) {
        return self::fetch_array($result, PDO::FETCH_OBJ);
    }
    
    public static function fetch_row(Legacy_MySQLQuery $result) {
        return self::fetch_array($result, PDO::FETCH_NUM);
    }
    
    public static function pdoPrepare($statement, $options = [], $connection = null) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        return $connection->prepare($statement, $options);
    }
    
    public static function pdoQuery($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $connection = null) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        return $connection->query($statement, $mode);
    }
    
    public static function pdoQuote($string, $type = PDO::PARAM_STR, $connection = null) {
        if ($connection === null) {
            $connection = self::$handle;
        }
        return $connection->quote($string, $type);
    }
    
}