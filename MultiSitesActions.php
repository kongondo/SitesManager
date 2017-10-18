<?php

/**
* Multi Sites: Actions
*
* This file forms part of the Multi Sites Suite.
* Executes various runtime CRUD tasks for the module.
*
* @author Francis Otieno (Kongondo)
* @version 0.0.2
*
* This is a Free Module.
* Some chuncks of code lifted from the official ProcessWire installer (install.php).
* @credits: Ryan Cramer
*
* ProcessMultiSites for ProcessWire
* Copyright (C) 2017 by Francis Otieno
* This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
*
*/

class MultiSitesActions extends ProcessMultiSites {

	public function __construct() {
        parent::__construct();
        $this->msUtilities = new MultiSitesUtilities();
    }


    /**
	 * Handles various CRUD and related actions for the module.
	 *
	 * Calls other methods for specific actions
	 *
	 *	@access public
     *	@param String $actionType Specifies the specific action to take.
     *	@param object $post Form with items to action.
	 *
	 */
    public function actionItems($actionType, $post) {

        $this->wire('session')->CSRF->validate();// validate against CSRF        
 
        if($actionType == 'installed')	    $this->actionInstalled($post);// bulk edit installed sites
        elseif($actionType == 'create')	$this->actionCreate($post);// single create sites form
        elseif($actionType == 'profiles')	$this->actionProfiles($post);// bulk edit profiles
        elseif($actionType == 'upload' || $actionType == 'edit_profile') $this->actionUpload($post);// single upload profile/edit uploaded profile
        elseif($actionType == 'configs')	$this->actionConfigs($post);// bulk edit installation configurations
        elseif($actionType == 'config' || $actionType == 'edit_config') $this->actionConfig($post);// single config/edit config
        elseif($actionType == 'versions')	$this->actionVersions($post);// bulk edit pw versions 
        elseif($actionType == 'cleanup')	$this->actionCleanup($post);// single cleanup form
         
        // @note: $this->notices updated in above action methods
        $notices = $this->notices;
 
        // @todo?: ideally, if error, we need to return the form values! however, we have done client-side validation, so...
        // check for success/error messages RE actions
        if(count($notices['errors'])) $this->msUtilities->runNotices($notices['errors'], 2);
        elseif(count($notices['messages'])) $this->msUtilities->runNotices($notices['messages'], 1);
        if(count($notices['warnings'])) $this->msUtilities->runNotices($notices['warnings'], 3);
        // create/install site and view sites list
        if($post->ms_create_and_view_btn && !count($notices['errors'])) $url = $this->wire('page')->url;
        // upload profile and view profiles list
        elseif($post->ms_upload_and_view_btn && !count($notices['errors'])) $url = $this->wire('page')->url . 'profiles/';
        // create/add install configuration and view configurations list
        elseif($post->ms_config_add_and_view_btn && !count($notices['errors'])) $url = $this->wire('page')->url . 'configs/';
        // redirect in a modal
        elseif(isset($notices['redirect'])) $url = $notices['redirect'] . '?modal=1';
        // all else
        else $url = '.';
        $this->session->redirect($url);
         
    }

    /**
	 * Bulk action installed sites.
	 *
	 * The method calls other methods for specific actions.
	 *
	 *	@access private
	 *	@param object $post Form with items to process.
	 *
	 */
    private function actionInstalled($post) {
        
        $action = $this->wire('sanitizer')->fieldName($post->ms_items_action_select);
        
        // @note: array
        $items = $post->ms_items_action_selected;

        if('select' == $action) {
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select an action to apply.');
            return;
        }

        if(!count($items)) {// @note: we also do some client-side validations
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select installed sites to action.');
            return;
        }
        
        // @note: these actions also delete the install page record!
        // @note: we skip locked records for all actions
        if('lock' == $action) $this->actionLock($items, 1);
        else if('unlock' == $action) $this->actionLock($items, 0);
        else if('delete_directory' == $action) $this->actionDeleteDirectory($items);
        else if('delete_database' == $action) $this->actionDeleteDatabase($items);
        else if('delete_all' == $action) {
            $this->actionDeleteDirectory($items, true);
            $this->actionDeleteDatabase($items);
        }

        // @note: update sites.json if deleted directory or database since site wont be found!
        if(count($this->removedSites)) {
            $this->notices = $this->msUtilities->removeFromSitesJSON($this->removedSites, $this->notices);
        }

    }

