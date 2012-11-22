<?php

abstract class Draft_Abstract
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
			$this->data = Database::get_full_row('draft', $this->id);
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
		Database::update('draft', array('state' => 1), $this->get_id());

		$order = 0;
		foreach ($users as $user) {
			Database::insert('draft_user', array(
				'id_draft' => $this->get_id(),
				'id_user' => $user,
				'order' => $order
			));
			$order++;
		}

		$sets = Database::order('order', 'asc')
			->get_full_table('draft_set', 'id_draft = ?', $this->get_id());
		$ids = array();
		foreach ($sets as $set) {
			$booster = Booster::factory($set['id_set']);

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
	}
}

/*
 *




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
 */