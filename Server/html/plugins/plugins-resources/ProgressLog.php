<?php

	class ProgressLog{
		private $messages; // array of messages
		private $plugin;
		
		public function __construct($plugin){
			$this->messages = array();
			$this->plugin = $plugin;
		}
		
		/**
		 * add a message to log
		 * @param $message the message to log
		 * @param $classes optional space-separated css classes to apply to message
		 */
		public function add($message, $classes = ''){
			$classes = html_attr($classes);
			$this->messages[] = "<p class=\"{$classes}\">{$message}</p>";
		}
		
		/**
		 * append text to last message
		 * @param $append text to append
		 */
		public function append($append){
			if(!count($this->messages)) return;
			
			$last_index = count($this->messages) - 1;
			/* remove ending </p> */
			$this->messages[$last_index] = substr($this->messages[$last_index], 0, -5);
			$this->messages[$last_index] .= $append . '</p>';
		}
		
		/**
		 * append 'Failed' to last message
		 */
		public function failed(){
			$this->append('<span class="text-danger language hspacer-lg" data-key="FAILED">FAILED</span>');
		}
		
		/**
		 * append 'OK' to last message
		 */
		public function ok(){
			$this->append('<span class="text-success language hspacer-lg" data-key="OK">OK</span>');
		}
		
		/**
		 * append 'Skipped' to last message
		 */
		public function skipped(){
			$this->append('<span class="text-warning language hspacer-lg" data-key="SKIPPED">Skipped</span>');
		}
		
		/**
		 * append a line separator to the progress log (e.g. to start a new section)
		 */
		public function line(){
			$this->messages[] = '<p><hr class="navbar-inverse"></p>';
		}
		
		/**
		 * @return html code for displaying the progress log
		 */
		public function show(){
			$data = array('messages' => $this->messages);
			return $this->plugin->view(dirname(__FILE__).'/views/progress-log.php', $data);
		}
	}
