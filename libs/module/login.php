<?php

class Module_Login extends Module_Abstract_Authorized
{
	protected $css = array('login');
	protected $js = array('external/tabs', 'external/upload',
		'external/alert', 'login');

	protected $redirect_location = '/';

	protected function get_user() {
		return parent::get_user() == false;
	}
}
