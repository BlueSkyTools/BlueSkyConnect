<?php
	include(dirname(__FILE__).'/../plugins-resources/loader.php');

	class mass_update extends AppGiniPlugin {
		public function __construct($config = array()) {
			parent::__construct($config);
		}

		public function command_needs_select2($tn, $cmd) {
			if($cmd->value != 'allowUserToSpecify') return false;

			$fn = $cmd->field;
			$field = $this->field($tn, $fn);

			// if field is an options list, it needs a select2
			if(strlen((string) $field->CSValueList)) return true;

			// if field is a non-autofill lookup, it needs a select2
			if(
				((string) $field->autoFill) == 'False' && 
				strlen((string) $field->parentTable) && 
				(
					strlen((string) $field->parentCaptionField) ||
					strlen((string) $field->parentCaptionField2)
				)
			) return true;

			return false;
		}

		public function command_needs_richedit($tn, $cmd) {
			if($cmd->value != 'allowUserToSpecify') return false;

			$fn = $cmd->field;
			$field = $this->field($tn, $fn);

			return ((string) $field->htmlarea == 'True'); 
		}

		/**
		 * Retrieve mass update commands of a given table as array of command objects
		 *
		 * @param      string  $tn     table name
		 *
		 * @return     array  array of command objects for given table
		 */
		public function commands_array($tn) {
			$mu_node = $this->get_table_plugin_node($tn);

			// if we have no configured commands for this table, move on
			if($mu_node === false) return array();
			if(!isset($mu_node->command_details)) return array();

			$commands = json_decode($mu_node->command_details);
			if(!is_array($commands) || !count($commands)) return array();

			return array_values($commands);
		}
	}
