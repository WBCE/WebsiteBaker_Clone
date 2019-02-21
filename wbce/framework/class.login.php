<?php
/**
 * WebsiteBaker Community Edition (WBCE)
 * Way Better Content Editing.
 * Visit http://wbce.org to learn more and to join the community.
 *
 * @copyright Ryan Djurovich (2004-2009)
 * @copyright WebsiteBaker Org. e.V. (2009-2015)
 * @copyright WBCE Project (2015-)
 * @license GNU GPL2 (or any later version)
 */

// Prevent this file from being accessed directly
if (!defined('WB_PATH')) {
    require_once dirname(__FILE__) . '/globalExceptionHandler.php';
    throw new IllegalFileException();
}

// Load class Admin
require_once WB_PATH . "/framework/class.admin.php";

class Login extends Admin
{
    public function __construct($aConfig)
    {
        global $MESSAGE, $database;
        
        parent::__construct();
        
        // Get configuration values and turn them into properties
        foreach($aConfig as $key => $value) {
            $this->{(strtolower($key))} = $value;
        }
        
        if (!isset($this->redirect_url)) {
            $this->redirect_url = '';            
        }
        
        // Get the supplied username and password
        if ($this->get_post('username_fieldname') != '') {
            $sUsername = $this->get_post('username_fieldname');
            $sPassword = $this->get_post('password_fieldname');
        } else {
            $sUsername = 'username';
            $sPassword = 'password';
        }
        $this->username = htmlspecialchars(strtolower($this->get_post($sUsername)), ENT_QUOTES);
        $this->password = $this->get_post($sPassword);
        
        // Figure out if the "remember me" option has been checked
        if ($this->get_post('remember') == 'true') {
            $this->remember = $this->get_post('remember');
        } else {
            $this->remember = false;
        }
        
        // Get the length of the supplied username and password
        if ($this->get_post($sUsername) != '') {
            $this->username_len = strlen($this->username);
            $this->password_len = strlen($this->password);
        }
        
        // If the url is blank, set it to the default url
        $this->url = $this->get_post('url');
        if ($this->redirect_url != '') {
            $this->url = $this->redirect_url;
        }
        if (strlen($this->url) < 2) {
            $this->url = $aConfig['DEFAULT_URL'];
        }
        if ($this->is_authenticated() == true) {
            // User already logged-in, redirect to preset url
            header('Location: ' . $this->url);
            exit();
        } elseif ($this->is_remembered() == true) {
            // User has been "remembered" Get the users password
            $sSql    = "SELECT * FROM `{TP}users` 'WHERE `user_id`= %d";
            $resUser = $database->query(sprintf($sSql, $this->get_safe_remember_key()));
            $aUserData      = $resUser->fetchRow(MYSQL_ASSOC);
            $this->username = $aUserData['username'];
            $this->password = $aUserData['password'];
            // Check if the user exists (authenticate them)
            if ($this->authenticate()) {
                // Authentication successful
                header("Location: " . $this->url);
                exit(0);
            } else {
                $this->message = $MESSAGE['LOGIN_AUTHENTICATION_FAILED'];
                $this->increase_attemps();
            }
        } elseif ($this->username == '' && $this->password == '') {
            $this->message = $MESSAGE['LOGIN_BOTH_BLANK'];
            $this->display_login();
        } elseif ($this->username == '') {
            $this->message = $MESSAGE['LOGIN_USERNAME_BLANK'];
            $this->increase_attemps();
        } elseif ($this->password == '') {
            $this->message = $MESSAGE['LOGIN_PASSWORD_BLANK'];
            $this->increase_attemps();
        } else {
            // Check if the user exists (authenticate them)
            $this->password = $this->doPasswordEncode($this->password);
            if ($this->authenticate()) {
                // Authentication successful
                header("Location: " . $this->url);
                exit(0);
            } else {
                $this->message = $MESSAGE['LOGIN_AUTHENTICATION_FAILED'];
                $this->increase_attemps();
            }
        }
    }

        
    /**
     * @brief   Authenticate the user on login, write users data into $_SESSION
     *          and write into the database the last login time
     * 
     * @global  object  $database
     * @return  int     ammount of grroups the user is member of
     */
    public function authenticate()
    {
        global $database;
        $sLoginname = preg_match('/[\;\=\&\|\<\> ]/', $this->username) ? '' : $this->username;
        
        // Get user information
        $sSql = "SELECT * FROM `{TP}users` WHERE `username`='%s' AND `password`='%s' AND `active` = 1";
        $resUsers  = $database->query(sprintf($sSql, $sLoginname, $this->password));
        $aUserData = $resUsers->fetchRow(MYSQL_ASSOC);
        $iNumRows = $resUsers->numRows();
        if ($iNumRows == 1) {
            $iUserID = $aUserData['user_id'];
            $this->user_id = $iUserID;
            $_SESSION['USER_ID']      = $iUserID;
            $_SESSION['GROUP_ID']     = $aUserData['group_id'];
            $_SESSION['GROUPS_ID']    = $aUserData['groups_id'];
            $_SESSION['USERNAME']     = $aUserData['username'];
            $_SESSION['DISPLAY_NAME'] = $aUserData['display_name'];
            $_SESSION['EMAIL']        = $aUserData['email'];
            $_SESSION['HOME_FOLDER']  = $aUserData['home_folder'];
           
            // Run remember function if needed
            if ($this->remember == true) {
                $this->remember($this->user_id);
            }
            
            // Set language
            if ($aUserData['language'] != '') {
                $_SESSION['LANGUAGE'] = $aUserData['language'];
            }
            
            // Set timezone
            if ($aUserData['timezone'] != '-72000') {
                $_SESSION['TIMEZONE'] = $aUserData['timezone'];
            } else {
                // Set a session var so apps can tell user is using default tz
                $_SESSION['USE_DEFAULT_TIMEZONE'] = true;
            }
            
            // Set date format
            if ($aUserData['date_format'] != '') {
                $_SESSION['DATE_FORMAT'] = $aUserData['date_format'];
            } else {
                // Set a session var so apps can tell user is using default date format
                $_SESSION['USE_DEFAULT_DATE_FORMAT'] = true;
            }
            
            // Set time format
            if ($aUserData['time_format'] != '') {
                $_SESSION['TIME_FORMAT'] = $aUserData['time_format'];
            } else {
                // Set a session var so apps can tell user is using default time format
                $_SESSION['USE_DEFAULT_TIME_FORMAT'] = true;
            }

            // Get group information
            $_SESSION['SYSTEM_PERMISSIONS']   = array();
            $_SESSION['MODULE_PERMISSIONS']   = array();
            $_SESSION['TEMPLATE_PERMISSIONS'] = array();
            $_SESSION['GROUP_NAME']           = array();

            $bFirstGroup = true;
            foreach (explode(",", $this->get_session('GROUPS_ID')) as $iCurrGroupID) {
                $sSql = "SELECT * FROM `{TP}groups` WHERE `group_id` = %d";
                $resGroup = $database->query(sprintf($sSql, $iCurrGroupID));
                $aGroup   = $resGroup->fetchRow(MYSQL_ASSOC);
                $_SESSION['GROUP_NAME'][$iCurrGroupID] = $aGroup['name'];
                // Set system permissions
                if ($aGroup['system_permissions'] != '') {
                    $_SESSION['SYSTEM_PERMISSIONS'] = array_merge(
                        $_SESSION['SYSTEM_PERMISSIONS'], 
                        explode(',', $aGroup['system_permissions'])
                    );
                }
                // Set module permissions
                if ($aGroup['module_permissions'] != '') {
                    if ($bFirstGroup) {
                        $_SESSION['MODULE_PERMISSIONS'] = explode(',', $aGroup['module_permissions']);
                    } else {
                        $_SESSION['MODULE_PERMISSIONS'] = array_intersect(
                            $_SESSION['MODULE_PERMISSIONS'], 
                            explode(',', $aGroup['module_permissions'])
                        );
                    }
                }
                // Set template permissions
                if ($aGroup['template_permissions'] != '') {
                    if ($bFirstGroup) {
                        $_SESSION['TEMPLATE_PERMISSIONS'] = explode(',', $aGroup['template_permissions']);
                    } else {
                        $_SESSION['TEMPLATE_PERMISSIONS'] = array_intersect(
                            $_SESSION['TEMPLATE_PERMISSIONS'], 
                            explode(',', $aGroup['template_permissions'])
                        );
                    }
                }
                $bFirstGroup = false;
            }

            // Update the users table with current ip and timestamp
            $aUpdateUser = array(
                'user_id'    => $iUserID,
                'login_when' => time(),
                'login_ip'   => $_SERVER['REMOTE_ADDR'],
            );
            $database->updateRow('{TP}users', 'user_id', $aUpdateUser);
        } else {
            $iNumRows = 0;
        }
        // Return if the user exists or not
        return $iNumRows;
    }

    
    
