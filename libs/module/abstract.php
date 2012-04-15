<?php

abstract class Module_Abstract
{
	protected $url = array();
	protected $headers = array();

	public function __construct($url) {
		$this->url = $url;
	}

	public function send_headers() {
		foreach ($this->headers as $key => $header) {
			if (is_numeric($key)) {
				header($header);
			} else {
				header("$key: $header");
			}
		}

		return $this;
	}

	abstract public function send_output();

	protected function get_data() {
		return array();
	}
}
