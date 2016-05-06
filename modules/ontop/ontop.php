<?php

/**
 * OnTop Integration Module
 *
 * This module provides easy way to push notifications through OnTop
 * service to Android phones. It supports automatic notifications for certain
 * system events such as shop purchases, contact form submissions, etc.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;

require_once('units/manager.php');
require_once('units/handler.php');

use \Modules\OnTop\Manager as Manager;
use \Modules\OnTop\Handler as Handler;


class ontop extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// connect events
		Events::connect('shop', 'transaction-completed', 'handle_shop_transaction_complete', $this);
		Events::connect('contact_form', 'submitted', 'handle_contact_form_submit', $this);

		// register backend
		if ($section == 'backend' && ModuleHandler::is_loaded('backend')) {
			$backend = backend::getInstance();

			$ontop_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_ontop'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					window_Open(
						'ontop_applications',
						450,
						$this->getLanguageConstant('title_ontop'),
						true, true,
						backend_UrlMake($this->name, 'applications')
					),
					$level=5
				);

			$backend->addMenu($this->name, $ontop_menu);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show_applications':
					$this->tag_ApplicationList($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'add':
					$this->add_application();
					break;

				case 'edit':
					$this->edit_application();
					break;

				case 'save':
					$this->save_application();
					break;

				case 'delete':
					$this->delete_application();
					break;

				case 'delete_commit':
					$this->delete_application_commit();
					break;

				case 'test':
					$this->test_application();
					break;

				default:
					$this->show_applications();
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$sql = "
			CREATE TABLE `ontop_applications` (
				`id` int NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(100) NOT NULL,
				`uid` VARCHAR(64) NOT NULL,
				`key` VARCHAR(128) NOT NULL,
				`shop_transaction_complete` BOOLEAN NOT NULL DEFAULT '0',
				`contact_form_submit` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('ontop_applications');

		$db->drop_tables($tables);
	}

	/**
	 * Show application management window.
	 */
	private function show_applications() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'ontop_new_application', 350,
										$this->getLanguageConstant('title_add_application'),
										true, false,
										$this->name,
										'add'
									),
					);

		$template->registerTagHandler('cms:list', $this, 'tag_ApplicationList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new application to the database.
	 */
	private function add_application() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'save'),
					'cancel_action'	=> window_Close('ontop_new_application')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing application from the database.
	 */
	private function edit_application() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'id'                        => $item->id,
						'name'                      => $item->name,
						'uid'                       => $item->uid,
						'key'                       => $item->key,
						'shop_transaction_complete' => $item->shop_transaction_complete,
						'contact_form_submit'       => $item->contact_form_submit,
						'form_action'               => backend_UrlMake($this->name, 'save'),
						'cancel_action'             => window_Close('ontop_edit_application')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save changed or new application data.
	 */
	private function save_application() {
		$manager = Manager::getInstance();
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		// collect data
		$data = array(
				'name' => escape_chars($_REQUEST['name']),
				'uid' => escape_chars($_REQUEST['uid']),
				'key' => escape_chars($_REQUEST['key']),
			);

		// collect boolean parameters
		$boolean_params = array(
				'shop_transaction_complete',
				'contact_form_submit'
			);

		foreach ($boolean_params as $param)
			$data[$param] = isset($_REQUEST[$param]) &&
				($_REQUEST[$param] == 'on' || $_REQUEST[$param] == '1') ? 1 : 0;

		if (is_null($id)) {
			$window = 'ontop_new_application';
			$manager->insertData($data);

		} else {
			$window = 'ontop_edit_application';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_application_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('ontop_applications'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before removing application.
	 */
	private function delete_application() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_application_delete'),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'ontop_delete_application',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('ontop_delete_application')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform application removal.
	 */
	private function delete_application_commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_application_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('ontop_delete_application').';'.window_ReloadContent('ontop_applications')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Send test message to selected application.
	 */
	private function test_application() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::getInstance();

		$target = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		Handler::set_targets(array(array($target->uid, $target->key)));

		$numbers = sprintf('%03d-%03d', rand(0, 999), rand(0, 999));
		Handler::push($numbers, 'Test', 'Numbers');

		$template = new TemplateHandler('message.xml', $this->path.'templates/');

		$params = array(
					'message'	=> $this->getLanguageConstant('message_test')."<br><b>{$numbers}</b>",
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('ontop_test_application').';'.window_ReloadContent('ontop_applications')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle new completed transaction event.
	 *
	 * @param object $transaction
	 */
	public function handle_shop_transaction_complete($transaction) {
		if (!ModuleHandler::is_loaded('shop'))
			return;

		// prepare data
		$buyer = Transaction::get_buyer($transaction);
		$message = $this->getLanguageConstant('message_shop_transaction_complete');
		$message = str_replace(
				array('%from%', '%total%'),
				array($buyer->first_name.' '.$buyer->last_name, $transaction->total),
				$message
			);

		// get push targets
		$targets = Handler::get_targets(array('shop_transaction_complete'));
		Handler::set_targets($targets);

		// push notifications
		Handler::push($message, 'Shop', 'Transaction complete');
	}

	/**
	 * Handle successful form submission.
	 *
	 * @param array $sender
	 * @param array $recipients
	 * @param array $template
	 * @param array $data
	 */
	public function handle_contact_form_submit($sender, $recipients, $template, $data) {
		// prepare data
		$message = $this->getLanguageConstant('message_contact_form_submit')."\n";
		foreach ($data as $key => $value)
			$message .= $key.': '.$value."\n";

		// get push targets
		$targets = Handler::get_targets(array('contact_form_submit'));
		Handler::set_targets($targets);

		// push notifications
		Handler::push($message, 'Contact form', 'Submit');
	}

	/**
	 * Rendering function for application list.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ApplicationList($tag_params, $children) {
		$manager = Manager::getInstance();
		$conditions = array();

		// get application from the database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) == 0)
			return;

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');

		// parse template
		foreach ($items as $item) {
			$params = array(
				'name'                      => $item->name,
				'uid'                       => $item->uid,
				'key'                       => $item->key,
				'shop_transaction_complete' => $item->shop_transaction_complete,
				'contact_form_submit'       => $item->contact_form_submit,
				'item_change' => url_MakeHyperlink(
					$this->getLanguageConstant('change'),
					window_Open(
						'ontop_edit_application', 	// window id
						350,				// width
						$this->getLanguageConstant('title_edit_application'), // title
						false, false,
						url_Make(
							'transfer_control',
							'backend_module',
							array('module', $this->name),
							array('backend_action', 'edit'),
							array('id', $item->id)
						)
					)),
				'item_delete' => url_MakeHyperlink(
					$this->getLanguageConstant('delete'),
					window_Open(
						'ontop_delete_application', 	// window id
						400,				// width
						$this->getLanguageConstant('title_delete_application'), // title
						false, false,
						url_Make(
							'transfer_control',
							'backend_module',
							array('module', $this->name),
							array('backend_action', 'delete'),
							array('id', $item->id)
						)
					)),
				'item_test' => url_MakeHyperlink(
					$this->getLanguageConstant('test'),
					window_Open(
						'ontop_test_application', 	// window id
						400,				// width
						$this->getLanguageConstant('title_test_application'), // title
						false, false,
						url_Make(
							'transfer_control',
							'backend_module',
							array('module', $this->name),
							array('backend_action', 'test'),
							array('id', $item->id)
						)
					))
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}
}