    /**
     * @brief  Increase the count for login attemps
     */
    public function increase_attemps()
    {
        if (!isset($_SESSION['ATTEMPS'])) {
            $_SESSION['ATTEMPS'] = 0;
        } else {
            $_SESSION['ATTEMPS'] = $this->get_session('ATTEMPS') + 1;
        }
        $this->display_login();
    }

    
    /**
     * @brief   Display the login screen
     * 
     * @global  array  $MESSAGE
     * @global  array  $MENU
     * @global  array  $TEXT
     * @return  void   implements template object
     */
    public function display_login()
    {
        // Get language vars
        global $MESSAGE, $MENU, $TEXT;
        
        // If attemps more than allowed, warn the user
        if ($this->get_session('ATTEMPS') > $this->max_attemps) {
            $this->warn();
        }
        
        // Show the login form
        if ($this->frontend != true) {
            // Setup template object, parse vars to it, then parse it
            require_once WB_PATH . '/include/phplib/template.inc';
            $oTemplate = new Template(dirname($this->correct_theme_source($this->template_file)));
            $oTemplate->set_file('page', $this->template_file);
            $oTemplate->set_block('page', 'mainBlock', 'main');
            if ($this->remember_me_option != true) {
                $oTemplate->set_var('DISPLAY_REMEMBER_ME', 'display: none;');
            } else {
                $oTemplate->set_var('DISPLAY_REMEMBER_ME', '');
            }
            $aTextStrings = array('FORGOTTEN_DETAILS', 'USERNAME', 'PASSWORD', 'REMEMBER_ME', 'LOGIN', 'HOME');
            foreach($aTextStrings as $sToken){
                $oTemplate->set_var('TEXT_'.$sToken, $TEXT[$sToken]);
            }
            $oTemplate->set_var(
                array(
                    'WB_URL'                 => WB_URL,
                    'THEME_URL'              => THEME_URL,
                    'VERSION'                => VERSION,
                    'REVISION'               => REVISION,
                    'PAGES_DIRECTORY'        => PAGES_DIRECTORY,
                    'ACTION_URL'             => $this->login_url,
                    'ATTEMPS'                => $this->get_session('ATTEMPS'),
                    'USERNAME'               => $this->username,
                    'USERNAME_FIELDNAME'     => $this->username_fieldname,
                    'PASSWORD_FIELDNAME'     => $this->password_fieldname,
                    'MESSAGE'                => $this->message,
                    'INTERFACE_DIR_URL'      => ADMIN_URL . '/interface',
                    'MAX_USERNAME_LEN'       => $this->max_username_len,
                    'MAX_PASSWORD_LEN'       => $this->max_password_len,
                    'LANGUAGE'               => strtolower(LANGUAGE),
                    'FORGOTTEN_DETAILS_APP'  => $this->forgotten_details_app,
                    'SECTION_LOGIN'          => $MENU['LOGIN'],
                    'CHARSET'                => (defined('DEFAULT_CHARSET') ? DEFAULT_CHARSET : 'utf-8'),
                )
            );            

            $oTemplate->parse('main', 'mainBlock', false);
            $oTemplate->pparse('output', 'page');
        }
    }

    
    /**
     * @brief   Sanities the REMEMBER_KEY cookie to avoid SQL injection
     * 
     * @return  string
     */
    public function get_safe_remember_key()
    {
        if (!((strlen($_COOKIE['REMEMBER_KEY']) == 23) && (substr($_COOKIE['REMEMBER_KEY'], 11, 1) == '_'))) {
            return '';
        }

        // create a clean cookie (XXXXXXXXXXX_YYYYYYYYYYY) where X:= numeric, Y:= hash
        $clean_cookie = sprintf('%011d', (int) substr($_COOKIE['REMEMBER_KEY'], 0, 11)) . substr($_COOKIE['REMEMBER_KEY'], 11);
        return ($clean_cookie == $_COOKIE['REMEMBER_KEY']) ? $this->add_slashes($clean_cookie) : '';
    }

    
    /**
     * @brief    Warn user that they have had too many login attemps
     */
    public function warn()
    {
        header('Location: ' . $this->warning_url);
        exit(0);
    }

