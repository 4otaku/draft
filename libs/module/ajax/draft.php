<?php

class Module_Ajax_Draft extends Module_Ajax_Abstract_Authorized
{
	protected $id;

	protected function get_base_params($data) {
		if (!isset($data['id']) || !is_numeric($data['id'])) {
			return false;
		}

		$this->id = (int) $data['id'];

		return parent::get_base_params($data);
	}

	protected function do_start ($get) {
		if (preg_match('/[^,\d]/ui', $get['user']) ||
			!Database::get_count('draft', 'id_user = ? and id = ? and state = ?',
				array($this->user, $this->id, 0))) {

			return array('success' => false);
		}

		$users = array_filter(explode(',', $get['user']));
		$users[] = $this->user;
		$users = array_unique($users);
		shuffle($users);

		Database::begin();

		$is_sealed = (bool) Database::get_field('draft', 'is_sealed', $this->id);

		Database::update('draft', array('state' => 1), $this->id);

		$order = 0;
		foreach ($users as $user) {
			Database::insert('draft_user', array(
				'id_draft' => $this->id,
				'id_user' => $user,
				'order' => $order
			));
			$order++;
		}

		$sets = Database::order('order', 'asc')
			->get_full_table('draft_set', 'id_draft = ?', $this->id);

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
							'id_card' => $id_card,
							'id_user' => $is_sealed ? $user : 0
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

		$opts = Database::get_row('draft', array('pause_time', 'pick_time'), $this->id);
		$start = time() + $opts['pause_time'];

		if (!$is_sealed) {
			foreach ($sets as $set_number => $set) {

				$set_start = $start +
					15 * $set_number * $opts['pick_time'] +
					$opts['pause_time'] * $set_number;

				if (!$set_number) {
					Database::insert('draft_step', array(
						'id_draft' => $this->id,
						'type' => 'start',
						'time' => date('Y-m-d G:i:s', $set_start)
					));
				} else {
					Database::insert('draft_step', array(
						'id_draft' => $this->id,
						'type' => 'look',
						'time' => date('Y-m-d G:i:s', $set_start)
					));
				}

				for ($i = 1; $i <= 15; $i++) {
					Database::insert('draft_step', array(
						'id_draft' => $this->id,
						'type' => 'pick_' . (15 * $set_number + $i),
						'time' => date('Y-m-d G:i:s', $set_start + $i * $opts['pick_time'])
					));
				}
			}
		}

		if (Database::get_count('draft_step', 'id_draft = ? and type = ?',
			array($this->id, 'build'))) {
			// Какой-то процесс успел раньше нас, откатываемся.
			Database::rollback();
			return array('success' => false);
		}

		Database::insert('draft_step', array(
			'id_draft' => $this->id,
			'type' => 'build',
			'time' => date('Y-m-d G:i:s', $start + 864000)
		));

		Database::commit();
		return array('success' => true);
	}

