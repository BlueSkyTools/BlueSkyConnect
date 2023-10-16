<?php 
	include(dirname(__FILE__) . '/header.php'); 

	$mass_update = new mass_update(array(
        'title' => 'Mass Update',
        'name' => 'mass_update',
        'logo' => 'mass_update-logo-lg.png',
        'output_path' => $_REQUEST['path']
    ));
	
	$axp_md5 = $_REQUEST['axp'];
	$projectFile = '';
	$project = $mass_update->get_xml_file($axp_md5, $projectFile);

	// output generate page top content
	echo $mass_update->header_nav();
	echo $mass_update->breadcrumb(array(
		'index.php' => 'Projects',
		'project.php?axp=' . urlencode($axp_md5) => substr($projectFile, 0, -4),
		'output-folder.php?axp=' . urlencode($axp_md5) => 'Output folder',
		'' => 'Generating files'
	));


	// validate provided path
	$path = $_REQUEST['path'];
	if(!$mass_update->is_appgini_app($path)) {
		echo $mass_update->error_message('Invalid application path!');
		include(dirname(__FILE__) . '/footer.php');
		exit;
	}

	/* Copying language file */
	copy_language_file($mass_update, $path);

	/* Adding to footer-extras.php */
	edit_footer_extras($mass_update, $path);

	for($i = 0; $i < count($project->table); $i++) {
		$tn = (string) $project->table[$i]->name;
		$commands = $mass_update->commands_array($tn);

		// if we have no configured commands for this table, move on
		if(!count($commands)) continue;

		$mass_update->progress_log->add("Table '{$tn}' has " . count($commands) . " commands", 'text-bold');

		for($j = 0; $j < count($commands); $j++)
			command_ajax_file($mass_update, $path, $tn, $commands[$j]);

		commands_js_functions($mass_update, $path, $tn);
		list_commands_in_more_menu($mass_update, $path, $tn);
	}

	echo $mass_update->progress_log->show();

	include(dirname(__FILE__) . '/footer.php');

 	

	/**
	 * check command groups and return PHP string representing groups array, to be placed in generated code
	 *
	 * @param      object  $cmd    The command object
	 *
	 * @return     string  PHP code for defining the groups array, or '*' for all signed-in users
	 */
	function groups_array_php($cmd) {
		$all_groups = "'*'";
		if(!isset($cmd->groups))   return $all_groups;
		if(!is_array($cmd->groups)) return $all_groups;
		if(!count($cmd->groups))    return $all_groups;

		$groups = array_map('to_utf8', $cmd->groups);

		return preg_replace('/^\[(.*)\]$/', 'array($1)', json_encode($groups));
	}

	function new_value_selector($mu, $tn, $cmd) {
		$fn = $cmd->field;
		$field = $mu->field($tn, $fn);

		if($mu->command_needs_select2($tn, $cmd))
			return '$j(\'#mass-update-new-value\').select2(\'val\')';

		if((string) $field->htmlarea == 'True')
			return '$j(\'.modal .nicEdit-main\').html()';

		return '$j(\'#mass-update-new-value\').val()';
	}

	function new_value_form($mu, $tn, $cmd) {
		if($cmd->value != 'allowUserToSpecify') return "''";

		$fn = $cmd->field;
		$field = $mu->field($tn, $fn);

		if($mu->command_needs_select2($tn, $cmd))
			return json_encode('<div id="mass-update-new-value"></div>');

		if(
			(string) $field->htmlarea == 'True' ||
			(string) $field->textarea == 'True'
		) return json_encode('<textarea rows="4" cols="50" class="form-control" id="mass-update-new-value"></textarea>');

		if((string) $field->checkBox == 'True')
			return '"<select class=\"form-control\" id=\"mass-update-new-value\">' .
					'<option value=\"1\">" + massUpdateTranslation.checked + "</option>' .
					'<option value=\"0\">" + massUpdateTranslation.unchecked + "</option>' .
					'<option value=\"MASS_UPDATE_TOGGLE_CHECKBOX\">" + massUpdateTranslation.toggle + "</option>' .
				'</select>"';

		return '"<div class=\"form-group\">' .
				'<label for=\"mass-update-new-value\">" + massUpdateTranslation.newValue + "</label>' .
				'<input type=\"text\" class=\"form-control\" id=\"mass-update-new-value\">' .
			'</div>"';
	}

	function command_ajax_url($tn, $cmd) {

		return "hooks/ajax-mass-update-{$tn}-{$cmd->field}-{$cmd->hash}.php";
	}

	function select2_population_code($mu, $tn, $cmd) {
		$fn = $cmd->field;
		$field = $mu->field($tn, $fn);

		// field is an option list? populate with list values
		$stored_csv_values = (string) $field->CSValueList;
		if(strlen($stored_csv_values)) {
			$values = preg_split('/\|\||;;/', $stored_csv_values);
			$data = array();
			for($i = 0; $i < count($values); $i++)
				$data[] = array('id' => to_utf8($values[$i]), 'text' => to_utf8($values[$i]));
			return '﹣﹣﹣﹣﹣﹣data: ' . json_encode($data) . ',';
		}

		// field is a lookup? obtain values from ajax_combo.php
		if(
			((string) $field->autoFill) == 'False' && 
			strlen((string) $field->parentTable) && 
			(
				strlen((string) $field->parentCaptionField) ||
				strlen((string) $field->parentCaptionField2)
			)
		) return "﹣﹣﹣﹣﹣﹣ajax: {
			﹣﹣﹣﹣﹣﹣﹣﹣url: 'ajax_combo.php',
			﹣﹣﹣﹣﹣﹣﹣﹣dataType: 'json',
			﹣﹣﹣﹣﹣﹣﹣﹣cache: true,
			﹣﹣﹣﹣﹣﹣﹣﹣data: function(term, page) {
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣return { t: '{$tn}', f: '{$fn}', s: term, p: page }; 
			﹣﹣﹣﹣﹣﹣﹣﹣},
			﹣﹣﹣﹣﹣﹣﹣﹣results: function(resp, page){ return resp; }
			﹣﹣﹣﹣﹣﹣},";

		return '';
	}

	function copy_language_file($mu, $path) {
		$src = dirname(__FILE__) . '/app-resources/language-mass-update.js';
		$dest = $path . '/hooks/language-mass-update.js';
		$mu->copy_file($src, $dest, true);
	}

	function edit_footer_extras($mu, $path) {
		$mu->progress_log->add(
			'Updating footer-extras.php to load nicEdit and language file  ',
			'text-info'
		);
		
		$footer_extras_path = $path . '/hooks/footer-extras.php';
		$footer_extras_code = @file_get_contents($footer_extras_path);

		// if mass update code already there, replace it
		$mu_code_outside_php_pattern = '/\n<!-- start of mass update plugin code.*end of mass update plugin code -->\n/s';
		$mu_code_outside_php = preg_match($mu_code_outside_php_pattern, $footer_extras_code);

		$mu_code_inside_php_pattern = '/\n\/\* start of mass update plugin code.*end of mass update plugin code -->\n/s';
		$mu_code_inside_php = preg_match($mu_code_inside_php_pattern, $footer_extras_code);
		
		ob_start();
		
		/* 
		 * if footer-extras file has custom code, it might have an open PHP tag
		 * that should be closed first before appending plugin code ...
		 * so, if count of closing php tags != count of opening tags && count > 0,
		 * close php tag first ...
		 */
		$php_begin = '<' . '?php';
		$php_end = '?>';

		// close php tag if open
		if(!$mu_code_inside_php && !$mu_code_outside_php) {
			$open_tags = substr_count($footer_extras_code, $php_begin);
			$close_tags = substr_count($footer_extras_code, $php_end);

			if($open_tags != $close_tags && $open_tags) echo "\n{$php_end}";
		}

		echo "\n<!-- start of mass update plugin code -->\n{$php_begin}\n";
		?>
		﹣﹣if(isset($x) && strpos($x->HTML, 'selected_records_more') !== false) {
		﹣﹣﹣﹣if(strpos($x->HTML, 'nicEdit.js') === false) echo '<script src="nicEdit.js"></script>';
		﹣﹣﹣﹣echo '<script src="hooks/language-mass-update.js"></script>';
		﹣﹣}
		<?php
		echo "\n{$php_end}\n<!-- end of mass update plugin code -->\n";

		$new_code = $mu->format_indents(ob_get_clean());

		// determine if we're replacing existing mu code or appending new code
		if($mu_code_outside_php)
			$new_code = preg_replace($mu_code_outside_php_pattern, $new_code, $footer_extras_code);
		elseif($mu_code_inside_php)
			$new_code = preg_replace($mu_code_inside_php_pattern, $new_code, $footer_extras_code);
		else
			$new_code = $footer_extras_code . $new_code;

		if(!@file_put_contents($footer_extras_path, $new_code)) {
			$mu->progress_log->failed();
			return;
		}

		$mu->progress_log->ok();
	}

	function command_ajax_file($mu, $path, $tn, $cmd) {
		$php_begin = '<' . '?php';
		$php_end = '?>';
		$field = $mu->field($tn, $cmd->field);
		$field_data_type = (int) $field->dataType;

		// get new value
		switch($cmd->value) {
			case 'fixedValue':
				$new_value = makeSafe($cmd->fixedValue);
				break;
			case 'checked':
				$new_value = '1';
				break;
			case 'unchecked':
				$new_value = '0';
				break;
			default:
				// 'allowUserToSpecify', 'toggle', ... etc
				$new_value = '';
				break;
		}

		ob_start(); 
		echo $php_begin . "\n";
		?>
		/* mass_update: Applying command: <?php echo $cmd->label; ?> */
		$allowed_groups = <?php echo groups_array_php($cmd); ?>;

		$hooks_dir = dirname(__FILE__);
		include("{$hooks_dir}/../defaultLang.php");
		include("{$hooks_dir}/../language.php");
		include("{$hooks_dir}/../lib.php");

		// check permissions
		$user = getMemberInfo();
		if($allowed_groups == '*') {
		﹣﹣// allow any signed user
		﹣﹣if(!$user['username'] || $user['username'] == 'guest') {
		﹣﹣﹣﹣@header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
		﹣﹣﹣﹣exit;
		﹣﹣}
		} elseif(!in_array($user['group'], $allowed_groups)) {
		﹣﹣@header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
		﹣﹣exit;
		}

		/* receive and validate calling parameters */
		$ids = $_REQUEST['ids'];
		if(empty($ids) || !is_array($ids)) {
		﹣﹣@header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented');
		﹣﹣exit;
		}

		<?php if($cmd->value == 'allowUserToSpecify') { ?>
			<?php if($field_data_type == 12) { ?>

				// parse time value, prepending with today's formatted date to avoid parse errors
				$new_value = mysql_datetime(date(app_datetime_format()) . ' ' . $_REQUEST['newValue']);
				<?php if((string) $field->notNull == 'True') { ?>

					// field can't be empty
					if(!$new_value) {
				<?php } else  { ?>

					// field must be a valid time or empty
					if(!$new_value && trim($_REQUEST['newValue'])) {
				<?php } ?>

					﹣﹣@header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented');
					﹣﹣exit;
					}
			<?php } elseif($field_data_type >= 9 && $field_data_type <= 11) { ?>

				// parse date/datetime
				$new_value = mysql_datetime($_REQUEST['newValue']);
				<?php if((string) $field->notNull == 'True') { ?>

					// field can't be empty
					if(!$new_value) {
				<?php } else  { ?>

					// field must be a valid date/datetime or empty
					if(!$new_value && trim($_REQUEST['newValue'])) {
				<?php } ?>

					﹣﹣@header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented');
					﹣﹣exit;
					}
			<?php } else { ?>

				$new_value = makeSafe($_REQUEST['newValue']);
			<?php } ?>
		<?php } else { ?>

			$new_value = makeSafe('<?php echo $new_value; ?>');
		<?php } ?>

		/* prepare a safe comma-separated list of IDs to use in the query */
		$cs_ids = array();
		foreach($ids as $id) $cs_ids[] = "'" . makeSafe($id) . "'";
		$cs_ids = implode(', ', $cs_ids);

		$tn = '<?php echo $tn; ?>';
		$field = '<?php echo $cmd->field; ?>';
		$pk = getPKFieldName($tn);

		<?php if($cmd->value == 'toggle') { ?>
			$query = "UPDATE `{$tn}` SET 
			﹣﹣`{$field}` = IF(ISNULL(`{$field}`), '1', IF(`{$field}`, '0', '1'))
			﹣﹣WHERE `{$pk}` IN ({$cs_ids})";
		<?php } else { ?>
			$query = "UPDATE `{$tn}` SET `{$field}`='{$new_value}' WHERE `{$pk}` IN ({$cs_ids})";
		<?php } ?>

		<?php if($cmd->value == 'allowUserToSpecify') { ?>
			if($new_value == 'MASS_UPDATE_TOGGLE_CHECKBOX')
			﹣﹣$query = "UPDATE `{$tn}` SET
			﹣﹣﹣﹣`{$field}` = IF(ISNULL(`{$field}`), '1', IF(`{$field}`, '0', '1')) 
			﹣﹣﹣﹣WHERE `{$pk}` IN ({$cs_ids})";
		<?php } ?>

		$e = array('silentErrors' => true);
		sql($query, $e);

		if($e['error']) {
		﹣﹣@header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented');
		}

		<?php
		$code = $mu->format_indents(ob_get_clean());

		/* Generating ajax file */
		$ajax_file = "{$path}/" . command_ajax_url($tn, $cmd);
		
		$mu->progress_log->add("Generating {$ajax_file}  ", 'text-info');
		if(@file_put_contents($ajax_file, $code)) {
			$mu->progress_log->ok();
		} else {
			$mu->progress_log->failed();
		}
	}

	function commands_js_functions($mu, $path, $tn) {
		$commands = $mu->commands_array($tn);
		if(!count($commands)) return;

		$mu_code_start = '/* start of mass_update code */';
		$mu_code_end = '/* end of mass_update code */';
		ob_start();

		echo $mu_code_start;
		?>

		var massUpdateAlert = function(msg, showOk, okClass) {
		﹣﹣if(showOk == undefined) showOk = false;
		﹣﹣if(okClass == undefined) okClass = 'default';

		﹣﹣var footer = [];
		﹣﹣if(showOk) footer.push({ label: massUpdateTranslation.ok, bs_class: okClass });

		﹣﹣$j('.modal').modal('hide');
		﹣﹣var mId = modal_window({ message: '', title: msg, footer: footer });
		﹣﹣$j('#' + mId).find('.modal-body').remove();
		﹣﹣if(!footer.length) $j('#' + mId).find('.modal-footer').remove();
		}

		<?php
		
		for($i = 0; $i < count($commands); $i++) {
			$cmd = $commands[$i];
			$field = $mu->field($tn, $cmd->field);
			$field_data_type = (int) $field->dataType;
			?>

			/* <?php echo $cmd->label; ?> command */
			function massUpdateCommand_<?php echo $cmd->hash; ?>(tn, ids) {
				<?php if($cmd->value == 'allowUserToSpecify') { ?>

					﹣﹣/* Ask user for new value */
					﹣﹣modal_window({
					﹣﹣﹣﹣id: 'mass-update-new-value-modal',
					﹣﹣﹣﹣message: <?php echo new_value_form($mu, $tn, $cmd); ?>,
					﹣﹣﹣﹣title: '<i class="glyphicon glyphicon-<?php echo $cmd->icon; ?>"></i> ' + 
					﹣﹣﹣﹣﹣﹣<?php echo json_encode(to_utf8($cmd->label)); ?>,
					﹣﹣﹣﹣footer :[{
					﹣﹣﹣﹣﹣﹣label: massUpdateTranslation.confirm,
					﹣﹣﹣﹣﹣﹣bs_class: 'primary',
					﹣﹣﹣﹣﹣﹣click: function () {
					﹣﹣﹣﹣﹣﹣﹣﹣var newValue = <?php echo new_value_selector($mu, $tn, $cmd); ?>

					<?php if($cmd->confirmation) { ?>

						﹣﹣﹣﹣﹣﹣﹣﹣/* ask user for confirmation before applying updates */
						﹣﹣﹣﹣﹣﹣﹣﹣if(!confirm(massUpdateTranslation.areYouSureApply)) return;
					<?php } ?>

					﹣﹣﹣﹣﹣﹣﹣﹣/* send update request */
					﹣﹣﹣﹣﹣﹣﹣﹣massUpdateAlert(massUpdateTranslation.pleaseWait);
					﹣﹣﹣﹣﹣﹣﹣﹣$j.ajax({
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣url: <?php echo json_encode(command_ajax_url($tn, $cmd)); ?>,
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣data: { ids: ids, newValue: newValue },
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣success: function() { location.reload(); },
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣error: function() {
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣massUpdateAlert('<span class="text-danger">' + massUpdateTranslation.error + '</span>', true, 'danger');
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
					﹣﹣﹣﹣﹣﹣﹣﹣});
					﹣﹣﹣﹣﹣﹣}
					﹣﹣﹣﹣}]
					﹣﹣});

					<?php if($mu->command_needs_select2($tn, $cmd)) { ?>

						﹣﹣/* prepare select2 drop-down inside modal */
						﹣﹣$j('#mass-update-new-value-modal').on('shown.bs.modal', function () {
						﹣﹣﹣﹣$j("#mass-update-new-value").select2({
						﹣﹣﹣﹣﹣﹣width: '100%',
						﹣﹣﹣﹣﹣﹣formatNoMatches: function(term){ return massUpdateTranslation.noMatches; },
						﹣﹣﹣﹣﹣﹣minimumResultsForSearch: 5,
						﹣﹣﹣﹣﹣﹣loadMorePadding: 200,
						<?php echo select2_population_code($mu, $tn, $cmd); ?>

						﹣﹣﹣﹣﹣﹣escapeMarkup: function(str){ return str; }
						﹣﹣﹣﹣}).select2('focus');
						﹣﹣});
					<?php } elseif($mu->command_needs_richedit($tn, $cmd)) { ?>

						﹣﹣/* prepare nicEditor inside modal */
						﹣﹣$j('#mass-update-new-value-modal').on('shown.bs.modal', function () {
						﹣﹣﹣﹣new nicEditor({ fullPanel : true }).panelInstance('mass-update-new-value');
						﹣﹣﹣﹣$j('.nicEdit-panelContain').parent().width('100%');
						﹣﹣﹣﹣$j('.nicEdit-panelContain').parent().next().width('98%');
						﹣﹣﹣﹣$j('.nicEdit-main').width('99%');
						﹣﹣﹣﹣$j('.nicEdit-main').attr('style', 'min-height: 5em !important');
								
						﹣﹣﹣﹣// focus on nicedit box
						﹣﹣﹣﹣$j('.modal .nicEdit-main').focus();
						﹣﹣});
					<?php } else { ?>
						﹣﹣$j('#mass-update-new-value-modal').on('shown.bs.modal', function () {
						<?php if($field_data_type == 9) { ?>
							﹣﹣﹣﹣// show date picker
							﹣﹣﹣﹣$j('#mass-update-new-value').datetimepicker({
							﹣﹣﹣﹣﹣﹣format: '<?php echo $mu->datetime_format('moment'); ?>'
							﹣﹣﹣﹣});
						<?php } elseif($field_data_type == 10 || $field_data_type == 11) { ?>
							﹣﹣﹣﹣// show datetime picker
							﹣﹣﹣﹣$j('#mass-update-new-value').datetimepicker({
							﹣﹣﹣﹣﹣﹣format: '<?php echo $mu->datetime_format('moment', 'dt'); ?>'
							﹣﹣﹣﹣});
						<?php } elseif($field_data_type == 12) { ?>
							﹣﹣﹣﹣// show time picker
							﹣﹣﹣﹣$j('#mass-update-new-value').datetimepicker({
							﹣﹣﹣﹣﹣﹣format: '<?php echo $mu->datetime_format('moment', 't'); ?>'
							﹣﹣﹣﹣});
						<?php } ?>

						﹣﹣﹣﹣// focus new value
						﹣﹣﹣﹣$j('#mass-update-new-value').focus();
						﹣﹣});
					<?php } ?>
				<?php } else { ?>
					<?php if($cmd->confirmation) { ?>

						﹣﹣/* ask user for confirmation before applying updates */
						﹣﹣if(!confirm(massUpdateTranslation.areYouSureApply)) return;
					<?php } ?>

					﹣﹣massUpdateAlert(massUpdateTranslation.pleaseWait);

					﹣﹣$j.ajax({
					﹣﹣﹣﹣url: <?php echo json_encode(command_ajax_url($tn, $cmd)); ?>,
					﹣﹣﹣﹣data: { ids: ids },
					﹣﹣﹣﹣success: function() { location.reload(); },
					﹣﹣﹣﹣error: function() {
					﹣﹣﹣﹣﹣﹣massUpdateAlert('<span class="text-danger">' + massUpdateTranslation.error + '</span>', true, 'danger');
					﹣﹣﹣﹣}
					﹣﹣});
				<?php } ?>

			}
			<?php
		}

		echo $mu_code_end;
		$code = $mu->format_indents(ob_get_clean());

		// save to tablename-tv.js
		$tvjs_file = "{$path}/hooks/{$tn}-tv.js";
		if(@file_exists($tvjs_file)) {
			$mu->progress_log->add("File {$tn}-tv.js already exists, so we're updating it  " , 'text-info');
			
			$existing_code = @file_get_contents($tvjs_file);
			$old_mu_code_regex = '/' . preg_quote($mu_code_start, '/') . '(.*)' . preg_quote($mu_code_end, '/') . '/s';

			// if file contains old mass-update code, replace it
			if(preg_match($old_mu_code_regex, $existing_code)) {
				$rep = preg_replace($old_mu_code_regex, $code, $existing_code);
				$overwritten = @file_put_contents($tvjs_file, $rep);
			} else {
				$overwritten = @file_put_contents($tvjs_file, "{$code}\n\n{$existing_code}");
			}
		} else {
			$mu->progress_log->add("Writing {$tn}-tv.js  " , 'text-info');
			$overwritten = @file_put_contents($tvjs_file, $code);
		}

		if($overwritten)
			$mu->progress_log->ok();
		else
			$mu->progress_log->failed();
	}

	function commands_by_user_group($mu, $tn) {
		$commands = $mu->commands_array($tn);
		if(!count($commands)) return array();

		$groups = array();
		for($ci = 0; $ci < count($commands); $ci++) {
			$cmd = $commands[$ci];

			// if command is accessible to all logged users, add to '*' special group
			if(!count($cmd->groups)) {
				$groups['*'][] = $cmd->hash;
				continue;
			}

			for($gi = 0; $gi < count($cmd->groups); $gi++)
				$groups[$cmd->groups[$gi]][] = $cmd->hash;
		}

		/* now, for commands added to '*' group, also add them to every other group */
		if(!isset($groups['*'])) return $groups;
		foreach($groups as $group => $commands) {
			if($group == '*') continue;
			foreach($groups['*'] as $hash) {
				if(!in_array($hash, $groups[$group])) $groups[$group][] = $hash;
			}
		}

		return $groups;
	}

	function list_commands_in_more_menu($mu, $path, $tn) {
		$commands = $mu->commands_array($tn);
		if(!count($commands)) return;

		$group_commands = commands_by_user_group($mu, $tn);
		ob_start();
		?>
		
		﹣﹣﹣﹣/*
		﹣﹣﹣﹣ * Q: How do I return other custom batch commands not defined in mass_update plugin?
		﹣﹣﹣﹣ * 
		﹣﹣﹣﹣ * A: Define your commands ABOVE the 'Inserted by Mass Update' comment above 
		﹣﹣﹣﹣ * in an array named $custom_actions_top to display them above the commands 
		﹣﹣﹣﹣ * created by the mass_update plugin.
		﹣﹣﹣﹣ * 
		﹣﹣﹣﹣ * You can also define commands in an array named $custom_actions_bottom
		﹣﹣﹣﹣ * (also ABOVE the 'Inserted by Mass Update' comment block) to display them 
		﹣﹣﹣﹣ * below the commands created by the mass_update plugin.
		﹣﹣﹣﹣ * 
		﹣﹣﹣﹣*/

		﹣﹣﹣﹣if(!isset($custom_actions_top) || !is_array($custom_actions_top))
		﹣﹣﹣﹣﹣﹣$custom_actions_top = array();

		﹣﹣﹣﹣if(!isset($custom_actions_bottom) || !is_array($custom_actions_bottom))
		﹣﹣﹣﹣﹣﹣$custom_actions_bottom = array();

		﹣﹣﹣﹣$command = array(
		<?php for($i = 0; $i < count($commands); $i++) { ?>
			<?php $cmd = $commands[$i]; ?>
			﹣﹣﹣﹣﹣﹣'<?php echo $cmd->hash; ?>' => array(
			﹣﹣﹣﹣﹣﹣﹣﹣'title' => <?php echo json_encode(to_utf8($cmd->label)); ?>,
			﹣﹣﹣﹣﹣﹣﹣﹣'function' => 'massUpdateCommand_<?php echo $cmd->hash; ?>',
			﹣﹣﹣﹣﹣﹣﹣﹣'icon' => '<?php echo $cmd->icon; ?>'
			﹣﹣﹣﹣﹣﹣),
		<?php } ?>
		﹣﹣﹣﹣);
		
		﹣﹣﹣﹣$mi = getMemberInfo();
		﹣﹣﹣﹣switch($mi['group']) {
		<?php if(is_array($group_commands)) foreach ($group_commands as $group => $hashes) { ?>
			<?php if($group == '*') continue; /* '*' group will be handled at the end as defaul case */ ?>
			﹣﹣﹣﹣﹣﹣case '<?php echo $group; ?>':
			﹣﹣﹣﹣﹣﹣﹣﹣return array_merge(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$custom_actions_top,
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣array(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$command['<?php
			echo implode(
				"'],\n﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣\$command['", 
				$hashes
			);?>']
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣),
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$custom_actions_bottom
			﹣﹣﹣﹣﹣﹣﹣﹣);
		<?php } ?>
		<?php if(isset($group_commands['*']) && count($group_commands['*'])) { ?>
			﹣﹣﹣﹣﹣﹣default:
			﹣﹣﹣﹣﹣﹣﹣﹣/* for all other logged users, enable the following commands */
			﹣﹣﹣﹣﹣﹣﹣﹣if($mi['username'] && $mi['username'] != 'guest')
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣return array_merge(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$custom_actions_top,
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣array(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$command['<?php
			echo implode(
				"'],\n﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣\$command['",
				$group_commands['*']
			); ?>']
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣),
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$custom_actions_bottom
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣);
		<?php } ?>
		﹣﹣﹣﹣}

		<?php
		$code = $mu->format_indents(ob_get_clean());

		$hook_function = "{$tn}_batch_actions";
		$mu->progress_log->add("Generating code in {$hook_function} function  " , 'text-info');

		if($mu->replace_to_hook("{$path}/hooks/{$tn}.php", $hook_function, $code, 'top'))
			$mu->progress_log->ok();
		else
			$mu->progress_log->failed();
	}