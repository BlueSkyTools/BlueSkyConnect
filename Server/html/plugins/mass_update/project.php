<?php
	include(dirname(__FILE__) . '/header.php');

	// validate project name
	if (!isset($_REQUEST['axp']) || !preg_match('/^[a-f0-9]{32}$/i', $_REQUEST['axp'])) {
		echo '<br>' . $mass_update->error_message('Project file not found.');
		exit;
	}
	
	$axp_md5 = $_REQUEST['axp'];
	$projectFile = '';
	$xmlFile = $mass_update->get_xml_file($axp_md5, $projectFile);
//-----------------------------------------------------------------------------------------
?>
<script src="../plugins-resources/itemsList.js"></script>
<script src="../plugins-resources/form.js"></script>
<script src="../plugins-resources/project.js"></script>
<script>
	if(window.AppGiniPlugin === undefined) window.AppGiniPlugin = {};
	
	AppGiniPlugin.prj = <?php echo json_encode($xmlFile); ?>;
	AppGiniPlugin.axp_md5 = <?php echo json_encode($axp_md5); ?>;
	AppGiniPlugin.prependPath = <?php echo json_encode(PREPEND_PATH); ?>;
</script>
<script src="project.js"></script>

<?php echo $mass_update->header_nav(); ?>

<?php echo $mass_update->breadcrumb(array(
	'index.php' => 'Projects',
	'' => substr($projectFile, 0, -4)
)); ?>

<a id="btn-output-folder" href="output-folder.php?axp=<?php echo $axp_md5; ?>" class="pull-right btn btn-primary btn-sm" style="padding: 0.25em 3em;">SPECIFY OUTPUT FOLDER <span class="glyphicon glyphicon-chevron-right"></span></a>
<div class="clearfix"></div>

	<div class="row">
	<div class="col-md-4"> 
		<?php
			$xml_file = $mass_update->get_xml_file($axp_md5, $projectFile);
			echo $mass_update->show_tables(array(
				'axp' => $xml_file,
				'click_handler' => 'AppGiniPlugin.showTableCommands',
				'select_first_table' => true
			));
			$tables = $xml_file->table;
		?>
	</div>
	<div class="col-md-8">
		<div class="alert alert-danger hidden" id="alert-no-mass-update-fields">
			<i class="glyphicon glyphicon-exclamation-sign"></i>
			This table has no fields that can be mass-updated.
			The following types of fields can't be mass-updated:
			File upload, unqiue, auto-increment and auto-fill fields.
		</div>

		<div id="table-commands"></div>
	</div>
</div>

<div class="modal fade" id="command-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="modal-title">New command</h4>
			</div>
			<div class="modal-body">
				<form id="command-form"></form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button type="button" class="btn btn-primary" id="save-command">Save changes</button>
			</div>
		</div>
	</div>
</div>


<style>
	.btn.details-list:focus, .btn.summary-list:focus {
		outline: 0;
	}
	.items-list-header {
		margin-bottom: 1rem;
	}
	.panel tr:first-child th, .panel tr:first-child td {
		border-top: none !important;
	}
	.panel-title { font-weight: bold; }

	.pointer {
		cursor: pointer;
	}

	.item .item-details-key:last-child, .item .item-details-value:last-child {
		border-bottom: none;
	}
	.item .item-details-key, .item .item-details-value {
		padding: 1rem;
		border-bottom: solid 1px #ddd;
		height: 4.5rem !important;
		overflow: hidden;
	}
	.item .item-details-values {
		padding-left: 0 !important;
	}
	.item .item-details-keys {
		text-align: right;
		font-weight: bold;
		padding-right: 0 !important;
		width: 30% !important;
		min-width: 10em;
	}

	.panel-title .glyphicon-list-alt.hspacer-md { display: none; }

	.panel-body table { margin-bottom: 0; }
</style>

<?php
include(dirname(__FILE__) . '/footer.php'); ?>