    /**
	 * Install a ProcessWire site.
	 *
	 * Sites are installed as per the ProcessWire multisites strategy.
	 *
	 *	@access private
	 *	@param object $post Form with settings for site creation.
	 *
	 */
	private function actionCreate($post) {

        // process and set properties
        $this->siteType = (int) $post->ms_create_site_type;
        $this->createMethod = (int) $post->ms_create_method;

        $single = 1 === $this->siteType ? true : false;

        // process and set properties
        if(1 == $this->createMethod) $this->actionCreateForm($post);
        elseif(2 == $this->createMethod) $this->actionCreateTypePaste($post);
        elseif(3==$this->createMethod) $this->actionCreateInstallConfig($post);

        // @note: we also do some client-side validations

        # PREPARE VARIABLES.
        $sanitizer = $this->wire('sanitizer');
        $checkEmpty = array();

        # 1. SITE (10)
        
        $title = $checkEmpty['title'] = $sanitizer->text($post->ms_site_title);

        // check if an installed site with that title already exists
        //  @todo: amend to make generic e.g. for use here and in action config?
        $this->notices = $this->msUtilities->validatePageTitle($title, $this->notices);
        if(0 == $this->notices['no_duplicate_site_title']) {
            $this->notices['errors'][] = $this->_('Multi Sites: An installed site with that title already exists. Amend the title and try again.');
            return;
        }

        $description = $sanitizer->purify($post->ms_site_description);// @note: not required
        
        // multi-site: install directory (string)
        $siteDirectoryName = $this->siteDirectoryName;
        // single-site: install directory (path)
        $installDirectoryPath = $this->installDirectoryPath;
        // single site: processwire version
        $processWireVersion = $this->processWireVersion;

        if(!$single) {
            $checkEmpty['directory'] = $this->siteDirectoryName;
        }
        else {
            $checkEmpty['directory'] = $installDirectoryPath;
            $checkEmpty['directory'] = $processWireVersion;
        }        

        $hostAndDomainName = $checkEmpty['hostAndDomainName'] = $this->hostAndDomainName;        
        $adminLoginName = $checkEmpty['adminURL'] = $this->adminLoginName;// @note:doing other validations later

        // creating via form or saved (and not type or paste)
        if(2 !== $this->createMethod) {
            // colour theme
            $colourThemeID = $checkEmpty['colourTheme'] = $this->colourThemeID;
            // get colour theme by name
            $colourTheme = $colourThemeID ? $this->colours[$colourThemeID] : $this->colours[1];
            // admin theme
            $adminThemeID = $checkEmpty['adminTheme'] = $this->adminThemeID;
            // get admin theme by name
            $adminTheme = $adminThemeID ? $this->adminThemes[$adminThemeID] : $this->adminThemes[1];
            $timezoneID = $checkEmpty['timezoneID'] = $this->timezoneID;
        }
        // creating via type or paste
        else {
            // @todo: just to be sure, check if in array $this->colours and $this->adminthemes respectively?? return error if not?
            $colourTheme = mb_strtolower($this->colourTheme);// @todo: here or ealier?
            $adminTheme = $this->pwAdminTheme;
        }        
                
        // @note: value not required so not adding to checkempty
        // @note: not required
        $httpHostsNames = $this->httpHostsNames;
        $profile = $checkEmpty['profile'] = $this->profile;

        # 2. DATABASE (5) @note: we sanitize these later in MultisitesUtilities::validateDatabaseConfigs
        // @todo...these sanitizations OK?
        $dbName = $checkEmpty['dbName'] = $this->dbName;
        $dbUser = $checkEmpty['dbUser'] = $this->dbUser;
        $dbPass = $checkEmpty['dbPass'] = $this->dbPass;
        $dbHost = $checkEmpty['dbHost'] = $this->dbHost;
        $dbPort = $checkEmpty['dbPort'] = $this->dbPort;    
        $dbCharset = $this->dbCharset;// @note: not currently in use @todo?
        $dbEngine = $this->dbEngine;// @todo: -ditto-

        # 3. SUPERUSER (4)
        // @note: validation as well below
        $superUserName = $checkEmpty['superUserName'] = $this->superUserName;
        $superUserPassword  = $checkEmpty['superUserPassword']= $this->superUserPassword;
        $superUserPasswordConfirm = $checkEmpty['superUserPasswordConfirm'] = $this->superUserPasswordConfirm;
        $superUserEmail = $checkEmpty['superUserEmail'] = $this->superUserEmail;

        #4. FILE PERMISSIONS (2)        
        $directoriesPermissions = $checkEmpty['directoriesPermissions'] = $this->directoriesPermissions;
        $filesPermissions = $checkEmpty['filesPermissions'] = $this->filesPermissions;

        /***************************************************************************************************************/

        # CHECK EMPTIES
        $empties = array_filter($checkEmpty, function($var){return empty($var);} );

        if(count($empties)) {
            $error = $this->_('Multi Sites: Some required settings were not completed. These are') . ':<br> ' . implode("<br>",array_keys($empties));
            $this->notices['errors'][] = $error;
            return;
        }

        # EXTRA VALIDATIONS

        // if single directory is path; else it is a single string for multi sites
        $directory = $single ? $installDirectoryPath : $siteDirectoryName;

        /*
            @note: check if:
            single-sites: the specified $installDirectoryPath has already been created at the given webroot. If not abort!
            multi-sites: an identical site $directory already exists in this PW root!  if yes, abort!
        */

        $this->notices = $this->msUtilities->validateDuplicateSiteDirectory($directory, $this->siteType, $this->notices);
        if(1 == $this->notices['directory_error']) {
            if($single) $directoryError = $this->_('Multi Sites: An install directory for your single-site does not exist! Please create the directory at the specified path first.');
            else $directoryError = $this->_('Multi Sites: An installed site with that directory already exists. Please specify a different directory name for the new multi-site.');
            $this->notices['errors'][] = $directoryError;
            return;
        }

        // admin validations
        $this->notices = $this->msUtilities->validateAdminLoginName($adminLoginName, $this->notices);
        // timezone validations
        $timezone = $this->timezone ? $this->timezone : $this->msUtilities->validateTimezone($timezoneID);
        // http hostnames validations
        $httpHostsNames = $this->msUtilities->validateHttpHosts($httpHostsNames);// @note: returns array
        // database configs validations
        $databaseConfigs = array(
            'dbName' => $dbName,
            'dbUser' => $dbUser,
            'dbPass' => $dbPass,
            'dbHost' => $dbHost,
            'dbPort' => $dbPort,
            'dbEngine' => $dbEngine,// @todo: @see note above
            'dbCharset' => $dbCharset,// @todo: -ditto-
        );
        $databaseConfigs = $this->msUtilities->validateDatabaseConfigs($databaseConfigs);// @note: array
        // supersuer validations
        $this->notices = $this->msUtilities->validateSuperUserName($superUserName, $this->notices);
        $this->notices = $this->msUtilities->validateSuperUserPassword($superUserPassword,$superUserPasswordConfirm, $this->notices);
        $this->notices = $this->msUtilities->validateSuperUserEmail($superUserEmail, $this->notices);
        // file permissions validations
        $this->notices['directories_permission'] = 1;
        $this->notices['files_permission'] = 1;
        $permissionsArray = array('directories' => $directoriesPermissions, 'files' => $filesPermissions);
        foreach ($permissionsArray as $permission => $value) {
            $validPermission = $this->msUtilities->validateFilePermissions($value);
            if(!$validPermission) {
                $this->notices['errors'][] = sprintf(__('Value for %s permissions is invalid.'), $permission);
                $this->notices[$permission . '_permission'] = 0;
            }
            else ${"{$permission}Permissions"} = $value;
        }
        // return if validation errors found
        if(count($this->notices['errors'])) return;

        # GET AND UNZIP PROFILE FILE
        $profileFile = $this->msUtilities->getProfileFile($profile);
        $this->notices = $this->msUtilities->unzipFile($profileFile['path'], $this->privateTempSitesDir, $this->notices);

        // return if unzip profile errors
        if(count($this->notices['errors'])) return;
        
        # PROFILE CHECK
        $profileTopDirectory = $profileFile['name'];
        $this->notices = $this->msUtilities->requiredDirectoriesFilesCheck($profileTopDirectory, $this->notices);
        $this->notices = $this->msUtilities->installFileCheck($profileTopDirectory, $this->notices);
        $this->notices = $this->msUtilities->configWritableCheck($profileTopDirectory, $this->notices);

        // return if fail profile checks
        if(count($this->notices['errors'])) return;
        
        # CHECK & CREATE DATABASE: @see original dbSaveConfig() + dbCreateDatabase()
        $this->notices = $this->msUtilities->databaseCheck($databaseConfigs, $this->notices);
        /* if(1 != $this->notices['database'] || 1049 != $this->notices['database']) {            
            // means we got errors; return
            return;
        } */
        //
        // return if database errors
        if(count($this->notices['errors'])) return;
     
        # SAVE CONFIG FILE (site/config.php) @see dbSaveConfigFile()

        // @note: prepare values for writing to /site/config.php
        $values = array();
        $values = array_merge($values, $databaseConfigs, $httpHostsNames);
        $values['chmodDir'] = $directoriesPermissions;
        $values['chmodFile'] = $filesPermissions;
        $values['timezone'] = $timezone;
        $values['temp_site_directory_name'] = $profileTopDirectory;
        #$values['site_directory_name'] = $siteDirectoryName;// @todo: need this?
        
        // write to config.php
        $this->notices = $this->msUtilities->dbSaveConfigFile($values, $this->notices);
        // return if write to config file errors
        if(count($this->notices['errors'])) return;

        # IMPORT PROFILES DATA (SQL + FILES)
        // if we got here, it means we wrote to /site/config.php OK
        // @note: method also calls profileImportSQL() and profileImportFiles()
        $this->notices = $this->msUtilities->profileImport($values, $this->notices);
        // return if errors importing profiles
        if(count($this->notices['errors'])) return;

        # REMOVE /SITE/INSTALL FOLDER + RENAME & MOVE SITE FOLDER! 
        // @todo...is this ok for single sites?
        $this->notices = $this->msUtilities->removeInstallDirectory($profileTopDirectory, $this->notices);
        
        $this->notices = $this->msUtilities->renameAndMoveSite($profileTopDirectory, $directory, $this->siteType, $this->notices);
        // return if errors removing install directory and moving site failed/errors
        if(count($this->notices['errors'])) return;

        # GET, UNZIP AND MOVE WIRE FOLDER AND FILES TO SPECIFIED WEBROOT if single-site
        if($single) $this->notices = $this->msUtilities->moveWire($directory, $processWireVersion, $this->notices);        
        // ELSE
        # UPDATE/WRITE TO 'sites.json' if multi-site
        else $this->notices = $this->msUtilities->addToSitesJSON($hostAndDomainName, $siteDirectoryName, $this->notices);
        
        if(count($this->notices['errors'])) return; 

        # UPDATE/SAVE SUPERUSER ACCOUNT + ADMIN + THEME FOR NEW SITE        
        $adminAccountValues = array(
            'superUserName' => $superUserName,
            'superUserPassword' => $superUserPassword,
            'superUserEmail' => $superUserEmail,
            'adminLoginName' => $adminLoginName,
            // @note: watch this! If 'reno', in DB AdminThemeReno the key colors is left empty to default to main.css
            'colourTheme' => ('reno' == $colourTheme ? '' : $colourTheme),// @todo: ideally in a converter?
            'adminTheme' => $adminTheme,
            'siteDirectoryName' => $siteDirectoryName,
            'installDirectoryPath' => $installDirectoryPath,
            'hostAndDomainName' => $hostAndDomainName,
        );

        $this->notices = $this->msUtilities->adminAccountSave($adminAccountValues, $this->siteType, $this->notices);
        // return if errors saving admin account
        if(count($this->notices['errors'])) return;
                
        # CREATE A RECORD/PAGE OF THE INSTALL ('installed site')
        // pass sanitized values for saving to install page
        $installPageValues = array(
            'title' => $title,
            'description' => $description,
            'directory' => $directory,// @note: contextual for single vs. multi-site
            'hostAndDomainName' => $hostAndDomainName,
            'profileFile' => $profileFile['name'],
            'adminLoginName' => $adminLoginName,
            'colourTheme' => $colourTheme,
            'adminTheme' => $adminTheme,
            'timezone' => $timezone,
            'httpHostsNames' => implode(' ', $httpHostsNames['httpHosts']),
            'dbName' => $dbName,
            'dbUser' => $dbUser,
            'dbHost' => $dbHost,
            'superUserName' => $superUserName,
            'superUserEmail' => $superUserEmail,
            'chmodDir' => $directoriesPermissions,
            'chmodFile' => $filesPermissions,
            'siteType' => $this->siteType,
            'pwVersion' => $processWireVersion,// @note: the page ID!
        );

        $this->notices = $this->actionCreateInstalledSitePage($installPageValues, $this->notices);

        return; 

    }

