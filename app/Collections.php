<?php

class Collections {

    protected static $_instances = array();
    protected static $_underscored = array();
    protected static $_formats = array('array' => 'static::toArray', 'json' => 'static::toJSON');
    protected static $_find = array('all'=>true,'first'=>true,'count'=>true);


    /**
     * Enables magic finders. These provide some syntactic-sugar which allows
     * to i.e. use `Model::all()` instead  of `Model::find('all')`.
     *
     * ```
     * // Retrieves post with id `23` using the `'first'` finder.
     * Posts::first(array('conditions' => array('id' => 23)));
     * Posts::findById(23);
     *
     * // All posts that have a trueish `published` field.
     * Posts::all(array('conditions' => array('is_published' => true)));
     * Posts::findAll(array('conditions' => array('is_published' => true)));
     * Posts::findAllByIsPublshed(true)
     *
     * // Counts all posts.
     * Posts::count()
     * ```
     * @link http://php.net/language.oop5.overloading.php PHP Manual: Overloading
     * @throws BadMethodCallException On unhandled call, will throw an exception.
     * @param string $method Method name caught by `__callStatic()`.
     * @param array $params Arguments given to the above `$method` call.
     * @return mixed Results of dispatched `Model::find()` call.
     */
    public static function __callStatic($method, $params)
    {
        if (isset(self::$_find[$method])) {
            if (count($params) === 2 && is_array($params[1])) {
                $params = array($params[1] + array($method => $params[0]));
            }
            if ($params && !is_array($params[0])) {
                $params[0] = array('conditions' => self::key($params[0]));
            }
            return self::find($method, $params ? $params[0] : array());
        }
        preg_match('/^findBy(?P<field>\w+)$|^find(?P<type>\w+)By(?P<fields>\w+)$/', $method, $args);
        if ($args) {
            $field = self::underscore($args['field'] ? $args['field'] : $args['fields']);
            $type = isset($args['type']) ? $args['type'] : 'first';
            $type[0] = strtolower($type[0]);
            $conditions = array($field => array_shift($params));
            $params = (isset($params[0]) && count($params) === 1) ? $params[0] : $params;
            return self::find($type, compact('conditions') + $params);
        }

        $self = static::_object();
        if (method_exists($self,(string)$method)) { // call protected static methods (pure magic)
            return call_user_func_array(array(&$self, $method), $params);
        }
        $message = "Method `%s` not defined or handled in class `%s`.";
        throw new BadMethodCallException(sprintf($message, $method, get_called_class()));
    }

    /**
     * Magic method that allows calling methods on the model instance.

     * @param string $method Method name caught by `__call()`.
     * @param array $params Arguments given to the above `$method` call.
     * @return mixed
     */
    public function __call($method, array $params = array())
    {
        $self = static::_object();
        if (method_exists($self,(string)$method)) {
            return call_user_func_array(array(&$self, $method), $params);
        }
        $message = "Unhandled method call `{$method}`.";
        throw new BadMethodCallException($message);
    }

