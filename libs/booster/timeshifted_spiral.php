<?php

class Booster_Timeshifted_Spiral extends Booster_Abstract_New
{
	protected $common = 10;

	public function generate() {
		parent::generate();

		$shifted = Booster::get_cards('TST');
		if (empty($shifted)) {
			Grabber::get_set('TST', true);
			$shifted = Booster::get_cards('TST');
		}
		$shifted = current($shifted);

		$key = array_rand($shifted);
		$id = $shifted[$key];
		$this->insert_card($id);
		$this->ids[] = $id;

		return $this->ids;
	}
}