<?php

abstract class Module_Ajax_Abstract_Authorized extends Module_Ajax_Abstract
{
	protected $user;

	protected function get_base_params($data) {
		if (!User::get('id')) {
			return false;
		}

		$this->user = User::get('id');

		return true;
	}
}
