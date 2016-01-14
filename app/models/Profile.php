<?php

class Profile extends Model
{

    public static $source = 'profile';      // table name

    protected static $type = 'profile';     // profile type


    public function profileSet() {
        if ($this->usertype !== 'profile') { return true; }
        $meta = $this->getMeta('profile_filled');
        return !empty($meta) ? true : false;
    }

    /**
     * @return array Array of stats ['friends','eyeing', 'eyeingMe']
     */
    public function getStats() {
        return self::$db->sqlFetchrow(sprintf("SELECT * FROM {view_num_stats} WHERE id=%d", $this->user_id));
    }

    public function eye() {
        $eyeing = self::$db->sqlFetchRow(sprintf("SELECT * FROM {checkout} WHERE user_id=%d AND target_id=%d", self::$uid, $this->user_id));
        if ($eyeing) {
            self::$db->sqlQuery(sprintf("DELETE FROM {checkout} WHERE user_id=%d AND target_id=%d LIMIT 1", self::$uid, $this->user_id));
            return 'del';
        } else {
            $row = array('user_id' => self::$uid, 'target_id' => $this->user_id);
            self::$db->sqlReplaceInto('{checkout}', $row);
            return 'add';
        }
        return false;
    }

    /*
     * Fetches num times a user have been endorsed form users_meta
     *
     * name: Users::getEndorseCount
     * @param
     * @return int
     *
     */
    public function getEndorseCount() {
        return (int)$this->getMeta('endorse_count');
    }

    public function canLove() {
        $record = self::$db->sqlFetchRow(sprintf("SELECT * FROM {love} WHERE user_id=%d AND post_id=%d", self::$uid, $this->id));
        return empty($record);
    }

    public function getMeEyeingUrl() {
        return $this->getProfileUrl() . '/meeyeing';
    }

    public function getMeEyeingUsersIds() {
        return self::$db->sqlFetchCol(sprintf("SELECT target_id FROM {checkout} WHERE user_id=%d", $this->user_id))?: [];
    }

    public function getMeEyeingUsers() {
        $users = [];
        foreach ($this->getMeEyeingUsersIds() as $id) {
            $users[] = Users::findById($id);
        }
        return $users;
    }

    public function getEyeingMeUrl() {
        return $this->getProfileUrl() . '/eyeingme';
    }

    public function getEyeingMeUsersIds() {
        return self::$db->sqlFetchCol(sprintf("SELECT user_id FROM {checkout} WHERE target_id=%d", $this->user_id))?: [];
    }

    public function getEyeingMeUsers() {
        $users = [];
        foreach ($this->getEyeingMeUsersIds() as $id) {
            $users[] = Users::findById($id);
        }
        return $users;
    }

    public function getFriendsUrl() {
        return $this->getProfileUrl() . '/friends';
    }

    public function getFriendsIds() {
        return self::$db->sqlFetchCol(sprintf("SELECT friend_id FROM {friend} WHERE user_id=%d AND status='accepted'", $this->user_id))?: [];
    }

    public function getFriends() {
        $friends = [];
        foreach ($this->getFriendsIds() as $id) {
            $friends[] = Users::findById($id);
        }
        return $friends;
    }

    public function getPendingFriendsCount() {
        return Friends::getPendingFriendsCount();
    }

    public function getPendingFriends() {
        return Friends::getPendingFriends();
    }

    // GetFavoriteTagsOnlyRoot
    public function getFavouriteTags() {
        return self::$db->sqlFetchAll(sprintf("SELECT * FROM {favorite_tag} WHERE user_id=%d and parent_id is null ORDER BY label", $this->user_id));
    }

    public function getMeta($key, $field='', $forcedUserId = '') {
        $row = self::$db->sqlFetchRow($q=sprintf("SELECT * FROM `user_meta` WHERE user_id=%d AND `key`='%s'", $forcedUserId > 0 ? $forcedUserId :(int) $this->user_id, $key), false);

        $value = $row['value'];

        // check if array and field set
        if (is_array($value) && !empty($field)) {
            return $value[$field];
        }
        // check if serialized array
        $valueUnserialised = unserialize($value);
        if (is_array($valueUnserialised)) {
            return $valueUnserialised; // set value as array if it was a serialized array
        }
        // check if empty
        if (empty($value)) {
            // set some defaults when not set
            switch ($key) {
                case 'pages_switches':
                    $value = unserialize(PAGES_SWITCHES) ?: [];
                    break;
                case 'feed_switches':
                    $value = unserialize(FEED_SWITCHES) ?: [];
                    break;
                case 'current_location_radius':
                    $value = LOCATION_RADIUS;
                    break;
                default:
                    $value = [];
            }
        }
        return $value;
    }

