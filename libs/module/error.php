<?php

class Module_Error extends Module_Abstract_Html
{
	protected $headers = array('HTTP/1.x 404 Not Found');
	protected $css = array('error');
}
