$j(function() {
	/* TODO: (Debugging) set to false on production */
	AppGiniPlugin.enableDebugging = false;

	// the calling page should load the AppGini project object
	// into AppGiniPlugin.prj before loading this file
	if(AppGiniPlugin.prj == undefined) return;
	var project = AppGiniPlugin.project(AppGiniPlugin.prj);

	// fix for non-searchable select2 inside modal, https://stackoverflow.com/a/19574076/1945185
	$j.fn.modal.Constructor.prototype.enforceFocus = function() {};

	var main = function() {
		// main should run only once
		if(AppGiniPlugin._mainAlreadyRun != undefined) return;
		AppGiniPlugin._mainAlreadyRun = true;

		appendCommandCounterToTablesList();
		initCommandsList();
		buildCommandFormIfNeeded();

		/* Triggring Add and Edit modal events */
		$j("#save-command").on('click', saveCommand);
		$j('#field').on('change', fieldChangeHandler);
		$j('#value').on('change', valueChangeHandler);
		$j("#label")
			.on('keyup', function() {
				if(validLabel()) $j('#label-validation').addClass('hidden');
			})
			.on('blur', trimLabel);

		/* place output folder button inside breadcrumb */
		$j('#btn-output-folder').appendTo('.breadcrumb:first');
	}

	AppGiniPlugin.showTableCommands = function(id) {
		AppGiniPlugin.debug('AppGiniPlugin.showTableCommands', id);

		var table = project.getTable(id);
		AppGiniPlugin.tableIndex = id;
		AppGiniPlugin.tableName = table.name;

		// if table has no mass-updatable fields, hide commands list and show a warning
		var muFields = getMassUpdatableFields(id);
		if(!muFields.length) {
			$j('#table-commands').addClass('hidden');
			$j('#alert-no-mass-update-fields').removeClass('hidden');
			return;
		}

		$j('#table-commands').removeClass('hidden');
		$j('#alert-no-mass-update-fields').addClass('hidden');

		var commands = getCommands(table.name);
		AppGiniPlugin.commandsList.items(commands);
	}

	var saveCommand = function() {
		/* Label must be provided */
		trimLabel();
		if(!validLabel()) {
			$j('#label-validation').removeClass('hidden');
			$j('#label').focus();
		}

		/* Field must be specified */
		if(!validField()) {
			$j('#field-validation').removeClass('hidden');
			$j('#field').focus();
		}
		
		/* Value must be specified */
		if(!validValue()) {
			$j('#value-validation').removeClass('hidden');
			$j('#value').focus();
		}

		// prevent submission if validation errors are visible
		if($j('.validation-error:visible').length) return;
		
		$j('#save-command').text('Please wait ...').prop('disabled', true);

		var val = function(id) { return $j('#' + id).val(); };
		$j.ajax({
			type: 'POST',
			url: 'ajax-update-command.php',
			data: {
				axp: AppGiniPlugin.axp_md5,
				tableName: AppGiniPlugin.tableName,
				label: val('label'),
				icon: $j('#icon').select2('val'),
				field: val('field'),
				value: val('value'),
				fixedValue: fixedValue(AppGiniPlugin.tableName, val('field')),
				groups: val('groups'),
				confirmation: $j('#confirmation').prop('checked') + 1 - 1, // converts boolean to 0/1
				hash: val('command-id'),
			},
			success: function(data){
				var ti = project.getTableIndex(data.tableName);
				if(ti < 0) return;

				setCommands(ti, data.commands);
				if(ti == AppGiniPlugin.tableIndex)
					AppGiniPlugin.commandsList.items(data.commands);
			},
			complete: function() {
				$j('#command-modal').modal('hide');
				updateCommandCounters();
			}
		});
	}

	var initCommandsList = function() {
		if(AppGiniPlugin.commandsList != undefined) return;

		AppGiniPlugin.commandsList = AppGiniPlugin.itemsList({
			container: '#table-commands',
			noItemsText: 'This table has no commands configured yet.',
			addLabel: 'Add Command',
			deleteConfirmation: 'Are you sure you want to delete this command?',
	
			itemId: function(cmd) { return cmd.hash; },
	
			itemTitle: function(cmd) {
				return '<i class="glyphicon glyphicon-' + cmd.icon + '"></i> ' +
					cmd.label;
			},
			
			add: function() {
				resetCommandForm();
				showCommandFormModal('Add a new batch command to <span class="text-bold text-info">' + AppGiniPlugin.tableName + '</span> table');
			},
			
			edit: function(cmdId) {
				resetCommandForm();
				showCommandFormModal();
				loadCommand(cmdId);
			},
	
			delete: function(cmdId) {
			 	$j.ajax({
					type: 'POST',
					url: 'ajax-delete-command.php',
					data: { 
						axp: AppGiniPlugin.axp_md5, 
						tableName: AppGiniPlugin.tableName,
						hash: cmdId
					},
					success: function(data) {
						var ti = project.getTableIndex(data.tableName);
						if(ti < 0) return;

						setCommands(ti, data.commands);
						if(ti == AppGiniPlugin.tableIndex)
							AppGiniPlugin.commandsList.items(data.commands);
					},
					error: function() {
						AppGiniPlugin.commandsList.redraw();
					},
					complete: function() {
						updateCommandCounters();
					}
				});
			},
			
			itemDetails: function(cmd) {
				var yes = '<span class="label label-success"><i class="glyphicon glyphicon-ok"></i> Yes</span>',
					no = '<span class="label label-danger"><i class="glyphicon glyphicon-remove"></i> No</span>',
					groups = cmd.groups.length ? '<code>' + cmd.groups.join('</code> <code>') + '</code>' : '<i>All groups</i>',
					field = project.getField(AppGiniPlugin.tableName, cmd.field);
					
				var val = '<i>(Allow user to specify)</i>';
				if(cmd.value == 'fixedValue') {
					val = cmd.fixedValue;
				} else if(field.checkBox == 'True') {
					if(cmd.value == 'checked')
						val = '<i class="glyphicon glyphicon-check" title="Checked"></i>';
					if(cmd.value == 'unchecked')
						val = '<i class="glyphicon glyphicon-unchecked" title="Unchecked"></i>';
					if(cmd.value == 'toggle')
						val = '<i>Toggle checkbox</i>';
				}
	
				return {
					'Field': '<code>' + cmd.field + '</code>',
					'Value': ($j.trim(val).length ? val : '<i>(Empty)</i>'),
					'Confirm first': cmd.confirmation == 1 ? yes : no,
					'Groups': groups,
				};
			}
		});
	}

	var showCommandFormModal = function(title) {
		AppGiniPlugin.debug('showCommandFormModal', title);

		title = title || 'New command';
		buildCommandFormIfNeeded();
		
		$j('#modal-title').html(title);
		populateFieldDropdown(AppGiniPlugin.tableName);

		$j('#command-modal').modal().on('shown.bs.modal', function() {
			$j('#label').focus();
		});
	}

	var validLabel = function() { return $j("#label").val().trim().length > 0; },
		validField = function() { return $j("#field").val().length > 0; },
		validValue = function() { return $j("#value").val().length > 0; },
		trimLabel  = function() { $j("#label").val($j("#label").val().trim()); };

	var buildCommandFormIfNeeded = function() {
		if(AppGiniPlugin.commandForm != undefined) return AppGiniPlugin.commandForm;

		AppGiniPlugin.debug('buildCommandFormIfNeeded');

		AppGiniPlugin.commandForm = AppGiniPlugin.form('#command-form');
		AppGiniPlugin.commandForm
			.add({
				id: 'label',
				label: 'Command title displayed in \'More\' menu',
				required: true,
				maxLength: 100,
				help: '<span class="text-danger">Command title can\'t be empty.</span>',
				helpId: 'label-validation',
				helpClasses: 'hidden validation-error'
			})
			.addHtml(
				// this is going to be a select2, thus the usage of addHtml rather than add
				'<div class="form-group">' +
					'<label for="icon" class="control-label">Command icon</label>' +
					'<div><select id="icon" name="icon"></select></div>' +
				'</div>'
			)
			.add({
				id: 'field',
				label: 'Field to update',
				required: true,
				type: 'select',
				noSelectionText: 'Select field to update',
				help: '<span class="text-danger">Field to update can\'t be empty.</span>',
				helpClasses: 'hidden validation-error',
				helpId: 'field-validation'
			})
			.add({
				id: 'value',
				label: 'New value for the field',
				required: true,
				type: 'select',
				emptyFirstElement: false,
				groupClasses: 'hidden',
				help: '<span class="text-danger">New value for the field must be specified.</span>',
				helpId: 'value-validation',
				helpClasses: 'hidden validation-error'
			})
			.add({
				id: 'fixed-value-options-list',
				label: 'Fixed value',
				type: 'select',
				emptyFirstElement: false,
				groupClasses: 'hidden',
				help: 'Select the value to set this field to.',
			})
			.add({
				id: 'fixed-value',
				type: 'textarea',
				help: 'Enter the value to set this field to. HTML code allowed, but won\'t work for numeric/date fields.',
				label: 'Fixed value',
				groupClasses: 'hidden',
				rows: 1 // should change this to 5 for textarea fields
			})
			.add({
				id: 'groups',
				label: 'Groups that can see this command',
				type: 'textarea',
				init: 'Admins',
				help: 'Enter each group in a separate line, or leave it blank for all signed-in users.'
			})
			.add({
				id: 'confirmation',
				label: 'Confirm first before applying mass update',
				type: 'checkbox',
				init: true
			})
			.add({
				id: 'command-id',
				type: 'hidden',
				init: function() { return AppGiniPlugin.randomHash(); }
			})

		populateIconDropdown();

		return AppGiniPlugin.commandForm;
	}

	var objectToArray = function(obj) {
		if(obj.length != undefined) return obj;
		if(typeof(obj) != 'object') return [];

		// if commands is an object rather than an array, convert to array
		var arr = [];
		for(var i in obj) {
			if(!obj.hasOwnProperty(i)) continue;
			arr.push(obj[i]);
		}

		return arr;		
	}

	var setCommands = function(tni, commands) {
		project.setTablePlugin(tni, 'mass_update', { 
			command_details:  JSON.stringify(objectToArray(commands))
		});
	}

	var getCommands = function(tni) {
		var data = project.getTablePlugin(tni, 'mass_update');
		if(data.command_details == undefined) return [];
		var commands = JSON.parse(data.command_details);
		return objectToArray(commands);
	}

	var getCommand = function(tni, cmdId) {
		var cmds = getCommands(tni);
		for(var i = 0; i < cmds.length; i++) {
			if(cmds[i].hash == cmdId) return cmds[i];
		}
	}

	var resetCommandForm = function() {
		AppGiniPlugin.debug('resetCommandForm');

		buildCommandFormIfNeeded().reset();

		$j('#icon').select2('val', '');
		$j('#field').val('').change();
		$j('#value').val('').change();
		$j('#save-command').text('Save changes').prop('disabled', false);
	}
	 
	var populateIconDropdown = function() {
		
		var glyphIcons = [
			'', 'asterisk', 'plus', 'euro', 'eur', 'minus', 'cloud', 'envelope', 'pencil',
			'glass', 'music', 'search', 'heart', 'star', 'star-empty', 'user', 'film',
			'th-large', 'th', 'th-list', 'ok', 'remove', 'zoom-in', 'zoom-out', 'off',
			'signal', 'cog', 'trash', 'home', 'file', 'time', 'road', 'download-alt',
			'download', 'upload', 'inbox', 'play-circle', 'repeat', 'refresh',
			'list-alt', 'lock', 'flag', 'headphones', 'volume-off', 'volume-down',
			'volume-up', 'qrcode', 'barcode', 'tag', 'tags', 'book', 'bookmark',
			'print', 'camera', 'font', 'bold', 'italic', 'text-height', 'text-width',
			'align-left', 'align-center', 'align-right', 'align-justify', 'list',
			'indent-left', 'indent-right', 'facetime-video', 'picture', 'map-marker',
			'adjust', 'tint', 'edit', 'share', 'check', 'move', 'step-backward',
			'fast-backward', 'backward', 'play', 'pause', 'stop', 'forward',
			'fast-forward', 'step-forward', 'eject', 'chevron-left', 'chevron-right',
			'plus-sign', 'minus-sign', 'remove-sign', 'ok-sign', 'question-sign',
			'info-sign', 'screenshot', 'remove-circle', 'ok-circle', 'ban-circle',
			'arrow-left', 'arrow-right', 'arrow-up', 'arrow-down', 'share-alt',
			'resize-full', 'resize-small', 'exclamation-sign', 'gift', 'leaf',
			'fire', 'eye-open', 'eye-close', 'warning-sign', 'plane', 'calendar',
			'random', 'comment', 'magnet', 'chevron-up', 'chevron-down', 'retweet',
			'shopping-cart', 'folder-close', 'folder-open', 'resize-vertical',
			'resize-horizontal', 'hdd', 'bullhorn', 'bell', 'certificate', 'thumbs-up',
			'thumbs-down', 'hand-right', 'hand-left', 'hand-up', 'hand-down',
			'circle-arrow-right', 'circle-arrow-left', 'circle-arrow-up',
			'circle-arrow-down', 'globe', 'wrench', 'tasks', 'filter', 'briefcase',
			'fullscreen', 'dashboard', 'paperclip', 'heart-empty', 'link', 'phone',
			'pushpin', 'usd', 'gbp', 'sort', 'sort-by-alphabet', 'sort-by-alphabet-alt',
			'sort-by-order', 'sort-by-order-alt', 'sort-by-attributes',
			'sort-by-attributes-alt', 'unchecked', 'expand', 'collapse-down',
			'collapse-up', 'log-in', 'flash', 'log-out', 'new-window', 'record',
			'save', 'open', 'saved', 'import', 'export', 'send', 'floppy-disk',
			'floppy-saved', 'floppy-remove', 'floppy-save', 'floppy-open', 'credit-card',
			'transfer', 'cutlery', 'header', 'compressed', 'earphone', 'phone-alt',
			'tower', 'stats', 'sd-video', 'hd-video', 'subtitles', 'sound-stereo',
			'sound-dolby', 'sound-5-1', 'sound-6-1', 'sound-7-1', 'copyright-mark',
			'registration-mark', 'cloud-download', 'cloud-upload', 'tree-conifer',
			'tree-deciduous', 'cd', 'save-file', 'open-file', 'level-up', 'copy', 'paste',
			'alert', 'equalizer', 'king', 'queen', 'pawn', 'bishop', 'knight',
			'baby-formula', 'tent', 'blackboard', 'bed', 'apple', 'erase', 'hourglass',
			'lamp', 'duplicate', 'piggy-bank', 'scissors', 'bitcoin', 'btc', 'xbt',
			'yen', 'jpy', 'ruble', 'rub', 'scale', 'ice-lolly', 'ice-lolly-tasted',
			'education', 'option-horizontal', 'option-vertical', 'menu-hamburger',
			'modal-window', 'oil', 'grain', 'sunglasses', 'text-size', 'text-color',
			'text-background', 'object-align-top', 'object-align-bottom',
			'object-align-horizontal', 'object-align-left', 'object-align-vertical',
			'object-align-right', 'triangle-right', 'triangle-left', 'triangle-bottom',
			'triangle-top', 'console', 'superscript', 'subscript', 'menu-left',
			'menu-right', 'menu-down', 'menu-up'
		], format = function(icon) {
			if (!icon.id) return icon.text;
			return '<span class="glyphicon glyphicon-' + icon.text + '"></span> ' + icon.text;	
		};

		$j('#icon').empty();
		for(var i = 0; i < glyphIcons.length; i++) {
			$j("<option></option>")
				.attr("value", glyphIcons[i])
				.text(glyphIcons[i])
				.appendTo('#icon');
		}

		$j("#icon").select2({
			width: '100%',
			formatResult: format,
			formatSelection: format,
			escapeMarkup: function(m) { return m; }
		});
	}

	var getMassUpdatableFields = function (tni) {
		var fields = project.getTable(tni).field;
		var options = [];

		for(var j = 0; j < fields.length; j++) {
			// cases where a field can't be mass-updated
			if(fields[j].allowImageUpload == "True")  continue;
			if(fields[j].autoIncrement == "True")  continue;
			if(fields[j].unique == "True") continue;
			if(fields[j].autoFill == "True" &&  typeof(fields[j].parentTable) == "string") continue;

			options.push({ id: fields[j].name, text: fields[j].caption });
		}

		return options;
	}

	var populateFieldDropdown = function(tni, value) {
		var options = getMassUpdatableFields(tni);
		AppGiniPlugin.commandForm.get('field').setOptions(options, value);
	}

	var selectedField = function() {
		var t = project.getTable(AppGiniPlugin.tableIndex);
		
		return project.getField(t.name, $j('#field').val());
	}

	var fieldChangeHandler = function() {
		var form = AppGiniPlugin.commandForm,
			field = selectedField(),
			optionAllowUserToSpecify = { id: 'allowUserToSpecify', text: '[Allow user to specify]' };

		// if no field specified, reset and hide #value and #fixed-value
		if(field == undefined || field.name == '') {
			form.get('value').setOptions([]);
			form.get('value').hide();

			form.get('fixed-value').reset();
			form.get('fixed-value').hide();
			
			form.get('fixed-value-options-list').reset();
			form.get('fixed-value-options-list').hide();
			
			return;
		}

		$j('#field-validation').addClass('hidden');
		/* for checkbox fields, show check-box specific values */
		if(field.checkBox == 'True') {
			form.get('value').setOptions([
				optionAllowUserToSpecify,
				{ id: 'checked', text: 'Checked' },
				{ id: 'unchecked', text: 'Unchecked' },
				{ id: 'toggle', text: 'Toggle' }
			]);

		/* default: populate value drop-down with the 2 generic options below */
		} else {
			form.get('value').setOptions([
				optionAllowUserToSpecify,
				{ id: 'fixedValue', text: 'Fixed value' }
			]);
		}

		if(optionsListField(field)) {
			/* populate #fixed-value-options-list with available options list values */
			var csvOptions = [], rawCSV = field.CSValueList.split(';;');

			for(var i = 0; i < rawCSV.length; i++)
				csvOptions.push({ id: rawCSV[i], text: rawCSV[i] });

			form.get('fixed-value-options-list').setOptions(csvOptions);
		} else {
			form.get('fixed-value-options-list').setOptions([]);
		}

		form.get('value').show();
		$j('#value').focus();
		valueChangeHandler();
	}

	/* Display/hide 'Fixed value' input group based on 'Set to value' selection */
	var valueChangeHandler = function() {
		var val = $j('#value').val();
		if(val.length) $j('#value-validation').addClass('hidden');

		if(val != 'fixedValue') {
			AppGiniPlugin.commandForm.get('fixed-value').display(false);
			AppGiniPlugin.commandForm.get('fixed-value-options-list').display(false);
			return;
		}

		// for option-list fields, show #fixed-value-options-list
		if(optionsListField(selectedField())) {
			AppGiniPlugin.commandForm.get('fixed-value').display(false);
			AppGiniPlugin.commandForm.get('fixed-value-options-list').display(true);
			$j('#fixed-value-options-list').focus();
			return;
		}

		AppGiniPlugin.commandForm.get('fixed-value').display(true);
		AppGiniPlugin.commandForm.get('fixed-value-options-list').display(false);
		$j('#fixed-value').focus();
	}

	/*
	 set/return fixed val from the appropriate element according to field type (option-list or not)
	 */
	var fixedValue = function(tn, fn, val) {
		var field = project.getField(tn, fn);
		if(field === undefined) return;

		if(optionsListField(field)) {
			if(val !== undefined) $j('#fixed-value-options-list').val(val);
			return $j('#fixed-value-options-list').val();
		}

		if(val !== undefined) $j('#fixed-value').val(val);
		return $j('#fixed-value').val();
	}

	var optionsListField = function(field) {
		if(field === undefined) return false;
		return typeof(field.CSValueList) == 'string' && field.CSValueList.length > 0;
	}

	/* populate command editor form with specified command config */
	var loadCommand = function(cmdId) {
		var tn = AppGiniPlugin.tableName,
			cmd = getCommand(tn, cmdId),
			field = project.getField(tn, cmd.field);

		if(field === undefined || !field.name) {
			$j('#modal-title').html('<span class="text-bold text-danger">Invalid field</span>');
			return;
		}

		$j('#modal-title').html('Edit <span class="text-bold text-info">' + cmd.label + '</span> command');
		populateFieldDropdown(tn);
		$j('#confirmation').prop('checked', cmd.confirmation == 1);
		$j('#field').val(cmd.field).change();
		$j('#value').val(cmd.value).change();
		fixedValue(tn, field.name, cmd.fixedValue);
		$j('#groups').val(cmd.groups.join('\n'));
		$j('#command-id').val(cmd.hash);
		$j('#icon').select2('val', cmd.icon);
		$j('#label').val(cmd.label);
	}

	var updateCommandCounters = function() {
		var num = 0, cc;
		for(var i = 0; i < AppGiniPlugin.prj.table.length; i++) {
			cc = $j('#num-commands-' + i);
			num = getCommands(i).length;
			cc.text('' + num);
			if(!num)
				cc.addClass('hidden');
			else
				cc.removeClass('hidden');

		}
	}

	var appendCommandCounterToTablesList = function() {
		// run only once
		if(AppGiniPlugin.appendCommandCounterToTablesListCalled != undefined) return;
		AppGiniPlugin.appendCommandCounterToTablesListCalled = true;

		$j('#tables-list a').each(function() {
			var index = $j(this).data('table_index');
			$j('<span class="label label-info hidden" id="num-commands-' + index + '"></span>').appendTo(this);
		});

		updateCommandCounters();
	}

	main();
	
})
	
