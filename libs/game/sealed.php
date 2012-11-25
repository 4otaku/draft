<?php

class Game_Sealed extends Game_Abstract
{
	protected function make_booster($id, $set, $user) {
		$booster = parent::make_booster($id, $set, $user);
		$booster->set_user($user);
		return $booster;
	}
}