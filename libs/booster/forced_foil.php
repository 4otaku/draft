<?php

class Booster_Forced_Foil extends Booster_Abstract_New
{
	public function __construct($id) {
		parent::__construct($id);
		$this->is_foil = true;
	}
}