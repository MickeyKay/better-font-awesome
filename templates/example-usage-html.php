<?php
/**
 * HTML mark with instrucstions on how to use Font Awesome
 */
?>

<div class="bfa-usage-text">
	<h3><?php esc_html_e( 'Usage', 'better-font-awesome' ); ?></h3>

	<b><?php printf( esc_html_x( 'Font Awesome version %s', 'For version 4.x +', 'better-font-awesome' ), '4.x +' ); ?></b>
	<small>
		<a href="http://fontawesome.io/examples/">
			<?php echo esc_html_x( 'See all available options', 'For version 4.x +', 'better-font-awesome' ); ?> &raquo;
		</a>
	</small>
	<br/><br/>

	<?php $code_alternative_str = esc_html_x( 'or', 'Text between two variations of code markup examples', 'better-font-awesome' ); ?>

	<i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code>
	<?php echo $code_alternative_str; ?>
	<code>&lt;i class="fa-coffee"&gt;&lt;/i&gt;</code>
	<br/><br/>

	<i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="fa-2x"]</code>
	<?php echo $code_alternative_str; ?>
	<code>&lt;i class="fa-coffee fa-2x"&gt;&lt;/i&gt;</code>
	<br/><br/>

	<i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="fa-2x fa-rotate-90"]</code>
	<?php echo $code_alternative_str; ?>
	<code>&lt;i class="fa-coffee fa-2x fa-rotate-90"&gt;&lt;/i&gt;</code>
	<br/><br/><br/>

	<b><?php printf( esc_html_x( 'Font Awesome version %s', 'For version 3.x', 'better-font-awesome' ), '3.x' ); ?></b>
	<small>
		<a href="http://fontawesome.io/3.2.1/examples/">
			<?php echo esc_html_x( 'See all available options', 'For version 3.x', 'better-font-awesome' ); ?> &raquo;
		</a>
	</small>
	<br/><br/>

	<i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code>
	<?php echo $code_alternative_str; ?>
	<code>&lt;i class="icon-coffee"&gt;&lt;/i&gt;</code>
	<br/><br/>

	<i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="icon-2x"]</code>
	<?php echo $code_alternative_str; ?>
	<code>&lt;i class="icon-coffee icon-2x"&gt;&lt;/i&gt;</code>
	<br/><br/>

	<i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="icon-2x icon-rotate-90"]</code>
	<?php echo $code_alternative_str; ?>
	<code>&lt;i class="icon-coffee icon-2x icon-rotate-90"&gt;&lt;/i&gt;</code>

</div>
