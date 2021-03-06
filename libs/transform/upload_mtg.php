<?php

class Transform_Upload_Mtg extends Transform_Upload_Abstract_Image
{
	protected function get_max_size() {
		return false;
	}

	protected function test_file() {}

	protected function process() {
		$pathinfo = pathinfo($this->name);

		if (!file_exists(IMAGES.SL.'small'.$pathinfo['dirname'])) {
			mkdir(IMAGES.SL.'small'.$pathinfo['dirname']);
		}
		if (!file_exists(IMAGES.SL.'full'.$pathinfo['dirname'])) {
			mkdir(IMAGES.SL.'full'.$pathinfo['dirname']);
		}

		$newthumb = IMAGES.SL.'small'.$pathinfo['dirname'].SL.$pathinfo['filename'].'.jpg';

		chmod($this->file, 0755);
		$this->worker = Transform_Image::get_worker($this->file);
		$this->animated = false;

		if ($this->info[0] > Config::get('card', 'full_width')) {
			$this->scale(array(Config::get('card', 'full_width'), Config::get('card', 'full_height')),
				IMAGES.SL.'full'.SL.$this->name);
		} else {
			copy($this->file, IMAGES.SL.'full'.SL.$this->name);
		}

		$this->scale(array(Config::get('card', 'width'), Config::get('card', 'height')), $newthumb);
	}
}
