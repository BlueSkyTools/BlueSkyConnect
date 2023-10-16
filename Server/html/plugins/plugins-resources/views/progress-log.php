<?php if(!isset($this)) die("You can't call this file directly."); ?>

<style>
	#progress{
		white-space: normal;
		font-family: Monaco, 'Courier New', monospace;
		font-size: 0.9em;
		overflow-y: auto;
		padding: 1.2em;
		height: 65vh;
	}
	#progress > p{
		margin-bottom: 0.1em;
		margin-top: 0.1em;
	}
	.spacer{
		margin-left: 3.6em;
	}
	#progress code{
		padding: 0.2em;
		font-size: 1.1em;
		font-weight: bold;
	}
</style>

<pre id="progress">
	<?php echo implode('', $messages) ?>
</pre>