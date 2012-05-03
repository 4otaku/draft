

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `draft`
--

-- --------------------------------------------------------

--
-- Структура таблицы `card`
--

CREATE TABLE IF NOT EXISTS `card` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) NOT NULL,
  `color` varchar(1) NOT NULL,
  `mana_cost` varchar(32) NOT NULL,
  `image` varchar(256) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=235 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft`
--

CREATE TABLE IF NOT EXISTS `draft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int(10) unsigned NOT NULL,
  `pick_time` int(10) unsigned NOT NULL,
  `pause_time` int(10) unsigned NOT NULL,
  `update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `state` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=71 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_booster`
--

CREATE TABLE IF NOT EXISTS `draft_booster` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_draft` int(10) unsigned NOT NULL,
  `id_set` varchar(8) NOT NULL,
  `order` smallint(5) unsigned NOT NULL,
  `state` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `selector` (`id_draft`,`order`,`state`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=107 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_user`
--

CREATE TABLE IF NOT EXISTS `draft_user` (
  `id_draft` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_draft`,`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `message`
--

CREATE TABLE IF NOT EXISTS `message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_draft` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `selector` (`id_draft`,`time`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=86 ;

-- --------------------------------------------------------

--
-- Структура таблицы `presense`
--

CREATE TABLE IF NOT EXISTS `presense` (
  `id_draft` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_draft`,`id_user`),
  KEY `selector` (`id_draft`,`time`,`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `set`
--

CREATE TABLE IF NOT EXISTS `set` (
  `id` varchar(8) NOT NULL,
  `name` varchar(128) NOT NULL,
  `grabbed` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `order` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `set_card`
--

CREATE TABLE IF NOT EXISTS `set_card` (
  `id_set` varchar(8) NOT NULL,
  `id_card` int(10) unsigned NOT NULL,
  `rarity` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id_set`,`id_card`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(40) NOT NULL,
  `password` varchar(32) NOT NULL,
  `cookie` varchar(32) NOT NULL,
  `avatar` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`,`cookie`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
