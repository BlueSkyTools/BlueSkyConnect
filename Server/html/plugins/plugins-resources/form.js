/**
 * @param formSelector the CSS selector of the form, typically '#form-id'
 * @param idPrefix optional string to be prepended to all element ids, default is empty string
 */
AppGiniPlugin.form = function(formSelector, idPrefix) {
	/*
	 element id attribute is _prefixed in DOM
	 but is _notPrefixed in keys of _elements or in any other internal storage
	 */
	idPrefix = idPrefix || '';
	if(typeof(idPrefix) != 'string') idPrefix = '';

	/* store form elements statically */
	AppGiniPlugin.formElements = AppGiniPlugin.formElements || {};
	AppGiniPlugin.formElements[formSelector] = AppGiniPlugin.formElements[formSelector] || {};

	/* store form idPrefix values statically */
	AppGiniPlugin.formIdPrefix = AppGiniPlugin.formIdPrefix || {};
	AppGiniPlugin.formIdPrefix[formSelector] = AppGiniPlugin.formIdPrefix[formSelector] || idPrefix;

	/* retrieve stored idPrefix */
	idPrefix = AppGiniPlugin.formIdPrefix[formSelector];

	/* form elements in the format id: { props }, .. */
	var _elements = AppGiniPlugin.formElements[formSelector],

	_prefixPattern = function() {
		return new RegExp('^' + idPrefix + '-');
	},

	_notPrefixed = function(id) {
		if(!idPrefix) return id;
		return id.replace(_prefixPattern(), '');
	},

	_prefixed = function(id) {
		if(!idPrefix) return id;
		if(id.match(_prefixPattern())) return id; // already prefixed
		return idPrefix + '-' + id;
	},

	_render = function(id) {
		var e = _elements[id], html = {};

		var cssClasses = function(classes) {
			if(typeof(classes) == 'string') return classes;
			if(typeof(classes) == 'object' && classes.length != undefined)
				return classes.join(' ');
			return '';
		};

		var appendHelpBlock = function(div) {
			if(e.help != undefined) {
				var help = $j('<span></span>');
				help.addClass('help-block ' + cssClasses(e.helpClasses));
				help.html(e.help);
				if(e.helpId != undefined) help.attr('id', e.helpId);
				help.appendTo(div);
			}
		};

		var appendLabel = function(div) {
			var label = $j('<label></label>');
			label.html(e.label);
			label.addClass('control-label ' + cssClasses(e.labelClasses));
			label.attr('for', e.id);
			label.attr('title', e.labelTitle);
			label.appendTo(div);
		};

		var prepareFormGroup = function(control) {
			var formGroup = $j('<div></div>');
			formGroup.addClass('form-group ' + cssClasses(e.groupClasses));

			if(e.type != 'checkbox') appendLabel(formGroup);

			if(e.controlWrapperClasses.length || e.type == 'checkbox') {
				wrapper = $j('<div></div>');
				wrapper.addClass('control-wrapper ' + cssClasses(e.controlWrapperClasses));
				if(e.type == 'checkbox') {
					wrapper.addClass('checkbox');
					var label = $j('<label></label>');
					control.prependTo(label);
					var labelSpan = $j('<span></span>');
					labelSpan.html(e.label);
					labelSpan.addClass('checkbox-label');
					labelSpan.appendTo(label);

					label.appendTo(wrapper);

					if(e.labelClasses.length) {
						var offset = $j('<div></div>');
						offset.addClass(cssClasses(e.labelClasses));
						offset.prependTo(formGroup);
					}
				} else {
					control.appendTo(wrapper);
				}
				appendHelpBlock(wrapper);
				wrapper.appendTo(formGroup);
			} else {
				control.appendTo(formGroup);
				appendHelpBlock(formGroup);
			}

			formGroup.appendTo(formSelector);
		};

		switch(e.type) {
			case 'checkbox':
				html.control = $j('<input type="checkbox">');
				html.control.attr('id', _prefixed(e.id));
				html.control.attr('name', e.name);
				html.control.attr('value', 1);
				html.control.prop('checked', e.init);
				html.control.prop('required', e.required);
				html.control.addClass(cssClasses(e.controlClasses));

				prepareFormGroup(html.control);
				break;
			
			/*
				TODO
				considerations:
				inline or one-per-line? .radio, .radio-inline
				one common label
				for each option:
					id, text (id is actually the value attribute)
					control id? derived from common id, adding -1, -2, .. etc?

				structure for one-per-line:
					.form-group
						div.labelClasses (contains label)
						.control-wrapper.radio + controlWrapperClasses
							label
								input.controlClasses[name, id, value, checked]
								span.radio-label
						.control-wrapper.radio + controlWrapperClasses
							...
						.help-block + helpClasses

				structure for inline:
					.form-group
						div.labelClasses (contains label)
						.control-wrapper + controlWrapperClasses
							label.radio-inline
								input.controlClasses[name, id, value, checked]
								span.radio-label
							label.radio-inline
								...
						.help-block + helpClasses

			*/
			case 'radio':
				break;

			case 'hidden':
				html.control = $j('<input type="hidden">');
				html.control.attr('id', _prefixed(e.id));
				html.control.attr('name', e.name);
				html.control.attr('value', e.init);
				html.control.appendTo(formSelector);
				break;

			case 'select':
				html.control = $j('<select></select>');
				html.control.addClass('form-control ' + cssClasses(e.controlClasses));
				html.control.attr('id', _prefixed(e.id));
				html.control.attr('name', e.name);
				html.control.prop('required', e.required);

				prepareFormGroup(html.control);
				e.setOptions(e.options, e.init);
				break;
			
			case 'textarea':
				html.control = $j('<textarea></textarea>');
				html.control.addClass('form-control ' + cssClasses(e.controlClasses));
				html.control.attr('id', _prefixed(e.id));
				html.control.attr('name', e.name);
				html.control.attr('rows', e.rows);
				if(e.maxLength) html.control.attr('maxlength', e.maxLength);
				html.control.prop('required', e.required);
				html.control.val(e.init);

				prepareFormGroup(html.control);
				break;
			
			/*
				TODO:
				support for HTML5 inputs:
				datetime, datetime-local, date, month, time, week, number, email, url, search, tel, and color
			*/
			case 'text':
			default:
				html.control = $j('<input type="text">');
				html.control.addClass('form-control ' + cssClasses(e.controlClasses));
				html.control.attr('id', _prefixed(e.id));
				html.control.attr('name', e.name);
				html.control.attr('value', e.init);
				html.control.prop('required', e.required);
				if(e.maxLength) html.control.attr('maxlength', e.maxLength);

				prepareFormGroup(html.control);
				break;
		}
	},

	thisForm = {
		addHtml: function(html) {
			$j(html).appendTo(formSelector);
			return this;
		},

		addSeparator: function(widthUnits) {
			if(
				undefined == widthUnits || 
				typeof(widthUnits) != 'number' || 
				widthUnits < 1 || 
				widthUnits > 12
			) widthUnits = 10;
			var offset = (12 - widthUnits) / 2;
			this.addHtml('<div class="row"><div class="col-xs-offset-' + offset + ' col-xs-' + widthUnits + '"><hr></div></div>');
			return this;
		},

		toObject: function() {
			var obj = {};

			for(var id in _elements) {
				if(!_elements.hasOwnProperty(id)) continue;
				if(_elements[id].type == 'checkbox') {
					obj[id] = _elements[id].jDom().prop('checked');
					continue;
				}
				obj[id] = _elements[id].jDom().val();
			}

			return obj;
		},

		add: function(props) {
			if(undefined == props.id) return false; // id must be provided
			if(undefined != _elements[props.id]) return false; // element with same id already exists

			_elements[props.id] = $j.extend({}, {
				name: props.id,
				init: '',
				groupClasses: [], /* CSS classes to add to .form-group */
				labelClasses: [], /* CSS classes to add to .control-label */
				controlClasses: [], /* CSS classes to add to input element */
				helpClasses: [], /* CSS classes to add to help block */
				controlWrapperClasses: [], /* CSS classes to add to div that wraps input element */
				label: '',
				labelTitle: '',
				type: 'text', /* text (default), checkbox, select, textarea, radio, hidden */
				help: '',
				helpId: null,
				options: [], /* for selects and radios */
				emptyFirstOption: true, /* for selects */
				noSelectionText: '', /* for selects */
				rows: 5, /* for textareas */
				required: false,
				maxLength: 0, /* for text and textarea, 0 = unlimited */

				display: function(display) {
					if(display === undefined) display = true;

					this.jDom()
						.prop('disabled', !display)
						.parents('.form-group')
						.addClass(display ? '' : 'hidden')
						.removeClass(display ? 'hidden' : '');
				},

				show: function() {
					this.display(true);
				},

				hide: function() {
					this.display(false);
				},

				setOptions: function(options, value, append) {
					if(this.type != 'select') return;

					if(options === undefined) options = [];
					if(typeof(options) != 'object') options = [];

					if(value === undefined) value = null;
					if(append === undefined) append = false;

					var select = this.jDom();

					if(!append) {
						select.empty();
						if(this.emptyFirstOption) {
							// if we don't already have an empty first option, add one
							if(
								!options.length ||
								(options.length && options[0].id !== null && options[0].id !== '')
							)
								options.unshift({ id: '', text: this.noSelectionText });
						}
					}

					for(var i = 0; i < options.length; i++) {
						$j('<option></option>')
							.attr('value', options[i].id)
							.prop('selected', options[i].id == value)
							.text(options[i].text)
							.appendTo(select);
					}
				},

				jDom: function() {
					return $j(formSelector + ' [id="' + _prefixed(this.id) + '"]');
				},

				reset: function() {
					switch(this.type) {
						case 'checkbox':
							this.jDom().prop('checked', this.init).trigger('change');
							break;
						case 'select':
							this.setOptions(this.options, this.init);
							this.jDom().trigger('change');
							break;
						default:
							this.jDom().val(this.init).trigger('change');
					}
				},

				error: function(msg) {
					var hasError = (msg.length > 0);
					var helpBlock = this.jDom().parents('.form-group').find('.help-block');

					// help-block already hidden before applying error?
					var helpBlockHidden = (hasError && helpBlock.hasClass('hidden'));

					this.jDom().parents('.form-group').toggleClass('has-error', hasError);
					helpBlock
						.toggleClass('text-danger', hasError)
						.toggleClass('originally-hidden', helpBlockHidden)
						.html(hasError ? msg : this.help);
					
					if(hasError) {
						this.jDom().focus();
						helpBlock.removeClass('hidden');
					}
					if(!hasError && helpBlock.hasClass('originally-hidden')) helpBlock.addClass('hidden');
				}
			}, props);

			_render(props.id);

			return this;
		},

		reset: function() {
			for(var id in _elements) {
				if(!_elements.hasOwnProperty(id)) continue;
				_elements[id].reset();
				_elements[id].error(''); // remove any previously set error messages
			}

			return this;
		},

		get: function(id) {
			return _elements[id];
		},

		last: function() {
			var lastId, lastEl;
			for(lastId in _elements) {
				if(!_elements.hasOwnProperty(lastId)) continue;
				lastEl = _elements[lastId];
			}
			return lastEl;
		},

		first: function() {
			var firstId, firstEl;
			for(firstId in _elements) {
				if(!_elements.hasOwnProperty(firstId)) continue;
				firstEl = _elements[firstId];
				break;
			}
			return firstEl;
		}
	};

	return thisForm;
}