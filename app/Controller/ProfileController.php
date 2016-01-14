<?php
class ProfileController extends Controller
{

    /*
     * Show profile overview page
     *
     * name: ProfileController::view
     * @param
     * @return
     *
     */
    public function view() {
        $id = $this->request['id'];
        $profile = Profile::findByProfileUrl($this->request['id']);
        if ($this->request['params']['email_check']) {
            $fields = ['email' => $profile->email, 'registration_date' => $profile->registration_date, 'id' => $profile->user_id];
            $register = Register::_sendConfirmationMail($fields);
            return compact('register');
        }
        $preferences = Preferences::findByUserId($profile->user_id);
        $post_offset = 15;
        $posts = Posts::all(array(
            'conditions' => array(
                'user_id' => $profile->user_id,
                'status' => 'publish'
            ),
            'limit' => 15
        ));
        $stats = $profile->getStats();
        $favouriteTags = $profile->getFavouriteTags();
        return compact('profile','preferences','posts','post_offset','stats','favouriteTags');
    }

    /*
     * Show profile friends page
     *
     * name: ProfileController::friends
     * @param
     * @return
     *
     */
    public function friends() {
        $profile = Profile::findByProfileUrl($this->request['id']);
        $title = "Buddies";
        $users = $profile->getFriends();
        $stats = $profile->getStats();
        $this->render['template'] = 'users';
        return compact('title','users','profile','stats');
    }

    /*
     * Show profile edit page
     *
     * name: ProfileController::edit
     * @param
     * @return
     *
     */
    public function edit() {
        $profile = Profile::findByProfileUrl($this->request['id']);
        if($profile->isMyProfile()) {
            $title = 'Edit';
            $this->render['template'] = 'edit';
            return compact('title','profile');
        }
        $this->redirect('/errors/view/401');
    }

    /*
     * Show profile eyeing me page
     *
     * name: ProfileController::eyeingme
     * @param
     * @return
     *
     */
    public function eyeingme() {
        $profile = Profile::findByProfileUrl($this->request['id']);
        if($profile->isMyProfile()) {
            $title = 'Eyeing Me';
            $users = $profile->getEyeingMeUsers();
            $stats = $profile->getStats();
            $this->render['template'] = 'users';
            return compact('title','users','profile','stats');
        }
        $this->redirect('/errors/view/401');
    }

    /*
     * Show profile me eyeing page
     *
     * name: ProfileController::meeyeing
     * @param
     * @return
     *
     */
    public function meeyeing() {
        $profile = Profile::findByProfileUrl($this->request['id']);
        if($profile->isMyProfile()) {
            $title = 'I\'m Eyeing';
            $users = $profile->getMeEyeingUsers();
            $stats = $profile->getStats();
            $this->render['template'] = 'users';
            return compact('title','users','profile','stats');
        }
        $this->redirect('/errors/view/401');
    }

    /*
     * Show profile settings page
     *
     * name: ProfileController::settings
     * @param
     * @return
     *
     */
    public function settings() {
        $profile = Profile::findByProfileUrl($this->request['id']);
        if($profile->isMyProfile()) {
            $title = 'Settings';
            $this->render['template'] = 'settings';
            return compact('title','profile');
        }
        $this->redirect('/errors/view/401');
    }

    // upload new profile image
    public function saveProfileImage(){
        if($img = Profile::updateProfileImage($this)) {
            die(json_encode(array('success' => true, 'img' => $img)));
        }
        die(json_encode(array('success' => false, 'error' => "Wrong filetype or corrupt image, please try another image")));
    }

    // remove profile image
    public function deleteProfileImage(){
        if(Profile::removeProfileImage()) {
            die(json_encode(array('success' => true, 'img' => $this->profile->getProfileImage())));
        }
        die(json_encode(array('success' => false, 'error' => "Something went wrong here, the image could not be deleted")));
    }

    // upload new header image
    public function saveHeaderImage(){
        if($img = Profile::updateHeaderImage($this)) {
            die(json_encode(array('success' => true, 'img' => $img)));
        }
        die(json_encode(array('success' => false, 'error' => "Wrong filetype or corrupt image, please try another image")));
    }

    // remove header image
    public function deleteHeaderImage(){
        if(Profile::removeHeaderImage()) {
            die(json_encode(array('success' => true, 'img' => $this->profile->getHeaderImage())));
        }
        die(json_encode(array('success' => false, 'error' => "Something went wrong here, the image could not be deleted")));
    }


    public function save(){
        // check if data posted
            $fields =  $this->request['params']['profile'];
            // check errors
            $errors = [];
            $required = ['name','city','city_feed','professions'];
            foreach ($required as $r) {
                if (empty($fields[$r])) {
                    $errors[] = $r;
                }
            }

            // update if no errors
            if (empty($errors)) {
                if(Profile::update($fields)) {
                    // tell the application to refresh the session data
                    Auth::updateUser();
                    die(json_encode(array('success' => true, 'msg' => "Your profile is all set!")));
                }
                // update went wrong, show errors
                die(json_encode(array('success' => false, 'error' => $errors)));
            }
            // form errors! back to signup form
            die(json_encode(array('success' => false, 'error' => $errors)));
    }


    public function setSetting(){
        if(isset($this->request['params']['settings'])) {
            $settings = $this->request['params']['settings'];
            // 1) set users_preferences
            if (isset($settings['preferences'])) {  Preferences::update($settings); }
            // 2) set profile data
            if (isset($settings['profile'])) { Auth::updateUser(); }
        }
        $success = 'true';
        return compact('success');
    }


    /*
     * Update travel setting
     *
     * name: ProfileController::setTravel
     * @param
     * @return
     *
     */
    public function setTravel() {
        if(isset($this->request['params']['settings'])) {
            $settings = $this->request['params']['settings'];
            if(isset($settings['preferences'])) { Preferences::update($settings); }
        }
        $status = true;
        return compact('status');
    }

}
