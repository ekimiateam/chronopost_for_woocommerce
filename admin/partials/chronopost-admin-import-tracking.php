<?php settings_errors(); ?>
<table class="form-table show-table">
	<tr>
		<td>
			<div class="chronopost-imports">
				<div class="hint"><?php _e( 'Use this function to massively assign Chronopost parcel numbers to the desired orders. 
                            This is useful if you edit your waybills from a third-party application. (Eg ChronoShip Office ChronoShip Station ...).
                            The expected file must be in CSV format with semicolon separator.<br/><br/>It must contain 2 columns : <ol><li>WooCommerce orders reference</li><li>
                            Chronopost tracking number</li></ol><br/>An email contaning the tracking number and a link 
                            to follow the parcel will be sent to the customer.',
						'chronopost' ) ?></div>
				<div class="chronopost-import-trackings">
					<label><span><?php _e( 'Order reference column number', 'chronopost' ) ?></span><input
							name="chronopost_imports[general][order_id_column]" type="number" value="<?php echo chrono_get_imports_option('order_id_column') ?>" min="1"></label>
					<label><span><?php _e( 'Tracking column number', 'chronopost' ) ?></span><input
							name="chronopost_imports[general][tracking_number_column]" type="number" value="<?php echo chrono_get_imports_option('tracking_number_column') ?>" min="1"></label>
					<label><strong><?php _e( 'Pick a file to upload', 'chronopost' ) ?></strong>
						<input type="file" name="chronopost_tracking" value="">
					</label>
				</div>
			</div>
		</td>
	</tr>
</table>
