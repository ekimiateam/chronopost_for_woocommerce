<?php

function chrono_get_timezone() {
	$timezone_string = get_option( 'timezone_string' );

	if ( ! empty( $timezone_string ) ) {
		return $timezone_string;
	}

	$offset  = get_option( 'gmt_offset' );
	$hours   = (int) $offset;
	$minutes = ( $offset - floor( $offset ) ) * 60;
	$offset  = sprintf( '%+03d:%02d', $hours, $minutes );

	return $offset;
}

function chrono_get_option( $key = '', $section = 'general', $default = false ) {
	$option = chrono_get_generic_option( 'chronopost_settings', $key, $section, $default );
	return is_string($option) ? trim($option) : $option;
}

function chrono_get_imports_option( $key = '', $section = 'general', $default = false ) {
	return chrono_get_generic_option( 'chronopost_imports', $key, $section, $default );
}

/**
 * @param        $settings
 * @param string $key
 * @param string $section
 * @param        $default
 *
 * @return string
 */
function chrono_get_generic_option( $settings, $key, $section, $default ) {
	$options = get_option( $settings, $default );
	if ( is_array( $options ) ) {
		if ( ! array_key_exists( $section, $options ) ) {
			return $default == false ? '' : $default;
		}

		if ( array_key_exists( $key, $options[ $section ] ) ) {
			return $options[ $section ][ $key ];
		}
	}

	return $default == false ? '' : $default;
}

function chrono_get_media_path() {
	return WP_CONTENT_DIR . '/uploads/chronopost/';
}

function chrono_get_media_url() {
	return WP_CONTENT_URL . '/uploads/chronopost/';
}

function chrono_get_weight_unit() {
	 return strtolower( get_option( 'woocommerce_weight_unit' ) );
}

function chrono_get_product_code_by_id( $id_method ) {
	$product_code_key         = chrono_get_option( 'enable', 'bal_option' ) == 'yes' ? 'product_code_bal' : 'product_code';
	$default_product_code_key = 'product_code';

	$chronopost_methods = get_option( 'chronopost_shipping_methods', array(), true );

	if ( array_key_exists( $id_method, $chronopost_methods ) ) {
		if ( $chronopost_methods[ $id_method ][ $product_code_key ] != false ) {
			return $chronopost_methods[ $id_method ][ $product_code_key ];
		}
	}
	return $chronopost_methods[ $id_method ][ $default_product_code_key ];
}

function chrono_get_method_settings( $id_method, $instance_id = false, $key = false ) {
    global $method_settings_cache;

    $hash = md5( serialize([$id_method, $instance_id, $key]));

    if (isset($method_settings_cache[$hash])) {
        return $method_settings_cache[$hash];
    }

	if (!is_numeric($instance_id) && (!is_admin() || is_ajax())) {
		$key = $instance_id;

		// Get current shipping zone
		$shipping_packages =  WC()->cart->get_shipping_packages();
		$shipping_zone = wc_get_shipping_zone( reset( $shipping_packages ) );

		// Fetch method instance for zone
		$instance = chrono_get_zone_method_by_id($shipping_zone->get_id(), $id_method);
		$instance_id = $instance->get_instance_id();
	}

	$method_settings = (array) get_option( 'woocommerce_' . $id_method . '_' . $instance_id . '_settings' );
	$result = ! $key ? (array) $method_settings : ( array_key_exists( $key, $method_settings ) ? $method_settings[ $key ] : '' );

    $method_settings_cache[$hash] = $result;

    return $result;
}

function chrono_get_tracking_url( $skybill_number = false, $shipping_method_id = false, $order_shipping_instance = null ) {
	if ( $skybill_number && $shipping_method_id ) {
		return str_replace( '{tracking_number}', $skybill_number, chrono_get_method_settings( $shipping_method_id, $order_shipping_instance, 'tracking_url' ) );
	}
	return false;
}

function chrono_get_shipment_datas( $order_id ) {
	$shipment_datas = get_post_meta( $order_id, '_shipment_datas', true );
	if ( is_array( $shipment_datas ) && isset( $shipment_datas[0] ) &&
		 ( array_key_exists( '_skybill_number', $shipment_datas[0] ) || array_key_exists( '_reservation_number', $shipment_datas[0] ) ) ) {
		return $shipment_datas;
	}

	return false;
}

function chrono_get_parcels_number( $order_id ) {
	return get_post_meta( $order_id, '_parcels_number', true ) ?: 1;
}

function chrono_is_shipping_methods_without_saturday( $shipping_method_id ) {
	$shippingMethodsNoSaturday = array(
		'chronorelaiseurope',
		'chronotoshopeurope',
		'chronoexpress',
		'chronoclassic',
	);
	return in_array( $shipping_method_id, $shippingMethodsNoSaturday );
}

