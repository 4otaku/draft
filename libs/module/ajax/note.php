<?php

class Module_Ajax_Note extends Module_Ajax_Abstract_Authorized
{
	protected $id;

	protected function get_base_params($data) {
		if (!isset($data['room']) || !is_numeric($data['room'])) {
			return false;
		}

		$this->id = (int) $data['room'];

		return true;
	}

	protected function do_add ($data) {
		if (empty($data['text'])) {
			return array('success' => false);
		}

		$user = $this->user;
		$room = $this->id;
		$text = $data['text'];

		if ($room > 0 && !Database::get_count('draft_user',
			'id_draft = ? and id_user = ?', array($room, $user))) {

			return array('success' => false);
		}

		$replace = array('&' => '&amp', '"' => '&quot;', '<' => '&lt;',
			'>' => '&gt;', '\\' => '&#092;', "'" => '&apos;');
		$text = str_replace(array_keys($replace), array_values($replace), $text);
		$text = trim($text);

		$lines = explode("\n", $text);
		$first_line = array_shift($lines);
		if (!$first_line) {
			return array('success' => false);
		}

		if (preg_match('/^.{200}/ui', $first_line, $result)) {
			$header = $result[0];
		} elseif (!empty($lines)) {
			$header = $first_line;
		} else {
			$header = '';
		}

		Database::insert('note', array(
			'id_draft' => $room,
			'id_user' => $user,
			'text' => $text,
			'header' => $header,
		));

		return array('success' => true);
	}

	protected function do_delete ($data) {
		$user = $this->user;
		$id = $data['id'];

		Database::delete('note', 'id_user = ? and id = ?', array($user, $id));

		return array('success' => true);
	}
}