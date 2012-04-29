<?php

class Module_Index extends Module_Abstract_Authorized
{
	protected $css = array('chat', 'index');
	protected $js = array('external/cookie', 'external/md5',
		'external/timer', 'chat', 'index');
}
