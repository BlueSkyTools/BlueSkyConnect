/**
 * Usage:
 * 
 * var proj = AppGiniPlugin.project(projectObject);
 * proj.get().table[]
 * proj.getFieldIndex()
 * ...
 */

AppGiniPlugin.project = function(prj) {
	// validate project
	if(prj == undefined) return false;
	if(prj.table == undefined) return false;

	if(prj.table.length == undefined)
		prj.table = $j.makeArray(prj.table);

	/* fix tables that have only one field returning 'field' as an object rather than array */
	for(var i = 0; i < prj.table.length; i++) {
		if(!Array.isArray(prj.table[i].field))
			prj.table[i].field = [prj.table[i].field];
	}

	return {

		/**
		 * Retrieve the project as raw object
		 *
		 * @return     {object}  project
		 */
		get: function() { return prj; },

		/**
		 * The purpose of this function is to provide backward compatibility with
		 * legacy code that calls other functions passing a table index instead
		 * of a table name .. the function detects whether passed param is a table
		 * name or index and returns the index.
		 *
		 * @param      {(string|int)}  tableNameOrIndex  The table name or index
		 * @return     {(int|boolean)}     table index or false if invalid
		 */
		getTableIndex: function(tableNameOrIndex) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getTableIndex', tableNameOrIndex);
			if(prj.table[tableNameOrIndex] != undefined) return tableNameOrIndex;

			for(var ti = 0; ti < prj.table.length; ti++) {
				if(prj.table[ti].name == tableNameOrIndex) return ti;
			}

			return false;
		},

		/**
		 * Retrieve the caption of a table or a field
		 *
		 * @param      {(string|int)}  tableIndex  The table index or name
		 * @param      {string}  fieldName   Optional, the field name
		 * @return     {string}  The caption of the table if no field name provided, or the field caption if field name provided.
		 */
		getCaption: function(tableIndex, fieldName) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getCaption', tableIndex, fieldName);
			
			var ti = this.getTableIndex(tableIndex);
			if(ti === false) return '';

			var table = prj.table[ti];
			
			if(fieldName === undefined)
				return (table.caption != undefined ? table.caption : '');
			
			for(var fi = 0; fi < table.field.length; fi++) {
				if(table.field[fi].name != fieldName ) continue;
				return table.field[fi].caption;
			}
			
			return '';
		},

		/**
		 * Gets the field index.
		 *
		 * @param      {(string|int)}        tableIndex  The table index or name
		 * @param      {string}            fieldName   The field name
		 * @return     {(boolean|number)}  The field index, or false on error.
		 */
		getFieldIndex: function(tableIndex, fieldName) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getFieldIndex', tableIndex, fieldName);

			tableIndex = this.getTableIndex(tableIndex);
			if(tableIndex === false) return false;

			var field = prj.table[tableIndex].field;
			
			for(var fi = 0; fi < field.length; fi++) {
				if(field[fi].name == fieldName) return fi; 	
			}

			return false;
		},

		isLookupField: function(tableIndex, fieldName) {
			AppGiniPlugin.debug('AppGiniPlugin.project.isLookupField', tableIndex, fieldName);
			
			tableIndex = this.getTableIndex(tableIndex);
			if(tableIndex === false) return false;

			var fi = this.getFieldIndex(tableIndex, fieldName);
			if(fi === false) return false ;
			
			if(typeof(prj.table[tableIndex].field[fi].parentTable) != "string")
				return false;

			if(!prj.table[tableIndex].field[fi].parentTable.length) return false;
			
			return true;
		},

		getLookupTableName: function(tableIndex, fieldName) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getLookupTable', tableIndex, fieldName);

			tableIndex = this.getTableIndex(tableIndex);
			if(tableIndex === false) return '';

			var fi = this.getFieldIndex(tableIndex, fieldName);
			var field = prj.table[tableIndex].field[fi];
			if(field == undefined) return '';

			return (typeof(field.parentTable) != 'string' ? '' : field.parentTable);
		},

		getLookupTable: function(ti, fn) {

			return this.getLookupTableName(ti, fn);
		},

		getParentCaptionFieldName: function(tableIndex, fieldName) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getLookupValue', tableIndex, fieldName);
		
			tableIndex = this.getTableIndex(tableIndex);
			if(tableIndex === false) return '';

			var fi = this.getFieldIndex(tableIndex, fieldName);
			if(fi === false) return '';

			var pcf = prj.table[tableIndex].field[fi].parentCaptionField;

			return (typeof(pcf) == 'string' ? pcf : '');
		},

		getLookupValue: function(ti, fn) {
			
			return this.getParentCaptionFieldName(ti, fn);
		},

		getTable: function(tni) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getTable', tni);

			var ti = this.getTableIndex(tni);
			if(ti === false) return; // undefined

			return prj.table[ti];
		},

		getField: function(tni, fni) {
			AppGiniPlugin.debug('AppGiniPlugin.project.getField', tni, fni);

			var ti = this.getTableIndex(tni);
			if(ti === false) return; // undefined

			var fi = fni;
			if(typeof(fni) == 'string') fi = this.getFieldIndex(tni, fni);
			if(fi === false) return; // undefined

			return prj.table[ti].field[fi];
		},

		getTableByName: function(tni) {
			
			return this.getTable(tni);
		},

		getProjectPlugin: function(pluginName) {
			prj.plugins = prj.plugins || {};
			prj.plugins[pluginName] = prj.plugins[pluginName] || {}
			return prj.plugins[pluginName];
		},

		setProjectPlugin: function(pluginName, data) {
			// create plugin node if necessary
			var pd = this.getProjectPlugin(pluginName);
			prj.plugins[pluginName] = data;
		},

		getTablePlugin: function(tni, pluginName) {
			var t = this.getTable(tni);
			if(t === undefined) return; // undefined

			t.plugins = t.plugins || {};
			t.plugins[pluginName] = t.plugins[pluginName] || {};
			return t.plugins[pluginName];
		},

		setTablePlugin: function(tni, pluginName, data) {
			var t = this.getTable(tni);
			if(t === undefined) return false;

			t.plugins = t.plugins || {};
			t.plugins[pluginName] = data;
			return true;
		}
	};
}