    // ********************************************************************* //
    // 
    //           The two methods below are currently not in use
    // 
    // ********************************************************************* //
  
    // Function to set a "remembering" cookie for the user
    public function remember($iUserID)
    {
        return true;
        
        //        global $database;
        //        $remember_key = '';
        //        // Generate user id to append to the remember key
        //        $length = 11-strlen($iUserID);
        //        if($length > 0) {
        //            for($i = 1; $i <= $length; $i++) {
        //                $remember_key .= '0';
        //            }
        //        }
        //        // Generate remember key
        //        $remember_key .= $iUserID.'_';
        //        $salt = "abchefghjkmnpqrstuvwxyz0123456789";
        //        srand((double)microtime()*1000000);
        //        $i = 0;
        //        while ($i <= 10) {
        //            $num = rand() % 33;
        //            $tmp = substr($salt, $num, 1);
        //            $remember_key = $remember_key . $tmp;
        //            $i++;
        //        }
        //        $remember_key = $remember_key;
        //        // Update the remember key in the db
        //        // $database = new database();
        //        $database->query("UPDATE ".$this->users_table." SET remember_key = '$remember_key' WHERE user_id = '$iUserID' LIMIT 1");
        //        if($database->is_error()) {
        //            return false;
        //        } else {
        //            // Workout options for the cookie
        //            $cookie_name = 'REMEMBER_KEY';
        //            $cookie_value = $remember_key;
        //            $cookie_expire = time()+60*60*24*30;
        //            // Set the cookie
        //            if(setcookie($cookie_name, $cookie_value, $cookie_expire, '/')) {
        //                return true;
        //            } else {
        //                return false;
        //            }
        //        }
    }

