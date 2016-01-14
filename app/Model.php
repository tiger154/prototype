<?php

class Model extends Collections
{

    protected $_update = array();
    protected $_data = array();

    protected static $db;       // db handle
    protected static $profile;  // curent user profile object
    protected static $uid;      // curent user id
    protected static $request;  // request array


    public function __construct(array $_data = array()) {
        if(!isset(self::$db)) { self::$db = Core::getDb(); }
        if(!isset(self::$request)) { self::$request = Core::$request; }
        if(!isset(self::$profile)) {
            self::$profile = Auth::$profile;
            self::$uid = self::$profile->user_id;
        }
        if(isset($_data)) { $this->_data = $_data; }
    }

    /**
     * Overloading for reading inaccessible properties.
     *
     * @param string $name Property name.
     * @return mixed Result.
     */
    public function &__get($name)
    {
        return isset($this->_update[$name]) ? $this->_update[$name] : isset($this->_data[$name]) ? $this->_data[$name] : null;
    }
    /**
     * PHP magic method used when setting properties on the `Entity` instance, i.e.
     * `$entity->title = 'Lorem Ipsum'`.
     *
     * @param string $name The name of the field/property to write to, i.e. `title` in the above example.
     * @param mixed $value The value to write, i.e. `'Lorem Ipsum'`.
     * @return void
     */
    public function __set($name, $value) {
        $this->_update[$name] = $value;
    }

    public function __toString()
    {
        return json_encode($this->to('array'));
    }

    public static function cleanAndClickable($message)
    {
        // trim
        $message = trim($message);
        //Convert all urls to links
        $message = preg_replace('#([\s|^])(www)#i', '$1http://$2', $message);
        $pattern = '#((http|https|ftp|telnet|news|gopher|file|wais):\/\/)([^\s]+)#i';
        $message = preg_replace_callback($pattern, function($matches) {
            return '<a href="'.$matches[0].'" target="_blank">'.strtok($matches[3], '/').'</a>';
        }, $message);
        // Convert all e-mail adressess
        $pattern = '#([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.';
        $pattern .= '[a-wyz][a-z](fo|g|l|m|mes|o|op|pa|ro|seum|t|u|v|z)?)#i';
        $replacement = '<a href="mailto:\\1">\\1</a>';
        $message = preg_replace($pattern, $replacement, $message);
        // Convert reference
        $pattern3 = "/(?<![^\s])@([^@\s]+)/";
        $message = preg_replace($pattern3, '<b>@$1</b>', $message);
        // return with all tags stripped except a and br
        return strip_tags(nl2br($message), '<a><br><b>');
    }
}

?>