    /*
     * name: Events::find
     * @param string|object|integer $type
     *
     * The name of the finder to use. The following finders are available:
     *
     * 'all': Returns all records matching the conditions.
     * 'first': Returns the first record matching the conditions.
     * 'count': Returns an integer with the count of all records matching the conditions.
     *
     * Instead of the name of a finder, also supports for an integer as a first parameter. When passed such a value it is equal to Model::find('first', array('conditions' => array('id' => <value>))).
     * When an undefine finder is tried to be used, the method will not error out, but fallback to the 'all' finder.
     *
     * @param array $options
     *
     * Options for the query. By default, accepts:
     *
     * 'conditions' array: The conditions for the query i.e. 'array('published' => true).
     * 'fields' array|null: The fields that should be retrieved. When set to null and by default, uses all fields. To optimize query performance, limit the fields to just the ones actually needed.
     * 'order' array: The order in which the data will be returned. To sort by multiple fields use the array syntax array('title' => 'ASC', 'id' => 'ASC).
     * 'limit' integer: The maximum number of records to return.
     * 'offset' integer: Pagination (also provide the limit).
    */
    public static function find($type='', array $options = array()) {
        $where = $order = $joins = '';
        if ($type && is_numeric($type)) {
            $options['conditions'] = ['id' => (int)$type];
            $type = 'first';
        }
        if(Core::$cache->exists('collections_'.get_called_class(),json_encode($options))) { // check if cached
            $result = Core::$cache->get('collections_'.get_called_class(),json_encode($options));
        } else {
            $table = isset(static::$source) ? static::$source : strtolower(get_called_class());
            if(isset($options['conditions']) && is_array($options['conditions'])) {
                $conditions = self::_mapConditions(self::_cleanOperators($options['conditions']), $table);
                $ac = [];
                foreach($conditions as $k => $v) {
                    if($k==='AND') {
                        $ac['AND'][] = '('.implode(' AND ',$v).')';
                    } else if ($k==='OR' && (count($v)===1)) {
                        $ac['OR'][] = "OR $v[0]";
                    } else if($k==='OR') {
                        $ac['AND'][] = '('.implode(' OR ',$v).')';
                    } else if($k==='MATCH' && (count($v)===1)) {
                        $ac['MATCH'][] = "$v[0]";
                    } else if($k==='MATCH') {
                        $ac['MATCH'][] = implode(' AND ',$v);
                    } else {
                        $ac['AND'][] = implode(' AND ',$v);
                    }
                }
                if(!empty($ac)) { $where = ' WHERE'; }
                if(!empty($ac['MATCH'])) { $where .= ' '.implode(' AND ',$ac['MATCH']).' AND'; }
                if(!empty($ac['AND'])) { $where .= ' '.implode(' AND ',$ac['AND']); }
                if(!empty($ac['OR'])) { $where .= ' '.implode(' OR ',$ac['OR']); }
            }
            if($type == 'count') {
                $fields = 'COUNT(*) as count';
            } else {
                $fields = '*';
                if(isset($options['fields']) && is_array($options['fields'])) {
                    $fields = [];
                    foreach($options['fields'] as $field) {
                        $fields[] = (strpos('.',$field) === false) ? $table.'.'.$field : $field;
                    }
                    $fields = implode(",",$fields);
                }
                if(isset($options['order'])) {
                    if(is_array($options['order'])) {
                        $arr = [];
                        foreach ($options['order'] as $key => $val) {
                            if($find = ucfirst(stristr($key, '.', true))) {
                                $ok = (class_exists($find) && isset($find::$source)) ? $find::$source.stristr($key,'.') : strtolower($find).stristr($key,'.');
                            } else {
                                $ok = $table.'.'.$key; // default class name is table name
                            }
                            $arr[]= $ok.' '.$val;
                        }
                        $order = ' ORDER BY '.implode(',',$arr);
                    }
                }
                $limit = isset($options['limit']) && is_numeric($options['limit']) ? ' LIMIT '.(int)$options['limit'] : ($type == 'first' ? ' LIMIT 1' : '');
                $offset = isset($options['offset']) && is_numeric($options['offset']) && !empty($limit) ? ' OFFSET '.(int)$options['offset'] : '';
            }
            if(isset($options['joins']) && is_array($options['joins'])) {
                $joins = [];
                foreach($options['joins'] as $model => $opts) {
                    $model = (property_exists($model,'source') && isset($model::$source)) ? $model::$source : strtolower($model);
                    $constraints = self::_mapJoins(self::_mapConditions(self::_cleanOperators($opts['constraints']), $model));
                    $joins[] = ' INNER JOIN '.$model.' '.$model.' ON '.$constraints;
                }
                $joins = implode(' ', $joins);
            }
            $sql = sprintf("SELECT %s FROM %s %s%s%s%s%s%s", $fields, $table, $table, $joins, $where, $order, $limit, $offset);
            error_log("sql: $sql");
            if(isset($options['objects']) && $options['objects'] == false) {
                $result = Core::getDb()->sqlFetchAll($sql);
            } else {
                $rows = Core::getDb()->sqlFetchAll($sql);
                if(!empty($rows)) {
                    $class = get_called_class();
                    if(count($rows) === 1) { // return 1 object
                        $result = $class::create($rows[0]);
                    } else { // return array of objects
                        $result = [];
                        foreach($rows as $data) {
                            $result[] = $class::create($data);
                        }
                    }
                }
            }
            Core::$cache->add('collections_'.get_called_class(), json_encode($options), $result); // cache it
        }
        return $result;
    }

