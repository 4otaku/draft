<?php

class Module_Ajax extends Module_Abstract
{
	protected $headers = array('Content-type' => 'application/json');

	public function __construct($url) {
		parent::__construct($url);

		if (!empty($_FILES)) {
			$this->headers = array('Content-type' => 'text/html');
		}
	}

	public function send_output() {
		echo trim(json_encode($this->get_data()));
	}

	protected function get_data() {
		$method = 'do_' . rtrim($this->url[2], '?');

		$get = $this->clean_globals($_GET, array());
		$post = $this->clean_globals($_POST, array());

		if (method_exists($this, $method)) {
			return $this->$method(array_merge($post, $get));
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
			'id_draft' => $get['room'],
			'id_user' => User::get('id'),
			'time' => NULL
		), array('room', 'user'));

		if (empty($get['first_load'])) {
			$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));
			$messages = Database::join('user', 'u.id = m.id_user')->order('m.time', 'ASC')->
				get_table('message', 'm.id, m.id_user, m.text, unix_timestamp(m.time) as time, u.login',
					'm.time > ? and m.id_draft = ?', array($time, $get['room']));
		} else {
			$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'firsttime'));
			$messages = Database::join('user', 'u.id = m.id_user')->limit(50)->order('m.time')->
				get_table('message', 'm.id, m.id_user, m.text, unix_timestamp(m.time) as time, u.login',
					'm.time > ? and m.id_draft = ?', array($time, $get['room']));
			$messages = array_reverse($messages);
		}

		$data = array(
			'success' => true,
			'presense' => Database::join('user', 'u.id = p.id_user')->
				get_table('presense', 'u.id, u.login', 'p.time > ? and id_draft = ?',
				array($time, $get['room'])),
			'message' => $messages,
			'last_draft_change' => strtotime(Database::order('update')
				->get_field('draft', 'update'))
		);

		return $data;
	}

	protected function do_add_message ($get) {

		$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));

		if (!isset($get['room']) || !is_numeric($get['room']) ||
			!isset($get['text']) || preg_match('/<>&\n\r/', $get['text'])
			|| !User::get('id') || !Database::get_count('presense',
				'time > ? and id_user = ? and id_draft = ?',
				array($time, User::get('id'), $get['room']))) {

			return array('success' => false);
		}

		Database::insert('message', array(
			'id_draft' => $get['room'],
			'id_user' => User::get('id'),
			'text' => $get['text']
		));

		return array(
			'success' => true,
			'id' => Database::last_id()
		);
	}

	protected function do_add_draft ($get) {
		if (!isset($get['pick_time']) || !is_numeric($get['pick_time']) ||
			!isset($get['pause_time']) || !is_numeric($get['pause_time']) ||
			!isset($get['set']) || !is_array($get['set']) || !User::get('id')) {

			return array('success' => false);
		}

		if (!empty($get['start'])) {
			$utc = $get['utc'] + 240;

			$start = date('Y-m-d G:i:s', strtotime($get['start']) + $utc * 60);
		} else {
			$start = '';
		}

		$sets = array();
		foreach ($get['set'] as $set) {
			if (preg_match('/[^-\d\.a-z]/ui', $set)) {
				continue;
			}

			$set = Database::get_full_row('set', 'id = ?', $set);

			if (empty($set)) {
				continue;
			}

			$sets[] = $set;

			if (!$set['grabbed']) {
				Grabber::get_set($set['id']);
			}
		}

		Database::begin();

		Database::insert('draft', array(
			'id_user' => User::get('id'),
			'pick_time' => $get['pick_time'],
			'pause_time' => $get['pause_time'],
			'start' => $start
		));

		$id_draft = Database::last_id();

		$order = 0;
		foreach ($sets as $set) {
			Database::insert('draft_set', array(
				'id_draft' => $id_draft,
				'order' => ++$order,
				'id_set' => $set['id']
			));
		}

		Database::commit();

		return array('success' => true);
	}

	protected function do_get_draft ($get) {
		if (!User::get('id')) {
			return array('success' => false);
		}

		$data = Database::join('draft_set', 'ds.id_draft = d.id')
			->join('user', 'u.id = d.id_user')
			->join('draft_user', 'd.id = du.id_draft and du.signed_out = 0 and du.id_user = ' . User::get('id'))
			->join('set', 'ds.id_set = s.id')->group('d.id')
			->get_table('draft', array('d.id, d.id_user, d.state, u.login, d.pick_time, d.update,
				d.pause_time', 'd.start', 'group_concat(s.name) as booster', 'du.id_user as presense'),
				'd.state != ? and d.update > ?', array(4, date('Y-m-d G:i:s', time() - 864000)));

		$date_missed = time() - 7200;
		foreach ($data as $key => $item) {
			if ($item['state'] > 0 && empty($item['presense'])
				&& strtotime($item['update']) < $date_missed) {

				unset($data[$key]);
				continue;
			}
			if (empty($item['start']) || $item['start'] == '0000-00-00 00:00:00') {
				unset($data[$key]['start']);
			} else {
				$data[$key]['start'] = strtotime($item['start']);
			}
		}

		return array(
			'success' => true,
			'data' => $data
		);
	}

	protected function do_leave_draft ($get) {
		if (!isset($get['id']) || !is_numeric($get['id']) || !User::get('id') ||
			Database::get_count('draft_user', 'id_user = ? and id = ?',
				array(User::get('id'), $get['id']))) {

			return array('success' => false);
		}

		Database::update('draft_user', array('signed_out' => 1),
			'id_user = ? and id_draft = ?', array(User::get('id'), $get['id']));

		if (Database::get_count('draft_user', 'signed_out = 0 and id_draft = ?', $get['id']) < 2) {
			Database::update('draft', array('state' => 4), $get['id']);
		}

		return array('success' => true);
	}

	protected function do_delete_draft ($get) {
		if (!isset($get['id']) || !is_numeric($get['id']) || !User::get('id') ||
			!Database::get_count('draft', 'id_user = ? and id = ? and state != ?',
				array(User::get('id'), $get['id'], 4))) {

			return array('success' => false);
		}

		Database::update('draft', array('state' => 4),
			'id_user = ? and id = ?', array(User::get('id'), $get['id']));

		return array('success' => true);
	}

	protected function do_start_draft ($get) {
		if (!isset($get['id']) || !is_numeric($get['id']) ||
			!User::get('id') || preg_match('/[^,\d]/ui', $get['user']) ||
			!Database::get_count('draft', 'id_user = ? and id = ? and state = ?',
				array(User::get('id'), $get['id'], 0))) {

			return array('success' => false);
		}

		$users = array_filter(explode(',', $get['user']));
		$users[] = User::get('id');
		$users = array_unique($users);
		shuffle($users);

		Database::begin();

		Database::update('draft', array('state' => 1),
			'id_user = ? and id = ?', array(User::get('id'), $get['id']));

		$order = 0;
		foreach ($users as $user) {
			Database::insert('draft_user', array(
				'id_draft' => $get['id'],
				'id_user' => $user,
				'order' => $order
			));
			$order++;
		}

		$sets = Database::order('order', 'asc')
			->get_full_table('draft_set', 'id_draft = ?', $get['id']);

		$ids = array();
		foreach ($sets as $set) {
			$card_ids = Database::join('set_card', 'sc.id_card = c.id')
				->group('sc.rarity')->get_table('card', array('sc.rarity',
				'group_concat(c.`id`) as ids'), 'sc.id_set = ?', $set['id_set']);

			$cards = array();
			foreach ($card_ids as $group) {
				$cards[$group['rarity']] = explode(',', $group['ids']);
				shuffle($cards[$group['rarity']]);
			}

			if (!isset($cards[1])) {
				Database::rollback();
				return array('success' => false);
			}

			foreach ($users as $user) {
				$mythic = mt_rand(0, 8) < 1;
				$foil = mt_rand(0, 4) < 1;
				$generate = array(1 => $foil ? 10 : 11, 2 => 0, 3 => 0, 4 => 0);

				if ($mythic && isset($cards[4])) {
					$generate[4] += 1;
				} elseif (isset($cards[3])) {
					$generate[3] += 1;
				} elseif (isset($cards[2])) {
					$generate[2] += 1;
				} else {
					$generate[1] += 1;
				}

				if (isset($cards[2])) {
					$generate[2] += 3;
				} else {
					$generate[1] += 3;
				}

				Database::insert('draft_booster', array(
					'id_draft_set' => $set['id'],
					'id_user' => $user
				));

				$id_booster = Database::last_id();

				foreach ($generate as $rarity => $number) {
					$tmp = $cards[$rarity];
					for ($i = 0; $i < $number; $i++) {
						$key = array_rand($tmp);
						$id_card = $tmp[$key];
						Database::insert('draft_booster_card', array(
							'id_draft_booster' => $id_booster,
							'id_card' => $id_card
						));
						$ids[] = $id_card;
						unset($tmp[$key]);
					}
				}

				if ($foil) {
					$foil_rarity = mt_rand(0, 120);
					if ($foil_rarity < 1 && isset($cards[4])) {
						$foil_rarity = 4;
					} elseif ($foil_rarity < 8 && isset($cards[3])) {
						$foil_rarity = 3;
					} elseif ($foil_rarity < 32 && isset($cards[2])) {
						$foil_rarity = 2;
					} else {
						$foil_rarity = 1;
					}
					$tmp = $cards[$foil_rarity];
					$id_card = $tmp[array_rand($tmp)];
					Database::insert('draft_booster_card', array(
						'id_draft_booster' => $id_booster,
						'id_card' => $id_card
					));
					$ids[] = $id_card;
				}
			}
		}

		Grabber::get_images(array_unique($ids));

		$opts = Database::get_row('draft', array('pause_time', 'pick_time'), $get['id']);
		$start = time() + $opts['pause_time'];

		foreach ($sets as $set_number => $set) {

			$set_start = $start +
				15 * $set_number * $opts['pick_time'] +
				$opts['pause_time'] * $set_number;

			if (!$set_number) {
				Database::insert('draft_step', array(
					'id_draft' => $get['id'],
					'type' => 'start',
					'time' => date('Y-m-d G:i:s', $set_start)
				));
			} else {
				Database::insert('draft_step', array(
					'id_draft' => $get['id'],
					'type' => 'look',
					'time' => date('Y-m-d G:i:s', $set_start)
				));
			}

			for ($i = 1; $i <= 15; $i++) {
				Database::insert('draft_step', array(
					'id_draft' => $get['id'],
					'type' => 'pick_' . (15 * $set_number + $i),
					'time' => date('Y-m-d G:i:s', $set_start + $i * $opts['pick_time'])
				));
			}
		}

		if (Database::get_count('draft_step', 'id_draft = ? and type = ?',
			array($get['id'], 'build'))) {
			// Какой-то процесс успел раньше нас, откатываемся.
			Database::rollback();
			return array('success' => false);
		}

		Database::insert('draft_step', array(
			'id_draft' => $get['id'],
			'type' => 'build',
			'time' => date('Y-m-d G:i:s', $start + 864000)
		));

		Database::commit();
		return array('success' => true);
	}

	protected function do_get_draft_data ($get) {
		if (!isset($get['id']) || !is_numeric($get['id']) || !User::get('id')) {
			return array('success' => false);
		}

		if (Database::get_count('draft_user', 'id_draft = ? and id_user = ? and created_deck = 1',
			array($get['id'], User::get('id')))) {

			return $this->get_duel_data($get['id'], User::get('id'));
		}

		$action = Database::order('time', 'asc')->get_full_row('draft_step',
			'id_draft = ? and time > current_timestamp', $get['id']);

		if (!empty($action)) {
			$action['time'] = strtotime($action['time']);
			if (strpos($action['type'], 'pick_') === 0) {
				$pick = str_replace('pick_', '', $action['type']);
				$set = ceil($pick / 15);
				$shift = ($pick - 1) % 15;
				$action['picked'] = Database::join('draft_booster', 'db.id_draft_set = ds.id')
					->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
					->get_table('draft_set', 'dbc.id_user',
						'ds.id_draft = ? and ds.order = ? and dbc.pick = ? and dbc.id_user > 0',
						array($get['id'], $set, $shift + ($set - 1) * 15));
			}
		}

		$forced = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->order('ds.order', 'asc')->order('dbc.pick', 'asc')->order('dbc.id_user', 'asc')
			->get_table('draft_set', array('dbc.id_card', 'dbc.id_user', 'dbc.pick', 'ds.order'),
				'ds.id_draft = ? and dbc.forced = 1', $get['id']);

		foreach ($forced as &$item) {
			if ($item['id_user'] != User::get('id')) {
				unset($item['id_card']);
			}
			$item['pick'] = $item['pick'] % 15;
		}

		return array('success' => true, 'action' => $action, 'forced' => $forced);
	}

	protected function do_get_draft_user ($get) {
		if (!isset($get['id']) || !is_numeric($get['id'])) {
			return array('success' => false);
		}
		return array('success' => true,
			'user' => Database::join('user', 'u.id = du.id_user')->
				order('du.order', 'asc')->get_table('draft_user',
				'u.id, u.login, u.avatar, du.signed_out', 'du.id_draft = ?', $get['id']));
	}

	protected function do_get_draft_card ($get) {
		if (!isset($get['id']) || !is_numeric($get['id'])) {
			return array('success' => false);
		}

		$lands = Database::get_vector('card',
			array('id', 'name', 'image', 'color'), 'id <= 5');

		$cards = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->join('card', 'c.id = dbc.id_card')->get_vector('draft_set',
				array('c.id', 'c.name', 'c.image', 'c.color'),
				'ds.id_draft = ?', $get['id']);

		$cards = $cards + $lands;
		ksort($cards);

		return array('success' => true, 'cards' => $cards);
	}

	protected function do_get_draft_pick ($get) {
		$log = array('start get pick ' . $get['id'] . ' ' . $get['number'] . ' ' . User::get('id'));
		$time = microtime(true);

		if (!isset($get['id']) || !is_numeric($get['id']) ||
			!isset($get['number']) || !is_numeric($get['number']) ||
				($get['number'] > 1 &&
					!Database::get_count('draft_step', 'id_draft = ? and type = ? and time <= ?',
						array($get['id'], 'pick_' . ($get['number'] - 1), date('Y-m-d G:i:s'))))) {

			return array('success' => false);
		}
		$log[] = 'test: ' . (microtime(true) - $time);

		$set = ceil($get['number'] / 15);
		$shift = ($get['number'] - 1) % 15;
		$order = Database::get_field('draft_user', 'order', 'id_user = ? and id_draft = ?',
			array(User::get('id'), $get['id']));
		$log[] = 'order: ' . (microtime(true) - $time);
		$max = Database::get_field('draft_user', 'max(`order`)', 'id_draft = ?', $get['id']);
		$log[] = 'max: ' . (microtime(true) - $time);

		if ($order === false || $max === false) {
			return array('success' => false);
		}

		$this->test_force_picks($get['id'], $set, $shift);
		$log[] = 'force: ' . (microtime(true) - $time);

		$order = ($order + ($max + 1) * 15 + $shift * ($set % 2 ? 1 : -1)) % ($max + 1);
		$user = Database::get_field('draft_user', 'id_user', '`order` = ? and id_draft = ?',
			array($order, $get['id']));
		$log[] = 'user: ' . (microtime(true) - $time);

		if ($user === false) {
			return array('success' => false);
		}

		$cards = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->get_table('draft_set', array('dbc.id', 'dbc.id_card'),
				'ds.id_draft = ? and ds.order = ? and dbc.id_user = ? and db.id_user = ?',
				array($get['id'], $set, 0, $user));
		$log[] = 'cards: ' . (microtime(true) - $time);
		file_put_contents(CACHE . SL . 'get_pick_' . $get['id'] . '_' . $get['number'] . '_' . User::get('id'), implode("\n", $log));

		return array('success' => true, 'cards' => $cards);
	}

	protected function test_force_picks($draft, $set, $shift) {
		if ($shift == 0) {
			return;
		}
		$log = array('test force ' . $draft . ' ' . $set . ' ' . $shift);
		$time = microtime(true);

		$test = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->group('db.id_user')->get_table('draft_set', 'count(*) as count',
				'ds.id_draft = ? and ds.order = ? and dbc.id_user = ?', array($draft, $set, 0));
		$log[] = 'test: ' . (microtime(true) - $time);

		$need_force = false;
		foreach ($test as $item) {
			if ($item['count'] > 15 - $shift) {
				$need_force = true;
				break;
			}
		}

		if (!$need_force) {
			return;
		}

		$users = Database::get_vector('draft_user', 'id_user', 'id_draft = ?', $draft);
		$log[] = 'users: ' . (microtime(true) - $time);
		$this->force_picks($users, $draft, $set, $shift);
		$log[] = 'done: ' . (microtime(true) - $time);
		file_put_contents(CACHE . SL . 'test_force_' .  $draft . '_' . $set . '_' . $shift . '_' . User::get('id'), implode("\n", $log));
	}

	protected function force_picks($users, $draft, $set, $shift) {
		$log = array('start force ' . $draft . ' ' . $set . ' ' . $shift . ' ' . count($users));
		$time = microtime(true);

		$needed = array();
		foreach ($users as $user) {
			$needed[$user] = array();
			for ($i = 0; $i < $shift; $i++) {
				$needed[$user][$i] = true;
			}
		}

		$data = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->get_full_table('draft_set', 'ds.id_draft = ? and ds.order = ?',
			array($draft, $set));
		$log[] = 'data: ' . (microtime(true) - $time);

		foreach ($data as $card) {
			if ($card['id_user'] == 0) {
				continue;
			}

			unset($needed[$card['id_user']][$card['pick'] % 15]);
		}

		$needed = array_filter($needed);
		$max = Database::get_field('draft_user', 'max(`order`)', 'id_draft = ?', $draft);
		$log[] = 'max: ' . (microtime(true) - $time);
		foreach ($needed as $user => $picks) {
			$user_data = Database::get_row('draft_user', array('order', 'force_picks'),
				'id_user = ? and id_draft = ?', array($user, $draft));
			$log[] = 'user data: ' . (microtime(true) - $time);
			Database::update('draft_user', array('force_picks' => '++'),
				'id_user = ? and id_draft = ?', array($user, $draft));
			$log[] = 'set force: ' . (microtime(true) - $time);

			foreach ($picks as $pick => $null) {
				$order = ($user_data['order'] + ($max + 1) * 15 + $pick * ($set % 2 ? 1 : -1)) % ($max + 1);
				$booster_user = Database::get_field('draft_user', 'id_user',
					'`order` = ? and id_draft = ?', array($order, $draft));
				$log[] = 'booster: ' . (microtime(true) - $time);

				$cards = Database::join('draft_booster', 'db.id_draft_set = ds.id')
					->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
					->get_vector('draft_set', array('dbc.id', 'dbc.id_draft_booster'),
						'ds.id_draft = ? and ds.order = ? and dbc.id_user = ? and db.id_user = ?',
						array($draft, $set, 0, $booster_user));
				$log[] = 'choice: ' . (microtime(true) - $time);

				$card = array_rand($cards);

				$pick = $pick + ($set - 1) * 15;
				Database::update('draft_booster_card', array(
					'id_user' => $user,
					'pick' => $pick,
					'forced' => 1
				), 'id = ? and id_user = 0 and not exists
					(select 1 from (select * from `draft_booster_card` where id_draft_booster = ?) as t
					where t.pick = ? and t.id_user > 0)',
				array($card, $cards[$card], $pick));
				$log[] = 'set: ' . (microtime(true) - $time);
			}
		}

		file_put_contents(CACHE . SL . 'force_' .  $draft . '_' . $set . '_' . $shift . '_' . count($users) . '_' . User::get('id'), implode("\n", $log));
	}

	protected function do_draft_pick($get) {
		$log = array('start do pick ' . $get['id'] . ' ' . $get['number'] . ' ' . User::get('id'));
		$time = microtime(true);

		if (!isset($get['id']) || !is_numeric($get['id']) ||
			!isset($get['number']) || !is_numeric($get['number']) ||
			!isset($get['card']) || !is_numeric($get['card']) ||
			!User::get('id')
		) {

			return array('success' => false);
		}
		$log[] = 'test: ' . (microtime(true) - $time);

		$user = User::get('id');
		$draft = $get['id'];
		$set = ceil($get['number'] / 15);
		$shift = ($get['number'] - 1) % 15;
		$card = $get['card'];

		$user_data = Database::get_row('draft_user', array('order', 'force_picks'),
			'id_user = ? and id_draft = ?', array($user, $draft));
		$log[] = 'user: ' . (microtime(true) - $time);
		$max = Database::get_field('draft_user', 'max(`order`)', 'id_draft = ?', $draft);
		$log[] = 'max: ' . (microtime(true) - $time);
		$order = ($user_data['order'] + ($max + 1) * 15 + $shift * ($set % 2 ? 1 : -1)) % ($max + 1);
		$user_booster = Database::get_field('draft_user', 'id_user', '`order` = ? and id_draft = ?',
			array($order, $draft));
		$log[] = 'booster: ' . (microtime(true) - $time);
		$id_booster = Database::join('draft_set', 'ds.id = db.id_draft_set')->
			get_field('draft_booster', 'db.`id`', 'ds.`order` = ? and ds.id_draft = ? and db.id_user = ?',
			array($set, $draft, $user_booster));
		$log[] = 'id_booster: ' . (microtime(true) - $time);

		$pick = $shift + ($set - 1) * 15;
		Database::update('draft_booster_card', array(
			'id_user' => $user,
			'pick' => $pick,
			'forced' => 0
		), 'id = ? and id_user = 0 and not exists
			(select 1 from (select * from `draft_booster_card` where id_draft_booster = ?) as t
			where t.pick = ? and t.id_user > 0)',
		array($card, $id_booster, $shift));
		$log[] = 'do pick: ' . (microtime(true) - $time);

		$success = Database::count_affected() > 0;

		if ($user_data['force_picks']) {
			Database::update('draft_user', array('force_picks' => 0),
				'id_user = ? and id_draft = ?', array($user, $draft));
		}

		if ($success) {
			$picked_count = Database::join('draft_booster', 'db.id_draft_set = ds.id')
				->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
				->get_count('draft_set', 'ds.id_draft = ? and ds.order = ? and dbc.pick = ? and dbc.id_user > 0',
					array($draft, $set, $pick));
			$log[] = 'pciked count: ' . (microtime(true) - $time);
			$force_users = Database::get_vector('draft_user', 'id_user',
				'id_draft = ? and force_picks > ?', array($draft, 1));
			$log[] = 'force users: ' . (microtime(true) - $time);

			if (count($force_users) + $picked_count >=
				Database::get_count('draft_user', 'id_draft = ?', $draft)) {

				if (!empty($force_users)) {
					$this->force_picks($force_users, $draft, $set, $shift);
					$log[] = 'force: ' . (microtime(true) - $time);
				}

				$log[] = 'last tested: ' . (microtime(true) - $time);
				$this->shift_draft_steps($draft, $get['number']);
				$log[] = 'shift performed: ' . (microtime(true) - $time);
			}
		}

		file_put_contents(CACHE . SL . 'do_pick_' . $get['id'] . '_' . $get['number'] . '_' . User::get('id'), implode("\n", $log));
		return array('success' => $success);
	}

	protected function do_get_draft_deck($get) {
		if (!isset($get['id']) || !is_numeric($get['id']) || !User::get('id')) {
			return array('success' => false);
		}

		$user = User::get('id');
		$draft = $get['id'];

		if (!empty($get['add_land'])) {
			$count = Database::join('draft_booster', 'db.id_draft_set = ds.id')
				->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
				->get_count('draft_set', 'ds.id_draft = ? and dbc.id_user = ?', array($draft, $user));

			if ($count < 100) {

				$id_booster = Database::join('draft_booster', 'db.id_draft_set = ds.id')
					->get_field('draft_set', 'db.id', 'ds.id_draft = ?', $draft);

				$pick = 100;
				$insert = array();
				for ($id_card = 1; $id_card <=5; $id_card++) {
					for ($j = 1; $j <=100; $j++) {
						$pick++;

						$insert[] = array(
							'id_draft_booster' => $id_booster,
							'id_card' => $id_card,
							'id_user' => $user,
							'pick' => $pick
						);
					}
				}

				Database::bulk_insert('draft_booster_card', $insert, true);
			}
		}

		$data = Database::group('dbc.id_card')->join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->get_table('draft_set', array('dbc.id_card', 'count(*) as count'),
				'ds.id_draft = ? and dbc.id_user = ?', array($draft, $user));

		return array('success' => true, 'cards' => $data);
	}

	protected function do_set_draft_deck($get) {
		if (!isset($get['id']) || !is_numeric($get['id']) ||
			!isset($get['c']) || !is_array($get['c']) ||
			count($get['c']) < 40 || !User::get('id')) {

			return array('success' => false);
		}

		$user = User::get('id');
		$draft = $get['id'];

		if (!Database::get_count('draft_user', 'id_draft = ? and id_user = ? and created_deck = 0',
			array($draft, $user))) {

			return array('success' => false);
		}

		Database::begin();

		foreach ($get['c'] as $card) {
			if (!is_numeric($card)) {
				Database::rollback();
				return array('success' => false);
			}

			$id = Database::join('draft_booster', 'db.id_draft_set = ds.id')
				->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
				->get_field('draft_set', 'dbc.id',
					'ds.id_draft = ? and dbc.id_user = ? and dbc.id_card = ? and deck = 0',
					array($draft, $user, $card));

			if (!$id) {
				Database::rollback();
				return array('success' => false);
			}

			Database::update('draft_booster_card', array('deck' => 1), $id);
		}

		Database::update('draft_user', array('created_deck' => 1),
			'id_draft = ? and id_user = ?', array($draft, $user));

		Database::commit();
		return array('success' => true);
	}

	protected function do_add_note($data) {
		if (!isset($data['room']) || !is_numeric($data['room']) ||
			empty($data['text']) || !User::get('id')) {

			return array('success' => false);
		}

		$user = User::get('id');
		$room = $data['room'];
		$text = $data['text'];

		if ($room > 0 && !Database::get_count('draft_user',
			'id_draft = ? and id_user = ?', array($room, $user))) {

			return array('success' => false);
		}

		$replace = array('&' => '&amp', '"' => '&quot;', '<' => '&lt;',
			'>' => '&gt;', '\\' => '&#092;', "'" => '&apos;');
		$text = str_replace(array_keys($replace), array_values($replace), $text);
		$text = trim($text);

		$lines = explode("\n", $text);
		$first_line = array_shift($lines);
		if (!$first_line) {
			return array('success' => false);
		}

		if (preg_match('/^.{200}/ui', $first_line, $result)) {
			$header = $result[0];
		} elseif (!empty($lines)) {
			$header = $first_line;
		} else {
			$header = '';
		}

		Database::insert('note', array(
			'id_draft' => $room,
			'id_user' => $user,
			'text' => $text,
			'header' => $header,
		));

		return array('success' => true);
	}

	protected function do_delete_note($data) {
		if (!isset($data['id']) || !is_numeric($data['id']) || !User::get('id')) {
			return array('success' => false);
		}

		$user = User::get('id');
		$id = $data['id'];

		Database::delete('note', 'id_user = ? and id = ?', array($user, $id));

		return array('success' => true);
	}

	protected function shift_draft_steps($id, $pick) {
		$sets = Database::get_row('draft', array('pause_time', 'pick_time'), $id);

		$start = time();

		$steps = Database::order('time', 'asc')->
			get_vector('draft_step', array('id', 'time'),
				'id_draft = ? and time > ?', array($id, date('Y-m-d G:i:s', $start)));

		if (empty($steps)) {
			return;
		}

		$step = reset($steps);
		$shift = strtotime($step) - $start;

		if ($shift < 3) {
			return;
		}

		foreach ($steps as $id => $step) {
			Database::update('draft_step', array(
				'time' => date('Y-m-d G:i:s', strtotime($step) - $shift)
			), $id);
		}
	}

	protected function get_duel_data($draft, $user) {

		$deck = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->get_table('draft_set', array('dbc.id_card', 'dbc.deck', 'dbc.sided'),
				'ds.id_draft = ? and dbc.id_user = ?', array($draft, $user));

		$users = Database::get_table('draft_user', 'id_user',
			'id_draft = ? and id_user != ? and created_deck = 1',
			array($draft, $user));

		return array('success' => true, 'deck' => $deck, 'users' => $users, 'ready' => true);
	}
}
