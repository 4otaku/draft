<?php

abstract class Game_Abstract
{
	protected $id;
	protected $data = null;

	public function __construct($id)
	{
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}

	public function get($param = false) {
		if ($this->data === null) {
			$this->data = Database::get_full_row('game', $this->id);
		}

		return $param ? $this->data[$param] : $this->data;
	}

	public function start($users) {
		// Уже запущен
		if ($this->get('state') != 0) {
			return false;
		}

		$users = array_filter(explode(',', $users));
		$users[] = $this->get('id_user');
		$users = array_unique($users);
		shuffle($users);

		Database::begin();

		try {
			$this->create_data($users);
		} catch (Error $e) {
			Database::rollback();
			return false;
		}

		Database::commit();
		return true;
	}

	protected function create_data($users) {
		Database::update('game', array('state' => 1), $this->get_id());

		$this->insert_users($users);

		$sets = Database::order('order', 'asc')
			->get_full_table('game_set', 'id_game = ?', $this->get_id());
		$ids = array();
		foreach ($sets as $set) {
			foreach ($users as $user) {
				Database::insert('game_booster', array(
					'id_game_set' => $set['id'],
					'id_user' => $user
				));

				$booster = Database::last_id();

				$booster = $this->make_booster($booster, $set['id_set'], $user);
				$ids = array_merge($ids, $booster->generate());
			}
		}

		Grabber::get_images(array_unique($ids));

		$this->insert_game_steps($sets);
	}

	protected function insert_users($users) {
		$order = 0;
		foreach ($users as $user) {
			Database::insert('game_user', array(
				'id_game' => $this->get_id(),
				'id_user' => $user,
				'order' => $order
			));
			$order++;
		}
	}

	protected function make_booster($id, $set, $user) {
		return Booster::make_for_set($set, $id);
	}

	protected function insert_game_steps($sets) {
		if (Database::get_count('game_step', 'id_game = ? and type = ?',
			array($this->get_id(), 'build'))) {
			// Какой-то процесс успел раньше нас, откатываемся.
			throw new Error();
		}

		Database::insert('game_step', array(
			'id_game' => $this->get_id(),
			'type' => 'build',
			'time' => date('Y-m-d G:i:s', time() + 864000)
		));
	}

	public function is_ready($user) {
		return Database::get_count('game_user',
			'id_game = ? and id_user = ? and created_deck = 1',
			array($this->get_id(), $user)) > 0;
	}

	public function get_action() {
		$action = Database::order('time', 'asc')->get_full_row('game_step',
			'id_game = ? and time > current_timestamp', $this->get_id());

		if (!empty($action)) {
			$action['time'] = strtotime($action['time']);
		}

		if (preg_match('/^pick_\d+$/', $action['type'])) {
			$action['data'] = (int) str_replace('pick_', '', $action['type']);
			$action['type'] = 'pick';
		} else {
			$action['data'] = false;
		}

		return $action;
	}

	public function get_forced($user) {
		return array();
	}

	public function get_duel_data($user) {
		$deck = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->get_table('game_set', array('gbc.id_card', 'gbc.deck', 'gbc.sided'),
			'gs.id_game = ? and gbc.id_user = ?', array($this->get_id(), $user));

		$users = Database::get_table('game_user', 'id_user',
			'id_game = ? and id_user != ? and created_deck = 1',
			array($this->get_id(), $user));

		return array('deck' => $deck, 'users' => $users, 'ready' => true);
	}

	public function get_users() {
		return Database::join('user', 'u.id = gu.id_user')->
			order('gu.order', 'asc')->get_table('game_user',
			'u.id, u.login, u.avatar, gu.signed_out', 'gu.id_game = ?',
			$this->get_id());
	}

	public function get_card_list() {
		return Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->join('card', 'c.id = gbc.id_card')->get_vector('game_set',
			array('c.id', 'c.name', 'c.image', 'c.color', 'c.mana_cost'),
			'gs.id_game = ?', $this->get_id());
	}

	public function has_pick($pick) {
		return false;
	}

	public function add_booster($user) {
		return false;
	}

	public function get_pick($set, $user, $shift) {
		throw new Error();
	}

	public function do_pick($user, $card, $set, $shift) {
		throw new Error();
	}

	public function test_force_picks($set, $shift) {
		return;
	}

	public function get_deck($user, $add_land = false) {
		if ($add_land && !$this->land_added($user)) {
			$this->add_land($user);
		}

		return Database::group('gbc.id_card')->join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->get_table('game_set', array('gbc.id_card', 'count(*) as count', 'sum(gbc.deck) as deck'),
			'gs.id_game = ? and gbc.id_user = ?', array($this->get_id(), $user));
	}

	public function land_added($user) {
		$count = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->get_count('game_set', 'gs.id_game = ? and gbc.id_user = ? and gbc.id_card = 1',
			array($this->get_id(), $user));

		return $count >= 100;
	}

	public function add_land($user) {

		$id_booster = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->get_field('game_set', 'gb.id', 'gs.id_game = ?', $this->get_id());

		$insert = $this->generate_lands($id_booster, $user);

		Database::bulk_insert('game_booster_card', $insert, true);
	}

	protected function generate_lands($booster, $user) {
		$pick = 200;
		$insert = array();
		for ($id_card = 1; $id_card <=5; $id_card++) {
			for ($j = 1; $j <=100; $j++) {
				$pick++;

				$insert[] = array(
					'id_game_booster' => $booster,
					'id_card' => $id_card,
					'id_user' => $user,
					'pick' => $pick
				);
			}
		}

		return $insert;
	}

	public function set_deck($user, $cards) {
		$old_ids = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->join('game_booster_card', 'gbc.id_game_booster = gb.id')
			->get_vector('game_set', 'gbc.id',
				'gs.id_game = ? and gbc.id_user = ? and deck = 1',
				array($this->get_id(), $user));

		$old_ids = array_keys($old_ids);
		Database::update('game_booster_card', array('deck' => 0),
			Database::array_in('id', $old_ids), $old_ids);

		foreach ($cards as $card) {
			if (!is_numeric($card)) {
				throw new Error();
			}

			$id = Database::join('game_booster', 'gb.id_game_set = gs.id')
				->join('game_booster_card', 'gbc.id_game_booster = gb.id')
				->get_field('game_set', 'gbc.id',
				'gs.id_game = ? and gbc.id_user = ? and gbc.id_card = ? and deck = 0',
				array($this->get_id(), $user, $card));

			if (!$id) {
				throw new Error();
			}

			Database::update('game_booster_card', array('deck' => 1), $id);
		}

		Database::update('game_user', array('created_deck' => 1),
			'id_game = ? and id_user = ?', array($this->get_id(), $user));
	}
}
