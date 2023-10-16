AppGiniPlugin.sampleComponent = function(options) {
	/*
	 * This is what we mainly need a component to do:
	 * 1. Attach to a specific DOM node, only once
	 * 2. If called for a node that already has the component, return the one already attached
	 * 3. Store state reliably
	 * 4. State can be modified only through the component
	 * 5. Expose some methods to control the component
	 * 
	 * Below is an example component. We should copy that file and use as
	 * a starting point to develop page components. What we should change below:
	 * -------------------------------------------------------------------------
	 * 1. value of _componentCSSClass and _componentDataKey
	 * 2. list of "options that can't be overidden" in _main()
	 * 3. list of methods to return in publicMethods, passing options to each,
	 *    as retrieved from this.storedOptions()
	 * 4. list of defaults in _defaultOptions()
	 * 5. the way _render() constructs the component in the page
	 * 6. and of course the name of the component at line 1 above
	 *    and the component file name :)
	*/

	var _componentCSSClass = 'sample-component',
	_componentDataKey = 'sampleComponent',

	_main = function(options) {
		if(!_optionsContainerIsValid(options)) return false;
		options = _defaultOptions(options);
		
		if(_containerHasComponent(options.container)) {
			var stored = $j(options.container).data(_componentDataKey);
			// if we want to preserve the value of any part of options,
			// we should do so here like the below example:
			
			// options that can't be overidden
			options.array1 = _deepCopy(stored.array1, true);
			options.callback1 = stored.callback1;
		}

		_render(options);

		publicMethods.container = options.container;

		return publicMethods;
	},

	publicMethods = {
		container: '',
		storedOptions: function() { return _deepCopy($j(this.container).data(_componentDataKey)); },
		redraw:  function() {  _render(this.storedOptions()); },
		destroy: function() { _destroy(this.storedOptions()); }
	},

	_defaultOptions = function(options) {
		/* this is the default state of component, to be amended with passed options */
		var defaults = {
			container: '',
			setting1: 'example-value',
			config1: {
				example1: 'val1',
				example2: ''
			},
			array1: [
				{ id: 1, title: 'title 1'},
				{ id: 2, title: 'title 2'}
			],
			callback1: function(id) {}
		};

		return $j.extend({}, defaults, options);
	},

	_optionsContainerIsValid = function(options) {
		if(options == undefined) return false;
		if(options.container == undefined) return false;
		if($j(options.container).length != 1) return false;
		if($j(options.container).parents('.' + _componentCSSClass).length > 0) return false;

		return true;
	},

	_containerHasComponent = function(container) {
		return $j(container).data(_componentDataKey) != undefined;
	},

	_deepCopy = function(obj, asArray) {
		if(asArray == undefined) asArray = false;
		var objCopy = $j.extend(true, {}, obj);
		if(!asArray) return objCopy;

		var arrCopy = [];
		for(var i in objCopy) arrCopy.push(objCopy[i]);
		return arrCopy;
	},

	// build the component and attach it to container
	_render = function(options) {
		if(options == undefined || options.container == undefined) return;
		var component = $j(options.container);
		if(!component.length) return;

		component
			.empty()
			.addClass(_componentCSSClass)
			.data(_componentDataKey, _deepCopy(options));

		var content = $j('<div class="sample-component-title">' + options.setting1 + '</div>');
		content.appendTo(component)
		
		for(var i = 0; i < options.array1.length; i++) {
			$j('<div class="sample-component-item">' + options.array1[i].title + '</div>')
				.appendTo(component);
		}
	},

	_destroy = function(options) {
		if(options == undefined || options.container == undefined) return;
		var component = $j(options.container);
		if(!component.length) return;

		component
			.empty()
			.removeClass(_componentCSSClass)
			.removeData(_componentDataKey);
	};

	return _main(options);
}