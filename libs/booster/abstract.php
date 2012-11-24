<?php

abstract class Booster_Abstract
{
	protected $start_pool = array();
	protected $pool = array();
	protected $ids = array();
	protected $id = 0;
	protected $user = 0;
	protected $deck = 0;

	protected $land = 0;
	protected $common = 11;
	protected $uncommon = 3;
	protected $rare = 1;

	public function __constructed($id) {
		$this->id = $id;
	}

	public function set_pool($pool) {
		$this->start_pool = $pool;
		$this->pool = $pool;
	}

	public function set_user($user) {
		$this->user = $user;
	}

	public function set_in_deck() {
		$this->deck = 1;
	}

	public function generate() {
		for ($i = 0; $i < $this->land; $i++) {
			$this->add_card(0);
		}
		for ($i = 0; $i < $this->common; $i++) {
			$this->add_card(1);
		}
		for ($i = 0; $i < $this->uncommon; $i++) {
			$this->add_card(2);
		}
		for ($i = 0; $i < $this->rare; $i++) {
			$this->add_card(3);
		}

		return $this->ids;
	}

	protected function add_card($rarity) {
		while (empty($this->pool[$rarity]) && $rarity >= 0) {
			$rarity--;
		}
		if ($rarity < 0) {
			throw new Error();
		}

		$key = array_rand($this->pool[$rarity]);
		$id = $this->pool[$rarity][$key];
		$this->insert_card($id);
		$this->ids[] = $id;
		unset($this->pool[$rarity][$key]);
	}

	protected function insert_card($id) {
		Database::insert('draft_booster_card', array(
			'id_draft_booster' => $this->id,
			'id_card' => $id,
			'id_user' => $this->user
		));
	}
}