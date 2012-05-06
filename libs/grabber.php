<?php

class Grabber
{
	protected static $queryCard =
		'//descendant::table[@id="MAIN"]/descendant::td[position()=2]/child::div[position()=1]/child::div[@id][@class]';
	protected static $queryImg = 'descendant::img[@title][position()=2]';
	protected static $queryMana = 'child::table[position()=2]/descendant::td[position()=1]/descendant::img';
	protected static $queryRarity = 'child::table[position()=2]/descendant::td[position()=4]/descendant::img';
	protected static $queryLast = '//descendant::span[@class="split-pages"]/descendant::span[position()=last()]';

	protected static $rarity = array(
		'Common' => 1,
		'Uncommon' => 2,
		'Rare' => 3,
		'Mythic' => 4,
		'Special' => 1
	);

	public static function get_set_list() {
		try {
			$doc = new DOMDocument();
			@$doc->loadHTMLFile('http://www.mtg.ru/cards/search.phtml');
			$list = $doc->getElementById('Grp')->getElementsByTagName('option');

			$order = 0;
			$return = array();
			foreach ($list as $node) {
				if (!preg_match('/value="([^"]+)".*?>(.*?[a-z].*?)(?:\/\/|<)/ui',
					$node->C14N(), $data)) {

					continue;
				}

				$insert = array(
					'id' => $data[1],
					'name' => trim($data[2]),
					'order' => ++$order
				);

				Database::insert('set', $insert);
				$return[] = $insert;
			}

			return $return;

		} catch (DOMException $e) {
			return array('id' => 0, 'name' => 'Произошла ошибка');
		}
	}

	public static function get_set($set) {
		try {
			Database::begin();
			$count = 0;

			while ($count < 2000) {
				$doc = new DOMDocument();
				$url = 'http://www.mtg.ru/cards/search.phtml?Grp=' .
					$set . '&page=' . ++$count;
				@$doc->loadHTMLFile($url);
				$xpath = new DOMXpath($doc);

				$elements = $xpath->query(self::$queryCard);
				foreach($elements as $element) {
					$insert = array('mana_cost' => '');

					$class = $element->getAttribute('class');
					$class = explode(' ', $class);
					$insert['color'] = str_replace('Color', '', $class[0]);

					$rarity = $xpath->query(self::$queryRarity,
						$element)->item(0);

					if (empty($rarity)) {
						// Базовая земля или прочая хренька
						$rarity = 0;
					} else {
						$rarity = self::$rarity[$rarity->getAttribute('alt')];
					}

					$name = $element->getElementsByTagName('h2')
						->item(0)->C14N();

					preg_match('/.*?<h2>(.+?)(?:<\/h2>\s*$|\/\s*<wbr)/sui', $name, $name);
					$insert['name'] = trim($name[1]);

					$insert['image'] = $xpath->query(self::$queryImg,
						$element)->item(0)->getAttribute('src');
					$insert['image'] = str_replace('/pictures', '', $insert['image']);
					$insert['image'] = str_replace('_small/', '/', $insert['image']);
					$manas = $xpath->query(self::$queryMana,
						$element);
					foreach ($manas as $mana) {
						$insert['mana_cost'] .=
							'(' . $mana->getAttribute('alt') . ')';
					}

					Database::insert('card', $insert);

					Database::insert('set_card', array(
						'id_set' => $set,
						'id_card' => Database::last_id(),
						'rarity' => $rarity
					));
				}

				$last = $xpath->query(self::$queryLast)->item(0);
				if (!strpos($last->C14N(), '|')) {
					break;
				}
			}

			Database::update('set', array('grabbed' => 1), 'id = ?', $set);

			Database::commit();
		} catch (DOMException $e) {
			Database::rollback();
		}
	}
}