    protected static function create(array $_data = array()) {
        $class = get_called_class();
        if(class_exists($class)) {
            return new $class($_data);
        }
        die("class '$class' was not found");
    }

    protected function save($data = null) {
        $self = static::_object();
        var_dump($self);
        $params = compact('data');
        var_dump($params);
        exit;
    }

    private static function _mapJoins($joins) {
        $ac = [];
        foreach($joins as $k => $v) {
            if($k==='AND') {
                $ac['AND'][] = '('.implode(' AND ',$v).')';
            } else if ($k==='OR' && (count($v)===1)) {
                $ac['OR'][] = "OR $v[0]";
            } else if($k==='OR') {
                $ac['AND'][] = '('.implode(' OR ',$v).')';
            } else if($k==='MATCH' && (count($v)===1)) {
                $ac['MATCH'][] = "$v[0]";
            } else if($k==='MATCH') {
                $ac['MATCH'][] = implode(' AND ',$v);
            } else {
                $ac['AND'][] = implode(' AND ',$v);
            }
        }
        if(!empty($ac['MATCH'])) { $on .= ' '.implode(' AND ',$ac['MATCH']).' AND'; }
        if(!empty($ac['AND'])) { $on .= ' '.implode(' AND ',$ac['AND']); }
        if(!empty($ac['OR'])) { $on .= ' '.implode(' OR ',$ac['OR']); }

        return $on;
    }

    private static function _mapConditions($c,$table,$kb=0,$kc=0,&$r=[]) {
        if(is_array($c) && !empty($c)) {
            foreach($c as $ka => $v) {
                if(strtolower($ka)!=='$in' && strtolower($ka)!=='$between' && is_array($v)) {
                    self::_mapConditions($v,$table,$ka,$kb,$r);
                } else {
                    if(!is_array($v) && $find = ucfirst(stristr($v, '.', true))) {
                        $v = (class_exists($find) && isset($find::$source)) ? $find::$source.stristr($v,'.') : strtolower($find).stristr($v,'.');
                    } else if (!is_array($v) && strtolower($ka)!=='$like') {
                        $v = "'$v'";
                    }

                    if(strtolower($ka)=='$in') {
                        $a = $table.'.'.$kb.' IN ('.implode('\',\'',$v).')';
                    } else if(strtolower($ka)=='$between') {
                        $a = $table.'.'.$kb.' BETWEEN \''.implode("' AND '",$v).'\'';
                    } else if(in_array(strtolower($ka), ['>','>=','<','<='])) {
                        $a = $table.'.'.$kb.' '.$ka.' '.$v;
                    } else if (strtolower($ka)=='$like') {
                        $a = $table.'.'.$kb.' LIKE \''.$v.'\'';
                    } else if($kb && $kb === 'MATCH') {
                        $a = "MATCH ($table.$kc) AGAINST ($v IN BOOLEAN MODE)";
                    } else if($kb && !in_array($kb,['AND','OR'])) {
                        $a = $table.'.'.$kb.' '.$ka.' '.$v;
                    } else {
                        $a = $table.'.'.$ka.' = '.$v;
                    }

                    $n = ['AND','OR','MATCH'];
                    if(in_array((string)$ka,$n)) {
                        $r[$ka][] = $a;
                    } else if(in_array((string)$kb,$n)) {
                        $r[$kb][] = $a;
                    } else if(in_array((string)$kc,$n)) {
                        $r[$kc][] = $a;
                    } else {
                        $r['ALL'][] = $a;
                    }
                }
            }
            return $r;
        }
    }

