AppGiniPlugin.itemsList = function(options) {

	var _componentCSSClass = 'appgini-plugin-items-list',
	_componentDataKey = 'itemsList',

	_main = function(options) {
		if(!_optionsContainerIsValid(options)) return false;
		options = _defaultOptions(options);
		
		if(_containerHasComponent(options.container)) {
			var stored = $j(options.container).data(_componentDataKey);
			
			// options that can't be overidden
			options.items = _deepCopy(stored.items, true);
			options.add = stored.add;
			options.itemId = stored.itemId;
			options.itemTitle = stored.itemTitle;
			options.edit = stored.edit;
			options.delete = stored.delete;
			options.itemLeft = stored.itemLeft;
			options.itemRight = stored.itemRight;
			options.itemDetails = stored.itemDetails;
		}

		_render(options);

		publicMethods.container = options.container;
		return publicMethods;
	},

	/* before returning public methods, make sure to set value of container */
	publicMethods = {
		container: '',
		storedOptions: function() { return _deepCopy($j(this.container).data(_componentDataKey)); },
		itemById: function(id){ return _itemById(this.storedOptions(), id); },
		items: function(newItems){ return _items(this.storedOptions(), newItems); },
		add: function(item){ return _addItem(this.storedOptions(), item); },
		update: function(id, updates){ return _updateItem(this.storedOptions(), id, updates); },
		remove: function(id){ return _removeItem(this.storedOptions(), id); },
		redraw:  function() {  _render(this.storedOptions()); },
		destroy: function() { _destroy(this.storedOptions()); }
	},

	_defaultOptions = function(options) {
		/* this is the default state of component, to be amended with passed options */
		var defaults = {
			container: '',

			/* array of items to display */
			items: [],

			/* message to display if items array is empty */
			noItemsText: 'This table has no items configured yet.',
			noItemsAction: 'Create one now!',

			/* label for [add new item] button */
			addLabel: 'Add item',
			/* handler for [add new item] button */
			add: function() { console.log('No add handler function defined!'); },

			/* function to retrieve item id from item */
			itemId: function(item) { console.log('No itemId function defined!'); },

			/* function to retrieve item title from item */
			itemTitle: function(item) { console.log('No itemTitle function defined!'); },

			/* handler for [edit] button */
			edit: function(id) { console.log('No edit handler function defined!'); },

			/* handler for [delete] button */
			delete: function(id) { console.log('No delete handler function defined!'); },
			/* confirmation text when deleting an item */
			deleteConfirmation: 'Are you sure you want to delete this item?',

			/* function to return HTML to display to the left of the item details */
			itemLeft: function(item) { return ''; },
			/* function to return HTML to display to the right of the item details */
			itemRight: function(item) { return ''; },
			/* function to return item details as an object (key/value pairs) */
			itemDetails: function(item) { return item; }

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

	_attr = function(str) {
		return ('' + str)
			.replace(/&/g, '&amp;')
			.replace(/'/g, '&#39;')
			.replace(/"/g, '&#34;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	},

	// build the component and attach it to container
	_render = function(options) {

		var appendContainerHeaderTo = function(container) {
			/* do we already have a container header? */
			if(container.find('.items-list-header').length)
				return container.find('.items-list-header').eq(0);

			/* create header div */
			var containerHeader = $j('<div class="items-list-header"></div>');

			/* create 'Add' button */
			$j('<button type="button" class="btn btn-success">' +
					'<i class="glyphicon glyphicon-plus"></i> ' + options.addLabel +
			'</button>')
			.click(options.add)
			.appendTo(containerHeader);

			/* create summary/details toggle */
			var detailsToggle = $j('<div class="btn-group pull-right"></div>');
			$j('<button type="button" class="btn btn-default summary-list">' +
				'<i class="glyphicon glyphicon-list"></i>' +
			'</button>')
			.click(function() {
				toggleListDetails(container);
			})
			.appendTo(detailsToggle);

			$j('<button type="button" class="btn btn-default active details-list">' +
				'<i class="glyphicon glyphicon-th-list"></i>' +
			'</button>')
			.click(function() {
				toggleListDetails(container);
			})
			.appendTo(detailsToggle);

			detailsToggle.appendTo(containerHeader);
			
			$j('<div class="clearfix"></div>').appendTo(containerHeader);
			containerHeader.appendTo(container);

			// handle edit item button
			container.on('click', '.edit-item', function() {
				var itemId = $j(this).data('id');
				options.edit(itemId);
			});

			// handle delete item button
			container.on('click', '.delete-item', function() {
				var itemId = $j(this).data('id');
				if(!confirm(options.deleteConfirmation)) return false;

				$j('.btn[data-id="' + itemId + '"]')
					.prop('disabled', true)
					.parents('.panel-success')
					.addClass('panel-danger')
					.removeClass('panel-success');

				options.delete(itemId);
			});

			// mark container as an itemsList through CSS class
			container.addClass(_componentCSSClass);

			return containerHeader;
		},

		toggleListDetails = function(container) {
			if(container.find('.btn.details-list').hasClass('active')) {
				container.find('.btn.summary-list').addClass('active')
				container.find('.btn.details-list').removeClass('active')
			} else {
				container.find('.btn.summary-list').removeClass('active')
				container.find('.btn.details-list').addClass('active')
			}
			applyListSummaryDetails(container);
		},

		applyListSummaryDetails = function(container) {
			if(container.find('.btn.details-list').hasClass('active')) {
				container.find('.items-list').removeClass('hidden-panel-body');
				container.find('.items-list .panel-body').removeClass('hidden');
			} else {
				container.find('.items-list').addClass('hidden-panel-body');
				container.find('.items-list .panel-body').addClass('hidden');
			}
		},

		getEmptyItemsList = function(container) {
			/* do we already have an items list? */
			var itemsList = container.find('.items-list');
			if(itemsList.length) {
				itemsList.empty();
				return itemsList.eq(0);
			}

			/* create items list */
			itemsList = $j('<div class="items-list"></div>');
			itemsList.appendTo(container);
			return itemsList;
		},

		getItem = function(item) {
			var id = options.itemId(item),
				title = options.itemTitle(item),
				itemLeft = '' + options.itemLeft(item),
				itemRight = '' + options.itemRight(item),
				details = options.itemDetails(item),
				detailsKeys = function() {
					var html = '';
					for(k in details) {
						html += '<div class="item-details-key">' + k + '</div>';
					}
					return html;
				},
				detailsValues = function() {
					var html = '';
					for(k in details) {
						html += '<div class="item-details-value">' + details[k] + '</div>';
					}
					return html;
				};

				if(itemLeft)   itemLeft = '<td class="item-left">'  + itemLeft  + '</td>';
				if(itemRight) itemRight = '<td class="item-right">' + itemRight + '</td>';

			return $j(
				'<div class="panel panel-success item">' +
					'<div class="panel-heading">' +
						'<h3 class="panel-title">' +
							'<i class="glyphicon glyphicon-list-alt hspacer-md"></i>' +
							'<span class="panel-title-text">' + title + '</span>' +
							'<div class="btn-group pull-right">' +
								'<button data-id="' + _attr(id) + '" class="btn btn-sm edit-item btn-default" type="button">' +
									'<i class="glyphicon glyphicon-pencil text-primary"></i>' +
									' Edit' +
								'</button>' +
								'<button data-id="' + _attr(id) + '" class="btn btn-sm delete-item btn-default" type="button">' +
									'<i class="glyphicon glyphicon-trash text-danger"></i>' +
									' Delete' +
								'</button>' +
							'</div>' +
							'<div class="clearfix"></div>' +
						'</h3>' +
					'</div>' +
					'<div class="panel-body">' +
						'<table class="table">' +
							'<tr>' +
								itemLeft +
								'<td class="item-details-keys">' + detailsKeys() + '</td>' +
								'<td class="item-details-values">' + detailsValues() + '</td>' +
								itemRight +
							'</tr>' +
						'</table>' +
					'</div>' +
				'</div>'
			);
		},

		showDesertNotification = function(itemsList) {
			var desert = $j('<div class="alert alert-warning">' + options.noItemsText + '</div>');
			$j('<button class="btn btn-success hspacer-lg">' + options.noItemsAction + '</button>')
				.click(options.add)
				.appendTo(desert);
			desert.appendTo(itemsList);

			return true;
		};

		if(options == undefined || options.container == undefined) return;
		var component = $j(options.container);
		if(!component.length) return;

		component
			.addClass(_componentCSSClass)
			.data(_componentDataKey, _deepCopy(options));

		appendContainerHeaderTo(component);
		var itemsList = getEmptyItemsList(component);

		if(!options.items.length) return showDesertNotification(itemsList);
		
		for(var i = 0; i < options.items.length; i++) {
			getItem(options.items[i]).appendTo(itemsList);
			applyListSummaryDetails(component);
		}

		return true;
	},

	_destroy = function(options) {
		if(options == undefined || options.container == undefined) return;
		var component = $j(options.container);
		if(!component.length) return;

		component
			.empty()
			.off('click')
			.removeClass(_componentCSSClass)
			.removeData(_componentDataKey);
	},

	_itemById = function(options, id) {
		var i = _itemIndexById(options, id);
		if(i == -1) return;

		// return a copy of, rather than a reference to, the item
		return _deepCopy(options.items[i]);
	},

	_itemIndexById = function(options, id) {
		for(var i = 0; i < options.items.length; i++) {
			if(options.itemId(options.items[i]) == id)
				return i;
		}

		return -1; // not found
	},

	_items = function(options, newItems) {
		if(newItems == undefined) return _deepCopy(options.items, true);
		if(newItems.length == undefined) return false;

		options.items = _deepCopy(newItems, true);
		_render(options);

		publicMethods.container = options.container;
		return publicMethods;
	},

	_addItem = function(options, item) {
		publicMethods.container = options.container;

		// make sure item has an ID
		var id = options.itemId(item);
		if(id == undefined) return publicMethods;

		// prevent duplicate item IDs
		if(_itemIndexById(options, id) != -1) return publicMethods;

		options.items.push(_deepCopy(item));
		_render(options);
		return publicMethods;
	},

	_updateItem = function(options, id, updates) {
		publicMethods.container = options.container;
		var i = _itemIndexById(options, id);
		if(i == -1) return publicMethods;

		options.items[i] = $j.extend(true, {}, options.items[i], updates);
		_render(options);
		return publicMethods;
	},

	_removeItem = function(options, id) {
		publicMethods.container = options.container;
		var i = _itemIndexById(options, id);
		if(i == -1) return publicMethods;

		options.items.splice(i, 1);
		_render(options);

		return publicMethods;
	};

	return _main(options);
}