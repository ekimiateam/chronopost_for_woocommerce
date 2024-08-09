<?php
/**
 * Chronopost Webservice methods
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

class Chronopost_Webservice
{
	const CHRONOPOST_REVERSE_R = '4R'; // for Chronopost Reverse 9
	const CHRONOPOST_REVERSE_S = '4S'; // for Chronopost Reverse 10
	const CHRONOPOST_REVERSE_T = '4T'; // for Chronopost Reverse 13
	const CHRONOPOST_REVERSE_U = '4U'; // for Chronopost Reverse 18
	const CHRONOPOST_REVERSE_DEFAULT = '01'; // for Chronopost Reverse 18
	const CHRONOPOST_REVERSE_RELAIS_EUROPE = '3T'; // for Chronopost Reverse RelaisEurope
	const CHRONOPOST_REVERSE_TOSHOP = '5Y'; // for Chronopost Reverse TOSHOP
	const CHRONOPOST_REVERSE_TOSHOP_EUROPE = '6C'; // for Chronopost Reverse TOSHOP EUROPE

	const CHRONOPOST_REVERSE_R_SERVICE = '885'; // for Chronopost Reverse 9
	const CHRONOPOST_REVERSE_S_SERVICE = '180'; // for Chronopost Reverse 10
	const CHRONOPOST_REVERSE_T_SERVICE = '898'; // for Chronopost Reverse 13
	const CHRONOPOST_REVERSE_U_SERVICE = '835'; // for Chronopost Reverse 18
	const CHRONOPOST_REVERSE_DEFAULT_SERVICE = '226';  // for Chronopost Reverse 18
	const CHRONOPOST_REVERSE_RELAY_EUROPE_SERVICE = '332'; // for Chronopost Reverse RelaisEurope
	const CHRONOPOST_REVERSE_TOSHOP_SERVICE = '1'; // for Chronopost Reverse Toshop
	const CHRONOPOST_REVERSE_TOSHOP_EUROPE_SERVICE = '332'; // for Chronopost Reverse Toshop Europe

	const CHRONOPOST_EXPRESS = '17';

	protected $methodsAllowed = [];
    protected $methodsByContract = [];
	public $admin_notice = '';

	public static function getReturnServiceLabel($code)
	{
		switch ($code) {
			case static::CHRONOPOST_REVERSE_R:
				return 'CHRONO 9';
			case static::CHRONOPOST_REVERSE_S:
				return 'CHRONO 10';
			case static::CHRONOPOST_REVERSE_T:
				return 'CHRONO 13';
			case static::CHRONOPOST_REVERSE_U:
				return 'CHRONO 18';
			case static::CHRONOPOST_REVERSE_RELAIS_EUROPE:
				return 'CHRONO RELAIS EUROPE';
			case static::CHRONOPOST_REVERSE_TOSHOP:
				return 'CHRONO TO SHOP';
			case static::CHRONOPOST_REVERSE_TOSHOP_EUROPE:
				return 'CHRONO TO SHOP EUROPE';
			case static::CHRONOPOST_REVERSE_DEFAULT:
				return 'DEFAULT';
			default :
				return $code;
		}
	}

	/**
	 * Get the human-readable name of return product code
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public static function getReturnProductName(string $code): string
	{
		$returnProductCodeLabels = [
			static::CHRONOPOST_REVERSE_R => 'C9 REVERSE',
			static::CHRONOPOST_REVERSE_S => 'C10 REVERSE',
			static::CHRONOPOST_REVERSE_T => 'C13 REVERSE',
			static::CHRONOPOST_REVERSE_U => 'C18 REVERSE',
			static::CHRONOPOST_REVERSE_TOSHOP => '2SHOP REVERSE',
			static::CHRONOPOST_REVERSE_TOSHOP_EUROPE => '2SHOP REVERSE EUROPE',
		];

		return $returnProductCodeLabels[$code] ?? 'C13 DEFAULT';
	}


	public function getReturnMethods(): array
	{
		$returnMethods = [
			Chronopost_Webservice::CHRONOPOST_REVERSE_DEFAULT,
			Chronopost_Webservice::CHRONOPOST_REVERSE_R,
			Chronopost_Webservice::CHRONOPOST_REVERSE_S,
			Chronopost_Webservice::CHRONOPOST_REVERSE_T,
			Chronopost_Webservice::CHRONOPOST_REVERSE_U,
			Chronopost_Webservice::CHRONOPOST_REVERSE_RELAIS_EUROPE,
			Chronopost_Webservice::CHRONOPOST_REVERSE_TOSHOP,
			Chronopost_Webservice::CHRONOPOST_REVERSE_TOSHOP_EUROPE,
		];

		$contracts = chrono_get_option( 'accounts' );
		$order = new WC_Order($_REQUEST['post']);
		$address = [
			'country' => $order->get_shipping_country(),
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
		];

		$methodsAllowed = [];

		foreach ($contracts as $contract) {
			// chrono_get_contract_infos($contract['number']);
			$methodsAllowed[$contract['number']] = array_intersect($this->getMethods($address, $contract), $returnMethods);
		}

		$methodsLabel = [];

		foreach ($methodsAllowed as $contractNumber => $codes) {
			if (!isset($methodsLabel[$contractNumber])) {
				$methodsLabel[$contractNumber] = [];
			}
			foreach ($codes as $code) {
				$methodsLabel[$contractNumber][$code] = self::getReturnProductName($code);
			}
		}

		return $methodsLabel;
	}

    /**
     * @param $address
     * @param $contract
     *
     * @return array
     */
	public function getMethods($address, $contract = null, $addressFrom = null)
	{
        $addressHash = md5(json_encode($address));

		// Make sure contract information
		if (empty($contract)) {
			$contract = chrono_get_option('accounts');
			$contract = $contract[0];
		}

        if ($addressFrom === null) {

        }

		if ($contract !== null) {
			$accountNumber = $contract['number'];
			$accountPassword = $contract['password'];

            if (!isset($this->methodsByContract[$accountNumber])) {
                $this->methodsByContract[$accountNumber] = [];
            }

			if (!isset($this->methodsByContract[$accountNumber][$addressHash])) {
				$this->methodsByContract[$accountNumber][$addressHash] = [];

				try {
					$params = [
						'accountNumber'  => $accountNumber,
						'password'       => $accountPassword,
						'depCountryCode' => $address['country'],
						'depZipCode'     => $address['postcode'],
						'arrCountryCode' => chrono_get_option('country', 'return'),
						'arrZipCode'     => chrono_get_option('zipcode', 'return'),
						'arrCity'        => chrono_get_option('city', 'return'),
						'type'           => 'M',
						'weight'		 => 1,
					];

					$webservbt = $this->calculateProducts($params);
					if ($webservbt->return->errorCode == 0 && isset($webservbt->return->productList)) {
						if (is_array($webservbt->return->productList)) {
							foreach ($webservbt->return->productList as $product) {
								$this->methodsByContract[$accountNumber][$addressHash][] = $product->productCode;
							}
						} else { /* cas où il y a un seul résultat */
							$product = $webservbt->return->productList;
							$this->methodsByContract[$accountNumber][$addressHash][] = $product->productCode;
						}
					}
				} catch (Exception $e) {
					return [];
				}
			}

			return $this->methodsByContract[$accountNumber][$addressHash];
		}

		return [];
	}

	public function print_admin_notice()
	{
		echo chrono_notice($this->admin_notice, 'error');
	}

	public function print_admin_success()
	{
		echo chrono_notice($this->admin_notice, 'success');
	}

	/**
	 * @param WC_Order $_order
	 * @param bool     $ajax
	 *
	 * @return array|bool|mixed
	 * @throws Exception
	 */
	public function saveAndCreateShipmentLabel($_order, $ajax = false)
	{
		//Si l'expedition est réalisé par Mondial Relay, on créé le tracking automatiquement.
		$order_shipping_method = $_order->get_shipping_methods();
		$shipping_method = reset($order_shipping_method);
		$shipping_method_id = $shipping_method->get_method_id();
		$shipping_method_instance = chrono_get_shipping_method_by_instance_id($shipping_method->get_instance_id());
		$shippingMethodAllow = array_keys(get_option('chronopost_shipping_methods'));
		$shipment_datas = false;

		if (in_array($shipping_method_id, $shippingMethodAllow)) {
			$ref = array();

			// parcels
			$parcels_number = chrono_get_parcels_number($_order->get_id());

			// Dimensions
			$parcels_dimensions = chrono_get_parcels_dimensions($_order->get_id());

			if ($parcels_dimensions && is_array($parcels_dimensions)) {
				// Check if within boundaries
				$check = chrono_check_packages_dimensions($shipping_method_id, $parcels_dimensions);
				if ($check !== true) {
					$this->admin_notice .= $check;
					add_action('admin_notices', array($this, 'print_admin_notice'));

					return false;
				}
			} else {
				$parcels_dimensions = array();
				for ($i = 1; $i <= $parcels_number; $i++) {
					$parcels_dimensions[$i] = array(
						'weight' => Chronopost_Package::getTotalWeight($_order->get_items()),
						'length' => 1,
						'height' => 1,
						'width'  => 1,
					);
				}
			}

			//header parameters
			$contract = $shipping_method_instance->getContractInfos($_order);

            if (!$contract) {
                $this->admin_notice = __('Configuration error:', 'chronopost') . ' ' . __("The contract can't be found for this order. You can check the carrier configuration to fix this issue.", 'chronopost');
                add_action('admin_notices', array($this, 'print_admin_notice'));
                return false;
            }

			$header = array(
				'idEmit'        => 'WOO',
				'accountNumber' => $contract['number'],
				'subAccount'    => $contract['subaccount'],
			);

			//shipper parameters
			$shipperMobilePhone = $this->checkMobileNumber(chrono_get_option('mobile', 'shipper'));
			$shipper = array(
				'shipperAdress1'     => chrono_get_option('address', 'shipper'),
				'shipperAdress2'     => chrono_get_option('address2', 'shipper'),
				'shipperCity'        => chrono_get_option('city', 'shipper'),
				'shipperCivility'    => chrono_get_option('civility', 'shipper'),
				'shipperContactName' => chrono_get_option('contactname', 'shipper'),
				'shipperCountry'     => chrono_get_option('country', 'shipper'),
				'shipperEmail'       => chrono_get_option('email', 'shipper'),
				'shipperMobilePhone' => $shipperMobilePhone,
				'shipperName'        => chrono_get_option('name', 'shipper'),
				'shipperName2'       => chrono_get_option('name2', 'shipper'),
				'shipperPhone'       => chrono_get_option('phone', 'shipper'),
				'shipperPreAlert'    => '',
				'shipperZipCode'     => chrono_get_option('zipcode', 'shipper'),
			);

			//customer parameters
			$customerMobilePhone = $this->checkMobileNumber(chrono_get_option('mobile', 'customer'));
			$customer = array(
				'customerAdress1'     => chrono_get_option('address', 'customer'),
				'customerAdress2'     => chrono_get_option('address2', 'customer'),
				'customerCity'        => chrono_get_option('city', 'customer'),
				'customerCivility'    => chrono_get_option('civility', 'customer'),
				'customerContactName' => chrono_get_option('contactname', 'customer'),
				'customerCountry'     => chrono_get_option('country', 'customer'),
				'customerEmail'       => chrono_get_option('email', 'customer'),
				'customerMobilePhone' => $customerMobilePhone,
				'customerName'        => chrono_get_option('name', 'customer'),
				'customerName2'       => chrono_get_option('name2', 'customer'),
				'customerPhone'       => chrono_get_option('phone', 'customer'),
				'customerPreAlert'    => '',
				'customerZipCode'     => chrono_get_option('zipcode', 'customer'),
			);

			$customer_obj = new WC_Customer($_order->get_customer_id());
			$customer_email = $_order->get_billing_email() ? $_order->get_billing_email() : $customer_obj->get_email();
			$recipientMobilePhone = $this->checkMobileNumber($_order->get_billing_phone()); //no shipping mobile so we use the billing phone
			$recipientName = $this->getFilledValue($_order->get_shipping_company()); //RelayPoint Name if chronorelais or Companyname if chronopost and
			$recipientName2 = $this->getFilledValue($_order->get_shipping_first_name() . ' ' . $_order->get_shipping_last_name());

			//remove any alphabets in phone number
			// billing phone (there's nos shipping phone in woocommerce!)
			$recipientPhone = trim(preg_replace('/[^0-9\.\-]/', ' ', $_order->get_billing_phone()));

			$recipient = array(
				'recipientAdress1'     => substr($this->getFilledValue($_order->get_shipping_address_1()), 0, 38),
				'recipientAdress2'     => substr($this->getFilledValue($_order->get_shipping_address_2()), 0, 38),
				'recipientCity'        => $this->getFilledValue($_order->get_shipping_city()),
				'recipientContactName' => $recipientName2,
				'recipientCountry'     => $this->getFilledValue($_order->get_shipping_country()),
				'recipientEmail'       => $customer_email,
				'recipientMobilePhone' => $recipientMobilePhone,
				'recipientName'        => $recipientName,
				'recipientName2'       => $recipientName2,
				'recipientPhone'       => $recipientPhone,
				'recipientPreAlert'    => '',
				'recipientZipCode'     => $this->getFilledValue($_order->get_shipping_postcode()),
			);

			//ref parameters
			$recipientRef = false;
			if ($pickup_relay_datas = get_post_meta($_order->get_id(), '_shipping_method_chronorelais', true)) {
				$recipientRef = $pickup_relay_datas['id'];
			}

			if (!$recipientRef) {
				$recipientRef = $_order->get_customer_id();
			}

			$shipperRef = $_order->get_id();

			for ($i = 1; $i <= $parcels_number; $i++) {
				$ref[] = array(
					'recipientRef' => $recipientRef,
					'shipperRef'   => $shipperRef,
				);
			}

			//skybill parameters
			// Livraison Samedi (Delivery Saturday) field
			$SaturdayShipping = 0; //default value for the saturday shipping
			$sat_shipMethodAllow = array_diff($shippingMethodAllow,
				array('chronorelaiseurope', 'chronotoshopeurope', 'chronoexpress', 'chronoclassic', 'chronosameday'));

			if ($shipping_method_id === 'chronosameday') {
				$SaturdayShipping = '973';
			}

			if (in_array($shipping_method_id, $sat_shipMethodAllow)) {
				$pm_saturday_shipping = get_post_meta($_order->get_id(), '_ship_on_saturday', true);
				$_force_deliver_on_saturday = $pm_saturday_shipping == 'yes' ? true : false;
				$_deliver_on_saturday = chrono_get_method_settings($shipping_method_id, 'deliver_on_saturday') == 'yes' ? 1 : 0;
				$is_sending_day = chrono_is_sending_day();

				if ($pm_saturday_shipping === 'no' && $shipping_method_id !== 'chronosameday') {
					$SaturdayShipping = 0;
				}
				elseif ($shipping_method_id === 'chronotoshopdirect') {
					$SaturdayShipping = 6;
				}
				else {
					if (
						$_force_deliver_on_saturday ||
						($_deliver_on_saturday && $is_sending_day)
					) {
						// Code différent si Chrono Relai Dom
						if ($shipping_method_id === 'chronorelaisdom') {
							$SaturdayShipping = 369;
						} else {
							if ($shipping_method_id == 'chronosameday') {
								$SaturdayShipping = '974';
							} else {
								$SaturdayShipping = 6;
							}
						}
					} elseif (!($_deliver_on_saturday && $is_sending_day) && $shipping_method_id !== 'chronosameday') {
						$SaturdayShipping = 1;
						if ($shipping_method_id === 'chronorelaisdom') {
							$SaturdayShipping = 368;
						}
					}
				}
			}

			$weight = Chronopost_Package::getTotalWeight($_order->get_items());

			// si chronorelaiseurope : service : 337 si poids < 3kg ou 338 si > 3kg
			if ($shipping_method_id === 'chronorelaiseurope' || $shipping_method_id === 'chronotoshopeurope') {
				$weight <= 3 ? $SaturdayShipping = '337' : $SaturdayShipping = '338';
			}

			$adValoremEnabled = chrono_get_option('enable', 'insurance') === 'yes' ? true : false;
			$order_insurance_enable = get_post_meta($_order->get_id(), '_insurance_enable', true);
			if ($order_insurance_enable != '') {
				$adValoremEnabled = $order_insurance_enable === 'no' ? false : true;
			}

			$totalAdValorem = 0;
			if ($order_insurance_enable === 'yes' || $adValoremEnabled) {
				$totalAdValorem = chrono_get_advalorem_amount($_order);
			}

			if ($weight > 30) {
				$weight = 0; // On met le poids à 0 car les colis sont pesé sur place
			}

			if (!$parcels_dimensions) {
				$parcel_weight = $weight;
				$parcel_height = $parcel_length = $parcel_width = 1;
				$parcels_dimensions = array(
					1 => array(
						'weight' => $parcel_weight,
						'height' => $parcel_height,
						'length' => $parcel_length,
						'width'  => $parcel_width,
					),
				);
			}

			$skybill = array();
			for ($i = 1; $i <= $parcels_number; $i++) {
				$parcel_weight = $weight;
				$parcel_height = $parcel_length = $parcel_width = 1;
				if ($parcels_dimensions) {
					$parcel_weight = $parcels_dimensions[$i]['weight'];
					$parcel_height = $parcels_dimensions[$i]['height'];
					$parcel_length = $parcels_dimensions[$i]['length'];
					$parcel_width = $parcels_dimensions[$i]['width'];
				}
				$newSkybill = array(
					'codCurrency'     => 'EUR',
					'codValue'        => '',
					'content1'        => '',
					'content2'        => '',
					'content3'        => '',
					'content4'        => '',
					'content5'        => '',
					'customsCurrency' => 'EUR',
					'customsValue'    => '',
					'evtCode'         => 'DC',
					'objectType'      => 'MAR',
					'productCode'     => chrono_get_product_code_by_id($shipping_method_id),
					'service'         => $SaturdayShipping,
					'shipDate'        => date('c'),
					'shipHour'        => date('H'),
					'skybillRank'     => $i,
					'bulkNumber'      => $parcels_number,
					'weight'          => $parcel_weight,
					'weightUnit'      => 'KGM',
					'height'          => $parcel_height,
					'length'          => $parcel_length,
					'width'           => $parcel_width,
				);
				if ($adValoremEnabled) {
					$newSkybill['insuredCurrency'] = 'EUR';
					$newSkybill['insuredValue'] = (int) $totalAdValorem;
				}
				array_push($skybill, $newSkybill);
			}

			$skybillParams = array(
				'mode' => chrono_get_option('mode', 'skybill'),
			);

			$expeditionArray = array(
				'headerValue'        => $header,
				'shipperValue'       => $shipper,
				'customerValue'      => $customer,
				'recipientValue'     => $recipient,
				'refValue'           => $ref,
				'skybillValue'       => $skybill,
				'skybillParamsValue' => $skybillParams,
				'password'           => $contract['password'],
				'numberOfParcel'     => $parcels_number,
			);

			// si chronopostprecise : ajout parametres supplementaires
			if ($shipping_method_id === 'chronoprecise') {
				$chronopostprecise_creneaux_info = get_post_meta($_order->get_id(), '_shipping_method_chronoprecise');
				if (is_array($chronopostprecise_creneaux_info)) {
					$chronopostprecise_creneaux_info = array_shift($chronopostprecise_creneaux_info);
				}

				$_dateRdvStart = new DateTime($chronopostprecise_creneaux_info['deliveryDate']);
				$_dateRdvStart->setTime($chronopostprecise_creneaux_info['startHour'],
					$chronopostprecise_creneaux_info['startMinutes']);

				$_dateRdvEnd = new DateTime($chronopostprecise_creneaux_info['deliveryDate']);
				$_dateRdvEnd->setTime($chronopostprecise_creneaux_info['endHour'],
					$chronopostprecise_creneaux_info['endMinutes']);

				$scheduledValue = array(
					'appointmentValue' => array(
						'timeSlotStartDate'   => $_dateRdvStart->format('Y-m-d') . 'T' . $_dateRdvStart->format('H:i:s'),
						'timeSlotEndDate'     => $_dateRdvEnd->format('Y-m-d') . 'T' . $_dateRdvEnd->format('H:i:s'),
						'timeSlotTariffLevel' => $chronopostprecise_creneaux_info['tariffLevel'],
					),
				);

				$expeditionArray['scheduledValue'] = $scheduledValue;

				foreach ($expeditionArray['skybillValue'] as &$skybillValue) {
					$skybillValue['productCode'] = $chronopostprecise_creneaux_info['productCode'];
					$skybillValue['service'] = $chronopostprecise_creneaux_info['serviceCode'];
					if (isset($chronopostprecise_creneaux_info['asCode'])) {
						$skybillValue['as'] = $chronopostprecise_creneaux_info['asCode'];
					}
				}
			}

			try {
                $_shippingServiceUrl = 'https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl';
                $client = new SoapClient($_shippingServiceUrl, array('trace' => true));
                $expedition = $client->shippingMultiParcelWithReservationV3($expeditionArray);

				if (!$expedition->return->errorCode && $expedition->return->reservationNumber) {
					if (isset($expedition->return->resultParcelValue->skybillNumber)) {
						$expedition->return->resultParcelValue = array($expedition->return->resultParcelValue);
					}

					// Save chronopost shipment data in post metas
					$shipment_datas = WC_Chronopost_Order::add_tracking_numbers($_order,
						$expedition->return->resultParcelValue, $expedition->return->reservationNumber);
					// Save dimensions
					$shipment_datas = WC_Chronopost_Order::add_parcels_dimensions($shipment_datas, $parcels_dimensions);
					// Save skybill params
					$shipment_datas = WC_Chronopost_Order::add_parcels_skybill_params($shipment_datas, $expeditionArray);
					update_post_meta($_order->get_id(), '_shipment_datas', $shipment_datas);
				} else {

					switch ($expedition->return->errorCode) {
						case 33:
							$shipment_datas = array('error' => 33);
							break;
						default:
							$shipment_datas = array('error' => -1);
							$this->admin_notice .= ' ' . __('Webservice error:',
									'chronopost') . ' ' . $expedition->return->errorMessage;
							break;
					}
					add_action('admin_notices', array($this, 'print_admin_notice'));
				}
			} catch (SoapFault $fault) {
				$this->admin_notice = __('An error occured during the label creation. Please check the customer datas or your Chronopost settings.',
					'chronopost');
				$this->admin_notice .= ' ' . __('System error:', 'chronopost') . ' ' . $fault->getMessage();
				add_action('admin_notices', array($this, 'print_admin_notice'));
			} catch (Exception $fault) {
				$this->admin_notice = __('An error occured during the label creation. Please check the customer datas or your Chronopost settings.',
					'chronopost');
				$this->admin_notice .= ' ' . __('System error:', 'chronopost') . ' ' . $fault->getMessage();
				add_action('admin_notices', array($this, 'print_admin_notice'));
			}
		}

		// Cleanup
		delete_post_meta($_order->get_id(), '_parcels_number');
		delete_post_meta($_order->get_id(), '_parcels_dimensions');

		return $shipment_datas;
	}

	/**
	 * @param $reservation
	 *
	 * @return false
	 * @throws SoapFault
	 */
	public function getPDFSkybillByReservation($reservation)
	{
		$client = new SoapClient(
			'https://www.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl',
			array('trace' => true)
		);
		try {
			$result = $client->getReservedSkybill(
				array(
					'reservationNumber' => $reservation,
				)
			);

			if ($result->return->errorCode === 0) {
				return $result->return;
			}
		} catch (Exception $e) {
			return false;
		}

		return false;
	}

	public function getPointsRelaisByCp($cp)
	{
		try {
			$client = new SoapClient(
				'http://wsshipping.chronopost.fr/soap.point.relais/services/ServiceRechercheBt?wsdl', array(
					'trace'              => 0,
					'connection_timeout' => 10,
				)
			);

			return $client->__call('rechercheBtParCodeproduitEtCodepostalEtDate', array(0, $cp, 0));
		} catch (Exception $e) {
			return $this->getPointsRelaisByPudo($cp);
		}
	}

	/* get point relais by address */
	public function getPointRelaisByAddress($shippingMethodCode = 'chronorelais')
	{

		if (!$shippingMethodCode) {
			return false;
		}


		try {
			$pointRelaisProductCode = chrono_get_product_code_by_id($shippingMethodCode);

			// 4P

			$pointRelaisWs = 'https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';

			$pointRelaisWsMethod = 'recherchePointChronopost';
			$addAddressToWs = 1;
			if ($shippingMethodCode === 'chronorelaiseurope' || $shippingMethodCode === 'chronotoshopeurope' || $shippingMethodCode === 'chronorelaisdom') {
				$pointRelaisWsMethod = 'recherchePointChronopostInter';
				$addAddressToWs = 0;
			}

			$maxPointChronopost = chrono_get_method_settings($shippingMethodCode, 'max_pickup_relay_number');
			$maxDistanceSearch = chrono_get_method_settings($shippingMethodCode, 'max_distance_search');

			$pointRelaisService = 'T';

			$client = new SoapClient(
				$pointRelaisWs, array(
					'trace'              => 0,
					'connection_timeout' => 10,
				)
			);

			/* si dom => on ne met pas le code ISO mais un code spécifique, sinon le relai dom ne fonctionne pas */
			$countryDomCode = $this->getCountryDomCode();

			$countryId = WC()->customer->get_shipping_country();
			if (isset($countryDomCode[$countryId])) {
				$countryId = $countryDomCode[$countryId];
			}

			$chronopost_product = chrono_get_shipping_method_by_id($shippingMethodCode);
			$contract = $chronopost_product->getContractInfos();
			$dropoff_type = chrono_get_option('type', 'dropoff');
			if (!$dropoff_type) {
				$dropoff_type = 'P';
			}

            if ($shippingMethodCode === 'chronotoshopdirect' || $shippingMethodCode === 'chronotoshopeurope') {
                $dropoff_type = 'C';
            }

			$params = array(
				'accountNumber'      => $contract['number'],
				'password'           => $contract['password'],
				'zipCode'            => $this->getFilledValue(WC()->customer->get_shipping_postcode()),
				'city'               => $this->getFilledValue(WC()->customer->get_shipping_city()),
				'countryCode'        => $this->getFilledValue($countryId),
				'type'               => $dropoff_type,
				'productCode'        => $pointRelaisProductCode,
				'service'            => $pointRelaisService,
				'weight'             => 2000,
				'shippingDate'       => date('d/m/Y'),
				'maxPointChronopost' => $maxPointChronopost,
				'maxDistanceSearch'  => $maxDistanceSearch,
				'holidayTolerant'    => 1,
			);

			if ($addAddressToWs) {
				$params['address'] = $this->getFilledValue(WC()->customer->get_shipping_address());
			}

			$webservbt = $client->$pointRelaisWsMethod($params);

			/* format $webservbt pour avoir le meme format que lors de l'appel du WS par code postal */
			if ($webservbt->return->errorCode == 0 && isset($webservbt->return->listePointRelais)) {
				/*
				 * Format entrée
				 *
				 * accesPersonneMobiliteReduite
					actif
					adresse1
					adresse2
					adresse3
					codePays
					codePostal
					coordGeolocalisationLatitude
					coordGeolocalisationLongitude
					distanceEnMetre
					identifiant
					indiceDeLocalisation
					listeHoraireOuverture
					localite
					nom
					poidsMaxi
					typeDePoint
					urlGoogleMaps
				 *
				 * Format sortie
				 * adresse1
					adresse2
					adresse3
					codePostal
					dateArriveColis
					horairesOuvertureDimanche ("10:00-12:30 14:30-19:00")
					horairesOuvertureJeudi
					horairesOuvertureLundi
					horairesOuvertureMardi
					horairesOuvertureMercredi
					horairesOuvertureSamedi
					horairesOuvertureVendredi
					identifiantChronopostPointA2PAS
					localite
					nomEnseigne
				 *
				 *
				 *
				 * 2013-02-19T10:42:40.196Z
				 *
				 */
				$listePr = array();
				if (isset($webservbt->return->listePointRelais)) {
					$listePr = $webservbt->return->listePointRelais;
					if (count($webservbt->return->listePointRelais) == 1) {
						$listePr = array($listePr);
					}
				}
				$return = array();
				foreach ($listePr as $pr) {
					$newPr = (object)array();
					$newPr->adresse1 = $pr->adresse1;
					$newPr->adresse2 = property_exists($pr, 'adresse2') ? $pr->adresse2 : '';
					$newPr->adresse3 = property_exists($pr, 'adresse3') ? $pr->adresse3 : '';
					$newPr->codePostal = $pr->codePostal;
					$newPr->identifiantChronopostPointA2PAS = $pr->identifiant;
					$newPr->latitude = $pr->coordGeolocalisationLatitude;
					$newPr->longitude = $pr->coordGeolocalisationLongitude;
					$newPr->localite = $pr->localite;
					$newPr->nomEnseigne = $pr->nom;
					$time = new DateTime;
					$newPr->dateArriveColis = $time->format(DateTime::ATOM);
					$newPr->horairesOuvertureLundi = $newPr->horairesOuvertureMardi = $newPr->horairesOuvertureMercredi = $newPr->horairesOuvertureJeudi = $newPr->horairesOuvertureVendredi = $newPr->horairesOuvertureSamedi = $newPr->horairesOuvertureDimanche = '';
					foreach ($pr->listeHoraireOuverture as $horaire) {
						switch ($horaire->jour) {
							case '1':
								$newPr->horairesOuvertureLundi = $horaire->horairesAsString;
								break;
							case '2':
								$newPr->horairesOuvertureMardi = $horaire->horairesAsString;
								break;
							case '3':
								$newPr->horairesOuvertureMercredi = $horaire->horairesAsString;
								break;
							case '4':
								$newPr->horairesOuvertureJeudi = $horaire->horairesAsString;
								break;
							case '5':
								$newPr->horairesOuvertureVendredi = $horaire->horairesAsString;
								break;
							case '6':
								$newPr->horairesOuvertureSamedi = $horaire->horairesAsString;
								break;
							case '7':
								if (!empty($horaire->horairesAsString)) {
									$newPr->horairesOuvertureDimanche = $horaire->horairesAsString;
								}
								break;
							default:
								break;
						}
					}
					if (empty($newPr->horairesOuvertureLundi)) {
						$newPr->horairesOuvertureLundi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureMardi)) {
						$newPr->horairesOuvertureMardi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureMercredi)) {
						$newPr->horairesOuvertureMercredi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureJeudi)) {
						$newPr->horairesOuvertureJeudi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureVendredi)) {
						$newPr->horairesOuvertureVendredi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureSamedi)) {
						$newPr->horairesOuvertureSamedi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureDimanche)) {
						$newPr->horairesOuvertureDimanche = '00:00-00:00 00:00-00:00';
					}

					$return[] = $newPr;
				}

				return $return;
			}
		} catch (Exception $e) {
			$address = array(
				'address'  => $this->getFilledValue(WC()->customer->get_shipping_address()),
				'postcode' => $this->getFilledValue(WC()->customer->get_shipping_postcode()),
				'city'     => $this->getFilledValue(WC()->customer->get_shipping_city()),
				'country'  => $this->getFilledValue($countryId),
			);

			return $this->getPointsRelaisByPudo($address);
		}
	}

	protected function getCountryDomCode()
	{
		return array(
			'RE' => 'REU',
			'MQ' => 'MTQ',
			'GP' => 'GLP',
			'MX' => 'MYT',
			'GF' => 'GUF',
		);
	}

	/**
	 * Mapping from ISO code to Chrono code
	 *
	 * @return array
	 */
	public function getChronoCode()
	{
		return array(
			'YT' => 'YO',
			'MC' => 'FR',
			'MF' => 'PM',
		);
	}

	public function getDetailRelaisPoint($btcode)
	{
		try {
			$shipping_method = new WC_Chronorelais();
			$contract = $shipping_method->getContractInfos();
			$params = array(
				'accountNumber' => $contract['number'],
				'password'      => $contract['password'],
				'identifiant'   => $btcode,
			);

			$client = new SoapClient('https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl');
			$webservbt = $client->rechercheDetailPointChronopost($params);

			if ($webservbt->return->errorCode == 0) {
				return $webservbt->return->listePointRelais;
			} else {
				return $this->getDetailRelaisPointByPudo($btcode);
			}
		} catch (Exception $e) {
			return $this->getDetailRelaisPointByPudo($btcode);
		}
	}


	/*
	 *
	 * WS de secours
	 */

	public function getDetailRelaisPointByPudo($btcode)
	{
		$params = array(
			'carrier' => 'CHR',
			'key'     => '75f6fe195dc88ceecbc0f8a2f70a8f3a',
			'pudo_id' => $btcode,
		);

		try {
			$client = new SoapClient(
				'http://mypudo.pickup-services.com/mypudo/mypudo.asmx?wsdl', array(
					'trace'              => 0,
					'connection_timeout' => 10,
				)
			);
			$webservbt = $client->GetPudoDetails($params);
			$webservbt = json_decode(json_encode((object)simplexml_load_string($webservbt->GetPudoDetailsResult->any)),
				1);
			if (!isset($webservbt['ERROR'])) {
				$pr = $webservbt['PUDO_ITEMS']['PUDO_ITEM'];
				if ($pr && $pr['@attributes']['active'] == 'true') {
					$newPr = (object)array();
					$newPr->adresse1 = $pr['ADDRESS1'];
					$newPr->adresse2 = is_array($pr['ADDRESS2']) ? implode(' ', $pr['ADDRESS2']) : $pr['ADDRESS2'];
					$newPr->adresse3 = is_array($pr['ADDRESS3']) ? implode(' ', $pr['ADDRESS3']) : $pr['ADDRESS3'];
					$newPr->codePostal = $pr['ZIPCODE'];
					$newPr->identifiantChronopostPointA2PAS = $pr['PUDO_ID'];
					$newPr->localite = $pr['CITY'];
					$newPr->latitude = str_replace(",", ".", $pr['LATITUDE']);
					$newPr->longitude = str_replace(",", ".", $pr['LONGITUDE']);
					$newPr->nomEnseigne = $pr['NAME'];
					$time = new DateTime;
					$newPr->dateArriveColis = $time->format(DateTime::ATOM);
					$newPr->horairesOuvertureLundi = $newPr->horairesOuvertureMardi = $newPr->horairesOuvertureMercredi = $newPr->horairesOuvertureJeudi = $newPr->horairesOuvertureVendredi = $newPr->horairesOuvertureSamedi = $newPr->horairesOuvertureDimanche = '';

					if (isset($pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'])) {
						$listeHoraires = $pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'];
						foreach ($listeHoraires as $horaire) {
							switch ($horaire['DAY_ID']) {
								case '1':
									if (!empty($newPr->horairesOuvertureLundi)) {
										$newPr->horairesOuvertureLundi .= ' ';
									}
									$newPr->horairesOuvertureLundi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								case '2':
									if (!empty($newPr->horairesOuvertureMardi)) {
										$newPr->horairesOuvertureMardi .= ' ';
									}
									$newPr->horairesOuvertureMardi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								case '3':
									if (!empty($newPr->horairesOuvertureMercredi)) {
										$newPr->horairesOuvertureMercredi .= ' ';
									}
									$newPr->horairesOuvertureMercredi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								case '4':
									if (!empty($newPr->horairesOuvertureJeudi)) {
										$newPr->horairesOuvertureJeudi .= ' ';
									}
									$newPr->horairesOuvertureJeudi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								case '5':
									if (!empty($newPr->horairesOuvertureVendredi)) {
										$newPr->horairesOuvertureVendredi .= ' ';
									}
									$newPr->horairesOuvertureVendredi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								case '6':
									if (!empty($newPr->horairesOuvertureSamedi)) {
										$newPr->horairesOuvertureSamedi .= ' ';
									}
									$newPr->horairesOuvertureSamedi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								case '7':
									if (!empty($newPr->horairesOuvertureDimanche)) {
										$newPr->horairesOuvertureDimanche .= ' ';
									}
									$newPr->horairesOuvertureDimanche .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
									break;
								default:
									break;
							}
						}
					}
					if (empty($newPr->horairesOuvertureLundi)) {
						$newPr->horairesOuvertureLundi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureMardi)) {
						$newPr->horairesOuvertureMardi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureMercredi)) {
						$newPr->horairesOuvertureMercredi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureJeudi)) {
						$newPr->horairesOuvertureJeudi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureVendredi)) {
						$newPr->horairesOuvertureVendredi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureSamedi)) {
						$newPr->horairesOuvertureSamedi = '00:00-00:00 00:00-00:00';
					}
					if (empty($newPr->horairesOuvertureDimanche)) {
						$newPr->horairesOuvertureDimanche = '00:00-00:00 00:00-00:00';
					}

					return $newPr;
				}
			}
		} catch (Exception $e) {
			return false;
		}

		return false;
	}

	public function getPointsRelaisByPudo($cp = '', $address = array())
	{
		$params = array(
			'carrier'             => 'CHR',
			'key'                 => '75f6fe195dc88ceecbc0f8a2f70a8f3a',
			'address'             => array_key_exists('address',
				$address) ? $this->getFilledValue($address['address']) : '',
			'zipCode'             => array_key_exists('postcode',
				$address) ? $this->getFilledValue($address['postcode']) : $cp,
			'city'                => array_key_exists('city',
				$address) ? $this->getFilledValue($address['city']) : 'Lille',
			'countrycode'         => array_key_exists('country',
				$address) ? $this->getFilledValue($address['country']) : '',
			'requestID'           => '1',
			'date_from'           => date('d/m/Y'),
			'max_pudo_number'     => 5,
			'max_distance_search' => 10,
			'weight'              => 1,
			'category'            => '',
			'holiday_tolerant'    => 1,
		);
		try {
			$client = new SoapClient(
				'http://mypudo.pickup-services.com/mypudo/mypudo.asmx?wsdl', array(
					'trace'              => 0,
					'connection_timeout' => 10,
				)
			);
			$webservbt = $client->GetPudoList($params);
			$webservbt = json_decode(json_encode((object)simplexml_load_string($webservbt->GetPudoListResult->any)), 1);
			if (!isset($webservbt['ERROR'])) {
				$return = array();

				$listePr = $webservbt['PUDO_ITEMS']['PUDO_ITEM'];
				if ($listePr) {
					$i = 0;
					foreach ($listePr as $pr) {
						if ($pr['@attributes']['active'] == 'true' && $i < 5) {
							$newPr = (object)array();
							$newPr->adresse1 = $pr['ADDRESS1'];
							$newPr->adresse2 = is_array($pr['ADDRESS2']) ? implode(' ',
								$pr['ADDRESS2']) : $pr['ADDRESS2'];
							$newPr->adresse3 = is_array($pr['ADDRESS3']) ? implode(' ',
								$pr['ADDRESS3']) : $pr['ADDRESS3'];
							$newPr->codePostal = $pr['ZIPCODE'];
							$newPr->identifiantChronopostPointA2PAS = $pr['PUDO_ID'];
							$newPr->latitude = str_replace(",", ".", $pr['LATITUDE']);
							$newPr->longitude = str_replace(",", ".", $pr['LONGITUDE']);
							$newPr->localite = $pr['CITY'];
							$newPr->nomEnseigne = $pr['NAME'];
							$time = new DateTime;
							$newPr->dateArriveColis = $time->format(DateTime::ATOM);
							$newPr->horairesOuvertureLundi = $newPr->horairesOuvertureMardi = $newPr->horairesOuvertureMercredi = $newPr->horairesOuvertureJeudi = $newPr->horairesOuvertureVendredi = $newPr->horairesOuvertureSamedi = $newPr->horairesOuvertureDimanche = '';

							if (isset($pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'])) {
								$listeHoraires = $pr['OPENING_HOURS_ITEMS']['OPENING_HOURS_ITEM'];
								foreach ($listeHoraires as $horaire) {
									switch ($horaire['DAY_ID']) {
										case '1':
											if (!empty($newPr->horairesOuvertureLundi)) {
												$newPr->horairesOuvertureLundi .= ' ';
											}
											$newPr->horairesOuvertureLundi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
										case '2':
											if (!empty($newPr->horairesOuvertureMardi)) {
												$newPr->horairesOuvertureMardi .= ' ';
											}
											$newPr->horairesOuvertureMardi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
										case '3':
											if (!empty($newPr->horairesOuvertureMercredi)) {
												$newPr->horairesOuvertureMercredi .= ' ';
											}
											$newPr->horairesOuvertureMercredi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
										case '4':
											if (!empty($newPr->horairesOuvertureJeudi)) {
												$newPr->horairesOuvertureJeudi .= ' ';
											}
											$newPr->horairesOuvertureJeudi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
										case '5':
											if (!empty($newPr->horairesOuvertureVendredi)) {
												$newPr->horairesOuvertureVendredi .= ' ';
											}
											$newPr->horairesOuvertureVendredi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
										case '6':
											if (!empty($newPr->horairesOuvertureSamedi)) {
												$newPr->horairesOuvertureSamedi .= ' ';
											}
											$newPr->horairesOuvertureSamedi .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
										case '7':
											if (!empty($newPr->horairesOuvertureDimanche)) {
												$newPr->horairesOuvertureDimanche .= ' ';
											}
											$newPr->horairesOuvertureDimanche .= $horaire['START_TM'] . '-' . $horaire['END_TM'];
											break;
									}
								}
							}
							if (empty($newPr->horairesOuvertureLundi)) {
								$newPr->horairesOuvertureLundi = '00:00-00:00 00:00-00:00';
							}
							if (empty($newPr->horairesOuvertureMardi)) {
								$newPr->horairesOuvertureMardi = '00:00-00:00 00:00-00:00';
							}
							if (empty($newPr->horairesOuvertureMercredi)) {
								$newPr->horairesOuvertureMercredi = '00:00-00:00 00:00-00:00';
							}
							if (empty($newPr->horairesOuvertureJeudi)) {
								$newPr->horairesOuvertureJeudi = '00:00-00:00 00:00-00:00';
							}
							if (empty($newPr->horairesOuvertureVendredi)) {
								$newPr->horairesOuvertureVendredi = '00:00-00:00 00:00-00:00';
							}
							if (empty($newPr->horairesOuvertureSamedi)) {
								$newPr->horairesOuvertureSamedi = '00:00-00:00 00:00-00:00';
							}
							if (empty($newPr->horairesOuvertureDimanche)) {
								$newPr->horairesOuvertureDimanche = '00:00-00:00 00:00-00:00';
							}

							$return[] = $newPr;
							$i++;
						}
					}

					return $return;
				}
			}
		} catch (Exception $e) {
			return false;
		}

		return false;
	}

	public function getQuickcost($quickCost, $quickcost_url = '')
	{
		if (!$quickcost_url) {
			$quickcost_url = 'https://www.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl';
		}
		try {
			$client = new SoapClient($quickcost_url);
			$webservbt = $client->quickCost($quickCost);

			return $webservbt->return;
		} catch (Exception $e) {
			return false;
		}
	}

	public function checkLogin($quickCost, $quickcost_url = '')
	{
		return $this->calculateProducts($quickCost, $quickcost_url);
	}

	public function calculateProducts($quickCost, $quickcost_url = '')
	{
		if (!$quickcost_url) {
			$quickcost_url = 'https://www.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl';
		}
		try {
			$client = new SoapClient($quickcost_url);
			$webservbt = $client->calculateProducts($quickCost);

			return $webservbt;
		} catch (Exception $e) {
			return false;
		}
	}

    /**
     * Return true si la méthode de livraison fait partie du contrat
     *
     * @param null|WC_Chronopost_Product $chronopost_product
     * @param array                      $package
     * @param null                       $contract
     *
     * @return bool
     */
	public function getMethodIsAllowed($chronopost_product = null, $package = array(), $contract = null)
	{
		$code = $chronopost_product->product_code;
		$weight = 0;
		try {
			$safe_package = $package;
			$stored_allowed_methods = false;

			// Mapping ISO code to Chronopost code
			if ($package['destination']['country'] == 'YT' || $package['destination']['country'] == 'MC' || $package['destination']['country'] == 'MF') {
				$package['destination']['country'] = $this->getChronoCode()[$package['destination']['country']];
			}

			if (WC()->session) {
				$stored_allowed_methods = WC()->session->get('chrono_allowed_methods');
				// Remove data objects so hashes are consistent
				foreach ($safe_package['contents'] as $item_id => $item) {
					unset($safe_package['contents'][$item_id]['data']);
				}
				unset($safe_package['rates']);
				$safe_package_hash = 'wc_chrono_allowed_methods_' . md5(json_encode($safe_package) . WC_Cache_Helper::get_transient_version('shipping'));
				$weight = Chronopost_Package::getTotalWeight($package['contents']);
			}

			if (is_array($stored_allowed_methods) && $safe_package_hash == $stored_allowed_methods['allowed_methods_hash']) {
				$this->methodsAllowed = $stored_allowed_methods['methods_allowed'];
			} else {
				$this->methodsAllowed = false;
			}

			if ($this->methodsAllowed === false || !in_array($code, $this->methodsAllowed)) {
				$this->methodsAllowed = array();

				if (!$weight || !is_numeric($weight)) {
					$weight = '0.1';
				}

                if (!$contract) {
                    $contract = $chronopost_product->getContractInfos();
                }

				if (!$contract) {
					return false;
				}

				$params = array(
					'accountNumber'  => $contract['number'],
					'password'       => $contract['password'],
					'depCountryCode' => chrono_get_option('country', 'shipper'),
					'depZipCode'     => chrono_get_option('zipcode', 'shipper'),
					'arrCountryCode' => $package['destination']['country'],
					'arrZipCode'     => $package['destination']['postcode'],
					'arrCity'        => $package['destination']['city'] ? $this->getFilledValue($package['destination']['city']) : '-',
					'type'           => 'M',
					'weight'         => str_replace(',', '.', $weight),
				);

				$webservbt = $this->calculateProducts($params);
				if ($webservbt->return->errorCode == 0 && isset($webservbt->return->productList)) {
					if (is_array($webservbt->return->productList)) {
						foreach ($webservbt->return->productList as $product) {
							$this->methodsAllowed[] = $product->productCode;
						}
					} else { /* cas ou il y a un seul résultat */
						$product = $webservbt->return->productList;
						$this->methodsAllowed[] = $product->productCode;
					}
				}
			}

			if (WC()->session) {
				// Store in session to avoid recalculation
				WC()->session->set(
					'chrono_allowed_methods', array(
						'allowed_methods_hash' => $safe_package_hash,
						'methods_allowed'      => $this->methodsAllowed,
					)
				);
			}
			if (is_numeric($code)) {
				$code = (int)$code;
			}
			if (!empty($this->methodsAllowed) && in_array($code, $this->methodsAllowed)) {
				return true;
			}

			return false;
		} catch (Exception $e) {
			return false;
		}
	}


	public function getFilledValue($value)
	{
		if ($value) {
			return $this->removeaccents(trim($value));
		}

		return '';
	}

	public function removeaccents($string)
	{
		$stringToReturn = str_replace(
			array(
				'à',
				'á',
				'â',
				'ã',
				'ä',
				'ç',
				'è',
				'é',
				'ê',
				'ë',
				'ì',
				'í',
				'î',
				'ï',
				'ñ',
				'ò',
				'ó',
				'ô',
				'õ',
				'ö',
				'ù',
				'ú',
				'û',
				'ü',
				'ý',
				'ÿ',
				'À',
				'Á',
				'Â',
				'Ã',
				'Ä',
				'Ç',
				'È',
				'É',
				'Ê',
				'Ë',
				'Ì',
				'Í',
				'Î',
				'Ï',
				'Ñ',
				'Ò',
				'Ó',
				'Ô',
				'Õ',
				'Ö',
				'Ù',
				'Ú',
				'Û',
				'Ü',
				'Ý',
				'/',
				'\xa8'
			),
			array(
				'a',
				'a',
				'a',
				'a',
				'a',
				'c',
				'e',
				'e',
				'e',
				'e',
				'i',
				'i',
				'i',
				'i',
				'n',
				'o',
				'o',
				'o',
				'o',
				'o',
				'u',
				'u',
				'u',
				'u',
				'y',
				'y',
				'A',
				'A',
				'A',
				'A',
				'A',
				'C',
				'E',
				'E',
				'E',
				'E',
				'I',
				'I',
				'I',
				'I',
				'N',
				'O',
				'O',
				'O',
				'O',
				'O',
				'U',
				'U',
				'U',
				'U',
				'Y',
				' ',
				'e'
			),
			$string
		);
		// Remove all remaining other unknown characters
		$stringToReturn = preg_replace('/[^a-zA-Z0-9\-]/', ' ', $stringToReturn);
		$stringToReturn = preg_replace('/^[\-]+/', '', $stringToReturn);
		$stringToReturn = preg_replace('/[\-]+$/', '', $stringToReturn);
		$stringToReturn = preg_replace('/[\-]{2,}/', ' ', $stringToReturn);

		return $stringToReturn;
	}

	public function getEtiquetteRetour($_order)
	{
		// On récupère les infos d'expédition
		$order_shipping_method = $_order->get_shipping_methods();
		$shipping_method = reset($order_shipping_method);
		$shipping_method_instance = chrono_get_shipping_method_by_instance_id($shipping_method->get_instance_id());

		$expeditionArray = $this->getEtiquetteRetourParams($_order, $shipping_method->get_method_id(), $shipping_method_instance);
		if ($expeditionArray) {
			$client = new SoapClient('https://ws.chronopost.fr/shipping-cxf/ShippingServiceWS?wsdl', array('trace' => true));

			try {
				$webservbt = $client->shippingMultiParcelV3($expeditionArray);
				if (!$webservbt->return->errorCode && isset($webservbt->return->resultMultiParcelValue)) {
					$resultMultiParcelValue = is_array($webservbt->return->resultMultiParcelValue) ? $webservbt->return->resultMultiParcelValue[0] : $webservbt->return->resultMultiParcelValue;
				}

				if (!$webservbt->return->errorCode && $resultMultiParcelValue->pdfEtiquette) {
					$this->admin_notice = __('The return label has been generated and has just been sent',
						'chronopost');
					add_action('admin_notices', array($this, 'print_admin_success'));

					return $webservbt->return->resultMultiParcelValue;
				}

				$this->admin_notice = $webservbt->return->errorMessage;
				add_action('admin_notices', array($this, 'print_admin_notice'));

				return false;
			} catch (SoapFault $fault) {
				$this->admin_notice = $fault->faultstring;
				add_action('admin_notices', array($this, 'print_admin_notice'));

				return false;
			}
		}
	}


	protected function getEtiquetteRetourParams($_order, $_shippingMethod, $shippingMethodInstance)
	{
		if (!chrono_can_return_package($_order->get_shipping_country())) {
			$country = isset(WC()->countries->countries[$_order->shipping_country]) ? WC()->countries->countries[$_order->shipping_country] : $_order;
			$this->admin_notice = sprintf(__('Return labels are not available for this country : %s', 'chronopost'),
				$country);
			add_action('admin_notices', array($this, 'print_admin_notice'));

			return false;
		}

		$shippingMethodAllow = array_keys(get_option('chronopost_shipping_methods'));
		if (!in_array($_shippingMethod, $shippingMethodAllow)) {
			$this->admin_notice = __('Return labels are not available for this method:', 'chronopost') . ' ' . $_shippingMethod;
			add_action('admin_notices', array($this, 'print_admin_notice'));

			return false;
		}

		if (in_array($_shippingMethod, $shippingMethodAllow)) {
			$parcels_number = chrono_get_parcels_number($_order->get_id());
			$contract = $shippingMethodInstance->getContractInfos();

			//header parameters
			$header = array(
				'idEmit'        => 'WOO',
				'accountNumber' => $contract['number'],
				'subAccount'    => $contract['subaccount'],
			);

			//shipper parameters
			$shipperMobilePhone = $this->checkMobileNumber(chrono_get_option('mobile', 'return'));
			$recipient = array(
				'recipientAdress1'     => chrono_get_option('address', 'return'),
				'recipientAdress2'     => chrono_get_option('address2', 'return'),
				'recipientCity'        => chrono_get_option('city', 'return'),
				'recipientCivility'    => chrono_get_option('civility', 'return'),
				'recipientContactName' => chrono_get_option('contactname', 'return'),
				'recipientCountry'     => chrono_get_option('country', 'return'),
				'recipientEmail'       => chrono_get_option('email', 'return'),
				'recipientMobilePhone' => $shipperMobilePhone,
				'recipientName'        => chrono_get_option('name', 'return'),
				'recipientName2'       => chrono_get_option('name2', 'return'),
				'recipientPhone'       => chrono_get_option('phone', 'return'),
				'recipientPreAlert'    => '',
				'recipientZipCode'     => chrono_get_option('zipcode', 'return'),
			);

			//customer parameters
			$customerMobilePhone = $this->checkMobileNumber(chrono_get_option('mobile', 'customer'));
			$customer = array(
				'customerAdress1'     => chrono_get_option('address', 'customer'),
				'customerAdress2'     => chrono_get_option('address2', 'customer'),
				'customerCity'        => chrono_get_option('city', 'customer'),
				'customerCivility'    => chrono_get_option('civility', 'customer'),
				'customerContactName' => chrono_get_option('contactname', 'customer'),
				'customerCountry'     => chrono_get_option('country', 'customer'),
				'customerEmail'       => chrono_get_option('email', 'customer'),
				'customerMobilePhone' => $customerMobilePhone,
				'customerName'        => chrono_get_option('name', 'customer'),
				'customerName2'       => chrono_get_option('name2', 'customer'),
				'customerPhone'       => chrono_get_option('phone', 'customer'),
				'customerPreAlert'    => '',
				'customerZipCode'     => chrono_get_option('zipcode', 'customer'),
			);

			//recipient parameters
			$customer_obj = new WC_Customer($_order->get_customer_id());

            // Default shipper is original shipping address.
            // But for pickup carriers, we use the customer's billing address.
            // Exception for customers that have FR billing address and shipping to INT
			$addr_type = 'shipping';
            $isPickup = chrono_is_pickup($_shippingMethod);
			if ($isPickup) {
				$addr_type = 'billing';
			}

			// Champs forcément basés sur l'adresse de livraison
			$customer_email = $_order->get_billing_email() ? $_order->get_billing_email() : $customer_obj->get_email();

			$recipientMobilePhone = $this->checkMobileNumber($_order->get_billing_phone()); //no shipping mobile so we use the billing phone
			$recipientName = $this->getFilledValue(call_user_func(array(
				$_order,
				"get_{$addr_type}_company"
			))); //RelayPoint Name if chronorelais or Companyname if chronopost and

			$recipientName2 = $this->getFilledValue($_order->get_shipping_first_name() . ' ' . $_order->get_shipping_last_name());
			//remove any alphabets in phone number

			$recipientPhone = trim(preg_replace('/[^0-9\.\-]/', ' ', $_order->get_billing_phone()));

			$shipper = array(
				'shipperAdress1'     => substr($this->getFilledValue(call_user_func(array(
					$_order,
					"get_{$addr_type}_address_1"
				))), 0, 38),
				'shipperAdress2'     => substr($this->getFilledValue(call_user_func(array(
					$_order,
					"get_{$addr_type}_address_2"
				))), 0, 38),
				'shipperCity'        => $this->getFilledValue(call_user_func(array($_order, "get_{$addr_type}_city"))),
				'shipperCivility'    => 'M',
				'shipperContactName' => $recipientName2,
				'shipperCountry'     => $this->getFilledValue(call_user_func(array(
					$_order,
					"get_{$addr_type}_country"
				))),
				'shipperEmail'       => $customer_email,
				'shipperMobilePhone' => $recipientMobilePhone,
				'shipperName'        => $recipientName,
				'shipperName2'       => $recipientName2,
				'shipperPhone'       => $recipientPhone,
				'shipperPreAlert'    => '',
				'shipperZipCode'     => $this->getFilledValue(call_user_func(array(
					$_order,
					"get_{$addr_type}_postcode"
				))),
			);

			//ref parameters
			$recipientRef = false;

			if ($pickup_relay_datas = get_post_meta($_order->get_id(), '_shipping_method_chronorelais', true)) {
				$recipientRef = $pickup_relay_datas['id'];
			}

			if (!$recipientRef) {
				$recipientRef = $_order->get_customer_id();
			}

			$shipperRef = $_order->get_id();

			$ref = array(
				'recipientRef' => $recipientRef,
				'shipperRef'   => $shipperRef,
			);

			// Livraison Samedi (Delivery Saturday) field
			$SaturdayShipping = 0; //default value for the saturday shipping

			if (in_array($_shippingMethod, $shippingMethodAllow)) {
				$_deliver_on_saturday = get_post_meta($_order->get_id(), '_ship_on_saturday', true) === 'yes' ? true : false;
				if (!$_deliver_on_saturday) {
					$_deliver_on_saturday = chrono_get_method_settings($_shippingMethod, 'deliver_on_saturday') == 'yes' ? 1 : 0;
				} else {
					if ($_deliver_on_saturday) {
						$_deliver_on_saturday = 1;
					} else {
						$_deliver_on_saturday = 0;
					}
				}

				$is_sending_day = chrono_is_sending_day();
				if ($_deliver_on_saturday && $is_sending_day) {
					$SaturdayShipping = 6;

					// Code différent si Chrono Relai Dom
					if ($_shippingMethod === 'chronorelaisdom') {
						$SaturdayShipping = 368;
					}
				} elseif (!$_deliver_on_saturday && $is_sending_day) {
					$SaturdayShipping = 1;
				}
			}

			$weight = 0; /* On met le poids à 0 car les colis sont pesé sur place */
			$method_datas = chrono_get_shipping_method_by_id($_shippingMethod);
			$productCode = $method_datas->product_code_return;

			// If return from abroad (not France) : 3T
			// If return from France : 4T
			$returnMethod = get_post_meta($_order->get_id(), '_last_return_method', true) ?? '4T';
			if ($returnMethod === '') {
				$returnMethod = '4T';
			}

			if ($recipient['recipientCountry'] === 'FR' && $shipper['shipperCountry'] !== 'FR') {
				// todo: check if contract has 2shop for 2shopretour europe
				$productCode = '3T';
				$SaturdayShipping = '332';
			} elseif ($recipient['recipientCountry'] === 'FR' && $shipper['shipperCountry'] === 'FR') {
				$productCode = $returnMethod;

				// Exception for 2ShopDirect
				if ($method_datas->product_code_return === self::CHRONOPOST_REVERSE_TOSHOP) {
					$productCode = $method_datas->product_code_return;
					$SaturdayShipping = 6;
				}
			}

			$skybill = array(
				'codCurrency'     => 'EUR',
				'codValue'        => '',
				'content1'        => '',
				'content2'        => '',
				'content3'        => '',
				'content4'        => '',
				'content5'        => '',
				'customsCurrency' => 'EUR',
				'customsValue'    => '',
				'evtCode'         => 'DC',
				'insuredCurrency' => 'EUR',
				'insuredValue'    => '',
				'objectType'      => 'MAR',
				'productCode'     => $productCode,
				'service'         => $SaturdayShipping,
				'shipDate'        => date('c'),
				'shipHour'        => date('H'),
				'weight'          => $weight,
				'weightUnit'      => 'KGM',
				'height'          => 1,
				'length'          => 1,
				'width'           => 1,
			);

			$skybillParams = array(
				'mode'            => chrono_get_option('mode', 'skybill'),
				'withReservation' => 2,
			);

            if ($_shippingMethod === 'chronorelaiseurope' || $_shippingMethod === 'chronotoshopeurope' || $_shippingMethod === 'chronorelaisdom' ||
                $_shippingMethod === 'chronoexpress') {
                $skybillParams['mode'] = 'PPR|XML';
            }

            switch ($productCode) {
                case '4T':
                    $skybillParams['mode'] = 'SLT|XML|XML2D|PDF';
                    break;
                case '3T':
                case '6C':
                    $skybillParams['mode'] = 'PPR|XML';
                    break;
            }

			$expeditionArray = array(
				'headerValue'        => $header,
				'shipperValue'       => $shipper,
				'customerValue'      => $customer,
				'recipientValue'     => $recipient,
				'refValue'           => $ref,
				'skybillValue'       => $skybill,
				'skybillParamsValue' => $skybillParams,
				'password'           => $contract['password'],
				'numberOfParcel'     => $parcels_number,
			);

			if ($productCode === '4T') {
				$expeditionArray = array_merge($expeditionArray, array(
					'version'     => '2.0',
					'modeRetour'  => '3',
					'multiParcel' => 'N',
				));
			}

			return $expeditionArray;
		}
	}

	public function cancelSkybill($_order, $skybillNumber = '', $shipping_method_id = 0)
	{
		if ($skybillNumber) {
			try {
				$client = new SoapClient(
					'https://www.chronopost.fr/tracking-cxf/TrackingServiceWS?wsdl', array(
						'trace'              => 0,
						'connection_timeout' => 10,
					)
				);

				$shipping_method = chrono_get_shipping_method_by_id($shipping_method_id);
				$contract = $shipping_method->getContractInfos($_order);
				$params = array(
					'accountNumber' => $contract['number'],
					'password'      => $contract['password'],
					'skybillNumber' => $skybillNumber,
					'language'      => get_option('WPLANG'),
				);

				$webservbt = $client->cancelSkybill($params);

				if ($webservbt) {
					/* suppression du numéro de tracking */
					if ($webservbt->return->errorCode === 0) {
						$this->admin_notice = sprintf(__('The label %s was cancelled', 'chronopost'), $skybillNumber);
						add_action('admin_notices', array($this, 'print_admin_success'));

						return true;
					} else {
						switch ($webservbt->return->errorCode) {
							case '1':
								$this->admin_notice = sprintf(__('An error occured when cancelling the %s label',
									'chronopost'), $skybillNumber);
								break;
							case '2':
								$this->admin_notice = sprintf(__('the %s package does not belong to the contract passed as parameter or has not yet been registered in the Chronopost tracking system',
									'chronopost'), $skybillNumber);
								break;
							case '3':
								$this->admin_notice = sprintf(__('The %s package can not be cancelled because it was supported by Chronopost',
									'chronopost'), $skybillNumber);
								break;
							default:
								$this->admin_notice = '';
								break;
						}
						add_action('admin_notices', array($this, 'print_admin_notice'));

						return false;
					}
				} else {
					$this->admin_notice = sprintf('Désolé, une erreur est survenu lors de la suppression de l\'étiquette %s. Merci de contacter Chronopost ou de réessayer plus tard',
						$skybillNumber);
					add_action('admin_notices', array($this, 'print_admin_notice'));

					return false;
				}
			} catch (Exception $e) {
				return false;
			}
		}

		return false;
	}

	/* Livraison sur rendez-vous */
	public function getSearchDeliverySlot($shipping_method_id)
	{
		$chronopost_product = chrono_get_shipping_method_by_id($shipping_method_id);
		$table_slots = get_option($shipping_method_id . '_' . $chronopost_product->get_instance_id() . '_table_slots', false);
		$cost_levels = get_option($shipping_method_id . '_' . $chronopost_product->get_instance_id() . '_cost_levels', false);

		try {
			$contract = $chronopost_product->getContractInfos();
			$soapHeaders = array();
			$namespace = 'http://cxf.soap.ws.creneau.chronopost.fr/';
			$soapHeaders[] = new SoapHeader($namespace, 'password', $contract['password']);
			$soapHeaders[] = new SoapHeader($namespace, 'accountNumber', $contract['number']);

			$_creneauServiceUrl = 'https://www.chronopost.fr/rdv-cxf/services/CreneauServiceWS?wsdl';
			if ($chronopost_product->instance_settings['quickcost_url']) {
				$_creneauServiceUrl = preg_replace('/https:\/\/(.*)\/quickcost-cxf\/QuickcostServiceWS\?wsdl/U',
					'https://$1/rdv-cxf/services/CreneauServiceWS?wsdl',
					$chronopost_product->instance_settings['quickcost_url']);
			}
			$client = new SoapClient(
				$_creneauServiceUrl, array(
					'trace'              => 0,
					'connection_timeout' => 10,
				)
			);
			$client->__setSoapHeaders($soapHeaders);
			$dateRemiseColis_nbJour = chrono_get_method_settings($shipping_method_id, 'delivery_date_day_nbr');
			$dateRemiseColis_jour = chrono_get_method_settings($shipping_method_id, 'delivery_date_day');
			$dateRemiseColis_heures_minutes = chrono_get_method_settings($shipping_method_id, 'delivery_date_hour');

			/* definition date de debut */
			$dateBegin = date('Y-m-d H:i:s');
			if (isset($dateRemiseColis_nbJour) && $dateRemiseColis_nbJour > 0) {
				$dateBegin = date('Y-m-d', strtotime('+' . (int)$dateRemiseColis_nbJour . ' day'));
			} elseif (isset($dateRemiseColis_jour) && isset($dateRemiseColis_heures_minutes)) {
				$jour_text = date('l', strtotime('Sunday +' . $dateRemiseColis_jour . ' days'));
				$dateBegin = date('Y-m-d',
						strtotime('next ' . $jour_text)) . ' ' . $dateRemiseColis_heures_minutes . ':00';
			}
			$dateBegin = date('Y-m-d', strtotime($dateBegin)) . 'T' . date('H:i:s', strtotime($dateBegin));

			/* si dom => on ne met pas le code ISO mais un code spécifique, sinon le relai dom ne fonctionne pas */
			$countryDomCode = $this->getCountryDomCode();

			$countryId = WC()->customer->get_shipping_country();
			if (isset($countryDomCode[$countryId])) {
				$countryId = $countryDomCode[$countryId];
			}

			$params = array(
				'callerTool'  => 'RDVWS',
				'productType' => 'RDV',
				'shipperAdress1' => chrono_get_option('address', 'shipper'),
				'shipperAdress2' => chrono_get_option('address2', 'shipper'),
				'shipperZipCode' => chrono_get_option('zipcode', 'shipper'),
				'shipperCity'    => chrono_get_option('city', 'shipper'),
				'shipperCountry' => chrono_get_option('country', 'shipper'),
				'recipientAdress1' => $this->getFilledValue(WC()->customer->get_shipping_address()),
				'recipientAdress2' => $this->getFilledValue(WC()->customer->get_shipping_address_2()),
				'recipientZipCode' => $this->getFilledValue(WC()->customer->get_shipping_postcode()),
				'recipientCity'    => $this->getFilledValue(WC()->customer->get_shipping_city()),
				'recipientCountry' => $this->getFilledValue($countryId),
				'weight'                    => 1,
				'dateBegin'                 => $dateBegin,
				'shipperDeliverySlotClosed' => '',
				'currency'                  => 'EUR',
				'isDeliveryDate'            => 0,
				'slotType'                  => '',
			);

			if (is_array($cost_levels)) {
				$rateLevelsNotShow = array();
				foreach ($cost_levels as $key => $cost_level) {
					if (array_key_exists('price', $cost_level)) {
						$params['rate' . $key] = $cost_level['price'];
					}
					if (array_key_exists('status', $cost_level) && $cost_level['status'] == '0') {
						$rateLevelsNotShow[] = $key;
					}
				}
				$params['rateLevelsNotShow'] = $rateLevelsNotShow;
			}

			/* creneaux à fermer */
			if (is_array($table_slots) && count($table_slots) > 0) {
				foreach ($table_slots as $_creneau) {
					$endHour = explode(':', $_creneau['endhour']);
					$endHour[1] = str_pad($endHour[1] + 1, 2, '0', STR_PAD_LEFT);
					$_creneau['endhour'] = implode(':', $endHour);
					$jour_debut_text = date('l', strtotime('Sunday +' . $_creneau['startday'] . ' days'));
					$jour_fin_text = date('l', strtotime('Sunday +' . $_creneau['endday'] . ' days'));

					$dateDebut = '';
					$dateFin = '';

					/* creation de creneaux aux bons formats, pour 6 semaines consécutives */
					for ($indiceWeek = 0; $indiceWeek < 6; $indiceWeek++) {
						if (empty($dateDebut)) {
							$dateDebut = date('Y-m-d',
									strtotime('next ' . $jour_debut_text)) . ' ' . $_creneau['starthour'] . ':00';
							$dateFin = date('Y-m-d',
									strtotime('next ' . $jour_fin_text)) . ' ' . $_creneau['endhour'] . ':00';
							if (date('N') >= $_creneau['startday']) {
								$dateDebut = date('Y-m-d', strtotime(date('Y-m-d',
											strtotime($dateDebut)) . ' -7 day')) . ' ' . $_creneau['starthour'] . ':00';
							}
							if (date('N') >= $_creneau['endday']) {
								$dateFin = date('Y-m-d', strtotime(date('Y-m-d',
											strtotime($dateFin)) . ' -7 day')) . ' ' . $_creneau['endhour'] . ':00';
							}
						} else {
							$dateDebut = date('Y-m-d', strtotime($jour_debut_text . ' next week ' . date('Y-m-d',
										strtotime($dateDebut)))) . ' ' . $_creneau['starthour'] . ':00';
							$dateFin = date('Y-m-d', strtotime($jour_fin_text . ' next week ' . date('Y-m-d',
										strtotime($dateFin)))) . ' ' . $_creneau['endhour'] . ':00';
						}

						$dateDebutStr = date('Y-m-d',
								chrono_add_gmt_timestamp(strtotime($dateDebut))) . 'T' . date('H:i:s',
								chrono_add_gmt_timestamp(strtotime($dateDebut)));
						$dateFinStr = date('Y-m-d', chrono_add_gmt_timestamp(strtotime($dateFin))) . 'T' . date('H:i:s',
								chrono_add_gmt_timestamp(strtotime($dateFin)));

						if (!isset($params['shipperDeliverySlotClosed']) || $params['shipperDeliverySlotClosed'] == '') {
							$params['shipperDeliverySlotClosed'] = array();
						}
						$params['shipperDeliverySlotClosed'][] = $dateDebutStr . '/' . $dateFinStr;
					}
				}
			}

			$webservbt = $client->searchDeliverySlot($params);

			if ($webservbt->return->code == 0) {
				return $webservbt;
			}

			return false;
		} catch (Exception $e) {
			return false;
		}
	}

	public function confirmDeliverySlot($chronopost_product, $rdvInfo = '')
	{
		try {
			$contract = $chronopost_product->getContractInfos();
			$soapHeaders = array();
			$namespace = 'http://cxf.soap.ws.creneau.chronopost.fr/';
			$soapHeaders[] = new SoapHeader($namespace, 'password', $contract['password']);
			$soapHeaders[] = new SoapHeader($namespace, 'accountNumber', $contract['number']);

			$_creneauServiceUrl = 'https://www.chronopost.fr/rdv-cxf/services/CreneauServiceWS?wsdl';
			if ($chronopost_product->instance_settings['quickcost_url']) {
				$_creneauServiceUrl = preg_replace('/https:\/\/(.*)\/quickcost-cxf\/QuickcostServiceWS\?wsdl/U',
					'https://$1/rdv-cxf/services/CreneauServiceWS?wsdl',
					$chronopost_product->instance_settings['quickcost_url']);
			}
			$client = new SoapClient(
				$_creneauServiceUrl, array(
					'trace'              => 1,
					'connection_timeout' => 10,
				)
			);
			$client->__setSoapHeaders($soapHeaders);

			$params = array(
				'callerTool'  => 'RDVWS',
				'productType' => 'RDV',

				'codeSlot'      => $rdvInfo['deliverySlotCode'],
				'meshCode'      => $rdvInfo['meshCode'],
				'transactionID' => $rdvInfo['transactionID'],
				'rank'          => $rdvInfo['rank'],
				'position'      => $rdvInfo['rank'],
				'dateSelected'  => $rdvInfo['deliveryDate'],
			);

			return $client->confirmDeliverySlotV2($params);
		} catch (Exception $e) {
			return false;
		}
	}

	protected function checkMobileNumber($value)
	{
		if ($reqvalue = trim($value)) {
			$_number = substr($reqvalue, 0, 2);
			$fixed_array = array('01', '02', '03', '04', '05', '06', '07');
			if (in_array($_number, $fixed_array)) {
				return $reqvalue;
			}

			return '';
		}
	}
}