    private static function _cleanOperators($q) {
        $q = json_encode($q);
        $search = ['$and','$or','&&','||','$match','$gte','$gt','$lte','$lt','$not','$is'];
        $replace = ['AND','OR','AND','OR','MATCH','>=','>','<=','<','!=','='];
        $q = str_ireplace($search,$replace, $q);
        return json_decode($q, true);
    }

    protected static function &_object() {
        $class = get_called_class();
        if (!isset(static::$_instances[$class])) {
            static::$_instances[$class] = new $class();
        }
        return static::$_instances[$class];
    }

    /**
     * Takes a CamelCased version of a word and turns it into an under_scored one.
     *
     * @param string $word CamelCased version of a word (i.e. `'RedBike'`).
     * @return string Under_scored version of the workd (i.e. `'red_bike'`).
     */
    public static function underscore($word) {
        if (isset(static::$_underscored[$word])) {
            return static::$_underscored[$word];
        }
        return static::$_underscored[$word] = strtolower(static::slug($word, '_'));
    }
    /**
     * Returns a string with all spaces converted to given replacement.
     *
     * @param string $string An arbitrary string to convert.
     * @param string $replacement The replacement to use for spaces.
     * @return string The converted string.
     */
    public static function slug($string, $replacement = '-') {
        $map = array(
            '/[^\w\s]/' => ' ', '/\\s+/' => $replacement,
            '/(?<=[a-z])([A-Z])/' => $replacement . '\\1',
            str_replace(':rep', preg_quote($replacement, '/'), '/^[:rep]+|[:rep]+$/') => ''
        );
        return preg_replace(array_keys($map), array_values($map), $string);
    }

    /**
     * Converts a `Collection` object to another type of object, or a simple type such as an array.
     * The supported values of `$format` depend on the format handlers registered in the static
     * property `Collection::$_formats`. The `Collection` class comes with built-in support for
     * array conversion, but other formats may be registered.
     *
     * Once the appropriate handlers are registered, a `Collection` instance can be
     * converted into any handler-supported format, i.e.:
     * ```
     * $collection->to('json'); // returns a JSON string
     * $collection->to('xml'); // returns an XML string
     * ```
     *
     *  _Please note that Lithium does not ship with a default XML handler, but one can be
     * configured easily._
     *
     * @param string $format By default the only supported value is `'array'`. However, additional
     *        format handlers can be registered using the `formats()` method.
     * @return mixed The object converted to the value specified in `$format`; usually an array or
     *         string.
     */
    protected function to($format) {
        $data = $this->_data;
        if (isset(static::$_formats[$format]) && is_callable(static::$_formats[$format])) {
            $handler = static::$_formats[$format];
            $handler = is_string($handler) ? explode('::', $handler, 2) : $handler;
            if (is_array($handler)) {
                list($class, $method) = $handler;
                return static::$method($data);
            }
        }
        return false;
    }

    /**
     * Exports a `Collection` instance to json.
     *
     * @param `Collection` instance $data.
     * @return array Returns the value of `$data` as JSON, recursively converting all
     * sub-objects and other values to their closest array or scalar equivalents.
     */
    public static function toJSON(&$data) {
        return json_encode($data);
    }

    /**
     * Exports a `Collection` instance to an array.
     *
     * @param `Collection` instance $data.
     * @return array Returns the value of `$data` as a pure PHP array, recursively converting all
     *         sub-objects and other values to their closest array or scalar equivalents.
     */
    public static function toArray(&$data) {
        $result = array();
        foreach ($data as $key => $item) {
            switch (true) {
                case is_array($item):
                    $result[$key] = static::toArray($item);
                break;
                case (!is_object($item)):
                    $result[$key] = $item;
                break;
                case (method_exists($item, 'to')):
                    $result[$key] = $item->to('array');
                break;
                case ($vars = get_object_vars($item)):
                    $result[$key] = static::toArray($vars);
                break;
                case (method_exists($item, '__toString')):
                    $result[$key] = (string) $item;
                break;
                default:
                    $result[$key] = $item;
                break;
            }
        }
        return $result;
    }
}
