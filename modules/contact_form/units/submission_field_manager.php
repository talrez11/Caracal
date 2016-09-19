<?php

class ContactForm_SubmissionFieldManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_submission_fields');

		$this->add_property('id', 'int');
		$this->add_property('submission', 'int');
		$this->add_property('field', 'int');
		$this->add_property('value', 'text');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
