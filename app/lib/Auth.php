<?php

class Auth extends Model
{

    public static $status;
    public static $profile;             // current user profile object


    protected function crypt($passward, $type = ''){
        if($type == 'sha256'){ return hash(sha256, SALT. $passward);}
        if($type == 'md5'){ return md5(SALT . $passward);}

        $pwd = (strtotime('now') >= strtotime(DATE_CHANGE_SHA)) ? hash(sha256, SALT. $passward) : md5(SALT . $passward);
        return $pwd;
    }

    protected function isloggedIn() {
        if (!session_id()) { session_start(); }
        $session_id = session_id();
        $user = self::$db->sqlFetchRow("SELECT * FROM {session} s INNER JOIN {user} u ON (s.user_id=u.id) WHERE session_id='" . $session_id . "'");
        if ((!empty($user) && $user['status']==='active') || self::remembered()) { // user is logged in (valid session) or remembered
            self::$db->sqlQuery("UPDATE {session} SET last_action=NOW() WHERE session_id='" . $session_id . "'");
            self::$profile = Users::findById($user['id']); // load and set profile
            self::$status = 'active';
            return true;
        }
        // try login
        if (!$user && !empty(self::$request['params']['username']) && !empty(self::$request['params']['password'])) {
            return Auth::login();
        }
        return false;
    }

    protected function login() {
        $username = preg_replace('/[^a-z0-9\-_\s@\.]+/i', '', self::$request['params']['username']);
        $password = addslashes(self::$request['params']['password']);
        if(!empty($username) && !empty($password)) {
            $user = self::$db->sqlFetchRow($s=sprintf("SELECT * FROM {user} WHERE `password` in ('%s','%s') AND username='%s' LIMIT 1", Auth::crypt($password,'md5'), Auth::crypt($password,'sha256'), $username));
            if (!empty($user) && $user['status'] === 'pending') {
                self::$status = 'pending';
                return false;
            }
            if (!empty($user) && $user['status'] === 'active') { // login ok
                if (!session_id()) { session_start(); }
                $session_id = session_id();
                $record = array('session_id' => $session_id, 'user_id' => $user['id'], 'ip' => $_SERVER['REMOTE_ADDR'], 'last_action' => date("Y-m-d H:i:s"));
                self::$db->sqlInsertInto('{session}', $record); // write session to db
                self::$profile = Users::findById($user['id']); // load and set profile
                $_SESSION['top_user_id'] = $user['id']; // set top uid
                session_write_close();
                // remember this user if they checked the box
                if(isset(self::$request['params']['remember']) && self::$request['params']['remember'] == 1) {
                    Auth::saveLogin($user['id']);
                }
                self::$status = 'active';
                return true;
            }
        }
        self::$status = 'failed';
        return false;
    }

    protected function logout() {
        if (!session_id()) { session_start(); }
        self::$db->sqlQuery("DELETE FROM {session} WHERE session_id='" . session_id() . "'");
        Auth::forgetUser();
    }

    protected function cleanSessions() {
       self::$db->sqlQuery("DELETE FROM session WHERE last_action < (NOW() - INTERVAL 24 HOUR)");
    }

    /**
     * Destroys any series and token cookies so they will no longer be remembered
     */
    private function forgetUser() {
        setcookie("queeraz_series", "", time()-3600);
        setcookie("queeraz_token", "", time()-3600);
    }

    /**
     * Checks if the user is 'remembered' by checking the 'remember me' box
     * If YES, updates the user with this session id so they can continue browsing
     */
    private function remembered() {
        if(isset($_COOKIE['queeraz_series']) && isset($_COOKIE['queeraz_token'])) {
            // now hash the two
            $seriesHash = hash('sha256', SALT . $_COOKIE['queeraz_series']);
            $tokenHash = hash('sha256', SALT . $_COOKIE['queeraz_token']);
            // see if we have a match
            $query = sprintf("select * from user where auth_series = '%s' and auth_token = '%s'", $seriesHash, $tokenHash);
            $user = self::$db->sqlFetchRow($query);
            if(!empty($user)){ // GOT A MATCH; AUTHENTICATED
                $newToken = self::rand_str(50); // issue new TOKEN cookie RAW
                setcookie('queeraz_token', $newToken);
                $newTokenHash = hash('sha256', SALT . $newToken); // save TOKEN HASH into db
                self::$db->sqlQuery(sprintf("update user set auth_token = '%s' where id = %d", $newTokenHash, $user['id']));
                // add session info
                $record = array('session_id' => session_id(), 'user_id' => $user['id'], 'ip' => $_SERVER['REMOTE_ADDR'], 'last_action' => date("Y-m-d H:i:s"));
                self::$db->sqlInsertInto('{session}', $record);
                return true;
            }
        }
        return false;
    }

