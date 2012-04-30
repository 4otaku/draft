<?php

class Module_Index extends Module_Abstract_Authorized
{
	protected $css = array('chat', 'index');
	protected $js = array('external/cookie', 'external/md5',
		'external/timer', 'chat', 'index');

	protected function get_data() {
		$data = parent::get_data();
		$data['sets'] = Database::order('order', 'asc')->get_table('set');

		if (empty($data['sets'])) {
			$data['sets'] = Grabber::get_set_list();
		}

		return $data;
	}
}
