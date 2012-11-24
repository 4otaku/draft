<?php

class Booster_Mythic extends Booster_Abstract_New
{
	protected function add_card($rarity) {
		if ($rarity == 3 && (mt_rand(0, 8) < 1)) {
			$rarity = 4;
		}

		parent::add_card($rarity);
	}
}