    /**
     * 'Remembers' this user since they checked the 'remember me' check box
     * @param int $id The user's id
     */
    private function saveLogin($id) {
        $series = self::rand_str(50); // create series RAW
        $token = self::rand_str(50); // create token RAW
        $seriesHash = hash('sha256', SALT . $series);
        $tokenHash = hash('sha256', SALT . $token);
        setcookie('queeraz_series', $series); // save RAW as cookies
        setcookie('queeraz_token', $token);
        $query = sprintf("UPDATE `user` SET auth_series = '%s', auth_token = '%s' where id = %d", $seriesHash, $tokenHash, $id); // save HASHES of these 2 in user row
        self::$db->sqlQuery($query);
    }

    /**
     * Generates a random all-caps text-only string of N length
     * @param int $n Length of the random string
     * @return string The random string
     */
    private static function rand_str($n) {
        $s = '';
        for($i=1; $i<=$n; $i++) {
            $s .= chr(rand(65,90));
        }
        return $s;
    }

    protected function isAdmin() {
        return in_array(self::$profile->user_id, array(51,53,10,61)); // Joost, Keith & Ineke, Jeonhwan
    }

    protected function updateUser() {
        $user = self::$db->sqlFetchRow("SELECT * FROM {session} s INNER JOIN {user} u ON (s.user_id=u.id) WHERE session_id='" . session_id() . "'");
        if (!empty($user)) {
            self::$profile = Users::findById($user['id']);
        }
    }

    /**
     * Switches to a page account if attached to your user account
     *
     * @param int $user_id
     * @return void
     */
    protected function switchUser($uid) {
        error_log("uid = $uid");
        $url = '/central';
        // find attached pages
        $tuid = (int)self::$profile->getTopUserId();

        $page = self::$db->sqlFetchRow($q=sprintf("SELECT * FROM {pages_user} pu WHERE user_id=%d AND page_id=%d", $tuid, $uid));
        if (Auth::isAdmin() || !empty($page) || $tuid===(int)$uid) {
            if (!session_id()) { session_start(); }
            self::$db->sqlQuery(sprintf("UPDATE {session} SET user_id=%d WHERE session_id='%s'", $uid, session_id()));
            // update session
            $_SESSION['top_user_id'] = $tuid;
            session_write_close();

            if (!empty($page)) { // get profile url when switching to a page
                $url = "/".$page['page_type']."/view/".self::$db->sqlFetchField($q=sprintf("SELECT profile_url FROM {pages_%s} WHERE user_id=%d", (string)$page['page_type'], (int)$uid));
            }
        }
        return $url;
    }

    /*
     *
     * name: Auth::generateRandomToken
     * @param
     * @return
     *
     */
    protected function generateRandomToken($length=20) {
        $validCharacters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUXYVWZ0123456789";
        $validCharNumber = strlen($validCharacters);
        $token = "";
        for ($i = 0; $i < $length; $i++) {
            $index = mt_rand(0, $validCharNumber - 1);
            $token .= $validCharacters[$index];
        }
        return $token;
    }

    /**
     * Reset password
     *
     * name: Auth::resetPassword
     * @param
     * @return
     *
     */
    protected function resetPassword($fields) {
        if (!empty($fields['password']) && !empty($fields['email'])) {
            $return = self::$db->sqlQuery(sprintf("UPDATE {user} SET `password`='%s',`last_edit_action`='%s' WHERE `username`='%s'", Auth::crypt($fields['password']), date("Y-m-d H:i:s"), $fields['email'])); // update pass in db
            if($return !== false) {
                $cleanup = self::$db->sqlQuery(sprintf("DELETE FROM {lost_passwords} WHERE `email`='%s'", $fields['email'])); // remove token(s) from database for this user
                return true;
            }
        }
        return false;
    }

