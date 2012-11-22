<?php

class Module_Ajax_Draft_List extends Module_Ajax_Abstract_Authorized
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

		Database::insert('draft', array(
			'id_user' => $this->user,
			'pick_time' => isset($data['pick_time']) ? $data['pick_time'] : 0,
			'pause_time' => isset($data['pause_time']) ? $data['pause_time'] : 0,
			'type' => isset($data['type']) ? $data['type'] : 1,
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

	protected function do_get ($dev_null) {

		$data = Database::join('draft_set', 'ds.id_draft = d.id')
			->join('user', 'u.id = d.id_user')
			->join('draft_user', 'd.id = du.id_draft and du.signed_out = 0 and du.id_user = ' . $this->user)
			->join('set', 'ds.id_set = s.id')->group('d.id')
			->get_table('draft', array('d.id, d.id_user, d.state, u.login, d.pick_time, d.update,
				d.pause_time, d.start, d.type', 'group_concat(s.name) as booster', 'du.id_user as presense'),
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

	protected function do_leave ($data) {
		if (!isset($data['id']) || !is_numeric($data['id']) ||
			Database::get_count('draft_user', 'id_user = ? and id = ?',
				array($this->user, $data['id']))) {

			return array('success' => false);
		}

		Database::update('draft_user', array('signed_out' => 1),
			'id_user = ? and id_draft = ?', array($this->user, $data['id']));

		if (Database::get_count('draft_user', 'signed_out = 0 and id_draft = ?', $data['id']) < 2) {
			Database::update('draft', array('state' => 4), $data['id']);
		}

		return array('success' => true);
	}

	protected function do_delete ($data) {
		if (!isset($data['id']) || !is_numeric($data['id']) ||
			!Database::get_count('draft', 'id_user = ? and id = ? and state != ?',
				array($this->user, $data['id'], 4))) {

			return array('success' => false);
		}

		Database::update('draft', array('state' => 4),
			'id_user = ? and id = ?', array($this->user, $data['id']));

		return array('success' => true);
	}
}