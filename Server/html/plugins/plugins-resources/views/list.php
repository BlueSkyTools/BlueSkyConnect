<?php if(!isset($this)) die("You can't call this file directly."); ?>

<?php
	/* if you change something here, consider also changing views/tables-list.php */
	
	/*
		The following variables are assumed to exist when calling this view file:
		-------------------------------------------------------------------------
		$list_id -- id attribute for the list container
		$classes -- CSS classes for the list container
		$items -- array of items of the list. Each item is an assoc array as follows:
			'icon' -- optional path to icon file, relative to the main app path
			'glyphicon' -- optional Bootstrap glyphicon -- only the icon name is needed, example: "ok", "chevron-left", .. etc
			'label' -- label to display in the list
		$default_icon -- if no icon or glyphicon specified for an item, this icon is used if present
		$default_glyphicon -- if no icon or glyphicon specified for an item, this glyphicon is used if present
		$click_handler -- js function for handling clicks, receives the clicked item index
		$select_first_item -- boolean to specify if first item in the list is initially clicked
	*/
?>

<div id="<?php echo $list_id; ?>" class="<?php echo $classes; ?> list-group">
	
	<?php $i = 0; ?>
	<?php foreach($items as $item){ ?>
		<a href="#" class="list-group-item" data-item_index="<?php echo $i; ?>">
			<?php if(!empty($item['icon'])){ ?>
				<img src="../../<?php echo $item['icon'];?>">
			<?php } ?>

			<?php if(!empty($item['glyphicon'])){ ?>
				<i class="glyphicon glyphicon-<?php echo $item['glyphicon']; ?>"></i>
			<?php } ?>

			<?php if(empty($item['icon']) && empty($item['glyphicon'])){ ?>
				<?php if($default_icon){ ?><img src="../../<?php echo $default_icon;?>"><?php } ?>
				<?php if($default_glyphicon){ ?><i class="glyphicon glyphicon-<?php echo $default_glyphicon; ?>"></i><?php } ?>
			<?php } ?>

			<?php echo $item['label']; ?>
		</a>
		<?php $i++; ?>
	<?php }	?>

</div>

<style>
	#<?php echo $list_id; ?>{
		min-height: 150px;
		overflow-Y:scroll;
	}
</style>

<script>
	$j(function(){
		/* call 'click_handler' function on clicking an item from the list */
		$j('#<?php echo $list_id; ?> .list-group-item').click(function(){
			$j("#<?php echo $list_id; ?> a").removeClass("active");
			$j(this).addClass("active");
			
			<?php echo $click_handler; ?>($j(this).data('item_index'));
			
			return false;
		});
		
		<?php if($select_first_item){ ?>
			/* select the first item on page load */
			$j('#<?php echo $list_id; ?> > a').first().click();
		<?php } ?>
		
		/* set item list height on resizing the page */
		var adjust_item_list_height = function(){
			$j('#<?php echo $list_id; ?>').css({
				'max-height': $j(window).height() - $j('.page-header').outerHeight(true) - 20 + 'px'
			});
		}
		$j(window).resize(adjust_item_list_height);
		adjust_item_list_height();
		
		/* allow navigating the list through keyboard arrow keys */
		$j('#<?php echo $list_id; ?>').on('keydown', '.list-group-item', function(e){
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
					var first = $j(this).siblings().first();
					if(first.length) first.focus().click();
					break;
				case 35: // end
					e.preventDefault();
					var last = $j(this).siblings().last();
					if(last.length) last.focus().click();
					break;
			}
		});
	})
</script>










