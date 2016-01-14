<?php

class View
{

    protected $request; // current request object
    protected $profile; // current user profile object
    protected $uid;     // current user id

    protected $lang;
    protected $dictionary = array();
    protected $smarty;
    protected $render = array(
        'data' => array(),
        'controller' => null,
        'template' => null,
        'base' => "base",
        'type' => "html",
        'class' => null,
        'ads' => true
    );

    public function __construct(array $render=array(), array $request=array(), array $props=array()) {
        $this->request = $request;
        $this->lang = isset($props['lang']) ? (string)$props['lang'] : 'en';
        if(isset($props['profile'])) {
            $this->profile = $props['profile'];
            $this->uid = (int)$this->profile->user_id;
        }
        if(!empty($render)) {
            $this->render = array_merge($this->render, $render);
            if(!isset($render['type']) && isset($request['ajax'])) { $this->render['type'] = 'json'; } // render json on ajax calls
            $this->render['controller'] = $request['controller']; // set template path
            $this->render['title'] = $render['title']?: ucfirst($request['controller']); // set title
        }
    }

    public function render() {
        switch ($this->render['type']) {
            case 'json':
                $this->renderJson();
                break;
            default:
                $this->renderHtml();
                break;
        }
    }

    private function renderJson() {
        header('Content-type: application/json');
        if(isset($this->render['template'])) {
            $smarty = $this->getSmarty();
            if($this->uid) { $smarty->assign("user", $this->profile); } // assign current user object
            if(!empty($this->render['data'])) { // assign data
                foreach($this->render['data'] as $k => $v) {
                    if($k=='nohtml' && is_array($v)) {
                        $exclude = $v;
                    } else {
                        $smarty->assign($k, $v);
                    }
                }
            }
            $result['html'] = $smarty->fetch($this->getTemplate());
            if(!empty($exclude)) {
                foreach($exclude as $k => $v) {
                    $result[$k] = $v;
                }
            }
        } else {
            $result = $this->render['data'];
        }
        echo json_encode($result);
    }

    private function renderHtml() {
        $smarty = $this->getSmarty();
        if($this->render['ads']) {
            $ads =  Ads::find('all', array(
                'conditions' => array(
                    'start_date' => array('$between' => array(0,time())),
                    '$or' => array(
                        'end_date' => array('$gte' => time(),'$is' => 0)
                    ),
                    'active' => 1
                )
            ));
            $smarty->assign("ads", $ads); // assign ads
        }

        $smarty->assign("title", $this->render['title']); // assign current user object
        if($this->uid) { $smarty->assign("user", $this->profile); } // assign current user object
        if(!empty($this->render['data'])) { // assign data
            foreach($this->render['data'] as $k => $v) {
                $smarty->assign($k, $v);
            }
        }
        $smarty->assign("body", $smarty->fetch($this->getTemplate()));
        $smarty->display($this->getBase());
    }

    private function getSmarty() {
       if (!isset($this->smarty)) {
            $smarty = new Smarty();
            $smarty->addTemplateDir(VIEWS_DIR);
            $smarty->setCompileDir(BASE_DIR.'/cache/templates');
            $smarty->registerPlugin('modifier', 'lang', array($this, 'translate')); // translations
            $smarty->registerPlugin('function', 'profiler', array('Profiler', 'show')); // profiler
            $smarty->force_compile = true;
            $this->smarty = $smarty;
       }
       return $this->smarty;
    }

    public function translate($text) {
        if (empty($this->dictionary)) {
            $lines = explode("\n", file_get_contents("../i18n/lang_".$this->lang.".txt"));
            foreach ($lines as $line) {
                if (trim($line)=='') continue;
                list($index, $translation) = array_map('trim', explode("=", $line));
                $this->dictionary[$index] = $translation;
            }
        }
        return isset($this->dictionary[$text]) ? $this->dictionary[$text] : $text;
    }

    private function getBase() {
        // main controller base template
        if (file_exists(strtolower(VIEWS_DIR."/".$this->render['controller']."/".$this->render['base'].".html"))) {
            return strtolower(VIEWS_DIR."/".$this->render['controller']."/".$this->render['base'].".html");
        }
        // other controller base template
        if (file_exists(strtolower(VIEWS_DIR."/".$this->render['base'].".html"))) {
            return strtolower(VIEWS_DIR."/".$this->render['base'].".html");
        }
        // no hits
        return false;
    }

    private function getTemplate() {
        $template = $this->render['template']?:$this->request['action'];
        // main controller body template
        if (file_exists(strtolower(VIEWS_DIR."/".$this->render['controller']."/".$template.".html"))) {
            return strtolower(VIEWS_DIR."/".$this->render['controller']."/".$template.".html");
        }
        // other controller body template
        if (file_exists(strtolower(VIEWS_DIR."/".$this->render['template'].".html"))) {
            return strtolower(VIEWS_DIR."/".$this->render['template'].".html");
        }
        // fallback to index template
        if (file_exists(strtolower(VIEWS_DIR."/".$this->render['controller']."/index.html"))) {
            return strtolower(VIEWS_DIR."/".$this->render['controller']."/index.html");
        }
        // no hits
        return false;
    }
}
