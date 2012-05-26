<?php

class Module_Draft extends Module_Abstract_Authorized
{
	protected $css = array('external/countdown' , 'chat', 'draft');
	protected $js = array('external/cookie', 'external/md5',
		'external/timer', 'external/countdown', 'external/draggable', 'chat', 'draft');
	protected $redirect_location = '/';

	protected $draft = array();

	public function __construct($url) {
		parent::__construct($url);

		if (!is_numeric($url[2])) {
			$this->create_redirect();
		}

		$draft = Database::join('user', 'u.id = d.id_user')
			->get_row('draft', array('d.*', 'u.login'), 'd.id = ?', $url[2]);
		if (empty($draft)) {
			$this->create_redirect();
		}

		$this->draft = $draft;
	}

	protected function get_data() {
		$data = parent::get_data();

		$this->draft['booster'] = Database::join('set', 's.id = ds.id_set')
			->order('ds.order', 'asc')->get_table('draft_set',
			's.name, s.id, ds.state', 'ds.id_draft = ?', $this->draft['id']);

		foreach ($this->draft['booster'] as &$booster) {
			$booster['name'] = str_replace("'", '&apos;', $booster['name']);
		}

		$data['draft'] = $this->draft;

		return $data;
	}
}