    /**
     * Process and set values for installing a ProcessWire site using the full form.
     *
     * @access private
     * @param object $post Form with settings for site creation.
     * 
     */
    private function actionCreateForm($post) {

        $sanitizer = $this->wire('sanitizer');

        # 1. SITE
        // @todo? sanitize OK?
        // multi-site: install directory (string)
        $this->siteDirectoryName = $sanitizer->pageName($post->ms_site_directory);
        // single-site: install directory (path). use directory separator? @todo?
        #$this->installDirectoryPath = rtrim($sanitizer->text($post->ms_site_install_directory),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $this->installDirectoryPath = $sanitizer->text($post->ms_site_install_directory);
        // single site: processwire version
        $this->processWireVersion = (int) $post->ms_create_pw_version_select;        
        
        // @todo? sanitize URL?
        $this->hostAndDomainName = $sanitizer->text($post->ms_site_domain);
        $this->profile = (int) $post->ms_installation_profile;
        $this->adminLoginName = $sanitizer->pageName($post->ms_admin_url);// @note:doing other validations later
        // colour theme
        $this->colourThemeID = (int) $post->ms_colour_theme;
        // admin theme
        $this->adminThemeID = (int) $post->ms_admin_theme;

        $this->timezoneID = (int) $post->ms_timezone_id;
        // @note: value not required so not adding to checkempty
        // @note: not required
        $this->httpHostsNames = $sanitizer->purify($post->ms_http_host_names);

        # 2. DATABASE (5) @note: we sanitize these later in MultisitesUtilities::validateDatabaseConfigs
        // @todo...these sanitizations OK?
        $this->dbName = $sanitizer->text($post->ms_db_name);
        $this->dbUser = $sanitizer->text($post->ms_db_user);
        $this->dbPass = $post->ms_db_pass;
        $this->dbHost = $sanitizer->text($post->ms_db_host);
        $this->dbPort = (int) $post->ms_db_port;    
        $this->dbCharset = $sanitizer->text($post->ms_db_charset);// @note: not currently in use @todo?
        $this->dbEngine = $sanitizer->text($post->ms_db_engine);// @todo: -ditto-

        # 3. SUPERUSER (4)
        // @note: validation as well below
        $this->superUserName = $sanitizer->pageName($post->ms_superuser_name);
        $this->superUserPassword  = $post->ms_superuser_pass;
        $this->superUserPasswordConfirm = $post->ms_superuser_pass_confirm;
        $this->superUserEmail = $sanitizer->email($post->ms_superuser_email);

        #4. FILE PERMISSIONS (2)        
        $this->directoriesPermissions = (int) $post->ms_directories_permission;
        $this->filesPermissions = (int) $post->ms_files_permission;



        /*
            SITE
            -------

            pw version (single site)
            Install Directory (single site) OR Site Directory (multi site)
            Hostname and Domain
            Installation Profile
            Admin Login URL
            Admin Theme
            Colour Theme
            Default Time Zone
            HTTP Host Names

            DATABASE
            ---------
            DB Name
            DB User
            DB Password
            DB Host
            DB Port

            SUPERUSER
            ---------
            User Name
            Password
            Password (confirm)
            Email Address


            FILE PERMISSIONS
            ----------------
            Directories 
            Files
        
        */

    }

