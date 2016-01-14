<?php

class Database
{

    protected $_dbname;
    protected $_connection;

    function __construct($host, $username, $password, $dbname) {

        if(!$this->_connection) {
            // load mysqli extension
            if(extension_loaded('mysqli')) {
                // set connection
                $this->_connection = mysqli_connect($host, $username, $password, $dbname);
                // check connection
                if (mysqli_connect_errno()) {
                    printf("Connect failed: %s\n", mysqli_connect_error());
                    exit();
                }
                // set character set
                if (!mysqli_set_charset($this->_connection, "utf8mb4")) {
                    printf("Error loading character set utf8: %s\n", mysqli_error($this->_connection));
                }
            }
        }
        // return
        return (!$this->_connection) ? false : $this;
    }

    public function getDbName() {
        return $this->_dbname;
    }

    public function getConnection() {
        return $this->_connection;
    }
}
?>
