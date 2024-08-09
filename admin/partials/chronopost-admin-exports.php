<?php
$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
$limit = 10;
$offset = ( $pagenum - 1 ) * $limit;
// Get the orders
$_orders = WC_Chronopost_Order::get_orders($limit, $pagenum);
$total = WC_Chronopost_Order::get_post_count(true);
$num_of_pages = ceil( $total / $limit );
?>

<div class="wrap">
    <form id="shipment-list" method="get">
        <h1><?php _e("Chronopost Exports", 'chronopost') ?></h1>
		<?php if ($total === 0): ?>
			<p><?php _e('No results found.'); ?></p>
		<?php else: ?>
			<hr class="wp-header-end">
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php _e( 'Select bulk action' ); ?></label><select name="chronoaction" id="bulk-action-selector-top">
					<option value="-1"><?php _e( 'Bulk Actions' ); ?></option>
					<option value="export-css" class="hide-if-no-js"><?php echo __('Export CSS', 'chronopost'); ?></option>
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

			<table class="wp-list-table widefat fixed striped posts" cellpadding="0" cellspacing="0">
				<thead>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select all'); ?></label>
						<input id="cb-select-all-1" type="checkbox">
					</td>
					<th class="manage-column column-order_status"><?php _e("Status", 'chronopost') ?></th>
					<th class="manage-colum column-order_date"><?php _e("Order", 'woocommerce') ?></th>
					<th class="manage-colum column-shipping_address"><?php _e("Shipped to", 'chronopost') ?></th>
					<th class="manage-colum column-order_date"><?php _e("Date", 'chronopost') ?></th>
					<th class="manage-colum column-actions"><?php _e("CSS", 'chronopost') ?></th>
				</thead>
				<tbody>
				<?php while( $_orders->have_posts() ) : $_orders->the_post(); ?>
					<?php
						$_order = new WC_Order( get_the_ID() );
						$order_shipping_method = $_order->get_shipping_methods();
						$shipping_method = reset($order_shipping_method);
						$shipping_method_id = $shipping_method->get_method_id();
						$shipment_datas = chrono_get_shipment_datas($_order->get_id());
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
						</td>
						<td>
							<?php
								$address = $_order->get_shipping_address_1() . ' ' . $_order->get_shipping_address_2() . ' ' . $_order->get_shipping_postcode() . ' ' .$_order->get_shipping_city();
								$address_link = 'https://maps.google.com/maps?&q=' . urlencode( $address ) . '&z=16';
								echo "<a href=\"$address_link\">". $_order->get_formatted_shipping_address() . "</a>";
								echo "<small class=\"meta\">{$shipping_method->get_name()}</small>";
							?>
						</td>
						<td>
							<?php echo get_the_date(); ?>
						</td>
					<td class="tracking-link">
						<a class="button button-small button-primary chrono-generate-label" data-order-id="<?php echo $_order->get_id(); ?>" href="<?php echo admin_url('admin.php?page=chronopost-shipping&chronoaction=export-css&order='.$_order->get_id().'&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' )); ?>"><?php echo __('Export CSS', 'chronopost'); ?></a>
						<span class="spinner"></span>
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