    /**
     * Process and set values for installing a ProcessWire site using type or paste method.
     *
     * @access private
     * @param object $post Form with settings for site creation.
     * 
     */
    private function actionCreateTypePaste($post) {

        // @note: here we use some 'user-friendly' indexes, e.g. 'user' rather than the normal 'superUserName' we use elsewhere
        
        /*         
            ## REQUIRED (INDIVIDUAL) VALUES FROM POST ##

            # @FROM $post

            SITE
            -------
            Site Type
            title
            description

            pw version (single site)
            Install Directory (single site)
            Installation Profile // @note: moved from copy paste; easier to pick from select(?)
            HTTP Host Names

            ## ALLOWABLE COPY-PASTED VALUES ##

            @from $post->ms_create_copy_paste
            $typePasteConfigurations = $post->ms_create_copy_paste;

            SITE
            -------

            Site Directory (multi site)
            Hostname and Domain            
            Admin Login URL
            Admin Theme
            Colour Theme
            Default Time Zone
            

            DATABASE
            ---------
            DB Name
            DB User
            DB Password
            DB Host
            DB Port

            SUPERUSER
            ---------
            User Name
            Password
            Password (confirm)
            Email Address


            FILE PERMISSIONS
            ----------------
            Directories 
            Files
        
        */

        $sanitizer = $this->wire('sanitizer');
        $configs = array();

        $pageNameSanitize = array('site','admin','user');
        $textSanitize = array('profile','colour','theme','timezone','hostDomain','dbName','dbUser','dbHost','dbCharset','dbEngine');
        $intSanitize = array('dbPort', 'chmodDir', 'chmodFile');
        $emailSanitize = array('email');

        $rawConfigs = $post->ms_create_copy_paste;
        $defaultConfigs = $this->msUtilities->getDefaultInstallConfigs();

        if(1 == $this->siteType) unset($defaultConfigs['site']);

        // get combined key=value pairs first (comma-separated)
        $configurableValuesArray = explode(',', $rawConfigs);
        
        foreach($configurableValuesArray as $c) {

            $keyPair = explode('=', $c);
            $property = $keyPair[0];
            $value = $keyPair[1];

            // sanitize
            if(in_array($property, $intSanitize)) $value = (int) $value;
            elseif(in_array($property, $emailSanitize)) $value = $sanitizer->email($value);
            elseif(in_array($property, $textSanitize)) $value = $sanitizer->text($value);
            elseif(in_array($property, $pageNameSanitize)) $value = $sanitizer->pageName($value);

            if(isset($defaultConfigs[$property])) $this->$defaultConfigs[$property] = $value;
            $configs[$property] = $value;

        }        
        
        if(count($configs) < count($defaultConfigs)) {
            $this->notices['errors'][] = $this->_('Some required key=>value pairs are missing');
        }

        # @FROM POST

        // use directory separator? @todo?
        #$this->installDirectoryPath = rtrim($sanitizer->text($post->ms_site_install_directory),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $this->installDirectoryPath = $sanitizer->text($post->ms_site_install_directory);
        // single site: processwire version
        $this->processWireVersion = (int) $post->ms_create_pw_version_select;
        $this->profile = (int) $post->ms_installation_profile;
        $this->httpHostsNames = $sanitizer->purify($post->ms_http_host_names);

    }

    /**
     * Process and set values for installing a ProcessWire site using a saved install configuration method.
     *
     * @access private
     * @param object $post Form with settings for site creation.
     * 
     */
    private function actionCreateInstallConfig($post) {

        $sanitizer = $this->wire('sanitizer');
        $configID = (int) $post->ms_create_json_configs;
        $configs = $this->msUtilities->getSavedInstallConfiguration($configID);
        
        /*

            ## REQUIRED INPUTS IN POST ##

            SITE
            -------
            Site Type
            title
            description

            pw version (single site)
            Install Directory (single site) OR Site Directory (multi site)
            Hostname and Domain
            Admin Login URL
            HTTP Host Names

            DATABASE
            ---------
            DB Name
            DB Password

            SUPERUSER
            ---------
            Password
            Password (confirm)


            ## VALUES SAVED IN CONFIG ##

            SITE
            -------

            Installation Profile (ID)
            Admin Theme (ID)
            Colour Theme (ID)
            Default Time Zone (ID)

            DATABASE
            ---------
            DB User
            DB Host
            DB Port

            SUPERUSER
            ---------
            User Name
            Email Address

            FILE PERMISSIONS
            ----------------
            Directories 
            Files
        
        */

        // @todo: refactor! some duplication here vs. actioncreateform()             

        # 1. SITE

         # @FROM POST

        // @todo? sanitize OK?
        // multi-site: install directory (string)
        $this->siteDirectoryName = $sanitizer->pageName($post->ms_site_directory);
        // single-site: install directory (path). use directory separator? @todo?
        #$this->installDirectoryPath = rtrim($sanitizer->text($post->ms_site_install_directory),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $this->installDirectoryPath = $sanitizer->text($post->ms_site_install_directory);
        // single site: processwire version
        $this->processWireVersion = (int) $post->ms_create_pw_version_select;        
        
        // @todo? sanitize URL?
        $this->hostAndDomainName = $sanitizer->text($post->ms_site_domain);
        $this->adminLoginName = $sanitizer->pageName($post->ms_admin_url);// @note:doing other validations later

        // @note: not required
        $this->httpHostsNames = $sanitizer->purify($post->ms_http_host_names);

        # @FROM SAVED INSTALL CONFIG
        $this->profile = (int) $configs['profileFile'];
        // colour theme
        $this->colourThemeID = (int) $configs['colourTheme'];
        // admin theme
        $this->adminThemeID = (int) $configs['adminTheme'];
        $this->timezoneID = (int) $configs['timezone'];

         # 2. DATABASE (5) @note: we sanitize these later in MultisitesUtilities::validateDatabaseConfigs

        # @FROM POST
        // @todo...these sanitizations OK?
        $this->dbName = $sanitizer->text($post->ms_db_name);
        $this->dbPass = $post->ms_db_pass;
        
        # @FROM SAVED INSTALL CONFIG
        $this->dbUser = $sanitizer->text($configs['dbUser']);
        $this->dbHost = $sanitizer->text($configs['dbHost']);
        $this->dbPort = (int) $configs['dbPort'];
        // @todo?  
        $this->dbCharset = isset($configs['dbCharset']) ? $sanitizer->text($configs['dbCharset']) : '';// @note: not currently in use 
        $this->dbEngine = isset($configs['dbEngine']) ? $sanitizer->text($configs['dbEngine']) : '';// @todo: -ditto-
       
        # 3. SUPERUSER (4)
        # @FROM POST
        $this->superUserPassword  = $post->ms_superuser_pass;
        $this->superUserPasswordConfirm = $post->ms_superuser_pass_confirm;
        
        # @FROM SAVED INSTALL CONFIG
        $this->superUserName = $sanitizer->pageName($configs['superUserName']);
        $this->superUserEmail = $sanitizer->email($configs['superUserEmail']);

        #4. FILE PERMISSIONS (2)
        # @FROM SAVED INSTALL CONFIG   
        $this->directoriesPermissions = (int) $configs['chmodDir'];
        $this->filesPermissions = (int) $configs['chmodFile'];


    }

