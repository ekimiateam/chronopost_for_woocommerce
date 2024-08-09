<?php
/**
 * @var array $_creneaux Tableau des créneaux
 * @var string $shipping_method_id l'ID de la méthode Chronopost (chronoprecise)
 * @var WooCommerce $woocommerce
 */
global $woocommerce;
?>
<input type="hidden" name="chronopostprecise_creneaux_info" id="chronopostprecise_creneaux_info" value="" />
<div id="outer-container-method-chronoprecise">
    <!-- Permettra de récupérer toutes les infos aux format json du créneau choisi : date, code, ... -->
    <div id="container-method-chronoprecise">
    <?php
    if($_creneaux):
        $_chronopost_product = chrono_get_shipping_method_by_id($shipping_method_id);
        $_baseCost = 0;
        foreach ($woocommerce->cart->get_shipping_packages() as $package) {
            $packageCost = $_chronopost_product->get_shipping_rate($package);
            if (isset($packageCost['cost'])) {
	            $_baseCost+= $packageCost['cost'];
            }
        }

        /**
        * Code jours dans résultats WS :
        * 1 = Lundi
        * 7 = dimanche
        **/

        $_creneauxSort = array();
        $_distinctCreneauxHoraire = array();
        $_semaine = 0;

        if(!is_array($_creneaux)) $_creneaux = array($_creneaux);

        /* trie des creneaux par heures et date */
        foreach($_creneaux as $_creneau):

            $_creneau = (array)$_creneau;

            $_creneauHoraire = str_pad($_creneau['startHour'], 2, '0', STR_PAD_LEFT).'h';
            if($_creneau['startMinutes']) {
                $_creneauHoraire .= str_pad($_creneau['startMinutes'], 2, '0', STR_PAD_LEFT);
            }
            $_creneauHoraire .= ' - ';
            $_creneauHoraire .= str_pad($_creneau['endHour'], 2, '0', STR_PAD_LEFT).'h';
            if($_creneau['endMinutes']) {
                $_creneauHoraire .= str_pad($_creneau['endMinutes'], 2, '0', STR_PAD_LEFT);
            }

            if(!in_array($_creneauHoraire, $_distinctCreneauxHoraire)) {
                $_distinctCreneauxHoraire[] = $_creneauHoraire;
            }

            /* rangement des creneaux par jour */
            if(!isset($_creneauxSort[$_creneau['deliveryDate']])) {
                $_creneauxSort[$_creneau['deliveryDate']] = array();
            }
            $_creneauxSort[$_creneau['deliveryDate']][$_creneauHoraire] = $_creneau;

        endforeach;

        sort($_distinctCreneauxHoraire);

        /* on scinde le tableau tous les 7 éléments (7 jours) */
        $_creneauxSortByWeek = array_chunk($_creneauxSort, 7, true);

        ?>

            <div class="global-desktop" id="global-desktop">
                <div id="rdvCarousel">
                    <div id="rdvCarouselContent">
                        <?php for($i = 0; $i < count($_creneauxSortByWeek); $i++): ?>
                            <?php $_creneauxSort = $_creneauxSortByWeek[$i]; ?>
                            <section class="slide content" id="content<?php echo $i ?>">
                                <table class="date-time" id="thead" width="100%">
                                    <thead>
                                        <tr class="date-row" id="date-row">
                                            <th>&nbsp;</th>
                                            <?php foreach($_creneauxSort as $_day => $_creneaux): ?>
	                                            <?php $_dateTime = new DateTime($_day); ?>
                                                <th scope="col" id="th_<?php echo date('d-m-Y', strtotime($_day) + $_dateTime->getOffset()) ?>">
                                                    <?php echo date_i18n( "D <\s\p\a\\n>j</\s\p\a\\n> M", $_dateTime->getTimestamp() + $_dateTime->getOffset(), true ); ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($_distinctCreneauxHoraire as $_horaire): ?>
                                            <tr>
                                                <th class="time-cell" scope="row"><?php echo $_horaire; ?></th>
                                                <?php foreach($_creneauxSort as $_day => $_creneaux): ?>
                                                    <?php if(isset($_creneaux[$_horaire]) && $_creneaux[$_horaire]['status'] == 'O'): ?>
                                                        <?php $_creneaux[$_horaire]['meshCode'] = $meshCode;?>
                                                        <?php $_creneaux[$_horaire]['transactionID'] = $transactionID;?>
                                                        <td<?php echo $_creneaux[$_horaire]['incentiveFlag'] ? ' class="incentive-flag"': ''; ?>>
                                                            <label>
                                                                <span>
                                                                    <input type="radio" name="shipping_method_chronopostprecise" class="shipping_method_chronopostprecise" data-slotvaluejson='<?php echo json_encode($_creneaux[$_horaire],true) ?>' >
                                                                    <?php
                                                                    if(chrono_get_method_settings($shipping_method_id, 'cost_levels_show') == '1') {
                                                                        echo wc_price((float) $_baseCost + (float) $cost_levels[$_creneaux[$_horaire]['tariffLevel']]['price']);
                                                                    }
                                                                    ?>
                                                                </span>
                                                            </label>
                                                        </td>
                                                    <?php else: ?>
                                                        <td class="unavailable">&nbsp;</td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </section>
                        <?php endfor; ?>
                    </div>
                </div> <!-- End #rdvCarousel -->
                <div class="button-center">
                    <button data-fancybox-close="" class="button button-primary" title="Close"><span class="wcicon-check"></span> <?php _e('Confirm time slot', 'chronopost'); ?></button>
                </div>
            </div>
        <?php //else: ?>
            <!-- Mobile -->
            <div class="global-mobile" id="global-mobile">
                <header class="header">
                    <h1>S&eacute;lectionnez une date et un cr&eacute;neau horaire ci dessous&nbsp;:</h1>
                    <div class="scroll">
                        <table class="date-time">
                            <tr class="date-row" id="date-row">
                                <?php for($i = 0; $i < count($_creneauxSortByWeek); $i++): ?>
                                    <?php $_creneauxSort = $_creneauxSortByWeek[$i]; ?>
                                    <?php foreach($_creneauxSort as $_day => $_creneaux): ?>
                                        <th scope="col" id="th_mobile_<?php echo date('d-m-Y', strtotime($_day)) ?>">
                                            <?php $_dateTime = new DateTime($_day); ?>
                                            <?php echo date_i18n( "D <\s\p\a\\n>j</\s\p\a\\n> M", $_dateTime->getTimestamp() ); ?>
                                        </th>
                                    <?php endforeach; ?>
                                <?php endfor; ?>
                            </tr>
                        </table>
                    </div>
                </header>
                <section class="content" id="content">
                    <div class="scroll-v" id="scroll-v">
                        <div class="time-list" id="time-list">
                            <?php for($i = 0; $i < count($_creneauxSortByWeek); $i++): ?>
                                <?php $_creneauxSort = $_creneauxSortByWeek[$i]; ?>

                                <?php foreach($_creneauxSort as $_day => $_creneaux): ?>
                                    <ul id="ul_mobile_<?php echo date('d-m-Y', strtotime($_day)) ?>" style="display:none;">
                                        <?php foreach($_distinctCreneauxHoraire as $_horaire): ?>
                                            <?php if(isset($_creneaux[$_horaire]) && $_creneaux[$_horaire]['status'] == 'O'): ?>
                                                <?php $_creneaux[$_horaire]['meshCode'] = $meshCode;?>
                                                <?php $_creneaux[$_horaire]['transactionID'] = $transactionID;?>
                                                <li>
                                                    <label>
                                                        <span class="time-cell"><b><?php echo $_horaire; ?></b></span>
                                                        <span class="price-cell">
                                                            <input type="radio" name="shipping_method_chronopostprecise" class="shipping_method_chronopostprecise" data-slotvaluejson='<?php echo json_encode($_creneaux[$_horaire],true) ?>' >
                                                            <?php
                                                            if(chrono_get_method_settings($shipping_method_id, 'cost_levels_show') == '1') {
                                                                echo wc_price((float) $_baseCost + (float) $cost_levels[$_creneaux[$_horaire]['tariffLevel']]['price']);
                                                            }
                                                            ?>
                                                        </span>
                                                    </label>
                                                </li>
                                            <?php else: ?>
                                                <li class="unavailable">
                                                    <label>
                                                        <span class="time-cell"><?php echo $_horaire; ?></span>
                                                        <span class="price-cell">non disponible</span>
                                                    </label>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endforeach; ?>

                            <?php endfor; ?>
                        </div>
                </section>
                <div class="button-center">
                    <button data-fancybox-close="" class="button button-primary" title="Close"><span class="wcicon-check"></span> <?php _e('Confirm time slot', 'chronopost'); ?></button>
                </div>
            </div>
            <!-- End mobile -->

        <?php //endif; ?>

    <?php else: /* Aucun créneaux disponible */ ?>
        <div class="chronopostprecise_noresult" id="chronopostprecise_noresult"></div>
            <div class="popin" id="popin">
                <?php _e("It's not possible to use this service for your order yet, we are currently working to allow new cities to benefit from this new service.", 'chronopost') ?>
            </div>
        </script>
    <?php endif; ?>
    </div>
</div>
