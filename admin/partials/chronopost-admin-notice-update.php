<div class="notice notice-success is-dismissible">
	<h2 class="full-width"><?php _e('Chronopost database upgrade needed', 'chronopost') ?></h2>
	<p><?php _e( 'The plugin has been updated and needs to upgrade the database to reflect major changes in the way the plugin handles carriers. This will run the following commands :', 'chronopost' ); ?></p>
	<ol>
		<li><?php _e('Check your current Chronopost shipping settings', 'chronopost') ?></li>
		<li><?php _e('Create the corresponding shipping zones if needed', 'chronopost') ?></li>
		<li><?php _e('Assign Chronopost carriers to the correct zone', 'chronopost') ?></li>
	</ol>
	<p><em><?php _e( 'The Chronopost Team', 'chronopost' ); ?></em></p>
	<p>
		<a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=shipping&chronopost_skip_upgrade=2.0.0' )  ?>" class="button"><?php _e('No, I will do this myself', 'chronopost') ?></a>
		<a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=shipping&chronopost_do_upgrade=2.0.0' )  ?>" class="button button-primary"><?php _e('Update database automatically', 'chronopost') ?></a>
	</p>
</div>
