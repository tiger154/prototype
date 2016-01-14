<?php

class Mail extends PHPMailer {

    public $smarty; // smarty
    private  $template; // smarty template


    public function __construct() {
        $this->smarty = $this->getSmarty();
    }

    /**
     * Set smarty template
     * @param $t
     */
    public function setTemplate($t){
        $this->template = $t;
    }

    /**
     * Singleton instance
     * @return Smarty
     * @throws SmartyException
     */
    private function getSmarty() {
        if (!isset($this->smarty)) {
            $smarty = new Smarty();
            $smarty->addTemplateDir(VIEWS_DIR);
            $smarty->setCompileDir(BASE_DIR.'/cache/templates');
            $smarty->registerPlugin('modifier', 'lang', array('View', 'translate')); // translations
            $smarty->registerPlugin('function', 'profiler', array('Profiler', 'show')); // profiler
            $smarty->force_compile = true;
            $this->smarty = $smarty;
        }
        return $this->smarty;
    }

    /**
     * Fetch smarty and assign html to Mail's body
     * @return string
     */
    public function smartyFatch(){
        if(!isset($this->template)){
            return 'Error : There is no template';
        }
        $body = $this->smarty->fetch($this->template);
        $this->msgHTML($body);
        return $body;
    }


}