    // Function to check if a user has been remembered
    public function is_remembered() {
        return false;
        
        //        global $database;
        //        // add if get_safe_remember_key not empty
        //        if(isset($_COOKIE['REMEMBER_KEY']) && ($_COOKIE['REMEMBER_KEY'] != '') && ($this->get_safe_remember_key() <> '')){
        //            // Check if the remember key is correct
        //            // $database = new database();
        //            $sql = "SELECT `user_id` FROM `{TP}users` WHERE `remember_key` = '";
        //            $sql .= $this->get_safe_remember_key() . "' LIMIT 1";
        //            $check_query = $database->query($sql);
        //
        //            if($check_query->numRows() > 0) {
        //                $check_fetch = $check_query->fetchRow();
        //                $iUserID = $check_fetch['user_id'];
        //                // Check the remember key prefix
        //                $remember_key_prefix = '';
        //                $length = 11-strlen($iUserID);
        //                if($length > 0) {
        //                    for($i = 1; $i <= $length; $i++) {
        //                        $remember_key_prefix .= '0';
        //                    }
        //                }
        //                $remember_key_prefix .= $iUserID.'_';
        //                $length = strlen($remember_key_prefix);
        //                if(substr($_COOKIE['REMEMBER_KEY'], 0, $length) == $remember_key_prefix) {
        //                    return true;
        //                } else {
        //                    return false;
        //                }
        //            } else {
        //                return false;
        //            }
        //        } else {
        //            return false;
        //        }
    }
    
}