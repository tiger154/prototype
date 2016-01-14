<?php

class Core
{

    protected static $db;               // db instance
    protected static $config = array(); // db config

    protected $connection;       // db connection
    protected $option = array(); // app versions

    public static $cache;               // global caching object
    public static $request = array();   // request array


    public function __construct($config) {
        static::$config = $config;
        static::$request = $this->request();
        static::$cache = new Cache();
        $this->connection = static::connect();
        if (isset($_REQUEST['upgrade'])) { $this->checkDBVersion(); }
        if (isset($_REQUEST['cssbump'])) { $this->setOption('cssv', time()); }
        if (rand(0,100) <= 10) { Auth::cleanSessions(); } // 10% chance; should be moved to a cron job
    }

    public function request() {
        $request = Router::run();
        $matches = [];
        foreach($request['matches'] as $k => $v) {
            if(is_int($k)) { continue; }
            $matches[$k] = $v;
        }
        unset($request['matches'], $request['pattern']);
        $request = array_merge($request, $matches);
        foreach($request as $k => $v) {
            $request[$k] = $v;
        }
        $request['action'] = (isset($request['action']) && !empty($request['action']))  ? $request['action'] : 'index' ; // set default action
        if(!empty($_REQUEST)) {
            $request['params'] = $_REQUEST;
        }
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // check if ajax call
            $request['ajax'] = true;
        }
        return $request;
    }

    private static function connect() {
        if(!empty(self::$config)) {
            if($connection = new Database(self::$config['host'], self::$config['user'], self::$config['password'], self::$config['db'])) {
                return $connection;
            } else {
                die("No (valid) database settings defined or db down!");
            }
        } else {
            die("No database settings defined!");
        }
    }

    /**
     * Returns new cmysql class for custom SQL querying, based on current database connection
     * @return cMySql
     */
    public static function getDb($fresh=false) {
        if ($fresh || !isset(self::$db)) {
            static::$db = new MySQL(static::connect());
            return static::$db;
        }
        return static::$db;
    }

    private function checkDBVersion() {
       $db = static::getDb();
       $changes = file_get_contents(BASE_DIR . '/changes.sql.txt');
       $changes = explode("\n", $changes);
       $statements = array();
       foreach ($changes as $line) {
            if (preg_match('/=>VERSION_([0-9]{11})/', $line, $matches)) {
                $version = $matches[1];
                $statements[$version] = array();
            } else {
                if (trim($line)!='')
                    $statements[$version][] = $line;
            }
       }
       foreach ($statements as $ix => $lines) {
            $lines = join("\n", $lines);
            $lines = explode("++", $lines);
            $statements[$ix] = $lines;
       }
       $db_version = $this->getOption('db_version');
       foreach ($statements as $version => $sql) {
            if ($db_version < $version) {
                foreach ($sql as $statement) {
                    $db->sqlQuery($statement);
                }
                $this->setOption('db_version', $version);
                $db_version = $version;
            }
       }
    }

    private function getOption($key) {
        $db = static::getDb();
        if (!isset($this->option[$key])) {
            $this->option[$key] = $db->sqlFetchField("SELECT `value` FROM `{option}` WHERE `key`='$key'");
        }
        return $this->option[$key];
    }

    private function setOption($key, $value) {
        $db = static::getDb();
        $row = array('key' => $key, 'value' => $value);
        $db->sqlReplaceInto('{option}', $row);
        $this->option[$key] = $value;
    }

    public function trace($string) {
        if(is_array($string)) $string = print_r($string, true);
        error_log($string);
    }

    public function redirect($url, $params='') {
        if (preg_match('/^:/', $url)) {
            $url = Router::url($url);
        }
        if ($params) {
            $url .= ('?' . $params);
        }
        header("Location: $url");
        die();
    }

    public static function convert($size) {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    public static function profiler() {

        global $profilerStartBenchmark, $profilerClassAllocatedSize;

        $profiler = [];
        $profiler['end'] = round(microtime(true) - $profilerStartBenchmark, 2);
        $profiler['memReal'] = self::convert(memory_get_usage(true));
        $profiler['memAlloc'] = self::convert(memory_get_usage());
        $profiler['memPeakReal'] = self::convert(memory_get_peak_usage(true));
        $profiler['memPeakAlloc'] = self::convert(memory_get_peak_usage());
        $profiler['request'] = self::$request;
        $totalSize = 0; $classes=[];
        foreach ($profilerClassAllocatedSize as $name => $size) {
            $totalSize += $size;
            $profiler['classes'][$name] = self::convert($size);
        }
        $profiler['totalSize'] = self::convert($totalSize);
        return $profiler;
    }
}

?>
