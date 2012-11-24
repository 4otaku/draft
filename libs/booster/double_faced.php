<?php

class Booster_Double_Faced extends Booster_Mythic
{
	protected $common = 9;

	protected $double_pool = array();
	protected $double_list = array('Afflicted Deserter / Werewolf Ransacker',
		'Chalice of Life / Chalice of Death', 'Chosen of Markov / Markov\'s Servant',
		'Elbrus, the Binding Blade / Withengar Unbound',
		'Hinterland Hermit / Hinterland Scourge',
		'Huntmaster of the Fells / Ravager of the Fells',
		'Lambholt Elder / Silverpelt Werewolf',
		'Loyal Cathar / Unhallowed Cathar',
		'Mondronen Shaman / Tovolar\'s Magehunter',
		'Ravenous Demon / Archdemon of Greed',
		'Scorned Villager / Moonscarred Werewolf',
		'Soul Seizer / Ghastly Haunting',
		'Wolfbitten Captive / Krallenhorde Killer',
		'Bloodline Keeper / Lord of Lineage',
		'Civilized Scholar / Homicidal Brute',
		'Cloistered Youth / Unholy Fiend',
		'Daybreak Ranger / Nightfall Predator',
		'Delver of Secrets / Insectile Aberration',
		'Garruk Relentless / Garruk, the Veil-Cursed',
		'Gatstaf Shepherd / Gatstaf Howler',
		'Grizzled Outcasts / Krallenhorde Wantons',
		'Hanweir Watchkeep / Bane of Hanweir',
		'Instigator Gang / Wildblood Pack',
		'Kruin Outlaw / Terror of Kruin Pass',
		'Ludevic\'s Test Subject / Ludevic\'s Abomination',
		'Mayor of Avabruck / Howlpack Alpha',
		'Reckless Waif / Merciless Predator',
		'Screeching Bat / Stalking Vampire',
		'Thraben Sentry / Thraben Militia',
		'Tormented Pariah / Rampaging Werewolf',
		'Ulvenwald Mystics / Ulvenwald Primordials',
		'Village Ironsmith / Ironfang',
		'Villagers of Estwald / Howlpack of Estwald');

	public function generate() {
		$double_ids = array_keys(Database::get_vector('card', 'id',
			Database::array_in('name', $this->double_list), $this->double_list));

		foreach ($this->pool as $rarity => $cards) {
			$this->double_pool[$rarity] = array();
			foreach ($cards as $card) {
				if (in_array($card, $double_ids)) {
					$this->double_pool[$rarity][] = $card;
				}
			}
		}

		$rarity = mt_rand(0, $this->rare + $this->uncommon + $this->common);

		parent::generate();

		$this->is_foil = false;
		$this->land = 0;
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

		$this->pool = $this->double_pool;
		parent::generate();

		return $this->ids;
	}
}