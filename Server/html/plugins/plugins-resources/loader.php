<?php
	@include(get_app_path() . "/defaultLang.php");
	@include(get_app_path() . "/language.php");
	@include(get_app_path() . "/lib.php");
	@include(dirname(__FILE__) . "/ProgressLog.php");
	@include(dirname(__FILE__) . "/AppGiniPlugin.php");
	
	function get_app_path() {
		return realpath(dirname(__FILE__) . '/../..');
	}

	function load_plugins_resources() {
		$resources_dir = dirname(__FILE__);
		$base_dir = get_app_path();
		$plugins_dir = realpath("{$resources_dir}/..");
		$error_msgs = array();
		$plugins_error = false;
		
		if(!is_readable("{$base_dir}/defaultLang.php")) $plugins_error = true;
		if(!is_readable("{$base_dir}/language.php")) $plugins_error = true;
		if(!is_readable("{$base_dir}/lib.php")) $plugins_error = true;
		if(!is_readable("{$resources_dir}/ProgressLog.php")) $plugins_error = true;
		if(!is_readable("{$resources_dir}/AppGiniPlugin.php")) $plugins_error = true;
		
		if($plugins_error) $error_msgs[] = "The plugin was not installed correctly, you must put it inside the plugins folder under you main AppGini application folder.";
		
		$res = AppGiniPlugin::prepare_projects_folder();
		if($res !== true) $error_msgs[] = $res;
		
		if(count($error_msgs)) {
			?><!DOCTYPE html>
			<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
			<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
			<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
			<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->

			<head>
				<meta charset="<?php echo datalist_db_encoding; ?>">
				<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
				<meta name="description" content="">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">

				<title>Plugin installed incorrectly!</title>
				
				<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">				
			</head>
			<body>
				<div class="container" style="margin-top: 5em;">
					<div class="panel panel-danger">
						<div class="panel-heading"><h3 class="panel-title">Error:</h3></div>
						<div class="panel-body">
							<p class="text-danger"><?php echo implode('<br>&bull; ', $error_msgs); ?></p>
						</div>
					</div>
				</div>
			</body>
			</html>
			<?php 
			exit;
		}
	}

	load_plugins_resources();