function chrono_get_parcels_dimensions( $order_id ) {
	return json_decode( get_post_meta( $order_id, '_parcels_dimensions', true ), true ) ?: array();
}

function chrono_notice( $message, $type = 'success', $modal = false, $args = array() ) {
	$class = 'notice notice-' . $type;

	/**
   * Define the array of defaults
   */
	$defaults = array(
		'width'  => 300,
		'height' => 500,
		'title'  => __( 'Information Chronopost' ),
	);

	/**
	 * Parse incoming $args into an array and merge it with $defaults
	 */
	$args = wp_parse_args( $args, $defaults );

	if ( ! $modal ) {
		return sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	} else {
		$btnClose = '<button onclick="javascript:tb_remove()" class="button button-primary">' . __( 'I understand', 'chronopost' ) . '</button>';
		return sprintf( '<div class="%1$s" style="display: none"><div id="alertModal" data-title="' . esc_attr( $args['title'] ) . '" data-width="' . $args['width'] . '" data-height="' . $args['height'] . '"><p>%2$s</p><div style="text-align:center;">' . $btnClose . '</div></div></div>', esc_attr( $class ), esc_html( $message ) );
	}
}

function get_day_with_key( $key ) {
	$days = array(
		'sunday',
		'monday',
		'thuesday',
		'wednesday',
		'thursday',
		'friday',
		'saturday',
	);
	return array_key_exists( $key, $days ) ? $days[ $key ] : 'sunday';
}

function chrono_get_saturday_shipping_days() {
	$startday             = chrono_get_option( 'startday', 'saturday_slot', 4 );
	$endday               = chrono_get_option( 'endday', 'saturday_slot', 5 );
	$starttime            = chrono_get_option( 'starttime', 'saturday_slot', '15:00' );
	$endtime              = chrono_get_option( 'endtime', 'saturday_slot', '18:00' );
	$SaturdayShippingDays = array(
		'startday'  => get_day_with_key( $startday ),
		'endday'    => get_day_with_key( $endday ),
		'starttime' => $starttime . ':00',
		'endtime'   => $endtime . ':00',
	);
	return $SaturdayShippingDays;
}

function chrono_is_sending_day() {
	$satDays = chrono_get_saturday_shipping_days();

	$satDayStart  = date( 'N', strtotime( $satDays['startday'] ) );
	$satTimeStart = explode( ':', $satDays['starttime'] );

	$endDayStart  = date( 'N', strtotime( $satDays['endday'] ) );
	$endTimeStart = explode( ':', $satDays['endtime'] );

	$start = new DateTime( 'last sun' );
	// COMPAT < 5.36 : no chaining (returns null)
	$start->modify( '+' . $satDayStart . ' days' );
	$start->modify( '+' . $satTimeStart[0] . ' hours' );
	$start->modify( '+' . $satTimeStart[1] . ' minutes' );
	$end = new DateTime( 'last sun' );
	$end->modify( '+' . $endDayStart . ' days' );
	$end->modify( '+' . $endTimeStart[0] . ' hours' );
	$end->modify( '+' . $endTimeStart[1] . ' minutes' );

	if ( $end < $start ) {
		$end->modify( '+1 week' );
	}

	$end   = $end->getTimestamp();
	$start = $start->getTimestamp();

	$now = current_time( 'timestamp' );

	if ( $start <= $now && $now <= $end ) {
		return true;
	}
	return false;
}

function chrono_add_gmt_timestamp( $timestamp ) {
	//return $timestamp;
	// todo : verifier cette fonction
	return $timestamp - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
}

function chrono_get_post_datas( $str_post_datas = '' ) {
	$post_datas = array();
	foreach ( explode( '&', $str_post_datas ) as $chunk ) {
		$param = explode( '=', $chunk );
		if ( $param ) {
			if ( urldecode( $param[0] ) == 'chronopostprecise_creneaux_info' ) {
				$post_datas[ urldecode( $param[0] ) ] = json_decode( urldecode( $param[1] ) );
			} else {
				$post_datas[ urldecode( $param[0] ) ] = urldecode( $param[1] );
			}
		}
	}
	return $post_datas;
}

function chronopost_is_configured() {
	$contracts = chrono_get_all_contracts();



	// si il n'y a aucun contrat configuré, on affiche la notice
	if ( ! $contracts ) {
		return false;
	}
	$default_options = Chronopost_Admin_Display::get_default_values();

	foreach ( $contracts as $contract ) {
		// si l'un des contrats est un contrat avec une valeur par défaut, on laisse la notice
		if ( $contract['number'] == $default_options['contract'][1]['number'] ) {
			return false;
		}
	}

	// si les comptes sont ok, on valide la configuration
	return true;
}

