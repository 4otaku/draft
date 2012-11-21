<?php

class Module_Ajax_User extends Module_Ajax_Abstract
{
	public function __construct($url) {
		parent::__construct($url);

		if (!empty($_FILES)) {
			$this->headers = array('Content-type' => 'text/html');
		}
	}

	protected function do_upload ($dev_null) {
		if (!empty($_FILES)) {
			$file = current(($_FILES));
			$file = $file['tmp_name'];
		} else {
			$file = file_get_contents('php://input');
		}

		$worker = new Transform_Upload_Avatar($file, 'temp.jpg');

		try {
			$data = $worker->process_file();
			$data['success'] = true;
		} catch (Error_Upload $e) {
			$data = array('error' => $e->getCode());
			$data['success'] = false;
		}

		return $data;
	}

	protected function do_register ($data) {
		if (!preg_match('/.{6}/ui', $data['password'])) {
			return array('error' => 'password_short', 'success' => false);
		}

		$cookie = md5(microtime());
		$password = $this->encode_password($data['password']);
		$login = preg_replace('/[^a-zа-яё_\s\d]/ui', '', $data['login']);
		$avatar = preg_replace('/[^a-z\d]/ui', '', $data['avatar']);

		if (!preg_match('/.{4}/ui', $login)) {
			return array('error' => 'login_short', 'success' => false);
		}

		if (preg_match('/.{21}/ui', $login)) {
			return array('error' => 'login_long', 'success' => false);
		}

		if (Database::get_count('user', 'login = ?', $login)) {
			return array('error' => 'login_used', 'success' => false);
		}

		Database::insert('user', array(
			'login' => $login,
			'password' => $password,
			'cookie' => $cookie,
			'avatar' => $avatar,
		));

		setcookie('user', $cookie, time() + MONTH, '/');

		return array('success' => true);
	}

	protected function do_login ($data) {
		$password = $this->encode_password($data['password']);
		$login = preg_replace('/[^a-zа-яё_\s\d]/ui', '', $data['login']);

		if (!Database::get_count('user', 'login = ?', $login)) {
			return array('error' => 'login_not_exist', 'success' => false);
		}

		$cookie = Database::get_field('user', 'cookie',
			'login = ? and password = ?', array($login, $password));

		if (empty($cookie)) {
			return array('error' => 'password_incorrect', 'success' => false);
		}

		setcookie('user', $cookie, time() + MONTH, '/');

		return array('success' => true);
	}

	protected function encode_password ($password) {
		return substr(hash('sha512',
			$password . Config::get('user', 'salt')), 0, 32);
	}
}