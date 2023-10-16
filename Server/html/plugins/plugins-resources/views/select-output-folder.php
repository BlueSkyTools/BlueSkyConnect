<?php if(!isset($this)) die("You can't call this file directly."); ?>

<form method="post" action="<?php echo $next_page; ?>">
	<div class="form-group">
		<label for="<?php echo $path_parameter; ?>" class="control-label language" data-key="path_to_appgini_app"></label>
		<div class="input-group ltr">
			<input type="text" class="form-control" id="output-folder" name="<?php echo $path_parameter; ?>" value="<?php echo $this->app_path; ?>" autofocus>
			<span class="input-group-btn">
				<button class="btn btn-default" type="button" id="recheck-output-folder"><i class="glyphicon glyphicon-refresh"></i></button>
				<button class="btn btn-default" type="button" id="output-folder-status" disabled></button>
			</span>
		</div>
		<span class="help-block"><span class="language" data-key="specify_full_path_appgini_app"></span>
		<code>/var/www/html/photo-gallery</code></span>
	</div>
	
	<?php foreach($extra_options as $name => $label){ ?>
		<div class="checkbox">
			<label>
				<input type="checkbox" name="<?php echo html_attr($name); ?>" value="1">
				<b><?php echo $label; ?></b>
			</label>
		</div>
	<?php } ?>
	
	<div class="text-center"><button type="submit" class="btn btn-primary btn-lg" id="submit" style="padding: 0.5em 4em;"><span class="language hspacer-lg" data-key="Continue"></span><i class="glyphicon glyphicon-chevron-right"></i></button></div>
	
	<script>
		$j(function(){
			var current_check = false;

			var check_app_path = function(){
				if(current_check !== false) current_check.abort();
				
				var invalid_path = function(){
					$j('#output-folder-status')
						.removeClass('btn-success btn-default')
						.addClass('btn-danger')
						.html('<i class="glyphicon glyphicon-remove"></i> ' + AppGiniPlugin.Translate.word('invalid_path'));
					$j('#submit').prop('disabled', true);
					return false;
				}
				
				var valid_path = function(){
					$j('#output-folder-status')
						.removeClass('btn-danger btn-default')
						.addClass('btn-success')
						.html('<i class="glyphicon glyphicon-ok"></i> ' + AppGiniPlugin.Translate.word('valid_path'));
					$j('#submit').prop('disabled', false);
					return true;
				}
				
				var please_wait = function(){
					$j('#output-folder-status')
						.removeClass('btn-danger btn-success')
						.addClass('btn-default')
						.html(AppGiniPlugin.Translate.word('please_wait'));
					$j('#submit').prop('disabled', true);
				}
				
				var path = $j('#output-folder').val();
				if(!path.length) return invalid_path();
				
				please_wait();
				current_check = $j.ajax({
					url: '../plugins-resources/ajax-check-app-path.php',
					data: { path: path },
					success: valid_path,
					error: invalid_path
				})
			};
			
			AppGiniPlugin.Translate.ready(check_app_path);
			$j('#output-folder').keyup(check_app_path);
			$j('#recheck-output-folder').click(check_app_path);
		})
	</script>
</form>
