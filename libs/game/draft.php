<?php

class Game_Draft extends Game_Abstract
{
	protected function insert_game_steps($sets) {
		$opts = Database::get_row('game', array('pause_time', 'pick_time'), $this->get_id());
		$start = time() + $opts['pause_time'];

		foreach ($sets as $set_number => $set) {

			$set_start = $start +
				15 * $set_number * $opts['pick_time'] +
				$opts['pause_time'] * $set_number;

			if (!$set_number) {
				Database::insert('game_step', array(
					'id_game' => $this->get_id(),
					'type' => 'start',
					'time' => date('Y-m-d G:i:s', $set_start)
				));
			} else {
				Database::insert('game_step', array(
					'id_game' => $this->get_id(),
					'type' => 'look',
					'time' => date('Y-m-d G:i:s', $set_start)
				));
			}

			for ($i = 1; $i <= 15; $i++) {
				Database::insert('game_step', array(
					'id_game' => $this->get_id(),
					'type' => 'pick_' . (15 * $set_number + $i),
					'time' => date('Y-m-d G:i:s', $set_start + $i * $opts['pick_time'])
				));
			}
		}

		parent::insert_game_steps($sets);
	}

	public function get_action() {
		$action = parent::get_action();

		if (!empty($action['type']) && $action['type'] == 'pick') {
			$pick = $action['data'];
			$set = ceil($pick / 15);
			$shift = ($pick - 1) % 15;
			$action['picked'] = Database::join('game_booster', 'gb.id_game_set = gs.id')
				->join('game_booster_card', 'gbc.id_game_booster = gb.id')
				->get_table('game_set', 'gbc.id_user',
				'gs.id_game = ? and gs.order = ? and gbc.pick = ? and gbc.id_user > 0',
				array($this->get_id(), $set, $shift + ($set - 1) * 15));
		}

		return $action;
	}

	public function get_forced($user) {
		$forced = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->order('gs.order', 'asc')->order('gbc.pick', 'asc')->order('gbc.id_user', 'asc')
			->get_table('game_set', array('gbc.id_card', 'gbc.id_user', 'gbc.pick', 'gs.order'),
			'gs.id_game = ? and gbc.forced = 1', $this->get_id());

		foreach ($forced as &$item) {
			if ($item['id_user'] != $user) {
				unset($item['id_card']);
			}
			$item['pick'] = $item['pick'] % 15;
		}

		return $forced;
	}

	public function has_pick($pick) {
		return Database::get_count('game_step', 'id_game = ? and type = ? and time <= ?',
			array($this->get_id(), 'pick_' . $pick, date('Y-m-d G:i:s'))) > 0;
	}

	public function get_pick($set, $user, $shift) {

		$order = Database::get_field('game_user', 'order', 'id_user = ? and id_game = ?',
			array($user, $this->get_id()));
		$max = Database::get_field('game_user', 'max(`order`)', 'id_game = ?', $this->get_id());

		if ($order === false || $max === false) {
			throw new Error();
		}

		$order = ($order + ($max + 1) * 15 + $shift * ($set % 2 ? 1 : -1)) % ($max + 1);
		$user = Database::get_field('game_user', 'id_user', '`order` = ? and id_game = ?',
			array($order, $this->get_id()));

		if ($user === false) {
			throw new Error();
		}

		$cards = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->get_table('game_set', array('gbc.id', 'gbc.id_card'),
			'gs.id_game = ? and gs.order = ? and gbc.id_user = ? and gb.id_user = ?',
			array($this->get_id(), $set, 0, $user));

		return $cards;
	}

	public function test_force_picks($set, $shift) {
		if ($shift == 0) {
			return;
		}

		$test = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->group('gb.id_user')->get_table('game_set', 'count(*) as count',
			'gs.id_game = ? and gs.order = ? and gbc.id_user = ?', array($this->get_id(), $set, 0));

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

		$users = Database::get_vector('game_user', 'id_user', 'id_game = ?', $this->get_id());
		$this->force_picks($users, $set, $shift);
	}

