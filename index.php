<?php
// require config
require_once(dirname(__DIR__) . "/config/config.php");

// set debug
if(DEBUG) {
    error_reporting(E_ALL & ~E_NOTICE);
    ini_set('display_errors', 1);
}

// start benchmark timer
if(defined("PROFILER") && PROFILER) {
    $profilerStartTime = microtime(true);
    $profilerClassAllocatedSize = [];
}

/*
 * The Queeraz custom autoloader method
 * name: __autoload
 * @param $class_name
 * @return bool
 */
function __autoload($class_name) {

    $return = false;

    if(defined("PROFILER") && PROFILER) {
        global $profilerClassAllocatedSize;
        $memNow = memory_get_usage();
    }

    // load smarty (TODO: Clean this smarty thing)
    if (!$return && $class_name == 'Smarty') {
        require_once(LIBS_DIR .'/smarty-3.1.27/libs/SmartyBC.class.php');
        define("_SMARTY_LOADED", true);
        $return = true;
    }
    // all other smarty classes should be loaded by smarty's autoloader
    if (!$return && preg_match('/^Smarty_/', $class_name) === 1) {
        return false;
    }

    $filename = $class_name . '.php';
    // App
    if(!$return && file_exists(APP_DIR . '/' . $filename)) {
        require_once(APP_DIR . '/' . $filename);
        $return = true;
    }
    // Controllers
    if(!$return && file_exists(CONTROLLER_DIR . '/' . $filename)) {
        require_once(CONTROLLER_DIR . '/' . $filename);
        $return = true;
    }
    // Models
    if(!$return && file_exists(MODELS_DIR . '/' . $filename)) {
        require_once(MODELS_DIR . '/' . $filename);
        $return = true;
    }
    // Libs
    if(!$return && file_exists(LIBS_DIR . '/' . $filename)) {
        require_once(LIBS_DIR . '/' . $filename);
        $return = true;
    }
    // Collections
    if(!$return && file_exists(COLLECTIONS_DIR . '/' . $filename)) {
        require_once(COLLECTIONS_DIR . '/' . $filename);
        $return = true;
    }

    if(defined("PROFILER") && PROFILER) {
        $profilerClassAllocatedSize[$class_name] = (memory_get_usage() - $memNow);
    }
    return $return;
}

// register the custom autoload method
spl_autoload_register('__autoload');

// start application
$application = new Dispatcher($db);
$application->run();
