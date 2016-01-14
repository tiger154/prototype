<?php

class Router {

    /*
     * These aliases are the url's we can go to on Queeraz, add one if needed
     */
    private static $_aliases = array(
        // Auth
        'auth' => array('pattern' => '/^\/Auth\/(?<action>[a-zA-Z]*)?(\/(?<id>[0-9]*)?)?/', 'controller' => 'Auth', 'public' => true),
        // front
        'front' => array('pattern' => '/^\/$/', 'controller' => 'Front', 'public' => true),
        // mail
        'mail' => array('pattern' => '/^\/mail\/([a-z\_]+)\/?$/', 'public' => true),
        // all others
        'wildcard' => array('pattern' => '/^\/(?<controller>[a-zA-Z]*)(\/(?<action>[a-zA-Z]*))?(\/(?<id>(((?=\S{0,7}[a-z])(?=\S{0,7}[0-9])[a-z0-9]{8})|[0-9]+))?)?/'),
    );

    public static function run() {
        $url = $_SERVER['REQUEST_URI'];
        if (($index=strpos($url, '?'))!==false) {
            $url = substr($url, 0, $index);
        }
        if (strrpos($url, '/') != strlen($url)-1) {
            $url .= '/';
        }
        if ($url == '//') {
            $url = '/';
        }
        foreach (self::$_aliases as $alias) {
            $matches = array();
            if (preg_match($alias['pattern'], $url, $matches)) {
                $alias['matches'] = $matches;
                return $alias;
            }
        }
        return false;
    }

    public static function url($alias) {
        $alias = preg_replace('/^:/', '', $alias);
        return self::$_aliases[$alias]['url'];
    }

    /**
     * Returns true if url is known by name $alias
     * @param string $alias
     * @param string $url
     */
    public static function is($alias, $url='') {
        $url = (!empty($url)) ? $url : $_SERVER['REQUEST_URI'];
        $info = self::url($url);
        return $info['alias'] == $alias;
    }
}
