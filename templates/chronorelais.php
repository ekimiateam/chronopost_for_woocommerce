<div id="outer-container-method-chronorelay">
    <?php if( isset($pickup_relays) && is_array($pickup_relays) && count($pickup_relays)>0): ?>
        <div id="container-method-chronorelay" class="map-need-to-reload">
            <h3><?php echo __('Chronopost Pickup Relays', 'chronopost')?></h3>
            <?php
            $postcode = WC()->customer->get_shipping_postcode();
            $city = WC()->customer->get_shipping_city();

            $method_settings = chrono_get_method_settings($shipping_method_id);

            $_shippingMethodCode = $shipping_method_id;

            $_canChangePostcode = (isset($method_settings['can_change_postcode']) && $method_settings['can_change_postcode'] == 'yes') ? true: false;

            $_canChangePostcode = false;
			$_canChangePostcode = apply_filters( 'chrono_can_change_postcode', $_canChangePostcode );

            $_canShowGoogleMap = true;
            ?>

            <?php
            $chronomapOptions = array(
                'methodID' => $shipping_method_id,
                'pickupRelays' => $pickup_relays,
                'idMap' => 'chronomap',
                'pickupRelayIcon' => CHRONO_PLUGIN_URL . '/public/img/Picto_Chrono_Relais.png',
                'homeIcon' => CHRONO_PLUGIN_URL . '/public/img/home.png',
                'activateGmap' => true,
                'canModifyPostcode' => $_canChangePostcode
            );

            $jsonChronoMapOptions = htmlspecialchars(json_encode($chronomapOptions), ENT_QUOTES, 'UTF-8');


			$_canChangePostcode = false;

            ?>
            <p class="chronorelais-explain"><?php echo sprintf(__('Please select one of the <span class="relay-count">%s</span> pickup relays displayed below to serve as the delivery address.', 'chronopost'), count($pickup_relays))?></p>
            <?php if($_canChangePostcode): ?>
                <div class="mappostalcode">
                    <div class="postcode-input">
                        <input type="text" name="city" id="mapcity" value="<?php echo $city ?>" class="input-text" />
                        <input type="text" name="mappostalcode" id="mappostalcode" value="<?php echo $postcode ?>" class="input-text" />
                    </div>
                    <button id="mappostalcodebtn" class="button" type="button"><?php echo __('Update', 'chronopost'); ?></button>
                </div>
            <?php endif; ?>
            <div class="wrapper-methods-chronorelais">
                <div class="sp-methods-chronorelais">
                    <ul class="pickup-relays">
                        <?php foreach($pickup_relays as $key=>$chronorelais): ?>
                            <li class="form-row validate-required">
                                <input name="shipping_method_chronorelais" type="radio" value="<?php echo $chronorelais->identifiantChronopostPointA2PAS;?>" id="s_method_chronorelais_<?php echo $chronorelais->identifiantChronopostPointA2PAS;?>" class="radio">
                                <label for="s_method_chronorelais_<?php echo $chronorelais->identifiantChronopostPointA2PAS;?>"><?php echo $chronorelais->nomEnseigne.' - '.$chronorelais->adresse1.' - '.$chronorelais->codePostal.' - '.$chronorelais->localite;?></label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="button-center">
                    <button data-fancybox-close="" class="button button-primary" title="Close"><span class="wcicon-check"></span> <?php _e('Confirm relay pickup', 'chronopost'); ?></button>
                </div>
                <?php if($_canShowGoogleMap): ?>
                    <div class="chronorelaismap" data-chronomap-options='<?php echo $jsonChronoMapOptions; ?>'>
                        <div id="chronomap" class="chronomap"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
