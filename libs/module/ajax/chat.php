<?php

class Module_Ajax_Chat extends Module_Ajax_Abstract_Authorized
{
	protected $id;

	protected function get_base_params($data) {
		if (!isset($data['room']) || !is_numeric($data['room'])) {
			return false;
		}

		$this->id = (int) $data['room'];

		return true;
	}

	protected function do_get ($data) {

		$this->write_presense();

		if (empty($data['first_load'])) {
			$message_time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));
			$messages = Database::join('user', 'u.id = m.id_user')->order('m.time', 'ASC')->
				get_table('message', 'm.id, m.id_user, m.text, unix_timestamp(m.time) as time, u.login',
				'm.time > ? and m.id_draft = ?', array($message_time, $this->id));
		} else {
			$message_time = date('Y-m-d G:i:s', time() - Config::get('chat', 'firsttime'));
			$messages = Database::join('user', 'u.id = m.id_user')->limit(50)->order('m.time')->
				get_table('message', 'm.id, m.id_user, m.text, unix_timestamp(m.time) as time, u.login',
				'm.time > ? and m.id_draft = ?', array($message_time, $this->id));
			$messages = array_reverse($messages);
		}
		$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));

		$data = array(
			'success' => true,
			'presense' => Database::join('user', 'u.id = p.id_user')->
				get_table('presense', 'u.id, u.login', 'p.time > ? and id_draft = ?',
				array($time, $this->id)),
			'message' => $messages,
			'last_draft_change' => strtotime(Database::order('update')
				->get_field('draft', 'update'))
		);

		return $data;
	}

	protected function do_add ($data) {

		$time = date('Y-m-d G:i:s', time() - Config::get('chat', 'loadtime'));
		$presense = Database::get_count('presense',	'time > ? and id_user = ? and id_draft = ?',
			array($time, $this->user, $this->id));

		if (!isset($data['text']) || preg_match('/<>&\n\r/', $data['text']) || !$presense) {
			return array('success' => false);
		}

		Database::insert('message', array(
			'id_draft' => $this->id,
			'id_user' => $this->user,
			'text' => $data['text']
		));

		return array(
			'success' => true,
			'id' => Database::last_id()
		);
	}

	protected function write_presense() {
		Database::replace('presense', array(
			'id_draft' => $this->id,
			'id_user' => $this->user,
			'time' => NULL
		), array('room', 'user'));
	}
}
