<?php

/**
* Sites Manager: Render
*
* This file forms part of the Sites Manager Suite.
* Renders markup for output in various places in the module.
*
* @author Francis Otieno (Kongondo)
* @version 0.0.3
*
* This is a Free Module.
*
* ProcessSitesManager for ProcessWire
* Copyright (C) 2017 by Francis Otieno
* This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
*
*/

class SitesManagerRender extends ProcessSitesManager {

	public function __construct() {
		parent::__construct();
		$this->smUtilities = new SitesManagerUtilities();
		$this->smActions = new SitesManagerActions();

		// get sanitised url segments
		$urlSegments = $this->smUtilities->getURLSegments();
		$this->urlSeg1 = $urlSegments[0];
		$this->urlSeg2 = $urlSegments[1];
		#$this->urlSeg3 = $urlSegments[2];
	}

	/**
	 * Render list of installed sites and their action form.
	 *
	 * @access protected
	 * @return string $out Markup of rendered installed sites' form.
	 * 
	 */
	protected function renderSitesInstalled() {

		// determine number of installed sites to show per page in dashboard. Default = 10 {see parent::showLimit}
		$this->setItemsShowLimit();

		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_installed_btn) {
			$actionType = 'installed';
			$this->smActions->actionItems($actionType, $post);
		}

		## prepare variables
		$out = '';
		$clientSideConfirmOptions = array('', 'hidden', 'sm_confirm', '', 0);
		$button = '';
		$selector = "template=sites-manager-installed-site,parent.name=sites-manager-installed-sites,limit={$this->showLimit}";
		$items = $this->smUtilities->getItems($selector);

		$actions = array(
			'select' => $this->_('Actions (checked items)'),// @note: this is set as the initial value
			'lock' => $this->_('Lock (no edit)'),
			'unlock' => $this->_('Unlock'),
			'delete_directory' => $this->_('Delete Site Directory'),
			'delete_database' => $this->_('Delete Site Database'),
			'delete_all' => $this->_('Delete Site Directory and Database'),
		);

		$selectOptions = array('','select', 'sm_itesm_action_select', '', $actions);

		$buttonOptions = array(
			'idName' => 'sm_installed_btn',
			'classes' => 'sm_bulk_action_btn',
			'value' => $this->_('Execute')
		);

		$url = $this->wire('page')->url . 'create/';
		$createSiteLink = '<a href="'.$url.'">' . $this->_('create') . '</a>';
		$noInstalledSitesNotice = sprintf(__('No Installed Sites found. You will need to %s a site for it to be listed here.'), $createSiteLink);

		## populate $out
		if(!$items->count) $out .= '<p>' . $noInstalledSitesNotice . '</p>';
		else {
			$out .= $this->renderHeadlineStrip($items) .
					$this->renderTopActionStrip($items, $selectOptions) .			
					$this->renderInstalledSitesList($items) .
					'<div id="sm_bottom_pagination">' . $this->renderPagination($items) . '</div>';
			$button = $this->renderInputButton($buttonOptions);
		}

		$out = $this->renderMenu() .
				'<div id="sm_installed_sites_wrapper" class="InputfieldContent sm_form_wrapper">' .
					'<form method="post" action="./" id="sm_installed_sites_form">' .
						$out . 
						$this->renderInputHidden($clientSideConfirmOptions) .
						$button .						
						$this->renderToken() .// CSRF
					'</form>'.
					$this->renderPopupInstalledSites() .
				'</div>';

