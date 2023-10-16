<?php
	include(dirname(__FILE__) . "/header.php");
	
	echo $mass_update->get_project(array(
		'header_nav' => true,
		'pre_upload' => file_get_contents(dirname(__FILE__) . "/video-link.html"),
		'redirect_to' => 'project.php'
	));

	include(dirname(__FILE__) . "/footer.php");
?>
