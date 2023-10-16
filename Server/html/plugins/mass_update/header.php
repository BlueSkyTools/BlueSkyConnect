<?php
	include(dirname(__FILE__).'/mass_update.php');
	
	$mass_update = new mass_update([
		'title' => 'Mass Update',
		'name' => 'mass_update',
		'logo' => 'mass_update-logo-lg.png',
		'version' => 1.4,
	]);

	if(!defined('PREPEND_PATH')) define('PREPEND_PATH', '../../');
	#########################################################

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

		<title>Mass Update</title>
		
		<link id="browser_favicon" rel="shortcut icon" href="<?php echo PREPEND_PATH; ?>resources/images/appgini-icon.png">

		<?php echo $mass_update->get_theme_css_links(); ?>
		
		<?php if(is_file(__DIR__ . '/../../dynamic.css')) { ?>
			<link rel="stylesheet" href="../../dynamic.css">
		<?php } else { ?>
			<link rel="stylesheet" href="../../dynamic.css.php">
		<?php } ?>		


		<!-- jquery ui -->
		<link rel="stylesheet" href="<?php echo PREPEND_PATH; ?>plugins/plugins-resources/jquery-ui/jquery-ui.min.css">
		<link rel="stylesheet" href="<?php echo PREPEND_PATH; ?>resources/select2/select2.css" media="screen">


		<!--[if lt IE 9]> <script src="<?php echo PREPEND_PATH; ?>resources/initializr/js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script> <![endif]-->
		<script src="<?php echo PREPEND_PATH; ?>resources/jquery/js/<?php echo $mass_update->get_jquery(); ?>"></script>

		<!-- jquery ui -->
		<script src="<?php echo PREPEND_PATH; ?>plugins/plugins-resources/jquery-ui/jquery-ui.min.js"></script>

		<script>var $j = jQuery.noConflict();</script>
		<script src="<?php echo PREPEND_PATH; ?>resources/initializr/js/vendor/bootstrap.min.js"></script>	
		<script src="<?php echo PREPEND_PATH; ?>plugins/plugins-resources/plugins-common.js"></script>
		<script src="<?php echo PREPEND_PATH; ?>resources/select2/select2.min.js"></script>


		<script>
			$j(function(){
				// disable rtl.css, if it exists ...
				$j('link[href$="rtl.css"]').remove();

				// translate UI
				AppGiniPlugin.Translate.live();
			})
		</script>

		<style>
			.breadcrumb > li + li::before { content: " \0025B6 "; }
		</style>
	</head>
	<body>
		<div class="container">
		
			<!-- process notifications -->
			<div style="height: 60px; margin: -15px 0 -45px;">
				<?php if(function_exists('showNotifications')) echo showNotifications(); ?>
			</div>

<?php

	/* grant access to the groups 'Admins' only */
	if (!$mass_update->is_admin() ){
		echo "<br>".$mass_update->error_message('Access denied.<br>Please, <a href=\'' . PREPEND_PATH . 'index.php?signIn=1\' >Log in</a> as administrator to access this page.' , false);
		exit;
	}

