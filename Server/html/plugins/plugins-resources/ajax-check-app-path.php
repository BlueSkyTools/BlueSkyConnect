<?php
	/* Checks if provided 'path' is a path to a valid AppGini app or not */
	$pr_dir = dirname(__FILE__);
	include("{$pr_dir}/../../defaultLang.php");
	include("{$pr_dir}/../../language.php");
	include("{$pr_dir}/../../lib.php");
	
	/* check access */
	$mi = getMemberInfo();
	if(!$mi['admin'] || !isset($_REQUEST['path'])) exit404();
	
	$path = realpath(trim($_REQUEST['path']));
	
	if(!is_dir($path)) exit404();
	
	if(
		!is_dir("{$path}/admin") ||
		!is_dir("{$path}/hooks") ||
		!is_file("{$path}/hooks/__global.php") ||
		!is_dir("{$path}/resources") ||
		!is_dir("{$path}/templates") ||
		!is_writable("{$path}/hooks")
	) exit404();
	
/********************************************************************/
	
	function exit404(){
		header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		exit;
	}