    public function getName($params='') {
        parse_str($params, $options);
        if (isset($options['link_to_profile'])) {
            $html = sprintf('<a class="link_to_profile" href="%s">%s</a>', $this->getProfileUrl(), $this->name);
            $result = $html;
        }  else {
            $result = $this->name;
        }
        return $result;
    }

    public function getProfileUrl() {
        return "/profile/view/$this->profile_url";
    }

    public function getTopUserId() {
        error_log($_SESSION['top_user_id']);
        return !empty($_SESSION['top_user_id']) ? (int)$_SESSION['top_user_id'] : self::$uid;
    }

    /*
     * Fetches all pages for user so he/she can switch account.
     *
     * name: cView::getPages
     * @param
     * @return
     *
     */
    public function getUserPages() {
        $pages = [];
        $pageTypes = ['business', 'group', 'experience'];
        if(in_array($this->usertype, $pageTypes)) {
            $user = Users::findById($this->getTopUserId()); // current user is a page user
            $uid = $user->user_id;
            $pages[] = array('pid' => $uid, 'name' => $user->getName());
        } else {
            $uid = self::$uid; // current user is a 'normal' user
        }
        return array_merge($pages, self::$db->sqlFetchAll(sprintf("SELECT pu.page_id as pid, p.name FROM pages_user pu INNER JOIN pages_business p ON (pu.page_id=p.user_id) WHERE pu.user_id=%d", $uid)));
    }

    public function hasNewMessages() {
        return Notifications::has('message', $this->user_id);
    }

