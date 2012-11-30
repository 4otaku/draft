<?php

class Game_Masters extends Game_Abstract
{
	protected function make_booster($id, $set, $user) {
		$booster = parent::make_booster($id, $set, $user);
		$booster->set_user($user);
		$booster->set_in_deck();
		return $booster;
	}

	protected function generate_lands($booster, $user) {

		$insert = parent::generate_lands($booster, $user);
		$to_add = array(1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3);
		foreach ($insert as &$item) {
			if (!empty($to_add[$item['id_card']])) {
				$item['deck'] = 1;
				$to_add[$item['id_card']]--;
			} else {
				$item['deck'] = 0;
			}
		}

		return $insert;
	}
}