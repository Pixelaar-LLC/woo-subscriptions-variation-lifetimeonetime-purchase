<?php
/**
 * Plugin Name: Woo Subscriptions Variation Lifetime/Onetime Purchase
 * Plugin URI: http://logicfire.in
 * Description: This plugin add the "Lifetime/Onetime Purchase" functionality to Woocommerce Subscriptions plugin .
 * Version: 1.2.2
 * Author: Logicfire
 * Author URI: https://profiles.wordpress.org/logicfire
 * Requires at least: 4.0.0
 * Tested up to: 5.0.3
 *
 * Text Domain: woosvl
 *
 * @package Woo_Subscriptions_Variation_Lifetime_Onetime_Purchase
 * @category Extension
 * @author Logicfire
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Add an option for lifetime/onetime purchase In the subscription intervals. (day, week, month, year, lifetime).
add_filter( 'woocommerce_subscription_periods', 'woo_lifetime_subscription', 10, 1 );
function woo_lifetime_subscription( $period ){
	$periodText = get_option( 'woo_lifetime_subscription_option_text' );
	if( !$periodText ) {
		$periodText = 'Lifetime';
	}
	$period['lifetime'] = sprintf( _nx( 'lifetime',  __( $periodText, 'woocommerce-subscriptions' ),  0, 'Subscription billing period.', 'woocommerce-subscriptions' ), 0 );
	return $period;
}

// Add subscription Lifetime length to subscription array.
add_filter( 'woocommerce_subscription_lengths', 'woo_lifetime_subscription_length', 10, 2 );
function woo_lifetime_subscription_length( $subscription_ranges, $subscription_period ) {
	$subscription_ranges['lifetime'] = array( 'Never expire' );
	return $subscription_ranges;
}

// Removes the Upgrade subscription switch/button from subscription. No upgrade if subscription is lifetime.
add_filter( 'woocommerce_subscriptions_switch_link','woo_restrict_upgrade_from_lifetime', 10, 4 );
function woo_restrict_upgrade_from_lifetime( $item_id, $item, $subscription ) {
	$billing_period = get_post_meta( $subscription->get_data()['order_id'], '_billing_period', true);
	if ( $billing_period == 'lifetime' ) {
		return '';
	} else {
		return $item_id;
	}
}

// Remove the next scheduled date for recurring payment after upgrade from recurring to lifetime subscription.
add_filter( 'woocommerce_payment_complete_order_status', 'woo_lifetime_date', 10, 3 );
function woo_lifetime_date( $status, $order_id, $order='' ) {
	$subscription_switch_data = get_post_meta( $order_id, '_subscription_switch_data', true );
	$subscription_switch = get_post_meta( $order_id, '_subscription_switch', true );
	if($subscription_switch) {
		$billing_period = $subscription_switch_data[$subscription_switch]['billing_schedule']['_billing_period'];
		if ( $billing_period === 'lifetime' ) {
			update_post_meta( $subscription_switch, '_schedule_next_payment', '' );
		}
	}
	return $status;
}

/**
 * Fix bug with WooCommerce Subscriptions "Lifetime" subscription length triggering a "next renewal date" of 1970 in the cart 
 * Because the product's "billing_interval" of 1 !== "$billing_length" of 0 then it calculates the next renewal instead of setting it to 0 in "woocommerce-subscriptions/includes/class-wc-subscriptions-product.php" function "get_first_renewal_payment_time"
 * This function filters the "first_renewal_payment_time" and if it's before a year ago it zeros it out
 */
add_filter( 'woocommerce_subscriptions_product_first_renewal_payment_time', 'woo_lifetime_product_first_renewal_payment_time', 10, 4 );
function woo_lifetime_product_first_renewal_payment_time( $first_renewal_timestamp, $product, $from_date_param, $timezone ) {
	$last_year_timestamp = mktime( 0, 0, 0, 1, 1, date('Y') - 1 );
	if ( $first_renewal_timestamp < $last_year_timestamp )
		$first_renewal_timestamp = 0;

	return $first_renewal_timestamp;
}

// PayPal Checkout parameters
add_filter( 'woocommerce_paypal_args', 'paypal_subscription_to_single_payment', 99, 2);
function paypal_subscription_to_single_payment($args, $order){
	foreach ( $order->get_items() as $item){
		$subscription_interval = get_post_meta( $item->get_data()['variation_id'], '_subscription_period', true );
		if( strcasecmp( $subscription_interval, 'lifetime' ) == 0 ) {
			// if($subscription_interval == 'lifetime'){
			$args['cmd'] = '_cart';
			$args['src'] = 0;
			$args['rm'] = 2;
			unset($args['a3']);
			unset($args['p3']);
			unset($args['t3']);
			unset($args['sra']);
		}
	}
	return $args;
}

// Add settings in Woocommerce -> Settings -> Subscriptions
add_filter( 'woocommerce_subscription_settings', 'woo_lifetime_text_phrase', 10, 1 );
function woo_lifetime_text_phrase( $settings ) {
	$settings[] =
		array(
			'name'     => __( 'Lifetime Option Text', 'woocommerce-subscriptions' ),
			'type'     => 'title',
			'desc'     => '',
			'id'       => 'woo_lifetime_option_text',
		);

	$settings[] = array(
		'name'        => __( 'Lifetime Subscription Option Text', 'woocommerce-subscriptions' ),
		'desc'        => __( '<br><br>Add text here to display on product page $x.xx /Lifetime to $x.xx /(Your custom text...)', 'woocommerce-subscriptions' ),
		'tip'         => '',
		'id'          => 'woo_lifetime_subscription_option_text',
		'css'         => 'min-width:150px;',
		'default'     => __( 'Lifetime', 'woocommerce-subscriptions' ),
		'type'        => 'text',
		'desc_tip'    => false,
		'placeholder' => __( 'Lifetime', 'woocommerce-subscriptions' ),
	);
	$settings[] = array( 'type' => 'sectionend', 'id' => 'woo_lifetime_option_text' );

	return $settings;
}