    /**
     * Creates a record of the newly installed site.
     *
     * @access private
     * @param Array $values Array with data for the page to create.
     * @param Array $notices Array for user feedback regarding action results.
     * 
     */
    private function  actionCreateInstalledSitePage($values, $notices) {

        // @note: values here already sanitized and validated

        $page = new Page();
        $page->template = 'multi-sites-installed-site';
        $page->parent = $this->wire('pages')->get('parent.name=multi-sites-installed-sites, template=multi-sites-installed-sites');
        $page->title = $values['title'];    

        // for settings, we unset 'title'
        unset($values['title']);
        $installedSiteSettingsJSON = wireEncodeJSON($values);
        $page->multi_sites_settings = $installedSiteSettingsJSON;

        ### save ###
        $page->save();

        if($page->id && $page->id > 0) $notice['messages'][] = $this->_('Successfully created site and a page record of the installation.');
        else $notice['errors'][] = $this->_('Unable to create page record of installed site.');
        // set notices back to class property
        $this->notices = $notices;
   
        return;

    }

    /**
	 * Bulk action site profiles.
     *
     * The method calls other methods for specific actions.
     *
	 *	@access private
	 *	@param object $post Form with items to process.
	 *
	 */
	private function actionProfiles($post) {
        
        $action = $this->wire('sanitizer')->fieldName($post->ms_items_action_select);
   
        // @note: array
        $items = $post->ms_items_action_selected;

        if('select' == $action) {
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select an action to apply.');
            return;
        }

        if(!count($items)) {// @note: we also do some client-side validations
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select profiles to action.');
            return;
        }

        // @note: available actions here -> lock; unlock, delete (trash?)
        if('lock' == $action) $this->actionLock($items, 1);
        elseif('unlock' == $action) $this->actionLock($items, 0);
        elseif('trash' == $action) $this->actionDelete($items, 1);// @note/@todo:? not in use currently
        elseif('delete' == $action) $this->actionDelete($items, 0);

    } 
    
	/**
	 * Upload and Save new or edited profiles.
	 *
     * Profiles are saved as pages.
     * Uploaded profile files must be in the format site-xxx.zip
	 *
	 *	@access private
	 *	@param object $post Form with items to process.
	 *
	 */
	private function actionUpload($post) {

       /*
            @note:
            - irrespective of $action, we require title and compatibility
            - if $action is 'upload' we require file
            - if $action is 'edit_profile' and they have sent a file, we require that file to be valid

        */
        
        $options = array();
        $settings = array();
        $noFileError = false;
        $path = '';
        $dir = $this->privateTempUploadsDir;
        $sanitizer = $this->wire('sanitizer');
        $action = $post->ms_edit_profile_btn ? 'edit_profile' : 'upload';

        if('edit_profile' == $action) $this->notices['redirect'] = $this->wire('page')->url . $this->wire('input')->urlSegmentsStr . '/';

        // process profile file
        $profileFile = isset($_FILES['ms_upload_profile_file']) && strlen($_FILES['ms_upload_profile_file']['name']) ? true : false;
        
        // check if file needed and if sent
        if('upload' == $action && !$profileFile) $noFileError = true;

        // if no profile file
        if($noFileError) {
            $this->notices['errors'][] = $this->_('Multi Sites: A profile file is required.');
            return;
        }

        $parent = $this->wire('pages')->get('parent.name=multi-sites,name=multi-sites-profiles, template=multi-sites-site-profiles');

        // check if editing vs creating a profile        
        $profilePageID = (int) $post->ms_edit_profile;
        if($profilePageID) {
            $page = $this->wire('pages')->get($profilePageID);
            if(!$page->id) {
                $this->notices['errors'][] = $this->_('Multi Sites: We could not find that profile.');
                return;
            }
            if($page->is(Page::statusLocked)) {                
                $this->notices['warnings'][] = $this->_('Multi Sites: Profile locked for edits.');
                return;
            }           
        }
        else {
            $page = new Page();
            $page->template = 'multi-sites-site-profile';
            $page->parent = $parent;
        }
       
        # title check
        $page->title = $sanitizer->text($post->ms_upload_profile_title);
        // if no title provided return error
        if(!$page->title) {
            $this->notices['errors'][] = $this->_('Multi Sites: A title is required.');
            return;
        }
        
        # profile name check
        // if a title was provided, we sanitize and convert it to a URL friendly page name
        if($page->title) $page->name = $sanitizer->pageName($page->title);
        $child = $parent->child( "name={$page->name}, id!={$page->id}, include=all" )->id;

        if($child) {
            // if name already in use return error
            $this->notices['errors'][] = $this->_('Multi Sites: A profile with that title already exists. Amend the title and try again.');
            return;
        }

        # compatibility not specified
        $profileCompatibility = (int) $post->ms_upload_profile_compatibility;
        if(!$profileCompatibility) {
            $this->notices['errors'][] = $this->_('Multi Sites: A ProcessWire compatibility must be specififed.');
            return;
        }
        
        // if a file was uploaded, process it
        if($profileFile) {

             // get new WireUpload to process files
            $uploadProfile = new WireUpload('ms_upload_profile_file'); // The name of upload item <input type='file'> in the <form>
            $uploadProfile->setOverwrite(true);
            $uploadProfile->setMaxFiles(1);
            $uploadProfile->setDestinationPath($dir);
            $uploadProfile->setValidExtensions(array('zip'));

            $files = $uploadProfile->execute();
            
            # make sure there are actually files; if so, proceed; if not, return error
            if(!count($files) && 'upload' == $action){
                $this->notices['errors'][] = $this->_('Multi Sites: There was an error uploading the profile file. Please try again.');
                return;
            }

            # mimetype validation            
            $options = array_merge($options, $this->commonImageExts, $this->allowedNonImageMimeTypes, $this->imageTypeConstants);

            // add processed file to options array to pass on @note: currently only accepting 1 zip file
            $options['files'] = $files[0];
            // this will check for mime types and confirm authenticity of image types
            $path = $dir . $files[0];
            $valid = $this->msUtilities->validateFile($path, $options);// returns an array

            if(!$valid['valid']) {
                unlink($path);// delete invalid file
                if('upload' == $action) {
                    $noFileError = true;// @todo: not really needed here?
                    $this->notices['errors'][] = $this->_('Multi Sites: There was an error validating the profile file. Please upload a valid file.');
                    return;
                }
            }

        }// END if $profileFile

        # good to go
        $settings['summary'] = $sanitizer->purify($post->ms_upload_profile_summary);
        $settings['compatibility'] = (int) $post->ms_upload_profile_compatibility;
        $page->multi_sites_settings = wireEncodeJSON($settings);

        $page->save();

        # add profile file
        if($profileFile && !$noFileError) {
            $page->multi_sites_files->deleteAll();
            $page->multi_sites_files->add($path);
            $page->save('multi_sites_files');
            unlink($path);// delete temporary file
        }
        
        $this->notices['messages'][] = $this->_('Multi Sites: Profile saved.');

        return;

    }

    /**
     * Bulk action site install configurations.
     *
     * @access private
     * @param object $post Form with items to process.
     * 
     */ 
    private function actionConfigs($post) {

        $action = $this->wire('sanitizer')->fieldName($post->ms_items_action_select);
        // @note: array
        $items = $post->ms_items_action_selected;

        if('select' == $action) {
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select an action to apply.');
            return;
        }

        if(!count($items)) {// @note: we also do some client-side validations
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select install configurations to action.');
            return;
        }

        // @note: available actions here -> lock; unlock, delete (trash?)
        if('lock' == $action) $this->actionLock($items, 1);
        elseif('unlock' == $action) $this->actionLock($items, 0);
        elseif('trash' == $action) $this->actionDelete($items, 1);// @note/@todo:? not in use currently
        elseif('delete' == $action) $this->actionDelete($items, 0);

    }

