<?php

class Draft
{
	/**
	 * @param {Integer} $id
	 * @return Draft_Abstract
	 */
	public static function factory($id) {
		$type = Database::get_field('draft', 'type', $id);

		switch ($type) {
			case 1: return new Draft_Usual($id);
			case 2: return new Draft_Sealed($id);
			case 3: return new Draft_Masters($id);
			default: return false;
		}
	}
}