AppGiniPlugin.language = AppGiniPlugin.language || {};
AppGiniPlugin.language.en = $j.extend(AppGiniPlugin.language.en, {
	rtl: false,

	FAILED: 'FAILED',
	OK: 'OK',
	SKIPPED: 'SKIPPED',

	copying_folder: 'Copying folder %src% ...',
	failed_to_copy_n_subfolders_from: 'Failed to copy %num_errors% subfolder(s)/file(s) from %src%.',
	folder_x_copied: 'Folder %src% copied successfully.',
	Error: 'Error:',
	Back: 'Back',
	couldnt_create_projects_dir: 'Could not create projects directory.<br>Please create \'projects\' directory inside the plugins directory',
	change_permissions_projects_dir: 'Please change the permission of the \'projects\' folder to be writeable.',
	invalid_project_file_name: 'Invalid project file name',
	path_to_appgini_app: 'Path to destination AppGini app',
	please_wait: 'Please wait ...',

	specify_full_path_appgini_app: 'Specify the full path of the AppGini application you want to install the output code to. Example: ',
	Continue: 'Continue',
	drag_appgini_axp_here: 'Drag your AppGini project file (*.axp) here to open it.',
	or_click_open_upload: 'Or click to open the upload dialog.',
	or_open_project_uploaded: 'Or open a project you uploaded before',
	projects_found: 'projects found:',
	click_project_to_load: 'Click on a project to load it',
	are_you_sure_delete_axp: 'Are you sure you want to delete this project file?',
	file_uploaded_success: 'File uploaded successfully.',
	project_exists_renamed: 'The project name already exists, the file was renamed to %new_name%.',
	must_upload_axp: 'You must upload a (.axp) file',
	couldnt_delete_axp: 'Couldn\'t delete this project file.',
	download_axp: 'Download this project file.',
	delete_axp: 'Delete this project file',

	valid_path: 'Valid path',
	invalid_path: 'Invalid path',
	



	/*************************************************************/
	end_place_holder: '--- Please keep this line at the end of the file! ---'
})