    /**
     * Add new or edit install configurations.
     *
     * @access private
     * @param object $post Form with items to process.
     * 
     */ 
    private function actionConfig($post) {

        /*
            @note:
            - irrespective of $action, all fields except description/summary are required
            - if $action can be either 'config' [creating/adding newe config] OR 'edit_config' 
        */

        # PREPARE VARIABLES.
        $action = $post->ms_edit_config_btn ? 'edit_config' : 'config';
        $sanitizer = $this->wire('sanitizer');
        $checkEmpty = array();
        
        $title = $checkEmpty['title'] = $sanitizer->text($post->ms_site_title);
        
        $description = $sanitizer->purify($post->ms_site_description);

        // set values to variables
        $this->actionCreateForm($post);

        $checkEmpty['profile'] = $this->profile;// profile ID     
        $checkEmpty['adminTheme'] = $this->adminThemeID;// admin theme ID        
        $checkEmpty['colourTheme'] = $this->colourThemeID;// colour theme ID       
        $checkEmpty['timezoneID'] = $this->timezoneID = (int) $post->ms_timezone_id; // timezone ID
        $checkEmpty['dbUser'] = $this->dbUser;// db User        
        $checkEmpty['dbHost'] = $this->dbHost;// db host        
        $checkEmpty['dbPort'] = $this->dbPort;// db port 
        $checkEmpty['superUserName'] = $this->superUserName;// superuser name
        $checkEmpty['superUserEmail'] = $this->superUserEmail;// superuser email        
        $checkEmpty['directoriesPermissions'] = $this->directoriesPermissions;// directories 
        $checkEmpty['filesPermissions'] = $this->filesPermissions;// files permission

        # CHECK EMPTIES
        $empties = array_filter($checkEmpty, function($var){return empty($var);} );
        
        if(count($empties)) {
            $error = $this->_('Multi Sites: Some required settings were not completed. These are') . ':<br> ' . implode("<br>",array_keys($empties));
            $this->notices['errors'][] = $error;
            return;
        }

        # EXTRA VALIDATIONS

        // database configs validations
        $databaseConfigs = array(
            'dbUser' => $this->dbUser,
            'dbHost' => $this->dbHost,
            'dbPort' => $this->dbPort,
            'dbEngine' => $this->dbEngine,// @todo: @see note above
            'dbCharset' => $this->dbCharset,// @todo: -ditto-
        );

        $databaseConfigs = $this->msUtilities->validateDatabaseConfigs($databaseConfigs);// @note: array

        // supersuer validations
        $this->notices = $this->msUtilities->validateSuperUserName($this->superUserName, $this->notices);
        $this->notices = $this->msUtilities->validateSuperUserEmail($this->superUserEmail, $this->notices);
        // file permissions validations
        $this->notices['directories_permission'] = 1;
        $this->notices['files_permission'] = 1;
        $permissionsArray = array('directories' => $this->directoriesPermissions, 'files' => $this->filesPermissions);
        foreach ($permissionsArray as $permission => $value) {
            $validPermission = $this->msUtilities->validateFilePermissions($value);
            if(!$validPermission) {
                $this->notices['errors'][] = sprintf(__('Value for %s permissions is invalid.'), $permission);
                $this->notices[$permission . '_permission'] = 0;
            }
            else $this->{"{$permission}Permissions"} = $value;
        }
        // return if validation errors found
        if(count($this->notices['errors'])) return;        

        if('edit_config' == $action) $this->notices['redirect'] = $this->wire('page')->url . $this->wire('input')->urlSegmentsStr . '/';
       
        $parent = $this->wire('pages')->get('parent.name=multi-sites,name=multi-sites-install-configurations, template=multi-sites-install-configurations');

        // check if editing vs creating a profile        
        $configPageID = (int) $post->ms_edit_config;
        
        if($configPageID) {
            $page = $this->wire('pages')->get($configPageID);
            if(!$page->id) {
                $this->notices['errors'][] = $this->_('Multi Sites: We could not find that configuration.');
                return;
            }
            if($page->is(Page::statusLocked)) {                
                $this->notices['warnings'][] = $this->_('Multi Sites: Install configuration locked for edits.');
                return;
            }           
        }
        else {
            $page = new Page();
            $page->template = 'multi-sites-install-configuration';
            $page->parent = $parent;
        }
       
        # title check
        $page->title = $sanitizer->text($post->ms_site_title);
        // if no title provided return error
        if(!$page->title) {
            $this->notices['errors'][] = $this->_('Multi Sites: A title is required.');
            return;
        }
        
        # install config name check
        // if a title was provided, we sanitize and convert it to a URL friendly page name
        if($page->title) $page->name = $sanitizer->pageName($page->title);
        $child = $parent->child( "name={$page->name}, id!={$page->id}, include=all" )->id;

        if($child) {
            // if name already in use return error
            $this->notices['errors'][] = $this->_('Multi Sites: An install configuration with that title already exists. Amend the title and try again.');
            return;
        } 
        
        # good to go        

        // pass sanitized values for saving to install page
        $installConfigValues = array(
            'summary' => $description,
            'profileFile' => $this->profile,
            'colourTheme' => $this->colourThemeID,
            'adminTheme' => $this->adminThemeID,
            'timezone' => $this->timezoneID,
            'dbUser' => $this->dbUser,
            'dbHost' => $this->dbHost,
            'dbHost' => $this->dbPort,
            'superUserName' => $this->superUserName,
            'superUserEmail' => $this->superUserEmail,
            'chmodDir' => $this->directoriesPermissions,
            'chmodFile' => $this->filesPermissions,
        );

        // merge values with validated db values for final array
        $installConfigValues = array_merge($installConfigValues, $databaseConfigs);
        
        // encode settings as JSON and save (to) page
        $page->multi_sites_settings = wireEncodeJSON($installConfigValues);
        $page->save();
                
        $this->notices['messages'][] = $this->_('Multi Sites: Install Configuration saved.');

        return;
        
    }

    /**
     * Bulk action ProcessWire versions list.
     *
     * @access private
     * @param object $post Form with items to process.
     * @param array $notices Array for user feedback regarding action results.
     * 
     */
    private function actionVersions($post) {

        $action = $this->wire('sanitizer')->fieldName($post->ms_items_action_select);

        // @note: array
        $items = $post->ms_items_action_selected;

        if('select' == $action) {
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select an action to apply.');
            return;
        }

        if(!count($items)) {// @note: we also do some client-side validations
            $this->notices['errors'][] = $this->_('Multi Sites: You need to select versions to action.');
            return;
        }

        // @note: available actions here -> lock; unlock, delete (trash?)
        if('lock' == $action) $this->actionLock($items, 1);
        elseif('unlock' == $action) $this->actionLock($items, 0);
        elseif('trash' == $action) $this->actionDelete($items, 1);// @note/@todo:? not in use currently
        elseif('delete' == $action) $this->actionDelete($items, 0);
        elseif('download' == $action) $this->actionDownload($post);

        return $notices;

    }
    
