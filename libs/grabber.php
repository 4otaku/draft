<?php

class Grabber
{
	protected static $queryCard =
		'//descendant::table[@id="MAIN"]/descendant::td[position()=2]/child::div[position()=1]/descendant::table[@class]';
	protected static $queryImg = 'descendant::img[@title][position()=2]';
	protected static $queryMana = 'descendant::table[position()=2]/descendant::tr[position()=2]/descendant::td[position()=1]/descendant::div[position()=1]/descendant::img';
	protected static $queryRarity = 'descendant::table[position()=2]/descendant::tr[position()=2]/descendant::td[position()=1]/descendant::div[position()=3]/descendant::img';
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

	public static function get_set($set, $transaction_started = false) {
		try {
			if (!$transaction_started) {
				Database::begin();
			}
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

//					$class = $element->getAttribute('class');
//					$class = explode(' ', $class);
//					$insert['color'] = str_replace('Color', '', $class[0]);

					$rarity = $xpath->query(self::$queryRarity,
						$element)->item(0);

					if (empty($rarity)) {
						// Базовая земля или прочая хренька
						$rarity = 0;
					} else {
						$rarity = self::$rarity[$rarity->getAttribute('alt')];
					}

					$name = $element->getElementsByTagName('h2')
						->item(0);

					if (empty($name)) {
						continue;
					} else {
						$name = $name->C14N();
					}

					preg_match('/.*?<h2>(.+?)(?:<\/h2>\s*$|\/\s*<wbr)/sui', $name, $name);
					$insert['name'] = trim($name[1]);

					$insert['image'] = $xpath->query(self::$queryImg,
						$element)->item(0)->getAttribute('src');
					$insert['image'] = str_replace('/pictures', '', $insert['image']);
					$insert['image'] = str_replace('_small/', '/', $insert['image']);
					$manas = $xpath->query(self::$queryMana,
						$element);

					$colors = '';
					foreach ($manas as $mana) {
						$mana_symbol = $mana->getAttribute('alt');
						$insert['mana_cost'] .= '(' . $mana_symbol . ')';
						$colors .= preg_replace('/[^WUBRG]/u', '', $mana_symbol);
					}
					$colors = array_filter(array_unique(str_split($colors)));

					if (count($colors) > 1) {
						$insert['color'] = 'M';
					} elseif (!trim($insert['mana_cost'])) {
						$insert['color'] = 'L';
					} elseif (!empty($colors)) {
						$insert['color'] = reset($colors);
					} else {
						$insert['color'] = 'A';
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

	public static function get_images($ids) {
		$images = Database::get_vector('card', array('id', 'image'),
			Database::array_in('id', $ids), $ids);
		foreach ($images as $image) {
			if (file_exists(IMAGES . SL . 'small' . SL . $image)) {
				continue;
			}

			$url = preg_replace('/^(\/.*)(\/.*)$/ui', 'http://www.mtg.ru/pictures$1_big$2', $image);

			$got = false; $i = 0;
			while (!$got && (++$i < 15)) {
				usleep(200000);

				$handle = curl_init($url);
				curl_setopt($handle,  CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($handle,  CURLOPT_BINARYTRANSFER, 1);
				$response = curl_exec($handle);
				$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
				curl_close($handle);

				if ($code == 404) {
					$url = 'http://www.mtg.ru/pictures' .  $image;
				} elseif (md5($response) != 'b7b25d6d52c197be99ed9093958b6f39') {
					$got = true;
				}
			}

			$worker = new Transform_Upload_Mtg($response, $image);

			try {
				$worker->process_file();
			} catch (Error_Upload $e) {}
		}
	}
}
