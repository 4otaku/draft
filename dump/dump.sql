

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

INSERT INTO `card` (`id`, `name`, `color`, `mana_cost`, `image`) VALUES
(1, 'Plains', 'L', '', '/Land/Plains.jpg'),
(2, 'Forest', 'L', '', '/Land/Forest.jpg'),
(3, 'Mountain', 'L', '', '/Land/Mountain.jpg'),
(4, 'Swamp', 'L', '', '/Land/Swamp.jpg'),
(5, 'Island', 'L', '', '/Land/Island.jpg')

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_booster`
--

CREATE TABLE IF NOT EXISTS `draft_booster` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_draft_set` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`id_draft_set`,`id_user`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_booster_card`
--

CREATE TABLE IF NOT EXISTS `draft_booster_card` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_draft_booster` int(10) unsigned NOT NULL,
  `id_card` int(10) unsigned NOT NULL,
  `id_user` smallint(5) unsigned NOT NULL DEFAULT '0',
  `pick` smallint(5) unsigned NOT NULL DEFAULT '0',
  `forced` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_set`
--

CREATE TABLE IF NOT EXISTS `draft_set` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_draft` int(10) unsigned NOT NULL,
  `id_set` varchar(8) NOT NULL,
  `order` smallint(5) unsigned NOT NULL,
  `state` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `selector` (`id_draft`,`order`,`state`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_step`
--

CREATE TABLE IF NOT EXISTS `draft_step` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_draft` int(10) unsigned NOT NULL,
  `type` varchar(16) NOT NULL,
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`id_draft`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Структура таблицы `draft_user`
--

CREATE TABLE IF NOT EXISTS `draft_user` (
  `id_draft` int(10) unsigned NOT NULL,
  `id_user` int(10) unsigned NOT NULL,
  `order` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id_draft`,`id_user`),
  UNIQUE KEY `order` (`id_draft`,`order`)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