    public function getNewMessageCount() {
        $sql = sprintf("SELECT info FROM {notification} n
            WHERE n.status='new' AND target_userid=%d
            AND notification_type='message'", $this->user_id);
        $rows = self::$db->sqlFetchAll($sql);
        foreach ($rows as $row) {
            $info = unserialize($row['info']);
            $conversations[] = $info['conversation_id'];
        }
        $conversations = array_unique($conversations);
        return count($conversations);
    }

    public function getNotifications() {
        return Notifications::getLast(array('share','comment','reference','friends_post','love','pulse'));
    }

    public function getNotificationsCount() {
        return Notifications::getCount(array('share','comment','reference','friends_post','love','pulse'), $this->user_id);
    }

    /*
     * Fetches user's city
     *
     * name: Users::getCity
     * @param
     * @return string
     *
     */
    public function getCity($params='', $city_iso=null) {
        $options = array();
        parse_str($params, $options);
        $city = isset($city_iso) ? $city_iso : $this->city;
        if (isset($options['as_text'])) {
            $city = Locations::toText($city);
        }
        if(isset($options['short'])){
            $city = strtoupper(Locations::toText($city, $params));
        }
        return $city;
    }

    public function getCurrentCity($params='') {
        $options = array();
        parse_str($params, $options);
        $iso = $this->getMeta('current_location');
        if(isset($options['as_iso'])) {
            return $iso;
        }
        return $this->getCity($params,$iso);
    }

    public function getCurrentRadius() {
        $result = $this->getMeta('current_location_radius');
        if($result == "country") { $result = 700; }
        if($result == "world") { $result = 900; }
        return $result;
    }

    public function canLoveComment($comment_id, $uid=null){
        if(empty($user_id)){
            $user_id = $this->user_id;
        }
        $record = self::$db->sqlFetchRow(sprintf("select * from love where user_id = %d and comment_id = %d", $user_id, $comment_id));
        return empty($record);
    }

    /*
     * Fetches url to switch user account
     *
     * name: Users::getSwitchUserUrl
     * @param
     * @return
     *
     */
    public function getSwitchUserUrl($uid = 0) {
        $linkID = ($uid > 0) ? $uid : $this->user_id;
        return "/?switchuser=" . $linkID;
    }

    /*
     * Checks if visitor is a friend
     *
     * name: Users::isFriend
     * @param
     * @return
     *
     */
    public function isFriend() {
        $row = self::$db->sqlFetchRow($q=sprintf("SELECT * FROM {friend} WHERE user_id=%d AND friend_id=%d", self::$uid, $this->user_id));
        return $row['status'];

    }

    /*
     * Checks if this is a personal profile and is so if it's the visitor's one
     *
     * name: Users::isMyProfile
     * @param
     * @return
     *
     */
    public function isMyProfile() {
        return self::$uid == $this->user_id;
    }

    /*
     * Check if this is a page and is so if it's the visitor's one
     *
     * name: Users::isMyPage
     * @param
     * @return
     *
     */
    public function isMyPage() {
        if ($this->usertype !== 'profile') {
            return self::$db->sqlFetchField(sprintf("SELECT user_id FROM {pages_user} WHERE page_id=%d", $this->user_id)) == self::$uid;
        }
        return false;
    }

    public function isTraveling(){
        $preferences = Preferences::first(array(
            'conditions' => array(
                'user_id' => $this->user_id
            ),
            'objects' => false
        ));
        if(!empty($preferences)){
            $preferences = $preferences[0];
        }
        return $preferences['is_traveling'];
    }

    public function isOnline(){
        $uid = self::$db->sqlFetchCol(sprintf("select count(user_id) as cnt from session where user_id = %d limit 1;", $this->user_id));
        return ($uid[0] > 0);
    }

    /*
     * Checks if a visitor of a profile page is eyeing me
     *
     * name: Users::isEyeingMe
     * @param
     * @return
     *
     */
    public function isEyeingMe() {
        return self::$db->sqlFetchField(sprintf("SELECT user_id FROM {checkout} WHERE user_id=%d AND target_id=%d", self::$uid, $this->user_id));
    }

    /*
     * Show user profile based on setting
     *
     * name: Users::showProfile
     * @param
     * @return
     *
     */
    public function showProfile() {
        switch ($this->profile_public) {
            // not public
            case '0':
                return false;
            // friends only
            case '1':
                return ($this->isFriend()) ? true : false;
            // everyone
            case '2':
                return true;
        }
    }

    /*
     * Checks if a visitor of a profile page is allowed to become friends
     *
     * name: Users::isFriendAllowed
     * @param none
     * @return bool
     */
    public function isFriendAllowed() {
        switch ($this->friends_allowed) {
            case '0': // not public
                return false;
            case '1': // friends of friends only
                $result = array_intersect(self::$profile->getFriendsIds(), $this->getFriendOfFriends());
                return !empty($result) ? true : false;
            case '2': // everyone
                return true;
        }
    }

    public function showBirthday() {
        switch ($this->dob_public) {
            // show only xx-xx
            case 'public_day_month':
                return date("m-d", strtotime($this->dob));
            // show all xxxx-xx-xx
            case 'public':
                return $this->dob;
            // don't show any
            case 'hidden':
                return "";
            // only friends
            case 'friends':
                return ($this->isFriend()) ? $this->dob : '';
        }
    }

    /*
     * Fetches a deduplicated list of id's from friends of friends of the current active profile user
     *
     * name: Users::getFriendOfFriends
     * @param none
     * @return array
     */
    public function getFriendOfFriends() {
        return self::$db->sqlFetchCol(sprintf("SELECT DISTINCT friend_id FROM {friend} WHERE user_id IN (%s)", implode(',', $this->getFriendsIds())))?: [];
    }

    /*
     * Update user's metadata (user_meta table)
     *
     * name: Users::updateMeta
     * @param
     * @return
     *
     */
    public function updateMeta($meta_key, $param2, $param3=null) {
        $meta_data = $this->getMeta($meta_key);
        if (!is_null($param3)) {
            // store as array
            $meta_data[$param2] = $param3;
        } else {
            // not an array, single value
            $meta_data = $param2;
        }

        if (is_array($meta_data)) {
            $meta_data = serialize($meta_data);
        }
        $user_id = $this->user_id;
        $meta = array(
                'user_id' => $user_id,
                'key' => $meta_key,
                'value' => $meta_data
        );
        self::$db->sqlReplaceInto('{user_meta}', $meta);
        return true;
    }

    /*
     * Fetches user's profile image
     *
     * name: Users::getProfileImage
     * @param
     * @return
     *
     */
    public function getProfileImage($width=100, $height=100) {
        $placeholder = (static::$type == 'profile') ? WEB_ROOT.'/images/static_face.png' : WEB_ROOT.'/images/no_page.jpg';
        $file_src = (!$this->image || !file_exists(WEB_ROOT.$this->image)) ? $placeholder : WEB_ROOT.$this->image;
        $append = '_' . $width . 'x' . $height;
        $file = preg_replace('/(.jpg|.png|.gif$)/', $append . '$1', $file_src);
        if (!file_exists($file)) {
            $cmd = "convert $file_src -thumbnail " . $width . "x" . $height .
                "^ -gravity center -extent " . $width . "x" . $height . " -quality 95 $file";
            exec($cmd);
        }
        $result = str_replace(WEB_ROOT, '', $file);
        return $result;
    }

    /*
     * Fetches user's header image
     *
     * name: Users::getHeaderImage
     * @param
     * @return
     *
     */
    public function getHeaderImage() {
        return (!$this->header_image || !file_exists(WEB_ROOT.$this->header_image)) ? '/images/no_header.png' : $this->header_image;
    }


    /*
     * Checks if user has an header image
     *
     * name: Users::hasHeaderImage
     * @param
     * @return
     *
     */
    public function hasHeaderImage() {
        return !is_null($this->header_image);
    }

    /*
     * Checks if user has an profile image
     *
     * name: Users::hasProfileImage
     * @param
     * @return
     *
     */
    public function hasProfileImage() {
        return !empty($this->image);
    }

    /*
     * Deletes user header image from disk
     *
     * @todo remove thumbnails etc as well
     * @param array $user
     * @return void
     */
    protected function removeHeaderImage() {
        $image = self::$db->sqlFetchField(sprintf("SELECT `header_image` FROM {profile} WHERE user_id=%d", self::$uid));
        if($image && self::$db->sqlQuery(sprintf("UPDATE {profile} SET header_image = '' WHERE user_id=%d", self::$uid))) {
            $images = glob(WEB_ROOT.preg_replace("%^(\/.*\/".self::$uid."_[a-f0-9]{32}).*%i", "$1*", $image));
            array_map('unlink', $images);
            return true;
        }
        return false;
    }

    /*
     * Deletes user profile image from disk
     *
     * @todo remove thumbnails etc as well
     * @param array $user
     * @return void
     */
    protected function removeProfileImage() {
        $image = self::$db->sqlFetchField(sprintf("SELECT `image` FROM {profile} WHERE user_id=%d", self::$uid));
        if($image && self::$db->sqlQuery(sprintf("UPDATE {profile} SET image = '' WHERE user_id=%d", self::$uid))) {
            $images = glob(WEB_ROOT.preg_replace("%^(\/.*\/".self::$uid."_[a-f0-9]{32}).*%i", "$1*", $image));
            array_map('unlink', $images);
            return true;
        }
        return false;
    }

    /*
     * Updates (set/change) user's profile image
     *
     * name: Users::updateProfileImage
     * @param
     * @return
     *
     */
    protected function updateProfileImage(){
        $type = 'avatar';
        $size = ['width' => 100, 'height' => 100];
        $crop = array();
        foreach(array('x', 'y', 'width', 'height', 'rotate', 'scaleX', 'scaleY') as $field){
            $crop[$field] = $_POST[$field];
        }
        $file = $_FILES['image'];
        // crop 'n save
        if($img = Images::cropImage($file, self::$uid, $type, $size, $crop)) {
            // wipe existing image(s)
            Profile::removeProfileImage();
            // set new image
            if(Profile::saveProfileImage($img)) {
                return $img;
            }
        }
        return false;
    }

    /*
     * Updates (set/change) user's header image
     *
     * name: Users::updateHeaderImage
     * @param
     * @return
     *
     */
    protected function updateHeaderImage(){
    $type = 'header';
    $size = ['width' => 584, 'height' => 280];
    $crop = [];
    foreach(array('x', 'y', 'width', 'height', 'rotate', 'scaleX', 'scaleY') as $field){
        $crop[$field] = $_POST[$field];
    }
    $file = $_FILES['image'];
    // crop 'n save
    if($img = Images::cropImage($file, self::$uid, $type, $size, $crop)) {
        // wipe existing image(s)
        static::removeHeaderImage();
        // set new image
        if(static::saveHeaderImage($img)) {
            return $img;
        }
    }
    return false;
}

    /*
     * Save user's profile image
     *
     * name: Users::saveProfileImage
     * @param
     * @return
     *
     */
    protected function saveProfileImage($img) {
        return self::$db->sqlQuery(sprintf("UPDATE {profile} SET image = '". $img ."' WHERE user_id=%d", self::$uid));
    }

    /*
     * Save user's header image
     *
     * name: Users::saveHeaderImage
     * @param
     * @return
     *
     */
    protected function saveHeaderImage($img) {
        return self::$db->sqlQuery(sprintf("UPDATE {profile} SET header_image = '". $img ."' WHERE user_id=%d", self::$uid));
    }


    public function getInterests() {
        $uid = $this->user_id;
        return self::$db->sqlFetchAll(sprintf("SELECT pi.*,NOT ISNULL(piu.user_id) AS checked FROM {profile_interests} pi LEFT JOIN {profile_interests_user} piu ON (pi.id=piu.interest_id AND piu.user_id=%d) ORDER BY `order`", self::$uid))?:[];
    }

    /**
     * Returns list of personal types defaulting to strings as int IDS
     * @return type
     */
    public function getPersonalTypes() {
        $uid = $this->user_id;
        $result = self::$db->sqlFetchAll(sprintf("SELECT ppt.*,NOT ISNULL(pptu.user_id) AS checked FROM {profile_personal_types} ppt LEFT JOIN {profile_personal_types_user} pptu ON (ppt.id=pptu.personal_type_id AND pptu.user_id=%d) ORDER BY `order`", $uid))?:[];
        $types = [];
        foreach ($result as $row) {
            $value = $row['id'];
            $label = str_replace('`', ',', $row['label']);
            $types[] = array('value' => $value, 'label' => $label, 'checked' => $row['checked']);
        }
        return $types;
    }

    public function getLookingFor() {
        $uid = $this->user_id;
        return self::$db->sqlFetchAll(sprintf("SELECT pl.*,NOT ISNULL(plu.user_id) AS checked FROM {profile_looking_for} pl LEFT JOIN {profile_looking_for_user} plu ON (pl.id=plu.looking_for_id AND plu.user_id=%d) ORDER BY `order`", $uid))?:[];
    }

    public function getProfessions() {
        $uid = $this->user_id;
        $options = self::$db->sqlFetchAll(sprintf("SELECT pp.*,NOT ISNULL(ppu.user_id) AS checked FROM {profile_professions} pp LEFT JOIN {profile_professions_user} ppu ON (pp.id=ppu.profession_id AND ppu.user_id=%d) ORDER BY `category`,`order`", $uid));
        $professions = [];
        $groupname = '';
        foreach ($options as $profession) {
            if ($groupname != $profession['category']) {
                $groupname = $profession['category'];
                $groups[$groupname] = [];
            }
            $professions[$groupname][] = $profession;
        }
        return $professions;
    }

    public function getLanguages() {
        $uid = $this->user_id;
        return self::$db->sqlFetchAll(sprintf("SELECT pl.id,pl.bibliographical AS value,pl.name_en AS label,NOT ISNULL(plu.user_id) AS checked FROM {profile_languages} pl LEFT JOIN {profile_languages_user} plu ON (pl.id=plu.language_id AND plu.user_id=%d) ORDER BY `name_en`", $uid))?:[];
    }


    public function getRelationships() {
        $labels = explode(",", "Single,In a relationship,Dating,In an open relationship,Married,In a 3 or moresome,Unspecified");
        $relationShips = [];
        foreach ($labels as $label) {
            $value = preg_replace('/[^a-z]+/i', "_", strtolower($label));
            $label = str_replace('`', ',', $label);
            $checked = ($this->relationship_status === $value) ? true : false;
            $relationShips[] = array('value' => $value, 'label' => $label, 'checked' => $checked);
        }
        return $relationShips;
    }

    public function getSexualOrientations() {
        $labels = explode(",", "Gay,Lesbian,Queer,Bisexual,Straight,A bit confused,Hetroflexible,Homoflexible,Asexual,Unspecified");
        $sexualOrientations = [];
        foreach ($labels as $label) {
            $value = preg_replace('/[^a-z]+/i', "_", strtolower($label));
            $label = str_replace('`', ',', $label);
            $checked = ($this->sexual_orientation === $value) ? true : false;
            $sexualOrientations[] = array('value' => $value, 'label' => $label, 'checked' => $checked);
        }
        return $sexualOrientations;
    }

    public function getGenders() {
        $labels = explode(",", "Male,Female,Transgender,Intersex,Genderqueer,Unspecified");
        $genders = [];
        foreach ($labels as $label) {
            $value = preg_replace('/[^a-z]+/i', "_", strtolower($label));
            $label = str_replace('`', ',', $label);
            $checked = ($this->gender === $value) ? true : false;
            $genders[] = array('value' => $value, 'label' => $label, 'checked' => $checked);
        }
        return $genders;
    }

    /**
     * Returns true if 'I' can endorse (been endorsed myself at least 3 times) and 'I' haven't endorsed this person already
     *
     * @return
     */
    public function canBeEndorsed() {
        $registrationDate = new DateTime(self::$profile->registration_date);
        $isRegisteredBefore = new DateTime("2015-08-01");
        $can_endorse = (self::$profile->getEndorseCount() >= 3 || $registrationDate < $isRegisteredBefore); // we can endorse if we have been endorsed at least 3 times or we have registered before the first of august 2015
        $can_endorse &= !$this->isEndorsedByMe(); // target can be endorsed if I haven't endorsed him yet
        $can_endorse &= ($this->user_id != self::$uid); // we can't endorse ourselves
        return $can_endorse;

    }

    public function isEndorsedByMe() {
        $endorsed_by = $this->getMeta('endorsed_by');
        if (empty($endorsed_by)) return false;
        return in_array(self::$uid, $endorsed_by);
    }

    public function endorse() {
        if ($this->canBeEndorsed()) {
            $endorsed_by = $this->getMeta('endorsed_by');
            $endorsed_by[] = self::$uid;
            $this->updateMeta('endorsed_by', $endorsed_by);
            // update endorse count
            $endorse_count = (int)$this->getMeta('endorse_count');
            $endorse_count++;
            $this->updateMeta('endorse_count', $endorse_count);
            return true;
        }
        return false;
    }

    /**
     * Updates the user's profile
     *
     * @param array $fields
     * @return void
     */
    protected function update($fields, $type=null) {
        // fetch user data
        $user =  self::$db->sqlFetchRow(sprintf("SELECT * FROM {user} WHERE id=%d", self::$uid));

        // update email field when set
        if( isset($fields['email']) ) { $user['email'] = $fields['email']; }
        // set last edit action date
        $user['last_edit_action'] = date("Y-m-d H:i:s");
        // fetch profile data

        $profile = self::$db->sqlFetchRow(sprintf("SELECT * FROM {profile} WHERE user_id=%d",  self::$uid));
        // update profile fields
        $profileFields = ['name','dob','city','city_of_birth','city_feed','gender','sexual_orientation','relationship_status','description'];

        foreach ($profileFields as $field) {
            // update when set
            if(isset($fields[$field])) { $profile[$field] = $fields[$field]; }
        }

        // update account
        self::$db->sqlReplaceInto('user', $user);
        // update profile
        self::$db->sqlReplaceInto('profile', $profile);
        // update options
        $profileOptions = ['languages', 'interests', 'professions', 'looking_for', 'personal_types'];
        foreach ($profileOptions as $option) {
            $data = (isset($fields[$option]) && is_array($fields[$option])) ? $fields[$option] : [];
            self::$db->sqlQuery(sprintf("DELETE FROM {profile_" . (string)$option . "_user} WHERE user_id=%d", self::$uid)); // wipe all
            if (!empty($data)) {
                $values = [];
                foreach ($data as $val) {
                    if (!is_numeric($val)) { // add new value
                        $clean = ucfirst(strip_tags(trim($val)));
                        if ((string)$option == 'professions') {
                            $val = self::$db->sqlInsertInto("{profile_" . (string)$option . "}", array('category' => 'Unsorted', 'label' => $clean)); // set new profession
                        } else {
                            $val = self::$db->sqlInsertInto("{profile_" . (string)$option . "}", array('label' => $clean)); // set new
                        }
                    }
                    $values[] = sprintf('(%d,%d)', $val, self::$uid);
                }
                $field = rtrim($option, 's');
                self::$db->sqlQuery("INSERT INTO {profile_" . (string)$option . "_user} (" . $field . "_id,user_id) VALUES " . join(',', $values)); // save
            }
        }
        // TODO:: join groups
        return true;
    }
}

