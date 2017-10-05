<?php

/**
* Multi Sites: Utilities
*
* This file forms part of the Multi Sites Suite.
* Provides various utility methods (validation, SQL, conversions, etc) for use throughout the module.
*
* @author Francis Otieno (Kongondo)
* @version 0.0.1
*
* This is a Free Module.
* Large chuncks of code lifted from the official ProcessWire installer (install.php).
* @credits: Ryan Cramer
*
* ProcessMultiSites for ProcessWire
* Copyright (C) 2017 by Francis Otieno
* This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
*
*/

class MultiSitesUtilities extends ProcessMultiSites {

	public function __construct() {
        parent::__construct();        
    }

    /* ######################### - GETTERS - ######################### */

    /**
	 * Get and sanitize URL Segments ready for various logic/operations.
	 *
	 * @access public
	 * @return array $urlSegments Array with URL segments information.
	 *
	 */
	public function getURLSegments() {
        
        $urlSegments = array();

        $input = $this->wire('input');
        $sanitizer = $this->wire('sanitizer');

        // @note: there will be times when we override sanitization type
        $urlSegments[] =  $sanitizer->pageName($input->urlSegment1);
        $urlSegments[] =  $sanitizer->pageName($input->urlSegment2);
        $urlSegments[] =  $sanitizer->pageName($input->urlSegment3);
        $urlSegments[] =  $sanitizer->pageName($input->urlSegment4);

        return $urlSegments;

    }

    /**
     * Return the absolute path to ProcessWire's root.
     *
     * @access private
     * @return string $pwRootPath ProcessWire's root path.
     * 
     */
    private function getPWRootPath() {
        $pwRootPath = $this->wire('config')->paths->root;
        return $pwRootPath;
    }

    /**
     * Return the absolute path to a given site's directory relative to PW root.
     *
     * @access private
     * @return string $sitePath Path to the site directory.
     * 
     */
    public function getSitePath($siteDirectoryName) {
        $sitePath = $this->getPWRootPath() . 'site-' . $siteDirectoryName;
        return $sitePath;
    }

    /**
     * Return the absolute path to a site's profile's temp directory used during install.
     *
     * @access private
     * @param string $profileTopDirectory The profile directory whose path to get.
     * @return string $path Path to the site's profile in the temp directory.
     * 
     */
    private function getInstallProfilePath($profileTopDirectory = '') {
        $path = $this->privateTempSitesDir . $profileTopDirectory;
        return $path;
    }

    /**
     * Attempt to get the CHMOD values on a server.
     *
     * @access public
     * @param string $index Index denoting if to return chmodDir vs chmodFile values.
     * @return string value at the given index.
     * 
     */
    public function getChmod($index) {
        // @see dbConfig() in original install.php
        $cgi = false;
		$defaults = array();

		if(is_writable(__FILE__)) {
			$defaults['chmodDir'] = "755";
			$defaults['chmodFile'] = "644";
			$cgi = true;
        } 
        
        else {
			$defaults['chmodDir'] = "777";
			$defaults['chmodFile'] = "666";
        }
        
        $defaults['cgi'] = $cgi;

        return $defaults[$index];

    }

    /**
     * Attempt to get
     *
     * @access public
     * @param string $index Index denoting value to return.
     * @return string value at the given index.
     * 
     */
    public function getMySQLDefaults($index) {
        // @see dbConfig() in original install.php
        $defaults = array(
            'dbHost' => (ini_get("mysqli.default_host") ? ini_get("mysqli.default_host") : 'localhost'),
            'dbPort' => (ini_get("mysqli.default_port") ? ini_get("mysqli.default_port") : 3306),
            'dbUser' => ini_get("mysqli.default_user"),
            'dbPass' => ini_get("mysqli.default_pw"),
            'dbEngine' => 'MyISAM'
        );
        return $defaults[$index];      
    }