	protected function force_picks($users, $set, $shift){
		$needed = array();
		foreach ($users as $user) {
			$needed[$user] = array();
			for ($i = 0; $i < $shift; $i++) {
				$needed[$user][$i] = true;
			}
		}

		$data = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->get_full_table('game_set', 'gs.id_game = ? and gs.order = ?',
			array($this->get_id(), $set));

		foreach ($data as $card) {
			if ($card['id_user'] == 0) {
				continue;
			}

			unset($needed[$card['id_user']][$card['pick'] % 15]);
		}

		$needed = array_filter($needed);
		$max = Database::get_field('game_user', 'max(`order`)', 'id_game = ?', $this->get_id());
		foreach ($needed as $user => $picks) {
			$user_data = Database::get_row('game_user', array('order', 'force_picks'),
				'id_user = ? and id_game = ?', array($user, $this->get_id()));
			Database::update('game_user', array('force_picks' => '++'),
				'id_user = ? and id_game = ?', array($user, $this->get_id()));

			foreach ($picks as $pick => $null) {
				$order = ($user_data['order'] + ($max + 1) * 15 + $pick * ($set % 2 ? 1 : -1)) % ($max + 1);
				$booster_user = Database::get_field('game_user', 'id_user',
					'`order` = ? and id_game = ?', array($order, $this->get_id()));

				$cards = Database::join('game_booster', 'gb.id_game_set = gs.id')
					->join('game_booster_card', 'gbc.id_game_booster = gb.id')
					->get_vector('game_set', array('gbc.id', 'gbc.id_game_booster'),
					'gs.id_game = ? and gs.order = ? and gbc.id_user = ? and gb.id_user = ?',
					array($this->get_id(), $set, 0, $booster_user));

				$card = array_rand($cards);

				$pick = $pick + ($set - 1) * 15;
				Database::update('game_booster_card', array(
						'id_user' => $user,
						'pick' => $pick,
						'forced' => 1
					), 'id = ? and id_user = 0 and not exists
					(select 1 from (select * from `game_booster_card` where id_game_booster = ?) as t
					where t.pick = ? and t.id_user > 0)',
					array($card, $cards[$card], $pick));
			}
		}
	}

	public function pick($user, $card, $set, $shift) {
		$user_data = Database::get_row('game_user', array('order', 'force_picks'),
			'id_user = ? and id_game = ?', array($user, $this->get_id()));
		$max = Database::get_field('game_user', 'max(`order`)', 'id_game = ?', $this->get_id());
		$order = ($user_data['order'] + ($max + 1) * 15 + $shift * ($set % 2 ? 1 : -1)) % ($max + 1);
		$user_booster = Database::get_field('game_user', 'id_user', '`order` = ? and id_game = ?',
			array($order, $this->get_id()));
		$id_booster = Database::join('game_set', 'gs.id = gb.id_game_set')->
			get_field('game_booster', 'gb.`id`', 'gs.`order` = ? and gs.id_game = ? and gb.id_user = ?',
			array($set, $this->get_id(), $user_booster));

		$pick = $shift + ($set - 1) * 15;
		Database::update('game_booster_card', array(
				'id_user' => $user,
				'pick' => $pick,
				'forced' => 0
			), 'id = ? and id_user = 0 and not exists
			(select 1 from (select * from `game_booster_card` where id_game_booster = ?) as t
			where t.pick = ? and t.id_user > 0)',
			array($card, $id_booster, $shift));

		$success = Database::count_affected() > 0;

		if ($user_data['force_picks']) {
			Database::update('game_user', array('force_picks' => 0),
				'id_user = ? and id_game = ?', array($user, $this->get_id()));
		}

		if ($success) {
			$picked_count = Database::join('game_booster', 'gb.id_game_set = gs.id')
				->join('game_booster_card', 'gbc.id_game_booster = gb.id')
				->get_count('game_set', 'gs.id_game = ? and gs.order = ? and gbc.pick = ? and gbc.id_user > 0',
				array($this->get_id(), $set, $pick));
			$force_users = Database::get_vector('game_user', 'id_user',
				'id_game = ? and force_picks > ?', array($this->get_id(), 1));

			if (count($force_users) + $picked_count >=
				Database::get_count('game_user', 'id_game = ?', $this->get_id())) {

				if (!empty($force_users)) {
					$this->force_picks($force_users, $set, $shift);
				}

				$this->shift_game_steps();
			}
		}

		return $success;
	}

	protected function shift_game_steps() {
		$start = time();

		$steps = Database::order('time', 'asc')->
			get_vector('game_step', array('id', 'time'),
				'id_game = ? and time > ?',
				array($this->get_id(), date('Y-m-d G:i:s', $start)));

		if (empty($steps)) {
			return;
		}

		$step = reset($steps);
		$shift = strtotime($step) - $start;

		if ($shift < 3) {
			return;
		}

		foreach ($steps as $id => $step) {
			Database::update('game_step', array(
				'time' => date('Y-m-d G:i:s', strtotime($step) - $shift)
			), $id);
		}
	}
}
