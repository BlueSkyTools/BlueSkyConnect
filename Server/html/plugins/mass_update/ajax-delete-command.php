<?php
	include(dirname(__FILE__).'/mass_update.php');

	/*
		$_REQUEST includes the following:
			axp: md5 hash of project
			tableName: container table name
			hash: hash ID of command to delete
	*/

	$mass_update = new mass_update(array(
		'title' => 'Mass Update',
		'name' => 'mass_update', 
		'logo' => 'mass_update-logo-lg.png' 
	));
	
	/* grant access to the groups 'Admins' only */
	$mass_update->reject_non_admin('Access denied');

	$axp_md5 = $_REQUEST['axp'];
	$table_name = $_REQUEST['tableName'];
	$hash = $_REQUEST['hash'];

	$projectFile = '';
	$xmlFile = $mass_update->get_xml_file($axp_md5, $projectFile);

	$table_index = $mass_update->table_index($table_name);
	if($table_index == -1) {
		$mass_update->ajax_json_error('Invalid table name');
	}

	$table = $mass_update->table($table_name);
	$commands_str = $table->plugins->mass_update->command_details;
	if(!isset($commands_str)) {
		$mass_update->ajax_json_error('No command to delete');
	}

	$commands = json_decode($commands_str, true);
	if(!is_array($commands)) {
		$mass_update->ajax_json_error('No command to delete');
	}

	// re-structure commands as a 0-based numeric array
	$commands = array_values($commands);

	// find and delete the command having the provided hash
	for($i = 0; $i < count($commands); $i++) {
		if($commands[$i]['hash'] == $hash) {
			unset($commands[$i]);
			break;
		}
	}

	// re-structure commands as a 0-based numeric array
	$commands = array_values($commands);

	/* update the node */
	$mass_update->update_project_plugin_node(array(
		'projectName' => $projectFile,
		'tableIndex' => $table_index,
		'pluginName' => 'mass_update',
		'nodeName' => 'command_details',
		'data' => json_encode($commands)
	));

	$mass_update->ajax_json_return(array(
		'tableIndex' => $table_index,
		'tableName' => $table_name,
		'commands' => $commands
	));
