<?php

class Module_Ajax_Setting extends Module_Ajax_Abstract_Authorized
{
	protected function do_set ($data) {
		if (!isset($data['setting']) || !preg_match('/^[a-z_]+$/uis', $data['setting']) ||
			!isset($data['value']) || !is_numeric($data['value'])) {

			return array('success' => false);
		}

		$setting = Database::get_field('setting', 'id', 'setting = ?', $data['setting']);

		if (empty($setting)) {
			return array('success' => false);
		}

		Database::replace('user_setting', array(
			'id_user' => $this->user,
			'id_setting' => $setting,
			'value' => $data['value'],
		), array('id_user', 'id_setting'));

		return array('success' => true);
	}
}