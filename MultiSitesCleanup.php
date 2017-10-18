<?php

/**
* Multi Sites: Cleanup
*
* This file forms part of the Multi Sites Suite.
* Utility to remove Multi sites components pre-module uninstall.
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

class MultiSitesCleanup extends ProcessMultiSites {

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
		$cleanupBtn = $post->ms_cleanup_btn;
		$this->removeIndexConfigFile = (int) $post->ms_index_config;
		$this->removeSitesJSONFile = (int) $post->ms_sites_json;
		// was the right button pressed
		if($cleanupBtn) return $this->cleanUpPages();
	}

	/**
	 * 	Delete multi sites pages.
	 *
	 * @access private
	 * @return method cleanUpTemplates().
	 *
	 */
	private function cleanUpPages() {
		$pages = $this->wire('pages');
		// grab the two parent pages
		$pagesTemplates = array('multi-sites-installed-sites', 'multi-sites-site-profiles', 'multi-sites-wires', 'multi-sites-install-configurations');
		foreach ($pagesTemplates as $templateName) {
			$p = $pages->get('parent.name=multi-sites,template='.$templateName);
			// recursively delete the pages - i.e., including their children
			if ($p->id) $pages->delete($p, true);
		}

		// also delete any pages that may have been left in the trash
		foreach ($pages->find('template=multi-sites-installed-site|multi-sites-site-profile|multi-sites-wire|multi-sites-install-configuration, status>=' . Page::statusTrash) as $p) $p->delete();
		
		return $this->cleanUpTemplates();

	}

	/**
	 * 	Delete multi sites templates.
	 *
	 * @access private
	 * @return method cleanUpFields().
	 * 
	 */
	private function cleanUpTemplates() {

		$templates = $this->wire('templates');
		$templatesArray = array('multi-sites-installed-sites','multi-sites-installed-site','multi-sites-site-profiles','multi-sites-site-profile','multi-sites-wires','multi-sites-wire','multi-sites-install-configurations','multi-sites-install-configuration');

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
	 * 	Delete multi sites fields.
	 *
	 * @access private
	 * @return method cleanUpFiles().
	 * 
	 */
	private function cleanUpFields() {
		$fields = $this->wire('fields');
		// array of multi sites fields. We'll use this to delete each
		$fieldsArray = array('multi_sites_files', 'multi_sites_settings');
		// delete each found field
		foreach ($fieldsArray as $fld) {
			$f = $fields->get($fld);
			if($f->id) $fields->delete($f);
		}
		return $this->cleanUpFiles();
	}

	/**
	 * 	Delete multi sites files as per instructions.
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
	 * 	Reset ProcessMultiSites module configurations.
	 *
	 *	@access private
	 *
	 */
	private function saveModuleConfigs() {
		
		$modules = $this->wire('modules');

		// reset to original/default state ProcessMultiSites configs!
		$reset = parent::configDefaults();

		// get ProcessMultiSites class
		$pms = $modules->get(get_parent_class($this));

		// save to ProcessMultiSites config data (reset)
		$modules->saveModuleConfigData($pms, $reset);

		// true if files were deleted = only true if checkboxex were selected
		$files = $this->deleteFiles == true ? $this->_(' Files') . ',' : '';
		$cleanupMessage =  sprintf(__('Multi Sites Components successfully removed. Fields, Templates %s and Pages deleted.'), $files);

		// if we made it here return success message!
		$this->message($cleanupMessage);
		// redirect to landing page// we want the page to reload so that user can now see Multi Sites execute screen...
		// ...telling them to either uninstall or re-install the module
		$this->wire('session')->redirect($this->wire('page')->url);

	}


}


