<?php

class Module_Ajax extends Module_Abstract
{
	protected $headers = array('Content-type' => 'application/json');

	public function send_output() {
		echo trim(json_encode($this->get_data()));
	}

	protected function get_data() {
		$method = 'do_' . $this->url[2];

		$get = $this->clean_globals($_GET, array());

		if (method_exists($this, $method)) {
			return $this->$method($get);
		}

		return array('error' => Error::INCORRECT_URL, 'success' => false);
	}

	protected function clean_globals($data, $input = array(), $iteration = 0) {
		if ($iteration > 10) {
			return $input;
		}

		foreach ($data as $k => $v) {

			if (is_array($v)) {
				$input[$k] = $this->clean_globals($data[$k], array(), $iteration + 1);
			} else {
				$v = stripslashes($v);

				$v = str_replace(chr('0'),'',$v);
				$v = str_replace("\0",'',$v);
				$v = str_replace("\x00",'',$v);
				$v = str_replace('%00','',$v);
				$v = str_replace("../","&#46;&#46;/",$v);

				$input[$k] = $v;
			}
		}

		return $input;
	}

	protected function encode_password ($password) {
		return substr(hash('sha512',
			$password . Config::get('user', 'salt')), 0, 32);
	}

	/* From here are realization functions */

	protected function do_upload ($get) {
		if (!empty($_FILES)) {
			$file = current(($_FILES));

			$file = $file['tmp_name'];
			$name = $file['name'];
		} elseif ($get['qqfile']) {

			$file = file_get_contents('php://input');
			$name = urldecode($get['qqfile']);
		} else {
			return array('error' => Error::EMPTY_FILE, 'success' => false);
		}

		$worker = new Transform_Upload_Avatar($file, $name);

		try {
			$data = $worker->process_file();
			$data['success'] = true;
		} catch (Error_Upload $e) {
			$data = array('error' => $e->getCode());
			$data['success'] = false;
		}

		return $data;
	}

	protected function do_register ($get) {
		if (!preg_match('/.{6}/ui', $get['password'])) {
			return array('error' => 'password_short', 'success' => false);
		}

		$cookie = md5(microtime());
		$password = $this->encode_password($get['password']);
		$login = preg_replace('/[^a-zа-яё_\s\d]/ui', '', $get['login']);
		$avatar = preg_replace('/[^a-z\d]/ui', '', $get['avatar']);

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

	protected function do_login ($get) {
		$password = $this->encode_password($get['password']);
		$login = preg_replace('/[^a-zа-яё_\s\d]/ui', '', $get['login']);

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

	protected function do_get_messages ($get) {

		if (!isset($get['room']) || !is_numeric($get['room']) || !User::get('id')) {
			return array('success' => false);
		}

		Database::replace('presense', array(
			'id_room' => $get['room'],
			'id_user' => User::get('id'),
			'time' => NULL
		), array('room', 'user'));

		$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));
		$message_time = empty($get['first_load']) ? $time :
			date('Y-m-d G:i:s', time() - Config::get('chat', 'firsttime'));

		return array(
			'success' => true, 2 => User::get('id'),
			'presense' => Database::join('user', 'u.id = p.id_user')->
				get_table('presense', 'u.id, u.login', 'p.time > ? and id_room = ?',
				array($time, $get['room'])),
			'message' => Database::get_table('message',
				'id, id_user, text', 'time > ? and id_room = ?',
				array($message_time, $get['room']))
		);
	}

	protected function do_add_message ($get) {

		$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));

		if (!isset($get['room']) || !is_numeric($get['room']) ||
			!isset($get['text']) || preg_match('/<>&\n\r/', $get['text'])
			|| !User::get('id') || !Database::get_count('presense',
				'time > ? and id_user = ? and id_room = ?',
				array($time, User::get('id'), $get['room']))) {

			return array('success' => false);
		}

		Database::insert('message', array(
			'id_room' => $get['room'],
			'id_user' => User::get('id'),
			'text' => $get['text']
		));

		return array(
			'success' => true,
			'id' => Database::last_id()
		);
	}
}