    /**
     * Download different versions of ProcessWire per request.
     *
     * @access private
     * @param object $post Form with items to process.
     * 
     */
    private function actionDownload($post) {
        
        $items = $post->ms_items_action_selected;
        $versionIndexes = $post->ms_processwire_version_index;
        $sanitizer = $this->wire('sanitizer');
        $parent = $this->wire('pages')->get('parent.name=multi-sites,name=multi-sites-processwire-files, template=multi-sites-wires');
        
        $actionStr = $this->_('downloaded'); 

        if(count($items)) {

            $i = 0;// count for success actions
            $j = 0;// count for failed actions

            foreach ($versionIndexes as $key => $index) {
                $versionIndex = (int) $index;
                $id = $items[(int)$key];

                # check if refreshing version vs. creating new                
                
                // editing version page
                if($id) {
                    $page = $this->wire('pages')->get($id);                    
                    if($page->id && $page->id > 0) {
                        if($page->is(Page::statusLocked)) {                
                            $this->notices['warnings'][] = $this->_('Multi Sites: Version page locked for edits.');
                            $j++;
                            continue;
                        }
                    }
                    // could not find the specified version page
                    else {
                        $this->notices['errors'][] = sprintf(__('Multi Sites: Could not find a version page with ID: %s.'), $id);
                        $j++;
                        continue;
                    }                              
                }
                // creating new version page
                else {
                    $page = new Page();
                    $page->template = 'multi-sites-wire';
                    $page->parent = $parent;
                }
                
                # title check
                $version = $this->msUtilities->getProcessWireVersionsInfo($versionIndex);
                $page->title = $version['title'];
                
                # version name check
                // if a title was provided, we sanitize and convert it to a URL friendly page name
                if($page->title) $page->name = $sanitizer->pageName($page->title);
                $child = $parent->child( "name={$page->name}, id!={$page->id}, include=all" )->id;
        
                if($child) {
                    // if name already in use return error @note: in such a case, user must have physically created the page
                    $this->notices['errors'][] = $this->_('Multi Sites: A version page with that title already exists. Please delete that page and try again.');
                    $j++;
                    continue;
                }

                set_time_limit(60);// try not to timeout
                // download processwire version
                $this->notices = $this->msUtilities->downloadProcessWireVersion($versionIndex, $this->notices);
                // if could not download processwire file or downloaded but could not compress for saving to page as file
                if(0 == $this->notices['download'] || 0 == $this->notices['wire_zip']) {
                    $j++;// @see delete directory
                    continue;
                }

                // the name given to the downloaded proceswire zip file
                $zipFileName = $version['zip'];
                // full path to the downloaded and processed processwire zip file (in temp directory)
                $zipFile = $this->privateTempUploadsDir . $zipFileName;

                // save
                $page->save();
                // add processwire wire zip file and save again
                $page->multi_sites_files->deleteAll();
                $page->multi_sites_files->add($zipFile);
                $page->save('multi_sites_files'); 
        
                // remove the temp pw zip file
                unlink($zipFile);

                $i++;

            }// end foreach

            /* prepare responses */
            if($i > 0) {
                // success       
                $this->notices['messages'][] = sprintf(_n('Multi Sites: %1$d item %2$s.', 'Multi Sites: %1$d items %2$s.', $i, $actionStr), $i, $actionStr);
                // warnings
                if($j) $this->notices['warnings'][] =  sprintf(_n('%1$d item could not be %2$s', '%1$d items could not be %2$s', $j, $actionStr), $j, $actionStr);
            }

            // if we could not download/save any items
            else {
                $this->notices['errors'][] = sprintf(__('Multi Sites: Selected items could not be %s.'), $actionStr);
            }

        }// end if count($items)

        return;

    }
     
	/**
     * Lock/Unlock items.
     *
     * These could be uploaded profiles pages or installed sites pages.
	 *
	 * @access private
	 * @param array $items Selected multi sites page items to unlock/lock..
	 * @param int $action Whether to lock or unlock. 0=unlock; 1=lock.
	 *
	 */
    private function actionLock($items, $action) {
        
        $pages = $this->wire('pages');
        $actionStr = $action ? $this->_('locked') : $this->_('unlocked');        

        if(count($items)) {

            $i = 0;// count for success actions
            $j = 0;// count for failed actions

            foreach ($items as $id) {
                $p = $pages->get((int) $id);
                if(!$p->id) continue;

                // unlock item
                if($action == 0) {
                    $p->removeStatus(Page::statusLocked);
                    $p->save();
                    // confirm successfully unlocked
                    if (!$p->is(Page::statusLocked)) $i++;
                    else $j++;
                }

                // lock item
                elseif($action == 1) {
                    $p->addStatus(Page::statusLocked);
                    $p->save();
                    // confirm successfully locked
                    if ($p->is(Page::statusLocked)) $i++;
                    else $j++;
                }

                else $j++;

            }// end foreach

            /* prepare responses */
            if($i > 0) {
                // success       
                $this->notices['messages'][] = sprintf(_n('Multi Sites: %1$d item %2$s.', 'Multi Sites: %1$d items %2$s.', $i, $actionStr), $i, $actionStr);
                // warnings
                if($j) $this->notices['warnings'][] =  sprintf(_n('%1$d item could not be %2$s', '%1$d items could not be %2$s', $j, $actionStr), $j, $actionStr);
            }

            // if we could not (un)lock any item
            else {
                $this->notices['errors'][] = sprintf(__('Multi Sites: Selected items could not be %s.'), $actionStr);
            }

        }// end if count($items)

        return;

    }

    /**
     * Trash/Delete unlocked items.
     *
     * These could be uploaded profiles pages or installed sites pages.
	 *
	 * @access private
	 * @param array $items Selected multi sites items to trash/delete.
	 * @param int $action Whether to trash or delete. 0=delete; 1=trash.
	 *
	 */
	private function actionDelete($items, $action) {        

        $pages = $this->wire('pages');
        $actionStr = $action ? $this->_('trashed') : $this->_('deleted');
        
        if(count($items)) {

            $i = 0;// count for success actions
            $j = 0;// count for failed actions

            foreach ($items as $id) {				
                $p = $pages->get((int) $id);
                if(!$p->id) continue;

                // if page locked for edits
                if($p->is(Page::statusLocked)) {
                    $j++;
                    continue;
                }

                // delete multi site item page record as well
                if($action == 0) {
                    // delete page record as well
                    $p->delete();// delete the page
                    $deletedPage = $pages->get((int) $id);
                    if(!$deletedPage->id)$i++;
                    else $j++;
                }

                // trash item
                elseif($action == 1) {
                    $pages->trash($p);// trash the page
                    if ($p->is(Page::statusTrash)) $i++;// confirm trashed;
                    else $j++;// found page but for some reason failed to trash
                }

                else $j++;

            }// end foreach

            /* prepare responses */
            if($i > 0) {
                // success       
                $this->notices['messages'][] = sprintf(_n('Multi Sites: %1$d item %2$s.', 'Multi Sites: %1$d items %2$s.', $i, $actionStr), $i, $actionStr);
                // warnings
                if($j) $this->notices['warnings'][] =  sprintf(_n('%1$d item could not be %2$s. Item is locked for edits.', '%1$d items could not be %2$s. Items are locked for edits.', $j, $actionStr), $j, $actionStr);
            }

            // if we could not delete files and/or pages
            else {
                $this->notices['errors'][] = sprintf(__('Multi Sites: Selected items could not be %s. Items locked for edits.'), $actionStr);
            }

        }// end if count($items)

        return;

    }

