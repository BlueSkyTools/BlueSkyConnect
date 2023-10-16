



/**
 * Generate random string
 */
function random_string(string_length){
	var text = "";
	var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

	for(var i = 0; i < string_length; i++)
		text += possible.charAt(Math.floor(Math.random() * possible.length));

	return text;
}

//--------------------------------------------------------------------------------------------------------------------

/**
 * options object. The following members can be provided:
 *    url: iframe url to load
 *    message: instead of a url to open, you could pass a message. HTML tags allowed.
 *    id: id attribute of modal window
 *    title: optional modal window title
 *    size: 'default', 'full'
 *    close: optional function to execute on closing the modal
 *    footer: optional array of objects describing the buttons to display in the footer.
 *       Each button object can have the following members:
 *          label: string, label of button
 *          bs_class: string, button bootstrap class. Can be 'primary', 'default', 'success', 'warning' or 'danger'
 *          click: function to execute on clicking the button. If the button closes the modal, this
 *                 function is executed before the close handler
 *          causes_closing: boolean, default is true.
 */
function modal_window(options){
	var id = options.id;
	var url = options.url;
	var title = options.title;
	var footer = options.footer;
	var message = options.message;

	if(typeof(id) == 'undefined') id = random_string(20);
	if(typeof(footer) == 'undefined') footer = [];

	if(jQuery('#' + id).length){
		/* modal exists -- remove it first */
		jQuery('#' + id).remove();
	}

	/* prepare footer buttons, if any */
	var footer_buttons = '';
	for(i = 0; i < footer.length; i++){
		if(typeof(footer[i].causes_closing) == 'undefined'){ footer[i].causes_closing = true; }
		if(typeof(footer[i].bs_class) == 'undefined'){ footer[i].bs_class = 'default'; }
		footer[i].id = id + '_footer_button_' + random_string(10);

		footer_buttons += '<button type="button" class="btn btn-' + footer[i].bs_class + '" ' +
				(footer[i].causes_closing ? 'data-dismiss="modal" ' : '') +
				'id="' + footer[i].id + '" ' +
				'>' + footer[i].label + '</button>';
	}

	jQuery('body').append(
		'<div class="modal fade" id="' + id + '" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">' +
			'<div class="modal-dialog">' +
				'<div class="modal-content">' +
					( title != undefined ?
						'<div class="modal-header">' +
							'<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>' +
							'<h3 class="modal-title" id="myModalLabel">' + title + '</h3>' +
						'</div>'
						: ''
					) +
					'<div class="modal-body" style="-webkit-overflow-scrolling:touch !important; overflow-y: auto;">' +
						( url != undefined ?
							'<iframe width="100%" height="100%" sandbox="allow-forms allow-scripts allow-same-origin allow-popups" src="' + url + '"></iframe>'
							: message
						) +
					'</div>' +
					( footer != undefined ?
						'<div class="modal-footer">' + footer_buttons + '</div>'
						: ''
					) +
				'</div>' +
			'</div>' +
		'</div>'
	);

	for(i = 0; i < footer.length; i++){
		if(typeof(footer[i].click) == 'function'){
			jQuery('#' + footer[i].id).click(footer[i].click);
		}
	}

	jQuery('#' + id).modal();

	if(typeof(options.close) == 'function'){
		jQuery('#' + id).on('hidden.bs.modal', options.close);
	}

	if(typeof(options.size) == 'undefined') options.size = 'default';

	if(options.size == 'full'){
		jQuery(window).resize(function(){
			jQuery('#' + id + ' .modal-dialog').width(jQuery(window).width() * 0.95);
			jQuery('#' + id + ' .modal-body').height(jQuery(window).height() * 0.7);
		}).trigger('resize');
	}

	return id;
}

//---------------------------------------------------------------------------------------------------------------------

/**
 *  Display div element slowly then hide after 5 sec
 *  @param  divElement: div element to be showed
 			location: redirect location after displaying div, 
 					  pass false if no redirect needed
 */
