<?php
	// downloads project file whose name is retrieved from the MD5 hash provided in Request
	$plugin_resources_dir = dirname(__FILE__);
	include($plugin_resources_dir . '/loader.php');
	
	$plugin = new AppGiniPlugin();
	$plugin->reject_non_admin();

	$axp_md5 = $_REQUEST['axp'];
	$project_file = '';
	$plugin->get_xml_file($axp_md5 , $project_file);

	$contents = @file_get_contents($plugin_resources_dir . '/../projects/' . $project_file);

	if(!$contents) {
		@http_response_code(404);
		exit;
	}

	@header('Content-Type: application/xml');
	@header('Content-Disposition: attachment; filename="' . urlencode($project_file) . '"');
	echo $contents;