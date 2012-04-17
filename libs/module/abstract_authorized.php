<?php

abstract class Module_Abstract_Authorized extends Module_Abstract_Html
{
	protected $redirect = false;
	protected $redirect_location = '/login/';

	public function __construct($url) {
		parent::__construct($url);

		$user = $this->get_user();
		if (empty($user)) {
			$this->headers[] = 'HTTP/1.x 302 Moved Temporarily';
			$this->headers['Location'] = $this->redirect_location;
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
		return $this;
	}

	protected function get_user() {
		if (!isset($_COOKIE['user'])) {
			return false;
		}
		$cookie = $_COOKIE['user'];

		return Database::get_full_row('user', 'cookie = ?', $cookie);
	}
}
