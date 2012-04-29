<?php

class User
{
	protected static $user = null;

	public static function get($key = false) {

		if (self::$user === null && isset($_COOKIE['user'])) {
			self::$user = Database::get_full_row('user',
				'cookie = ?', $_COOKIE['user']);
		}

		return $key ? self::$user[$key] : self::$user;
	}
}
