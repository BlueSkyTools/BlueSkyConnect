<?php if(!isset($this)) die("You can't call this file directly."); ?>

<link href="../plugins-resources/dropzone/dropzone.min.css" rel="stylesheet">

<?php if($header_nav) { ?>

	<?php echo $this->header_nav(); ?>
	<div style="height: 5em;"></div>
	<style>.page-header { display: none; } </style>

<?php } else { ?>

	<div class="page-header">
		<h1><img src="<?php echo html_attr($this->logo); ?>" style="height: 1em;"> <?php echo $this->title; ?></h1>
	</div>

<?php } ?>

<?php echo $pre_upload; ?>
<div class="clearfix"></div>

<div> 
	<div id="response"></div>
	<form method="post" id="my-awesome-dropzone" class="dropzone" autocomplete="off" enctype="multipart/form-data">
		<div class="dz-default dz-message">
			<h1>
				<i class="glyphicon glyphicon-upload text-primary" style="font-size: 300%;"></i><br>
				<span class="language" data-key="drag_appgini_axp_here">Drag your AppGini project file (*.axp) here to open it.</span><br>
				<small class="language" data-key="or_click_open_upload">Or click to open the upload dialog.</small>			
			</h1>
		</div>
	</form>
</div>

<?php echo $post_upload; ?>
<div class="clearfix"></div>

<?php if($projectsNum) { ?>
	<div id="projects-dialog" class="hidden">
		<div class="alert alert-danger project-delete-error hidden language" data-key="couldnt_delete_axp"><?php echo $this->translation['Couldn\'t delete this record']; ?></div>
		<div class="row">
			<?php foreach($currentProjects as $projName) { ?>
				<div class="col-lg-3 col-md-3 col-sm-6 col-xs-6 project-container">
					<div class="well">
						<div class="caption text-center">
							<a title="<?php echo htmlspecialchars($projName); ?>" href="<?php echo $redirect_to; ?>?axp=<?php echo md5($projName); ?>">
								<img src="../plugins-resources/images/bigprof-logo-only.png"><br>
								<?php echo strlen($projName) > 15 ? substr($projName, 0, 9) . '...' : substr($projName, 0, -4); ?>
							</a>
							<div class="project-actions text-center" data-axp="<?php echo md5($projName); ?>">
								<a href="#" class="btn btn-default btn-sm project-download"><i class="glyphicon glyphicon-download"></i></a>
								<a href="#" class="btn btn-default btn-sm project-delete"><i class="text-danger glyphicon glyphicon-trash"></i></a>
							</div>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>

	<h4 class="pull-right">
		<a class="open-projects" href="#">
			<i class="glyphicon glyphicon-folder-open text-info"></i>&nbsp;
			<span class="language" data-key="or_open_project_uploaded">Or open a project you uploaded before</span>
			(<span class="language" data-key="projects_found">projects found:</span> <span class="projects-count"><?php echo $projectsNum; ?></span>)
		</a>
	</h4>
	<div class="clearfix"></div>

	<script>
		$j(function(){
			$j('.open-projects').click(function(e){
				e.preventDefault();
				modal_window({
					message: $j('#projects-dialog').html(), 
					title: AppGiniPlugin.Translate.word('click_project_to_load')
				});
			})

			AppGiniPlugin.Translate.ready(function() {
				$j('.project-delete').attr('title', AppGiniPlugin.Translate.word('delete_axp'));
				$j('.project-download').attr('title', AppGiniPlugin.Translate.word('download_axp'));
			});


			$j(document).on('click', '.project-download', function(e) {
				e.preventDefault();
				var axp = $j(this).parents('.project-actions').data('axp');
				location.href = '../plugins-resources/download-project.php?axp=' + axp;
			})

			$j(document).on('click', '.project-delete', function(e) {
				e.preventDefault();

				if(!confirm(AppGiniPlugin.Translate.word('are_you_sure_delete_axp'))) return;
				var prj = $j(this).parents('.project-actions'), axp = prj.data('axp');
				
				$j('.project-delete-error').addClass('hidden');
				$j.ajax({
					url: '../plugins-resources/ajax-delete-project.php',
					data: { axp: axp },
					success: function() {
						prj.parents('.project-container').animate(
							{ opacity: 0 }, 
							400, 
							function() {
								$j(this).addClass('hidden');

								// update future modal contents to the same as the current one
								$j('#projects-dialog').html($j('.modal-body:visible').html());
							}
						);
						var num = parseInt($j('.projects-count').text());
						$j('.projects-count').html(num - 1);
					},
					error: function() {
						$j('.project-delete-error').removeClass('hidden');
					}
				});
			})
		});
	</script>
<?php } ?>

<?php if($updateMessage) { ?>
	<div class="alert alert-warning vspacer-lg"><?php echo $updateMessage; ?></div>
<?php } ?>

<style>
	.dz-image , .dz-preview{
		width: 100% !important;
		margin: auto !important;
	}	
	.dropzone {
	    border: 3px dashed darkblue;
		min-height: 100px;
		-webkit-border-radius: 30px;
		border-radius: 30px;
		background: rgba(50, 50, 50, 0.06);
		padding: 23px;
	}
	
	.dz-default > img{
		max-width:100%;
		max-height:100%;
	}
	
	.row .thumbnail {
		height: 160px;
		overflow: hidden;
	}

	.modal .caption img {
		max-width: 76px;
		width: 90%;
		border: solid 1px #aef3f5;
		border-radius: 4px;
		background-color: #fff;
	}
</style>


<script src="../plugins-resources/dropzone/dropzone.min.js"></script>
<script>
	Dropzone.options.myAwesomeDropzone = {
		paramName: "uploadedFile", // The name that will be used to transfer the file
		url: "../plugins-resources/upload-ajax.php",
		acceptedFiles: ".axp,.AXP",
		uploadMultiple: false,
		maxFiles: 1,
		accept: function(file, done) {
		done();
		},
		init: function() {
			this.on("success", function(file, response) {
				$j(".dropzone").css( "border" ,"3px dotted blue");
				response = JSON.parse(response);
				if ( response["response-type"] =="success"){
					var successDiv = $j("<div>", {class: "alert alert-success" , style: "display: none; padding-top: 6px; padding-bottom: 6px;"});
					var successMsg = '' + 
						AppGiniPlugin.Translate.word('file_uploaded_success') + (
							response.isRenamed ? 
							'<br>' + AppGiniPlugin.Translate.word('project_exists_renamed', { new_name: response.fileName }) : ''
						) + 
						'<br>' + AppGiniPlugin.Translate.word('please_wait');
					successDiv.html( successMsg );
					$j("#response").html(successDiv);
					dismissible_msg(successDiv , "<?php echo $redirect_to; ?>?axp=" + response.md5FileName);
				}
			});

			this.on("error", function(file, response) {
				response = AppGiniPlugin.Translate.word('must_upload_axp'); //dropzone sends it's own error messages in string
				if(undefined === response.error) response = response.error;
					
				$j("#response").html("<div class='alert alert-danger'>" + response + "</div>");
				$j(".dropzone").css( "border" ,"3px dotted red");
				
				setTimeout( deleteFile, 5000 , file , this);
			});
		}
	}

	function deleteFile(file, elm) {
		elm.removeFile(file);
	}
</script>
