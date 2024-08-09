var console = console || {
    "log": function(stuff) {}
};

(function( $ ) {
    'use strict';

    if (typeof $.fancybox != 'undefined') {
        $.fancybox.defaults.touch = false;
    }

    $.chronomap = function(element, options) {

        var defaults = {
            idMap: 'map',
            mapOptions: {
                zoom: 8,
                center: L.latLng(47.37285025362682, 2.4172996312499784),
                panControl: false,
                rotateControl: true,
                scaleControl: true,
                zoomControl: true
            },
            methodID: 'chronorelais',
            pickupRelayIcon: false,
            homeIcon: false,
            pickupRelays: [],
            postcodeInputSelector: '#mappostalcode',
            cityInputSelector: '#mapcity',
            postcodeButtonSelector: '#mappostalcodebtn',
            activateGmap: true,
            canModifyPostcode: true,
            pickupRelayListContainerSelector: '.pickup-relays',
            pickupRelayListHtml: '<li class="form-row validate-required"><input name="shipping_method_chronorelais" type="radio" value="%identifiantChronopostPointA2PAS%" id="s_method_chronorelais_%identifiantChronopostPointA2PAS%" class="radio"><label for="s_method_chronorelais_%identifiantChronopostPointA2PAS%">%nomEnseigne% - %adresse1% - %codePostal% - %localite%</label></li>',
            noPickupRelayHtml: '<a href="javascript:;">%no_pickup_relay%</a>',
            currentPickupRelayHtml: '<span class="pickup-relay-selected">%pickup_relay_name%</span> <a href="javascript:;">%pickup_relay_edit_text%</a>'
        };

        var plugin = this;

        plugin.settings = {}

        var $element = $(element);

        plugin.init = function() {
            plugin.settings = $.extend({}, defaults, options);
            let selectedRelaisMethod = jQuery('#order_review input[value^="chronorelais"]:checked').val();
            if (!selectedRelaisMethod) {
              selectedRelaisMethod = jQuery('#order_review input[value^="chronotoshop"]:checked').val();
            }
            plugin.current_chronorelais_method = plugin.getCurrentChronorelaisMethod();

            plugin.currentAddress = plugin.getShipAddress();

            $('#container-method-chronorelay').addClass('map-need-to-reload');

            if ($('.chronorelaismap').data('chronomap-options')) {
                plugin.settings = $.extend({}, plugin.settings, $('.chronorelaismap').data('chronomap-options'));
            }
            //plugin.loadMap();
            plugin.initEvents();
        };

        plugin.getCurrentChronorelaisMethod = function() {
          let selectedRelaisMethod = jQuery('#order_review input[value^="chronorelais"]:checked').val();
          if (!selectedRelaisMethod) {
            selectedRelaisMethod = jQuery('#order_review input[value^="chronotoshop"]:checked').val();
          }
          return selectedRelaisMethod;
        }

        plugin.initEvents = function() {

            $(document.body).on('click', '.pickup-relay-link a', function() {
                plugin.openMap();
            });

            $( document.body ).on( 'updated_checkout', function() {
                let shippingMethodHasChanged = false;
                let domCheckedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
                if (plugin.current_shipping_method !== domCheckedShippingMethod) {
                	shippingMethodHasChanged = true;
                }
                plugin.current_postcode = $('#mappostalcode').val();
                plugin.current_city = $('#mapcity').val();
                plugin.current_shipping_method = domCheckedShippingMethod;
                if (plugin.getShipAddress() != plugin.currentAddress || shippingMethodHasChanged) {
                    $('#container-method-chronorelay .wrapper-methods-chronorelais .pickup-relays').html('');
                    plugin.current_chronorelais_method = plugin.getCurrentChronorelaisMethod();
                    plugin.currentAddress = plugin.getShipAddress();
                    $('#container-method-chronorelay').addClass('map-need-to-reload');
                }
            });
        };

        plugin.openMap = function() {
            $.fancybox.open({
                src  : '#container-method-chronorelay',
                type : 'inline',
                // Clicked on the slide
                clickSlide : false,
                // Clicked on the background (backdrop) element
                touch: false,
                opts : {
                    afterShow : function( instance, current ) {
                        if ($('#container-method-chronorelay').hasClass('map-need-to-reload')) {
                            let shipToDifferentAddress = $('#ship-to-different-address-checkbox');
                            plugin.loadMap();
                            if (shipToDifferentAddress.is(':checked')) {
                              plugin.updatePickupRelay($( 'input#shipping_postcode' ).val(), $( 'input#shipping_city' ).val());
                            } else {
                              plugin.updatePickupRelay($( 'input#billing_postcode' ).val(), $( 'input#billing_city' ).val());
                            }

                            $('.pickup-relay-link').html(
                                plugin.settings.noPickupRelayHtml
                                    .replace('%no_pickup_relay%', Chronomap.no_pickup_relay)
                            );
                            $('#container-method-chronorelay').removeClass('map-need-to-reload')
                        }
                        plugin.map.invalidateSize();
                        //plugin.map.fitBounds(plugin.marker_group.getBounds());
                        setTimeout(function() {
                            plugin.map.invalidateSize();
                            //plugin.map.fitBounds(plugin.marker_group.getBounds());
                        }, 100);
                    }
                }
            });
        };

        plugin.loadMap = function() {
            if ($element.find('#'+plugin.settings.idMap).length) {

            		// on ré-initialise la map
								var leaflet_map = L.DomUtil.get(plugin.settings.idMap);
								if(leaflet_map != null){
										leaflet_map._leaflet_id = null;
								}

                plugin.markers = [];
                plugin.relayIcon = plugin.settings.pickupRelayIcon;
                plugin.map = L.map(plugin.settings.idMap).setView([47.37285025362682, 2.4172996312499784], 12);
                plugin.map.bounds = new L.latLngBounds();

                plugin.marker_group = L.featureGroup();
                plugin.marker_group.addTo(plugin.map);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(plugin.map);


                if (plugin.settings.pickupRelays.length > 0) {
                    plugin.loadPickupRelays();
                }
            }
            plugin.loadPostCodeForm();
        };

        plugin.loadPickupRelays = function() {
            var prAddress = '',
                curPr,
                idPR,
                htmlPrList = '',
                tmpHtmlPrList = '',
                timeoutSearch,
                prFieldsToRetrieve = ['identifiantChronopostPointA2PAS', 'adresse1', 'codePostal', 'localite', 'nomEnseigne','latitude','longitude'];

            clearTimeout(timeoutSearch);

            for (var i = 0; i < plugin.settings.pickupRelays.length; i++) {
                curPr = plugin.settings.pickupRelays[i];
                prAddress = curPr.adresse1;
                idPR = curPr.identifiantChronopostPointA2PAS;
                if (curPr.adresse2) {
                    prAddress += ' ' + curPr.adresse2;
                }
                if (curPr.adresse3) {
                    prAddress += ' ' + curPr.adresse3;
                }
                prAddress += ' ' + curPr.codePostal;
                prAddress += ' ' + curPr.localite;

                var p = [curPr.latitude, curPr.longitude];
                plugin.settings.pickupRelays[i].location = p;
                var marker = plugin.addPickupRelayMarker(plugin.settings.pickupRelays[i]);
                plugin.settings.pickupRelays[i].marker = marker;
                plugin.markers.push(marker);
                //plugin.map.bounds.extend(p);
                //plugin.map.fitBounds(plugin.map.bounds);
                //plugin.map.setView(plugin.map.bounds.getCenter(),12);

                tmpHtmlPrList = plugin.settings.pickupRelayListHtml;

                for (var j = 0; j < prFieldsToRetrieve.length; j++) {
                    tmpHtmlPrList = tmpHtmlPrList.replace(new RegExp('%'+prFieldsToRetrieve[j]+'%', 'g'), curPr[prFieldsToRetrieve[j]]);
                }

                htmlPrList += tmpHtmlPrList;

								console.log($(plugin.settings.pickupRelayListContainerSelector));
								console.log(plugin.settings.pickupRelays.length);
                $(plugin.settings.pickupRelayListContainerSelector).html(htmlPrList);
								$('#container-method-chronorelay .relay-count').text(plugin.settings.pickupRelays.length);

            }
        };

        plugin.searchForIdentifier = function(nameKey, myArray){
            for (var i=0; i < myArray.length; i++) {
                if (myArray[i].identifiantChronopostPointA2PAS === nameKey) {
                    return myArray[i];
                }
            }
        };

        plugin.loadPostCodeForm = function() {

            $(document).on('click', plugin.settings.postcodeButtonSelector, function(event) {
                $element.trigger('chronomap:postcode_changed');
                event.preventDefault();
            });
            /*
            $(document).on('keydown', plugin.settings.postcodeInputSelector, function(event) {
                if (13 === event.keyCode) {
                    $element.trigger('chronomap:postcode_changed');
                    event.preventDefault();
                }
            });

             */

            $(document).on('change', '[name^="shipping_method_chronorelais"]', function(event) {
                $element.trigger('chronomap:pickuprelay_change');
                if (typeof plugin.searchForIdentifier($(this).val(), plugin.settings.pickupRelays).marker != 'undefined') {
                    plugin.searchForIdentifier($(this).val(), plugin.settings.pickupRelays).marker.openPopup();
                }
                event.preventDefault();
            });

            $element.on('chronomap:pickuprelay_change', function() {
                plugin.currentPickupRelayName = $('input[name="shipping_method_chronorelais"]:checked').next('label').text();
                $('.sp-methods-chronorelais').addClass('pickup-relay-selected');
                $('.pickup-relay-link').html(
                    plugin.settings.currentPickupRelayHtml
                        .replace('%pickup_relay_name%', plugin.currentPickupRelayName)
                        .replace('%pickup_relay_edit_text%', Chronomap.pickup_relay_edit_text)
                );
            });

            $element.on('chronomap:postcode_changed', function() {
                plugin.current_postcode = $('#mappostalcode').val();
                plugin.current_city = $('#mapcity').val();
                plugin.updatePickupRelay(plugin.current_postcode, plugin.current_city);

                $('.pickup-relay-link').html(
                    plugin.settings.noPickupRelayHtml
                        .replace('%no_pickup_relay%', Chronomap.no_pickup_relay)
                );
            });
        };

        plugin.updatePickupRelay = function(postcode, city = '') {
            plugin.resetAllMarkers();
            postcode = (typeof postcode == undefined) ? false : postcode;
            $('.wrapper-methods-chronorelais').addClass('chronopost-loading');
            $('.pickup-relay-link').hide();
            $('<div class="chronomap-text-loading">'+Chronomap.loading_txt+'</div>').insertAfter('.pickup-relay-link');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: Chronomap.ajaxurl,
                cache: true,
                data: {
                    'action': 'chronopost_pickup_relays',
                    'method_id' : plugin.current_chronorelais_method,
                    'postcode':   postcode,
                    'city': city,
                    'chrono_nonce' : Chronomap.chrono_nonce
                }
            }).done(function(output) {
                if (output.status == 'success' && output.data !== null && output.data.length > 0) {
                    plugin.settings.pickupRelays = output.data;
                    plugin.loadPickupRelays();
                    //plugin.map.setCenter(plugin.mapCenter, 11);
                }
                $('input.shipping_method[value='+ plugin.current_chronorelais_method +']').nextAll('.pickup-relay-link').show()
                $('.chronomap-text-loading').remove();
                $('.wrapper-methods-chronorelais').removeClass('chronopost-loading');
            });
        };

        plugin.resetAllMarkers = function() {
            plugin.marker_group.clearLayers();
            plugin.markers = [];
            plugin.map.bounds = L.LatLngBounds();
            plugin.settings.pickupRelays = [];
        };

        plugin.addPickupRelayMarker = function(pr) {
					if (typeof pr.latitude !== 'undefined') {
						var relaypoint_id = pr.identifiantChronopostPointA2PAS;

						var marker = L.marker([pr.latitude, pr.longitude], {
							icon: L.icon({
								iconUrl: plugin.relayIcon,
								iconSize: [45, 30]
							})
						});
						plugin.map.addLayer(marker);
						var popup =
							'<div class="marker-wrapper"><div class="info-section"><div class="marker-title">' + Chronomap.infos + '</div>' + plugin.getMarkerInfoContent(pr) + '</div><div class="hours-section"><div class="marker-title">' + Chronomap.opening_hours + '</div><div>' + plugin.getHoursTab(pr, true) + '</div></div></div>';

						// Save marker and add it to leaflet marker group
						plugin.markers[relaypoint_id] = marker;
						marker.addTo(plugin.marker_group).bindPopup(popup).on('click', function () {
							$('#s_method_chronorelais_' + relaypoint_id).prop('checked', 'checked');
							$element.trigger('chronomap:pickuprelay_change');
							$('.sp-methods-chronorelais').addClass('pickup-relay-selected');
						});
						plugin.map.fitBounds(plugin.marker_group.getBounds()) // Fit map with marker_group bounds

						return marker;
					}
        };

        plugin.getActionsForm = function(pr) {
            return '';
        };

        plugin.btQueryString = function(anArray, needEscape)
        {
            var rs = "" ;
            for (var key in anArray)
            {
                if (needEscape == true)
                {
                    if(anArray[key]) {
                        if (rs != "")
                            rs += "&"
                        rs += key +"=" + escape(anArray[key]) ;
                    }
                }
                else
                {
                    if(anArray[key]) {
                        if (rs != "")
                            rs += "_-_"
                        rs += key +"=" + anArray[key] ;
                    }
                }
            }
            return rs;
        };

        plugin.getMarkerInfoContent = function(pr){
            var icoPath = plugin.settings.pickupRelayIcon;
            var content="<div class=\"sw-map-adresse-wrp\" style=\"background-image: url("+ icoPath +"); background-repeat: no-repeat;padding-left:50px;\">"
                + "<div class=\"pickup-relay-title\">"+pr.nomEnseigne+"</div>"
                + "<div class=\"sw-map-adresse\">";
            content += pr.adresse1;
            if (pr.adresse2) {
                content += ' ' + pr.adresse2;
            }
            if (pr.adresse3) {
                content += ' ' + pr.adresse3;
            }
            content += ' ' + pr.codePostal + " " + pr.localite
                + "</div></div>";
            return content;
        };

        plugin.getHoursTab = function(pr, highlight)
        {
            var userAgent = navigator.userAgent.toLowerCase();
            var msie = /msie/.test( userAgent ) && !/opera/.test( userAgent );

            var rs = "" ;
            rs =  "<table class=\"sw-table\"";
            if(msie) {
                rs +=  " style=\"width:auto;\"";
            }
            rs +=  ">"
                + "<tr><td>"+ Chronomap.day_mon +"</td>"+ plugin.parseHours(pr.horairesOuvertureLundi, 1, highlight) +"</tr>"
                + "<tr><td>"+ Chronomap.day_tue +"</td>"+ plugin.parseHours(pr.horairesOuvertureMardi, 2, highlight) +"</tr>"
                + "<tr><td>"+ Chronomap.day_wed +"</td>"+ plugin.parseHours(pr.horairesOuvertureMercredi, 3, highlight) +"</tr>"
                + "<tr><td>"+ Chronomap.day_thu +"</td>"+ plugin.parseHours(pr.horairesOuvertureJeudi, 4, highlight) +"</tr>"
                + "<tr><td>"+ Chronomap.day_fri +"</td>"+ plugin.parseHours(pr.horairesOuvertureVendredi, 5, highlight) +"</tr>"
                + "<tr><td>"+ Chronomap.day_sat +"</td>"+ plugin.parseHours(pr.horairesOuvertureSamedi, 6, highlight) +"</tr>"
                + "<tr><td>"+ Chronomap.day_sun +"</td>"+ plugin.parseHours(pr.horairesOuvertureDimanche, 0, highlight) +"</tr>"
                + "</table>" ;
            return rs ;
        };

        plugin.parseHours = function(value, day, highlight)
        {
            var rs = "" ;

            var now = new Date() ;
            var today = now.getDay() ;	// number of day
            var attributedCell = "" ;
            var reg = new RegExp(" ", "g");

            var hours = value.split(reg) ;

            for (var i=0; i < hours.length; i++)
            {
                // first define the attributes for the current cell
                /* Aucun jour n'est mis en exergue car on ne sait pas quel sera le jour de livraison
                if ( highlight == true && day == today)
                {
                    attributedCell = "style=\"color:red;\"" ;
                }
                else
                {
            */
                attributedCell = "" ;
                /*
                }
            */

                // so, re-format time
                if (hours[i] == "00:00-00:00")
                {
                    hours[i] = "<td "+attributedCell+">"+ Chronomap.closed +"</td>" ;
                }
                else
                {
                    hours[i] = "<td "+attributedCell+">"+hours[i]+"</td>" ;
                }

                // yeah, concatenates result to the returned value
                rs += hours[i] ;
            }

            return rs ;
        };

        plugin.getShipAddress = function() {
            var address_1			 = $( 'input#billing_address_1' ).val(),
                address_2		 = $( 'input#billing_address_2' ).val(),
                postcode		 = $( 'input#billing_postcode' ).val(),
                city		 = $( 'input#billing_city' ).val(),
                country      = $( '#billing_country' ).val();

            if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
                address_1		 = $( 'input#shipping_address_1' ).val();
                address_2		 = $( 'input#shipping_address_2' ).val();
                postcode		 = $( 'input#shipping_postcode' ).val();
                city		 = $( 'input#shipping_city' ).val();
                country      = $( '#shipping_country' ).val();
            }

            var ship_address = address_1;
            if (address_2 != '') {
                ship_address += ' ' + address_2;
            }

            return ship_address + ' ' + postcode + ' ' + city + ' ' + country;
        };

        plugin.init();

    };

    $.chronomap.printPage = function(href) {
        var  fen=open("","Impression");
        fen.focus();
        if(href) {
            fen.location.href = href;
        }
    };

    $.fn.chronomap = function(options) {

        return this.each(function() {
            if (undefined == $(this).data('chronomap')) {
                var plugin = new $.chronomap(this, options);
                $(this).data('chronomap', plugin);
                if(options && options.openMap){
                    plugin.openMap();
                }
            }
        });

    };

})(jQuery);
