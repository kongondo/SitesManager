<?php

/**
* Sites Manager: Cleanup
*
* This file forms part of the Sites Manager Suite.
* Utility to remove Sites Manager components pre-module uninstall.
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

class SitesManagerCleanup extends ProcessSitesManager {

	// whether to remove these files.
	private $removeIndexConfigFile = null;
	private $removeSitesJSONFile = null;

	/**
	 * 	Prepare clean-up.
	 *
	 * @access public
	 * @param object $post The cleanup form to process.
	 * @return method cleanUpPages().
	 * 
	 */
	public function cleanUp($post) {
		$cleanupBtn = $post->sm_cleanup_btn;
		$this->removeIndexConfigFile = (int) $post->sm_index_config;
		$this->removeSitesJSONFile = (int) $post->sm_sites_json;
		// was the right button pressed
		if($cleanupBtn) return $this->cleanUpPages();
	}

	/**
	 * 	Delete sites manager pages.
	 *
	 * @access private
	 * @return method cleanUpTemplates().
	 *
	 */
	private function cleanUpPages() {
		$pages = $this->wire('pages');
		// grab the two parent pages
		$pagesTemplates = array('sites-manager-installed-sites', 'sites-manager-site-profiles', 'sites-manager-wires', 'sites-manager-install-configurations');
		foreach ($pagesTemplates as $templateName) {
			$p = $pages->get('parent.name=sites-manager,template='.$templateName);
			// recursively delete the pages - i.e., including their children
			if ($p->id) $pages->delete($p, true);
		}

		// also delete any pages that may have been left in the trash
		foreach ($pages->find('template=sites-manager-installed-site|sites-manager-site-profile|sites-manager-wire|sites-manager-install-configuration, status>=' . Page::statusTrash) as $p) $p->delete();
		
		return $this->cleanUpTemplates();

	}

	/**
	 * 	Delete sites manager templates.
	 *
	 * @access private
	 * @return method cleanUpFields().
	 * 
	 */
	private function cleanUpTemplates() {

		$templates = $this->wire('templates');
		$templatesArray = array('sites-manager-installed-sites','sites-manager-installed-site','sites-manager-site-profiles','sites-manager-site-profile','sites-manager-wires','sites-manager-wire','sites-manager-install-configurations','sites-manager-install-configuration');

		// delete each found template one by one
		foreach ($templatesArray as $tpl) {
			$t = $templates->get($tpl);
			if ($t->id) {
				$templates->delete($t);
				// delete the associated fieldgroups
				$this->wire('fieldgroups')->delete($t->fieldgroup);
			}
		}

		return $this->cleanUpFields();

	}

	/**
	 * 	Delete sites manager fields.
	 *
	 * @access private
	 * @return method cleanUpFiles().
	 * 
	 */
	private function cleanUpFields() {
		$fields = $this->wire('fields');
		// array of sites manager fields. We'll use this to delete each
		$fieldsArray = array('sites_manager_files', 'sites_manager_settings');
		// delete each found field
		foreach ($fieldsArray as $fld) {
			$f = $fields->get($fld);
			if($f->id) $fields->delete($f);
		}
		return $this->cleanUpFiles();
	}

	/**
	 * 	Delete sites manager files as per instructions.
	 *
	 * @access private
	 * @return method saveModuleConfigs().
	 *
	 */
	private function cleanUpFiles() {

		$config = $this->wire('config');
		$this->deleteFiles = false;

		// if deleting sites.json or index.config.php
		if ($this->removeIndexConfigFile || $this->removeSitesJSONFile) {

			$siteFiles = array('indexConfig' => 'index.config.php', 'sitesJSON' => 'sites.json');
			if(!$this->removeIndexConfigFile) unset($siteFiles['indexConfig']);
			if(!$this->removeSitesJSONFile) unset($siteFiles['sitesJSON']);

			$sourcepath = $config->paths->root;

			foreach ($siteFiles as $key => $siteFile) {
				// delete the file if found
				if(is_file($sourcepath . $siteFile)) unlink($sourcepath . $siteFile);
				$this->deleteFiles = true;
			}

		}
		
		return $this->saveModuleConfigs();
	
	}

	/**
	 * 	Reset ProcessSitesManager module configurations.
	 *
	 *	@access private
	 *
	 */
	private function saveModuleConfigs() {
		
		$modules = $this->wire('modules');

		// reset to original/default state ProcessSitesManager configs!
		$reset = parent::configDefaults();

		// get ProcessSitesManager class
		$pms = $modules->get(get_parent_class($this));

		// save to ProcessSitesManager config data (reset)
		$modules->saveModuleConfigData($pms, $reset);

		// true if files were deleted = only true if checkboxex were selected
		$files = $this->deleteFiles == true ? $this->_(' Files') . ',' : '';
		$cleanupMessage =  sprintf(__('Sites Manager Components successfully removed. Fields, Templates %s and Pages deleted.'), $files);

		// if we made it here return success message!
		$this->message($cleanupMessage);
		// redirect to landing page// we want the page to reload so that user can now see Sites Manager execute screen...
		// ...telling them to either uninstall or re-install the module
		$this->wire('session')->redirect($this->wire('page')->url);

	}


}


