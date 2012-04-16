<?php

class Module_Ajax extends Module_Abstract
{
	protected $headers = array('Content-type' => 'application/json');

	public function send_output() {
		echo trim(json_encode($this->get_data()));
	}

	protected function get_data() {
		$method = 'do_' . $this->url[2];

		$get = $this->clean_globals($_GET, array());

		if (method_exists($this, $method)) {
			return $this->$method($get);
		}
	}

	protected function clean_globals($data, $input = array(), $iteration = 0) {
		if ($iteration > 10) {
			return $input;
		}

		foreach ($data as $k => $v) {

			if (is_array($v)) {
				$input[$k] = $this->clean_globals($data[$k], array(), $iteration + 1);
			} else {
				$v = stripslashes($v);

				$v = str_replace(chr('0'),'',$v);
				$v = str_replace("\0",'',$v);
				$v = str_replace("\x00",'',$v);
				$v = str_replace('%00','',$v);
				$v = str_replace("../","&#46;&#46;/",$v);

				$input[$k] = $v;
			}
		}

		return $input;
	}

	/* From here are realization functions */

	function do_upload ($get) {
		return ($get);
	}
}
