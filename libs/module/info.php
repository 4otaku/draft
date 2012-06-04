<?php

class Module_Info extends Module_Abstract_Authorized
{
	protected $redirect_location = '/';

	protected $draft = 0;

	public function __construct($url) {
		parent::__construct($url);

		if (isset($url[2]) && is_numeric($url[2]) &&
			Database::get_count('draft', $url[2])) {

			$this->draft = $url[2];
		}
	}

	protected function get_data() {
		$data = parent::get_data();

		$data['note'] = Database::order('n.time')->join('note', 'u.id = n.id_user')
			->get_full_table('user', 'n.id_draft = ?', $this->draft);

		foreach ($data['note'] as &$note) {
			$note['text'] = nl2br($note['text']);
			$md5 = md5($note['login']);
			$parts = array(hexdec($md5{0}.$md5{1}), hexdec($md5{2}.$md5{3}), hexdec($md5{4}.$md5{5}));
			foreach ($parts as &$part) {
				$part = dechex(ceil($part / 2));
				if (strlen($part) == 1) {
					$part = '0' . $part;
				}
			}
			$note['color'] = implode($parts);
		}

		return $data;
	}
}
