<?php

class Booster_Mythic_Nonbasic extends Booster_Mythic
{
	protected $land = 0;
	protected $land_pool = array();

	public function generate() {
		$rarity = mt_rand(0, $this->rare + $this->uncommon + $this->common);

		parent::generate();

		$this->pool = $this->land_pool;

		$this->is_foil = false;
		$this->common = 0;
		$this->uncommon = 0;
		$this->rare = 0;

		if ($rarity < $this->rare) {
			$this->rare = 1;
		} elseif ($rarity < $this->rare + $this->uncommon) {
			$this->uncommon = 1;
		} else {
			$this->common = 1;
		}

		parent::generate();

		return $this->ids;
	}

	public function set_nonbasic_pool($pool) {
		$this->land_pool = $pool;
	}
}