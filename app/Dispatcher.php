<?php

class Dispatcher extends Core
{

    public function run() {
        $authenticated = Auth::isLoggedIn();
        $controller = ucfirst(strtolower(static::$request['controller'])).'Controller';
        // some router logic / protection
        if($authenticated) {
            if(isset(static::$request['params']['logout']) && Auth::logout()) {
                $this->redirect('/');
            }
            if(isset(static::$request['params']['switchuser'])) {
                $url = Auth::switchUser((int)static::$request['params']['switchuser']);
                $this->redirect($url);
            }
            if(empty($controller) || $controller==='FrontController' || ($controller==='JoinController' && Auth::$profile->profileSet())) {
                $this->redirect('/central');
            }
            if($controller!=='JoinController' && !Auth::$profile->profileSet()) {
                $this->redirect('/join');
            }
            if($controller==='AdminController' && !Auth::isAdmin()) {
                $this->redirect('/errors/view/401');
            }
        } else if(!static::$request['public']) {
            $this->redirect('/');
        }
        if(class_exists($controller, true)) {
            $props = !empty(Auth::$profile) ? ['profile' => Auth::$profile] : [];
            $invoke = new $controller();
            return $invoke(static::$request, $props); // invoke controller
        } else {
            $this->redirect('/errors/view/404');
        }
    }
}
