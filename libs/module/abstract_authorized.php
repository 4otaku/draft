<?php

abstract class Module_Abstract_Authorized extends Module_Abstract_Html
{
	protected $redirect = false;
	protected $redirect_location = '/login/';

	public function __construct($url) {
		parent::__construct($url);

		$user = $this->get_user();
		if (empty($user)) {
			$this->create_redirect();
		} else {
			$this->user = $user;
		}
	}

	protected function create_redirect() {
		$this->headers[] = 'HTTP/1.x 302 Moved Temporarily';
		$this->headers['Location'] = $this->redirect_location;
		$this->redirect = true;
	}

	public function send_headers() {
		parent::send_headers();

		if ($this->redirect) {
			exit;
		}
		return $this;
	}

	protected function get_user() {
		return User::get();
	}

	protected function get_data() {
		$data = parent::get_data();

		$data['user'] = $this->user;

		return $data;
	}
}
