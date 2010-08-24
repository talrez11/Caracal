<?php

/**
 * Base Module Class
 * @author MeanEYE[rcf]
 */

class Module {
	var $name;
	var $path;
	var $language;
	var $sections;
	var $file;
	var $settings;

	/**
	 * Constructor
	 *
	 * @return Module
	 */
	function __construct() {
		global $ModuleHandler;

		$this->path = dirname($this->file).'/';
		$this->name = get_class($this);
		$this->language = new LanguageHandler($this->path.'/data/language.xml');
		$this->sections = new SectionHandler($this->path.'/data/section.xml');

		$this->settings = $this->getSettings();
		$ModuleHandler->registerModule($this->file, $this);
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children=array()) {

	}

	/**
	 * Returns module mapped section file relative to module path
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 * @return string
	 */
	function getSectionFile($section, $action, $language="") {
		return $this->path.$this->sections->getFile($section, $action, $language);
	}

	/**
	 * Returns text for given module specific constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	function getLanguageConstant($constant, $language="") {
		global $LanguageHandler;

		$language_in_use = empty($language) ? $_SESSION['language'] : $language;
		$result = $this->language->getText($constant, $language_in_use);

		if (empty($result))
			$result = $LanguageHandler->getText($constant, $language_in_use);

		return $result;
	}

	/**
	 * Extracts multi-language field data and pack them in array
	 *
	 * @param string $name
	 * @param boolean $fix
	 * @return array
	 */
	function getMultilanguageField($name, $fix=true) {
		global $LanguageHandler;

		$result = array();
		$list = $LanguageHandler->getLanguages(false);

		foreach($list as $lang)
			$result[$lang] = $_REQUEST["{$name}_{$lang}"];

		return $result;
	}

	/**
	 * Event called upon module initialisation
	 */
	function onInit() {

	}
	/**
	 * Event called upon module registration
	 */
	function onRegister() {

	}

	/**
	 * Event called upon module removal
	 */
	function onDisable() {
	}

	/**
	 * Returns module defined variables
	 *
	 * @return array
	 */
	function getSettings() {
		global $db, $db_active;
		$result = array();

		if ($db_active == 1) {
			$settings = $db->get_results("SELECT `variable`, `value` FROM `system_settings` WHERE `module` = '$this->name' ORDER BY `variable` ASC");

			if ($db->num_rows > 0)
				foreach ($settings as $setting)
					$result[$setting->variable] = $setting->value;
		}
		return $result;
	}

	/**
	 * Updates or creates new variable in module settings
	 *
	 * @param string $var
	 * @param string $value
	 */
	function saveSetting($var, $value) {
		global $db;

		// TODO: Make usage of Item Manager
		$select_query = "SELECT count(`id`) FROM `system_settings` WHERE
						`module` = '{$this->name}' AND `variable` = '{$var}'";

		$update_query = "UPDATE `system_settings`
						SET `value` = '{$value}'
						WHERE `variable` = '{$var}' AND `module` = '{$this->name}'";

		$insert_query = "INSERT INTO `system_settings` (`module`,`variable`,`value`)
						VALUES ('{$this->name}', '{$var}', '{$value}')";

		$update = $db->get_var($select_query) > 0;
		$db->query( ($update) ? $update_query : $insert_query );
	}

}

?>
