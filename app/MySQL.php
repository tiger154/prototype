<?php

class MySQL
{

    protected $_database;
    protected $_last_result;
    protected $_auto_add_slashes = true;

    public static $_new_queries = 0;
    public static $_cached_queries = 0;

    function __construct($database) {
        $this->_database = $database;
    }

    /**
     * Replaces {table_name} with prefix_table_name
     *
     * @param mixed $query
     * @return
     */
    protected function _expandTables($query) {
       $prefix = '';
       $query = preg_replace('/{([a-z0-9_]+)}/i', $prefix . '$1', $query);
       return $query;
    }

    protected function _raiseError($error_msg) {
        debug_print_backtrace();
        trigger_error(
            "MySQL Error: ".mysqli_errno($this->_database->getConnection())." (".mysqli_error($this->_database->getConnection()).")
            </br>\n
            Details: " . $error_msg . " ", E_USER_ERROR
        );

    }

    public function sqlQuery($query) {
        $query = $this->_expandTables($query);
        $this->_last_result = mysqli_query($this->_database->getConnection(), $query) or $this->_raiseError("Invalid SQL: " . $query);
        return $this->_last_result;
    }

    public function sqlFetchField($query) {
        $row = $this->sqlFetchRow($query);
        if (is_array($row)) {
            return array_shift($row); // first element of the returned array
        }
        return false;
    }

    public function sqlFetchRow($query, $useCache=true, $type=MYSQL_ASSOC) {
        $cacheKey = md5($query.$type);
        if($useCache && Core::$cache->exists('mysql',$cacheKey)) { // check if cached
            self::$_cached_queries++;
            $data = unserialize(Core::$cache->get('mysql',$cacheKey));
        } else {
            $result = $this->sqlQuery($query);
            $data = mysqli_fetch_array($result, $type);
            if($useCache) { Core::$cache->add('mysql', $cacheKey, serialize($data)); } // cache it
            self::$_new_queries++;
        }
        return $data;
    }

    public function sqlFetchCol($query) {
        $data = $this->sqlFetchAll($query);
        $col = array();
        foreach ($data as $row) {
            $col[] = reset($row);
        }
        return $col;
    }

    public function sqlFetchAll($query, $useCache=true, $type=MYSQL_ASSOC) {
        $cacheKey = md5($query.$type);
        if($useCache && Core::$cache->exists('mysql',$cacheKey)) { // check if cached
            self::$_cached_queries++;
            $data = unserialize(Core::$cache->get('mysql',$cacheKey));
        } else {
            $result = $this->sqlQuery($query);
            $data = [];
            while ($row = mysqli_fetch_array($result, $type)) {
                $data[] = $row;
            }
            $this->sqlFreeResult($result);
            if($useCache) { Core::$cache->add('mysql', $cacheKey, serialize($data)); } // cache it
            self::$_new_queries++;
        }
        return $data;
    }

    public function sqlInsertId() {
        return mysqli_insert_id($this->_database->getConnection());
    }

    public function sqlNextRow($type=MYSQL_ASSOC) {
        return mysqli_fetch_array($this->_last_result, $type);
    }

    public function sqlNumRows() {
        return mysqli_num_rows($this->_last_result);
    }

    public function sqlFreeResult($result) {
        mysqli_free_result($result);
    }

    protected function _prepare4query($fields, &$sqlfields, &$sqlvalues) {
        $sqlfields = $sqlvalues = [];
        if (!is_array($fields)) return;
        foreach($fields as $key => $value) {
            $sqlfields[] = $key;
            if ($this->_auto_add_slashes && !is_array($value)) {
                $value = addslashes($value);
            }
            $sqlvalues[] = $value;
        }
        $sqlfields = join('`,`', $sqlfields);
        $sqlvalues = join("','", $sqlvalues);
    }

    function sqlInsertInto($table, $fields) {
        $this->_prepare4query($fields, $sqlfields, $sqlvalues);
        $this->sqlQuery($sql = "INSERT INTO `$table` (`$sqlfields`) VALUES ('$sqlvalues')");
        return $this->sqlInsertId();
    }

    function sqlReplaceInto($table, $fields) {
        $this->_prepare4query($fields, $sqlfields, $sqlvalues);
        $this->sqlQuery("REPLACE INTO `$table` (`$sqlfields`) VALUES ('$sqlvalues')");
    }

    /**
     * Creates a "SELECT FROM `table` WHERE .. conditions .." and returns the query results;
     * $conditions format: id=15&some_field='value' or as an array: array('id' => 15, 'some_field' => 'value')
     * $more_sql e.q. "ORDER BY id DESC"
     *
     * @param string $table
     * @param mixed $conditions
     * @param string $select_fields
     * @param string $more_sql
     * @return array
     */
    function sqlSelectAll($table, $conditions=1, $select_fields='*', $more_sql='') {
        $where = [];
        if (is_array($conditions)) {
            foreach ($conditions as $field=>$value) {
                if ($this->_auto_add_slashes && is_string($value)) {
                    $value = add_slashes($value);
                }
                $value = is_string($value) ? "'" . $value . "'" : $value;
                $where[] = "`$field`=$value";
            }
        } else {
            $where = explode('&', $conditions);
        }
        // build the query
        $query = "SELECT $select_fields FROM $table WHERE " . join(" AND ", $where) . " " . $more_sql;
        return $this->sqlFetchAll($query);
    }

    /**
     * Creates a "SELECT FROM `table` WHERE .. conditions .." and returns the first row of the query results;
     * $conditions format: id=15&some_field='value' or as an array: array('id' => 15, 'some_field' => 'value')
     * $more_sql e.q. "ORDER BY id DESC"
     *
     * @uses function sqlSelectAll()
     * @param string $table
     * @param mixed $conditions
     * @param string $select_fields
     * @param string $more_sql
     */
    function sqlSelect($table, $conditions=1, $select_field='*', $more_sql='') {
        $results = $this->sqlSelectAll($table, $conditions, $select_field, $more_sql);
        return array_shift($results);
    }

}

?>
