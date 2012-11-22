<?php

class Booster
{
	protected static $card_cache = array();

	/**
	 * @param {String} $set
	 * @return Booster_Abstract
	 */
	public static function factory($set) {
		if (!isset(self::$card_cache[$set])) {
			self::$card_cache[$set] = self::get_cards($set);
		}
	}

	protected static function get_cards($set) {
		$card_ids = Database::join('set_card', 'sc.id_card = c.id')
			->group('sc.rarity')->get_table('card', array('sc.rarity',
			'group_concat(c.`id`) as ids'), 'sc.id_set = ?', $set);

		$cards = array();
		foreach ($card_ids as $group) {
			$cards[$group['rarity']] = explode(',', $group['ids']);
			shuffle($cards[$group['rarity']]);
		}

		return $cards;
	}
}