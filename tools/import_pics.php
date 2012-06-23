#!/usr/bin/php
<?php

include dirname(__DIR__) . '/framework/init.php';

Autoload::init(array(LIBS, EXTERNAL, FRAMEWORK_LIBS, FRAMEWORK_EXTERNAL), CACHE);

Config::parse('define.ini', true);
Cache::$base_prefix = Config::get('cache', 'prefix');

function clear_dir($dir) {
	if (!file_exists($dir)) {
		return;
	}
	foreach(glob($dir . '/*') as $file) {
		unlink($file);
	}
}

$file = array_shift($argv);
$set = array_shift($argv);

if (empty($set)) {
	die ('Set needed' . "\n");
}

$set = Database::get_full_row('set', 'id = ?', $set);

if (empty($set)) {
	die ('Incorrect set' . "\n");
}

if (!$set['grabbed']) {
	Grabber::get_set($set['id']);
}

$folder = IMAGES . SL . 'import';

if (!file_exists($folder . SL . $set['id']) || !is_dir($folder . SL . $set['id'])) {
	die ('No import folder' . "\n");
}

$cards = Database::join('set_card', 'sc.id_card = c.id')->
	get_table('card', 'image', 'sc.id_set = ?', $set['id']);

$images = glob($folder . SL . $set['id'] . SL . '*.jpg');

$import = array();
foreach ($images as $image) {
	$compare = str_replace($folder, '', $image);
	$compare = str_replace('.full.jpg', '.jpg', $compare);
	$compare = preg_replace('/[^a-zA-Z\.\/\d\-]|\.(?!jpg$)/sui', '', $compare);

	foreach ($cards as $id => $card) {
		if ($compare == $card['image']) {
			$import[$image] = $card['image'];
			unset($cards[$id]);
			break;
		}
	}
}

if (!empty($cards)) {
	foreach ($cards as $card) {
		echo ('Missing card: ' . $card['image'] . "\n");
	}
	die;
}

clear_dir(IMAGES . SL . 'full' . SL . $set['id']);
clear_dir(IMAGES . SL . 'small' . SL . $set['id']);

$i = 0;
foreach ($import as $from => $to) {
	$worker = new Transform_Upload_Mtg($from, $to);

	try {
		$worker->process_file();
	} catch (Error_Upload $e) {}

	echo ++$i . '/' . count($import) . "\n";
	ob_flush();
	flush();
}
