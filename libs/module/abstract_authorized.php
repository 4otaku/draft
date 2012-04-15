<?php

abstract class Module_Abstract_Authorized extends Module_Abstract_Html
{
	protected $redirect = false;

	public function __construct($url) {
		parent::__construct($url);

		$user = $this->get_user();
		if (empty($user)) {
			$this->headers[] = 'HTTP/1.x 302 Moved Temporarily';
			$this->headers['Location'] = '/login/';
			$this->redirect = true;
		} else {
			$this->user = $user;
		}
	}

	public function send_headers() {
		parent::send_headers();
		if ($this->redirect) {
			exit;
		}
	}

	protected function get_user() {
		$cookie = $_COOKIE['user'];
		if (empty($cookie)) {
			return false;
		}

		return Database::get_full_row('user', 'cookie = ?', $cookie);
	}
}
