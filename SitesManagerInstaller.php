<?php

/**
* Sites Manager: Installer
*
* This file forms part of the Sites manager Suite.
* It is an install wizard for Sites manager module and is only run once when installing the module.
* It installs 'fields', 'templates', 'files' and 'sites manager parent  pages'.
* If the above already exist (i.e., same names); this installer aborts wholesale.
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

class SitesManagerInstaller extends ProcessSitesManager {


	const PAGE_NAME = 'sites-manager';// this process' name

	/**
	 * Check if identical files, fields, templates and Sites manager pages exist before install.
	 *
	 * @access public
	 * @param null|integer $mode Whether to verify if install possible (null) or commence install (1).
	 *
	 */
	public function verifyInstall($mode = null) {

		$pageCheck = '';
		$config = $this->wire('config');

		// if we have already verified install, proceed directly to first step of installer
		if($mode == 1) return $this->createFields();

		// 1. ###### First we check if required page, fields, templates and files already exist.
		// If yes to any of these, we abort installation and return error messages

		# items exist checks
		// check if required page already exists under admin
		$parent = $this->wire('pages')->get($config->adminRootPageID);
		$page = $this->wire('pages')->get("parent=$parent, template=admin, include=all, name=".self::PAGE_NAME);
		if($page->id && $page->id > 0) $pageCheck = $page->title;
		$pageExist = $pageCheck ? true : false;// we'll use this later + $pageCheck to show error

		// check if required fields already exist
		$fields  = array('settings' => 'sites_manager_settings', 'files' => 'sites_manager_files');
		$fieldsCheck = array();
		foreach ($fields as $key => $value) {if($this->wire('fields')->get($value))	$fieldsCheck[] = $this->wire('fields')->get($value)->name;}
		$fieldsExist = count($fieldsCheck) ? true : false;

		// check if required templates already exist
		$templates = array('site_profiles' => 'sites-manager-site-profiles', 'site_profile' => 'sites-manager-site-profile', 'installed_sites' => 'sites-manager-installed-sites', 'installed_site' => 'sites-manager-installed-site','wire_files' => 'sites-manager-wires', 'wire_file' => 'sites-manager-wire','install_configurations' => 'sites-manager-install-configurations', 'install_configuration' => 'sites-manager-install-configuration');
		$templatesCheck = array();
		foreach ($templates as $template) {if($this->wire('templates')->get($template)) $templatesCheck[] = $this->wire('templates')->get($template)->name;}
		$templatesExist = count($templatesCheck) ? true : false;

		// check if required files already exist
		$files = array('sites_json' => 'sites.json', 'index_config' => 'index.config.php');		
		$filesCheck = array();
		$filesPath = $config->paths->root;
		foreach ($files as $file) {if(is_file($filesPath . $file)) $filesCheck[] = $file;}
		$filesExist = count($filesCheck) ? true : false;

		# items exist error messages

		// required page already exists error
		if($pageExist == true){
			$failedPage = $pageCheck;
			$this->error($this->_("Cannot install Sites manager Admin page. A page named 'sites-manager' is already in use under Admin. Its title is: {$failedPage}."));
		}

		// required fields already exists error
		if($fieldsExist == true){
			$failedFields = implode(', ', $fieldsCheck);
			$this->error($this->_("Cannot install Sites manager fields. Some field names already in use. These are: {$failedFields}."));
		}

		// required templates already exists error
		if($templatesExist == true){
			$failedTemplates = implode(', ', $templatesCheck);
			$this->error($this->_("Cannot install Sites manager templates. Some template names already in use. These are: {$failedTemplates}."));
		}
		
		// required files already exists error
		if($filesExist == true){
			$failedFiles = implode(', ', $filesCheck);
			$this->error($this->_("Cannot install Sites manager files. Some file names already in use. These are: {$failedFiles}."));
		}

		# abort if errors found
		//if any of our checks returned true, we abort early
		if($pageExist || $fieldsExist || $templatesExist || $filesExist) {
			throw new WireException($this->_('Due to the above errors, Sites manager did not install. Make necessary changes and try again.'));
			//due to above errors, we stop executing install of the following 'templates', 'fields' and 'pages'
		}

		// pass on to first step of install
		// return true to OK first step of install
		return true;

	}

	/**
	 * Create several Sites manager fields.
	 *
	 * @access private
	 * @return $this->createTemplates().
	 *
	 */
	private function createFields() {

		// 2. ###### We create the fields we will need to add to our templates ######

		/*
				Prepare the array (with properties) we will use to create fields.
				We will modify some properties later for different contexts (templates).

				Additional Settings
					 *	Some fields will need additional settings.
		 */

		$fields = array(
			'settings' => array('name'=>'sites_manager_settings', 'type'=> 'FieldtypeTextarea', 'label'=>'Sites Manager: Settings', 'collapsed'=>5,),
			'files' => array('name'=>'sites_manager_files', 'type'=>'FieldtypeFile', 'label'=>'Sites Manager: Files',  'collapsed'=>2, 'entityencodedesc'=>1),
		);

		foreach ($fields as $field) {

			$f = new Field(); //  create new field object
			$f->type = $this->wire('modules')->get($field['type']); // get the fieldtype
			$f->name = $field['name'];
			$f->label = $field['label'];
			if(isset($field['collapsed'])) $f->collapsed = $field['collapsed'];
			if(isset($field['entityencodedesc'])) $f->entityEncode = $field['entityencodedesc'];

			if($f->name =='sites_manager_settings') {
				$f->rows = 10;
				$f->contentType = 0;				
			}

			if($f->name == 'sites_manager_files') {
				$f->extensions = 'zip';// needs string
				$f->maxFiles = 1;
			}
			

			$f->tags = '-sitesmanager';
			$f->save(); //

		}// end foreach fields

		// grab our newly created fields, assigning them to variables. We'll later add the fields to our templates
		$f = $this->wire('fields');

		// set some Class properties on the fly. We will use this in createTemplates()
		$this->title = $f->get('title');
		$this->settings = $f->get('sites_manager_settings');
		$this->smFiles = $f->get('sites_manager_files');

		// lets create some templates and add our fields to them
		return $this->createTemplates();

	}

	/**
	 * Create several Sites manager templates.
	 *
	 * Create templates for each sites manager parent.
	 * @see https://processwire.com/talk/topic/12130-process-module-with-certain-permission-not-showing-up/?p=112674
	 * @access private
	 * @return method $this->extraTemplateSettings().
	 *
	 */
	private function createTemplates() {

		// 3. ###### We create the templates needed by Sites manager ######

		/*
			The template properties (indices) for the $templates array below
			Leave blank for defaults
				[0]	= label => string
				[1] = useRoles => boolean (0/1)
				[2] = noChildren
				[3] = noParents

			These three template properties are added later [out of preference, rather than creating too complex a $templates array]:
			childTemplates => array;
			parentTemplates => array;
			roles => array;
		 */

		// these are field objects we set earlier. We assign them to variables for simplicity
		$title = $this->title;
		$settings = $this->settings;
		$files = $this->smFiles;

		// array for creating new templates: $k=template name; $v=template properties + fields
		$templates = array(
			// profiles
			'sites-manager-site-profiles' => array('Sites Manager: Profiles', 1, '', 1, 'fields' => array($title)),
			'sites-manager-site-profile' => array('Sites Manager: Profile', 1, 1, '', 'fields' => array($title, $settings, $files)),		
			// install sites
			'sites-manager-installed-sites' => array('Sites Manager: Installed Sites', 1, '', 1, 'fields' => array($title)),
			'sites-manager-installed-site' => array('Sites Manager: Installed Site', 1, 1, '', 'fields' => array($title, $settings)),

			// wire files
			'sites-manager-wires' => array('Sites Manager: ProcessWire Files', 1, '', 1, 'fields' => array($title)),
			'sites-manager-wire' => array('Sites Manager: ProcessWire File', 1, 1, '', 'fields' => array($title, $files)),

			// install configs
			'sites-manager-install-configurations' => array('Sites Manager: Install Configurations', 1, '', 1, 'fields' => array($title)),
			'sites-manager-install-configuration' => array('Sites Manager: Install Configuration', 1, 1, '', 'fields' => array($title, $settings)),

		);

		//  create new fieldgroups and templates and add fields
		foreach ($templates as $k => $v) {

			// new fieldgroup
			$fg = new Fieldgroup();
			$fg->name = $k;

			// we loop through the fields array in each template array and add them to the fieldgroup
			foreach ($v['fields'] as $field) $fg->add($field);

			$fg->save();

			// create a new template to use with this fieldgroup
			$t = new Template();
			$t->name = $k;
			$t->fieldgroup = $fg; // add the fieldgroup
			// add template settings we need
			$t->label = $v[0];
			$t->useRoles = $v[1];
			$t->noChildren = $v[2];
			$t->noParents = $v[3];
			$t->tags = '-sitesmanager';// tag our templates for grouping in admin using the tag set by the user in final install

			// save new template with fields and settings now added
			$t->save();

		}// end templates foreach

		return $this->extraTemplateSettings();		

	}


	/**
	 * Add extra settings from some template.
	 *
	 * @access private
	 * @return method $this->createPages().
	 *
	 */
	private function extraTemplateSettings() {

		// 3. ###### extra settings from some templates ######
		
		$templates = $this->wire('templates');

		// prepare arrays for some templates' childTemplates AND parentTemplates

		// childTemplates: key = template name; value = allowed child templates
		$childTemplates = array('sites-manager-site-profiles' => 'sites-manager-site-profile','sites-manager-installed-sites' => 'sites-manager-installed-site','sites-manager-wires' => 'sites-manager-wire','sites-manager-install-configurations' => 'sites-manager-install-configuration');

		// add allowed child templates
		foreach ($childTemplates as $templateName => $childTemplateName) {
			$t = $templates->get($templateName);
			$t->childTemplates = array($templates->get($childTemplateName)->id);// needs to be added as array of template IDs
			$t->save();// save the template
		}
		// parentTemplates: key = template name; value = allowed parent templates
		$parentTemplates = array('sites-manager-site-profile' => 'sites-manager-site-profiles','sites-manager-installed-site' => 'sites-manager-installed-sites','sites-manager-wire' => 'sites-manager-wires','sites-manager-install-configuration' => 'sites-manager-install-configurations','sites-manager-site-profiles' => 'admin','sites-manager-installed-sites' => 'admin','sites-manager-wires' => 'admin','sites-manager-install-configurations' => 'admin');

		// add allowed parent templates
		foreach ($parentTemplates as $templateName => $parentTemplateName) {
					$t = $templates->get($templateName);
					$t->parentTemplates = array($templates->get($parentTemplateName)->id);// needs to be added as array of template IDs
					$t->save();// save the template
		}

		return $this->createPages();		

	}

	/**
	 * Create Sites manager pages.
	 *
	 * @access private
	 * @return method $this->copySitesFiles().
	 * 
	 */
	private function createPages() {
		
		$a = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
		$parent = $a->child('name='.self::PAGE_NAME);

		$sitesManagerPages = array(
			'sites-manager-site-profiles' =>  'Sites Manager: Profiles',
			'sites-manager-installed-sites' =>  'Sites Manager: Installed Sites',
			'sites-manager-wires' =>  'Sites Manager: ProcessWire Files',
			'sites-manager-install-configurations' =>  'Sites Manager: Install Configurations',
		);

		// create the child pages of 'Sites manager': These will be the parent pages of 'MS Profile' and 'MS Site' pages
		foreach ($sitesManagerPages as $templateName => $title) {
			$p = new Page();
			$p->template =  $this->wire('templates')->get($templateName);
			$p->parent = $parent;
			$p->title = $title;
			$p->addStatus(Page::statusHidden);// @note: saving as hidden; we don't want to show in AdminThemeReno side menu
			$p->save();
		}

		return $this->copySitesFiles();

	}

	/**
	 * Copy required module files to ProcessWire root folder.
	 *
	 * @access private
	 * @return method $this->saveModuleConfigs().
	 * 
	 */
	private function copySitesFiles() {	
		
		$sitesFiles = array('sites_json' => 'sites.json', 'index_config' => 'index.config.txt');
		
		$sourcePath = dirname(__FILE__) . '/files/';
		$destinationPath = wire('config')->paths->root;
		$renamedFile = 'index.config.php';
		
		foreach ($sitesFiles as $key => $filename) {			
			$sourceFile = $sourcePath . $filename;
			$destinationFile = $destinationPath . ($key == 'index_config' ? $renamedFile : $filename);
			if(is_file($destinationFile)) continue;		
			copy($sourceFile, $destinationFile);
		}

		return $this->saveModuleConfigs();
	
	}

	/**
	 * 	Save ProcessMultSites module configurations data.
	 *
	 *	@access private
	 *
	 */
	private function saveModuleConfigs() {
		
		$modules = $this->wire('modules');

		$data = $modules->getModuleConfigData(get_parent_class($this));

		// we add sitesmanagerFullyInstalled = 1 to finalConfig
		$data['sitesmanagerFullyInstalled'] = 1;

		// get ProcessSitesManager class
		$pms = $modules->get(get_parent_class($this));

		// save to ProcessSitesManager config data
		$modules->saveModuleConfigData($pms, $data);

		$sucessMessage = $this->_('Sites Manager Module Successfully Installed. Fields, Templates, Files and Pages created');
		// if we made it here return success message!
		$this->message($sucessMessage);
		// redirect to landing page (reload)
		$this->wire('session')->redirect($this->wire('page')->url);

	}



	

}