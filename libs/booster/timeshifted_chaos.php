<?php

class Booster_Timeshifted_Chaos extends Booster_Abstract_New
{
	protected $common = 8;
	protected $uncommon = 2;
	protected $shifted_pool = array();

	protected $shifted = array('Blood Knight', 'Bog Serpent', 'Brute Force', 'Calciderm',
		'Damnation', 'Dunerider Outlaw', 'Essence Warden', 'Fa\'adiyah Seer', 'Frozen Aether',
		'Gaea\'s Anthem', 'Gossamer Phantasm', 'Groundbreaker', 'Harmonize', 'Healing Leaves',
		'Hedge Troll', 'Keen Sense', 'Kor Dirge', 'Malach of the Dawn', 'Mana Tithe', 'Melancholy',
		'Merfolk Thaumaturgist', 'Mesa Enchantress', 'Molten Firebird', 'Mycologist', 'Null Profusion',
		'Ovinize', 'Piracy Charm', 'Porphyry Nodes', 'Primal Plasma', 'Prodigal Pyromancer', 'Pyrohemia',
		'Rathi Trapper', 'Reckless Wurm', 'Revered Dead', 'Riptide Pilferer', 'Seal of Primordium',
		'Serendib Sorcerer', 'Serra Sphinx', 'Shivan Wumpus', 'Shrouded Lore', 'Simian Spirit Guide',
		'Sinew Sliver', 'Skirk Shaman', 'Sunlance', 'Vampiric Link');

	public function generate() {
		$shifted_ids = array_keys(Database::get_vector('card', 'id',
			Database::array_in('name', $this->shifted), $this->shifted));

		foreach ($this->pool as $rarity => &$cards) {
			$this->shifted_pool[$rarity] = array();
			foreach ($cards as $key => $card) {
				if (in_array($card, $shifted_ids)) {
					unset($cards[$key]);
					$this->shifted_pool[$rarity][] = $card;
				}
			}
			$cards = array_values($cards);
		}
		unset($cards);

		parent::generate();

		$this->is_foil = false;
		$this->land = 0;
		$this->common = 3;
		if ((mt_rand(0, 4) < 1)) {
			$this->uncommon = 0;
			$this->rare = 1;
		} else {
			$this->uncommon = 1;
			$this->rare = 0;
		}

		$this->pool = $this->shifted_pool;
		parent::generate();

		return $this->ids;
	}
}