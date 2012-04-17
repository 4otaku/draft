<?php

class Transform_Upload_Avatar extends Transform_Upload_Abstract_Image
{
	protected function get_max_size() {
		return Config::get('avatar', 'filesize');
	}

	protected function process() {
		$pathinfo = pathinfo($this->name);

		$extension = strtolower($pathinfo['extension']);

		$thumb = md5(microtime(true));
		$newthumb = IMAGES.SL.'avatar'.SL.$thumb.'.jpg';

		chmod($this->file, 0755);

		$this->worker = Transform_Image::get_worker($this->file);
		$this->animated = $this->is_animated($this->file);

		$this->scale(array(Config::get('avatar', 'width'),
			Config::get('avatar', 'height')), $newthumb);

		$this->set(array('thumb' => $thumb));
	}
}