function dismissible_msg( divElement , location ){
	  $j(divElement).show("slow", function(){ //
			setTimeout(function(){
				$j(divElement).hide("slow"); 
				if (location){
					window.location.href = location;
				}
			}, 5000);
		});		
}

AppGiniPlugin = {
	/*
		to be populated from caller file using this PHP code:
		echo json_encode($plugin->project_xml);
	*/
	project: {},
	
	/*
		to be populated from caller file using this PHP code:
		echo json_encode($plugin->get_fk_fields());
	*/
	fk_fields: {},

	/*
		set to true in plugin code to enable debugging to browser console
		debugging is done using AppGiniPlugin.debug()
	*/
	enableDebugging: false,

	/**
	 *  @brief display a debug message in browser console if enableDebugging is true
	 *  
	 *  @param [in] fn name of function to display in console
	 *  @param [in] p* optional values of function parameters, up to 5 supported
	 */	
	debug: function(fn, p1, p2, p3, p4, p5) {
		if(!this.enableDebugging) return;
		var msg = fn + '(';
		if(p1 != undefined) msg += (  "'" + p1 + "'");
		if(p2 != undefined) msg += (", '" + p2 + "'");
		if(p3 != undefined) msg += (", '" + p3 + "'");
		if(p4 != undefined) msg += (", '" + p4 + "'");
		if(p5 != undefined) msg += (", '" + p5 + "'");
		msg += ')';

		console.log(msg);
	},

	randomHash: function(length) {
		length = length || 20;
		var hash = '', pool = 'abcdefghijklmnopqrstuvwxyz0123456789';
		for(var i = 0; i < length; i++) {
			hash += pool.charAt(Math.random() * pool.length);
		}
		return hash;
	},

	/**
	 *  @brief retrieve an array of tables in project
	 *  
	 *  @param [in] map optional mapping object to specify which table info to return
	 *                  and what to call them. If not specified, the table object
	 *                  is returned as is.
	 *  @return array of table objects
	 */	
	get_tables: function(map){
		if(undefined == this.project.table) return [];
		if(undefined == map) return this.project.table;
		
		return this.array_map(this.project.table, map);
	},
	
	/**
	 *  @brief retrieve an array of fields in a specific table of the project
	 *  
	 *  @param [in] tn name of the table to retrieve fields from
	 *  @param [in] map optional mapping object to specify which field info to return
	 *                  and what to call them. If not specified, the field object
	 *                  is returned as is.
	 *  @return array of field objects
	 */	
	get_fields: function(tn, map){
		var t = this.get_table(tn);
		if(undefined == t.field) return [];
		if(undefined == map) return t.field;
		
		return this.array_map(t.field, map);
	},
	
	/*
		retrieve table object from project given its name
	*/
	get_table: function(tn){
		if(undefined == tn || undefined == this.project.table) return {};
		
		for(i in this.project.table){
			if(this.project.table[i].name == tn) return this.project.table[i];
		}
		
		return {};
	},
	
	/*
		retrieve field object from project given its table and name
	*/
	get_field: function(tn, fn){
		if(undefined == tn || undefined == fn || undefined == this.project.table) return {};
		
		var t = this.get_table(tn);
		if(undefined == t.field) return {};
		
		for(i in t.field){
			if(t.field[i].name == fn) return t.field[i];
		}
		
		return {};
	},
	
	array_unique: function(array){
		var a = array.concat();
		for(var i = 0; i < a.length; ++i){
			for(var j = i + 1; j < a.length; ++j){
				if(a[i] === a[j])
					a.splice(j--, 1);
			}
		}

		return a;
	},

	/* JS port of AppGiniPlugin::get_child_tables() */
	get_child_tables: function(tn){
		var children = [];
		if(undefined == this.fk_fields[tn]) return children;
		
		for(var ct in this.fk_fields){
			for(var ctfk in this.fk_fields[ct]){
				var pt = this.fk_fields[ct][ctfk];
				if(pt == tn && children.indexOf(ct) == -1) children.push(ct);
			}
		}
		
		return children;
	},

	/* JS port of AppGiniPlugin::get_parent_tables() */
	get_parent_tables: function(tn){
		var parents = [];
		if(undefined == this.fk_fields[tn]) return parents;
		
		for(var fkf in this.fk_fields[tn]){
			var pt = this.fk_fields[tn][fkf];
			if(parents.indexOf(pt) == -1) parents.push(pt);
		}
		
		return parents;
	},

	/* JS port of AppGiniPlugin::get_ancestor_tables() */
	get_ancestor_tables: function(tn, level){
		var ancestors = [];
		ancestors[0] = this.get_parent_tables(tn);
		if(undefined == level || level <= 1) return ancestors;
		
		for(var i = 1; i < level; i++){
			ancestors[i] = [];
			for(var j = 0; j < ancestors[i - 1].length; j++){
				var pt = ancestors[i - 1][j];
				//console.log('i: ' + i + ', j: ' + j + ', ancestors[' + (i - 1) + ']:');
				//console.log(ancestors[i - 1]);
				ancestors[i] = ancestors[i].concat(this.get_parent_tables(pt));
			}
		}
		
		var ancestors_flat = [];
		
		for(var i = 0; i < level; i++){
			ancestors_flat = ancestors_flat.concat(ancestors[i]);
		}
		
		return this.array_unique(ancestors_flat);
	},

	/*
		create a toggle switch.
		config: {
			placeholder: selector string for the switch container.
			label: the label to use for the switch. Defaults to 'Switch'
			position: position of the label relative to the switch. Possible values:
			          top, bottom, left, right (default)
			startup: name of the initial state of the switch. Defaults to the ON state.
			states: an object that defines the labels for the 2 states
			        of the switch and the handler for each of them.
			        the first state will be considered the "ON" state
					and the second the "OFF" state.
		}
	*/
	toggle: function(config){
		if(
			undefined == config.placeholder ||
			undefined == config.states ||
			config.states.length < 2
		) return false;
		
		config.label = config.label || 'Switch';
		config.position = config.position || 'right';
		
		var i = 0;
		for(var st in config.states){
			if(!i){ /* ON state */
				i = 1;
				var labelON = st;
				var modeON = config.states[st];
			}else if(i == 1){
				i++; /* to ignore any further states */
				var labelOFF = st;
				var modeOFF = config.states[st];
			}
		}

		config.startup = config.startup || labelON;
		
		var set = function(mode){
			if(undefined == mode) mode = labelON;

			/* ON/OFF buttons display */
			if(mode.toLowerCase() == labelON.toLowerCase()){
				$j(config.placeholder + ' .btn-on')
					.removeClass('btn-default')
					.addClass('active btn-success');
				$j(config.placeholder + ' .btn-off')
					.removeClass('active btn-danger')
					.addClass('btn-default');
			}else if(mode.toLowerCase() == labelOFF.toLowerCase()){
				$j(config.placeholder + ' .btn-on')
					.removeClass('active btn-success')
					.addClass('btn-default');
				$j(config.placeholder + ' .btn-off')
					.removeClass('btn-default')
					.addClass('active btn-danger');
			}

			/* mode switching handlers */
			if(mode.toLowerCase() == labelON.toLowerCase()){
				modeON();
			}else if(mode.toLowerCase() == labelOFF.toLowerCase()){
				modeOFF();
			}
		};
		
		var get = function(){
			var turned_on = $j(config.placeholder + ' .btn-on').hasClass('active');
			return (turned_on ? labelON : labelOFF);
		}
		
		/* switch code */
		var switch_code = '' +
			'LABEL-TOP-LEFT' +
			'<div class="btn-group btn-group-sm">' +
				'<button type="button" class="btn btn-success btn-on">' +
					labelON +
				'</button>' +
				'<button type="button" class="btn btn-default btn-off">' +
					labelOFF +
				'</button>' +
			'</div>' +
			'LABEL-BOTTOM-RIGHT' +
			'<style>' +
				config.placeholder + ' .btn:focus{ outline: 0; }' +
			'</style>';
		
		switch(config.position.toLowerCase()){
			case 'top':
				switch_code = switch_code.replace(/LABEL-TOP-LEFT/, '<b> ' + config.label + ' </b><br>');
				break;
			case 'left':
				switch_code = switch_code.replace(/LABEL-TOP-LEFT/, '<b> ' + config.label + ' </b>');
				break;
			case 'bottom':
				switch_code = switch_code.replace(/LABEL-BOTTOM-RIGHT/, '<br><b> ' + config.label + ' </b>');
				break;
			default: /* right is default */
				switch_code = switch_code.replace(/LABEL-BOTTOM-RIGHT/, '<b> ' + config.label + ' </b>');
		}
		switch_code = switch_code.replace(/(LABEL-TOP-LEFT|LABEL-BOTTOM-RIGHT)/, '');
		
		/* create the switch */
		$j(config.placeholder)
			.on('click', '.btn', function(){ //
				var state = get();
				set(state == labelON ? labelOFF : labelON);
			})
			.append(switch_code);
			
		/* startup mode */
		set(config.startup);
		
		/*
			API:
				set(mode) for external control of the switch,
				get() to retrieve current mode label
		*/
		return {
			set: set,
			get: get
		};
	},
	
	/* BSF-based search for the relationship between 2 tables */
	
	find_path: function(t1, t2){
		var gr = [], curr, ncurr, q = [t1], path = [], cn = t2;

		for(var t in this.project.table){
			gr[this.project.table[t].name] = {
				dist: Infinity,
				prnt: ''
			};
		}
		if(undefined == gr[t1] || undefined == gr[t2]) return [];
		gr[t1].dist = 0;
		
		while(q.length){
			curr = q.pop();
			ncurr = this.array_unique(this.get_parent_tables(curr).concat(this.get_child_tables(curr)));
			for(var n in ncurr){
				var nn = ncurr[n];
				if(undefined == gr[nn]) continue;
				if(gr[nn].dist == Infinity){
					gr[nn].dist = gr[curr].dist + 1;
					gr[nn].prnt = curr;
					q.push(nn);
				}
			}
		}
		
		while(cn != t1){
			path.push(cn);
			cn = gr[cn].prnt;
		}
		
		path.push(t1);
		return path.reverse();
	},
	
	/**
	 *  @brief Map a given array of objects to another one
	 *  
	 *  @param [in] source the source array, [{ id1: '', ... }, ...]
	 *  @param [in] map mapping object { source: destination, .. }
	 *  @return mapped array
	 */
	array_map: function(source, map){
		if(undefined == source) return [];
		if(undefined == map) return source;
		
		var mapped = [], mo = {};
		for(var i = 0; i < source.length; i++){
			mo = {};
			for(var k in source[i]){
				if(undefined == map[k]) continue;
				mo[map[k]] = source[i][k];
			}
			mapped.push(mo);
		}
		
		return mapped;
	},
	
	/**
	 *  @brief populates a select drop-down
	 *  
	 *  @param [in] settings object: {
	 *                  select: selector expression of the <select> to be populated
	 *                  options: array of { id: '', text: ''} to populate the select
	 *                  selected_id: [optional] id of the selected elements
	 *                  mode: one of the following strings:
	 *                        'overwrite': (default) clears the select before populating it
	 *                        'append': appends options only if their id's don't already exist and updates text for existing id's
	 *              }
	 */
	populate_select: function(settings){
		if(undefined == settings) return console.log('no settings passed to populate_select()');
		if(undefined == settings.select) return console.log('no selector provided to populate_select()');
		if(undefined == settings.options) return console.log('no options passed to populate_select()');
		if(typeof(settings.options) != 'object') return console.log('"options" setting passed to populate_select() must be an array');
		if(typeof(settings.options[0]) != 'object' && settings.options.length) return console.log('"options" setting passed to populate_select() must be an array of onjects: {id, text}');
		
		if(undefined == settings.mode) settings.mode = 'overwrite';
		if(undefined == settings.selected_id) settings.selected_id = '';
		
		var $s = $j(settings.select).eq(0);
		if($s.length != 1) return console.log('select passed to populate_select() not found');
		
		if(settings.mode == 'overwrite' &&  $s.children().length) $s.children().remove();
		
		var id = '', id_esc = '', text = '';
		for(var i = 0; i < settings.options.length; i++){
			// option exists? if so, update text, else create
			id = settings.options[i].id;
			id_esc = this.escape_quotes(id);
			text = settings.options[i].text;
			if(!$s.find('option[value="' + id_esc + '"]').length){
				$j('<option></option>')
					.attr('value', id)
					.appendTo($s);
			}
			
			$s.find('option[value="' + id_esc + '"]')
				.text(text)
				.prop('selected', id == settings.selected_id);
		}
	},
	
	escape_quotes: function(s){
		return s.replace(/'/, '\\\'').replace(/"/, '\\\"');
	},

	md5: function(str){
		/* adapted from http://www.myersdaily.org/joseph/javascript/md5.js */
		
		if(str == undefined){
			console.error('AppGiniPlugin.md5: Nothing to hash!');
			return false;
		}
		
		var md5cycle = function(x, k){ /**/
			var a = x[0];
			var b = x[1];
			var c = x[2];
			var d = x[3];

			a = ff(a, b, c, d, k[0], 7, -680876936);
			d = ff(d, a, b, c, k[1], 12, -389564586);
			c = ff(c, d, a, b, k[2], 17, 606105819);
			b = ff(b, c, d, a, k[3], 22, -1044525330);
			a = ff(a, b, c, d, k[4], 7, -176418897);
			d = ff(d, a, b, c, k[5], 12, 1200080426);
			c = ff(c, d, a, b, k[6], 17, -1473231341);
			b = ff(b, c, d, a, k[7], 22, -45705983);
			a = ff(a, b, c, d, k[8], 7, 1770035416);
			d = ff(d, a, b, c, k[9], 12, -1958414417);
			c = ff(c, d, a, b, k[10], 17, -42063);
			b = ff(b, c, d, a, k[11], 22, -1990404162);
			a = ff(a, b, c, d, k[12], 7, 1804603682);
			d = ff(d, a, b, c, k[13], 12, -40341101);
			c = ff(c, d, a, b, k[14], 17, -1502002290);
			b = ff(b, c, d, a, k[15], 22, 1236535329);

			a = gg(a, b, c, d, k[1], 5, -165796510);
			d = gg(d, a, b, c, k[6], 9, -1069501632);
			c = gg(c, d, a, b, k[11], 14, 643717713);
			b = gg(b, c, d, a, k[0], 20, -373897302);
			a = gg(a, b, c, d, k[5], 5, -701558691);
			d = gg(d, a, b, c, k[10], 9, 38016083);
			c = gg(c, d, a, b, k[15], 14, -660478335);
			b = gg(b, c, d, a, k[4], 20, -405537848);
			a = gg(a, b, c, d, k[9], 5, 568446438);
			d = gg(d, a, b, c, k[14], 9, -1019803690);
			c = gg(c, d, a, b, k[3], 14, -187363961);
			b = gg(b, c, d, a, k[8], 20, 1163531501);
			a = gg(a, b, c, d, k[13], 5, -1444681467);
			d = gg(d, a, b, c, k[2], 9, -51403784);
			c = gg(c, d, a, b, k[7], 14, 1735328473);
			b = gg(b, c, d, a, k[12], 20, -1926607734);

			a = hh(a, b, c, d, k[5], 4, -378558);
			d = hh(d, a, b, c, k[8], 11, -2022574463);
			c = hh(c, d, a, b, k[11], 16, 1839030562);
			b = hh(b, c, d, a, k[14], 23, -35309556);
			a = hh(a, b, c, d, k[1], 4, -1530992060);
			d = hh(d, a, b, c, k[4], 11, 1272893353);
			c = hh(c, d, a, b, k[7], 16, -155497632);
			b = hh(b, c, d, a, k[10], 23, -1094730640);
			a = hh(a, b, c, d, k[13], 4, 681279174);
			d = hh(d, a, b, c, k[0], 11, -358537222);
			c = hh(c, d, a, b, k[3], 16, -722521979);
			b = hh(b, c, d, a, k[6], 23, 76029189);
			a = hh(a, b, c, d, k[9], 4, -640364487);
			d = hh(d, a, b, c, k[12], 11, -421815835);
			c = hh(c, d, a, b, k[15], 16, 530742520);
			b = hh(b, c, d, a, k[2], 23, -995338651);

			a = ii(a, b, c, d, k[0], 6, -198630844);
			d = ii(d, a, b, c, k[7], 10, 1126891415);
			c = ii(c, d, a, b, k[14], 15, -1416354905);
			b = ii(b, c, d, a, k[5], 21, -57434055);
			a = ii(a, b, c, d, k[12], 6, 1700485571);
			d = ii(d, a, b, c, k[3], 10, -1894986606);
			c = ii(c, d, a, b, k[10], 15, -1051523);
			b = ii(b, c, d, a, k[1], 21, -2054922799);
			a = ii(a, b, c, d, k[8], 6, 1873313359);
			d = ii(d, a, b, c, k[15], 10, -30611744);
			c = ii(c, d, a, b, k[6], 15, -1560198380);
			b = ii(b, c, d, a, k[13], 21, 1309151649);
			a = ii(a, b, c, d, k[4], 6, -145523070);
			d = ii(d, a, b, c, k[11], 10, -1120210379);
			c = ii(c, d, a, b, k[2], 15, 718787259);
			b = ii(b, c, d, a, k[9], 21, -343485551);

			x[0] = add32(a, x[0]);
			x[1] = add32(b, x[1]);
			x[2] = add32(c, x[2]);
			x[3] = add32(d, x[3]);

		}

		var cmn = function(q, a, b, x, s, t){ /**/
			a = add32(add32(a, q), add32(x, t));
			return add32((a << s) | (a >>> (32 - s)), b);
		}

		var ff = function(a, b, c, d, x, s, t){ /**/
			return cmn((b & c) | ((~b) & d), a, b, x, s, t);
		}

		var gg = function(a, b, c, d, x, s, t){ /**/
			return cmn((b & d) | (c & (~d)), a, b, x, s, t);
		}

		var hh = function(a, b, c, d, x, s, t){ /**/
			return cmn(b^c^d, a, b, x, s, t);
		}

		var ii = function(a, b, c, d, x, s, t){ /**/
			return cmn(c^(b | (~d)), a, b, x, s, t);
		}

		var md51 = function(s){ /**/
			var txt = '';
			var n = s.length;
			var state = [1732584193, -271733879, -1732584194, 271733878];
			var i;
			for (i = 64; i <= s.length; i += 64) {
				md5cycle(state, md5blk(s.substring(i - 64, i)));
			}
			s = s.substring(i - 64);
			var tail = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
			for (i = 0; i < s.length; i++)
				tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3);
			tail[i >> 2] |= 0x80 << ((i % 4) << 3);
			if (i > 55) {
				md5cycle(state, tail);
				for (i = 0; i < 16; i++)
					tail[i] = 0;
			}
			tail[14] = n * 8;
			md5cycle(state, tail);
			return state;
		}

		/* there needs to be support for Unicode here,
		 * unless we pretend that we can redefine the MD-5
		 * algorithm for multi-byte characters (perhaps
		 * by adding every four 16-bit characters and
		 * shortening the sum to 32 bits). Otherwise
		 * I suggest performing MD-5 as if every character
		 * was two bytes--e.g., 0040 0025 = @%--but then
		 * how will an ordinary MD-5 sum be matched?
		 * There is no way to standardize text to something
		 * like UTF-8 before transformation; speed cost is
		 * utterly prohibitive. The JavaScript standard
		 * itself needs to look at this: it should start
		 * providing access to strings as preformed UTF-8
		 * 8-bit unsigned value arrays.
		 */
		var md5blk = function(s){ /* I figured global was faster.   */
			var md5blks = [];
			var i; /* Andy King said do it this way. */
			for (i = 0; i < 64; i += 4) {
				md5blks[i >> 2] = s.charCodeAt(i)
					 + (s.charCodeAt(i + 1) << 8)
					 + (s.charCodeAt(i + 2) << 16)
					 + (s.charCodeAt(i + 3) << 24);
			}
			return md5blks;
		}

		var hex_chr = '0123456789abcdef'.split('');

		var rhex = function(n){ /**/
			var s = '';
			var j = 0;
			for (; j < 4; j++)
				s += hex_chr[(n >> (j * 8 + 4)) & 0x0F]
				 + hex_chr[(n >> (j * 8)) & 0x0F];
			return s;
		}

		var hex = function(x){ /**/
			for (var i = 0; i < x.length; i++)
				x[i] = rhex(x[i]);
			return x.join('');
		}

		var md5hash = function(s){ /**/
			return hex(md51(s));
		}

		/* this function is much faster,
		so if possible we use it. Some IEs
		are the only ones I know of that
		need the idiotic second function,
		generated by an if clause.  */

		var add32 = function(a, b){ /**/
			return (a + b) & 0xFFFFFFFF;
		}

		if (md5hash('hello') != '5d41402abc4b2a76b9719d911017c592') {
			var add32 = function(x, y){ /**/
				var lsw = (x & 0xFFFF) + (y & 0xFFFF);
				var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
				return (msw << 16) | (lsw & 0xFFFF);
			}
		}

		return (typeof(str) == 'object' ? md5hash(JSON.stringify(str)) : md5hash(str));
	},

	/*
	 *	UI translation
	 *	--------------
	 *	Usage:
	 *	Translate the entire UI whenever DOM mutated:
	 *		AppGiniPlugin.Translate.live();
	 *		
	 *	Execute callback when language files are ready:
	 *		AppGiniPlugin.Translate.ready(callback);
	 *	
	 *	Translate the entire UI:
	 *	    AppGiniPlugin.Translate.ui(callback);
	 *	    
	 *	Return translation of a single key, after performing variable replacements:
	 *	    translation = AppGiniPlugin.Translate.word('key', replacements);
	 *	    
	 *	Change language:
	 *		AppGiniPlugin.Translate.setLang(lang);
	 *		// you should then call AppGiniPlugin.Translate.ui() to apply the new language
	 *	    
	 *	Current language is stored in AppGiniPlugin.selectedLanguage (default is 'en')
	 *	    
	 *	Note: AppGiniPlugin.Translate.word() will fail if called before AppGiniPlugin.Translate.ui()
	*/
	Translate: {
		live: function() {
			// call only once!
			if(this._liveCalled !== undefined) return;
			this._liveCalled = true;

			var body = $j('body').get(0), config = { attributes: true, childList: true, subtree: true };

			// auto translate UI whenever DOM changes
			var observer = new MutationObserver(function(list, observer) {
				// prevent re-triggering MutationObserver while translating DOM!
				observer.disconnect();

				// translate UI
				AppGiniPlugin.Translate.ui(function() {
					// start observing (again!)
					observer.observe(body, config);
				});
			});

			// trigger MutationObserver
			observer.observe(body, config);
			$j('body').append('<span class="hidden">MutationObserver.trigger()</span>');
		},
		setLang: function(lang) {
			localStorage.setItem('AppGiniPlugin.selectedLanguage', lang);
			AppGiniPlugin.selectedLanguage = lang;
		},
		ui: function(doneCallback) {
			var self = this, parent = AppGiniPlugin;
			
			var error = function(err) { console.error(err); return false; },
				baseLangFile = function(lang) { return '../plugins-resources/language/' + lang + '.js';	},
				pluginLangFile = function(lang) { return 'language/' + lang + '.js'; },
				loadLanguageFiles = function(callback) {
					parent.selectedLanguage = localStorage.getItem('AppGiniPlugin.selectedLanguage') || 'en';

					// If the stored language files are already loaded, execute callback and quit
					if(parent.language !== undefined && parent.language[parent.selectedLanguage] !== undefined) {
						callback();
						return;
					}

					// load plugins base default language file (en) ...
					$j.getScript(baseLangFile('en'))
					.fail(function() {
						error('Error loading ' + baseLangFile('en'));
					})
					
					// next, load plugin default language file
					.done(function() {
						self._baseLangFileEn = true;
						$j.getScript(pluginLangFile('en'))
						.fail(function() {
							error('Error loading ' + pluginLangFile('en'));
						})

						// then configured language file (if not 'en') for plugins base
						.done(function() {
							self._pluginLangFileEn = true;
							if(parent.selectedLanguage == 'en') {
								self._baseLangFile = true;
								self._pluginLangFile = true;
								callback();
								return;
							}

							$j.getScript(baseLangFile(parent.selectedLanguage))
							.fail(function() {
								error('Error loading ' + baseLangFile(parent.selectedLanguage));
							})

							// then configured language file for plugin
							.done(function() {
								self._baseLangFile = true;
								$j.getScript(pluginLangFile(parent.selectedLanguage))
								.fail(function() {
									error('Error loading ' + pluginLangFile(parent.selectedLanguage));
								})
								.always(function() {
									self._pluginLangFile = true;
								})
							})

							// finally, execute the callback (whether configured language loaded or not)
							.always(callback);
						})
					})
				};

			loadLanguageFiles(function() {
				var els = $j('.language');
				for(var i = 0; i < els.length; i++) {
					var el = els.eq(i);
					var replace = el.data();
					if(undefined === replace.key) continue;

					var translation = self.word(replace.key, replace);
					if(translation !== false) el.html(translation);
				}
				
				// set document direction
				var rtl = parent.language[parent.selectedLanguage].rtl;
				if(rtl === undefined) rtl = false;
				$j('body').css('direction', rtl ? 'rtl' : 'ltr');

				// After translation is done, call doneCallback
				if(typeof(doneCallback) == 'function') {
					doneCallback();
				}
			});
		},
		word: function(key, replace) {
			var self = this, parent = AppGiniPlugin;

			if(undefined === parent.selectedLanguage) return false;
			if(undefined === parent.language[parent.selectedLanguage]) return false;

			// try to find the translation in the selected language
			var translation = parent.language[parent.selectedLanguage][key];
			if(undefined === replace || typeof(replace) !== 'object' || replace.length !== undefined) replace = false;

			// if that fails, try the english translation
			if(undefined === translation) {
				translation = parent.language['en'][key];
				if(undefined === translation) {
					console.error('Translation of ' + key + ' not defined.');
					return false;
				}
			}

			if(false !== replace)
				// replace placeholders with provided replacements ...
				for(var r in replace) {
					if(!replace.hasOwnProperty(r)) continue;
					// do a global (all occurances) replace of %{r}% with corresponding value
					translation = translation.replace(new RegExp('%' + r + '%', 'g'), replace[r]);
				}

			return translation;
		},
		ready: function(callback) {
			var self = this, parent = AppGiniPlugin;
			// poll every 50 msec until language ready
			if(
				self._baseLangFileEn === undefined || 
				self._pluginLangFileEn === undefined ||
				self._baseLangFile === undefined || 
				self._pluginLangFile === undefined
			) {
				setTimeout(function() { self.ready(callback) }, 50);
				return;
			}

			callback();
		}
	}
};
