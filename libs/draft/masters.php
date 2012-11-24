<?php

class Draft_Masters extends Draft_Abstract
{
	protected function make_booster($id, $set, $user) {
		$booster = parent::make_booster($id, $set, $user);
		$booster->set_user($user);
		$booster->set_in_deck();
		return $booster;
	}
}