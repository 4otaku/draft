<?php

class Game_Masters extends Game_Abstract
{
	protected function make_booster($id, $set, $user) {
		$booster = parent::make_booster($id, $set, $user);
		$booster->set_user($user);
		$booster->set_in_deck(true);
		return $booster;
	}

	protected function insert_users($users) {
		$order = 0;
		foreach ($users as $user) {
			Database::insert('game_user', array(
				'id_game' => $this->get_id(),
				'id_user' => $user,
				'order' => $order,
				'created_deck' => 1
			));
			$order++;
		}
	}

	public function get_duel_data($user) {
		if (!$this->land_added($user)) {
			$this->add_land($user);
		}

		$data = parent::get_duel_data($user);
		$data['booster'] = Database::join('game_booster', 'gb.id_game_set = gs.id')
			->group('gb.id_user')->get_vector('game_set',
				array('gb.id_user', 'count(gb.id) as count'),
				'gs.id_game = ?', $this->get_id());

		return $data;
	}

	public function add_booster($user) {
		$set = Database::order('order')->get_row('game_set',
			array('id_set', 'order'), 'id_game = ?', $this->get_id());

		if (!$set) {
			return false;
		}

		Database::insert('game_set', array(
			'id_game' => $this->get_id(),
			'id_set' => $set['id_set'],
			'order' => $set['order'] + 1
		));

		$id = Database::last_id();

		Database::insert('game_booster', array(
			'id_game_set' => $id,
			'id_user' => $user
		));

		$booster = Database::last_id();

		$booster = $this->make_booster($booster, $set['id_set'], $user);
		$booster->set_in_deck(false);
		$ids = $booster->generate();

		Grabber::get_images(array_unique($ids));

		return true;
	}

	protected function generate_lands($booster, $user) {

		$insert = parent::generate_lands($booster, $user);
		$to_add = array(1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3);
		foreach ($insert as &$item) {
			if (!empty($to_add[$item['id_card']])) {
				$item['deck'] = 1;
				$to_add[$item['id_card']]--;
			} else {
				$item['deck'] = 0;
			}
		}

		return $insert;
	}

	public function set_deck($user, $cards) {
		$count = (int) Database::join('game_booster', 'gb.id_game_set = gs.id')
			->get_count('game_set',	'gs.id_game = ? and gb.id_user = ?',
			array($this->get_id(), $user));

		if ($count < 2) {
			throw new Error();
		}

		parent::set_deck($user, $cards);
	}
}
