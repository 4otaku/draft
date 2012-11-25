<?php

class Game
{
	/**
	 * @param {Integer} $id
	 * @return Game_Abstract
	 */
	public static function factory($id) {
		$type = Database::get_field('game', 'type', $id);

		switch ($type) {
			case 1: return new Game_Draft($id);
			case 2: return new Game_Sealed($id);
			case 3: return new Game_Masters($id);
			default: return false;
		}
	}
}