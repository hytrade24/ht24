<?php

class Legacy_MySQLQuery {

    /**
     * @var PDOStatement $statement
     */
    protected $statement = null;
    protected $statementCursor = null;
    
    public function __construct($query, $cursorScroll = false, $options = [], $connection = null)
    {
        if ($cursorScroll) {
            $queryOptions[ PDO::ATTR_CURSOR ] = PDO::CURSOR_SCROLL;
            $this->statementCursor = 0;
        }
        $this->statement = Legacy_MySQLConnection::pdoPrepare($query, $options, $connection);
    }
    
    public function execute($inputParams = []) {
        return $this->statement->execute($inputParams);
    }
    
    public function error() {
        list($codeState, $codeMySQL, $strError) = $this->statement->errorInfo();
        return $strError;
    }
    
    public function errno($connection = null, $statement = null) {
        list($codeState, $codeMySQL, $strError) = $this->statement->errorInfo();
        return $codeMySQL;
    }
    
    public function num_rows() {
        return $this->statement->rowCount();
    }

    public function num_cols() {
        return $this->statement->columnCount();
    }
    
    public function data_seek($row_number) {
        if ($this->statementCursor === null) {
            error_log("Fatal error! Tried to use 'mysql_data_seek' without enabling the \$cursorScroll option on 'mysql_query'!");
            debug_print_backtrace();
            die("Fatal error! Tried to use 'mysql_data_seek' without enabling the \$cursorScroll option on 'mysql_query'!");
        }
        if ($row_number <= $this->statementCursor) {
            $this->execute();
        }
        $this->statementCursor = $row_number;
    }
    
    public function fetch($type = PDO::FETCH_BOTH) {
        if ($this->statementCursor !== null) {
            return $this->statement->fetch($type, PDO::FETCH_ORI_ABS, $this->statementCursor++);
        } else {
            return $this->statement->fetch($type);
        }
    }

}