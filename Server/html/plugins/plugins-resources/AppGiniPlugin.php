<?php

	class AppGiniPlugin{
		protected $title, $name, $logo, $errors;
		public $progress_log;
		public $translation;
		public $project_xml;
		protected $fk_fields;
		public $path, $app_path; /* path to plugin folder, and to the AppGini app hosting the plugin, resp. */
		public $output_path; /* path to the guest AppGini app where plugin output will be saved. */

		/**
		 *  @brief Initiate the class.
		 *  *Example:*
		 *  @code{.php}
		        $myplugin = new AppGiniPlugin(array(
					  'title' => 'My Plugin',
					  'name' => 'myplugin',
					  'logo' => 'images/myplugin.png'
				));
		 *  @endcode
		 *  @param $config an optional assoc. array that can accept the following keys:
		 *         * `title`: the plugin name as displayed to users
		 *         * `name`: the plugin 'slug', provide a name that can be used as a PHP variable
		 *         * `logo`: path to logo file to use, relative to plugin folder.
		 *         * `output_path`: path to destination app where plugin code will be generated. Defaults to host app path (where the plugin is installed).
		 *  
		 *  @return an instance that you can use to invoke the various plugin methods.
		 */
		public function __construct($config = array()){
			error_reporting(E_ERROR | E_WARNING | E_PARSE);
			$this->errors = array();
			
			global $Translation;
			$this->translation =& $Translation;
			
			$this->title = 'AppGini Plugin';
			$this->name = 'plugin';
			$this->logo = '';
			
			if(isset($config['title'])) $this->title = $config['title'];
			if(isset($config['name'])) $this->name = $config['name'];
			if(isset($config['logo'])) $this->logo = $config['logo'];
			$this->version = isset($config['version']) ? $config['version'] : 1.0;
			
			$this->app_path = $this->output_path = realpath(dirname(__FILE__) . '/../../');
			$this->path = realpath("{$this->app_path}/plugins/{$this->name}");
			if(!$this->path) $this->error('__construct', 'Plugin not found!');
			
			if(isset($config['output_path']) && realpath($config['output_path'])){
				$this->output_path = realpath($config['output_path']);
			}
			
			if(!class_exists('ProgressLog')) include_once(dirname(__FILE__).'/ProgressLog.php');
			$this->progress_log = new ProgressLog($this);
		}
		
		protected function error($method, $msg, $return = false){
			$this->errors[] = array(
				'method' => $method,
				'msg' => $msg
			);
			
			return $return;
		}
		
		/**
		 *  @return The last error message that occured, if any (as a string).
		 */
		public function last_error(){
			$last_error_index = count($this->errors);
			if(!$last_error_index) return '';
			
			return $this->errors[$last_error_index - 1]['msg'];
		}
		
		/**
		 * filter array of datas
		 * @param $inputArray: input data array
		 */
		public function filter_inputs(&$inputArray){
			foreach ($inputArray as $key => $value) {
				$inputArray[$key] = htmlspecialchars($value);
			}
		}
		
		/**
		 * Copy folder with and sub-folders from source to destinaton
		 * @param $src: source folder path
		 * @param $dst: destination folder path
		 * @param $log boolean show log in progress_log if true
		 * @param $level optional integer indicating number of indents to shift in the progress log
		 * @return Boolean indicating success/failure
		 */
		public function recurse_copy($src, $dst, $log = false, $level = 0){
			$indents = str_repeat('&nbsp;', 2 * $level);
			if($log) $this->progress_log->add($indents . '<span class="language" data-key="copying_folder" data-src="' . basename($src) . '">Copying folder ' . basename($src) . ' ...</span>', 'text-info');
			
			$dir = @opendir($src);
			if(!$dir){
				if($log) $this->progress_log->failed();
				return false;
			}
			
			if(!@mkdir($dst, 0755) && !is_dir($dst)){
				if($log) $this->progress_log->failed();
				return false;
			}
			
			$errors = array();
			while(false !== ($file = readdir($dir))){
				/* ignore self and parent (. and ..) */
				if($file == '.' || $file == '..') continue;
				
				/* if folder, recurse copy */
				if(is_dir($src . '/' . $file)) {
					if(!$this->recurse_copy($src . '/' . $file, $dst . '/' . $file, $log, $level + 1))
						$errors[] = $file;
					continue;
				}

				/* if file, copy */
				if(!@copy($src . '/' . $file, $dst . '/' . $file))
					$errors[] = $file;
			}
			closedir($dir);
			
			if(count($errors)){
				if($log) $this->progress_log->add($indents . '<span class="language" data-key="failed_to_copy_n_subfolders_from" data-num_errors="' . count($errors) . '" data-src="' . basename($src) . '">Failed to copy ' . count($errors) . ' subfolder(s)/file(s) from ' . basename($src) . '.</span>', 'text-danger');
				return false;
			}
			
			if($log) $this->progress_log->add($indents . '<span class="language" data-key="folder_x_copied" data-src="' . basename($src) . '">Folder ' . basename($src) . ' copied successfully.</span>', 'text-success');
			return true;
		}
		
		/**
		 * Display error messages
		 * @param $msg: error message
		 * @param $back_url: pass explicit false to suppress back button
		 * @return  html code for a styled error message
		 */
		public function error_message($msg, $back_url = '') {
			ob_start();
			echo '<div class="panel panel-danger">';
			echo '<div class="panel-heading"><h3 class="panel-title language" data-key="Error:">Error:</h3></div>';
			echo '<div class="panel-body"><p class="text-danger">' . $msg . '</p>';
			if ($back_url !== false) { // explicitly passing false suppresses the back link completely
				echo '<div class="text-center">';
				if ($back_url) {
					echo '<a href="' . $back_url . '" class="btn btn-danger btn-lg vspacer-lg"><i class="glyphicon glyphicon-chevron-left"></i> <span class="language" data-key="Back">Back</span></a>';
				} else {
					echo '<a href="#" class="btn btn-danger btn-lg vspacer-lg" onclick="history.go(-1); return false;"><i class="glyphicon glyphicon-chevron-left"></i> <span class="language" data-key="Back">Back</span></a>';
				}
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
			$out = ob_get_contents();
			ob_end_clean();

			return $out;
		}

		/**
		 * Create and prepare projects upload folder if necessary
		 *
		 * @return     (string or boolean)  error message string on error, true on success
		 */
		public static function prepare_projects_folder() {
			$projects_dir = realpath(dirname(__FILE__) . '/..') . '/projects';

			// Create projects folder if not already there ...
			if(!is_dir($projects_dir))
				if(!@mkdir($projects_dir))
					return '<span class="language" data-key="couldnt_create_projects_dir">Could not create projects directory.<br>Please create \'projects\' directory inside the plugins directory</span> (<code>' . $projects_dir . '</code>).';

			// Ensure that the projects folder has write permission
			if(!is_writable($projects_dir))
				return '<span class="language" data-key="change_permissions_projects_dir">Please change the permission of the \'projects\' folder to be writeable.</span>';

			// try to add .htaccess file if not already there
			if(!@file_exists("{$projects_dir}/.htaccess"))
				@file_put_contents("{$projects_dir}/.htaccess", implode("\n", array(
					// no directory listing
					'Options -Indexes',

					// no direct download of axp files
					'<Files ~ "\.axp$">',
					'	Order allow,deny',
					'	Deny from all',
					'</Files>',
				)));

			// try to add an index file to prevent directory listing if .htaccess is not supported
			if(!@file_exists("{$projects_dir}/index.html"))
				@touch("{$projects_dir}/index.html");

			return true;
		}

		/**
		 * Retrieves XML object from given hashed project file name
		 * and sets project_xml class prop to the loaded xml object.
		 *
		 * @param $fileHash: md5 hashed project name
		 * @param $projectFile: project file name ( empty var passed by reference )
		 * @return XML project file object 
		 */
		public function get_xml_file($fileHash, &$projectFile){
			if(!function_exists('simpleXML_load_file'))
				die('Please, enable simplexml extention in your php.ini configuration file.');

			// scan projects dir for a file with matching MD5 hash
			$projectFile = null;
			$d = dir(dirname(__FILE__) . '/../projects');
			while(false !== ($entry = $d->read())) {
				if($entry == '..' || $entry == '.') continue;

				if(md5($entry) == $fileHash) {
					$projectFile = $entry;
					break;
				}
			}
			$d->close();

			if(!$projectFile) return false;

			// validate that the file is not corrupted
			@$xmlFile = simpleXML_load_file("../projects/{$projectFile}", 'SimpleXMLElement', LIBXML_NOCDATA);
			if(!$xmlFile) return false;
			
			return ($this->project_xml = $xmlFile);
		}
		
		/**
		 * Check if the current logged-in user is an adminstrator
		 * @return  boolean
		 */
		public function is_admin(){
			return getLoggedAdmin() !== false;
		}

		/**
		 * Quit with 403 status in case current user is not admin
		 *
		 * @param      string  $msg    Optional message to display before quitting
		 */
		public function reject_non_admin($msg = '') {
			if($this->is_admin()) return;

			@header('HTTP/1.0 403 Forbidden');
			die($msg);
		}
		
		/**
		 * Update node in axp file table 
		 * @param $nodeData : target node data array having:
		 *           projectName: table axp project name
		 *           tableIndex: table index inside axp file
		 *           fieldIndex: field index inside table if exists, null otherwise
		 *           pluginName:  plugin to be updated
		 *           nodeName:  plugin node to be updated if exists, null otherwise
		 *           data: data to update the node with
		 * @return  boolean
		 */
		public function update_project_plugin_node($nodeData){
			if(!preg_match('/^[a-z0-9-_]+\.axp$/i', $nodeData['projectName'] )){
				return $this->error('update_project_plugin_node', 'Invalid project file name');
			}

			$axp_file = dirname(__FILE__) . "/../projects/" . $nodeData['projectName'];
			@$xmlFile = simpleXML_load_file($axp_file, 'SimpleXMLElement'/*, LIBXML_NOCDATA*/);


			if ( !isset($xmlFile) ) {
				return $this->error('update_project_plugin_node', 'Could not load project file as XML');
			}
			

			$targetNode = &$xmlFile;
			if ( isset ($nodeData['tableIndex']) &&  $nodeData['tableIndex'] >= 0 ){
					$targetNode =& $xmlFile->table[$nodeData['tableIndex']];
					if ( isset ($nodeData['fieldIndex'])  &&  $nodeData['fieldIndex'] >= 0 ){
						$targetNode =& $targetNode->field[$nodeData['fieldIndex']];   
					}
			}

			$targetNode =& $this->check_or_create_plugin_node( $targetNode , $nodeData ) ;
		   
			if ($targetNode){

				$targetNode[0] = $nodeData['data'];
				$xmlFile->asXML($axp_file);    
				return true;
			}    

			return $this->error('update_project_plugin_node', 'No targetNode');
		}
		
		/**
		 * Detect and return the theme style links to insert into the plugin header
		 * @return string '<link rel="stylesheet" ...'
		 */
		public function get_theme_css_links(){
			$host_app_header = @file_get_contents(dirname(__FILE__) . '/../../header.php');
			if(!$host_app_header){
				/* try to guess the theme and assume no 3D effect */
				return '<link rel="stylesheet" href="../../resources/initializr/css/bootstrap.css">';
			}
			
			$regex = '/<link\s+rel="stylesheet".*?resources\/initializr\/css\/(.*?)\.css"/i';
			$mat = array();
			if(!preg_match_all($regex, $host_app_header, $mat)){
				/* error or no matches */
				return '';
			}
			
			$links = '';
			foreach($mat[1] as $m){
				if($m == 'bootstrap-theme'){
					$links .= "<!--[if gt IE 8]><!-->\n";
					$links .= '<link rel="stylesheet" href="../../resources/initializr/css/bootstrap-theme.css">' . "\n";
					$links .= '<!--<![endif]-->' . "\n";
				}else{
					$links .= '<link rel="stylesheet" href="../../resources/initializr/css/' . $m . '.css">' . "\n";
				}
			}
			
			return $links;
		}

		/**
		 * get max. file size from php.ini configuration
		 */
		public function parse_size($size) {
			$unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
			$size = preg_replace('/[^0-9\.]/', '', $size); 		// Remove the non-numeric characters from the size.
			if ($unit) {
				// Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
				return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
			}else {
				return round($size);
			}
		}
		
		/**
		 * Loads a given view, passing the given data to it
		 * @param $view the path of a php file to be loaded
		 * @param $the_data_to_pass_to_the_view (optional) associative array containing the data to pass to the view
		 * @return the output of the parsed view as a string
		 */
		public function view($view, $the_data_to_pass_to_the_view = false){
			if(!is_file($view)) return $this->error('view', "'{$view}' is not a file");

			if(is_array($the_data_to_pass_to_the_view)){
				foreach($the_data_to_pass_to_the_view as $k => $v)
					$$k = $v;
			}
			unset($the_data_to_pass_to_the_view, $k, $v);

			ob_start();
			@include($view);
			$out = ob_get_contents();
			ob_end_clean();

			return $out;
		}

		/**
		 * Loads the page for uploading a new project or opening an existing one
		 * @param $content optional associative array that could contain:
		 *                 'pre_upload': cleint-side code to include above the upload box
		 *                 'post_upload': cleint-side code to include below the upload box
		 *                 'redirect_to': path of script to redirect users to after 
		 *                                uploading/selecting a project file. The path is relative
		 *                                to the plugin subfolder. The script will receive a GET
		 *                                parameter 'axp' containing the md5 hash of the project
		 *                                file name.
		 * @return the page code
		 */
		public function get_project($content = array()){
			$resources_dir = dirname(__FILE__);
			
			if(!isset($content['redirect_to']) || !$content['redirect_to']) 
				return $this->error_message('Missing "redirect_to" parameter in call to get_project().', false);
			
			$d = dir("{$resources_dir}/../projects");
			$currentProjects = array();
			while(false !== ($entry = $d->read())) {
				if(strtolower(substr($entry, -4, 4)) == '.axp') $currentProjects[] = $entry;
			}
			$d->close();

			$content['currentProjects'] = $currentProjects;
			$content['projectsNum'] = count($currentProjects);

			$content['updateMessage'] = $this->getLatestVersion();
			
			return $this->view("{$resources_dir}/views/load-project.php", $content);
		}

		protected function getLatestVersion() {
			$url = 'https://bigprof.com/plugin-latest-version.php?plugin=' . urlencode($this->name);
			$latestVersion = floatval(@file_get_contents($url));
			if(!$latestVersion || $this->version >= $latestVersion) return '';

			return "You're using version {$this->version} of {$this->title}. " .
				"The latest version is {$latestVersion}. " .
				"You can <a href=\"https://bigprof.com/appgini/download-plugins\" target=\"_blank\">download it from here</a>.";
		}

		/**
		 *  @brief Handles file uploaded via ajax [more details needed]
		 */
		public function process_ajax_upload(){
			$maxFileSize = ($this->parse_size(ini_get('post_max_size')) < $this->parse_size(ini_get('upload_max_filesize')) ? ini_get('post_max_size') : ini_get('upload_max_filesize'));
			
			try {

				//if file exceeded the filesize, no file will be sent
				if(!isset($_FILES['uploadedFile'])) {	
					throw new RuntimeException("No file sent, you must upload a (.axp) file not greater than {$maxFileSize}. Alternatively, you can manually upload the project file to the <code>projects</code> folder.");
				}
				
				$file = pathinfo($_FILES['uploadedFile']['name']);
				$ext = $file['extension']; // get the extension of the file	
				$filename = $file['filename'];
					
				// Undefined | Multiple Files | $_FILES Corruption Attack
				// If this request falls under any of them, treat it invalid.
				
				// Check $_FILES['uploadedFile']['error'] value.
				switch ($_FILES['uploadedFile']['error']) {
					case UPLOAD_ERR_OK:
						break;
					case UPLOAD_ERR_NO_FILE:
						throw new RuntimeException('No file sent.');
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						throw new RuntimeException("You must upload a (.axp) file not greater than {$maxFileSize}. Alternatively, you can manually upload the project file to the <code>projects</code> folder.");
					default:
						throw new RuntimeException('Unknown errors.');
				}
			
				//Check extention
				if ( strtolower($ext) != "axp") {
					throw new RuntimeException('You must upload a (.axp) file');
				}
				
				// $_FILES['uploadedFile']['name'] validation
				if( !preg_match('/^[a-z0-9-_]+\.axp$/i', $_FILES['uploadedFile']['name'] )) {
					throw new RuntimeException('File was not uploaded. The file can only contain "a-z", "0-9", "_" and "-".');
				}
				
				//check existing projects' names 
				$currentProjects = scandir ( "../projects"  );
				
				natsort($currentProjects);
				$currentProjects = array_reverse ( $currentProjects );
				
				$renameFlag = false;

				foreach ( $currentProjects as $projName ){
					if ( preg_match('/^'.$filename.'(-[0-9]+)?\.axp$/i', $projName )) {
						
						$matches = array();
						if ( !strcmp ( $_FILES['uploadedFile']['name'] , $projName) ){
							$newName = $filename."-"."1.axp";
							$renameFlag = true;
						}else{
						
							//increment number at the end of the name ( sorted desc, first one is the largest number)
							preg_match('/(-[0-9]+)\.axp$/i', $projName, $matches);
							$number = preg_replace("/[^0-9]/", '', $matches[0]);
							$newName = $filename."-".(((int)$number )+1).".axp";
							$renameFlag = true;
							break;
						}
						
					}else{
						//found name without number at the previous loop, and name with number not found at this loop
						if ($renameFlag){
							break;
						}
					}
				}
					
				if (!move_uploaded_file( $_FILES['uploadedFile']['tmp_name'], sprintf('../projects/%s',($renameFlag?$newName:$_FILES['uploadedFile']['name']))
				)) {
					throw new RuntimeException('Failed to move uploaded file.');
				}else{
			
					//file uploaded successfully							
					echo json_encode(array(
						"response-type" =>"success",
						"isRenamed" =>$renameFlag,
						"fileName" => $renameFlag?$newName:$_FILES['uploadedFile']['name'],
						"md5FileName"=>md5($renameFlag?$newName:$_FILES['uploadedFile']['name'])

					));
				}	
				
			} catch (RuntimeException $e){
				header('Content-Type: application/json');
				header($_SERVER['SERVER_PROTOCOL'] . 'error; Content-Type: application/json', true, 400);
				echo json_encode(array(
						"error" => $e->getMessage()
					));
			}
		}
		
		/**
		 * Injects provided code to a hook file
		 * @param $hook_file_path the full path of the hook file
		 * @param $hook_function name of the hook function to inject code into
		 * @param $code the [PHP] code to inject to the hook
		 * @param $location 'top' injects code directly after function declaration line
		 *                  'bottom' injects code directly before the last return statement in the
		 *                           function or before the ending curly bracket if no return statement
		 *                           found before it.
		 *                  >>>> 'bottom' is not yet supported -- only 'top' is supported now.
		 * @return true on success, false on failure
		 */
		public function add_to_hook($hook_file_path, $hook_function, $code, $location = 'top'){
			/* Check if hook file exists and is writable */
			$hook_code = @file_get_contents($hook_file_path);
			if(!$hook_code) return $this->error('add_to_hook', 'Unable to access hook file');
			
			/* Find hook function */
			preg_match('/function\s+' . $hook_function . '\s*\(/i', $hook_code, $matches, PREG_OFFSET_CAPTURE);
			if(count($matches) != 1) return $this->error('add_to_hook', 'Could not determine correct function location');
			
				/* start position of hook function */
				$hf_position = $matches[0][1];
				
				/* position of next function, or EOF position if this is the last function in the file */
				$nf_position = strlen($hook_code);
				preg_match('/function\s+[a-z0-9_]+\s*\(/i', $hook_code, $matches, PREG_OFFSET_CAPTURE, $hf_position + 10);
				if(count($matches)) $nf_position = $matches[0][1];
				
				/* hook function code */
				$old_function_code = substr($hook_code, $hf_position, $nf_position - $hf_position);
			
			/* Checks $code is not already in there */
				if(strpos($old_function_code, $code) !== false) return $this->error('add_to_hook', 'Code already exists');
			
			/* determine insertion point based on $location */
				/*********** location support not yet implemented ************/
			
			/* insert $code and save */
				$code_comment = "/* Inserted by {$this->title} on " . date('Y-m-d h:i:s') . " */";
				$new_function_code = preg_replace(
					'/(function\s+' . $hook_function . '\s*\(.*\)\s*\\' . chr(123) . ')/i',
					"\$1\n\t\t{$code_comment}\n\t\t{$code}\n\t\t/* End of {$this->title} code */\n",
					$old_function_code, 
					1
				);
				if(!$new_function_code) return $this->error('add_to_hook', 'Error while injecting code');
				if($new_function_code == $old_function_code) return $this->error('add_to_hook', 'Nothing changed');
				
				$hook_code = str_replace($old_function_code, $new_function_code, $hook_code);
				if(!@file_put_contents($hook_file_path, $hook_code)) return $this->error('add_to_hook', 'Could not save changes');
				
			return true;
		}
		/**
		 * Replaces provided code to a hook file
		 * @param $hook_file_path the full path of the hook file
		 * @param $hook_function name of the hook function to inject code into
		 * @param $code the [PHP] code to inject to the hook
		 * @param $location 'top' injects code directly after function declaration line
		 *                  'bottom' injects code directly before the last return statement in the
		 *                           function or before the ending curly bracket if no return statement
		 *                           found before it.
		 *                  >>>> 'bottom' is not yet supported -- only 'top' is supported now.
		 * @return true on success, false on failure
		 */
		public function replace_to_hook($hook_file_path, $hook_function, $code, $location = 'top'){
			/* Check if hook file exists and is writable */
			$hook_code = @file_get_contents($hook_file_path);
			if(!$hook_code) return $this->error('add_to_hook', 'Unable to access hook file');
			
			/* Find hook function */
			preg_match('/function\s+' . $hook_function . '\s*\(/i', $hook_code, $matches, PREG_OFFSET_CAPTURE);
			if(count($matches) != 1) return $this->error('add_to_hook', 'Could not determine correct function location');
			
				/* start position of hook function */
				$hf_position = $matches[0][1];
				
				/* position of next function, or EOF position if this is the last function in the file */
				$nf_position = strlen($hook_code);
				preg_match('/function\s+[a-z0-9_]+\s*\(/i', $hook_code, $matches, PREG_OFFSET_CAPTURE, $hf_position + 10);
				if(count($matches)) $nf_position = $matches[0][1];
				
				/* hook function code */
				$old_function_code = substr($hook_code, $hf_position, $nf_position - $hf_position);
				
			/* checks if the code exists */
			$plugin_code_pattern = "/\/\* Inserted by {$this->title} on (\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2}) \*\/(.*)\/\* End of {$this->title} code \*\//s";
			$code_comment = "/* Inserted by {$this->title} on " . date('Y-m-d h:i:s') . " */";
			$new_function_code = preg_replace(
				$plugin_code_pattern,
				"{$code_comment}\n\t\t{$code}\n\t\t/* End of {$this->title} code */",
				$old_function_code
			);
			if ( preg_match($plugin_code_pattern, $old_function_code) !=1 ){
				$new_function_code = preg_replace(
					'/(function\s+' . $hook_function . '\s*\(.*\)\s*\\' . chr(123) . ')/i',
					"\$1\n\t\t{$code_comment}\n\t\t{$code}\n\t\t/* End of {$this->title} code */\n",
					$old_function_code, 
					1
				);
			}
			if(!$new_function_code) return $this->error('add_to_hook', 'Error while injecting code');
			$hook_code = str_replace($old_function_code, $new_function_code, $hook_code);
			if(!@file_put_contents($hook_file_path, $hook_code)) return $this->error('add_to_hook', 'Could not save changes');	
			return true; 
		}
		
		/**
		 * Get client-side code for displaying clickable table list of open project
		 * @param $config associative array containing elements having the following keys:
		 *                'axp': Project XML object, as returned from AppGiniPlugin->get_xml_file()
		 *                'click_handler': js function to invoke when a table is clicked. The index of the table clicked is passed to that function.
		 *                'list_id' optional id attribute of returned list, defaults to 'tables-list'
		 *                'select_first_table' optional flag to select the first table in the list, defaults to true.
		 *                'classes' optional css classes (use space delimiter between class names) to apply to the table list, defaults to empty string.
		 * @return client-side code to be placed in page
		 */
		public function show_tables($config){
			$resources_dir = dirname(__FILE__);
			
			if(!isset($config['axp'])) return $this->error_message('Missing "axp" item in config array passed to show_tables()');
			if(!isset($config['click_handler'])) return $this->error_message('Missing "click_handler" item in config array passed to show_tables()');
			
			if(!isset($config['list_id'])) $config['list_id'] = 'tables-list';
			if(!isset($config['select_first_table'])) $config['select_first_table'] = true;
			if(!isset($config['classes'])) $config['classes'] = '';
			
			return $this->view("{$resources_dir}/views/tables-list.php", $config);
		}
		
		/**
		 * Get client-side code for displaying clickable list of items
		 * @param $config associative array containing elements having the following keys:
		 *                'items': array of assoc arrays:
		 *						'icon' -- optional path to icon file, relative to the main app path
		 *						'glyphicon' -- optional Bootstrap glyphicon -- only the icon name is needed, example: "ok", "chevron-left", .. etc
		 *						'label' -- label to display in the list
		 *                'click_handler': js function to invoke when a table is clicked. The index of the table clicked is passed to that function.
		 *                'list_id' optional id attribute of returned list, defaults to 'items-list'
		 *                'select_first_item' optional flag to select the first item in the list, defaults to true.
		 *                'classes' optional css classes (use space delimiter between class names) to apply to the items list, defaults to empty string.
		 *                'default_icon' optional default icon to use if no icon/glyphicon is specified for an item
		 *                'default_glyphicon' optional default glyphicon to use if no icon/glyphicon is specified for an item
		 * @return client-side code to be placed in page
		 */
		public function show_items($config){
			$resources_dir = dirname(__FILE__);
			
			if(!isset($config['items'])) return $this->error_message('Missing "items" array in config array passed to show_items()');
			if(!isset($config['click_handler'])) return $this->error_message('Missing "click_handler" item in config array passed to show_tables()');
			
			if(!isset($config['list_id'])) $config['list_id'] = 'items-list';
			if(!isset($config['select_first_item'])) $config['select_first_item'] = true;
			if(!isset($config['classes'])) $config['classes'] = '';
			
			return $this->view("{$resources_dir}/views/list.php", $config);
		}
		
		/**
		 * Check existance/create node in xml project structure
		 * @param $targetNode: parent xml node,which will be mofified to match the target node
		 *        $nodeData: target plugin node data to be checked/created
		 * @return the target node
		 */
		private function &check_or_create_plugin_node( &$targetNode , $nodeData){
			if (! isset($targetNode->plugins)){
				   $targetNode->addChild('plugins');  
			}
			$targetNode = &$targetNode->plugins; 
			
			if ( isset ($nodeData['pluginName'])){
				$pluginName = $nodeData['pluginName'];
				if (! isset($targetNode->$pluginName)){
					$targetNode->addChild($pluginName);   
				}
				$targetNode = &$targetNode->$pluginName;
			}

			if ( isset ($nodeData['nodeName'])){
				$node_name = $nodeData['nodeName'];
				if (! isset($targetNode->$node_name)){
				   $targetNode->addChild($node_name);   
				}
				$targetNode = &$targetNode->$node_name;
			}


			return $targetNode;
		}

		/**
		 *  @brief retrieve a list of child tables of a given table
		 *  
		 *  @param [in] $tn name of parent table
		 *  @return a numeric array of all tables that have a foreign
		 *          key (lookup) field pointing to the given parent 
		 *          table
		 */		
		public function get_child_tables($tn){
			if(empty($this->fk_fields)) $this->get_fk_fields();
			
			$chidren = array();
			if(!isset($this->fk_fields[$tn])) return $chidren;

			foreach($this->fk_fields as $ct => $ctfks){
				// if($ct == $tn) continue;
				foreach($ctfks as $ctfk => $pt){
					if($pt == $tn) $chidren[] = $ct;
				}
			}

			return array_unique($chidren);
		}

		/**
		 *  @brief retrieve direct parents of given child table
		 *  
		 *  @param [in] $tn child table
		 *  @return a numeric array listing all parent table names
		 */
		public function get_parent_tables($tn){
			if(empty($this->fk_fields)) $this->get_fk_fields();
			
			$parents = array();
			if(!isset($this->fk_fields[$tn])) return $parents;

			foreach($this->fk_fields[$tn] as $fkf => $pt){
				$parents[] = $pt;
			}

			return array_unique($parents);
		}
		
		/**
		 *  @brief retrieve all ancestors of given table, up to specified level
		 *  
		 *  @param [in] $tn table name to retrieve ancestors of
		 *  @param [in] $level levels to retrieve, defaults to 1
		 *  @return numeric array of ancestor table names
		 */		
		public function get_ancestor_tables($tn, $level = 1){
			if(empty($this->fk_fields)) $this->get_fk_fields();
			
			$ancestors = array();
			$ancestors[0] = $this->get_parent_tables($tn);
			if($level <= 1) return $ancestors[0];
			
			for($i = 1; $i < $level; $i++){
				$ancestors[$i] = array();
				foreach($ancestors[$i - 1] as $pt){
					$ancestors[$i] = array_merge($ancestors[$i], $this->get_parent_tables($pt));
				}
			}
			
			$ancestors_flat = array();
			for($i = 0; $i < $level; $i++){
				$ancestors_flat = array_merge($ancestors_flat, $ancestors[$i]);
			}
			
			return array_unique($ancestors_flat);
		}
		
		/**
		 *  @brief Retrieve all non-autofill lookup fields of the current project
		 *  
		 *  @return A 2D array of lookup fields and their parent table names in the format
		 *  @code{.php}
		 *  [
		 *  	'table_name' => [
		 *  		'lookup_field_name' => 'parent_table_name',
		 *  		... 
		 *  	],
		 *  	...
		 *  ]
		 *  @endcode
		 */
		public function get_fk_fields(){
			if($this->project_xml === null) return $this->error('get_fk_fields', 'No project loaded. Use AppGiniPlugin::get_xml_file() to load one.');
			if(!empty($this->fk_fields)) return $this->fk_fields;
			
			$this->fk_fields = array();
			foreach($this->project_xml->table as $table){
				$table_name = (string) $table->name;
				$this->fk_fields[$table_name] = array();
				foreach($table->field as $fn => $field){
					$parentTable = (string) $field->parentTable;
					$autoFill = ($field->autoFill == 'True' ? true : false);
					$field_name = (string) $field->name;
					if(!$parentTable || $autoFill) continue;
					
					$this->fk_fields[$table_name][$field_name] = $parentTable;
				}
			}
			
			return $this->fk_fields;
		}
		
		/**
		 *  @return An HTML string mentioing the current Git branch (only if the code is run from a cloned Git repo).
		 */
		public function git_branch(){
			$git_head = "{$this->app_path}/.git/HEAD";
			if(!is_readable($git_head)) return '';
			
			$branch = implode('/', array_slice(explode('/', file_get_contents($git_head)), 2));
			return "<div class=\"vspacer-lg bg-info text-info\"><small>&nbsp; <i class=\"glyphicon glyphicon-info-sign\"></i> Current git branch: {$branch}</small></div>";
		}
		
		/**
		 *  @param $exclude_anon Boolean (optional) excludes anonymous group if set to true.
		 *  @return An array of user groups. Each array element is an assoc array similar to this: ['groupID' => 3, 'name' => 'Accountants']
		 *  @details use this to retrieve groups of the HOST app where the plugin is 
		 *           installed rather than the destination app to which the plugin 
		 *           code would be generated.
		 */
		public function get_groups($exclude_anon = false){
			$groups = array();
			$config = config('adminConfig');
			$res = sql("select groupID, name from membership_groups order by name", $eo);
			while($row = db_fetch_assoc($res)){
				if($exclude_anon && $row['name'] == $config['anonymousGroup']) continue;
				$groups[] = $row;
			}
			
			return $groups;
		}
		
		/**
		 *  @brief BSF-based search for the relationship between 2 tables
		 *  
		 *  @param [in] $t1 first table
		 *  @param [in] $t2 second table
		 *  @return an array of tables that need to be joined to connect the 2 given tables
		 */
		public function find_path($t1, $t2){
			$gr = $path = array();
			$curr = $ncurr = '';
			$q = array($t1);
			$cn = $t2;

			foreach($this->project_xml->table as $t){
				$gr[(string) $t->name] = array(
					'dist' => INF,
					'prnt' => ''
				);
			}
			
			if(!isset($gr[$t1]) || !isset($gr[$t2])) return array();
			$gr[$t1]['dist'] = 0;
			
			while(count($q)){
				$curr = array_pop($q);
				$ncurr = array_unique(
					array_merge(
						$this->get_child_tables($curr),
						$this->get_parent_tables($curr)
					)
				);
				foreach($ncurr as $nn){
					if(!isset($gr[$nn])) continue;
					if($gr[$nn]['dist'] == INF){
						$gr[$nn]['dist'] = $gr[$curr]['dist'] + 1;
						$gr[$nn]['prnt'] = $curr;
						
						$q[] = $nn;
					}
				}
			}
			
			while($cn != $t1){
				$path[] = $cn;
				$cn = $gr[$cn]['prnt'];
			}
			
			$path[] = $t1;
			return array_reverse($path);
		}
		
		/**
		 *  @brief Retrieve fields and captions of a table's TV query
		 *  
		 *  @param [in] $table table name
		 *  @return false on error, or array of fields ['field', 'caption']
		 */
		public function sql_table_fields($table){
			$select_fields = get_sql_fields($table);
			$select_from = get_sql_from($table);
			if(!$select_fields || !$select_from) return $this->error('sql_table_fields', $this->translation['tableAccessDenied'], false);
			
			$sql = "SELECT {$select_fields} FROM {$select_from} limit 0";
			$res = sql($sql, $eo);
			$num_fields = db_num_fields($res);
			$aliases = array();
			for($i = 0; $i < $num_fields; $i++){
				$aliases[] = db_field_name($res, $i);
			}
			
			$last_pos = 0;
			$fields = array();
			for($i = 0; $i < $num_fields; $i++){
				$alias_start = strpos($select_fields, " as '{$aliases[$i]}', ", $last_pos) + 5;
				if($i == $num_fields - 1){
					$alias_start = strpos($select_fields, " as '{$aliases[$i]}'", $last_pos) + 5;
				}
				
				$field = substr($select_fields, $last_pos, $alias_start - $last_pos - 5);
				$fields[] = array('field' => $field, 'caption' => $aliases[$i]);
				$last_pos = $alias_start + strlen($aliases[$i]) + 3;
			}
			
			return $fields;
		}
		
		/**
		 *  @brief Retrieve field names of a given table in the format tn.fn
		 *  
		 *  @param [in] $tn table name
		 *  @return ['tn.fn', ..] or false on error
		 *  
		 *  @details field names are retrieved from the project_xml object
		 */
		public function project_table_fields($tn){
			if($this->project_xml === null) return $this->error('get_fk_fields', 'No project loaded. Use AppGiniPlugin::get_xml_file() to load one.');

			$fields = array();
			foreach($this->project_xml->table as $table){
				$table_name = (string) $table->name;
				if($table_name != $tn) continue;
				
				foreach($table->field as $fn => $field){
					$cfn = (string) $field->name;
					$fields[] = "{$table_name}.{$cfn}";
				}
				
				return $fields;
			}
			
			return $this->error('project_table_fields', 'Invalid table name');
		}
		
		/**
		 *  @brief Retrieves field names in the format tn.fn as well as their corresponding SQL query expressions and captions/aliases
		 *  
		 *  @param [in] $tn table name
		 *  @return ['tn.fn' => ['field' => '..', 'caption' => '..'], ..] or false on error
		 *  
		 *  @details combines the outputs from project_table_fields() and sql_table_fields()
		 */
		public function table_fields($tn){
			$ptf = $this->project_table_fields($tn);
			$stf = $this->sql_table_fields($tn);
			
			if(!$ptf || !$stf) return false; // no need to specify an error as it's been already specified by one of the above function calls
			
			$tf = array();
			for($i = 0; $i < count($ptf); $i++){
				$tf[$ptf[$i]] = $stf[$i];
			}
			
			return $tf;
		}
		
		/**
		 *  @brief retrieve the 1-based index of a given field in the TV query of a given table
		 *  
		 *  @param [in] $tnfn table name or tn.fn
		 *  @param [in] $fn (optional) field name only if first param is table name
		 *  @return 1-based index, or false on error
		 *  
		 *  @details Useful for determining the index of a field to use in 'ORDER BY' clause
		 */
		public function sql_table_field_index($tnfn, $fn = ''){
			$tn = $tnfn;
			if($fn == '') list($tn, $fn) = explode('.', $tnfn);
			if(!$tn || !$fn) return $this->error('sql_table_field_index', 'Invalid table or field name');
			
			$ptf = $this->project_table_fields($tn);
			if(!$ptf) return false; // no need to specify an error as it's been already specified by one of the above function call
			
			$i = array_search("{$tn}.{$fn}", $ptf);
			if($i === false || $i === NULL) return $this->error('sql_table_field_index', 'Invalid table or field name');
			
			return $i + 1;
		}
		
		/**
		 *  @brief checks if a given project xml matches current app
		 *  
		 *  @param [in] $pxml optional project xml object.
		 *                    If not provided, ->project_xml is used.
		 *  @return boolean, true if current app matches provided project,
		 *          false otherwise.
		 *  
		 *  @details works by looping through all tables of given project
		 *           and making sure they exist in db along with all fields
		 *           of the table as specified in the project.
		 */
		public function is_project_of_app($pxml = false){
			if($pxml === false) $pxml =& $this->project_xml;
			
			// do we have a valid project??
			$tables = @$pxml->table;
			if($tables === null) return $this->error('get_fk_fields', 'Invalid project xml object');
			$tables =& $pxml->table; // better memory usage for huge projects
			
			// get db table and field names according to project
			$pdb = array(); // 2D array of db structure according to project
			foreach($tables as $t){
				$tn = (string) $t->name;
				$pdb[$tn] = array();
				foreach($t->field as $f){
					$fn = (string) $f->name;
					$pdb[$tn][] = $fn;
				}
			}

			if(!count($pdb)) return false;
			
			// check that the retrieved table and field names do exist in db
			$eo = array('silentErrors' => true, 'error' => false);
			foreach($pdb as $tn => $fields){
				$fields_sql = implode('`,`', $fields);
				sql("select `{$fields_sql}` from `{$tn}` limit 0", $eo);
				if($eo['error']) return $this->error('is_project_of_app', "Table {$tn} or one of its fields not found in current app.");
			}
			
			return true;
		}
		
		/**
		 *  @brief reads a given AXP file to retrieve its xml
		 *  
		 *  @param [in] $axp AXP file full path
		 *  @return the project xml, or false on error
		 */
		public function load_axp($axp){
			if (!is_file($axp))
				return $this->error('load_axp', 'Project file not found.');

			// validate simpleXML extension enabled
			if (!function_exists('simpleXML_load_file'))
				return $this->error('load_axp', 'Please, enable simplexml extention in your php.ini configuration file.');

			// validate that the file is not corrupted
			$xml = @simpleXML_load_file($axp, 'SimpleXMLElement', LIBXML_NOCDATA);
			if(!$xml) return $this->error('load_axp', 'Invalid axp file.');
				
			return $xml;
		}

		/**
		 *  @brief Retrieve the project that matches the current app
		 *  
		 *  @param [in] $path optional path to projects folder
		 *  @return full path of AXP if found, false otherwise
		 */
		public function which_axp($path = ''){
			if(isset($this->_which_axp) && dirname($this->_which_axp) == $path)
				return $this->_which_axp;
			
			if(!$path) $path = "{$this->app_path}/plugins/projects";
			$path = rtrim($path, '\\/');
			
			// get axp's from path and check if matches current app
			$d = dir($path);
			while(false !== ($entry = $d->read())){
				if(strtolower(substr($entry, -4, 4)) == '.axp'){
					$axp = "{$path}/{$entry}";
					$xml = $this->load_axp($axp);
					if($this->is_project_of_app($xml))
						return $this->_which_axp = $axp;
				}
			}
			$d->close();
			
			return $this->error('which_axp', 'No matching project file found.');
		}
		
		/**
		 *  @brief Returns HTML code for displaying form for obtaining output folder
		 *  
		 *  @param [in] $config optional associative array with the following keys: 'next_page' the relative path of the next page user is sent to after submitting this form, defaults to 'generate.php'. 'path_parameter' is the name of the parameter containing the specified path to be sent to the next page, defaults to 'path'. 'extra_options' is an associative array of additional options to be displayed in the form as radio buttons where the key is the option name and the value is the displayed label.
		 *  @return HTML code of form
		 */
		public function show_select_output_folder($config = array()) {
			$resources_dir = dirname(__FILE__);
			
			if(!isset($config['next_page'])) $config['next_page'] = 'generate.php';
			if(!isset($config['path_parameter'])) $config['path_parameter'] = 'path';
			if(!isset($config['extra_options']) || !is_array($config['extra_options']))
				$config['extra_options'] = array();

			return $this->view("{$resources_dir}/views/select-output-folder.php", $config);
		}
		
		/**
		 *  @brief Checks if given path points to a valid AppGini app
		 *  
		 *  @param [in] $app_path path to check
		 *  @return boolean, true if valid path, false otherwise
		 */
		public function is_appgini_app($app_path){
			$path = realpath(trim($app_path));
			
			if(!is_dir($path)) return false;
			
			if(
				!is_dir("{$path}/admin") ||
				!is_dir("{$path}/hooks") ||
				!is_file("{$path}/hooks/__global.php") ||
				!is_dir("{$path}/resources") ||
				!is_dir("{$path}/templates") ||
				!is_writable("{$path}/hooks")
			) return false;

			return true;
		}
		
		/**
		 *  @brief Add a link to links-home or links-navmenu and update progress log
		 *  
		 *  @param [in] $links_file 'links-home' or 'links-navmenu'
		 *  @param [in] $link_array assoc array describing the link to write
		 */
		public function add_link($links_file, $link_array){
			if(!count($link_array)) return;
			if($links_file == 'links-home'){
				$links_array_name = 'homeLinks';
			}else{
				$links_array_name = 'navLinks';
				$links_file = 'links-navmenu';
			}
			
			// TODO: implement translation
			$this->progress_log->add("Adding {$this->name} link to {$links_file}.php ... ", 'text-info');
			$links_file = "{$this->output_path}/hooks/{$links_file}.php";
			
			/* if this plugin already wrote to links file, return */
			$links_file_code = @file_get_contents($links_file);
			if(strpos($links_file_code, "/* {$this->name} links */") !== false){
				$this->progress_log->skipped();
				return;
			}
			
			$new_code = '';
			
			/* if count of closing php tags == count of opening tags, open a php tag */
			$open_tags = substr_count($links_file_code, '<' . '?php');
			$close_tags = substr_count($links_file_code, '?>');
			if($open_tags == $close_tags) $new_code .= "\n<" . '?php';
			
			/* write link to file */
			$new_code .= "\n\t/* {$this->name} links */";
			$new_code .= "\n\t\${$links_array_name}[] = array(";
			foreach($link_array as $k => $v){
				$sk = addslashes($k);
				$sv = is_array($v) ? "array('" . implode("', '", $v) . "')" : "'" . addslashes($v) . "'";
				$new_code .= "\n\t\t'{$sk}' => {$sv},";
			}
			
			$new_code = substr($new_code, 0, -1);
			$new_code .= "\n\t);\n";
			
			@file_put_contents($links_file, $new_code, FILE_APPEND);
			$this->progress_log->ok();
		}

		/**
		 *  @brief Add/replace multiple links to links-home or links-navmenu and update progress log
		 *  
		 *  @param [in] $links_file 'links-home' or 'links-navmenu'
		 *  @param [in] $links array of assoc arrays describing the links to write
		 */
		public function add_links($links_file, $links) {
			if(!is_array($links)) return;
			if($links_file == 'links-home'){
				$links_array_name = 'homeLinks';
			}else{
				$links_array_name = 'navLinks';
				$links_file = 'links-navmenu';
			}
			
			// TODO: implement translation
			$this->progress_log->add("Adding {$this->name} links to {$links_file}.php ... ", 'text-info');
			$links_file = "{$this->output_path}/hooks/{$links_file}.php";
			$old_code = @file_get_contents($links_file);

			$start_code = "\n\t/* {$this->name} links */";
			$end_code = "\n\t/* end of {$this->name} links */";

			$new_code = $start_code;
			
			foreach ($links as $link_array) {
				/* write link to file */
				$new_code .= "\n\t\t\${$links_array_name}[] = array(";
				foreach($link_array as $k => $v) {
					$sk = addcslashes($k, "'\\");
					$sv = is_array($v) ? "array('" . implode("', '", $v) . "')" : "'" . addcslashes($v, "'\\") . "'";
					$new_code .= "\n\t\t\t'{$sk}' => {$sv},";
				}
				$new_code .= "\n\t\t);\n";
			}

			$new_code .= $end_code;

			// try to replace old plugin code with new one
			$replacements = 0;
			$updated_code = preg_replace(
				'/' . preg_quote($start_code, '/') . '.*' . preg_quote($end_code, '/') . '/s',
				$new_code, $old_code, 1, $replacements
			);

			// if no replacements were made, this means the plugin never wrote to this file before,
			// so append the new code
			if(!$replacements) {
				/* if count of closing php tags == count of opening tags, open a php tag */
				$open_tags = substr_count($old_code, '<' . '?php');
				$close_tags = substr_count($old_code, '?>');
				if($open_tags == $close_tags) $new_code = "\n<" . "?php" . $new_code;

				$updated_code = $old_code . $new_code;
			}

			@file_put_contents($links_file, $updated_code);
			
			$this->progress_log->ok();
		}
		
		/**
		 *  @brief Copies a file and updates progress log
		 *  
		 *  @param [in] $src full path to source file
		 *  @param [in] $dest full path to destination
		 *  @param [in] $log boolean show log in progress_log if true
		 *  @return Boolean indicating success/failure
		 */
		public function copy_file($src, $dest, $log = false){
			// TODO: implement translation
			if($log) $this->progress_log->add('Copying ' . basename($src) . ' ... ', 'text-info');
			if(!is_file($src)){
				if($log) $this->progress_log->failed();
				return false;
			}
			
			if(!@copy($src, $dest)){
				if($log) $this->progress_log->failed();
				return false;
			}
			
			if($log) $this->progress_log->ok();
			return true;
		}

		/**
			@param $table_name string, name of table
			@return primary key field name for given table or false if not found
		*/
		function get_pk_field_name($table_name) {
			$tables = $this->project_xml->table;
			foreach($tables as $table) {
				$ctn = (string) $table->name;
				if($ctn != $table_name) continue;

				$table_fields = $table->field;
				foreach($table_fields as $field){
					if(strtolower((string) $field->primaryKey) == 'true')
						return (string) $field->name;
				}
			}
			
			return $this->error('get_pk_field_name', 'PK not found or table invalid'); // no PK found
		}

		/**
			@return array of table names of project
		*/	 
		function get_table_names() {
			$tables_array = array();			
			foreach($this->project_xml->table as $table) {
				$tables_array[] = (string) $table->name;
			}
			
			return $tables_array;
		}
		
		/**
			@param $table_name string, name of table
			@return array of field names of table or false on error
		*/
		function get_table_fields($table_name) {
			$t_fields = $this->project_table_fields($table_name);
			if($t_fields === false) return false;
			
			$fields = array();
			foreach($t_fields as $field) {
				$fields[] = str_replace("{$table_name}.", '', $field);
			}
			return $fields;
		}
		
		/**
		 *  @return string, the file name of the latest existing jQuery library
		 */
		function get_jquery() {
			$jquery_dir = "{$this->app_path}/resources/jquery/js";

			$files = scandir($jquery_dir, SCANDIR_SORT_DESCENDING);
			foreach($files as $entry) {
				if(preg_match('/^jquery[-0-9\.]*\.min\.js$/i', $entry))
					return $entry;
			}

			return '';
		}

		function random_hash($length = 20) {
			$pool = 'abcdefghijklmnopqrstuvwxyz0123456789';
			$hash = '';
			for($i = 0; $i < $length; $i++) {
				$hash .= $pool[rand(0, strlen($pool))];
			}
			return $pool;
		}

		/**
			@param $tn string, name of table
			@return table object for given table name if exists, false otherwise
		*/
		function table($tn) {
			$ti = $this->table_index($tn);
			if($ti == -1) return false;
			return $this->project_xml->table[$ti];
		}

		/**
			@param $tn string, name of table
			@param $fn string, name of field
			@return field object for given table and field names if exist, false otherwise
		*/
		function field($tn, $fn) {
			$ti = $this->table_index($tn);
			if($ti == -1) return false;

			$fi = $this->field_index($tn, $fn);
			if($fi == -1) return false;

			return $this->project_xml->table[$ti]->field[$fi];
		}

		/**
			@param $tn string, name of table
			@param $fn string, name of field
			@return 0-based field index for given table and field names if exist, -1 otherwise
		*/
		function field_index($tn, $fn) {
			$table = $this->table($tn);
			if($table === false) return -1;

			// find field
			for($fi = 0; $fi < count($table->field); $fi++) {
				$cfn = (string) $table->field[$fi]->name;
				if($cfn == $fn) return $fi;
			}

			return -1;
		}

		/**
			@param $tn string, name of table
			@return 0-based index of given table name if exists, -1 otherwise
		*/
		function table_index($tn) {
			$prj = $this->project_xml;

			// find table
			for($ti = 0; $ti < count($prj->table); $ti++) {
				$ctn = (string) $prj->table[$ti]->name;
				if($ctn == $tn) return $ti;
			}

			return -1;
		}

		/**
		 * exit execution with HTTP status code 500 in JSON mode
		 *
		 * @param      <string>  $error  The error message response
		 */
		public function ajax_json_error($error) {
			@header('Content-type: application/json');
			@header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
			die(json_encode(array('error' => $error)));
		}
		
		/**
		 * exit execution normally, sending given $data as JSON response
		 *
		 * @param      <(object|array)>  $data   The data to output as JSON response
		 */
		public function ajax_json_return($data) {
			@header('Content-type: application/json');
			die(json_encode($data));
		}

		/**
		 * Retrieve plugin node for given table name, or false if none. Assumes table structure as follows: table->plugins->plugin_name->{node-to-return}
		 *
		 * @param      <string>  $tn     table name
		 * @return     <(object|boolean)> plugin object or false if not found
		 */
		public function get_table_plugin_node($tn) {
			$table = $this->table($tn);
			if($table === false) return false;

			if(!isset($table->plugins)) return false;
			if(!isset($table->plugins->{$this->name})) return false;

			return $table->plugins->{$this->name};
		}

		/**
		 *  Get date/time format string for use in different cases.
		 *  
		 *  @param [in] $destination string, one of these: 'php' (see date function), 'mysql', 'moment'
		 *  @param [in] $datetime string, one of these: 'd' = date, 't' = time, 'dt' = both
		 *  @return string
		 */
		public function datetime_format($destination = 'php', $datetime = 'd') {
			$date_formats = array(
				1 => 'Ymd',
				2 => 'dmY',
				3 => 'mdY'
			);

			$time_formats = array(
				12 => 'h:i:s A',
				24 => 'H:i:s'
			);

			$separators = array(
				1 => '-',
				2 => ' ',
				3 => '.',
				4 => '/',
				5 => ','
			);
			
			$df_raw = $date_formats[(int) $this->project_xml->dateFormat];
			$tf = $time_formats[(int) $this->project_xml->timeFormat];
			$sep = $separators[(int) $this->project_xml->dateSeparator];

			$df = "{$df_raw[0]}{$sep}{$df_raw[1]}{$sep}{$df_raw[2]}";

			switch(strtolower($destination)){
				case 'mysql':
					$date = str_replace(array('m', 'd', 'y'), array('%m', '%d', '%Y'), $df);
					$time = str_replace(array('H', 'h', 'i', 's', 'A'), array('%H', '%h', '%i', '%s', '%p'), $tf);
					break;
				case 'moment':
					$date = str_replace(array('m', 'd', 'y'), array('MM', 'DD', 'YYYY'), $df);
					$time = str_replace(array('H', 'h', 'i', 's'), array('HH', 'hh', 'mm', 'ss'), $tf);
					break;
				default: // php
					$date = $df;
					$time = $tf;
			}

			$datetime = strtolower($datetime);
			if($datetime == 'dt' || $datetime == 'td') return "{$date} {$time}";
			if($datetime == 't') return $time;
			return $date;
		}

		public function header_nav() {
			ob_start(); ?>
			<nav class="navbar navbar-default navbar-fixed-top">
				<div class="container-fluid">
					<div class="navbar-header">
						<a class="navbar-brand" href="#">
							<img alt="Brand" src="<?php echo $this->name; ?>-logo-lg.png" style="height: 1.5em; display: inline-block;"> <?php echo $this->title; ?>
						</a>
					</div>

					<form class="navbar-form navbar-right hidden" id="ui-language-settings">
						<div class="form-group">
							<select class="form-control" id="selected-language"></select>
						</div>
					</form>
				</div>
			</nav>

			<script>
				$j(function() {
					// load available languages into #selected-language drop-down (only if 2 or more found)
					AppGiniPlugin.Translate.ready(function() {
						$j.ajax({
							url: '../plugins-resources/language/',
							success: function(langs) {
								$j('#ui-language-settings').toggleClass('hidden', langs.length < 2);
								// english only? abort.
								if(langs.length < 2) return;

								// populate drop-down
								AppGiniPlugin.populate_select({
									select: '#selected-language',
									options: langs.map(function(l) { return { id: l, text: l.toUpperCase() }; }),
									selected_id: AppGiniPlugin.selectedLanguage
								});

								// handle changing language
								$j('#selected-language').change(function() {
									AppGiniPlugin.Translate.setLang($j(this).val());
									location.reload();
								})
							}
						});
					});
				})
			</script>
			<?php return ob_get_clean();
		}

		/**
		 * Create Bootstrap breadcrumb from provided links
		 *
		 * @param      array  $links  The links ['url' => 'text', ... ] .. for the active page, use an empty url
		 *
		 * @return     string  HTML code for displaying breadcrumb
		 */
		public function breadcrumb($links) {
			ob_start();
			echo '<ol class="breadcrumb h3" style="margin-top: 3.5em;">';
			foreach($links as $url => $text) {
				if($url) {
					echo "<li><a href=\"{$url}\">{$text}</a></li>";
				} else {
					echo "<li class=\"active\">{$text}</li>";
				}
			}
			echo '</ol>';
			return ob_get_clean();
		}

		public function object_to_array($obj) {
			if(is_object($obj)) $obj = json_decode(json_encode($obj), true);
			return $obj;
		}

		/**
		 * Replace the special chars ﹣﹣ with a tab in provided code, 
		 * and removing any other initial white spaces.
		 * useful for keeping generated code nicely formatted in genenrator
		 *
		 * @param      string  $code   The code to reformat
		 *
		 * @return     string  formatted output code
		 */
		public function format_indents($code) {
			$code_arr = explode("\n", $code);
			$code_arr = preg_replace('/^\s+/', '', $code_arr);
			$code_arr = preg_replace('/﹣﹣/', "\t", $code_arr);

			return implode("\n", $code_arr);
		}
	}