    /**
     * Send email with reset token
     *
     * name: Auth::sendPasswordResetMail
     * @param
     * @return
     *
     */
    protected function sendPasswordResetMail($fields) {
        if (!empty($fields['email'])) {
            $token = Auth::generateRandomToken();
            $insert = array(
                'email' => $fields['email'],
                'token' => $token
            );
            $return = self::$db->sqlInsertInto('{lost_passwords}', $insert);
            if($return !== false) {
                // send password
                $protocol = isset($_SERVER["HTTPS"]) ? (($_SERVER["HTTPS"]==="on" || $_SERVER["HTTPS"]===1 || $_SERVER["SERVER_PORT"]===$pv_sslport) ? "https://" : "http://") :  (($_SERVER["SERVER_PORT"]===443) ? "https://" : "http://");
                $link = sprintf($protocol . $_SERVER["SERVER_NAME"] . '/?passwordreset=%s', $token);

                $mail = new Mail();
                $mail->setFrom('support@queeraz.com', 'Queeraz!');
                $mail->addAddress($fields['email']);
                $mail->setTemplate('mail/password_reset.html');
                $mail->Subject = 'Password reset for Queeraz';
                $mail->smarty->assign("link", $link);
                $mail->smarty->assign("fields", $fields);
                $mail->smartyFatch();

                if(!$mail->send()) {
                    //echo 'Mailer Error: ' . $mail->ErrorInfo;
                    return false;
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    /*
     * Check if email address is set and exists in DB
     *
     * name: Auth::checkEmailExists
     * @param
     * @return
     *
     */
    protected function checkEmailExists($fields) {
        $errors = [];
        if (empty($fields['email'])) {
            $errors[] = "The email address must be given!";
        }
        if(empty($errors)) {
            // email checks
            if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "I'm afraid this is not a valid email address, please check it.";
            } else {
                $email_exists = self::$db->sqlFetchRow(sprintf("SELECT * FROM {user} WHERE username='%s'", $fields['email']));
                if (!$email_exists) { $errors[] = "Sorry, this email address does not exists on Queeraz."; }
            }
        }
        return $errors;
    }

    /*
     * Check for strong passwords
     *
     * name: Auth::checkPassword
     * @param
     * @return
     *
     */
    protected function checkPassword($fields) {
        $errors = [];
        if (empty($fields['password'])) {
            $errors[] = "The password can not be empty!";
        }
        if(empty($errors)) {
            // password checks
            if ($fields['password'] != $fields['password_again']) {
                $errors[] = "The passwords don't match";
            } else {
                // password strength
                if( strlen($fields['password']) < 8 ) {
                    $errors[] = 'Password need to have at least 8 characters!';
                }
                if( !preg_match("#[0-9]+#", $fields['password']) ) {
                    $errors[] = 'Password must include at least one number!';
                }
                if( !preg_match("#[a-z]+#", $fields['password']) ) {
                    $errors[] = 'Password must include at least one lowercase letter!';
                }
                if( !preg_match("#[A-Z]+#", $fields['password']) ) {
                    $errors[] = 'Password must include at least one uppercase letter!';
                }
            }
        }
        return $errors;
    }

    /*
     *
     * name: Auth::checkPasswordToken
     * @param
     * @return
     *
     */
    protected function checkPasswordToken($fields) {
        $errors = [];
        // check if empty and token length
        if (empty($fields['token']) || strlen($fields['token']) !== 20) {
            $errors[] = "Token empty or wrong length.";
        }
        if(empty($errors)) {
            // try to fetch token from db
            $token_exists = self::$db->sqlFetchRow(sprintf("SELECT * FROM {lost_passwords} WHERE token='%s' AND email='%s'", $fields['token'], $fields['email']));
            if($token_exists) {
                // check if already used
                if($token_exists['used'] == 1) {
                    $errors[] = "Token has already been used, please use the lost password option again.";
                }
                // check expiration (24h)
                if(strtotime($token_exists['date_time']) <= strtotime('-24 hour')) {
                    $errors[] = "Token has expired, please use the lost password option again.";
                }
            } else {
                $errors[] = "Token does not exist, please use the lost password option again.";
            }
        }
        return $errors;
    }
}
