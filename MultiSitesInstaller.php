<?php

/**
* Multi Sites: Installer
*
* This file forms part of the Multi Sites Suite.
* It is an install wizard for Multi Sites module and is only run once when installing the module.
* It installs 'fields', 'templates', 'files' and 'multi sites parent  pages'.
* If the above already exist (i.e., same names); this installer aborts wholesale.
*
* @author Francis Otieno (Kongondo)
* @version 0.0.2
*
* This is a Free Module.
*
* ProcessMultiSites for ProcessWire
* Copyright (C) 2017 by Francis Otieno
* This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
*
*/

class MultiSitesInstaller extends ProcessMultiSites {


	const PAGE_NAME = 'multi-sites';// this process' name

	/**
	 * Check if identical files, fields, templates and Multi Sites pages exist before install.
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
		$fields  = array('settings' => 'multi_sites_settings', 'files' => 'multi_sites_files');
		$fieldsCheck = array();
		foreach ($fields as $key => $value) {if($this->wire('fields')->get($value))	$fieldsCheck[] = $this->wire('fields')->get($value)->name;}
		$fieldsExist = count($fieldsCheck) ? true : false;

		// check if required templates already exist
		$templates = array('site_profiles' => 'multi-sites-site-profiles', 'site_profile' => 'multi-sites-site-profile', 'installed_sites' => 'multi-sites-installed-sites', 'installed_site' => 'multi-sites-installed-site','wire_files' => 'multi-sites-wires', 'wire_file' => 'multi-sites-wire','install_configurations' => 'multi-sites-install-configurations', 'install_configuration' => 'multi-sites-install-configuration');
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
			$this->error($this->_("Cannot install Multi Sites Admin page. A page named 'multi-sites' is already in use under Admin. Its title is: {$failedPage}."));
		}

		// required fields already exists error
		if($fieldsExist == true){
			$failedFields = implode(', ', $fieldsCheck);
			$this->error($this->_("Cannot install Multi Sites fields. Some field names already in use. These are: {$failedFields}."));
		}

		// required templates already exists error
		if($templatesExist == true){
			$failedTemplates = implode(', ', $templatesCheck);
			$this->error($this->_("Cannot install Multi Sites templates. Some template names already in use. These are: {$failedTemplates}."));
		}
		
		// required files already exists error
		if($filesExist == true){
			$failedFiles = implode(', ', $filesCheck);
			$this->error($this->_("Cannot install Multi Sites files. Some file names already in use. These are: {$failedFiles}."));
		}

		# abort if errors found
		//if any of our checks returned true, we abort early
		if($pageExist || $fieldsExist || $templatesExist || $filesExist) {
			throw new WireException($this->_('Due to the above errors, Multi Sites did not install. Make necessary changes and try again.'));
			//due to above errors, we stop executing install of the following 'templates', 'fields' and 'pages'
		}

		// pass on to first step of install
		// return true to OK first step of install
		return true;

	}

	/**
	 * Create several Multi Sites fields.
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
			'settings' => array('name'=>'multi_sites_settings', 'type'=> 'FieldtypeTextarea', 'label'=>'Multi Sites: Settings', 'collapsed'=>5,),
			'files' => array('name'=>'multi_sites_files', 'type'=>'FieldtypeFile', 'label'=>'Multi Sites: Files',  'collapsed'=>2, 'entityencodedesc'=>1),
		);

		foreach ($fields as $field) {

			$f = new Field(); //  create new field object
			$f->type = $this->wire('modules')->get($field['type']); // get the fieldtype
			$f->name = $field['name'];
			$f->label = $field['label'];
			if(isset($field['collapsed'])) $f->collapsed = $field['collapsed'];
			if(isset($field['entityencodedesc'])) $f->entityEncode = $field['entityencodedesc'];

			if($f->name =='multi_sites_settings') {
				$f->rows = 10;
				$f->contentType = 0;				
			}

			if($f->name == 'multi_sites_files') {
				$f->extensions = 'zip';// needs string
				$f->maxFiles = 1;
			}
			

			$f->tags = '-multisites';
			$f->save(); //

		}// end foreach fields

		// grab our newly created fields, assigning them to variables. We'll later add the fields to our templates
		$f = $this->wire('fields');

		// set some Class properties on the fly. We will use this in createTemplates()
		$this->title = $f->get('title');
		$this->settings = $f->get('multi_sites_settings');
		$this->msFiles = $f->get('multi_sites_files');

		// lets create some templates and add our fields to them
		return $this->createTemplates();

	}

	/**
	 * Create several Multi Sites templates.
	 *
	 * Create templates for each multi sites parent.
	 * @see https://processwire.com/talk/topic/12130-process-module-with-certain-permission-not-showing-up/?p=112674
	 * @access private
	 * @return method $this->extraTemplateSettings().
	 *
	 */
	private function createTemplates() {

		// 3. ###### We create the templates needed by Multi Sites ######

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
		$files = $this->msFiles;

		// array for creating new templates: $k=template name; $v=template properties + fields
		$templates = array(
			// profiles
			'multi-sites-site-profiles' => array('Multi Sites: Profiles', 1, '', 1, 'fields' => array($title)),
			'multi-sites-site-profile' => array('Multi Sites: Profile', 1, 1, '', 'fields' => array($title, $settings, $files)),		
			// install sites
			'multi-sites-installed-sites' => array('Multi Sites: Installed Sites', 1, '', 1, 'fields' => array($title)),
			'multi-sites-installed-site' => array('Multi Sites: Installed Site', 1, 1, '', 'fields' => array($title, $settings)),

			// wire files
			'multi-sites-wires' => array('Multi Sites: ProcessWire Files', 1, '', 1, 'fields' => array($title)),
			'multi-sites-wire' => array('Multi Sites: ProcessWire File', 1, 1, '', 'fields' => array($title, $files)),

			// install configs
			'multi-sites-install-configurations' => array('Multi Sites: Install Configurations', 1, '', 1, 'fields' => array($title)),
			'multi-sites-install-configuration' => array('Multi Sites: Install Configuration', 1, 1, '', 'fields' => array($title, $settings)),

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
			$t->tags = '-multisites';// tag our templates for grouping in admin using the tag set by the user in final install

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
		$childTemplates = array('multi-sites-site-profiles' => 'multi-sites-site-profile','multi-sites-installed-sites' => 'multi-sites-installed-site','multi-sites-wires' => 'multi-sites-wire','multi-sites-install-configurations' => 'multi-sites-install-configuration');

		// add allowed child templates
		foreach ($childTemplates as $templateName => $childTemplateName) {
			$t = $templates->get($templateName);
			$t->childTemplates = array($templates->get($childTemplateName)->id);// needs to be added as array of template IDs
			$t->save();// save the template
		}
		// parentTemplates: key = template name; value = allowed parent templates
		$parentTemplates = array('multi-sites-site-profile' => 'multi-sites-site-profiles','multi-sites-installed-site' => 'multi-sites-installed-sites','multi-sites-wire' => 'multi-sites-wires','multi-sites-install-configuration' => 'multi-sites-install-configurations','multi-sites-site-profiles' => 'admin','multi-sites-installed-sites' => 'admin','multi-sites-wires' => 'admin','multi-sites-install-configurations' => 'admin');

		// add allowed parent templates
		foreach ($parentTemplates as $templateName => $parentTemplateName) {
					$t = $templates->get($templateName);
					$t->parentTemplates = array($templates->get($parentTemplateName)->id);// needs to be added as array of template IDs
					$t->save();// save the template
		}

		return $this->createPages();		

	}

	/**
	 * Create Multi Sites pages.
	 *
	 * @access private
	 * @return method $this->copySitesFiles().
	 * 
	 */
	private function createPages() {
		
		$a = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
		$parent = $a->child('name='.self::PAGE_NAME);

		$multiSitesPages = array(
			'multi-sites-site-profiles' =>  'Multi Sites: Profiles',
			'multi-sites-installed-sites' =>  'Multi Sites: Installed Sites',
			'multi-sites-wires' =>  'Multi Sites: ProcessWire Files',
			'multi-sites-install-configurations' =>  'Multi Sites: Install Configurations',
		);

		// create the child pages of 'Multi Sites': These will be the parent pages of 'MS Profile' and 'MS Site' pages
		foreach ($multiSitesPages as $templateName => $title) {
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

		// we add multisitesFullyInstalled = 1 to finalConfig
		$data['multisitesFullyInstalled'] = 1;

		// get ProcessMultiSites class
		$pms = $modules->get(get_parent_class($this));

		// save to ProcessMultiSites config data
		$modules->saveModuleConfigData($pms, $data);

		$sucessMessage = $this->_('Multi Sites Module Successfully Installed. Fields, Templates, files and Pages created');
		// if we made it here return success message!
		$this->message($sucessMessage);
		// redirect to landing page (reload)
		$this->wire('session')->redirect($this->wire('page')->url);

	}



	

}