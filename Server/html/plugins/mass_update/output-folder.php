<?php include(dirname(__FILE__) . '/header.php'); ?>

<?php
	$axp_md5 = $_REQUEST['axp'];
	$projectFile = '';
	$xmlFile = $mass_update->get_xml_file($axp_md5 , $projectFile);
?>

<?php echo $mass_update->header_nav(); ?>

<?php echo $mass_update->breadcrumb(array(
	'index.php' => 'Projects',
	'project.php?axp=' . urlencode($axp_md5) => substr($projectFile, 0, -4),
	'' => 'Output folder',
)); ?>

<?php
	echo $mass_update->show_select_output_folder(array(
		'next_page' => 'generate.php?axp=' . urlencode($_REQUEST['axp']),
		'extra_options' => array(
			/* 'option1' => 'Option 1 label', */
			/* 'option2' => 'Option 2 label' */
		)
	));
?>

<?php include(dirname(__FILE__) . '/footer.php'); ?>
