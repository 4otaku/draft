<?php

abstract class Booster_Abstract_Foil extends Booster_Abstract
{
	protected $is_foil = false;

	public function __construct($id) {
		parent::__construct($id);
		$this->is_foil = (mt_rand(0, 6) < 1);
	}

	public function generate() {
		if ($this->is_foil) {
			$rarity = mt_rand(0, $this->rare + $this->uncommon + $this->common + $this->land);
			if ($rarity < $this->rare) {
				$rarity = 3;
			} elseif ($rarity < $this->rare + $this->uncommon) {
				$rarity = 2;
			} elseif ($rarity < $this->rare + $this->uncommon + $this->common) {
				$rarity = 1;
			} else {
				$rarity = 0;
			}
			$this->unset_for_foil($rarity);
		}

		parent::generate();

		if ($this->is_foil) {
			$this->pool = $this->start_pool;
			$this->add_card($rarity);
		}

		return $this->ids;
	}

	protected function unset_for_foil($rarity) {
		if ($rarity == 3) {
			$this->rare--;
		} elseif ($rarity == 2) {
			$this->uncommon--;
		} elseif ($rarity == 1) {
			$this->common--;
		} elseif ($rarity == 0) {
			$this->land--;
		}
	}
}