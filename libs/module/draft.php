<?php

class Module_Draft extends Module_Abstract_Authorized
{
	protected $css = array('chat', 'draft');
	protected $js = array('external/cookie', 'external/md5',
		'external/timer', 'chat', 'draft');
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

		$this->draft['booster'] = Database::join('set', 's.id = db.id_set')
			->order('db.order', 'asc')->get_table('draft_booster',
			's.name, s.id, db.state', 'db.id_draft = ?', $this->draft['id']);

		$data['draft'] = $this->draft;

		return $data;
	}
}
