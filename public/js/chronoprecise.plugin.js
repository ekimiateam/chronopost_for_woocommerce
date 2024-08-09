;(function($) {

	$.chronoprecise = function(el, options) {

		var defaults = {
			navHtml: '<header class="rdvCarouselheader" id="rdvCarouselheader"><nav><a href="#" class="carousel-control prev" rel="prev">%prev_week_txt%</a><a href="#" class="carousel-control next" rel="next">%next_week_txt%</a></nav></header>'
		}

		var plugin = this;

		plugin.settings = {}

		var init = function() {
			plugin.firstLoaded = false;
			plugin.selectRdv = false;
			plugin.settings = $.extend({}, defaults, options);
			plugin.el = el;
			plugin.currentScreen = 1;
			plugin.totalScreen = $(plugin.el).find('#rdvCarouselContent .slide').length;
			plugin.initNavigation();
			plugin.initEvents();
			plugin.initMobileEvents();
			plugin.firstLoaded = true;
		}

		plugin.reload = function() {
			plugin.el = el;
			plugin.firstLoaded = true;
			plugin.currentScreen = 1;
			plugin.totalScreen = $(plugin.el).find('#rdvCarouselContent .slide').length;
			plugin.initNavigation();
			plugin.resetEvents();
			plugin.initEvents();
			plugin.initMobileEvents();
		}

		plugin.initEvents = function() {
			$(document.body).on('updated_checkout', function () {
				plugin.current_shipping_method = $('input[name="shipping_method[0]"]:checked').val();
				plugin.isActive = $('.appointment-link').length ? true : false;

				if (plugin.getShipAddress() != plugin.currentAddress && !plugin.loadingRdv && plugin.isActive && ! plugin.selectRdv) {
					plugin.loadingRdv = true,
						shipping_method = $('.appointment-link').closest('li').find('input[name="shipping_method[0]"]').val();
					$.ajax({
						type: 'POST',
						dataType: 'json',
						url: Chronoprecise.ajaxurl,
						cache: false,
						data: {
							'action': 'load_chronoprecise_appointment',
							'method_id' : shipping_method,
							'chrono_nonce' : Chronoprecise.chrono_nonce
						}
					})
						.done(function(output) {
							$('#outer-container-method-chronoprecise').html($(output.data).filter('#outer-container-method-chronoprecise').html());
							plugin.loadingRdv = false;
							$('#outer-container-method-chronoprecise').data('chronoprecise').reload();
							$(document).trigger('chronoprecise:slot_loaded');
							plugin.getScreen(plugin.currentScreen);
						});
				}
				setTimeout(function () {
					plugin.selectRdv = false;
				}, 200);
			});

			if (plugin.firstLoaded == false) {
				$(document.body).on('click', '.appointment-link a', function() {
					if (typeof plugin.loadingRdv != 'undefined' && plugin.loadingRdv) {
						$(document).on('chronoprecise:slot_loaded', function() {
							plugin.showFancybox();
							$(document).off('chronoprecise:slot_loaded');
						});
					} else {
						plugin.showFancybox();
					}
				});
			}

			$(document.body).on('click', '.shipping_method_chronopostprecise', function() {
				plugin.selectRdv = true;
				plugin.selectChronopostSrdvSlot(this);
			});

			$(document.body).on('click', '#container-method-chronoprecise .carousel-control', function(event) {
				event.preventDefault();
				if (!$(this).is('.inactive')) {
					plugin.currentScreen = $(this).is('.next') ? plugin.currentScreen + 1 : plugin.currentScreen - 1;
					plugin.getScreen(plugin.currentScreen);
				}
			});
		}

		plugin.showFancybox = function() {
			$.fancybox.open({
				src  : '#container-method-chronoprecise',
				type : 'inline',
				// Clicked on the slide
				clickSlide : false,
				// Clicked on the background (backdrop) element
				touch: false
			});
		}

		plugin.resetEvents = function() {
			//$( document.body ).off( 'updated_checkout');
			$(plugin.el).off('click', '.shipping_method_chronopostprecise');
			$(plugin.el).off('click', '#rdvCarouselheader .carousel-control');
			$('#global-mobile').off('click', 'th');
		}


		plugin.initMobileEvents = function() {
			$('#global-mobile').on('click', 'th', function(event){
				event.preventDefault();
				var $this = $(this);

				$('#global-mobile th').removeClass('active');
				$this.addClass('active');

				$('#time-list ul').hide();
				var idUlHoraireDay = $this.attr('id').replace("th","ul");
				$('#'+idUlHoraireDay).show();
			});
			$('#global-mobile th').eq(0).click();
		}

		var dateLeadingZero = function(date){
			return ('0' + date).slice(-2);
		}

		plugin.initNavigation = function() {
			plugin.settings.navHtml = plugin.settings.navHtml.replace('%prev_week_txt%', Chronoprecise.prev_week_txt).replace('%next_week_txt%', Chronoprecise.next_week_txt);
			if (plugin.totalScreen > 1) {
				$(plugin.el).find('#rdvCarousel').before(plugin.settings.navHtml);
			}
			plugin.getScreen(plugin.currentScreen);
		}

		plugin.getScreen = function(nb) {

			$('#rdvCarouselContent .slide')
				.removeClass('slide-active')
				.eq(nb - 1)
				.addClass('slide-active');
			$('#rdvCarouselheader .carousel-control')
				.removeClass('inactive')
			if (nb == 1) {
				$('#rdvCarouselheader .carousel-control.prev')
					.addClass('inactive');
			} else if (nb == plugin.totalScreen) {
				$('#rdvCarouselheader .carousel-control.next')
					.addClass('inactive');
			}
		}

		plugin.selectChronopostSrdvSlot = function(element) {

			$('#container-method-chronoprecise').addClass('slot-selected');

			var slotValue = $(element).data('slotvaluejson');

			var d = new Date(slotValue.deliveryDate);

			var thID = '#th_'+dateLeadingZero(d.getDate());
			thID += '-'+dateLeadingZero((d.getMonth() + 1));
			thID += '-'+d.getFullYear();

			$("#rdvCarousel .active").removeClass("active");

			var td = $(element).closest("td");
			var tr = td.closest("tr");
			var th = tr.find('th');

			th.addClass("active");
			$(thID).addClass("active");


			$("#global-mobile ul li.active").removeClass("active");

			var li = $(element).closest("li");
			li.addClass("active");

			$('#chronopostprecise_creneaux_info').val(JSON.stringify(slotValue)).trigger('chronoprecise:appointment_changed');
			setTimeout(function() {
				jQuery( document.body ).trigger( 'update_checkout', { update_shipping_method: true });
			}, 5);
		}

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
		}

		init();
	}

	$.fn.chronoprecise = function (options) {
		return this.each(function() {
			if (undefined == $(this).data('chronoprecise')) {
				var plugin = new $.chronoprecise(this, options);
				$(this).data('chronoprecise', plugin);
				if(options && options.openRdv){
					plugin.showFancybox();
				}

			}
		});
	}

})(jQuery);
