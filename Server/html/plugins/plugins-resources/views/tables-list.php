<?php if(!isset($this)) die("You can't call this file directly."); ?>

<?php
	/* if you change something here, consider also changing views/list.php */
	
	/*
		The following variables are assumed to exist when calling this view file:
		-------------------------------------------------------------------------
		$list_id -- id attribute for the list container
		$classes -- CSS classes for the list container
		$axp -- the AppGini project object
		$click_handler -- js function for handling clicks, receives the clicked item index
		$select_first_table -- boolean to specify if first item in the list is initially clicked
	*/
?>

<div id="<?php echo $list_id; ?>" class="<?php echo $classes; ?> list-group">

	<?php for($i= 0; $i < count($axp->table); $i++) { ?>
		<a href="#" class="list-group-item" data-table_index="<?php echo $i; ?>">
			<?php if(!empty($axp->table[$i]->tableIcon) && is_file(__DIR__ . "/../table_icons/{$axp->table[$i]->tableIcon}")) { ?>
				<img src="../plugins-resources/table_icons/<?php echo $axp->table[$i]->tableIcon; ?>">
			<?php } else { ?>
				<img src="../plugins-resources/table_icons/application_view_columns.png">
			<?php } ?>
			<?php echo $axp->table[$i]->caption; ?>
		</a>
	<?php } ?>

</div>

<style>
	#<?php echo $list_id; ?> {
		min-height: 5rem;
		max-height: 70vh;
		overflow-y: auto;
	}
</style>

<script>
	$j(function() {
		var listId = <?php echo json_encode("#$list_id"); ?>;

		/* call 'click_handler' function on clicking a table from the list */
		$j(listId + ' .list-group-item').click(function() {
			$j(listId + ' a').removeClass("active");
			$j(this).addClass("active");
			
			<?php echo $click_handler; ?>($j(this).data('table_index'));
			
			return false;
		});
		
		<?php if($select_first_table){ ?>
			/* select the first table on page load */
			$j(listId + ' > a').first().focus().click();
		<?php } ?>
		
		/* set table list height on resizing the page */
		var adjust_table_list_height = function(){
			$j(listId).css({
				'max-height': $j(window).height() - $j('.page-header').outerHeight(true) - 20 + 'px'
			});
		}
		$j(window).resize(adjust_table_list_height);
		adjust_table_list_height();
		
		/* allow navigating the list through keyboard arrow keys */
		$j(listId).on('keydown', '.list-group-item', function(e){
			switch(e.which){
				case 38: // up arrow
					e.preventDefault();
					var prev = $j(this).prev();
					if(prev.length) prev.focus().click();
					break;
				case 40: // down arrow
					e.preventDefault();
					var next = $j(this).next();
					if(next.length) next.focus().click();
					break;
				case 36: // home
					e.preventDefault();
					var first = $j(listId + ' .list-group-item').first();
					if(first.length) first.focus().click();
					break;
				case 35: // end
					e.preventDefault();
					var last = $j(listId + ' .list-group-item').last();
					if(last.length) last.focus().click();
					break;
			}
		});
	})
</script>