    /**
     * Return installer's database driver options.
     *
     * @access private
     * @return array $driverOptions MySQL driver options used in ProcessWire installer.
     * 
     */
    private function getDatabaseDriverOptions() {
        // @see dbSaveConfig() in original install.php
        $driverOptions = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            );
        return $driverOptions;
    }

    /**
     * Return DSN values for PDO connection.
     *
     * @access private
     * @param array $dbValues the The database connection values.
     * @return string $dsn The values for PDO connection.
     * 
     */
    private function getDataSourceName($dbValues) {
        // @see dbSaveConfig() in original install.php
        $dsn = "mysql:dbname=$dbValues[dbName];host=$dbValues[dbHost];port=$dbValues[dbPort]";
        return $dsn;
    }

    /**
	 *	Get limited number of Multi Sites items.
	 *
	 * Could be Installed Sites, or Site Profiles, etc.
     * Includes all except items in the trash.
	 *
	 *	@access public
	 *	@param string $selector The ProcessWire selector to retrieve items.
	 *	@return PageArray $items PageArray if items found.
	 *
	 */
	public function getItems($selector) {
        $items = $this->wire('pages')->find("$selector, include=all, parent!=7");
		return $items;
    }

    /**
     * Get site profiles.
     * 
     * These are each saved as pages in admin.
     * We ignore those withough site profile files.
     *
     * @access private
     * @return array $profilesArray id=>title pairs of profiles.
     * 
     */
    private function getProfiles() {
        $profilesArray = array();
        // @todo:? limit=100?
        $profilesSelector = "template=multi-sites-site-profile,parent.name=multi-sites-profiles,limit=100,multi_sites_files!=''";
        $profiles = $this->getItems($profilesSelector);        
        foreach ($profiles as $profile) $profilesArray[$profile->id] = $profile->title;  
        return $profilesArray;
    }

    /**
     * Get the profile file for a given profile page
     *
     * @access public
     * @param integer $profileID pageID of the profile to get.
     * @return string $profileFile Absolute path to the profile file.
     * 
     */
    public function getProfileFile($profileID) {
        $profileFile = array();
        $profilePage = $this->wire('pages')->get((int) $profileID);
        if($profilePage && $profilePage->id > 0) {
            $file = $profilePage->multi_sites_files->first();
            if($file) {
                $profileFile['path'] = $file->filename;
                $profileFile['name'] =  rtrim(str_replace($file->ext, '', $file->name), ".");
            }
        }
        return $profileFile;
    }

    /**
	 * Return PHP time zones.
	 * 
	 * Return JSON of id=>timezone pairs or single timezone.
	 *
	 * @access public
     * @param integer $index Index to specify the timezone to return from the array.
	 * @return string $timeZones JSON values of id=>time zone pairs or single timezone as string.
	 * 
	 */
	public function getTimeZones($index = null) {
        // @see in original timezones() install.php
        
        $timeZonesList = timezone_identifiers_list();

        $extras = array(
            'US Eastern|America/New_York',
            'US Central|America/Chicago',
            'US Mountain|America/Denver',
            'US Mountain (no DST)|America/Phoenix',
            'US Pacific|America/Los_Angeles',
            'US Alaska|America/Anchorage',
            'US Hawaii|America/Adak',
            'US Hawaii (no DST)|Pacific/Honolulu',
            );
        
        foreach($extras as $t) $timeZonesList[] = $t; 

        if(!$index) $timeZones = json_encode($timeZonesList);
        else $timeZones = $timeZonesList[$index];

        return $timeZones;
        
    }

    /**
     * Get option for a site installation form section.
     *
     * @access public
     * @param string $option Index of the option to return.
     * @return array $siteOptions Array with options for an installation's section.
     *
     */
    public function getSiteOptions($option) {
        // inner array: 0=label;1=input-type;2=id+name;3=notes;4=value/options
        $siteOptions = array(
            // site
            'site' => array(
                // 0 = section header
                0 => array($this->_('Site'),
                        ''
                ),
                1 => array($this->_('Title'),
                'text', 'ms_site_title', $this->getNotes('site_title')
                ),
                2 => array($this->_('Description'),
                'textarea', 'ms_site_description', $this->getNotes('description')
                ),
                3 => array($this->_('Site Directory'),
                'text', 'ms_site_directory', $this->getNotes('site_directory')
                ),
                4 => array($this->_('Hostname and Domain'),
                'text', 'ms_site_domain', $this->getNotes('site_domain')
                ),                
                5 => array($this->_('Installation Profile'),
                    'select', 'ms_installation_profile', $this->getNotes('installation_profile'), $this->getProfiles()
                ),
                6 => array($this->_('Admin Login URL') .
                ' <small>(a-z 0-9)</small>', 'text', 'ms_admin_url', $this->getNotes('admin_login_url')
                ),
                7 => array($this->_('Admin Theme'),
                'select', 'ms_admin_theme', $this->getNotes('admin_theme'), $this->adminThemes
                ),
                8 => array($this->_('Colour Theme'),
                    'select', 'ms_colour_theme', $this->getNotes('colour_theme'), $this->colours
                ),
                9 => array($this->_('Default Time Zone'),
                'text', 'ms_timezone', $this->getNotes('time_zone')
                ),
                // @see: original dbConfig() add $_SERVER['SERVER_NAME']?
                // @note: whitelist
                10 => array($this->_('HTTP Host Names'),
                'textarea', 'ms_http_host_names', $this->getNotes('host_names')
                ),

            ),
            // database
            'database' => array(
                // 0 = section header
                0 => array($this->_('MySQL Database'),
                    ''
                ),
                1 => array($this->_('DB Name'),
                    'text', 'ms_db_name', $this->getNotes('db_name')
                ),
                2 => array($this->_('DB User'),
                    'text', 'ms_db_user', $this->getNotes('db_user'), $this->getMySQLDefaults('dbUser')
                ),
                3 => array($this->_('DB Password'),
                    'password', 'ms_db_pass', $this->getNotes('db_pass'),
                    $this->getMySQLDefaults('dbPass')
                ),
                4 => array($this->_('DB Host'),
                'text', 'ms_db_host', $this->getNotes('db_host'),$this->getMySQLDefaults('dbHost'),
                ),
                5 => array($this->_('DB Port'),
                'text', 'ms_db_port', $this->getNotes('db_port'),$this->getMySQLDefaults('dbPort')
                ),
            ),
            // super user
            'superuser' => array(
                // 0 = section header
                0 => array($this->_('Superuser'),
                    ''
                ),
                1 => array($this->_('User Name') .
                    ' <small>(a-z 0-9)</small>', 'text', 'ms_superuser_name', $this->getNotes('superuser_name')
                ),
                2 => array($this->_('Password'),
                    'password', 'ms_superuser_pass', $this->getNotes('superuser_password')
                ),
                3 => array($this->_('Password') .
                ' <small>(' . $this->_('confirm') . ')</small>',
                'password', 'ms_superuser_pass_confirm', $this->getNotes('superuser_password_confirm')
                ),
                4 => array($this->_('Email Address'),
                'email', 'ms_superuser_email', $this->getNotes('superuser_email')
                ),
            ),            
            // file permissions
            'file_permissions' => array(
                // 0 = section header
                0 => array($this->_('File Permissions'),
                    '','', $this->getNotes('file_permissions_header')
                ),
                1 => array($this->_('Directories') .
                    ' <small>(a-z 0-9)</small>', 'text', 'ms_directories_permission', $this->getNotes('directories_permission'),
                    $this->getChmod('chmodDir')
                ),
                2 => array($this->_('Files'),
                    'text', 'ms_files_permission', $this->getNotes('files_permission'),
                    $this->getChmod('chmodFile')
                ),

            ),  

        );

        return $siteOptions[$option];

    }

    /**
     * Return corresponding notes for a site installation's form section.
     *
     * @access private
     * @param string $note Index at which to grab note in the array.
     * @return string value of $note as the index in the array.
     * 
     */
    private function getNotes($note) {

        $strongPassword = '<a target="_blank" href="http://en.wikipedia.org/wiki/Password_strength">' . $this->_('strong password') . '</a>';
        $supNameNote = sprintf(__('You will use this account to login to your ProcessWire admin. It will have superuser access, so please make sure to create a %s. It must be at least 2 characters long.'), $strongPassword);

        ## files stuff
        $securingFiles = '<a target="_blank" href="https://processwire.com/docs/security/file-permissions/">' . $this->_('Read more about securing file permissions') . '</a>';
        
        $filePermissionsHeaderNote = sprintf(__('When ProcessWire creates directories or files, it assigns permissions to them. Enter the most restrictive permissions possible that give ProcessWire (and you) read and write access to the web server (Apache). The safest setting to use varies from server to server. If you are not on a dedicated or private server, or are in any kind of shared environment, you may want to contact your web host to advise on what are the best permissions to use in your environment. %s.'), $securingFiles);
        
        $directoryPermissionsNote = $this->_("Permissions must be 3 digits each. Should you opt to use the default provided, you can also adjust the permissions later if desired by editing '/site/config.php'.");

        $chmodDir = '755';
        if($chmodDir == $this->getChmod('chmodDir')) {
            $directoryPermissionsNote .=  ' ' . sprintf(__('We detected that that Apache may be running as your user account. Given that, we populated this permission with %s as a possible starting point.'), $chmodDir);
        }

        ###

        $filePermissionsNote = $this->_("Permissions must be 3 digits each. Should you opt to use the default provided, you can also adjust the permissions later if desired by editing '/site/config.php'.");

        $chmodFile = '644';
        if($chmodFile == $this->getChmod('chmodFile')) {
            $filePermissionsNote .=  ' ' . sprintf(__('We detected that that Apache may be running as your user account. Given that, we populated this permission with %s as a possible starting point.'), $chmodFile);
        }

        ######

        $notes = array(
            # site
            'site_title' => $this->_("A title for the installation (in order to save a record of this installation)."),

            'site_directory' => $this->_("The name of the site directory to be used with this install. The name you indicate here will be pre-pended with 'site-' when creating the site directory. This is to ensure that ProcessWire's htaccess file can recognize and protect files in that directory. Hence, do not enter the word 'site-' yourself. It is advisable to use a single lowercase word. The final directory will be named similar to 'site-domain' or 'site-something.  This value will be used by index.config.php."),
            
            'site_domain' => $this->_("Enter the hostname including the domain for this site. For instance, 'mydomain.com', 'www.mydomain.com' or 'dev.mydomain.com'. This value will be used by index.config.php."),

            'installation_profile' => $this->_('A site installation profile is a ready-to-use and modify site for ProcessWire. If you are just getting started with ProcessWire, we recommend choosing the Default site profile. If you already know what you are doing, you might prefer the Blank site profile.'),
            
            'admin_login_url' => $this->_("You can change the admin URL later by editing the admin page and changing the name on the settings tab. It must be at least 2 characters long and cannot be 'wire' or 'site'."),
            
            'admin_theme' => $this->_('You can change the theme later in  Admin when editing a user.'),

            'colour_theme' => $this->_('You can change the colours later in Admin, core Modules, Admin Theme Settings.'),
            
            'time_zone' => $this->_('Default time zone to be used in your site.'),
            
            'host_names' => $this->_('What host names will this installation run on now and in the future? Please enter one host per line. You may also choose to leave this blank to auto-detect on each request, but we recommend using this whitelist for the best security in production environments. This field is recommended but not required. You can set this later by editing the file /site/config.php (setting \$config->httpHosts).'),

            'description' => $this->_('A brief description about this install. This is not required but helpful for site admin.'),

            # database
            'db_name' => $this->_('The name of the MySQL 5.x database to connect to on your server. If the database does not exist, we will attempt to create it. Make sure you are installing to a separate database than the one used by your other sites.'),
            'db_user' => $this->_('Specify the user account for this database. If the database already exists, the user account should have full read, write and delete permissions on the database. Recommended permissions are select, insert, update, delete, create, alter, index, drop, create temporary tables, and lock tables.'),
            'db_pass' => $this->_('The Database user password.'),
            'db_host' => $this->_("'The Database host. This is normally 'localhost'."),
            'db_port' => $this->_("The Database port to connect to. This is normally '3306'."),
            // @todo: add Experimental Database Options FOR PW 3.X  {dbCharset,dbEngine}?

            # superuser             
            'superuser_name' => $supNameNote,             
            'superuser_password' => $this->_('Please remember the password you enter here as you will not be able to retrieve it again. It must be at least 6 characters long.'),
            'superuser_password_confirm' => $this->_('Re-enter your password to confirm.'),
            'superuser_email' => $this->_('The email address for the superuser.'),

            # file permissions            
            'file_permissions_header' => $filePermissionsHeaderNote, 
            'directories_permission' => $directoryPermissionsNote,            
            'files_permission' => $filePermissionsNote,

        );

        return $notes[$note];

    }

    /**
     * Convert the given settings for a given page to a WireData object.
     *
     * @access public
     * @param string $settingsJSON JSON string of installed site settings.
     * @return WireData $settingsObject Object with installed site settings properties.
     * 
     */
    public function getSettingsObject($settingsJSON) {
        $settingsArray = json_decode($settingsJSON, true);    
        $settingsObject = new WireData();
        $settingsObject->setArray($settingsArray);
        return $settingsObject;    
    }

    /**
     * Get the contents of sites.json file and return as array.
     * 
     * This file holds the domain-name => site-directory pairs for all multi-sites in the system.
     *
     * @access private
     * @return array $sitesArray Array with key=>value pairs of sites data.
     * 
     */
    private function getSitesArray() {
        $sitesArray = array();
        $this->sitesFile = $this->wire('config')->paths->root . 'sites.json';
        //$handle = fopen($sitesFile, "r");
        //$sitesJSON = fread($handle, filesize($sitesFile));
        $sitesJSON = trim(file_get_contents($this->sitesFile));
        if(strlen($sitesJSON)) $sitesArray = json_decode($sitesJSON, true);
        return $sitesArray;
    }

    /* ######################### - CHECKERS - ######################### */

    /**
     * Check if this module's required files are present in ProcessWire root.
     *
     * @access public
     * @param array $notices For user feedbac if files missing.
     * @return array $notices Updated notices array if no files found.
     * 
     */
    public function requiredModuleFilesCheck($notices) {
        $pwRootPath = $this->getPWRootPath();
        $moduleFiles = array('sites.json', 'index.config.php');
        foreach ($moduleFiles as $file) {
            if(!is_file($pwRootPath . $file)) {
                $notices['errors'][] = sprintf(__('Missing required file: %s. Please copy one from this module\'s \'files\' directory to your ProcessWire root.'), $file);
            }
        }        

        return $notices;

    }

    /**
	 * Do a ProcessWire pre-install compatibility check.
     * 
     * Calls other methods to do specific checks.
     * 
     * @access public
     * @return array $notices Array with error/success messages regarding compatibility check.
	 *
	 */
    public function compatibilityCheck() {

        // here and subsequent methods, @see original compatibilityCheck()
        // @note: some checks moved out to own methods since need to do them post-unzip profile file

        $notices = $this->notices;
        
        ######################################################
        # PHP VERSION CHECK
        $notices = $this->phpVersionCheck($notices);        
        ######################################################       
        # PDO CHECK
        $notices = $this->pdoMySQLCheck($notices);        
        ######################################################
        // @todo: add?
		/* if(self::TEST_MODE) {
            $notices['errors'][] = $this->_('Example error message for test mode');
            $notices['warnings'][] = $this->_('Example warning message for test mode');
        } */
        
        ######################################################
        # REQUIRED FUNCTIONS EXIST CHECK
        $notices = $this->requiredFunctionsCheck($notices);
        ######################################################
        // APACHE MOD REWRITE CHECK: NON-CGI ENVIRONMENT
        $notices = $this->apacheModRewriteCheck($notices);        
        ######################################################
        // ZIPARCHIVE CLASS CHECK
        $notices = $this->zipArchiveCheck($notices);
        
        return $notices;

    }

    /**
     * Check if site profiles are available before any site install.
     *
     * @access public
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function profilesAvailableCheck($notices) {
        $selector = "template=multi-sites-site-profile,parent.name=multi-sites-profiles,limit=1";
        $item = $this->getItems($selector);
        if(!$item->count) $notices['errors'][] = $this->_('No site profiles to install!.');
        return $notices;
    }

    /**
     * Check for minimum required PHP version.
     *
     * @access private
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function phpVersionCheck(Array $notices) {

        if(version_compare(PHP_VERSION, self::MIN_REQUIRED_PHP_VERSION) >= 0) {
            $notices['messages'][] = $this->_('PHP version') .' '. PHP_VERSION;
        } 
        
        else {
            $versionError = $this->_('ProcessWire requires PHP version') .' '. self::MIN_REQUIRED_PHP_VERSION . ' ';
            $versionError .= $this->_('or newer. You are running PHP') . ' ' . PHP_VERSION;
            $notices['errors'][] = $versionError;
        }

        return $notices;

    }

    /**
     * Check for PDO MySQL extension.
     *
     * @access private
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function pdoMySQLCheck(Array $notices) {
        if(extension_loaded('pdo_mysql')) $notices['messages'][] = $this->_('PDO (mysql) database');
        else $notices['errors'][] = $this->_('PDO (pdo_mysql) is required (for MySQL database)');
        return $notices;
    }

    /**
     * Check if several required PHP functions exist.
     *
     * @access private
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function requiredFunctionsCheck(Array $notices) {

        $missing = $this->_('Missing');
        $notMissing = $this->_('OK');
        
        $requiredFunctions = array(
            'filter_var' => $this->_('Filter functions (filter_var)'),
            'mysqli_connect' => $this->_('MySQLi (not required by core, but may be required by some 3rd party modules)'),
            'imagecreatetruecolor' => $this->_('GD 2.0 or newer'),
            'json_encode' => $this->_('JSON support'),
            'preg_match' => $this->_('PCRE support'),
            'ctype_digit' => $this->_('CTYPE support'),
            'iconv' => $this->_('ICONV support'),
            'session_save_path' => $this->_('SESSION support'),
            'hash' => $this->_('HASH support'),
            'spl_autoload_register' => $this->_('SPL support'),
        );

        foreach ($requiredFunctions as $functionName => $note) {
            if($this->functionCheck($functionName)) $notices['messages'][] =  $note . ': ' . $notMissing;
            else $notices['errors'][] =  $note . ': ' . $missing;
        }

        return $notices;
        
    }

    /**
     * Check if mod_rewrite enabled on Apache.
     *
     * @access private
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function apacheModRewriteCheck(Array $notices) {
        // APACHE MOD REWRITE CHECK: NON-CGI ENVIRONMENT
        // @note: PW already running, but we check anyway!
		if(function_exists('apache_get_modules')) {
			if(in_array('mod_rewrite', apache_get_modules())) {
                $notices['messages'][] = $this->_('Found Apache module: mod_rewrite');
            }
            else {
                $notices['errors'][] = $this->_('Apache mod_rewrite does not appear to be installed and is required by ProcessWire.');
            }
        } 

        // APACHE CHECK: CGI ENVIRONMENT
        else {
			// apache_get_modules doesn't work on a cgi installation.
			// check for environment var set in htaccess file, as submitted by jmarjie. 
			$mod_rewrite = getenv('HTTP_MOD_REWRITE') == 'On' || getenv('REDIRECT_HTTP_MOD_REWRITE') == 'On' ? true : false;
			if($mod_rewrite) {
                $notices['messages'][] = $this->_('Found Apache module (cgi): mod_rewrite');
            } 
            else {
                $notices['errors'][] =  $this->_("Unable to determine if Apache mod_rewrite (required by ProcessWire) is installed. On some servers, we may not be able to detect it until your .htaccess file is place. Please click the 'check again' button at the bottom of this screen, if you haven't already.");
			}
        }
        
        return $notices;
        
    }

    /**
     * Check for Zip Archive support.
     *
     * @access private
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function zipArchiveCheck(Array $notices) {
        if(class_exists('ZipArchive')) $notices['messages'][] = $this->_('ZipArchive support');
        else $notices['warnings'][] = $this->_('ZipArchive support was not found. This is recommended, but not required to complete installation.');
        return $notices;        
    }

    /**
     * Check if some required site directories are present.
     *
     * @access public
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function requiredDirectoriesFilesCheck($profileTopDirectory, $notices) {

        $profileTopDirectory = $profileTopDirectory . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);

        # check for site assets and modules directories
        $dirs = array(
            'assets/' => true,
			'modules/' => false, 
			);
		foreach($dirs as $dir => $required) {
            $dir = $path . $dir;
			$d = ltrim($dir, '.'); 
			if(!file_exists($dir)){
                $notices['errors'][] = sprintf(__('Directory %s does not exist! Please create this and make it writable before continuing.'), $d);
            } 
            elseif(is_writable($dir)) $notices['messages'][] = sprintf(__('%s is writable.'), $d);
            elseif($required) {
                $notices['errors'][] = sprintf(__('Directory %s must be writable. Please adjust the server permissions before continuing.'), $d);
            }
            else $notices['warnings'][] = sprintf(__('We recommend that directory %s be made writable before continuing.'), $d);
        }// END foreach
                
        return $notices;
        
    }

    /**
     * Check if the install SQL file is present in a given profile.
     *
     * @param string $profileTopDirectory Path to the site profile to install.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function installFileCheck($profileTopDirectory, $notices) {
        // @see original dbConfig()
        $profileTopDirectory = $profileTopDirectory . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);
        # check for 'install.sql' file/
        if(!is_file($path . '/install/install.sql')) {
            $notices['errors'][] = sprintf(__('There is no installation profile in /site/ in the profile %s. Please place one there before continuing. You can get it at processwire.com/download.'), $this->profileTitle);
        }
        return $notices;        
    }

    /**
     * Check if the config.php file to be installed is writable.
     *
     * @access private
     * @param string $profileTopDirectory Path to the site profile to install.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function configWritableCheck($profileTopDirectory, $notices) {
        // @see original compatibilityCheck()
        $profileTopDirectory = $profileTopDirectory . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);
        if(is_writable($path . "/config.php")) $notices['messages'][] = $this->_('/site/config.php is writable.');
        else {
            $notices['errors'][] = $this->_('/site/config.php must be writable. Please adjust the server permissions before continuing..');
        }
        return $notices;
    }

    /**
     * Checks for htaccess.
     *
     * @access private
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function htaccessCheck(Array $notices) {

        // @note: not using this for now        
		if(!is_file("./.htaccess") || !is_readable("./.htaccess")) {
			if(@rename("./htaccess.txt", "./.htaccess")) {
                $notices['messages'][] = $this->_('Installed .htaccess.');
            }
            else {
            $notices['errors'][] = $this->_("/.htaccess doesn't exist. Before continuing, you should rename the included htaccess.txt file to be .htaccess (with the period in front of it, and no '.txt' at the end).");
        }

        } 
        else if(!strpos(file_get_contents("./.htaccess"), "PROCESSWIRE")) {
            $notices['errors'][] = $this->_('/.htaccess file exists, but is not for ProcessWire. Please overwrite or combine it with the provided /htaccess.txt file (i.e. rename /htaccess.txt to /.htaccess, with the period in front).');
        }
        else $notices['messages'][] = $this->_('.htaccess looks good.');
        
        return $notices;
    
    }   

    /**
	 * Check if the given function $name exists and report OK or fail.
	 *
     * @access private
     * @param string $name Name of a given function to check.
     * @return bool $functionExists Whether function exists.
     * 
	 */
	private function functionCheck($name) {
        $functionExists = false;
        if(function_exists($name)) $functionExists = true; 
        return $functionExists;
    }

    /**
     * Check supplied database connection values
     *
     * @access public
     * @param array $databaseConfigs Database connection configurations.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function databaseCheck($databaseConfigs, $notices) {        

        // @see original dbSaveConfig()
        error_reporting(0); 

        // @note: sanitising already done        
        $dsn = $this->getDataSourceName($databaseConfigs);        
        $driverOptions = $this->getDatabaseDriverOptions();
        
        // database already exists
        try {
            $database = new PDO($dsn, $databaseConfigs['dbUser'], $databaseConfigs['dbPass'], $driverOptions);
            $notices['messages'][] = sprintf(__('Database connection to %s successful.'), $databaseConfigs['dbName']);
        } 
        catch(Exception $e) {
            // @note: db credentials OK but schema does not exist
            if($e->getCode() == 1049) {
                // if schema does not exist, try to create it
                $notices = $this->dbCreateDatabase($dsn, $databaseConfigs, $driverOptions); 
                $notices['messages'][] = $this->_('Database connection information OK but schema does not exist.');
            } 
            
            else {
                $notices['errors'][] = $this->_('Database connection information did not work.'); 
                $notices['errors'][] = $e->getMessage(); 
            }
        }

        return $notices;

    }

    /* ######################### - VALIDATORS - ######################### */

    /**
     * Check if a given pre-installed site title already exists.
     *
     * @access public
     * @param string $title Title to check if exists.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function validatePageTitle($title, $notices){
        $notices['no_duplicate_site_title'] = 1;
        $name = $this->wire('sanitizer')->pageName($title);
        $parent = $this->wire('pages')->get('parent.name=multi-sites,name=multi-sites-installed-sites, template=multi-sites-installed-sites');
        $child = $parent->child( "name={$name}, include=all" )->id;
        //if name already in use
        if($child) $notices['no_duplicate_site_title'] = 0;       
        return $notices;
    }

    /**
     * Check if a site directory with an identical name already exists at root.
     *
     * @access public
     * @param string $siteDirectoryName Name of directory to check if exists.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function validateDuplicateSiteDirectory($siteDirectoryName, $notices){
        $notices['no_duplicate_site_directory'] = 1;
        $siteDirectoryName = 'site-' . $siteDirectoryName;
        if(is_dir($this->wire('config')->paths->root . $siteDirectoryName . '/')) {
            $notices['no_duplicate_site_directory'] = 0;
        }
        return $notices;
    }

    /**
     * Validate an admin login name/url.
     *
     * @access public
     * @param string $adminLoginName The admin name to validate.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function validateAdminLoginName($adminLoginName, $notices) {
        // @see original adminAccountSave()
        $adminName = $this->wire('sanitizer')->pageName($adminLoginName);
        if($adminName != $adminLoginName) $notices['errors'][] = $this->_('Admin login URL must be only a-z 0-9.');
        if($adminName == 'wire' || $adminName == 'site') $notices['errors'][] = $this->_("Admin name may not be 'wire' or 'site'.");
        if(strlen($adminName) < 2) $notices['errors'][] = $this->_('Admin login URL must be at least 2 characters long.');
        return $notices;
    }

    /**
     * Return timezone at given index.
     *
     * @access public
     * @param integer $timezoneID Index to retrieve timezone value at.
     * @return string $timezone Timezone at given index.
     *
     */
    public function validateTimezone($timezoneID) {
        // @see original dbSaveConfig()
        $timezone = $this->getTimeZones($timezoneID);
		if(strpos($timezone, '|')) list($label, $timezone) = explode('|', $timezone); 
        $timezone = $timezone ? $timezone : 'America/New_York';
        return $timezone;
    }

    /**
     * Return formatted Http Hosts.
     *
     * @access public
     * @param string $httpHosts Specified Http Hosts to format.
     * @return array $values Array of formatted Http Hosts.
     *
     */
    public function validateHttpHosts($httpHosts) {

        // @see original dbSaveConfig()
        $values['httpHosts'] = array();
        $httpHosts = trim($httpHosts);
        
		if(strlen($httpHosts)) {
			$httpHosts = str_replace(array("'", '"'), '', $httpHosts);
            $httpHosts = explode("\n", $httpHosts);
            // @todo: use pw sanitizer here?
			foreach($httpHosts as $key => $host) {
				$host = strtolower(trim(filter_var($host, FILTER_SANITIZE_URL)));
				$httpHosts[$key] = $host;
			}
			$values['httpHosts'] = $httpHosts;
        }
        
        return $values;

    }
    
    /**
     * Configure database values.
     *
     * @access public
     * @param array $databaseConfigs Database configuration values to format.
     * @return array $values Array with formatted database values.
     *
     */
    public function validateDatabaseConfigs($databaseConfigs) {
        // @see original dbSaveConfig()
		foreach($databaseConfigs as $key => $databaseConfig) {
			$value = get_magic_quotes_gpc() ? stripslashes($databaseConfig) : $databaseConfig; 
			$value = substr($value, 0, 255); 
			if(strpos($value, "'") !== false) $value = str_replace("'", "\\" . "'", $value); // allow for single quotes (i.e. dbPass)
			$values[$key] = trim($value); 
		}
	
		$values['dbCharset'] = strtolower($databaseConfigs['dbCharset']); 
		$values['dbEngine'] = ($databaseConfigs['dbEngine'] === 'InnoDB' ? 'InnoDB' : 'MyISAM'); 
        if(!ctype_alnum($values['dbCharset'])) $values['dbCharset'] = 'utf8';
        
        return $values;
        
    }

    /**
     * Validate a given Superuser name.
     *
     * @access public
     * @param string $superUserName Superuser name to validate.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     *
     */
    public function validateSuperUserName($superUserName,$notices) {
        // @see original adminAccountSave()
        $username = $this->wire('sanitizer')->pageName($superUserName);        
        if($username != $superUserName) $notices['errors'][] = $this->_('Superuser name must be only a-z 0-9.');
        if(strlen($username) < 2) $notices['errors'][] = $this->_('Superuser name must be at least 2 characters long.');
        return $notices;        
    }
    
    /**
     * Validate a given Superuser password.
     *
     * @access public
     * @param string $superUserPassword Superuser password to validate.
     * @param string $superUserPasswordConfirm Superuser password repeated for confirmation.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     *
     */
    public function validateSuperUserPassword($superUserPassword,$superUserPasswordConfirm,$notices) {
        // @see original adminAccountSave()
        if($superUserPassword !== $superUserPasswordConfirm) $notices['errors'][] = $this->_('Superuser passwords do not match.');
        if(strlen($superUserPassword) < 6) $notices['errors'][] = $this->_('Superuser password must be at least 6 characters long.');
        return $notices;
    }

    /**
     * Validate a given Superuser email.
     *
     * @access public
     * @param string $superUserEmail Email to validate.
     * @param array $notices For check feedback.
     * @return array $notices Updated feedback array.
     *
     */
    public function validateSuperUserEmail($superUserEmail,$notices) {
        // @see original adminAccountSave()
        $email = strtolower($this->wire('sanitizer')->email($superUserEmail)); 
        // @note: just future-proofing since empties not allowed anyway
        if(!$email) $notices['errors'][] = $this->_('Supersuser email address must be provided.');
        if($email != strtolower($superUserEmail)) $notices['errors'][] = $this->_('Superuser email address did not validate.');
        return $notices;
    }

    /**
     * Validate a specified chmod permission.
     *
     * @access public
     * @param string $filePermission Chmod permission to validate.
     * @return bool $valid If chmod permission valid or not.
     *
     */
    public function validateFilePermissions($filePermission) {
        // @see original dbSaveConfig()
        $valid = true;
        if(strlen("$filePermission") !== 3) $valid = false;
        return $valid;
    }

    /**
	 * Validate uploaded files.
	 *
	 * Validation for images vs. non-image files done separately in two separate methods.
	 * Here we only determine the file type and send off for validation.
	 *
	 * @access public
	 * @param string $path Full path to the file to validate.
	 * @param array $options Array of options for the validation.
	 * @return array $valid Array Responses about a file's validity.
	 *
	 */
	public function validateFile($path, Array $options) {		
        
        $valid = array();
        $commonImageExts = $options['commonImageExts'];

        if(!$path) {			
            // just in case method is being used externally
            $valid['valid'] = 'false';
            $valid['error'] = $this->_('A path needs to be specified');
        }		

        elseif(is_file($path)) {
            $file = new SplFileInfo($path);
            if(in_array($file->getExtension(), $commonImageExts)) {
                // @note: currently, we are not doing profile screenshots, so no other type of file except 'zip'
                $valid['valid'] = 'false';
                //$valid = $this->isFileImageValid($path, $options, $file->getExtension());                
            }
            else $valid = $this->isFileOtherValid($path, $options);
        }

        else {
            $valid['valid'] = false;
            $valid['error'] = $this->_('File not found!');			 
        }

        return $valid;

    }
    
    /**
	 * Validate uploaded non-image files.
	 *
	 * We check for mime type.
	 *
	 * @access private
	 * @param string $otherFile The full path to the non-image file to validate.
	 * @param array $options Array of options for the validation.
	 * @return array $valid Array Responses about a file's validity.
	 *
	 */
	private function isFileOtherValid($otherFile, Array $options) {
        
        $valid = array();
        $valid['isImage'] = false;

        $allowedNonImageMimeTypes = $options['allowedNonImageMimeTypes'];
        
        /*
            @note:
                - PHP 5.3.0 and later have Fileinfo built in, but on Windows it must be manually enabled in php.ini. 
                - In earlier versions, PHP had mime_content_type but it is now deprecated.
                - If none of these two available, for security, we assume invalid file and delete it.
        */

        if(function_exists('mime_content_type')) $mime = mime_content_type($otherFile);
        elseif(class_exists('finfo')){
            $finfo = new finfo;
            $mime = $finfo->file($otherFile, FILEINFO_MIME);
            $mime = substr($mime , 0, strpos($mime, ';'));// remove the ; charset=binary appended by this function after the mime_type
        }
        else $mime = '';// to force valid=false and deletion later

        // if mime type matches what we allowed, it is a valid file for upload
        if(in_array($mime, $allowedNonImageMimeTypes)) $valid['valid'] = true;
        else {
            $valid['valid'] = false;
            $valid['error'] = $this->_('Filetype not allowed');
            // @note: we delete the invalid file in getResponse()	
        }

        return $valid;

    }

    /**
	 * Validate uploaded image files.
	 *
	 * Assesses whether uploaded image files are actually images.
	 * We do this by looking at their FILE TYPE CONSTANT.
	 *
	 * @access private
	 * @param string $imageFile The full path to the image file to validate.
	 * @param array $options Array of options for the validation.
	 * @param string $imageFileExt The image file's extension.
	 * @return array $valid Responses about an image file's validity.
	 *
	 */
	private function isFileImageValid($imageFile, Array $options, $imageFileExt) {
        
        $valid = array();
        $valid['isImage'] = true;

        $allowedImageMimeTypes = $options['allowedImageMimeTypes'];

        $isValidImage = false;

        // exif_imagetype is faster, so we attempt to use it first
        if (function_exists('exif_imagetype')) 	{			
                $imageTypeConstants = $options['imageTypeConstants'];
                $imageFileTypeConstant = isset($imageTypeConstants[$imageFileExt]) ? $imageTypeConstants[$imageFileExt] : '';
                if (exif_imagetype($imageFile) == $imageFileTypeConstant) $isValidImage = true;
        }
        
        else {
                $mime = getimagesize($imageFile);
                $mime = $mime['mime'];
                if(in_array($mime, $allowedImageMimeTypes)) $isValidImage = true;
        }

        // if mime type matches what we allowed, it is a valid image for upload
        if($isValidImage) {
            $valid['valid'] = true;
            // if creating thumbnail. @note: let user decide if they WANT a thumbnail (default is false)
            if($options['createThumb'] == true) $this->createThumbnails($imageFile, $options);// if creating image thumbnail
        }

        else {
            $valid['valid'] = false;
            $valid['error'] = $this->_('Filetype not allowed');
            // @note: we delete the invalid file in getResponse()		
        }
                
        return $valid;

    }
    
    /* ######################### - OTHER - ######################### */
  
    /**
	 * Import profiles resources.
     * 
     * Imports both SQL and files.
     * 
     * @access public
     * @param array $values Set values for the import.
	 * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
	 */
    public function profileImport($values, $notices) {

        // @see original profileImport()   
        if(self::TEST_MODE) {
            $notices['messages'][] = $this->_('TEST MODE: Skipping profile import.');
            return $notices;
        }

        $dsn = $this->getDataSourceName($values);
        $driverOptions = $this->getDatabaseDriverOptions();
        $database = new PDO($dsn, $values['dbUser'], $values['dbPass'], $driverOptions);

        $profileTopDirectory = $values['temp_site_directory_name']  . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);
        $profile = $path . 'install/';
        if(!is_file("{$profile}install.sql")) {
            $notices['errors'][] = sprintf(__('No installation profile found in {%s}.'), $profile);
            return $notices;        
        }

        // checks to see if the database exists using an arbitrary query (could just as easily be something else)
        try {
            $query = $database->prepare("SHOW COLUMNS FROM pages"); 
            $result = $query->execute();
        } catch(Exception $e) {
            $result = false;
        }

        if(self::REPLACE_DB || !$result || $query->rowCount() == 0) {
            $this->chmodDir = $values['chmodDir'];
            $this->chmodFile = $values['chmodFile'];
            $this->profileTopDirectory = $values['temp_site_directory_name'] . '/';

            $pathCoreInstallSQL = $this->wire('config')->paths->core . "install.sql";
            $notices = $this->profileImportSQL($database, $pathCoreInstallSQL, $profile . "install.sql", $notices);
            
            // import '/install/files' to '/site-xxx/files/'
            if(is_dir($profile . "files")) $notices = $this->profileImportFiles($profile, $notices);
            else $notices = $this->makeDirectory($path . "assets/files/");
            // make 'site/' : 'cache', 'logs' and 'sessions' directories
            $notices = $this->makeDirectory($path . "assets/cache/", $notices); 
            $notices = $this->makeDirectory($path . "assets/logs/", $notices); 
            $notices = $this->makeDirectory($path . "assets/sessions/", $notices);       
        }       
        
        else $notices['messages'][] = $this->_('A profile is already imported, skipping...');

        /*
            @todo?/@note: the below code in @see original profileImport()
            - the intent is to copy /site-default/ modules to /site/modules/
            - this is because the other profiles that ship with processwire have an empty /site/modules/ folder
            - Hence the modules in /site-default/, e.g. InputfieldCKEditor and HelloWorld are copied over
            - In our case, this is not possible, so will instruct user to make sure their site profiles contain the modules they need?
        /
        // copy site modules /site-xxx/modules/ to /site-xxx/modules/
		/* $dir = "./site/modules/";
		$defaultDir = "./site-default/modules/"; 
		if(!is_dir($dir)) $this->makeDirectory($dir);
		if(is_dir($defaultDir)) {
			if(is_writable($dir)) {
				$result = $this->copyRecursive($defaultDir, $dir, false); 	
				if($result) {
					$this->ok("Imported: $defaultDir => $dir");					
                }
                
                else {
					$this->warn("Error Importing: $defaultDir => $dir"); 
				}
            } 
            else {
				$this->warn("$dir is not writable, unable to install default site modules (recommended, but not required)"); 
			}
		} else {
			// they are installing site-default already 
		} */

        return $notices;

    }

    /**
     * Run notices for user feedback.
     *
     * @access public
     * @param array $notices Notices for user feedback after any action.
     * @param integer $mode Whether to run success, or error or warning messages.
     * 
     */
    public function runNotices(Array $notices, $mode) {
        if(1 == $mode) $noticeType = 'message';
        elseif(2 == $mode) $noticeType = 'error';
        if(3 == $mode) $noticeType = 'warning';
        $notices = implode('<br>', $notices);        
        $this->$noticeType($notices, Notice::allowMarkup);
    }   

    /**
     * Helper method to convert compatibility index to corresponding ProcessWire version.
     *
     * @access public
     * @param integer $versionIndex Determines what value to return.
     * @return value at given index.
     * 
     */
    public function compatibilityConvert($versionIndex) {
        $compatibility = array(1=>'2.7', 2=>'2.8', 3=>'3.x');
        return $compatibility[$versionIndex];
    }    
      
    /**
     * Save/Update the superuser account for a new site.
     *
     * Since we don't have API access to the new site we put a file
     * that we ping (WireHttp) to save the new admin details.
     * No data is sent via the ping. Only a secure notification of intent.
     * 
     * @access public
     * @param array $values Credentials for superuser account.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function adminAccountSave(Array $values, $notices) {

        // @todo...add an install modules manifest?        
        $notices = $this->saveAdminAccountConfig($values, $notices);

        if(1 == $notices['save_admin_config']) {
           
            # post intent to save new admin account
            $data = array($this->tokenKey => $this->tokenValue);            
            $hostAndDomainName = $values['hostAndDomainName'];// @todo:? what if a subfolder?
            $adminLoginName = $values['adminLoginName'];
            
            // @note: on fresh install (before change), PW admin is 'processwire'!
            $postURL = "$hostAndDomainName/processwire/";
    
            // @note: we only post key=>value token to identify ourselves. Rest of credentials will be 'file_put_contents'
            $http = new WireHttp();
            $result = $http->post($postURL, $data);

            if($result) $notice['messages'][] = $this->_('Admin account successfully saved.');    
            else $notices['errors'][] = $this->_('Error saving admin account.');

            $newSitePath = $this->getSitePath($values['siteDirectoryName']); // @note: includes getPWRootPath()         
            $templatesPath =  $newSitePath . '/templates/';
            $assetsPath = $newSitePath . '/assets/';
            $tempAdminPHPFile = $templatesPath . 'admin.php';
            $installedSiteFile = $assetsPath . 'installed.php';
            $installedFileData = "<?php // The existence of this file prevents the installer from running. Don't delete it unless you want to re-run the install or you have deleted ./install.php.";
            
            # delete temporary admin.php
            if(is_file($tempAdminPHPFile)) unlink($tempAdminPHPFile);
            
            # revert original admin.php
            $originalAdminPHPFile = $templatesPath . 'ORIGINAL-admin.php';
            $adminPHPFile = $templatesPath . 'admin.php';
            $this->rename($originalAdminPHPFile, $adminPHPFile);

            // install the 'installed.php' file to denote site is installed
            if(!self::TEST_MODE) $this->filePutContents($installedSiteFile, $installedFileData);

        }

        // error out
        else $notices['errors'][] = $this->_('Admin account details could not be updated');

        return $notices;

    }

    /**
     * Writes to a file to securely save the admin account for the new site.
     *
     * @access private
     * @param array $values Credential for admin account save.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    private function saveAdminAccountConfig(Array $values, $notices) {

        $sourcePath = dirname(__FILE__) . '/files/';
        $adminConfigFile = $sourcePath . 'admin.config.txt';
        $newSitePath = $this->getSitePath($values['siteDirectoryName']); // @note: includes getPWRootPath()         
        $templatesPath =  $newSitePath . '/templates/';

        if(is_file($adminConfigFile)) {
            copy($adminConfigFile, $sourcePath . 'admin.config.php');
        }
        $adminConfigFile2 = $sourcePath. 'admin.config.php';

        /* 
            - generate one time has key=>value pair to post as post-name => post->value 
            - needed to update admin account on newly installed site
            - this is because we don't have access to that site from this module
            - hence we'll create a temporary admin.php to post to using WireHttp()
            - we only post the key=>value token pair
            - we are using the below inorder to avoid posting sensitive data
            - @see: $this->adminAccountSave()
        */
        
        $this->tokenKey = $tokenKey = 'MS_' . md5(mt_rand() . microtime(true)); 
        $this->tokenValue = $tokenValue = $tokenValue = 'MS_' . md5(mt_rand() . microtime(true)); 
        $sessionIP = $this->wire('session')->getIP();

        ############################# CONTENT TO APPEND TO TEMPORARY admin.php #############################

        $cfg =        
            "\$adminAccountArray = array(" .
            "\n\t'superUserName' => '$values[superUserName]'," .
            "\n\t'superUserPassword' => '$values[superUserPassword]'," .
            "\n\t'superUserEmail' => '$values[superUserEmail]'," .
            "\n\t'adminLoginName' => '$values[adminLoginName]'," .
            "\n\t'colourTheme' => '$values[colourTheme]'," .
            "\n\t'adminTheme' => '$values[adminTheme]'," .
            "\n);";

        $cfg .= 
            "\n" .
            "\nif(\$input->post->$tokenKey === '$tokenValue' && \$_SERVER['REMOTE_ADDR'] === '$sessionIP') {" .
                "\n\tadminAccountSave(\$adminAccountArray);" .       
            "\n};";
    
        ############################# APPEND CONTENT TEMPORARY admin.php #############################

        if(is_file($adminConfigFile2)) {
            if(($fp = fopen($adminConfigFile2, "a")) && fwrite($fp, $cfg)) {
                fclose($fp);
                // temporarily rename the current 'admin.php' file
                $result = $this->rename($templatesPath . 'admin.php', $templatesPath . 'ORIGINAL-admin.php');
                // create our temporary admin.php to ping in order to save new admin details
                if($result) $result = $this->rename($adminConfigFile2, $templatesPath . 'admin.php'); 
                if($result) {
                    $notices['messages'][] = $this->_('Successfully prepared save admin configurations');
                    $notices['save_admin_config'] = 1;
                }
                else {
                    $notices['messages'][] = $this->_('Could not prepare save admin configurations.');
                    $notices['save_admin_config'] = 0;
                }                
            }            
        }

        else  $notices['messages'][] = $this->_('Could not create save admin configurations.');

        return $notices;

    }
    
    /* ######################### - FILE FUNCTIONS - ######################### */

    /**
     * Wrapper method for PHP's file_put_contents() function.
     *
     * @access private
     * @param string $filename Absolute path to file to write to.
     * @param string $data String to write to file.
     * @return boolean $results Whether file written to or not.
     * 
     */    
    private function filePutContents($filename, $data) {
        // @todo:? lock_ex flag?
        $result = file_put_contents($filename, $data);
        return $result;
    }
    
    /**
     * Wrapper method for PHP's rename() function.
     *
     * @access private
     * @param string $oldname Absolute path directory/file to rename.
     * @param string $newname Absolute path to directory/file denoting new name.
     * @return bolean $result Whether directory/file successfully renamed or not.
     * 
     */
    private function rename($oldname, $newname) {
        $result = rename($oldname, $newname);
        return $result;
    }

    /**
     * Create a directory and assign permission.
     *
     * @access public
     * @param string $path Path to create directory at.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function makeDirectory($path, $notices) {
        // @see original mkdir()
        if(self::TEST_MODE) return;
        if(is_dir($path) || mkdir($path)) {
            chmod($path, octdec($this->chmodDir));
            $notices['messages'][] = sprintf(__('Created directory: %s.'), $path);
        }        
        else $notices['errors'][] = sprintf(__('Error creating directory: %s.'), $path);

        return $notices;
    }

    /**
     * Remove directories recursively.
     * 
     * Uses ProcessWires wireRmdir() function.
     * 
     * @access public
     * @param string $path Path to directory to remove.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function removeDirectory($path, $notices) {
        if(self::TEST_MODE) return;
        if(wireRmdir($path, true)) {
            $notices['messages'][] = sprintf(__('Successfully removed directory at: %s.'), $path);
        }
        else {            
            $notices['errors'][] = sprintf(__('Failed to remove directory at: %s. Please remove it manually.'), $path);
        }
        return $notices;
    }

    /**
     * Copy directories recursively.
     * 
     * @access public
     * @param string $src Absolute path to source directory.
     * @param string $dst Absolute path to destination to copy to.
     * @param boolean $overwrite Whether to overwrite destination if identical directory/file found. 
     * @return boolean Whether copy was successful or not.
     * 
     */
    public function copyRecursive($src, $dst, $overwrite = true) {
        // @see copyRecursive() in original install.php

        if(self::TEST_MODE) return;

        if(substr($src, -1) != '/') $src .= '/';
        if(substr($dst, -1) != '/') $dst .= '/';

        $dir = opendir($src);
        $this->makeDirectory($dst, false);

        while(false !== ($file = readdir($dir))) {
            if($file == '.' || $file == '..') continue; 
            if(is_dir($src . $file)) {
                $this->copyRecursive($src . $file, $dst . $file);
            } else {
                if(!$overwrite && file_exists($dst . $file)) {
                    // don't replace existing files when $overwrite == false;
                } else {
                    copy($src . $file, $dst . $file);
                    chmod($dst . $file, octdec($this->chmodFile));
                }
            }
        }

        closedir($dir);
        return true; 
    }

    /**
     * Add a new site entry to sites.json.
     * 
     * This file holds all multisites sites' info for use by index.config.php.
     *
     * @access public
     * @param string $hostAndDomainName The domain to write to sites.json.
     * @param string $siteDirectoryName The site directory name to write to sites.json.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function addToSitesJSON($hostAndDomainName, $siteDirectoryName, $notices) {
        $sitesArray = $this->getSitesArray();
        // update the sites array
        $sitesArray[$hostAndDomainName] = 'site-' . $siteDirectoryName;
        // update the sites.json file (content)
        $updatedSitesJSON = json_encode($sitesArray);
        // check if write OK (integer if OK boolean false if not)
        $sitesFile = $this->filePutContents($this->sitesFile, $updatedSitesJSON);// @todo:? lock_ex flag?
        if($sitesFile) $notices['messages'][] = $this->_('Successfully updated sites.json file');
        else $notices['errors'][] = $this->_('The sites.json file could not be updated. Please check that it is writable.');
        return $notices;        
    }

    /**
     * Remove a site entry from sites.json.
     * 
     * These are sites that have had their directories and/or databases deleted.
     * Their page records have also been deleted.
     * We remove them from sites.json since they are now inexistent.
     *
     * @param array array $removedSites Entries (directories) of sites to remove.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function removeFromSitesJSON(Array $removedSites, $notices) {
        $sitesArray = $this->getSitesArray();
        $updatedSitesArray = array_filter($sitesArray, function($a) use($removedSites) { 
            return !in_array($a, $removedSites,true);
        });

        // update the sites.json file (content)
        $updatedSitesJSON = json_encode($updatedSitesArray);

        $sitesJSONFile = $this->getPWRootPath() . 'sites.json';
        $sitesFile = $this->filePutContents($sitesJSONFile, $updatedSitesJSON);// @todo:? lock_ex flag?
        if($sitesFile) $notices['messages'][] = $this->_('Successfully updated sites.json file');
        else $notices['errors'][] = $this->_('The sites.json file could not be updated. Please check that it is writable.');
        return $notices;
    }

    /**
     * Unzip a profile file.
     *
     * @param string $profileFile Absolute path to profile file to uncompress.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function unzipProfileFile($profileFile, $notices) {
        
        $unzip = array();

        // if profile file not found
        if(!is_file($profileFile)) $notices['errors'][] = $this->_('No profile file found at the path.');
        else {
            // use in-built pw method. returns an array with unzipped files and folders
            $unzip = wireUnzipFile($profileFile, $this->privateTempSitesDir);
            if(count($unzip)) $notices['messages'][] = $this->_('Successfully unzipped profile file.');
            else $notices['errors'][] = $this->_('The profile directories are empty!');
        }

        return $notices;
        
    }

    /**
	 * Import profile files to installed site.
     * 
     * @access protected
     * @param string $fromPath Absolute path to import from.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
	 */
	protected function profileImportFiles($fromPath, $notices) {
        
        // @see original profileImportFiles()
        
        if(self::TEST_MODE) {
            $notices['messages'][] = sprintf(__('TEST MODE: Skipping file import -  %s.'), $fromPath);
            return $notices;
        }

        $profileTopDirectory = $this->profileTopDirectory;
        $path = $this->getInstallProfilePath($this->profileTopDirectory);

        $dir = new DirectoryIterator($fromPath);

        // move /site-installed/install/files/ to /site-installed/assets/
        foreach($dir as $file) {
            if($file->isDot()) continue; 
            if(!$file->isDir()) continue;
            $dirname = $file->getFilename();
            $pathname = $file->getPathname();
            if(is_writable($pathname) && self::FORCE_COPY == false) $result = $this->rename($pathname, $path . "assets/$dirname/"); 
            else $result = $this->copyRecursive($pathname, $path . "assets/$dirname/"); 
            if($result) {
                $notices['messages'][] = sprintf(__('Imported: %1$s => ./%2$sassets/%3$s/'), $pathname, $profileTopDirectory, $dirname);
            }
            else {
                $notices['errors'][] = sprintf(__('Error Importing: %1$s => ./%2$sassets/%3$s/'), $pathname, $profileTopDirectory, $dirname);
            }
        }

        return $notices;

    }

    /**
     * Remove the install directory after installing a site.
     *
     * @access public
     * @param string $profileTopDirectory Absolute path to the directory with the install folder.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function removeInstallDirectory($profileTopDirectory, $notices) {
        $profileTopDirectory = $profileTopDirectory . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);
        $profileInstallDirectory = $path . 'install/';
        if(is_dir($profileInstallDirectory)) $notices = $this->removeDirectory($profileInstallDirectory, $notices);
        else {
            // @todo:?
        }
        return $notices;
    }

    /**
     * Rename and move the newly installed site to ProcessWire's root.
     * 
     * The site will then be accessible as a normal multi-site.
     *
     * @access public
     * @param string $profileTopDirectory Absolute path to the newly installed site that needs moving.
     * @param string $siteDirectoryName Name to use when renaming the newly installed site's directory.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function renameAndMoveSite($profileTopDirectory, $siteDirectoryName, $notices) {
        
        $profileTopDirectory = $profileTopDirectory . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);
        
        // @note: we assume root (of PW) is writable since module running on existing install!
        $newSitePath = $this->getSitePath($siteDirectoryName);// @note: includes getPWRootPath()
        
        if(is_dir($path)) {
            $result = $this->rename($path, $newSitePath); 
            if($result) $notices['messages'][] = $this->_('Successfully renamed and moved site directory to root.');
            else {
                $notices['errors'][] = $this->_('Failed to rename and/or move site directory to root. Please check that your ProcessWire root folder is writable.');
            }
        }
        // for whatever reason, site directory has gone away!
        else $notices['errors'][] = sprintf(__('Site directory not found at %s.'), $path);

        return $notices;

    }
    
    /* ######################### - DATABASE FUNCTIONS - ######################### */

    /**
	 * Import a profile's SQL dump.
     * 
     * @access private
     * @param object $database The database to import to.
     * @param string $file1 Absolute path to ProcessWire's core sql file to import.
     * @param string $file2 Absolute path to the profile's sql file to import.
	 * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
	 */
	private function profileImportSQL($database, $file1, $file2, $notices, array $options = array()) {
        
        // @see original profileImportSQL()

        $defaults = array(
            'dbEngine' => 'MyISAM',
            'dbCharset' => 'utf8', 
            );
        
        $options = array_merge($defaults, $options); 
        if(self::TEST_MODE) return;
        $restoreOptions = array();
        $replace = array();
        if($options['dbEngine'] != 'MyISAM') {
            $replace['ENGINE=MyISAM'] = "ENGINE=$options[dbEngine]";
            $notices['warnings'][] = sprintf(__('Engine changed to %s, please keep an eye out for issues.'), $options['dbEngine']);
        }
        if($options['dbCharset'] != 'utf8') {
            $replace['CHARSET=utf8'] = "CHARSET=$options[dbCharset]";
            $notices['warnings'][] = sprintf(__('Character set has been changed to %s, please keep an eye out for issues.'), $options['dbCharset']);
        }
        if(count($replace)) $restoreOptions['findReplaceCreateTable'] = $replace; 
        
        $wireDatabaseBackupFile = $this->wire('config')->paths->core . "WireDatabaseBackup.php";
        require($wireDatabaseBackupFile); 
        $backup = new WireDatabaseBackup(); 
        $backup->setDatabase($database);
        
        if($backup->restoreMerge($file1, $file2, $restoreOptions)) {
            $notices['messages'][] = sprintf(__('Imported database file: %s.'), $file1);
            $notices['messages'][] = sprintf(__('Imported database file: %s.'), $file2);
        } 
        
        else {
            foreach($backup->errors() as $error) $notices['errors'][] = $error;
        }
        
        return $notices;

    }        
    
    /**
	 * Create database.
	 * 
	 * Note: only handles database names that stick to ascii _a-zA-Z0-9.
	 * For database names falling outside that set, they should be created
	 * ahead of time. 
	 * 
	 * Contains contributions from @plauclair PR #950
	 * 
     * @access protected
	 * @param string $dsn DSN (data source name) values for PDO connection.
	 * @param array $values The database connected credentials.
	 * @param array $driverOptions Connection driver options
	 * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
	 * 
	 */
	protected function dbCreateDatabase($dsn, $values, $driverOptions, $notices) {
        
        // @see original dbCreateDatabase()
        $dbCharset = preg_replace('/[^a-z0-9]/', '', strtolower(substr($values['dbCharset'], 0, 64)));
        $dbName = preg_replace('/[^_a-zA-Z0-9]/', '', substr($values['dbName'], 0, 64));
        $dbNameTest = str_replace('_', '', $dbName);

        if(ctype_alnum($dbNameTest) && $dbName === $values['dbName']
            && ctype_alnum($dbCharset) && $dbCharset === $values['dbCharset']) {
            
            // valid database name with no changes after sanitization
            try {
                $dsn2 = "mysql:host=$values[dbHost];port=$values[dbPort]";
                $database = new PDO($dsn2, $values['dbUser'], $values['dbPass'], $driverOptions);
                $database->exec("CREATE SCHEMA IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET `$dbCharset`");
                // reconnect
                $database = new PDO($dsn, $values['dbUser'], $values['dbPass'], $driverOptions);
                if($database) {
                    $notices['messages'][] = sprintf(__('Created database: %s.'), $dbName);
                    $notices['database'] = 1;
                }

            } catch(Exception $e) {
                $notices['errors'][] = sprintf(__('Failed to create database with name %s.'), $dbName);
                $notices['errors'][] = $e->getMessage();
            }
            
        } 
        
        else {
            $notices['errors'][] = $this->_('Unable to create database with that name. Please create the database with another tool and try again.');
        }
        
        return $notices;

    }

    /**
     * Save (append) configuration to /site/config.php.
     *
     * @access public
     * @param array $values Values to write to configuration file.
     * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
     */
    public function dbSaveConfigFile(Array $values, $notices) {

        // @see original dbSaveConfigFile()

        if(self::TEST_MODE) return true; 

        $salt = md5(mt_rand() . microtime(true)); 

        $cfg = 	"\n/**" . 
            "\n * Installer: Database Configuration" . 
            "\n * " . 
            "\n */" . 
            "\n\$config->dbHost = '$values[dbHost]';" . 
            "\n\$config->dbName = '$values[dbName]';" . 
            "\n\$config->dbUser = '$values[dbUser]';" . 
            "\n\$config->dbPass = '$values[dbPass]';" . 
            "\n\$config->dbPort = '$values[dbPort]';";
        
        if(!empty($values['dbCharset']) && strtolower($values['dbCharset']) != 'utf8') $cfg .= "\n\$config->dbCharset = '$values[dbCharset]';";
        if(!empty($values['dbEngine']) && $values['dbEngine'] == 'InnoDB') $cfg .= "\n\$config->dbEngine = 'InnoDB';";
        
        $cfg .= 
            "\n" . 
            "\n/**" . 
            "\n * Installer: User Authentication Salt " . 
            "\n * " . 
            "\n * Must be retained if you migrate your site from one server to another" . 
            "\n * " . 
            "\n */" . 
            "\n\$config->userAuthSalt = '$salt'; " . 
            "\n" . 
            "\n/**" . 
            "\n * Installer: File Permission Configuration" . 
            "\n * " . 
            "\n */" . 
            "\n\$config->chmodDir = '0$values[chmodDir]'; // permission for directories created by ProcessWire" . 	
            "\n\$config->chmodFile = '0$values[chmodFile]'; // permission for files created by ProcessWire " . 	
            "\n" . 
            "\n/**" . 
            "\n * Installer: Time zone setting" . 
            "\n * " . 
            "\n */" . 
            "\n\$config->timezone = '$values[timezone]';" . 	
            "\n\n";

        if(!empty($values['httpHosts'])) {
            $cfg .= "" . 
            "\n/**" . 
            "\n * Installer: HTTP Hosts Whitelist" . 
            "\n * " . 
            "\n */" . 
            "\n\$config->httpHosts = array("; 
            foreach($values['httpHosts'] as $host) $cfg .= "'$host', ";
            $cfg = rtrim($cfg, ", ") . ");\n\n";
        }
            
        $profileTopDirectory = $values['temp_site_directory_name']  . '/';
        $path = $this->getInstallProfilePath($profileTopDirectory);
        $configFile = $path . 'config.php';

        if(($fp = fopen($configFile, "a")) && fwrite($fp, $cfg)) {
            fclose($fp);
            $notices['messages'][] = sprintf(__('Saved configuration to /%sconfig.php.'), $profileTopDirectory);
        } 
        
        else {
            $notices['messages'][] = sprintf(__('Error saving configuration to /%sconfig.php. Please make sure it is writable.'), $profileTopDirectory);
        }

        return $notices;
    
    }

    /**
	 * Drop a deleted site's database.
	 *
	 * @access public
     * @param string $dbName Name of database to be deleted.
	 * @param array $notices For user feedback.
     * @return array $notices Updated feedback array.
     * 
	 */
	public function dropSiteDatabase($dbName, $notices) {
        
        $database = $this->wire('database');

        try {
            $sql = "DROP DATABASE `$dbName`";
            $query = $database->prepare($sql);
            $query->execute();
            $notices['messages'][] = sprintf(__('Successfully dropped the database: %s.'), $dbName);
            $notices[$dbName] = 1;
        }
        catch(Exception $e) {
            $notices['errors'][] = sprintf(__('Could not drop the database: %s. Please remove it manually.'), $dbName);
            $notices['errors'][] = $e->getMessage(); 
        }

        return $notices;

    }

    

}