function chronopost_methods_is_configured() {
	return is_array( get_option( 'chronopost_shipping_methods' ) );
}

function chrono_format_relay_address( $str ) {
	return ucwords( strtolower( $str ) );
}

/**
 * @param int $contract_number
 *
 * @return array
 */
function chrono_get_contract_infos( $contract_number ) {
	$infos = get_transient( 'contract_infos_' . $contract_number );
	if ( ! $infos ) {
		$accounts = chrono_get_option( 'accounts' );
		foreach ( $accounts as $account ) {
			if ( $account['number'] === $contract_number ) {
				$infos = $account;
				set_transient( 'contract_infos_' . $contract_number, $infos, 24 * 3600 );
			}
		}
	}
	return $infos;
}

/**
 * @param string $shipping_method_id
 *
 * @return bool|WC_Chronopost_Product
 */
function chrono_get_shipping_method_by_id( $shipping_method_id ) {
	$shipping_methods = WC()->shipping()->load_shipping_methods();

	if ( isset( $shipping_methods[ $shipping_method_id ] ) ) {
		return $shipping_methods[ $shipping_method_id ];
	}
	return false;
}

/**
 * @param int $shipping_method_id
 *
 * @return bool|WC_Chronopost_Product
 */
function chrono_get_zone_method_by_id( $zone_id, $method_id ) {
	$zone = new WC_Shipping_Zone($zone_id);
	$zone_methods = $zone->get_shipping_methods();
	foreach ($zone_methods as $shipping_method) {
		if ($shipping_method->id === $method_id) {
			return $shipping_method;
		}
	}
	return false;
}

/**
 * @param int $shipping_method_id
 *
 * @return bool|WC_Chronopost_Product
 */
function chrono_get_shipping_method_by_instance_id( $shipping_method_instance_id ) {
	$data_store = WC_Data_Store::load('shipping-zone');
	$shipping_method_instance = $data_store->get_method($shipping_method_instance_id);

	$zone = new WC_Shipping_Zone($shipping_method_instance->zone_id);
	$zone_methods = $zone->get_shipping_methods();
	if (isset($zone_methods[$shipping_method_instance_id])) {
		return $zone_methods[$shipping_method_instance_id];
	}
	return false;
}

/**
 * @param int $order_id
 *
 * @return bool|WC_Chronopost_Product
 */
function chrono_get_shipping_method_by_order( $order_id ) {
	$_order                = new WC_Order( $order_id );
	$order_shipping_method = $_order->get_shipping_methods();
	$shipping_method       = reset( $order_shipping_method );
	$shipping_method_id    = $shipping_method->get_method_id();
	return chrono_get_shipping_method_by_id( $shipping_method_id );
}

function chrono_get_shipping_method_instance_by_order( \WC_Order $order): \WC_Chronopost_Product
{
    // Find the shipping instance
    $order_shipping_instance = null;
    foreach ($order->get_shipping_methods() as $item_shipping_method) {
        $order_shipping_instance = chrono_get_shipping_method_by_instance_id($item_shipping_method->get_instance_id());
        break;
    }

    return $order_shipping_instance;
}

function chrono_filter_by_value( $array, $index, $value ) {
	if ( is_array( $array ) && count( $array ) > 0 ) {
		foreach ( array_keys( $array ) as $key ) {
			$temp[ $key ] = $array[ $key ][ $index ];

			if ( $temp[ $key ] == $value ) {
				$newarray[ $key ] = $array[ $key ];
			}
		}
	}
	return $newarray;
}

/**
 * @return mixed
 */
function chrono_get_all_contracts() {
	$accounts = chrono_get_option( 'accounts' );

	if ( ! isset( $accounts[ key( $accounts ) ]['status'] ) ) {
		$accounts = chrono_get_option( 'accounts' );
	} else {
		$accounts = chrono_filter_by_value( chrono_get_option( 'accounts' ), 'status', 'success' );
	}

	return $accounts;
}

/**
 * Can we return the package ?
 * @param $country
 *
 * @return bool
 */
function chrono_can_return_package( $country ) {
	// Load whitelist
	$whitelistFile = fopen( plugin_dir_path( __FILE__ ) . '../csv/chronoretour.csv', 'r' );
	$whitelist     = fgetcsv( $whitelistFile );
	return in_array( $country, $whitelist );
}

/**
 * Vérifie les dimensions de plusieurs paquets (tableau)
 * @param array $dimensions
 *
 * @return bool|string
 */
