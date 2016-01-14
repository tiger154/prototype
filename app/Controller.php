<?php

class Controller {

    protected $profile;             // current user profile object
    protected $uid;                 // current user id
    protected $request = array();   // request array
    protected $render = array();    // array to set render props
    protected static $translations; // array containing translations


    public function __invoke($request, $props, array $options = array()) {
        if(isset($props['profile']) && is_object($props['profile'])) {
            $this->profile = $props['profile'];
            $this->uid = $this->profile->user_id;
        }
        $this->request = $request; // give controllers access to $request
        $action = $this->request['action'];
        $args = isset($this->request['args']) ? $this->request['args'] : array();
        if (substr($action, 0, 1) === '_' || method_exists(__CLASS__, $action)) {
            trigger_error("Attempted to invoke a private method.", E_USER_WARNING);
        }
        if (!method_exists($this, $action)) {
            trigger_error("Action `{$action}` not found.", E_USER_WARNING);
        }
        if($data = $this->{$action}($args)) { // prepare data
            $this->render['data'] = !empty($res) ? $res : $data; // set data
        }
        $view = new View($this->render, $request, $props);
        return $view->render();
    }

    public function redirect($url, $params='') {
        if ($params) { $url .= ('?'.$params); }
        header("Location: $url");
    }

    public function translate($text) {
        $lang = $this->getLanguage();
        if (!isset(self::$translations)) {
            $lines = explode("\n", file_get_contents(BASE_DIR."/i18n/lang_$lang.txt"));
            foreach ($lines as $line) {
                if (trim($line)=='') continue;
                list($index, $translation) = array_map('trim', explode("=", $line));
                self::$translations[$index] = $translation;
            }
        }
        $result = isset(self::$translations[$text]) ? self::$translations[$text] : $text;
        return $result;
    }

    private function getLanguage() {
       return 'en';
    }

    /** TODO: move this!!
     *
     * Multiple File upload  with database insert
     * @param string $user_file
     * @param array $config
     * @return array
     * @throws exception
     */
    public function upload_files($user_file = 'Files', $config = array()){
        $lAttachObj = new Upload(); // load Upload lib
        $files = $_FILES;
        $cpt = count($_FILES[$user_file]['name']);
        for($i=0; $i<$cpt; $i++)
        {
            $_FILES[$user_file]['name']= $files[$user_file]['name'][$i];
            $_FILES[$user_file]['type']= $files[$user_file]['type'][$i];
            $_FILES[$user_file]['tmp_name']= $files[$user_file]['tmp_name'][$i];
            $_FILES[$user_file]['error']= $files[$user_file]['error'][$i];
            $_FILES[$user_file]['size']= $files[$user_file]['size'][$i];

            // upload file
            $lAttachObj->initialize($config);
            $lAttachObj->upload($user_file);

            // db insert
            $lAttachObj->file_id = Files::insert($lAttachObj->file_model);
            $fileArrays[] = $lAttachObj->file_model;
            $attachObjArrays[] = clone $lAttachObj; // to generete proper return obj, we need to clone
        }


        $rtn = array('attachObjs' => $attachObjArrays, 'files' => $fileArrays);
        return $rtn;
    }

}