		return $out;
	}

	/**
	 * Build and render a listing of installed sites.
	 *
	 * @access private
	 * @param PageArray $installedSites PageArray of installed sites.
	 * @return string $out Markup of list of installed sites.
	 * 
	 */
	private function renderInstalledSitesList($installedSites) {
		
		$out = '';
		$prefix = 'sm_itesm_action_selected';
		
		foreach ($installedSites as $s) {
			$ss = $this->smUtilities->getSettingsObject($s->sites_manager_settings);

			$directory = 'site-' . $ss->directory;// @note: directory of the install
			$protocol = 1 == $ss->domainProtocol ? 'http://' : 'https://';
			$siteURL = ' <a href="'. $protocol . $ss->hostAndDomainName.'" target="_blank">'.   $ss->hostAndDomainName . '</a>';
			$siteAdminURL = ' <a href="'. $protocol .$ss->hostAndDomainName. '/' . $ss->adminLoginName .'/" target="_blank">'.   $ss->adminLoginName . '</a>';
			$siteType = $this->_('Multi Site');
			$versionTitle = '';
			
			// single site
			if(1 == $ss->siteType) {
				$directory = $ss->directory;// @note: path to directory of the install
				$siteType = $this->_('Single Site');
				$pwVersionID = (int) $ss->pwVersion;
				$pwPage = $this->wire('pages')->get($pwVersionID);
				if($pwPage && $pwPage->id > 0) {
					$versionTitle = '<span>'. sprintf(__('ProcessWire Version: %s'), $pwPage->title) . '</span>';
				}
			}

			$out .= '<div class="sm_installed_sites" data-directory="'.$directory.'" data-database="'.$ss->dbName.'" data-site-type="'.$ss->siteType.'">' . 
						$this->renderInputCheckbox(array('','checkbox',$prefix.'[]','',$s->id,'mode'=>'multiple','id'=>$prefix.'_'.$s->id)) .
						'<h2>'. $s->title.'</h2>' .
						$this->renderLockedForEdits($s) .
						'<p>' . $ss->description . '</p>' .					
						'<span>' . $this->_('Domain:') . $siteURL.'</span>' .
						'<span>' . $this->_('Admin Login:') . $siteAdminURL.'</span>' .
						'<span>'. sprintf(__('Admin Theme: %s'), $ss->adminTheme) . '</span>' .
						'<span>'. sprintf(__('Colour Theme: %s'), ucfirst($ss->colourTheme)) . '</span>' .
						'<span>'. sprintf(__('Site Directory: %s'), $directory) . '</span>' .
						'<span>'. sprintf(__('Profile: %s'), $ss->profileFile) . '</span>' .					
						'<span>'. sprintf(__('Time Zone: %s.'), $ss->timezone) . '</span>' .
						'<span>'. sprintf(__('Host Names: %s'), $ss->httpHostsNames) . '</span>' .
						'<span>'. sprintf(__('Database Name: %s'), $ss->dbName) . '</span>' .
						//'<span>'. sprintf(__('Database User: %s'), $ss->dbUser) . '</span>' .// @todo?
						//'<span>'. sprintf(__('Database Host: %s'), $ss->dbHost) . '</span>' .// -DITTO-
						//'<span>'. sprintf(__('Superuser Name: %s'), $ss->superUserName) . '</span>' .// @todo?
						//'<span>'. sprintf(__('Superuser Email: %s'), $ss->superUserEmail) . '</span>' .// -DITTO-
						'<span>'. sprintf(__('Directory Permissions: %s'), $ss->chmodDir) . '</span>' .
						'<span>'. sprintf(__('File Permissions: %s'), $ss->chmodFile) . '</span>' .
						'<span>'. sprintf(__('Created: %s'), date('d F Y', $s->created)) . '</span>' .
						'<span>'. sprintf(__('Site Type: %s'), $siteType) . '</span>' .
						$versionTitle .		
				'</div>';
			
			
		}// end foreach $installedSites
		return $out;


	}
	
	/**
	 * Render the create and execute new site form.
	 *
	 * Used to install new multi-site.
	 * 
	 * @access protected
	 * @return string $out Markup of create/install site form.
	 * 
	 */
	protected function renderSitesCreate() {
	
		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_create_btn || $post->sm_create_and_view_btn) {
			$actionType = 'create';
			$this->smActions->actionItems($actionType, $post);
		}
		
		#++++++++++++++++++++++++++++++++++++++#
		
		$this->notices = $this->smUtilities->compatibilityCheck();// returns array		

		## prepare variables
		$out = '';
		$errors = '';
		$timeZoneHiddenOptions = array('', 'hidden', 'sm_timezone_id', '');
		$clientSideValidationOptions = array('', 'hidden', 'sm_confirm', '', 0);
		$buttonOptions = array(
						'idName' => 'sm_create_btn',
						'classes' => 'sm_create_execute_save',
						'value' => $this->_('Save')
					);

		$buttonSaveAndViewOptions = array(
			'idName' => 'sm_create_and_view_btn',
			'classes' => 'ui-priority-secondary',
			'value' => $this->_('Save & View Sites'),
			'clone' => 0
		);

		$url = $this->wire('page')->url . 'upload/';
		$uploadProfileLink = '<a href="'.$url.'">' . $this->_('Upload') . '</a>';
		$profilesMissingNotice = sprintf(__('Installation site profiles are missing. %s at least one to get started.'), $uploadProfileLink);

		## populate $out
		// render back links		
		$out .= $this->renderBackLink();

		## checks
		// @todo? revert to original?
		if(count($this->notices['errors'])){
			/* $errors .= '<p>' .$this->_('One or more errors were found during the compatibility check. We recommend you correct these issues before proceeding or');	
			$errors .= ' <a href="https://processwire.com/talk/">' . $this->_('contact ProcessWire support') . '</a> ';			
			$errors .= $this->_('if you have questions or think the error is incorrect. But if you want to proceed anyway, click Continue below.') . '</p>'; */

			$out .= '<p>' .$this->_('One or more errors were found during the compatibility check. These have to be fixed before you can use this module.');			
			$out .= $this->_('if you have questions or think the error is incorrect please'); 
			$out .= ' <a href="https://processwire.com/talk/" target="_blank">' . $this->_('contact ProcessWire support') . '</a>.</p>';	
			// run error messages
			$this->smUtilities->runNotices($this->notices['errors'], 2);			
			return $out;
		}

		else {
			// run warnings
			if(count($this->notices['warnings'])) $this->smUtilities->runNotices($this->notices['warnings'], 3);
			// run sucess messages
			$this->smUtilities->runNotices($this->notices['messages'], 1);
		}

		// create form
		$out .= '<div id="sm_create_wrapper" class="InputfieldContent sm_form_wrapper">';
		// profiles exist check
		$this->notices = $this->smUtilities->profilesAvailableCheck($this->notices);// returns array
		if(count($this->notices['errors']))$out .= $profilesMissingNotice;
		// if we have profiles, attach sites create form
		else {
			$out .= 
				'<form method="post" action="./" id="sm_create_form">' .
					// create site: form
					$this->renderCreateForm() .
					$this->renderInputHidden($timeZoneHiddenOptions) .
					// other form elements
					$this->renderInputHidden($clientSideValidationOptions) .
					$this->renderInputButton($buttonOptions) .
					$this->renderInputButton($buttonSaveAndViewOptions) .
					$this->renderToken() .// CSRF
				'</form>'.
				$this->renderTimeZonesScript() .
				$this->renderPopupCreateSite();
		}
		
		$out .= '</div>';

		return $out;

	}
	
	/**
	 * Render form inputs for use in create/install sites form.
	 *
	 * @access private
	 * @return String $out Markup of form inputs.
	 */
	private function renderCreateForm() {

		$siteCreateNotice =
		'<div id="sm_notice">' .
			'<p>' . $this->_("Use this form to create sites. Except for 'Description' and 'HTTP Host Names', all fields must be completed.") . '</p>' .
			'<p>' . $this->_("Please note that the wire folder is not needed. Please remove it from your site profile if present.") . '</p>' .
		'</div>';
		
		$out =	'<div id="sm_create_form_inputs_wrapper">' .
					$siteCreateNotice .			
					$this->renderSpinner() .					
					$this->renderCreateFormSections($this->smUtilities->getSiteOptions('site')) .
					$this->renderCreateFormSections($this->smUtilities->getSiteOptions('database')) .
					$this->renderCreateFormSections($this->smUtilities->getSiteOptions('superuser')) .
					$this->renderCreateFormSections($this->smUtilities->getSiteOptions('file_permissions')) .
				'</div>';

		return $out;
		
	}

	/**
	 * Render a specified section of the create form.
	 *
	 * @access private
	 * @param array $options Options to build the form.
	 * @return string $out Markup of the form section.
	 * 
	 */
	private function renderCreateFormSections($options) {
		
		$out = '';

		$class = ' sm_setting_header';
		$sectionIDs = array('site', 'database', 'superuser', 'file_permissions');
		$hideInputsArray = array('sm_create_pw_version_select', 'sm_site_install_directory', 'sm_create_copy_paste', 'sm_create_json_configs');
		$configurableInputsArray = $this->smUtilities->getInstallJSONConfigurableValues();

		foreach($options as $key => $value) {		
			
			$class = '';
			$cssClass = 'sm_setting_header';
					
			if(in_array($key, $sectionIDs)) {
				$notes = isset($value[3]) ? $value[3] : '';
				$out .=	'<div id="sm_'.$key.'_section" class="sm_section">' .
							'<h2>' . $value[0] .'</h2>'.
							'<p class="sm_section_notes">' . $notes.'</p>' .// section heading
						'</div>';						
			}
			else {
				
				$headerID = $value[2] . '_wrapper';
				$cssClass .= in_array($value[2], $hideInputsArray) ? ' sm_hide' : '';
				$cssClass .= in_array($value[2], $configurableInputsArray) ? ' sm_configurable' : '';

				$class = ' class="'.$cssClass.'"';				
				
				$out .=	'<div id="'.$headerID.'"' . $class .'>' .			
							'<div class="sm_setting">' . $this->getInput($value) . '</div>' .// label+input
							'<div class="sm_setting"><p class="sm_setting_notes">'  . $value[3] . '</p></div>' .// notes
						'</div>';
			}
		}

		return $out;


	}
	
	/**
	 * Render the sites' profiles list and their action form.
	 *
	 * @access protected
	 * @return string $out Markup of profiles list.
	 * 
	 */
	protected function renderSitesProfiles() {

		// determine number of site profiles to show per page in dashboard. Default = 10 {see parent::showLimit}
		$this->setItemsShowLimit();

		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_profiles_btn) {
			$actionType = 'profiles';
			$this->smActions->actionItems($actionType, $post);
		}
		
		## prepare variables
		$out = '';
		$errors = '';
		$popupTitle = $this->_('Action Profile');
		$button = '';
		$buttonOptions = array(
			'idName' => 'sm_profiles_btn',
			'classes' => 'sm_bulk_action_btn',
			'value' => $this->_('Execute')
		);

		$selector = "template=sites-manager-site-profile,parent.name=sites-manager-profiles,limit={$this->showLimit},sites_manager_files!=''";
		$items = $this->smUtilities->getItems($selector);

		$actions = array(
			'select' => $this->_('Actions (checked items)'),// @note: this is set as the initial value
			'lock' => $this->_('Lock (no edit)'),
			'unlock' => $this->_('Unlock'),
			//'trash' => $this->_('Trash Profile'),// @todo: include this?
			'delete' => $this->_('Delete Profile'),
		);

		$selectOptions = array('','select', 'sm_itesm_action_select', '', $actions);
		
		## populate $out
		if(!$items->count) {
			$url = $this->wire('page')->url . 'upload/';
			$uploadLink = '<a href="'.$url.'">' . $this->_('upload') . '</a>';
			$noProfilesNotice = sprintf(__('No Site Profiles found! You will need to %s at least one in order to use this module.'), $uploadLink);
			$errors .= '<p>' . $noProfilesNotice . '</p>';
		}

		else {
			$out .= $this->renderHeadlineStrip($items) .
					$this->renderTopActionStrip($items, $selectOptions) .
					$this->renderSitesProfilesList($items) .			
					'<div id="sm_bottom_pagination">' . $this->renderPagination($items) . '</div>';
			$button = $this->renderInputButton($buttonOptions);
		}

		$out = $this->renderMenu() .
			'<div id="sm_profiles_wrapper" class="InputfieldContent sm_form_wrapper">' .
				$errors .
				'<form method="post" action="./" id="sm_profiles_form">' .
					$out . 
					$button .
					$this->renderToken() .// CSRF
				'</form>'.
				$this->renderPopupNoItemsSelected($popupTitle) .
				$this->renderPopupNoActionSelected($popupTitle) .
			'</div>';

		return $out;

	}

	/**
	 * Build and render a listing of uploaded site profiles.
	 *
	 * @access private
	 * @param PageArray $siteProfiles PageArray of site profiles.
	 * @return string $out Markup of list of site profiles.
	 * 
	 */
	private function renderSitesProfilesList($siteProfiles) {

		## prepare variables
		$out = '';
		$prefix = 'sm_itesm_action_selected';
		$noProfileSummary = $this->_('This profile has no summary');		
		
		// profile rows
		foreach ($siteProfiles as $p) {
			$file = $p->sites_manager_files->first();
			$sp = $this->smUtilities->getSettingsObject($p->sites_manager_settings);
			$compatibility = $this->smUtilities->compatibilityConvert($sp->compatibility);

			if($p->is(Page::statusLocked)) $editText = $this->renderLockedForEdits($p);
			else {
				$editURLText = $this->_('edit');
				$url = $this->wire('page')->url . 'upload/' . $p->id . '/?modal=1';
				$editURL = 	'<a href="'.$url.'" class="sm_edit_profile pw-modal-medium pw-modal">' . $editURLText .	'</a>';
				$editText = ' <small class="sm_edit">('. $editURL .')</small>';
			}			

			$out .= '<div class="sm_profiles">' . 
					$this->renderInputCheckbox(array('','checkbox',$prefix.'[]','',$p->id,'mode'=>'multiple','id'=>$prefix.'_'.$p->id)) .
					'<h2>'. $p->title.'</h2>' .
					$editText .
					'<p class="sm_profile_summary">' . (strlen($sp->summary) ? $sp->summary : $noProfileSummary) . '</p>' .
					'<span>'. sprintf(__('File: %1$s (%2$s)'), $file->basename, $file->filesizeStr) . '</span>' .
					'<span>'. sprintf(__('Compatibility: %s'), $compatibility) . '</span>' .
					'<span>'. sprintf(__('Modified: %s'), date('d F Y', $p->modified)) . '</span>' .
				'</div>';
		}

		return $out;

	}
			
	/**
	 * Render the sites' profiles upload form.
	 *
	 * @access protected
	 * @return string $out Markup of upload form.
	 * 
	 */
	protected function renderSitesProfilesUpload() {

		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_upload_btn || $post->sm_edit_profile_btn || $post->sm_upload_and_view_btn) {
			$actionType = $post->sm_edit_profile_btn ? 'edit_profile' : 'upload';
			$this->smActions->actionItems($actionType, $post);
		}

		## prepare variables
		$out = '';	
		$profileTitle = '';
		$profileSummary = '';
		$profileCompatibility = '';
		$profileFile = '';
		$extraProfileFileText = '';
		$compatibility = '';
		$editingProfile = false;
		$clientSideValidationOptions = array('', 'hidden', 'sm_confirm', '', 0);

		# check if editing
		$editProfileID = (int) $this->urlSeg2;
		if($editProfileID){
			
			$editProfile = $this->wire('pages')->get($editProfileID);

			if($editProfile->id && $editProfile->id > 0) {
				$editingProfile = true;
				$profileTitle = $editProfile->title;

				$out .= '<h2 class="sm_edit">'.$this->_('Edit Profile').': ' . $profileTitle . '</h2>';

				$file = $editProfile->sites_manager_files->first();
				$sp = $this->smUtilities->getSettingsObject($editProfile->sites_manager_settings);
				$compatibility = $sp->compatibility;
				$profileSummary = $sp->summary;
				$profileCompatibility = $sp->compatibility;
				$profileFile = $file->basename;
				$extraProfileFileText = ' ' . $this->_('Current file is') . ': ' . $profileFile;
			}

		}

		$profileUploadNotice =
			'<div id="sm_notice">' .
				'<p>' . $this->_("Use this form to upload site profiles. A site profile file must be a compressed zip file. The zip file must be named as 'site-xxx.zip' where 'xxx' is the name of the profile, for instance, 'site-default' or 'site-custom'.") . '</p>' .
				'<p>' . $this->_("The zip file must contain a single top directory named identically to the file. For instance, for a profile file named 'site-default.zip', the top directory must be named 'site-default'. The directory structure inside the top directory must match the structure ProcessWire expects. Please see the site profiles that ship with ProcessWire installs if you are unsure.") . '</p>' .
				'<p>' . $this->_("Please note that the wire folder is not needed at all. Please remove it from your site profile if present.") . '</p>' .
			'</div>';

		$options = array(
			'profile_title' => array($this->_('Profile Title'), 
				'text', 'sm_upload_profile_title',
				$this->_('This title will appear in the select dropdown for profiles when choosing a profile to install'),
				$profileTitle
			),
			'profile_summary' => array($this->_('Profile Description'), 
				'textarea', 'sm_upload_profile_summary',
				$this->_("This is an optional short description/summary of the profile. It serves as a quick reminder what a profile contains when browsing the list of uploaded profiles."),
				$profileSummary
			),

			'profile_compatibility' => array($this->_('Profile Compatibility'), 
				'radio', 'sm_upload_profile_compatibility',
				$this->_("Indicate what ProcessWire version the site profile you are uploading is compatible with. Normally, a ProcessWire 2.7 profile should be also work in a ProcessWire 3.x site."),
				array(1=>'2.7', 2=>'2.8', 3=>'3.x'),
				'checked' => $compatibility
			)
		);

		$fileOptions = array($this->_('Profile File'), 
			'file', 'sm_upload_profile_file',
			$this->_("Upload a single site profile. It must be a zip file named as 'site-xxx' where 'xxx' is the name of the profile. For instance, 'site-custom' or 'site-default'.") . $extraProfileFileText
		);

		$editProfileHiddenOptions = array('', 'hidden', 'sm_edit_profile', '', $editProfileID);

		$buttonSaveAndViewOptions = array(
			'idName' => 'sm_upload_and_view_btn',
			'classes' => 'ui-priority-secondary',
			'value' => $this->_('Save & View Profiles'),
			'clone' => 0
		);

		$buttonValue = $this->_('Save');
		if(!$editingProfile) {
			// button
			$idName = 'sm_upload_btn';
			$buttonClone = 1;
			$buttonSaveAndView = $this->renderInputButton($buttonSaveAndViewOptions);
			// back links
			$menu = $this->renderBackLink();
			// form
			$class = 'sm_new_profile';
		}
		else {
			// button
			$idName = 'sm_edit_profile_btn';
			$buttonClone = 0;
			$buttonSaveAndView = '';
			// menu`
			$menu = '';
			// form
			$class = 'sm_edit_profile';
		}		

		$buttonOptions = array(
			'idName' => $idName,
			//'classes' => 'sm_upload_execute',
			'value' => $buttonValue,
			'clone' => $buttonClone			
		);

		## populate $out
		$out .= $menu;	// @note: back link only here
		
		$out .= '<div id="sm_profile_upload_wrapper" class="InputfieldContent sm_form_wrapper">';
		$out .= '<form method="post" action="./" id="sm_profile_upload_form" enctype="multipart/form-data" class="'.$class.'">';
		$out .= (!$editingProfile ? $profileUploadNotice : '');

		$out .= '<div id="sm_profile_upload">';
		foreach($options as $key => $value) {
			$out .=	 $this->getInput($value) .
					'<p class="notes">'.$value[3].'</p>';
		}
		$out .= 	$this->renderInputFiles($fileOptions) .
					'<p class="notes">'.$fileOptions[3].'</p>' .
				'</div>';

		if($editingProfile) $out .= $this->renderInputHidden($editProfileHiddenOptions);

		$out .= 	$this->renderInputHidden($clientSideValidationOptions) .
					$this->renderInputButton($buttonOptions) .
					$buttonSaveAndView . 		
					$this->renderToken() .// CSRF
				'</form>' .
				$this->renderPopupUploadValidation();

		$out .= '</div>';// END div#sm_profile_upload_wrapper
		
		return $out;

	}

	/**
	 * Render the sites' install configurations list and their action form.
	 *
	 * Used to install new multi-site.
	 * 
	 * @access protected
	 * @return string $out Markup of install configurations site form.
	 * 
	 */
	protected function renderSitesConfigs() {		

		// determine number of site configs to show per page in dashboard. Default = 10 {see parent::showLimit}
		$this->setItemsShowLimit();
		
		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_configs_btn) {
			$actionType = 'configs';
			$this->smActions->actionItems($actionType, $post);
		}
		
		## prepare variables
		$out = '';
		$errors = '';
		$popupTitle = $this->_('Action Config');
		$button = '';
		$buttonOptions = array(
			'idName' => 'sm_configs_btn',
			'classes' => 'sm_bulk_action_btn',
			'value' => $this->_('Execute')
		);

		$selector = "template=sites-manager-install-configuration,parent.name=sites-manager-install-configurations,limit={$this->showLimit}";
		$items = $this->smUtilities->getItems($selector);

		$actions = array(
			'select' => $this->_('Actions (checked items)'),// @note: this is set as the initial value
			'lock' => $this->_('Lock (no edit)'),
			'unlock' => $this->_('Unlock'),
			//'trash' => $this->_('Trash Configuration'),// @todo: include this?
			'delete' => $this->_('Delete Configuration'),
		);

		$selectOptions = array('','select', 'sm_itesm_action_select', '', $actions);
		
		## populate $out
		if(!$items->count) {
			$url = $this->wire('page')->url . 'config/';
			$configLink = '<a href="'.$url.'">' . $this->_('add') . '</a>';
			$noConfigsNotice = sprintf(__('No Site Installation Configurations found! You will need to %s at least one if you want to install using a configuration.'), $configLink);
			$errors .= '<p>' . $noConfigsNotice . '</p>';
		}

		else {
			$out .= $this->renderHeadlineStrip($items) .
					$this->renderTopActionStrip($items, $selectOptions) .
					$this->renderSitesConfigsList($items) .			
					'<div id="sm_bottom_pagination">' . $this->renderPagination($items) . '</div>';
			$button = $this->renderInputButton($buttonOptions);
		}

		$out = $this->renderMenu() .
			'<div id="sm_configs_wrapper" class="InputfieldContent sm_form_wrapper">' .
				$errors .
				'<form method="post" action="./" id="sm_configs_form">' .
					$out . 
					$button .
					$this->renderToken() .// CSRF
				'</form>'.
				$this->renderPopupNoItemsSelected($popupTitle) .
				$this->renderPopupNoActionSelected($popupTitle) .
			'</div>';

		return $out;

	}

	/**
	 * Build and render a listing of added site installed configurations.
	 *
	 * @access private
	 * @param PageArray $siteConfigs PageArray of install configs.
	 * @return string $out Markup of list of site install configurations.
	 * 
	 */
	private function renderSitesConfigsList($siteConfigs) {
		
		## prepare variables
		$out = '';
		$prefix = 'sm_itesm_action_selected';
		$noConfigSummary = $this->_('This install configuration has no summary');
		
		// profile rows
		foreach ($siteConfigs as $p) {
			$sc = $this->smUtilities->getSettingsObject($p->sites_manager_settings);
			$profile = '';

			if($p->is(Page::statusLocked)) $editText = $this->renderLockedForEdits($p);
			else {
				$editURLText = $this->_('edit');
				$url = $this->wire('page')->url . 'config/' . $p->id . '/?modal=1';
				$editURL = 	'<a href="'.$url.'" class="sm_edit_config pw-modal-medium pw-modal">' . $editURLText .	'</a>';
				$editText = ' <small class="sm_edit">('. $editURL .')</small>';
			}
			
			// profile page
			$profilePage = $this->wire('pages')->get((int) $sc->profileFile);
			if($profilePage && $profilePage->id > 0) {
				$profile = $profilePage->title;
			}

			// timezone by name
			$timezone = $this->smUtilities->getTimeZones($sc->timezone);

			$out .= '<div class="sm_configs">' . 
						$this->renderInputCheckbox(array('','checkbox',$prefix.'[]','',$p->id,'mode'=>'multiple','id'=>$prefix.'_'.$p->id)) .
						'<h2>'. $p->title.'</h2>' .
						$editText .
						'<p class="sm_config_summary">' . (strlen($sc->summary) ? $sc->summary : $noConfigSummary) . '</p>' .
						// saved values: site @note: some need converting from IDs
						'<span>'. sprintf(__('Profile: %s'), $profile) . '</span>' .	
						'<span>'. sprintf(__('Admin Theme: %s'), $this->adminThemes[$sc->adminTheme]) . '</span>' .
						'<span>'. sprintf(__('Colour Theme: %s'), ucfirst($this->colours[$sc->colourTheme])) . '</span>' .
						'<span>'. sprintf(__('Time Zone: %s'), $timezone) . '</span>' .
						// saved values: database
						'<span>'. sprintf(__('Database User: %s'), $sc->dbUser) . '</span>' .// @todo?
						'<span>'. sprintf(__('Database Host: %s'), $sc->dbHost) . '</span>' .// -DITTO-
						'<span>'. sprintf(__('Database Port: %s'), $sc->dbPort) . '</span>' .// -DITTO-
						// saved values: superuser
						'<span>'. sprintf(__('Superuser Name: %s'), $sc->superUserName) . '</span>' .// @todo?
						'<span>'. sprintf(__('Superuser Email: %s'), $sc->superUserEmail) . '</span>' .// -DITTO-				// saved values: file permissions
						'<span>'. sprintf(__('Directory Permissions: %s'), $sc->chmodDir) . '</span>' .
						'<span>'. sprintf(__('File Permissions: %s'), $sc->chmodFile) . '</span>' .

						'<span>'. sprintf(__('Modified: %s'), date('d F Y', $p->modified)) . '</span>' .
					'</div>';
		}

		return $out;

	}

	/**
	 * Render the sites' install configs create form.
	 *
	 * @access protected
	 * @return string $out Markup of install configuration form.
	 * 
	 */
	protected function renderSitesConfigsAdd() {
	
		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_config_add_btn || $post->sm_edit_config_btn || $post->sm_config_add_and_view_btn) {
			$actionType = $post->sm_edit_config_btn ? 'edit_config' : 'config';
			$this->smActions->actionItems($actionType, $post);
		}

		## prepare variables
		$out = '';	
		$this->configTitle = '';
		$editingConfig = false;
		$clientSideValidationOptions = array('', 'hidden', 'sm_confirm', '', 0);
		$timezoneID = '';

		# check if editing
		// @note: populating all the other values if editing
		$editConfigID = (int) $this->urlSeg2;
		if($editConfigID){			
			$editConfig = $this->wire('pages')->get($editConfigID);
			if($editConfig->id && $editConfig->id > 0) {
				$editingConfig = true;
				$this->configTitle = $editConfig->title;
				$out .= '<h2 class="sm_edit">'.$this->_('Edit Configuration').': ' . $this->configTitle . '</h2>';
				$this->siteInstallConfigs = $this->smUtilities->getSettingsObject($editConfig->sites_manager_settings);
				$timezoneID = $this->siteInstallConfigs->timezone;
			}
		}

		$timeZoneHiddenOptions = array('', 'hidden', 'sm_timezone_id', '', $timezoneID);
		$editConfigHiddenOptions = array('', 'hidden', 'sm_edit_config', '', $editConfigID);
		$editConfigHidden = $editingConfig ? $this->renderInputHidden($editConfigHiddenOptions) : '';

		$buttonSaveAndViewOptions = array(
			'idName' => 'sm_config_add_and_view_btn',
			'classes' => 'ui-priority-secondary',
			'value' => $this->_('Save & View Configurations'),
			'clone' => 0
		);

		$buttonValue = $this->_('Save');
		if(!$editingConfig) {
			// button
			$idName = 'sm_config_add_btn';
			$buttonClone = 1;
			$buttonSaveAndView = $this->renderInputButton($buttonSaveAndViewOptions);
			// backlink to configs list
			$backLink = $this->renderBackLink();
			// class for form to for configs edit vs non-editing mode 
			$class = 'sm_new_configuration';
		}
		else {
			// button
			$idName = 'sm_edit_config_btn';
			$buttonClone = 0;
			$buttonSaveAndView = '';
			// no back link here
			$backLink = '';
			// class for form to for configs edit vs non-editing mode 
			$class = 'sm_edit_configuration';
		}		

		$buttonOptions = array(
			'idName' => $idName,
			'value' => $buttonValue,
			'clone' => $buttonClone			
		);

		## populate $out
		$out .= $backLink;// @note: back link only here
		
		$out .= '<div id="sm_config_add_wrapper" class="InputfieldContent sm_form_wrapper">' .
					'<form method="post" action="./" id="sm_config_add_form" enctype="multipart/form-data" class="'.$class.'">' .
						// add/create install config form
						$this->renderAddConfigForm($editingConfig) .
						$this->renderInputHidden($timeZoneHiddenOptions) .
						// other form elements					
						$this->renderInputHidden($clientSideValidationOptions) .
						$editConfigHidden .
						$this->renderInputButton($buttonOptions) .
						$buttonSaveAndView . 		
						$this->renderToken() .// CSRF
					'</form>' .
					$this->renderTimeZonesScript() .
					$this->renderPopupCreateSite() .

				'</div>';// END div#sm_config_add_wrapper
		
		return $out;

	}

	/**
	 * Render parts of the configuration form for install configs' form.
	 *
	 * @access private
	 * @param boolean $editingConfig Checks if editing vs. adding a site install configuration.
	 * @return void
	 * 
	 */
	private function renderAddConfigForm($editingConfig) {

		## prepare variables
		$configSummary = '';		
		$configAddNotice =
		'<div id="sm_notice">' .
			'<p>' . 
				$this->_("Use this form to add site install configurations. All fields are required.").
			'</p>' .
		'</div>';

		// get form sections options
		$sitesArray = $this->smUtilities->getSiteOptions('site');
		$databaseArray = $this->smUtilities->getSiteOptions('database');
		$superUserArray = $this->smUtilities->getSiteOptions('superuser');
		$filePermissionsArray = $this->smUtilities->getSiteOptions('file_permissions');

		// @note: not all settings are applicable to this form compared to create form for sites
		// ...so, we remove them
		$sitesAllowed = array('site', 1,2,10,12,13,14);// keys of items to keep
		$sitesArray = $this->smUtilities->arrayIntersectKey($sitesArray, $sitesAllowed);
		$sitesArray[1][3] = $this->_('This title will appear in the select dropdown for install configurations when choosing a configuration to install');
		$sitesArray[2][3] = $this->_("This is an optional short description/summary of the install configuration. It serves as a quick reminder what an install configuration contains when browsing the list of install configurations.");

		$databaseAllowed = array('database',2,4,5);// keys of items to keep
		$databaseArray = $this->smUtilities->arrayIntersectKey($databaseArray, $databaseAllowed);

		$superUserAllowed = array('superuser',1,4);// keys of items to keep
		$superUserArray = $this->smUtilities->arrayIntersectKey($superUserArray, $superUserAllowed);

		// if editing, show saved values
		if($editingConfig) {
			# saved values
			$configs = $this->siteInstallConfigs;
			// saved values: site
			$sitesArray[1][4] = $this->configTitle;// title
			$sitesArray[2][4] = $configs->summary;// description
			$sitesArray[10]['selected'] = $configs->profileFile;// profile
			$sitesArray[12]['selected'] = $configs->adminTheme;// admin theme
			$sitesArray[13]['selected'] = $configs->colourTheme;// colour
			// timezone by name
			$timezone = $this->smUtilities->getTimeZones($configs->timezone);
			$sitesArray[14][4]= $timezone;// timezone
			// saved values: database
			$databaseArray[2][4] = $configs->dbUser;// db user
			$databaseArray[4][4] = $configs->dbHost;// db host
			$databaseArray[5][4] = $configs->dbPort;// db port
			// saved values: superuser
			$superUserArray[1][4] = $configs->superUserName;// superuser name
			$superUserArray[4][4] = $configs->superUserEmail;// superuser email
			
			// saved values: file permissions
			$filePermissionsArray[1][4] = $configs->chmodDir;// directories permission
			$filePermissionsArray[2][4] = $configs->chmodFile;// files permission

		}
		
		## populate $out
		$out =	'<div id="sm_config_add_form_inputs_wrapper">' .		
					(!$editingConfig ? $configAddNotice : '') .
					$this->renderCreateFormSections($sitesArray) .
					$this->renderCreateFormSections($databaseArray) .
					$this->renderCreateFormSections($superUserArray) .
					$this->renderCreateFormSections($filePermissionsArray) .				
			'</div>';

		return $out;

	}

	/**
	 * Render ProcessWire versions dashboard.
	 *
	 * @access protected
	 * @return string $out Markup of ProcessWire versions list.
	 * 
	 */
	protected function renderProcessWireVersions() {

		// determine number of versions to show per page in dashboard. Default = 10 {see parent::showLimit}
		$this->setItemsShowLimit();

		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_processwire_versions_btn) {
			$actionType = 'versions';
			$this->smActions->actionItems($actionType, $post);
		}
		
		## prepare variables
		$out = '';
		$popupTitle = $this->_('Action ProcessWire Version');
		$button = '';
		$buttonOptions = array(
			'idName' => 'sm_processwire_versions_btn',
			'classes' => 'sm_bulk_action_btn',
			'value' => $this->_('Execute')
		);

		$selector = "template=sites-manager-wire,parent.name=sites-manager-processwire-files,limit={$this->showLimit},sites_manager_files!=''"; 
		$items = $this->smUtilities->getItems($selector);

		$actions = array(
			'select' => $this->_('Actions (checked items)'),// @note: this is set as the initial value
			'lock' => $this->_('Lock (no edit)'),
			'unlock' => $this->_('Unlock'),
			//'trash' => $this->_('Trash Profile'),// @todo: include this?
			'delete' => $this->_('Delete'),//
			'download' => $this->_('Get Latest Version'),
		);

		$selectOptions = array('','select', 'sm_itesm_action_select', '', $actions);

		
		
		## populate $out
		$out .= $this->renderHeadlineStrip($items,2) .
				$this->renderTopActionStrip($items, $selectOptions) .
				$this->renderSpinner() .	
				'<p id="sm_download_warning" class="notes sm_hide">'.$this->_('Please note that it may take a little while to fetch and process downloads. Please be patient and do not refresh your browser.').'</p>' .
				$this->renderProcessWireVersionsList($items) .			
				'<div id="sm_bottom_pagination">' . $this->renderPagination($items) . '</div>';
		$button = $this->renderInputButton($buttonOptions);

		$out = $this->renderMenu() .
			'<div id="sm_processwire_versions_wrapper" class="InputfieldContent sm_form_wrapper">' .
				'<form method="post" action="./" id="sm_processwire_versions_form">' .
					$out . 
					$button .
					$this->renderToken() .// CSRF
				'</form>'.
				$this->renderPopupNoItemsSelected($popupTitle) .
				$this->renderPopupNoActionSelected($popupTitle) .
			'</div>';

		return $out;

	}

	/**
	 * Build and render a listing of uploaded site profiles.
	 *
	 * @access private
	 * @param PageArray $siteProfiles PageArray of site profiles.
	 * @return String $out Markup of list of site profiles.
	 * 
	 */
	private function renderProcessWireVersionsList($wireItems) {
		
		## prepare variables
		$out = '';
		$prefix = 'sm_itesm_action_selected';
		$processWireVersions = $this->smUtilities->getProcessWireVersionsInfo();
		$sanitizer = $this->wire('sanitizer');
		
		// processwire versions rows
		foreach ($processWireVersions as $index => $value) {

			$versionPageName = $sanitizer->pageName($value['title']);
			$version = $wireItems->get("name=$versionPageName");			
			
			if($version && $version->id > 0) {				
				$editText = $version->is(Page::statusLocked) ? $this->renderLockedForEdits($version) : '';
				$id = $version->id;
				$file = $version->sites_manager_files->first();
				$filename = $file->basename;
				$filesize = $file->filesizeStr;
				$fileText = sprintf(__('File: %1$s (%2$s)'), $filename, $filesize);
				$modified = '<span>'. sprintf(__('Modified: %s'), date('d F Y H:i', $version->modified)) . '</span>';
				$notDownloadedClass = '';
			}

			else {
				$editText = '';
				$id = 0;
				$fileText = $this->_('You have not downloaded this version yet. You need to do so before you can install it in a single site setup.');
				$modified = '';
				$notDownloadedClass = ' class="sm_red"';
			}

			## populate $out			
			$out .= '<div class="sm_processwire_versions" data-version-index="'.$index.'">' . 
						$this->renderInputCheckbox(array('','checkbox',$prefix.'[]','',$id,'mode'=>'multiple','id'=>$prefix.'_'.$index)) .
						'<h2>'. $value['title'].'</h2>' .
						$editText .
						'<p class="sm_processwire_version_summary">' . $value['summary'] . '</p>' .
						'<span'. $notDownloadedClass .'>'. $fileText  . '</span>' .
						$modified .
					'</div>';
		}

		return $out;

	}

	/**
	 * Render the cleanup form.
	 *
	 * @access protected
	 * @return string $out Markup of cleanup form.
	 * 
	 */
	protected function renderSitesCleanup() {

		## catch posts
		$post = $this->wire('input')->post;
		if($post->sm_cleanup_btn) {
			$actionType = 'cleanup';
			$this->smActions->actionItems($actionType, $post);
		}	
		
		## prepare variables
		$out = '';

		$filesPath = $this->wire('config')->paths->root;		
		$components = array(
			'pages' => array('Sites Manager: Profiles', 'Sites Manager: Installed Sites'),
			'fields' => array('sites_manager_settings', 'sites_manager_files'),
			'templates' => array('sites-manager-site-profiles', 'sites-manager-site-profile', 'sites-manager-installed-sites', 'sites-manager-installed-site','sites-manager-wires','sites-manager-wire','sites-manager-install-configurations','sites-manager-install-configuration'),
			'files' => array('sm_sites_json' => $filesPath .'sites.json', 'sm_index_config' => $filesPath .'index.config.php'),
		);

		$buttonOptions = array(
			'idName' => 'sm_cleanup_btn',
			'classes' => 'sm_cleanup_execute',
			'value' => $this->_('Cleanup')
		);

		$checkBoxLabel = $this->_('Check to delete file');

		## populate $out		
		$out .= $this->renderMenu();
		$out .= '<div id="sm_cleanup_wrapper" class="InputfieldContent sm_form_wrapper">';
		$out .= '<form method="post" action="./" id="sm_cleanup_form">';
		$out .= '<div id="sm_cleanup_warning">' .
					'<p>' . $this->_('This utility will irreversibly delete the following listed Sites Manager components and their child pages as applicable. Before proceeding, make sure that this is the action you wish to take.') . '</p>' .
					'<p>' . $this->_('Please note that installed sites\' databases and site directories and the files in them will be left untouched.') . '</p>' .
				'</div>';
		
		foreach ($components as $key => $value) {
			$out .= '<div class="sm_cleanup_header"><h2>'.ucfirst($key).'</h2></div>';
			$out .= '<div class="sm_cleanup_items"><ol>';
			foreach ($value as $k => $v) {
				if('files' == $key) $out .= '<li>'. $v.'<span>'.$this->renderInputCheckbox(array($checkBoxLabel,'checkbox',$k,'',1)).'</span></li>';
				else $out .= '<li>'. $v.'</li>';
			}
			$out .= '</ol></div>';
		}

		$out .= $this->renderInputButton($buttonOptions) .	
				$this->renderToken() .// CSRF	
				'</form>';

		$out .= '</div>';// END div#sm_cleanup_wrapper

		return $out;

	}

	/**
	 * Render navigation menu.
	 *
	 * @access private
	 * @return string $out Markup of menu.
	 *
	 */
	private function renderMenu() {
	
		## prepare variables
		$out = '';

		$menuItems = array(
			'installed' => $this->_('Sites'),
			//'create' => $this->_('Create'),
			'profiles' => $this->_('Profiles'),
			//'upload' => $this->_('Upload'),
			'configs' => $this->_('Configs'),
			'wire' => $this->_('Wire'),			
			'cleanup' => $this->_('Cleanup'),
		);

		## populate $out
		$out .= '<div id="sm_menu"><ul class="sm_menu">';

		foreach ($menuItems as $key => $value) {
			$on = ($this->urlSeg1 == $key) || (!$this->urlSeg1 && 'installed' == $key) ? 'sm_menu_item sm_on' : 'sm_menu_item';
			$url = 'installed' == $key ? $this->wire('page')->url : $this->wire('page')->url . $key .'/';
			$out .= '<li><a class="' . $on . '" href="' . $url . '">' . $value . '</a></li>';
		}

		$out .= '</ul>';
		$out .= '</div>';

		return $out;

	}

	/**
	 * Renders locked edit status markup for a given page.
	 *
	 * @access private
	 * @param Page $page Page whose locked edit status to check.
	 * @return string $out Markup showing locked edit status if applicable.
	 */
	private function renderLockedForEdits(Page $page) {
		$lockedForEditsStr = $this->_('Item locked for edits');
		$out = ($page->is(Page::statusLocked)) ? '<small class="sm_locked">'.$lockedForEditsStr.'</small>' : '';
		return $out;
	}

	/**
	 *	Render markup of headline strip for items dashboards.
	 *
	 *	@access private
	 *	@param PageArray $items Items that will be used in the headline.
	 *	@param integer $mode Denotes item type to return dashboard for.
	 *	@return string $out Markup of headline for dashboard.
	 *
	 */
	private function renderHeadlineStrip($items=null, $mode=1) {
		if(1 == $mode) $content = $this->renderItemsCount($items) . $this->renderCreateLink();// items dashboards (except wire)
		elseif(2 == $mode) $content = $this->renderItemsCount($items);// wire dashboard only
		else $content = $this->renderBackLink();// 'create/upload' contexts
		$out = '<div id="sm_headline_strip">'. $content . '</div>';
		return $out;
	}

	/**
	 *	Render markup of items count for items dashboards.
	 *
	 *	@access private
	 *	@param PageArray $items Items that will be used in count.
	 *	@return string $out Markup of items count.
	 *
	 */
	private function renderItemsCount($items) {
		// display a headline indicating quantities. We'll add this to respective items dashboard
		$itemsCount = '';
		$start = $items->getStart()+1;
		$end = $start + count($items)-1;
		$total = $items->getTotal();
		if($total) $itemsCount = '<p id="sm_itesm_count" class="description">' . sprintf(__('Items %1$d to %2$d of %3$d'), $start, $end, $total) . '</pn>';
		$out = '<div id="sm_headline_itesm_count">' .
					$itemsCount .
				'</div>';

		return $out;
	}

	/**
	 *	Render markup of a link to creating a Multi Site item.
	 *
	 *	@access private
	 *	@return string $out Markup of link.
	 *
	 */
	private function renderCreateLink() {

		$createLink = $this->smUtilities->setCreateLink();
		$createLinkURLSeg = $createLink[0];
		$createLinkText = $createLink[1];

		$link = '<a href="' .
			$this->wire('page')->url . $createLinkURLSeg . '/' .'"'.
			'title="' . $createLinkText . '">' . $this->renderIcons('add') .  $createLinkText  .
		'</a>';


		$out = '<div id="sm_headline_create_link">' .
					'<span id="sm_create_link">'. $link .'</span>' .
			'</div>';

		return $out;

	}

	/**
	 *	Render markup of a back link to list of Multi Site items.
	 *
	 *	@access private
	 *	@return string $out Markup of back link.
	 *
	 */
	private function renderBackLink() {
		
		$backLink = $this->smUtilities->setBackToItemsListLink();
		$backLinkURLSeg = 'installed' == $backLink[0] ? '' : $backLink[0] . '/';
		$backLinkText = $backLink[1];

		$link = '<a href="' .
			$this->wire('page')->url . $backLinkURLSeg .'"'.
			'title="' . $backLinkText . '">' . $this->renderIcons('back') .  $backLinkText  .
		'</a>';


		$out = '<div id="sm_headline_back_link">' .
					'<span id="sm_back_link">'. $link .'</span>' .
			'</div>';

		return $out;

	}

	/**
	 *	Render markup of an actions strip for manipulating listed items.
	 *
	 *	@access private
	 *	@param PageArray $items Items that will be rendered in pagination.
	 *	@param array $options Options for building actions select.
	 *	@return string $out Markup of inputs and pagination.
	 *
	 */
	private function renderTopActionStrip($items, $options) {
		$out = '<div id="sm_top_strip_actions">'.
					'<div id="sm_top_action_selects">' .
						$this->renderInputCheckbox(array('','checkbox','sm_toggle_all','',1)) .
						$this->renderInputSelect($options) .  $this->renderLimitSelect() .
					'</div>' .
					'<div id="sm_top_pagination">' . $this->renderPagination($items) . '</div>' .
				'</div>';

		return $out;
	}

	/**
	 *	Renders markup for a select for limiting number of items to show per paginated page.
	 *
	 *	@access private
	 *	@return String $out Markup of select.
	 *
	 */
	private function renderLimitSelect() {
		$perPageLabel = $this->_('per page');
		$out = '';
		$out .= '<select id="limit" name="show_limit">';
		$limits = array(5, 10, 15, 25, 50, 75, 100);
		foreach ($limits as $limit) $out .='<option value="' . $limit . '"' . ($this->showLimit == $limit ? 'selected="selected"':'') . '>' . $limit . ' ' . $perPageLabel .'</option>';
		$out .= '</select> ';
		return $out;
	}

	/**
	 *	Renders pagination of given PageArray items.
	 *
	 *	@access private
	 *	@param PageArray $items Items to paginate.
	 *	@return String $out Pagination markup.
	 *
	 */
	private function renderPagination($items) {
		$currentUrl = $this->wire('page')->url . $this->wire('input')->urlSegmentsStr."/";// get the url segment string.
		$out = $items->renderPager(array('baseUrl' => $currentUrl));
		return $out;
	}

	/**
	 * Render notice to show Sites Manager components missing.
	 * 
	 * These are fields, templates, files and pages.
	 *
	 * @access protected
	 * @return string $out Markup of error message.
	 */
	protected function renderSitesManagerComponentsMissing() {
		$moduleInstallURL = $this->wire('config')->urls->admin . 'module/edit?name=ProcessSitesManager';
		$reinstallModuleLink = '<a href="'.$moduleInstallURL.'">' . $this->_('re-install') . '</a>';
		$noComponentsNotice = sprintf(__('Required Sites Manager components  are missing. Please %s the module or resolve any errors that were shown during the install.'), $reinstallModuleLink);
		$out = '<div id="sm_missing_components_wrapper" class="InputfieldContent sm_form_wrapper"><p>'.$noComponentsNotice.'</p></div>';
		return $out;
	}

	######## INPUTS #######
	
	/**
	 * Get a specified input type.
	 *
	 * @access private
	 * @param array $options Options for the given input.
	 * @return string $out Markup of the requested input.
	 * 
	 */
	private function getInput($options) {
		$type = $options[1];
		if('select' == $type) $out = $this->renderInputSelect($options);
		elseif('textarea' == $type) $out = $this->renderInputTextArea($options);	
		elseif('checkbox' == $type) $out = $this->renderInputCheckbox($options);
		elseif('radio' == $type) $out = $this->renderInputRadio($options);
		else $out = $this->renderInputText($options);
		return $out;
	}		

	/**
	 * Render a select input.
	 *
	 * @access private
	 * @param array $options Options to build the input select.
	 * @return string $out Markup of input select.
	 * 
	 */
	private function renderInputSelect($options) {
		$values = $options[4];
		$selectedValue = isset($options['selected']) ? $options['selected'] : '';
		$out = 	$options[0] ? '<label for="'.$options[2].'">'.$options[0].'</label>' : '';
		$out .= '<select id="'.$options[2].'" name="'.$options[2].'">';		
		foreach($values as $value => $label) {
			$selectedStr = $value == $selectedValue ? ' selected' : '';
			$out .= '<option value="'.$value.'"'.$selectedStr.'>' . ucfirst($label) . '</option>';
		}
		$out .= '</select>';	
		return $out;
	}

	/**
	 * Render a text input.
	 *
	 * @access private
	 * @param array $options Options to build the input text.
	 * @return string $out Markup of input text.
	 * 
	 */
	private function renderInputText($options) {
		$placeHolder = '';
		if('sm_timezone' == $options[2]) $placeHolder = ' placeholder="'. $this->_('Start typing name of time zone...') .'"';
		
		$value = isset($options[4]) ? $options[4] : '';
		$out = 	'<label for="'.$options[2].'">'.$options[0].'</label>' .
				'<input id="'.$options[2].'" name="'.$options[2].'" value="'.$value.'" type="'.$options[1].'"'.$placeHolder.'>';		
		return $out;
	}

	/**
	 * Render a textarea
	 *
	 * @access private
	 * @param array $options Options to build the textarea.
	 * @return string $out Markup of the textarea.
	 * 
	 */
	private function renderInputTextArea($options) {		
		$value = isset($options[4]) ? $options[4] : '';
		$out = 	'<label for="'.$options[2].'">'.$options[0].'</label>' .
				'<textarea id="'.$options[2].'" name="'.$options[2].'" rows="3">'.$value.'</textarea>';		
		return $out;
	}

	/**
	 * Render a checkbox(es) input.
	 *
	 * @access private
	 * @param array $options Options to build the checkbox(es).
	 * @return string $out Markup of the checkbox(es).
	 * 
	 */
	private function renderInputCheckbox($options) {		
		$value = isset($options[4]) ? $options[4] : '';
		$mode =  isset($options['mode']) ? $options['mode'] : '';
		if('multiple' == $mode) $id = $options['id'];
		else $id = $options[2];		
		$out = 	($options[0] ? '<label for="'.$options[2].'">'.$options[0].'</label>' : '') .
				'<input id="'.$id.'" name="'.$options[2].'" value="'.$value.'" type="'.$options[1].'">';		
		return $out;
	}

	/**
	 * Render a radio input.
	 *
	 * @access private
	 * @param array $options Options to build the radio input.
	 * @return string $out Markup of the radio.
	 * 
	 */
	private function renderInputRadio($options) {

		$out = '';
		$values = $options[4];
		$checkedValue = isset($options['checked']) ? $options['checked'] : '';
		$out .= '<span class="sm_radio_header">'.$options[0].'</span>';
		foreach ($values as $value => $label) {
			$checkedStr = $value == $checkedValue ? ' checked' : '';
			$out .= '<input id="'.$options[2].'_'.$value.'" name="'.$options[2].'" value="'.$value.'" type="'.$options[1].'"'.$checkedStr.'>' .
					'<label for="'.$options[2].'_'.$value.'" class="sm_radio">'.$label.'</label>';
		}
		
		return $out;
	}

	/**
	 * Render a hidden input.
	 *
	 * @access private
	 * @param array $options Options to build the hidden input.
	 * @return string $out Markup of the hidden input.
	 * 
	 */
	private function renderInputHidden($options) {
		$value = isset($options[4]) ? $options[4] : '';
		$out = 	'<input id="'.$options[2].'" name="'. $options[2].'" value="'.$value.'" type="hidden">';
		return $out;
	}

	/**
	 * Render a button input.
	 *
	 * @access private
	 * @param array $options Options to build the button input.
	 * @return string $out Markup of the rendered button.
	 * 
	 */
	private function renderInputButton($options) {
		$f = $this->wire('modules')->get('InputfieldSubmit');
		$f->class .= isset($options['clone']) && 0 == $options['clone'] ? '' : ' head_button_clone';
		$f->attr('id+name', $options['idName']);
		$f->class .= isset($options['classes']) ? " " . $options['classes'] : '';// add a custom class to this submit button
		$f->attr('value', $options['value']);
		return $f->render();

	}

	/**
	 * Render a file input.
	 *
	 * @access private
	 * @param array$options Options to build the file input.
	 * @return string $out Markup of the rendered file input.
	 * 
	 */
	private function renderInputFiles($options) {			
		$out = 	'<label for="'.$options[2].'">'.$options[0].'</label>' .
		'<input id="'.$options[2].'" name="'.$options[2].'" type="'.$options[1].'" accept=".zip">';		
		return $out;
	}

	/**
	 * Render a hidden input with a token for CSRF protection.
	 *
	 * @access private
	 * @return string $token The input with the CSRF token.
	 * 
	 */
	private function renderToken() {		
		// CSRF
		$session = $this->wire('session');
		$tokenName = $session->CSRF->getTokenName();
		$tokenValue = $session->CSRF->getTokenValue();
		$token = "<input type='hidden' id='_post_token' name='" . $tokenName . "' value='" . $tokenValue . "'>";
		return $token;
	}

	######## OTHER #######

	/**
	 * Render markup for passing selectable attributes to JavaScript.
	 *
	 * Will be accessed as a JavaScript object in the browser.
	 * The JS configs are used by jQueryUI Autocomplete for attribute and attribute values suggestions.
	 *
	 * @access private
	 * @return string $out Markup of script.
	 *
	 */
	private function renderTimeZonesScript() {
		$timeZonesJSON = $this->smUtilities->getTimeZones();
		if(!$timeZonesJSON) $timeZonesJSON .= $timeZonesJSON . '"";';
		$out = "\n\t<script type='text/javascript'>\n\tvar sitesManagerTimeZonesConfig = $timeZonesJSON\n\t</script>";
		return $out;
	}

	/**
	 * Render elements for client-side validation.
	 * 
	 * Rendered in markup in popups using magnific.
	 *
	 * @access private
	 * @return string $out Markup of hidden popup elements.
	 * 
	 */
	private function renderPopupUploadValidation() {
		
		$popupTitle = $this->_('Upload Profile');		

		$out = '<div id="sm_validation_wrapper">' .
				'<div id="sm_validation" class="mfp-hide">' .
					'<h2 id="sm_validation_header">'. $this->_('Please correct the following errors in order to continue').'</h2>' .
				'</div>';

		$popups = array(
			// required fields
			'required_fields' => $this->_('All required fields need to be completed.'),
		);

		$out .= '<div id="sm_popup_messages" class="sm_hide">';
		foreach ($popups as $id => $text) {
			$out .= '<p id="sm_'.$id.'">'.$text.'</p>';
		}
		
		// hidden element for popup data
		$out .= 	'<span id="sm_popup_data" data-popup-title="'.$popupTitle.'" data-popup-src="sm_validation"></span>' .
					'</div>' .// END div#sm_popup_messages					
				'</div>';// END div#sm_validation_wrapper

		return $out;

	}

	/**
	 * Render elements for client-side validation.
	 * 
	 * Rendered in markup in popups using magnific.
	 *
	 * @access private
	 * @return string $out Markup of hidden popup elements.
	 * 
	 */
	private function renderPopupCreateSite() {

		$popupTitle = $this->_('Create/Install Site Validation');

		$out = '<div id="sm_validation_wrapper">' .
				'<div id="sm_validation" class="mfp-hide">' .
					'<h2 id="sm_validation_header">'. $this->_('Please correct the following errors in order to continue').'</h2>' .
					'<ol id="sm_validation_errors"></ol>' .
				'</div>';

		$popups = array(
			// required fields
			'required_fields' => $this->_('All required fields need to be completed.'),

			// admin
			'admin_name_disallowed' => $this->_("Admin name may not be 'wire' or 'site'."),
			'admin_name_short' => $this->_('Admin login URL must be at least 2 characters long.'),
			'admin_name_characters_disallowed' => $this->_('Admin login URL must be only a-z 0-9.'),

			// superuser
			'superuser_name_characters_disallowed' => $this->_('Superuser name must be only a-z 0-9.'),
			'superuser_name_short' => $this->_('Superuser name must be at least 2 characters long.'),
			'superuser_passwords_mismatch' => $this->_('Superuser passwords do not match.'),
			'superuser_password_short' => $this->_('Superuser password must be at least 6 characters long.'),
			'superuser_email_invalid' => $this->_('Superuser email address did not validate.'),			

		);

		$out .= '<div id="sm_popup_messages" class="sm_hide">';

		foreach ($popups as $id => $text) $out .= '<p id="sm_'.$id.'">'.$text.'</p>';

		// hidden element for popup data
		$out .= 	'<span id="sm_popup_data" data-popup-title="'.$popupTitle.'" data-popup-src="sm_validation"></span>' .
					'</div>' .// END div#sm_popup_messages
				'</div>';// END div#sm_validation_wrapper

		return $out;

	}

	/**
	 * Render elements for client-side confirmation for installed sites actions.
	 * 
	 * Most bulk actions in installed sites dashboard are permanently destructive!
	 * We use this popup for them to confirm their action.
	 * Rendered in markup in popups using magnific.
	 *
	 * @access private
	 * @return string $out Markup of hidden popup elements.
	 * 
	 */
	private function renderPopupInstalledSites() {
		
		$popupTitle = $this->_('Installed Sites Action');
		$popupTitle2 = $this->_('Action Installed Sites');

		$buttonConfirmOptions = array(
			'idName' => 'sm_installed_sites_action_confirm_btn',
			'classes' => 'sm_popup_btn',
			'value' => $this->_('Confirm Delete'),
			'clone' => 0
		);

		$buttonCancelOptions = array(
			'idName' => 'sm_installed_sites_action_cancel_btn',
			'classes' => 'sm_popup_btn ui-priority-secondary',
			'value' => $this->_('Cancel'),
			'clone' => 0
		);

		$out = '<div id="sm_installed_sites_action_wrapper">' .
				'<div id="sm_installed_sites_action_confirm" class="mfp-hide">' .
					'<h2 id="sm_installed_sites_action_confirm_header">'. $this->_('Are you sure you want to do this? You are about to permanently delete the following directories and/or databases and selected pages').'</h2>' .
					'<ol id="sm_installed_sites_delete_files_confirm_list"></ol>' .
					'<ol id="sm_installed_sites_delete_databases_confirm_list"></ol>' .
					$this->renderInputButton($buttonConfirmOptions) .
					$this->renderInputButton($buttonCancelOptions) .
				'</div>';
		// no action selected
	
		$popups = array(
			// delete sites files
			'delete_sites_files_confirm' => $this->_('The following sites directories will be permanently deleted.'),
			// delete sites databases
			'delete_sites_databases_confirm' => $this->_('The following sites databases will be permanently deleted.'),
		);

		$out .= '<div id="sm_popup_messages" class="sm_hide">';
		foreach ($popups as $id => $text) {
			$out .= '<p id="sm_'.$id.'">'.$text.'</p>';
		}
		
		// hidden element for popup data
		$out .= 	'<span id="sm_popup_data" data-popup-title="'.$popupTitle.'" data-popup-src="sm_installed_sites_action_confirm"></span>' .				
					'</div>' .// END div#sm_popup_messages	
					$this->renderPopupNoItemsSelected($popupTitle2) .
					$this->renderPopupNoActionSelected($popupTitle2) .
				'</div>';// END div#sm_installed_sites_action_wrapper

		return $out;

	}

	/**
	 * Render elements for client-side notification if no items selected for an executed bulk action.
	 *
	 * @access private
	 * @return string $out Markup of hidden popup elements.
	 * 
	 */
	private function renderPopupNoItemsSelected($popupTitle) {
		$out = 
			'<span id="sm_no_itesm_popup_data" data-popup-title="'.$popupTitle.'" data-popup-src="sm_no_item_selected"></span>' .		
			'<div id="sm_no_item_selected_wrapper">' .
			'<div id="sm_no_item_selected" class="mfp-hide">' .
				'<h2 id="sm_no_item_selected_header">'. $this->_('Please correct the following errors in order to continue').'</h2>' .
				'<p class="sm_error">'.$this->_('You need to select at least one item to action.').'</p>' .
			'</div>';
		return $out;
	}

	/**
	 * Render elements for client-side notification if no action selected for an executed bulk action.
	 *
	 * @access private
	 * @return string $out Markup of hidden popup elements.
	 * 
	 */
	private function renderPopupNoActionSelected($popupTitle) {
		$out = 
			'<span id="sm_no_action_popup_data" data-popup-title="'.$popupTitle.'" data-popup-src="sm_no_action_selected"></span>' .		
			'<div id="sm_no_action_selected_wrapper">' .
			'<div id="sm_no_action_selected" class="mfp-hide">' .
				'<h2 id="sm_no_action_selected_header">'. $this->_('Please correct the following errors in order to continue').'</h2>' .
				'<p class="sm_error">'.$this->_('You need to select an action to apply to selected items.').'</p>' .
			'</div>';
		return $out;
	}

	/**
	 * Render markup for a spinner for UX.
	 *
	 * @access private
	 * @param integer $attrNum The number of the attribute (mainly for JS use).
	 * @return string $out Markup for spinner.
	 *
	 */
	private function renderSpinner() {
		$out = '<div id="sm_spinner_wrapper">'.
					'<span id="sm_spinner" class="sm_spinner">' .
						'<i class="fa fa-lg fa-spin fa-spinner sm_spinner sm_hide"></i>' . 
					'</span>' .
				'</div>';
		return $out;
	}

	/**
	 * Renders icons for various uses in the UI.
	 *
	 * Fontawesome icons.
	 *
	 * @access private
	 * @param string $icon String matching an array index for icon to render.
	 * @return string $icon Markup of requested icon.
	 *
	 */
	private function renderIcons($icon) {
		
		$icons = array(

						'published' => '<i class="fa fa-fw fa-eye" title="' . $this->_('Published') . '"></i>',
						'unpublished' => '<i class="fa fa-fw fa-eye-slash" title="' . $this->_('Unpublished')  . '"></i>',
						'locked' => '<i class="fa fa-fw fa-lock" title="' . $this->_('Locked') . '"></i>',
						'unlocked' => '<i class="fa fa-fw fa-unlock" title="' . $this->_('Unlocked') . '"></i>',// fa-unlock-alt
						'usage' => '<i class="fa fa-check-square-o" title="' . $this->_('Usage Count') . '"></i>',
						'add' => '<i class="fa fa-fw fa-plus-circle"></i>',
						'back' => '<i class="fa fa-fw fa-arrow-circle-left"></i>',

			);

		$icon = $icons[$icon];

		return $icon;

	}

}