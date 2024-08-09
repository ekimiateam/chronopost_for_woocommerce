<?php
$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
$limit = 10;
$offset = ( $pagenum - 1 ) * $limit;
// Get the orders
$_orders = WC_Chronopost_Order::get_orders($limit, $pagenum);

$total = WC_Chronopost_Order::get_post_count();
$num_of_pages = ceil( $total / $limit );
?>
<div class="wrap">
	<form id="shipment-list" method="post" action="<?php echo admin_url('admin.php?page=chronopost-shipping&pagenum='.$pagenum); ?>">
		<?php if ($total === 0): ?>
			<h1><?php _e("Chronopost shipments", 'chronopost') ?></h1>
			<p><?php _e('No results found.'); ?></p>
		<?php else: ?>
			<hr class="wp-header-end">

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php _e( 'Select bulk action' ); ?></label><select name="chronoaction" id="bulk-action-selector-top">
						<option value="-1"><?php _e( 'Bulk Actions' ); ?></option>
						<option value="print-label" class="hide-if-no-js"><?php echo __('Generate label', 'chronopost'); ?></option>
						<option value="cancel-label" class="hide-if-no-js"><?php echo __('Cancel label', 'chronopost'); ?></option>
					</select>
					<input type="submit" id="doaction" class="button action" value="<?php _e('Apply'); ?>">
					<span class="spinner"></span>
				</div>

				<div class="tablenav-pages">
					<?php
					$page_links = paginate_links( array(
							'base' => add_query_arg( 'pagenum', '%#%' ),
							'format' => '',
							'prev_text'          => '<span aria-label="' . esc_attr__( 'Previous page' ) . '">' . __( '&laquo;' ) . '</span>',
							'next_text'          => '<span aria-label="' . esc_attr__( 'Next page' ) . '">' . __( '&raquo;' ) . '</span>',
							'before_page_number' => '<span class="screen-reader-text">' . __( 'Page' ) . '</span> ',
							'total'   => $num_of_pages,
							'current' => $pagenum
					));

					if ( $page_links ) {
						echo '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total ), number_format_i18n( $total ) ) . '</span>';
						echo $page_links;
					}
					?>
				</div>
			</div>
			<input type="hidden" name="shipment_nonce" value="<?php echo wp_create_nonce( 'shipment_list_nonce' ); ?>">

			<?php
			$dimensions = array('weight', 'height', 'length', 'width');
			$weight_unit = chrono_get_weight_unit();
			?>
			<?php $localized_dimensions = array(__('weight', 'chronopost'), __('height', 'chronopost'), __('length', 'chronopost'), __('width', 'chronopost')); ?>

			<table class="wp-list-table widefat fixed striped posts" cellpadding="0" cellspacing="0">
				<thead>
				<td id="cb" class="manage-column column-cb check-column">
					<label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select all'); ?></label>
					<input id="cb-select-all-1" type="checkbox">
				</td>
				<th class="manage-column column-order_status"><?php _e("Status", 'chronopost') ?></th>
				<th class="manage-colum column-order_date"><?php _e("Order", 'woocommerce') ?></th>
				<th class="manage-colum column-contract"><?php _e("Use contract", 'chronopost') ?></th>
				<th class="manage-colum column-contract"><?php _e("Shipping settings", 'chronopost' ) ?></th>
				<th class="manage-colum column-contract"><?php _e('Insurance amount', 'chronopost' ) ?></th>
				<th class="manage-colum column-order_date"><?php _e("Ship On Saturday", 'chronopost') ?></th>
				<th class="manage-colum column-actions"><?php _e("Tracking", 'chronopost') ?></th>
				<th class="manage-colum column-actions"><?php _e("Labels", 'chronopost') ?></th>
				<th class="manage-colum column-actions"><?php _e('Return labels', 'chronopost') ?></th>
				</thead>
				<tbody>
				<?php while( $_orders->have_posts() ) : $_orders->the_post(); ?>
					<?php
					$_order = new WC_Order( get_the_ID() );
					$order_shipping_method = $_order->get_shipping_methods();
					$shipping_method = reset($order_shipping_method);
					$shipping_method_id = $shipping_method->get_method_id();
					$shipment_datas = chrono_get_shipment_datas($_order->get_id());
					$parcels_number = chrono_get_parcels_number($_order->get_id());
					?>
					<tr id="order-<?php echo $_order->get_id(); ?>">
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="cb-select-1"><?php printf(__('Select order #%s', 'chronopost'), $_order->get_id()); ?></label>
							<input id="cb-select-1" type="checkbox" name="order[]" value="<?php echo $_order->get_id(); ?>">
						</th>
						<td class="order_status column-order_status"><mark class="<?php echo str_replace('wc-', 'status-', get_post_status()) ?> order-status"><span><?php echo wc_get_order_status_name(get_post_status()) ?></span></mark></td>
						<td>
							<?php
							if ( $_order->get_customer_id() ) {
								$user     = get_user_by( 'id', $_order->get_customer_id() );
								$username = '<a href="user-edit.php?user_id=' . absint( $_order->get_customer_id() ) . '">';
								$username .= esc_html( ucwords( $user->display_name ) );
								$username .= '</a>';
							} elseif ( $_order->get_billing_first_name() || $_order->get_billing_last_name() ) {
								/* translators: 1: first name 2: last name */
								$username = trim( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), $_order->get_billing_first_name(), $_order->get_billing_last_name() ) );
							} elseif ( $_order->get_billing_company() ) {
								$username = trim( $_order->get_billing_company() );
							} else {
								$username = __( 'Guest', 'woocommerce' );
							}

							printf(
								__( '%1$s by %2$s', 'woocommerce' ),
								'<a href="' . admin_url( 'post.php?post=' . absint( $_order->get_id() ) . '&action=edit' ) . '" class="row-title"><strong>#' . esc_attr( $_order->get_order_number() ) . '</strong></a>',
								$username
							);

							if ( $_order->get_billing_email() ) {
								echo '<small class="meta email"><a href="' . esc_url( 'mailto:' . $_order->get_billing_email() ) . '">' . esc_html( $_order->get_billing_email() ) . '</a></small>';
							}
							?>

							<div class="shipped-to">
								<label class="manage-colum column-shipping_address"><?php _e("Shipped to", 'chronopost') ?></label>

								<?php
								$address = $_order->get_shipping_address_1() . ' ' . $_order->get_shipping_address_2() . ' ' . $_order->get_shipping_postcode() . ' ' .$_order->get_shipping_city();
								$address_link = 'https://maps.google.com/maps?&q=' . urlencode( $address ) . '&z=16';
								echo "<a href=\"$address_link\">". $_order->get_formatted_shipping_address() . "</a>";
								echo "<small class=\"meta\">{$shipping_method->get_name()}</small>";
								?>
							</div>
						</td>
						<td class="column-contract">
							<?php
							$shipping_method_contract = '';
							$shipping_method_instance = chrono_get_shipping_method_instance_by_order($_order);
							if (isset($shipping_method_instance->instance_settings['contract'])) {
								$shipping_method_contract = $shipping_method_instance->instance_settings['contract'];
							}
							$contracts = chrono_get_all_contracts();
							// Pourrait être surchargé par l'utilisateur
							$order_contract = get_post_meta( $_order->get_id(), '_use_contract', true);
							if ($order_contract) {
								$shipping_method_contract = $order_contract;
							}
							?>
							<select name="use-contract" data-order-id="<?php echo $_order->get_id(); ?>" <?php echo $shipment_datas ? 'disabled="disabled"' : '' ?>>
								<?php foreach ($contracts as $contract): ?>
									<option value="<?php echo $contract['number'] ?>"
										<?php echo ($shipping_method_contract == $contract['number']) ? 'selected="selected"' : ''?>>
										<?php echo $contract['label'] ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td class="chrono-order-settings">
							<div class="column-shipping-grid">
								<div class="column-parcels">
									<label for="field-parcels" class="manage-colum column-parcels"><?php _e("Parcels number", 'chronopost') ?></label>
									<?php
									$max_weight = $shipping_method->get_method_id() == 'chronorelais' || $shipping_method->get_method_id() == 'chronorelaiseurope' || $shipping_method->get_method_id() == 'chronotoshopeurope' || $shipping_method->get_method_id() == 'chronorelaisdom' ? 20 : 30;
									?>
									<div class="sub-field">
										<input id="field-parcels" name="parcels" type="number" value="<?php echo $parcels_number ?>"
											   data-action="update_parcels" min="1"
                                               <?php if (chrono_is_toshop($shipping_method)):  ?>
                                                   disabled="disabled" max="1"
                                               <?php endif; ?>
											   data-order-id="<?php echo $_order->get_id(); ?>" />
									</div>
								</div>
								<?php

								$defaultWeight = Chronopost_Package::getTotalWeight($_order->get_items(), false);
								$default_dimensions = array(
									'weight' => $defaultWeight,
									'height' => 1,
									'length' => 1,
									'width' => 1
								);

								$parcels_dimensions = chrono_get_parcels_dimensions($_order->get_id());
								if (count($parcels_dimensions) === 0) {
									$parcels_dimensions = array( 1 => $default_dimensions );
								}

								$minParcels = count($parcels_dimensions);

								if ($weight_unit === 'g') {
									$defaultWeight = $defaultWeight * 1000;
								}
								?>
								<div class="parcels-settings">
									<?php foreach ($parcels_dimensions as $i => $parcel_dimensions): ?>
										<div class="package-dimensions <?php echo $i == 1 ? 'default' : '' ?>">
											<?php foreach ($parcel_dimensions as $dimension => $value) : ?>
												<?php
												if ($dimension === 'weight') {
													$max_weight = $shipping_method->get_method_id() == 'chronorelais' || $shipping_method->get_method_id() == 'chronorelaiseurope' || $shipping_method->get_method_id() == 'chronotoshopeurope' || $shipping_method->get_method_id() == 'chronorelaisdom' ? 20 : 30;
													if ($weight_unit === 'g') {
														$max_weight = $max_weight * 1000;
														$value = $value * 1000;
													}
												}
												?>
												<label class="column-dimensions">
												<span>
													<?php echo ucfirst(__($dimension, 'chronopost')) ?>
													<?php if ($dimension === 'weight'): ?>
														(<?php echo $weight_unit; ?>)
													<?php endif; ?>
												</span>
													<div class="sub-field">
														<input name="parcels_dimensions[1][<?php echo $dimension ?>]" type="number" class="default"
															   placeholder="<?php echo ucfirst(__($dimension, 'chronopost')) ?>"
															   value="<?php echo $value ?>"
															<?php echo $dimension == 'weight' ? 'step=".1" max="' . $max_weight . '"' : ''; ?>
															   data-order-id="<?php echo $_order->get_id(); ?>" />
													</div>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</td>
						<td class="column-insurance">
							<?php if (chrono_get_option('enable', 'insurance') == 'yes'): ?>
							<label class="manage-colum column-order_date"><?php _e("Enable?", 'chronopost') ?></label>
							<?php
							$insurance_enable = get_post_meta($_order->get_id(), '_insurance_enable', true) == '' ? 'yes' : get_post_meta($_order->get_id(), '_insurance_enable', true);
							$forceDisableInsurance = false;
							$insurance_enable = get_post_meta($_order->get_id(), '_insurance_enable', true) == '' ? 'yes' : get_post_meta($_order->get_id(), '_insurance_enable', true);

							$adValoremAmount = (float)chrono_get_option('min_amount', 'insurance');
							if ($_order->get_subtotal() < $adValoremAmount) {
								$insurance_enable = 'no';
								$forceDisableInsurance = true;
							}

                            // 2S can't be insured
                            if ($shipping_method->get_method_id() === 'chronotoshopdirect' || $shipping_method->get_method_id() === 'chronotoshopeurope') {
                                $insurance_enable = 'no';
                                $forceDisableInsurance = true;
                            }
							?>
							<div class="insurance-enable sub-field">
								<select
										name="insurance[enable]"
										data-order-id="<?php echo $_order->get_id(); ?>"
										data-action="update_insurance_enable"
										<?php echo $forceDisableInsurance ? 'disabled="disabled"' : '' ?>
								>
									<option value="no"<?php echo $insurance_enable == 'no' ? ' selected="selected"' : ''; ?>><?php _e('No', 'chronopost'); ?></option>
									<option value="yes"<?php echo $insurance_enable == 'yes' ? ' selected="selected"' : ''; ?>><?php _e('Yes', 'chronopost'); ?></option>
								</select>
							</div>
							<label class="manage-colum column-order_date"><?php _e('Amount', 'chronopost'); ?></label>
							<div class="sub-field">
								<input type="number" value="<?php echo chrono_get_advalorem_amount( $_order ) / 100; ?>" step="0.01"
									   id="insurance_amount" name="insurance[amount]"
										<?php echo $forceDisableInsurance ? 'disabled="disabled"' : '' ?>
									   data-order-id="<?php echo $_order->get_id(); ?>">
							</div>
							<?php else : ?>
								<?php _e('Not active', 'chronopost'); ?>
							<?php endif; ?>
						</td>

						<td class="ship-on-saturday">
							<?php
							// Find the shipping instance
							$order_shipping_instance = null;
							foreach ($_order->get_shipping_methods() as $item_shipping_method) {
								$order_shipping_instance = $item_shipping_method->get_instance_id();
								break;
							}
							$saturday_active = chrono_get_method_settings($shipping_method_id, $order_shipping_instance, 'deliver_on_saturday') == 'yes' ? true : false;
							$ship_saturday = get_post_meta( $_order->get_id(), '_ship_on_saturday', true);
							$user_defined = get_post_meta( $_order->get_id(), '_ship_on_saturday_user_defined', true);

							if ($ship_saturday == '' && $user_defined !== 'yes') {
								$ship_saturday = chrono_is_sending_day() ? 'yes' : 'no';
							}

							?>
							<?php if ($saturday_active && !chrono_is_shipping_methods_without_saturday($shipping_method_id)): ?>
								<select name="ship-saturday" data-order-id="<?php echo $_order->get_id(); ?>"<?php echo $user_defined === 'yes' ? ' disabled="disabled"' : ''; ?>>
									<option value="no"<?php echo $ship_saturday == 'no' ? ' selected="selected"' : ''; ?>><?php _e('No', 'chronopost'); ?></option>
									<option value="yes"<?php echo $ship_saturday == 'yes' ? ' selected="selected"' : ''; ?>><?php _e('Yes', 'chronopost'); ?></option>
								</select>
							<?php else: ?>
								<?php _e('Not active', 'chronopost'); ?>
							<?php endif; ?>
						</td>
						<td class="tracking-link">
							<?php if ($shipment_datas): ?>
								<?php foreach($shipment_datas as $shipment): ?>
									<?php foreach($shipment['_parcels'] as $parcel): ?>
										<div><a target="_blank" href="<?php echo chrono_get_tracking_url($parcel['_skybill_number'], $shipping_method_id); ?>"><?php echo $parcel['_skybill_number']; ?></a></div>
									<?php endforeach; ?>
								<?php endforeach; ?>
							<?php endif; ?>
							<span class="spinner"></span>
						</td>
						<td class="label-printing">
							<?php if ($shipment_datas): ?>
								<a class="button button-small chrono-print" target="_blank" href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=view-label&order='.$_order->get_id().'&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' )); ?>"><?php echo _n( 'Download label', 'Download labels', count($shipment_datas), 'chronopost' ); ?></a>
								<a class="button button-small button-primary chrono-generate-label" data-order-id="<?php echo $_order->get_id(); ?>" href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=print-label&order='.$_order->get_id().'&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' )); ?>"><?php echo __('Generate label', 'chronopost'); ?></a>
							<?php else: ?>
								<a class="button button-small button-primary chrono-generate-label" data-order-id="<?php echo $_order->get_id(); ?>" href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=print-label&order='.$_order->get_id().'&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' )); ?>"><?php echo __('Generate label', 'chronopost'); ?></a>
							<?php endif; ?>
						</td>
						<td class="return-printing">
							<?php if ($shipment_datas): ?>
								<?php foreach($shipment_datas as $shipment): ?>
									<?php foreach($shipment['_parcels'] as $parcel): ?>
										<?php
										$pdf_path = chrono_get_media_path().'chronopost-etiquette-retour-' . $parcel['_skybill_number'] . '.pdf';
										$return_label = chrono_get_return_label($_order->get_id(), $parcel['_skybill_number']);
										?>
										<?php if (file_exists($pdf_path)): ?>
											<?php if ($return_label): ?>
												<div class="return-action-wrapper">
													<a class="button button-small" target="_blank" href="<?php echo str_replace(chrono_get_media_path(), chrono_get_media_url(), $pdf_path); ?>">
														<?php _e('Return label', 'chronopost'); ?> <?php echo $parcel['_skybill_number']; ?>
													</a>
													<div class="return-action">
														<span class="chrono-icon-plus"></span>
														<ul>
															<li><a href="<?php echo str_replace(chrono_get_media_path(), chrono_get_media_url(), $pdf_path); ?>"><?php _e('View return label', 'chronopost'); ?></a></li>
															<li><a href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=return-label&order='.$_order->get_id().'&skybill_id=' . $parcel['_skybill_number'] . '&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' ).'&pagenum='.$pagenum); ?>"><?php _e('Generate return label again', 'chronopost'); ?></a></li>
															<li><a href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=return-label&method=delete&order='.$_order->get_id().'&return_skybill_id=' . $return_label . '&skybill_id=' . $parcel['_skybill_number'] . '&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' ).'&pagenum='.$pagenum); ?>"><?php _e('Delete return label', 'chronopost'); ?></a></li>
														</ul>
													</div>
												</div>
											<?php else: ?>
												<a class="button button-small" target="_blank" href="<?php echo str_replace(chrono_get_media_path(), chrono_get_media_url(), $pdf_path); ?>">
													<?php _e('Return label', 'chronopost'); ?> <?php echo $parcel['_skybill_number']; ?>
												</a>
											<?php endif; ?>
										<?php else: ?>
                                            <a class="button button-small button-primary" href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=return-label&order='.$_order->get_id().'&skybill_id=' . $parcel['_skybill_number'] . '&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' ).'&pagenum='.$pagenum); ?>"><?php _e('Generate return label', 'chronopost'); ?> <?php echo $parcel['_skybill_number']; ?></a>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					$page_links = paginate_links( array(
							'base' => add_query_arg( 'pagenum', '%#%' ),
							'format' => '',
							'prev_text'          => '<span aria-label="' . esc_attr__( 'Previous page' ) . '">' . __( '&laquo;' ) . '</span>',
							'next_text'          => '<span aria-label="' . esc_attr__( 'Next page' ) . '">' . __( '&raquo;' ) . '</span>',
							'before_page_number' => '<span class="screen-reader-text">' . __( 'Page' ) . '</span> ',
							'total'   => $num_of_pages,
							'current' => $pagenum
					));

					if ( $page_links ) {
						echo '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total ), number_format_i18n( $total ) ) . '</span>';
						echo $page_links;
					}
					?>
				</div>
				<br class="clear">
			</div>
		<?php endif; ?>
	</form>
</div>