	protected function do_get_data ($get) {
		if (Database::get_count('draft_user', 'id_draft = ? and id_user = ? and created_deck = 1',
			array($this->id, $this->user))) {

			return $this->get_duel_data();
		}

		$action = Database::order('time', 'asc')->get_full_row('draft_step',
			'id_draft = ? and time > current_timestamp', $this->id);

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
					array($this->id, $set, $shift + ($set - 1) * 15));
			}
		}

		$forced = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->order('ds.order', 'asc')->order('dbc.pick', 'asc')->order('dbc.id_user', 'asc')
			->get_table('draft_set', array('dbc.id_card', 'dbc.id_user', 'dbc.pick', 'ds.order'),
			'ds.id_draft = ? and dbc.forced = 1', $this->id);

		foreach ($forced as &$item) {
			if ($item['id_user'] != $this->user) {
				unset($item['id_card']);
			}
			$item['pick'] = $item['pick'] % 15;
		}

		return array('success' => true, 'action' => $action, 'forced' => $forced);
	}

	protected function do_get_user ($get) {
		$users = Database::join('user', 'u.id = du.id_user')->
			order('du.order', 'asc')->get_table('draft_user',
			'u.id, u.login, u.avatar, du.signed_out', 'du.id_draft = ?', $this->id);
		foreach ($users as &$user) {
			$user['nickname'] = $this->latinize($user['login']);
		}

		return array('success' => true, 'user' => $users);
	}

	protected function do_get_card ($get) {
		$lands = Database::get_vector('card',
			array('id', 'name', 'image', 'color'), 'id <= 5');

		$cards = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->join('card', 'c.id = dbc.id_card')->get_vector('draft_set',
			array('c.id', 'c.name', 'c.image', 'c.color'),
			'ds.id_draft = ?', $this->id);

		$cards = $cards + $lands;
		ksort($cards);

		return array('success' => true, 'cards' => $cards);
	}

	protected function do_get_pick ($get) {
		if (!isset($get['number']) || !is_numeric($get['number']) ||
			($get['number'] > 1 &&
				!Database::get_count('draft_step', 'id_draft = ? and type = ? and time <= ?',
					array($this->id, 'pick_' . ($get['number'] - 1), date('Y-m-d G:i:s'))))) {

			return array('success' => false);
		}

		$set = ceil($get['number'] / 15);
		$shift = ($get['number'] - 1) % 15;
		$order = Database::get_field('draft_user', 'order', 'id_user = ? and id_draft = ?',
			array($this->user, $this->id));
		$max = Database::get_field('draft_user', 'max(`order`)', 'id_draft = ?', $this->id);

		if ($order === false || $max === false) {
			return array('success' => false);
		}

		$this->test_force_picks($set, $shift);

		$order = ($order + ($max + 1) * 15 + $shift * ($set % 2 ? 1 : -1)) % ($max + 1);
		$user = Database::get_field('draft_user', 'id_user', '`order` = ? and id_draft = ?',
			array($order, $this->id));

		if ($user === false) {
			return array('success' => false);
		}

		$cards = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->get_table('draft_set', array('dbc.id', 'dbc.id_card'),
			'ds.id_draft = ? and ds.order = ? and dbc.id_user = ? and db.id_user = ?',
			array($this->id, $set, 0, $user));

		return array('success' => true, 'cards' => $cards);
	}

	protected function do_pick($get) {
		if (!isset($get['number']) || !is_numeric($get['number']) ||
			!isset($get['card']) || !is_numeric($get['card'])) {

			return array('success' => false);
		}

		$user = $this->user;
		$draft = $this->id;
		$set = ceil($get['number'] / 15);
		$shift = ($get['number'] - 1) % 15;
		$card = $get['card'];

		$user_data = Database::get_row('draft_user', array('order', 'force_picks'),
			'id_user = ? and id_draft = ?', array($user, $draft));
		$max = Database::get_field('draft_user', 'max(`order`)', 'id_draft = ?', $draft);
		$order = ($user_data['order'] + ($max + 1) * 15 + $shift * ($set % 2 ? 1 : -1)) % ($max + 1);
		$user_booster = Database::get_field('draft_user', 'id_user', '`order` = ? and id_draft = ?',
			array($order, $draft));
		$id_booster = Database::join('draft_set', 'ds.id = db.id_draft_set')->
			get_field('draft_booster', 'db.`id`', 'ds.`order` = ? and ds.id_draft = ? and db.id_user = ?',
			array($set, $draft, $user_booster));

		$pick = $shift + ($set - 1) * 15;
		Database::update('draft_booster_card', array(
				'id_user' => $user,
				'pick' => $pick,
				'forced' => 0
			), 'id = ? and id_user = 0 and not exists
			(select 1 from (select * from `draft_booster_card` where id_draft_booster = ?) as t
			where t.pick = ? and t.id_user > 0)',
			array($card, $id_booster, $shift));

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
			$force_users = Database::get_vector('draft_user', 'id_user',
				'id_draft = ? and force_picks > ?', array($draft, 1));

			if (count($force_users) + $picked_count >=
				Database::get_count('draft_user', 'id_draft = ?', $draft)) {

				if (!empty($force_users)) {
					$this->force_picks($force_users, $draft, $set, $shift);
				}

				$this->shift_draft_steps($draft);
			}
		}

		return array('success' => $success);
	}

	protected function do_get_deck($get) {
		$user = $this->user;
		$draft = $this->id;

		if (!empty($get['add_land'])) {
			$count = Database::join('draft_booster', 'db.id_draft_set = ds.id')
				->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
				->get_count('draft_set', 'ds.id_draft = ? and dbc.id_user = ?', array($draft, $user));

			if ($count <= 15 * Database::get_count('draft_set', 'id_draft = ?', $draft)) {

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

	protected function do_set_deck($get) {
		if (!isset($get['c']) || !is_array($get['c']) || count($get['c']) < 40) {
			return array('success' => false);
		}

		$user = $this->user;
		$draft = $this->id;

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

	protected function latinize ($string) {
		$table= array('а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e',
			'ж'=>'g', 'з'=>'z', 'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n',
			'о'=>'o', 'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'ы'=>'i',
			'э'=>'e', 'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ж'=>'G',
			'З'=>'Z', 'И'=>'I', 'Й'=>'Y', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O',
			'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F', 'Ы'=>'I', 'Э'=>'E',
			'ё'=>"yo", 'х'=>"h", 'ц'=>"ts", 'ч'=>"ch", 'ш'=>"sh", 'щ'=>"shch", 'ъ'=>"", 'ь'=>"",
			'ю'=>"yu", 'я'=>"ya", 'Ё'=>"YO", 'Х'=>"H", 'Ц'=>"TS", 'Ч'=>"CH", 'Ш'=>"SH", 'Щ'=>"SHCH",
			'Ъ'=>"", 'Ь'=>"", 'Ю'=>"YU", 'Я'=>"YA");
		$string = strtr($string, $table);
		return preg_replace('/[^a-z_\d\s]/ui', '_', $string);
	}

	protected function test_force_picks($set, $shift) {
		if ($shift == 0) {
			return;
		}

		$test = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->group('db.id_user')->get_table('draft_set', 'count(*) as count',
			'ds.id_draft = ? and ds.order = ? and dbc.id_user = ?', array($this->id, $set, 0));

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

		$users = Database::get_vector('draft_user', 'id_user', 'id_draft = ?', $this->id);
		$this->force_picks($users, $set, $shift);
	}

	protected function force_picks($users, $set, $shift) {
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
			array($this->id, $set));

		foreach ($data as $card) {
			if ($card['id_user'] == 0) {
				continue;
			}

			unset($needed[$card['id_user']][$card['pick'] % 15]);
		}

		$needed = array_filter($needed);
		$max = Database::get_field('draft_user', 'max(`order`)', 'id_draft = ?', $this->id);
		foreach ($needed as $user => $picks) {
			$user_data = Database::get_row('draft_user', array('order', 'force_picks'),
				'id_user = ? and id_draft = ?', array($user, $this->id));
			Database::update('draft_user', array('force_picks' => '++'),
				'id_user = ? and id_draft = ?', array($user, $this->id));

			foreach ($picks as $pick => $null) {
				$order = ($user_data['order'] + ($max + 1) * 15 + $pick * ($set % 2 ? 1 : -1)) % ($max + 1);
				$booster_user = Database::get_field('draft_user', 'id_user',
					'`order` = ? and id_draft = ?', array($order, $this->id));

				$cards = Database::join('draft_booster', 'db.id_draft_set = ds.id')
					->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
					->get_vector('draft_set', array('dbc.id', 'dbc.id_draft_booster'),
					'ds.id_draft = ? and ds.order = ? and dbc.id_user = ? and db.id_user = ?',
					array($this->id, $set, 0, $booster_user));

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
			}
		}
	}

	protected function shift_draft_steps($id) {
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

	protected function get_duel_data() {
		$deck = Database::join('draft_booster', 'db.id_draft_set = ds.id')
			->join('draft_booster_card', 'dbc.id_draft_booster = db.id')
			->get_table('draft_set', array('dbc.id_card', 'dbc.deck', 'dbc.sided'),
			'ds.id_draft = ? and dbc.id_user = ?', array($this->id, $this->user));

		$users = Database::get_table('draft_user', 'id_user',
			'id_draft = ? and id_user != ? and created_deck = 1',
			array($this->id, $this->user));

		return array('success' => true, 'deck' => $deck, 'users' => $users, 'ready' => true);
	}
}