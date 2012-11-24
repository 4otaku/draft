<?php

abstract class Booster_Abstract_New extends Booster_Abstract_Foil
{
	protected function unset_for_foil($rarity) {
		$this->common--;
	}
}