function chrono_check_packages_dimensions( $shipping_method_id, $dimensions ) {
	foreach ( $dimensions as $parcel_dimension ) {
		if ( $shipping_method_id === 'chronorelais' || $shipping_method_id === 'chronorelaiseurope' || $shipping_method_id === 'chronotoshopeurope' || $shipping_method_id === 'chronorelaisdom' ) {
			$max_weight      = 20; // Kg
			$max_size        = 100; // cm
			$max_global_size = 250; //cm
		} else {
			$max_weight      = 30; // Kg
			$max_size        = 150; // cm
			$max_global_size = 300; // cm
		}
		if ( $parcel_dimension['weight'] > $max_weight ) {
			return sprintf( __( 'One or several packages are above the weight limit (%s kg)', 'chronopost' ), $max_weight );
		}
		if ( $parcel_dimension['width'] > $max_size || $parcel_dimension['height'] > $max_size || $parcel_dimension['length'] > $max_size ) {
			return sprintf( __( 'One or several packages are above the size limit (%s cm)', 'chronopost' ), $max_size );
		}
		if ( $parcel_dimension['width'] + ( 2 * $parcel_dimension['height'] ) + ( 2 * $parcel_dimension['length'] ) > $max_global_size ) {
			 return sprintf( __( 'One or several packages are above the total (L+2H+2l) size limit (%s cm)', 'chronopost' ), $max_global_size );
		}
	}
	return true;
}

/**
 * Vérifie le montant de l'assurance
 * @param object $order
 *
 * @return bool|string
 */

function chrono_get_advalorem_amount( $_order ) {

    $insurance_amount = (float)get_post_meta($_order->get_id(), '_insurance_amount', true) * 100;

    $totalAdValorem = 0;
    $maxAmount = 20000;
    $adValoremAmount = (float)chrono_get_option('min_amount', 'insurance') * 100;

    foreach ($_order->get_items() as $item) {
        $totalAdValorem += $item->get_total() * 100;
    }

    $totalAdValorem = $insurance_amount > 0  ? $insurance_amount : $totalAdValorem;

    $totalAdValorem = min($totalAdValorem, $maxAmount);

    if ($totalAdValorem < $adValoremAmount) {
        $totalAdValorem = 0;
    }

    return $totalAdValorem;
}

function chrono_get_return_label( $order_id, $parent_skybill = false ) {
	$return_labels = chrono_get_return_labels( $order_id );
	if ( $parent_skybill && isset( $return_labels[ $parent_skybill ] ) ) {
		return $return_labels[ $parent_skybill ];
	}
	return false;
}

function chrono_get_return_labels( $order_id ) {
	$return_labels = get_post_meta( $order_id, 'chrono_return_labels', true );
	if ( is_array( $return_labels ) ) {
		return $return_labels;
	}
	return [];
}

function chrono_add_return_labels( $order_id = 0, $parent_skybill = false, $return_skybill = false ) {
	if ( $parent_skybill && $return_skybill ) {
		$return_labels = chrono_get_return_labels( $order_id );
		$return_labels[$parent_skybill] = $return_skybill;
		return update_post_meta( $order_id, 'chrono_return_labels', $return_labels);
	}
}

function chrono_get_saturday_shipping_post_data() {
	$enable_saturday_shipping = 'no';

	$post_datas = array_key_exists('post_data', $_POST) && $_POST['post_data'] != '' ? chrono_get_post_datas($_POST['post_data']) : $_POST;

	if (isset($post_datas['saturday_shipping_field_enable'])) {
		$enable_saturday_shipping = $post_datas['saturday_shipping_field_enable'];
	}

	return $enable_saturday_shipping;
}


add_action( 'woocommerce_cart_calculate_fees', 'saturday_add_surcharge' );

function saturday_add_surcharge($cart) {
	$enable_saturday_shipping = chrono_get_saturday_shipping_post_data();

	if ( is_admin() && ! defined( 'DOING_AJAX' ) )
		return;

	if ($enable_saturday_shipping === 'yes') {
		$shipping_methods = WC()->session->get('chosen_shipping_methods')[0];
		$fee = (float) chrono_get_method_settings($shipping_methods, 'deliver_on_saturday_amount');
		$cart->add_fee( __( 'Saturday shipping fee', 'chronopost' ), $fee, true, 'standard' );
	}
}

function chrono_is_toshop($shipping_method): bool
{
    return $shipping_method->get_method_id() === 'chronotoshopeurope' || $shipping_method->get_method_id() === 'chronotoshopdirect';
}

function chrono_is_pickup($shipping_method): bool
{
    return in_array($shipping_method,
        array('chronorelais', 'chronorelaiseurope', 'chronorelaisdom', 'chronotoshopdirect', 'chronotoshopeurope'));
}
