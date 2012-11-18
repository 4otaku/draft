<?php

class User
{
	protected static $user = null;

	public static function get($key = false) {

		if (self::$user === null && isset($_COOKIE['user'])) {
			self::$user = Database::get_full_row('user',
				'cookie = ?', $_COOKIE['user']);

			if (self::$user['id']) {
				self::$user['settings'] = self::load_settings(self::$user['id']);
			}
		}

		return $key ? self::$user[$key] : self::$user;
	}

	protected static function load_settings($id) {
		return Database::join('user_setting', 'us.id_setting = s.id and id_user = ' . $id)
			->get_vector('setting', array('s.setting', 'coalesce(us.value, s.default)'));
	}
}
