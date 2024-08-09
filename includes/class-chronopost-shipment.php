<?php

use setasign\Fpdi\Fpdi;

/**
 * Chronopost Shipment Management
 *
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

function chronopost_shipment_init()
{
	class Chronopost_Shipment
	{
		public function saveAndCreateShipmentLabel($order_id = 0)
		{
			if (!is_admin()) {
				return false;
			}
			$_order = new WC_Order($order_id);
			$ws = new Chronopost_Webservice();
			$shipping_labels = $ws->saveAndCreateShipmentLabel($_order);
			if (isset($_GET['action']) && $_GET['action'] == 'edit' && !empty($ws->admin_notice)) {
				set_transient('chronopost_admin_error', $ws->admin_notice);
			}

			return $shipping_labels;
		}

		/**
		 * Fetch from disk if available, or fetch from webservice
		 *
		 * @param $reservation
		 *
		 * @return string File location
		 */
		public function getShipmentLabel($reservation)
		{
			$pdf_file = chrono_get_media_path() . $reservation . '.pdf';

			if (!is_file($pdf_file)) {
				$ws = new Chronopost_Webservice();
				$label = $ws->getPDFSkybillByReservation($reservation);
				if ($label && $label->errorCode === 0) {
					file_put_contents($pdf_file, base64_decode($label->skybill));
				} else {
					if ($label === false) {
						throw new Exception(__('An error occured during the label creation.', 'chronopost'));
					}
				}
			}

			return $pdf_file;
		}
	}

	shipment_admin_print_action();
	shipment_admin_cancel_action();
	shipment_admin_view_action();
	shipment_admin_return_label_action();
	shipment_admin_daily_docket_print_action();
	shipment_admin_export_css_action();

	if (
		isset($_REQUEST['chronoaction']) && $_REQUEST['chronoaction'] == '-1'
		&& isset($_REQUEST['shipment_nonce'])
		&& wp_verify_nonce($_REQUEST['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		$_SESSION['alert_msg'] = __('Please select an action in the "Bulk Actions" list', 'chronopost');
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	}
}

function shipment_admin_cancel_action()
{
	if (
		isset($_REQUEST['chronoaction']) && $_REQUEST['chronoaction'] == 'cancel-label'
		&& isset($_REQUEST['shipment_nonce'])
		&& wp_verify_nonce($_REQUEST['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (!isset($_REQUEST['order'])) {
			chronopost_error_no_order_selected();
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			header('Location: ' . $_SERVER['HTTP_REFERER']);

			return;
		}

		$order_ids = array_map('esc_attr', (array)$_REQUEST['order']);
		try {
			foreach ($order_ids as $order_id) {
				$_order = new WC_Order($order_id);
				$shipment_labels_datas = get_post_meta($order_id, '_shipment_datas', true);

				foreach ($shipment_labels_datas as $key => $ship_data) {
					foreach ($ship_data['_parcels'] as $parcel_key => $parcel) {
						$ws = new Chronopost_Webservice();
						if ($ws->cancelSkybill($_order, $parcel['_skybill_number'],
							$ship_data['_shipping_method_id'])) {
							unset($shipment_labels_datas[$key]['_parcels'][$parcel_key]);
							if (empty($shipment_labels_datas[$key]['_parcels'])) {
								unset($shipment_labels_datas[$key]);
							}
						}
					}
				}
				update_post_meta($order_id, '_shipment_datas', $shipment_labels_datas);
			}
		} catch (SoapFault $fault) {
			add_action('admin_notices', 'chronopost_default_generate_label_error');
		}
	}
}

function chronopost_error_no_order_selected()
{
	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	$_SESSION['alert_msg'] = __('Please select orders before generate labels', 'chronopost');
}

function chronopost_error_return_label_already_created()
{
	$class = 'notice notice-error';
	$message = __('Return label has already been generated.', 'chronopost');
	echo chrono_notice($message, 'error');
}

function chronopost_default_generate_label_error()
{
	$class = 'notice notice-error';
	$message = __('An error occured while generating your labels. Please try again.', 'chronopost');
	echo chrono_notice($message, 'error');
}

function shipment_admin_view_action()
{
	if (
		isset($_GET['chronoaction']) && $_GET['chronoaction'] == 'view-label'
		&& isset($_GET['shipment_nonce'])
		&& wp_verify_nonce($_GET['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (!isset($_GET['order'])) {
			chronopost_error_no_order_selected();
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			header('Location: ' . $_SERVER['HTTP_REFERER']);

			return;
		}

		$order_ids = (array)$_REQUEST['order'];

		$shipment = new Chronopost_Shipment();
		$tmp_pdf_file = false;
		$pdf = new FPDI();

		try {
			foreach ($order_ids as $order_id) {
				$shipment_labels_datas = get_post_meta($order_id, '_shipment_datas', true);
				$reservations_printed = array();
				foreach ($shipment_labels_datas as $ship_data) {
					foreach ($ship_data['_parcels'] as $parcel_data) {
						// Legacy code (versions before 1.1.0)
						if (isset($parcel_data['_pdf_buffer'])) {
							$tmp_pdf_file = chrono_get_media_path() . $parcel_data['_skybill_number'] . '.pdf';
							file_put_contents($tmp_pdf_file, base64_decode($parcel_data['_pdf_buffer']));
						} else {
							if (isset($ship_data['_reservation_number']) && !empty($ship_data['_reservation_number'])) {
								if (in_array($ship_data['_reservation_number'], $reservations_printed)) {
									continue;
								}
								$pdf_file = $shipment->getShipmentLabel($ship_data['_reservation_number']);
								$tmp_pdf_file = $pdf_file;
								$reservations_printed[] = $ship_data['_reservation_number'];
							} else {
								if (isset($parcel_data['_skybill_number']) && !empty($parcel_data['_skybill_number'])) {
									if (in_array($parcel_data['_skybill_number'], $reservations_printed)) {
										continue;
									}
									$pdf_file = $shipment->getShipmentLabel($parcel_data['_skybill_number']);
									$tmp_pdf_file = $pdf_file;
									$reservations_printed[] = $parcel_data['_skybill_number'];
								} else {
									add_action('admin_notices', 'chronopost_default_generate_label_error');

									return false;
								}
							}
						}

						$pageCount = $pdf->setSourceFile($tmp_pdf_file);

						for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
							$template_id = $pdf->importPage($pageNo);
							$size = $pdf->getTemplateSize($template_id);

							if ($size['width'] > $size['height']) {
								$pdf->AddPage('L', array($size['width'], $size['height']));
							} else {
								$pdf->AddPage('P', array($size['width'], $size['height']));
							}

							$pdf->useTemplate($template_id);
						}
					}
				}
			}
		} catch (SoapFault $fault) {
			add_action('admin_notices', 'chronopost_default_generate_label_error');
		} catch (Exception $exception) {
			add_action('admin_notices', 'chronopost_default_generate_label_error');
		}

		if ($tmp_pdf_file) {
			$pdf->Output('D', 'chronopost-labels-' . date('d-m-Y') . '.pdf');
		}

		exit;
	}
}

function addFieldToCsv($csvContent, $fieldDelimiter, $fieldContent)
{
	return $csvContent . $fieldDelimiter . $fieldContent . $fieldDelimiter;
}

function shipment_admin_export_css_action()
{
	if (
		isset($_REQUEST['chronoaction']) && $_REQUEST['chronoaction'] == 'export-css'
		&& isset($_REQUEST['shipment_nonce'])
		&& wp_verify_nonce($_REQUEST['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (!isset($_REQUEST['order'])) {
			chronopost_error_no_order_selected();
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			header('Location: ' . $_SERVER['HTTP_REFERER']);

			return;
		}

		$order_ids = (array)$_REQUEST['order'];

		try {
			$separator = chrono_get_option('field_separator', 'css');
			$delimiter = chrono_get_option('field_delimiter', 'css');
			if ($delimiter == 'simple_quote') {
				$delimiter = "'";
			} elseif ($delimiter == 'double_quotes') {
				$delimiter = '"';
			} else {
				$delimiter = '';
			}

			$lineBreak = chrono_get_option('eol', 'css');
			if ($lineBreak == 'lf') {
				$lineBreak = "\n";
			} elseif ($lineBreak == 'cr') {
				$lineBreak = "\r";
			} elseif ($lineBreak == 'crlf') {
				$lineBreak = "\r\n";
			}

			$fileExtension = chrono_get_option('file_extension', 'css');
			$fileCharset = chrono_get_option('file_charset', 'css');

			$filename = 'chronopost-exportcss_' . date('d-m-Y') . $fileExtension;

			$content = '';

			foreach ($order_ids as $order_id) {
				$order = new WC_Order($order_id);

				$shipment_extra_datas = chrono_get_shipment_datas($order_id);
				$customer_obj = new WC_Customer($order->get_customer_id());

				$order_shipping_method = $order->get_shipping_methods();
				$shipping_method = reset($order_shipping_method);
				$_shippingMethod = $shipping_method->get_method_id();

				/* customer id */
				$content = addFieldToCsv($content, $delimiter, ($order->get_customer_id() ? $order->get_customer_id() : $order->get_shipping_last_name()));
				$content .= $separator;

				/* Nom du point relais OU société si livraison à domicile */
				$content = addFieldToCsv($content, $delimiter, $order->get_shipping_company());
				$content .= $separator;

				/* customer name */
				$content = addFieldToCsv($content, $delimiter,
					($order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name()));
				$content .= $separator;
				$content = addFieldToCsv($content, $delimiter,
					($order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name()));
				$content .= $separator;

				/* street address */
				$content = addFieldToCsv($content, $delimiter, $order->get_shipping_address_1());
				$content .= $separator;
				$content = addFieldToCsv($content, $delimiter, $order->get_shipping_address_2());
				$content .= $separator;

				/* digicode (vide)*/
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* postal code */
				$content = addFieldToCsv($content, $delimiter, $order->get_shipping_postcode());
				$content .= $separator;

				/* city */
				$content = addFieldToCsv($content, $delimiter, $order->get_shipping_city());
				$content .= $separator;

				/* country code */
				$content = addFieldToCsv($content, $delimiter, $order->get_shipping_country());
				$content .= $separator;

				/* telephone */
				$telephone = trim(preg_replace("/[^0-9.-]/", " ", $order->get_billing_phone()));
				$telephone = (strlen($telephone) >= 10 ? $telephone : '');
				$content = addFieldToCsv($content, $delimiter, $telephone);
				$content .= $separator;

				/* email */
				$customer_email = $order->get_billing_email() ? $order->get_billing_email() : $customer_obj->get_email();
				$content = addFieldToCsv($content, $delimiter, $customer_email);
				$content .= $separator;

				/* real order id */
				$content = addFieldToCsv($content, $delimiter, (int)$order->get_id());
				$content .= $separator;

				/* code barre client (vide) */
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* productCode */
				$content = addFieldToCsv($content, $delimiter, chrono_get_product_code_by_id($_shippingMethod));
				$content .= $separator;

				/* compte (vide) */
				$content = addFieldToCsv($content, $delimiter, chrono_get_option('account'));
				$content .= $separator;

				/* sous compte (vide) */
				$content = addFieldToCsv($content, $delimiter, chrono_get_option('subaccount'));
				$content .= $separator;

				/* empty fields : assurance */
				$maxAmount = 20000;
				$adValoremAmount = (float)chrono_get_option('min_amount', 'insurance');
				$adValoremEnabled = chrono_get_option('enable', 'insurance') === 'yes' ? true : false;

				$order_insurance_enable = get_post_meta($order->get_id(), '_insurance_enable', true);
				$insurance_amount = (float)get_post_meta($order->get_id(), '_insurance_amount', true);
				if ($order_insurance_enable != '') {
					$adValoremEnabled = $order_insurance_enable === 'no' ? false : true;
				}

				$totalAdValorem = (float)$order->get_total() - $order->get_shipping_total();

				$totalAdValorem = $insurance_amount > 0 ? $insurance_amount : $totalAdValorem;

				if ($adValoremEnabled) {
					$totalAdValorem = min($totalAdValorem, $maxAmount);
					if ($totalAdValorem < $adValoremAmount) {
						$totalAdValorem = 0;
					}
				}

				$content = addFieldToCsv($content, $delimiter, (float)$totalAdValorem);
				$content .= $separator;

				/* Valeur douane */
				$content = addFieldToCsv($content, $delimiter, 0);
				$content .= $separator;


				/* Document ou marchandise */
				$content = addFieldToCsv($content, $delimiter, 'M');
				$content .= $separator;

				/* description contenu (vide) */
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* Livraison Samedi */
				$SaturdayShipping = 'L'; //default value for the saturday shipping
				if ($_shippingMethod === "chronopost" || $_shippingMethod === "chronorelais") {
					$is_sending_day = chrono_is_sending_day();

					$_deliver_on_saturday = chrono_get_method_settings(
						$_shippingMethod,
						'deliver_on_saturday'
					) === 'yes' ? 1 : 0;

					if ($_deliver_on_saturday && $is_sending_day) {
						$SaturdayShipping = 'S';
					} elseif (!$_deliver_on_saturday && $is_sending_day) {
						$SaturdayShipping = 'L';
					}
				}

				$content = addFieldToCsv($content, $delimiter, $SaturdayShipping);
				$content .= $separator;

				/* chronorelay point */
				$recipientRef = '';
				if ($pickup_relay_datas = get_post_meta($order->get_id(), '_shipping_method_chronorelais', true)) {
					$recipientRef = $pickup_relay_datas['id'];
				}

				$content = addFieldToCsv($content, $delimiter, $recipientRef);
				$content .= $separator;

				/* total weight (in kg) */
				$order_weight = Chronopost_Package::getTotalWeight($order->get_items());
				$order_weight = number_format($order_weight, 2, '.', '');
				$content = addFieldToCsv($content, $delimiter, $order_weight);
				$content .= $separator;

				/* largeur (vide) */
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* longueur (vide) */
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* hauteur (vide) */
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* avertir destinataire (vide) */
				$content = addFieldToCsv($content, $delimiter, '1');
				$content .= $separator;

				/* nb colis (vide) */
				$content = addFieldToCsv($content, $delimiter, count($shipment_extra_datas));
				$content .= $separator;

				/* date envoi */
				$content = addFieldToCsv($content, $delimiter, date('d/m/Y'));
				$content .= $separator;

				/* a intégrer (vide) */
				$content = addFieldToCsv($content, $delimiter, 'Y');
				$content .= $separator;

				/* avertir expéditeur (vide) */
				$content = addFieldToCsv($content, $delimiter, 'N');
				$content .= $separator;

				/* DLC (vide) */
				$content = addFieldToCsv($content, $delimiter, '');
				$content .= $separator;

				/* champ specifique rdv */
				$chronopostprecise_creneaux_info = get_post_meta($order->get_id(), '_shipping_method_chronoprecise');
				if ($chronopostprecise_creneaux_info) {
					if (is_array($chronopostprecise_creneaux_info)) {
						$chronopostprecise_creneaux_info = array_shift($chronopostprecise_creneaux_info);
					}
					$_dateRdvStart = new DateTime($chronopostprecise_creneaux_info['deliveryDate']);
					$_dateRdvStart->setTime($chronopostprecise_creneaux_info['startHour'],
						$chronopostprecise_creneaux_info['startMinutes']);

					$_dateRdvEnd = new DateTime($chronopostprecise_creneaux_info['deliveryDate']);
					$_dateRdvEnd->setTime($chronopostprecise_creneaux_info['endHour'],
						$chronopostprecise_creneaux_info['endMinutes']);

					/* date debut rdv */
					$content = addFieldToCsv($content, $delimiter, $_dateRdvStart->format("dmyHi"));
					$content .= $separator;

					/* date fin rdv */
					$content = addFieldToCsv($content, $delimiter, $_dateRdvEnd->format("dmyHi"));
					$content .= $separator;

					/* Niveau tarifaire */
					$content = addFieldToCsv($content, $delimiter, $chronopostprecise_creneaux_info['tariffLevel']);
					$content .= $separator;

					/* code service */
					$content = addFieldToCsv($content, $delimiter, $chronopostprecise_creneaux_info['serviceCode']);
				} else {
					$content = addFieldToCsv($content, $delimiter, '');
					$content .= $separator;

					$content = addFieldToCsv($content, $delimiter, '');
					$content .= $separator;

					$content = addFieldToCsv($content, $delimiter, '');
					$content .= $separator;

					/* code service */
					$content = addFieldToCsv($content, $delimiter, '');
				}

				$content .= $lineBreak;
			}

			/* decode the content, depending on the charset */
			if ($fileCharset === 'ISO-8859-1') {
				$content = utf8_decode($content);
			}

			/* pick file mime type, depending on the extension */
			switch ($fileExtension) {
				case '.csv':
					$fileMimeType = 'application/csv';
					break;
				case '.chr':
					$fileMimeType = 'application/chr';
					break;
				default:
					$fileMimeType = 'text/plain';
					break;
			}

			header('Pragma: public');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0', true);
			header("Content-type: $fileMimeType", true);
			header('Content-Length: ' . strlen($content));
			header('Content-Disposition: attachment; filename=' . $filename);
			header('Last-Modified: ' . date('r'));

			print $content;

			die();
		} catch (SoapFault $fault) {
			add_action('admin_notices', 'chronopost_default_generate_label_error');
		}

		$pdf = new FPDI();
		$pdf->SetFont('Arial', 'B', 10);
		$pdf->AddPage();
		$pdf->Cell(150, 10, 'BORDEREAU RECAPITULATIF');
		$pdf->SetFont('Arial', '', 10);
		$pdf->Cell(100, 10, 'date : ' . date('d/m/Y'));
		$pdf->Ln();
		$pdf->Ln();

		$pdf->SetFont('Arial', 'B', 10);

		$pdf->OxiCell('EMETTEUR', 0, 10);
		$pdf->HeadTable(array(
			array('NOM', chrono_get_option('name', 'customer')),
			array('ADRESSE', chrono_get_option('address', 'customer')),
			array('ADRESSE (SUITE)', chrono_get_option('address2', 'customer')),
			array('VILLE', chrono_get_option('city', 'customer')),
			array('CODE POSTAL', chrono_get_option('zipcode', 'customer')),
			array('PAYS', chrono_get_option('country', 'customer')),
			array('TELEPHONE', chrono_get_option('phone', 'customer')),
			array('POSTE COMPTABLE', (int)(chrono_get_option('zipcode', 'customer') / 1000) * 1000 + 999)
		));

		$pdf->SetFont('Arial', 'B', 10);
		$pdf->Ln();
		$pdf->Ln();
		$pdf->OxiCell('DETAIL DES ENVOIS', 0, 10);

		$pdf->InnerTable(array('NUM DE LT', 'POIDS (kg)', 'CODE PRODUIT', 'CODE POSTAL', 'PAYS', 'ASSURANCE', 'VILLE'),	array());

		$pdf->SetFont('Arial', 'B', 10);
		$pdf->Ln();
		$pdf->Ln();
		$pdf->OxiCell('RESUME', 0, 10);

		$sum_nat = 0;
		$sum_inter = 0;
		$sum_nat_weight = 0;
		$sum_inter_weight = 0;

		$pdf->InnerTable(array('DESTINATION', 'UNITE', 'POIDS TOTAL (KG)'), array(
			array('NATIONAL', $sum_nat, $sum_nat_weight),
			array('INTERNATIONAL', $sum_inter, $sum_inter_weight),
			array('TOTAL', $sum_inter + $sum_nat, $sum_nat_weight + $sum_inter_weight)
		), array(40, 20, 35));

		$pdf->Ln(15);
		$pdf->SetFont('Arial', 'B', 10);
		$pdf->OxiCell('Bien pris en charge ' . ($sum_inter + $sum_nat) . ' colis.', 0, 10);
		$pdf->Ln(25);

		$pdf->HeadTable(array(
			array('Signature du Client', 'Signature du Messager Chronopost')
		), array(95, 95), false);
		$pdf->Output('D', 'chronopost-bordereau_' . date('d-m-Y') . '.pdf');
	}
}

function shipment_admin_daily_docket_print_action()
{
	if (
		isset($_REQUEST['chronoaction']) && $_REQUEST['chronoaction'] === 'print-daily-docket'
		&& isset($_REQUEST['shipment_nonce'])
		&& wp_verify_nonce($_REQUEST['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (!isset($_REQUEST['order'])) {
			chronopost_error_no_order_selected();
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			header('Location: ' . $_SERVER['HTTP_REFERER']);

			return;
		}
		$order_ids = (array)$_REQUEST['order'];

		$pdf = new DailyDocketPDF();

		try {
			$recapLT = array();
			$sum_nat = 0;
			$sum_inter = 0;
			$sum_nat_weight = 0;
			$sum_inter_weight = 0;
			foreach ($order_ids as $order_id) {
				$_order = new WC_Order($order_id);
				$postcode = $_order->get_shipping_postcode();
				$country = $_order->get_shipping_country();
				$city = $_order->get_shipping_city();
				$shipment_labels_datas = chrono_get_shipment_datas($order_id);

				$first_package_loaded = false;

				if (is_array($shipment_labels_datas)) {
					foreach ($shipment_labels_datas as $ship_data) {
						$weight = 0;
						foreach ($ship_data['_parcels'] as $parcel) {
							$totalAdValorem = $parcel['skybillValue']['insuredValue'] / 100;

							// only take the first shipment weight as no multi-shipping on woocommerce by default
							if (!$first_package_loaded) {
								$weight = Chronopost_Package::getTotalWeight($_order->get_items());

								if (!isset($parcel['skybillValue'])) {
									$totalAdValorem = 0;

									$maxAmount = 20000;
									$adValoremAmount = (float)chrono_get_option('min_amount', 'insurance');
									$adValoremEnabled = chrono_get_option('enable', 'insurance') === 'yes' ? true : false;

									$order_insurance_enable = get_post_meta($_order->get_id(), '_insurance_enable', true);
									$insurance_amount = (float)get_post_meta($_order->get_id(), '_insurance_amount',
											true) * 100;


									if ($order_insurance_enable != '') {
										$adValoremEnabled = $order_insurance_enable === 'no' ? false : true;
									}

									if ($adValoremEnabled) {
										foreach ($_order->get_items() as $item) {
											$totalAdValorem += $item->get_total() + (float)$item->get_total_tax() * $item->get_quantity();
										}
										$totalAdValorem = $insurance_amount > 0 ? $insurance_amount : $totalAdValorem;
										$totalAdValorem = min($totalAdValorem, $maxAmount);
										if ($totalAdValorem < $adValoremAmount) {
											$totalAdValorem = 0;
										}
									}
								}
							}

							$shipping_method_code = chrono_get_product_code_by_id($ship_data['_shipping_method_id']);
							$shipping_method_instance = chrono_get_shipping_method_by_id($ship_data['_shipping_method_id']);
							$contract = $shipping_method_instance->getContractInfos($_order);

							// Dimensions
							if (isset($parcel['dimensions']) && isset($parcel['dimensions']['weight'])) {
								$weight = $parcel['dimensions']['weight'];
							}

							if ($country === 'FR') {
								$sum_nat_weight = $sum_nat_weight + $weight;
								$sum_nat++;
							} else {
								$sum_inter_weight = $sum_inter_weight + $weight;
								$sum_inter++;
							}

							$tmpRecapLT = array(
								'lt_num'       => $parcel['_skybill_number'],
								'compte'       => $contract['number'],
								'weight'       => $weight,
								'product_code' => $shipping_method_code,
								'postcode'     => $postcode,
								'country'      => $country,
								'assurance'    => $totalAdValorem,
								'city'         => utf8_decode($city)
							);
							$recapLT[] = $tmpRecapLT;
							$first_package_loaded = true;
						}
					}
				}
			}
		} catch (SoapFault $fault) {
			add_action('admin_notices', 'chronopost_default_generate_label_error');
		}

		$pdf->SetFont('Arial', 'B', 10);
		$pdf->AddPage();
		$pdf->Cell(150, 10, 'BORDEREAU RECAPITULATIF');
		$pdf->SetFont('Arial', '', 10);
		$pdf->Cell(100, 10, 'date : ' . date('d/m/Y'));
		$pdf->Ln();
		$pdf->Ln();

		$pdf->SetFont('Arial', 'B', 9);

		$pdf->OxiCell('EMETTEUR', 0, 10);
		$pdf->HeadTable(array(
			array('NOM', chrono_get_option('name', 'customer')),
			array('ADRESSE', chrono_get_option('address', 'customer')),
			array('ADRESSE (SUITE)', chrono_get_option('address2', 'customer')),
			array('VILLE', chrono_get_option('city', 'customer')),
			array('CODE POSTAL', chrono_get_option('zipcode', 'customer')),
			array('PAYS', chrono_get_option('country', 'customer')),
			array('TELEPHONE', chrono_get_option('phone', 'customer')),
			array('POSTE COMPTABLE', (int)(chrono_get_option('zipcode', 'customer') / 1000) * 1000 + 999)
		));

		$pdf->SetFont('Arial', 'B', 10);
		$pdf->Ln();
		$pdf->Ln();
		$pdf->OxiCell('DETAIL DES ENVOIS', 0, 10);

		$pdf->InnerTable(array(
			'NUM DE LT',
			'CONTRAT',
			'POIDS (kg)',
			'CODE PRODUIT',
			'CODE POSTAL',
			'PAYS',
			'ASSURANCE',
			'VILLE'
		), $recapLT);

		$pdf->SetFont('Arial', 'B', 8);
		$pdf->Ln();
		$pdf->Ln();
		$pdf->OxiCell('RESUME', 0, 10);

		$pdf->InnerTable(array('DESTINATION', 'UNITE', 'POIDS TOTAL (KG)'), array(
			array('NATIONAL', $sum_nat, $sum_nat_weight),
			array('INTERNATIONAL', $sum_inter, $sum_inter_weight),
			array('TOTAL', $sum_inter + $sum_nat, $sum_nat_weight + $sum_inter_weight)
		), array(40, 20, 35));

		$pdf->Ln(15);
		$pdf->SetFont('Arial', 'B', 10);
		$pdf->OxiCell('Bien pris en charge ' . ($sum_inter + $sum_nat) . ' colis.', 0, 10);
		$pdf->Ln(25);

		$pdf->HeadTable(array(
			array('Signature du Client', 'Signature du Messager Chronopost')
		), array(95, 95), false);
		$pdf->Output('D', 'chronopost-bordereau_' . date('d-m-Y') . '.pdf');
	}
}

function chronopost_set_email_content_type()
{
	return "text/html";
}

function shipment_admin_return_label_action()
{
	if (
		isset($_REQUEST['chronoaction']) && $_REQUEST['chronoaction'] === 'return-label'
		&& isset($_REQUEST['shipment_nonce'])
		&& wp_verify_nonce($_REQUEST['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (!isset($_REQUEST['order'])) {
			chronopost_error_no_order_selected();
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			header('Location: ' . $_SERVER['HTTP_REFERER']);

			return;
		}

		$order_id = $_REQUEST['order'];
		$skybill_id = $_REQUEST['skybill_id'] ?? get_post_meta($order_id, '_last_return_label')[0];

		$_order = new WC_Order($order_id);
		$order_shipping_method = $_order->get_shipping_methods();
		$shipping_method = reset($order_shipping_method);
		$shipping_method_id = $shipping_method->get_method_id();

		$ws = new Chronopost_Webservice();

		if (isset($_REQUEST['method']) && $_REQUEST['method'] === 'delete') {
			$returnSkybill = $_REQUEST['return_skybill_id'];

			return $ws->cancelSkybill($_order, $returnSkybill, $shipping_method_id);
		}

		if (0) {
			add_action('admin_notices', 'chronopost_error_return_label_already_created');
		} else {
			$returnSkybill = $ws->getEtiquetteRetour($_order);
			$pdf_base_64 = $returnSkybill->pdfEtiquette;
			if ($pdf_base_64) {
				$pdf_path = chrono_get_media_path() . 'chronopost-etiquette-retour-' . $skybill_id . '.pdf';
				chrono_add_return_labels($order_id, $skybill_id, $returnSkybill->skybillNumber);
				file_put_contents($pdf_path, $pdf_base_64);

				// If return from abroad (not France) : 3T
				// If return from France : 4T
				$productCode = '';
				if (chrono_get_option('country', 'return') === 'FR' && $_order->get_shipping_country() !== 'FR') {
					// todo: check if contract has 2shop for 2shopretour europe
					$productCode = '3T';
				} elseif (chrono_get_option('country', 'return') === 'FR' && $_order->get_shipping_country() === 'FR') {
					$productCode = "4T";
				}

				// If 4T
				if ($productCode !== '4T') {
					WC()->mailer()->emails['WC_Return_Label_Email']->trigger(array(
						'order_id'     => $order_id,
						'return_label' => $pdf_path,
						'skybill_id'   => $skybill_id
					));
				}
			}
		}
	}
}

function shipment_admin_print_action()
{
	unset($_COOKIE['ChronopostGenerateProcess']);
	setcookie("ChronopostGenerateProcess", 'processing', time() + 3600);

	if (
		isset($_REQUEST['chronoaction']) && $_REQUEST['chronoaction'] === 'print-label'
		&& isset($_REQUEST['shipment_nonce'])
		&& wp_verify_nonce($_REQUEST['shipment_nonce'], 'shipment_list_nonce')
	) {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		if (!isset($_REQUEST['order'])) {
			chronopost_error_no_order_selected();
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			header('Location: ' . $_SERVER['HTTP_REFERER']);
		}

		$order_ids = (array)$_REQUEST['order'];

		$shipment = new Chronopost_Shipment();
		$tmp_pdf_file = false;
		$pdf = new FPDI();

		try {
			foreach ($order_ids as $order_id) {
				$_order = new WC_Order($order_id);
				$order_shipping_method = $_order->get_shipping_methods();
				if ($order_shipping_method) {
					$shipping_method = reset($order_shipping_method);
				}

				$max_weight = $shipping_method->get_method_id() === 'chronorelais' ||
					$shipping_method->get_method_id() === 'chronorelaiseurope' ||
					$shipping_method->get_method_id() === 'chronotoshopeurope' ||
					$shipping_method->get_method_id() === 'chronorelaisdom' ? 20 : 30;
				$weight = Chronopost_Package::getTotalWeight($_order->get_items());
				$dynamic_weight = (array)json_decode(get_post_meta($order_id, '_parcels_dimensions', true));
				if (count($dynamic_weight) >= 1) {
					$weight = 0;
					foreach ($dynamic_weight as $dim) {
						$weight = max((float)$dim->weight, $weight);
					}
				}

				if ($weight > $max_weight) {
					$_SESSION['alert_msg'] = sprintf(
						__('Warning : the total weight of the packages exceeds %skg for the #%s order, you will need to generate at least one additional label.', 'chronopost'),
						$max_weight,
						$order_id
					);

					if (isset($_GET['action']) && $_GET['action'] === 'edit') {
						wp_redirect($_order->get_edit_order_url());

						return false;
					}

					if (isset($_GET['chronoaction']) && $_GET['chronoaction'] === 'print-label') {
						wp_redirect($_SERVER['PHP_SELF'] . "?page=chronopost-shipping");

						return false;
					}
				}

				$shipment_labels_datas = $shipment->saveAndCreateShipmentLabel($order_id);
				if (!$shipment_labels_datas || isset($shipment_labels_datas['error'])) {
					if ($shipment_labels_datas['error'] == 33) {
						$_SESSION['label_error'] = __('An error occured during the label creation. Please check if this contract can edit labels for this carrier.',
							'chronopost');
					} else {
						$_SESSION['label_error'] = __('An error occured during the label creation. Please check the customer datas or your Chronopost settings.',
							'chronopost');;
						unset($_COOKIE['ChronopostGenerateProcess']);
					}

					if (isset($_GET['action']) && $_GET['action'] === 'edit') {
						wp_redirect($_order->get_edit_order_url());

						return false;
					}

					if (isset($_GET['chronoaction']) && $_GET['chronoaction'] === 'print-label') {
						wp_redirect($_SERVER['PHP_SELF'] . "?page=chronopost-shipping");

						return false;
					}
				} else {
					$reservations_printed = array();
					foreach ($shipment_labels_datas as $ship_data) {
						foreach ($ship_data['_parcels'] as $parcel_data) {
							// Legacy code (versions before 1.1.0)
							if (isset($parcel_data['_pdf_buffer'])) {
								$tmp_pdf_file = chrono_get_media_path() . $parcel_data['_skybill_number'] . '.pdf';
								file_put_contents($tmp_pdf_file, base64_decode($parcel_data['_pdf_buffer']));
							} else if (isset($ship_data['_reservation_number']) && !empty($ship_data['_reservation_number'])) {
								if (in_array($ship_data['_reservation_number'], $reservations_printed)) {
									continue;
								}

								$pdf_file = $shipment->getShipmentLabel($ship_data['_reservation_number']);
								$tmp_pdf_file = $pdf_file;
								$reservations_printed[] = $ship_data['_reservation_number'];
							} else if (isset($parcel_data['_skybill_number']) && !empty($parcel_data['_skybill_number'])) {
								if (in_array($parcel_data['_skybill_number'], $reservations_printed)) {
									continue;
								}

								$pdf_file = $shipment->getShipmentLabel($parcel_data['_skybill_number']);
								$tmp_pdf_file = $pdf_file;
								$reservations_printed[] = $parcel_data['_skybill_number'];
							} else {
								add_action('admin_notices', 'chronopost_default_generate_label_error');

								return false;
							}

							$pageCount = $pdf->setSourceFile($tmp_pdf_file);

							for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
								$template_id = $pdf->importPage($pageNo);
								$size = $pdf->getTemplateSize($template_id);

								if ($size['width'] > $size['height']) {
									$pdf->AddPage('L', array($size['width'], $size['height']));
								} else {
									$pdf->AddPage('P', array($size['width'], $size['height']));
								}

								$pdf->useTemplate($template_id);
							}
						}
					}
				}
			}
		} catch (SoapFault $fault) {
			add_action('admin_notices', 'chronopost_default_generate_label_error');
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
		}

		//delete session cookie
		setcookie("ChronopostGenerateProcess", 'done', time() + 3600);

		if ($tmp_pdf_file) {
			setcookie("ChronopostGenerateProcess", 'done', time() + 3600);
			$pdf->Output('D', 'chronopost-labels_' . date('d-m-Y') . '.pdf');
		}
	}
}

add_action('admin_init', 'chronopost_shipment_init');