    /**
     * Delete the site directory(ies) for selected installed sites.
     *
     * Single sites: This is the whole document root folder where PW is installed.
     * Multi Sites: These are the 'site' directories in ProcessWire root.
     * They are named in the format 'site-xxx' as per ProcessWire's multisite strategy.
     * Also deletes the site's page record if specified.
     *
     * @access private
     * @param array $items Seleted multi sites items whose directories to delete.
     * @param bool $wait If to delete installed site's page. Useful if deleting site's directory and database.
     *
     */
    private function actionDeleteDirectory($items, $wait=false) {
       
        $pages = $this->wire('pages');
                
        if(count($items)) {

            $i = 0;// count for success actions
            $j = 0;// count for failed actions

            foreach ($items as $id) {				
                $p = $pages->get((int) $id);
                if(!$p->id) continue;

                // if page locked for edits
                if($p->is(Page::statusLocked)) {
                    $j++;
                    continue;
                }

                // get installed site settings.
                $installedSiteSettings = json_decode($p->multi_sites_settings, true);

                $siteDirectoryName = isset($installedSiteSettings['directory']) ? $installedSiteSettings['directory'] : '';
                if(!$siteDirectoryName) {
                    $j++;
                    continue;
                }

                // continue if we don't know site type, otherwise we risk deleting wrong directory!
                $siteType = isset($installedSiteSettings['siteType']) ? $installedSiteSettings['siteType'] : '';
                if(!$siteType) {
                    $j++;
                    continue;
                }

                // single-site vs multi-site install path
                $sitePath = 1 == $siteType ?  $installedSiteSettings['directory'] : $this->msUtilities->getSitePath($siteDirectoryName);

                // delete installed site directory
                if(is_dir($sitePath)) {
                    $this->notices = $this->msUtilities->removeDirectory($sitePath, $this->notices);
                    if(!is_dir($sitePath)) $i++;
                    else $j++;
                }
                else {
                    $j++;
                    continue;
                }

                if(isset($installedSiteSettings['directory'])) {
                    $removedSite = 1 == $siteType ? $sitePath : 'site-' . $siteDirectoryName;
                    $this->removedSites[$p->id] = $removedSite;
                }

                // delete item's page record now if not waiting for database to be deleted (and subsequently the page record)
                if(!$wait) {
                    // delete installed site page record as well
                    $p->delete();// delete the page
                    // confirm page record deleted
                    $deletedPage = $pages->get((int) $id);
                    if(!$deletedPage->id) {
                        $this->notices['messages'][] = sprintf(__('Deleted page record for the site %s.'), $installedSiteSettings['hostAndDomainName']);
                    }
                    else {
                        $this->notices['errors'][] = sprintf(__('Could not delete page record for the site %s.'), $installedSiteSettings['hostAndDomainName']);
                    }
                }// END if(!$wait)


            }// END foreach

            /* prepare responses */
            if($i > 0) {
                // success       
                $this->notices['messages'][] = sprintf(_n('Multi Sites: Deleted %d directory.', 'Multi Sites: Deleted %d directories.', $i), $i);
                // warnings
                if($j) $this->notices['warnings'][] =  sprintf(_n('%d directory could not be deleted. Item is locked for edits.', '%d directories could not be deleted. Items are locked for edits.', $j), $j);
            }
            // if we could not delete any directory
            else {
                $this->notices['errors'][] = $this->_("Multi Sites: Selected items' directories could not be deleted. They are locked for edits.");
            }            

        }// end if count($items)
 
        return;

    }

    /**
     * Delete the site database for selected installed sites.
     *
     * Also deletes the site's page record.
     *
     * @access private
     * @param array $items Selected multi sites items whose databases to delete.
     *
     */
    private function actionDeleteDatabase($items) {
        
        
        $pages = $this->wire('pages');
        
        if(count($items)) {

            $i = 0;// count for success actions
            $j = 0;// count for failed actions

            foreach ($items as $id) {				
                $p = $pages->get((int) $id);
                if(!$p->id) continue;

                // if page locked for edits
                if($p->is(Page::statusLocked)) {
                    $j++;
                    continue;
                }

                // get installed site settings.
                $installedSiteSettings = json_decode($p->multi_sites_settings, true);

                $siteDatabaseName = isset($installedSiteSettings['dbName']) ? $installedSiteSettings['dbName'] : '';
                if(!$siteDatabaseName) {
                    $j++;
                    continue;
                }

                // delete installed site database
                $this->notices = $this->msUtilities->dropSiteDatabase($siteDatabaseName, $this->notices);
                // confirm drop
                if(isset($this->notices[$siteDatabaseName])) $i++;
                else $j++;// could not drop database

                if(isset($installedSiteSettings['directory'])) $this->removedSites[$p->id] = 'site-' . $installedSiteSettings['directory'];

                // delete installed site page record as well
                $p->delete();// delete the page
                 // confirm page record deleted
                 $deletedPage = $pages->get((int) $id);
                if(!$deletedPage->id) {
                     $this->notices['messages'][] = sprintf(__('Deleted page record for the site %s.'), $installedSiteSettings['hostAndDomainName']);
                }
                else {
                    $this->notices['errors'][] = sprintf(__('Could not delete page record for the site %s.'), $installedSiteSettings['hostAndDomainName']);
                }
                

            }// end foreach

            /* prepare responses */
            if($i > 0) {
                // success       
                $this->notices['messages'][] = sprintf(_n('Multi Sites: Deleted %d database.', 'Multi Sites: Deleted %d databases.', $i), $i);
                // warnings
                if($j) $this->notices['warnings'][] =  sprintf(_n('%d database could not be deleted.', '%d databases could not be deleted.', $j), $j);
            }
            // if we could not delete anything
            else {
                $this->notices['errors'][] = $this->_("Multi Sites: Selected items' databases could not be deleted. They are locked for edits.");
            }

        }// end if count($items)

        return;

    }
        
    /**
     * Calls a class to remove this module's components before uninstall.
     *
     * Components: pages, fields, templates and files.
     *
     * @access private
     * @param object $post Form with cleanup instructions.
     *
     */
    private function actionCleanup($post) {
        require_once(dirname(__FILE__) . '/MultiSitesCleanup.php');
        $this->cleanup = new MultiSitesCleanup();
        $this->cleanup->cleanUp($post);
    }

     
}