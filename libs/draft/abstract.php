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
			foreach ($users as $user) {
				Database::insert('draft_booster', array(
					'id_draft_set' => $set['id'],
					'id_user' => $user
				));

				$booster = Database::last_id();

				$booster = $this->make_booster($booster, $set['id_set'], $user);
				$ids = array_merge($ids, $booster->generate());
			}
		}

		Grabber::get_images(array_unique($ids));
	}

	protected function make_booster($id, $set, $user) {
		return Booster::make_for_set($id, $set);
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