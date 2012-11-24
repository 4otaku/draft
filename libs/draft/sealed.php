<?php

class Draft_Sealed extends Draft_Abstract
{
	protected function make_booster($id, $set, $user) {
		$booster = parent::create_booster($id, $set, $user);
		$booster->set_user($user);
		return $booster;
	}
}