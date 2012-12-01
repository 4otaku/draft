<?php

abstract class Module_Abstract_Html extends Module_Abstract
{
	protected $default_css = array('base');
	protected $default_js = array('base', 'external/overlay');
	protected $css = array();
	protected $js = array();

	public function send_output() {
		include_once EXTERNAL.SL.'Twig'.SL.'Autoloader.php';
		spl_autoload_register(array(new Twig_Autoloader, 'autoload'), true, true);

		$twig = new Twig_Environment(new Twig_Loader_Filesystem(HTML), array(
			'cache' => CACHE,
			'auto_reload' => true,
			'autoescape' => false
		));

		$template = strtolower(str_replace('Module_', '', get_called_class()));
		$template = $twig->loadTemplate($template.'.html');

		$data = $this->get_data();
		$data['js'] = $this->get_list('js');
		$data['css'] = $this->get_list('css');

		$template->display($data);
	}

	protected function get_list($type) {
		$default = 'default_' . $type;
		$list = array_unique(array_merge($this->$default, $this->$type));

		$time = 0;
		$base = $type == 'js' ? JS . SL : CSS . SL;
		foreach ($list as &$file) {
			$file = $file . '.' . $type;
			$time = max($time, filemtime($base . $file));
		}

		return array(
			'list' => $list,
			'time' => $time,
			'debug' => $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ?
				'&debug=1' : ''
		);
	}
}
