<?php
// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class Daily_Docket_List_Table extends WP_List_Table {

	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = $this->get_items_per_page( 'chrono_order_per_page', 10 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items, //total number of items
				'per_page'    => $per_page, // how many items to show on a page
			)
		);

		$this->items = self::get_orders( $per_page, $current_page );

	}

	public static function get_orders( $per_page = 10, $page_number = 1 ) {
		$_orders = WC_Chronopost_Order::get_orders( $per_page, $page_number, true );

		$orders = array();
		if ($_orders->posts === null) {
			return $orders;
		}

		foreach ( $_orders->posts as $order ) {

			$_order                = new WC_Order( $order->ID );
			$order_shipping_method = $_order->get_shipping_methods();
			$shipping_method       = reset( $order_shipping_method );

			// Order datas

			if ( $_order->get_customer_id() ) {
				$user      = get_user_by( 'id', $_order->get_customer_id() );
				$username  = '<a href="user-edit.php?user_id=' . absint( $_order->get_customer_id() ) . '">';
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

			$order_datas = sprintf(
				__( '%1$s by %2$s', 'woocommerce' ),
				'<a href="' . admin_url( 'post.php?post=' . absint( $_order->get_id() ) . '&action=edit' ) . '" class="row-title"><strong>#' . esc_attr( $_order->get_order_number() ) . '</strong></a>',
				$username
			);

			if ( $_order->get_billing_email() ) {
				$order_datas .= '<small class="meta email"><a href="' . esc_url( 'mailto:' . $_order->get_billing_email() ) . '">' . esc_html( $_order->get_billing_email() ) . '</a></small>';
			}


			// Shipping to

			$address      = $_order->get_shipping_address_1() . ' ' . $_order->get_shipping_address_2() . ' ' . $_order->get_shipping_postcode() . ' ' . $_order->get_shipping_city();
			$address_link = 'https://maps.google.com/maps?&q=' . urlencode( $address ) . '&z=16';

			$shipped_to  = "<a href=\"$address_link\">" . $_order->get_formatted_shipping_address() . '</a>';
			$shipped_to .= "<small class=\"meta\">{$shipping_method->get_name()}</small>";


			// Post status

			$status = '<mark class="' . str_replace( 'wc-', 'status-', $_order->get_status() ) . ' order-status"><span>' . wc_get_order_status_name( $_order->get_status() ) . '</span></mark>';

			$orders[] = array(
				'ID'        => $order->ID,
				'status'    => $status,
				'orderdata' => $order_datas,
				'shippedto' => $shipped_to,
				'date'      => get_the_date( '', $order->ID ),
			);
		}

		return $orders;
	}

	/** Text when no customer data is available */
	public function no_items() {
		_e( 'No daily dockets.', 'chronopost' );
	}

	/**
	 * Render a column when no column specific method exists.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			default:
				return $item[ $column_name ]; //Show the whole array for troubleshooting purposes
		}
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		return WC_Chronopost_Order::get_post_count( true );
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return Array
	 */
	public function get_columns() {
		$columns = array(
			'cb'        => '<label class="screen-reader-text" for="cb-select-all-1">' . __( 'Select all' ) . '</label><input id="cb-select-all-1" type="checkbox">',
			'status'    => __( 'Status', 'chronopost' ),
			'orderdata' => __( 'Order', 'chronopost' ),
			'shippedto' => __( 'Shipped to', 'chronopost' ),
			'date'      => __( 'Date', 'chronopost' ),
		);

		return $columns;
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="order[]" value="%s" />', $item['ID']
		);
	}


	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'print-daily-docket' => __( 'Export daily dockets', 'chronopost' ),
		];

		return $actions;
	}



	/**
	 * Display the bulk actions dropdown.
	 *
	 * @since 3.1.0
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 */
	protected function bulk_actions( $which = '' ) {
		if ( is_null( $this->_actions ) ) {
			$this->_actions = $this->get_bulk_actions();
			/**
			 * Filters the list table Bulk Actions drop-down.
			 *
			 * The dynamic portion of the hook name, `$this->screen->id`, refers
			 * to the ID of the current screen, usually a string.
			 *
			 * This filter can currently only be used to remove bulk actions.
			 *
			 * @since 3.5.0
			 *
			 * @param string[] $actions An array of the available bulk actions.
			 */
			$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );  // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$two            = '';
		} else {
			$two = '2';
		}

		if ( empty( $this->_actions ) ) {
			return;
		}

		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
		echo '<select name="chronoaction' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
		echo '<option value="-1">' . __( 'Bulk Actions' ) . "</option>\n";

		foreach ( $this->_actions as $name => $title ) {
			$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

			echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
		}

		echo "</select>\n";

		echo '<input type="hidden" name="shipment_nonce" value="' . wp_create_nonce( 'shipment_list_nonce' )  . '">';

		submit_button( __( 'Apply' ), 'action', '', false, array( 'id' => "doaction$two" ) );
		echo "\n";
	}

}
?>
<?php
$daily_docket_table = new Daily_Docket_List_Table();
?>
<div class="wrap">
	<h1><?php _e( 'Chronopost Daily Dockets', 'chronopost' ); ?></h1>
	<hr class="wp-header-end">
	<div id="poststuff">
		<div id="post-body-content">
			<div class="meta-box-sortables ui-sortable">
				<form method="post">
					<?php
					$daily_docket_table->prepare_items();
					$daily_docket_table->display();
					?>
				</form>
			</div>
		</div>
		<br class="clear">
	</div>
</div>
