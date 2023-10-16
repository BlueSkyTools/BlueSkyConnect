<?php
	// deletes a project file whose name is retrieved from the MD5 hash provided in Request
	$plugin_resources_dir = dirname(__FILE__);
	include($plugin_resources_dir . '/loader.php');
	
	$plugin = new AppGiniPlugin();
	$plugin->reject_non_admin();

	$axp_md5 = $_REQUEST['axp'];
	$project_file = '';
	$plugin->get_xml_file($axp_md5 , $project_file);
	$project_fullpath = $plugin_resources_dir . '/../projects/' . $project_file;
	@unlink($project_fullpath);

	// file not deleted? return 500 error
	if(@file_exists($project_fullpath)) {
		@header('X-Error-Message: ' . $plugin->translation['Couldn\'t delete this record'], true, 500);
		@header('X-FILENAME: ' . $project_fullpath);
		exit;
	}

	// file deleted ...