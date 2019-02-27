<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (version_compare(phpversion(), "7.0.0", ">=")) {
    
    define('MYSQL_ASSOC', PDO::FETCH_ASSOC);
    define('MYSQL_NUM', PDO::FETCH_NUM);
    define('MYSQL_BOTH', PDO::FETCH_BOTH);
    
    require_once __DIR__."/sys/Legacy/MySQLConnection.php";
    require_once __DIR__."/sys/Legacy/MySQLQuery.php";
    
    function mysql_connect($server = null, $user = null, $password = null, $new_link = false, $client_flags = 0) {
        return Legacy_MySQLConnection::connect($server, $user, $password, $new_link, $client_flags);
    }
    
    function mysql_select_db($database, $connection = null) {
        return Legacy_MySQLConnection::select_db($database, $connection);
    }
    
    function mysql_error($connection = null) {
        return Legacy_MySQLConnection::error($connection);
    }
    
    function mysql_errno($connection = null) {
        return Legacy_MySQLConnection::errno($connection);
    }
    
    function mysql_set_charset($charset, $connection = null) {
        return Legacy_MySQLConnection::set_charset($charset, $connection);
    }
    
    function mysql_query($query, $connection = null, $cursorScroll = false) {
        return Legacy_MySQLConnection::query($query, $connection, $cursorScroll);
    }
    
    function mysql_escape_string($string) {
        return Legacy_MySQLConnection::escape_string($string);
    }
    
    function mysql_real_escape_string($string) {
        return Legacy_MySQLConnection::escape_string($string);
    }
    
    function mysql_affected_rows($connection = null) {
        return Legacy_MySQLConnection::affected_rows($connection);
    }
    
    function mysql_insert_id($connection = null) {
        return Legacy_MySQLConnection::insert_id($connection);
    }
    
    function mysql_num_rows($result) {
        if (!$result instanceof Legacy_MySQLQuery) {
            return false;
        }
        return Legacy_MySQLConnection::num_rows($result);
    }
    
    function mysql_data_seek($result, $row_number) {
        if (!$result instanceof Legacy_MySQLQuery) {
            return false;
        }
        return Legacy_MySQLConnection::data_seek($result, $row_number);
    }
    
    function mysql_fetch_array($result, $fetchType = 4) {
        if (!$result instanceof Legacy_MySQLQuery) {
            return false;
        }
        return Legacy_MySQLConnection::fetch_array($result, $fetchType);
    }
    
    function mysql_fetch_assoc($result) {
        if (!$result instanceof Legacy_MySQLQuery) {
            return false;
        }
        return Legacy_MySQLConnection::fetch_assoc($result);
    }
    
    function mysql_fetch_object($result) {
        if (!$result instanceof Legacy_MySQLQuery) {
            return false;
        }
        return Legacy_MySQLConnection::fetch_object($result);
    }
    
    function mysql_fetch_row($result) {
        if (!$result instanceof Legacy_MySQLQuery) {
            return false;
        }
        return Legacy_MySQLConnection::fetch_row($result);
    }

}

?>