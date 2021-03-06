<?php

class Module_Ajax_Game_List extends Module_Ajax_Abstract_Authorized
{
	protected function do_add ($data) {
		if (!isset($data['set']) || !is_array($data['set'])) {
			return array('success' => false);
		}

		if (!empty($data['start'])) {
			$utc = $data['utc'] + 240;

			$start = date('Y-m-d G:i:s', strtotime($data['start']) + $utc * 60);
		} else {
			$start = '';
		}

		$sets = array();
		foreach ($data['set'] as $set) {
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

		Database::insert('game', array(
			'id_user' => $this->user,
			'pick_time' => isset($data['pick_time']) ? $data['pick_time'] : 0,
			'pause_time' => isset($data['pause_time']) ? $data['pause_time'] : 0,
			'type' => isset($data['type']) ? $data['type'] : 1,
			'start' => $start
		));

		$id_game = Database::last_id();

		$order = 0;
		foreach ($sets as $set) {
			Database::insert('game_set', array(
				'id_game' => $id_game,
				'order' => ++$order,
				'id_set' => $set['id']
			));
		}

		Database::commit();

		return array('success' => true);
	}

	protected function do_get ($dev_null) {

		$data = Database::join('game_set', 'gs.id_game = g.id')
			->join('user', 'u.id = g.id_user')
			->join('game_user', 'g.id = gu.id_game and gu.signed_out = 0 and gu.id_user = ' . $this->user)
			->join('set', 'gs.id_set = s.id')->group('g.id')
			->get_table('game', array('g.id, g.id_user, g.state, u.login, g.pick_time, g.update,
				g.pause_time, g.start, g.type', 'group_concat(s.name) as booster', 'gu.id_user as presense'),
			'g.state != ? and g.update > ?', array(4, date('Y-m-d G:i:s', time() - 86400)));

		$date_missed = time() - 10800;
		$ids = array();
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
			$ids[] = $item['id'];
		}

		$count = Database::group('id_game')->get_vector('game_user',
			array('id_game', 'count(`id_user`)'), Database::array_in('id_game', $ids), $ids);
		foreach ($data as $key => $item) {
			$data[$key]['user_count'] = isset($count[$item['id']]) ?
				$count[$item['id']] : 0;
		}

		return array(
			'success' => true,
			'data' => $data
		);
	}

	protected function do_leave ($data) {
		if (!isset($data['id']) || !is_numeric($data['id']) ||
			Database::get_count('game_user', 'id_user = ? and id = ?',
				array($this->user, $data['id']))) {

			return array('success' => false);
		}

		Database::update('game_user', array('signed_out' => 1),
			'id_user = ? and id_game = ?', array($this->user, $data['id']));

		if (Database::get_count('game_user', 'signed_out = 0 and id_game = ?', $data['id']) < 2) {
			Database::update('game', array('state' => 4), $data['id']);
		}

		return array('success' => true);
	}

	protected function do_delete ($data) {
		if (!isset($data['id']) || !is_numeric($data['id']) ||
			!Database::get_count('game', 'id_user = ? and id = ? and state != ?',
				array($this->user, $data['id'], 4))) {

			return array('success' => false);
		}

		Database::update('game', array('state' => 4),
			'id_user = ? and id = ?', array($this->user, $data['id']));

		return array('success' => true);
	}
}