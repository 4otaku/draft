<?php

class Module_Ajax_Game extends Module_Ajax_Abstract_Authorized
{
	/**
	 * @var {Game_Abstract}
	 */
	protected $game;

	protected function get_base_params($data) {
		if (!isset($data['id']) || !is_numeric($data['id'])) {
			return false;
		}

		$this->game = Game::factory((int) $data['id']);

		if (!$this->game)
		{
			return false;
		}

		return parent::get_base_params($data);
	}

	protected function do_start ($get) {
		if (preg_match('/[^,\d]/ui', $get['user']) || !$this->is_owner()) {

			return array('success' => false);
		}

		$success = $this->game->start($get['user']);
		return array('success' => $success);
	}

	protected function do_get_data ($get) {
		if ($this->game->is_ready($this->user)) {
			$data = $this->game->get_duel_data($this->user);
		} else {
			$data = array('action' => $this->game->get_action(),
				'forced' => $this->game->get_forced($this->user));
		}

		$data['success'] = true;
		return $data;
	}

	protected function do_get_user ($get) {
		$users = $this->game->get_users();

		foreach ($users as &$user) {
			$user['nickname'] = $this->latinize($user['login']);
		}

		return array('success' => true, 'user' => $users);
	}

	protected function do_get_card ($get) {
		$lands = Database::get_vector('card',
			array('id', 'name', 'image', 'color'), 'id <= 5');

		$cards = $this->game->get_card_list();

		$cards = $cards + $lands;
		ksort($cards);

		foreach ($cards as &$card) {
			$card['image'] .= '?' . filemtime(IMAGES . SL . 'small' . SL .
				$card['image']);
		}

		return array('success' => true, 'cards' => $cards);
	}

	protected function do_get_pick ($get) {
		if (!isset($get['number']) || !is_numeric($get['number']) ||
			($get['number'] > 1 && !$this->game->has_pick($get['number'] - 1))) {

			return array('success' => false);
		}

		$set = ceil($get['number'] / 15);
		$shift = ($get['number'] - 1) % 15;

		try {
			$cards = $this->game->get_pick($set, $this->user, $shift);
			$this->game->test_force_picks($set, $shift);
		} catch (Error $e) {
			return array('success' => false);
		}

		return array('success' => true, 'cards' => $cards);
	}

	protected function do_pick($get) {
		if (!isset($get['number']) || !is_numeric($get['number']) ||
			!isset($get['card']) || !is_numeric($get['card'])) {

			return array('success' => false);
		}


		$set = ceil($get['number'] / 15);
		$shift = ($get['number'] - 1) % 15;

		$success = $this->game->pick($this->user, $get['card'], $set, $shift);

		return array('success' => $success);
	}

	protected function do_get_deck($get) {
		return array('success' => true, 'cards' =>
			$this->game->get_deck($this->user, !empty($get['add_land'])));
	}

	protected function do_set_deck($get) {
		if (!isset($get['c']) || !is_array($get['c']) ||
			count($get['c']) < 40) {

			return array('success' => false, 'step' => 1);
		}

		Database::begin();
		try {
			$this->game->set_deck($this->user, $get['c']);
		} catch (Error $e) {
			Database::rollback();
		}

		Database::commit();
		return array('success' => true);
	}

	protected function do_add_booster ($get) {
		return array('success' =>
			$this->game->add_booster($this->user));
	}

	protected function is_owner() {
		return $this->game->get('id_user') == $this->user;
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
}
