<?php

class Cache
{
    protected $cache = array();


    /**
     * Get an object from the named static cache
     *
     * @param $cache_name - The name of the cache
     * @param $key - The key of the object to retrieve, may be NULL.
     * @return an object, or NULL
     */
    function get($name, $key=null) {
        if (isset($this->cache[$name]) && $key) {
            //error_log("Get from cache: '$name' key: '$key'");
            return $this->cache[$name][$key];
        }
        return false;
    }

    /**
     * Return true if the cache item has been set (even if the value is NULL). If $key is null, this method returns true if the named cache exists.
     *
     * @param $cache_name - The name of the cache
     * @param $key - The key of the object to test, or NULL to test if the cache exists
     *
     * @return true if the cache or key is set (even if NULL), false otherwise
     */
    function exists($name, $key=null) {
        if ($key) {
            if(!$this->cache[$name]) {
                return false;
            }
            return array_key_exists($key, $this->cache[$name]);
        }
        return array_key_exists($name, $this->cache);
    }


    /**
    * Put an object into the named static cache.
    *
    * @param $cache_name - The name of the cache
    * @param $key - The key of the object to cache
    * @param $data - The object to cache
    */
    function add($name, $key, $data) {
        if (!isset($this->cache[$name])) {
            $this->cache[$name] = array();
        }
        $this->cache[$name][$key] = $data;
        $data = json_encode($data);
        //error_log("Added to cache: '$name' key: '$key' data: '$data'");
    }


    /**
     * Clear the key/value pair from the named cache. If $key is NULL, the entire named cache will be cleared.
     *
     * @param $cache_name - The name of the cache
     * @param $key - The key of the object to cache; if NULL, will clear the entire named cache.
     */
    function clear($name, $key=null) {
        if ($key) {
            unset($this->cache[$name][$key]);
        } else {
            unset($this->cache[$name]);
        }
    }
}
