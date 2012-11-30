<?php

class Module_Game extends Module_Abstract_Authorized
{
	protected $css = array('external/countdown', 'chat', 'game');
	protected $js = array('external/cookie', 'external/ui', 'external/md5', 'external/sound',
		'external/rangeinput', 'external/dateformat', 'external/timer', 'external/countdown',
		'chat', 'game');
	protected $redirect_location = '/';

	protected $game = array();

	public function __construct($url) {
		parent::__construct($url);

		if (!is_numeric($url[2])) {
			$this->create_redirect();
		}

		$game = Database::join('user', 'u.id = g.id_user')
			->get_row('game', array('g.*', 'u.login'), 'g.id = ?', $url[2]);
		if (empty($game)) {
			$this->create_redirect();
		}

		$this->game = $game;
	}

	protected function get_data() {
		$data = parent::get_data();

		$this->game['booster'] = Database::join('set', 's.id = gs.id_set')
			->order('gs.order', 'asc')->get_table('game_set',
			's.name, s.id, gs.state', 'gs.id_game = ?', $this->game['id']);

		foreach ($this->game['booster'] as &$booster) {
			$booster['name'] = str_replace("'", '&apos;', $booster['name']);
		}

		if (empty($this->game['start']) || $this->game['start'] == '0000-00-00 00:00:00') {
			$this->game['start'] = 0;
		} else {
			$this->game['start'] = strtotime($this->game['start']);
		}

		$data['game'] = $this->game;

		return $data;
	}

	protected function get_list($type) {
		$data = parent::get_list($type);

		if ($type == 'js') {
			switch ($this->game['type']) {
				case 1: $type = 'draft'; break;
				case 2: $type = 'sealed'; break;
				case 3: $type = 'masters'; break;
				default: $type = 'draft';
			}

			$data['game'] = $type;
			$data['gametime'] = filemtime(JS . SL . 'game' . SL . $type . '.js');
		}

		return $data;
	}
}
