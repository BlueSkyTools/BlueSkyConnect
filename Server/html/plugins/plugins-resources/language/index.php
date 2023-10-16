<?php
	// return a list of available languages as a json array
	$base_lang_dir = dirname(__FILE__);
	include($base_lang_dir . '/../loader.php');
	
	$plugin = new AppGiniPlugin();
	$plugin->reject_non_admin();

	$languages = array();
	$d = dir($base_lang_dir);
	while(false !== ($entry = $d->read())) {
		$m = array();
		if(!preg_match('/^([a-z]{2})\.js$/', $entry, $m)) continue;
		$languages[] = $m[1];
	}
	$d->close();

	@header('Content-type: application/json');
	echo json_encode($languages);