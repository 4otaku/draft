<?php

class Booster
{
	protected static $card_cache = array();

	/**
	 * @param {String} $set
	 * @return Booster_Abstract
	 */
	public static function make_for_set($set, $id) {
		switch ($set) {
			case 'AN':case 'AQ':case 'LG':case 'DK':case 'FE':case 'IA':
			case 'HL':case 'AL':case 'MI':case 'VI':case 'WL':case 'TE':
			case 'SH':case 'EX':case '2E':case '3E':case '4E':case '5E':
				$booster = new Booster_Prefoil($id); break;
			case 'US':case 'UL':case 'UD':case 'MM':case 'NE':case 'PY':
			case 'IN':case 'PS':case 'AP':case 'OD':case 'TO':case 'JU':
			case 'ON':case 'LE':case 'SC':case 'MR':case 'DS':case 'FD':
			case 'CK':case 'BK':case 'SK':case 'RA':case 'GP':case 'DI':
			case 'CS':case '6E':
				$booster = new Booster_Old_Noland($id); break;
			case '7E':case '8E':case '9E':
				$booster = new Booster_Old_Land($id); break;
			case 'TS':
				$booster = new Booster_Timeshifted_Spiral($id); break;
			case 'PC':
				$booster = new Booster_Timeshifted_Chaos($id); break;
			case 'FS': case 'LW':case 'MT':case 'SM':case 'ET':
				$booster = new Booster_New_Noland($id); break;
			case '10E':
				$booster = new Booster_New_Land($id); break;
			case 'ISD': case 'DKA':
				$booster = new Booster_Double_Faced($id); break;
			default:
				$booster = new Booster_Mythic($id); break;
		}

		$booster->set_pool(self::get_cards($set));

		return $booster;
	}

	public static function get_cards($set) {
		if (empty(self::$card_cache[$set])) {
			$card_ids = Database::join('set_card', 'sc.id_card = c.id')
				->group('sc.rarity')->get_table('card', array('sc.rarity',
				'group_concat(c.`id`) as ids'), 'sc.id_set = ?', $set);

			$cards = array();
			foreach ($card_ids as $group) {
				$cards[$group['rarity']] = explode(',', $group['ids']);
			}

			$cards[0] = array(1, 2, 3, 4, 5);

			self::$card_cache[$set] = $cards;
		}

		return self::$card_cache[$set];
	}
}