<?php

/*
	Plugin Name:  Just1StepAhead for WooCommerce
	Description:  Send SMS notifications based on WooCommerce Events
	Version:      0.0.1
	Author:       Just 1 Step Ahead
	Author URI:   https://www.just1stepahead.com
	License:      GPLv2
	License URI:  https://www.gnu.org/licenses/gpl-2.0.html
	Text Domain:  jisa-woocommerce
*/
namespace J1sa\WoocommercePlugin;

use RuntimeException;
use Exception;
use WP_Error;

if ( !defined( 'ABSPATH' ) ) {
    exit("Not in WP");
}

// define( 'WP_DEBUG', true );

class Configuration_Page {

	function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array ( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array ( $this, 'register_styles' ) );

		add_action('admin_notices', array ( $this, 'admin_notice' ));
		add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array($this, 'add_action_links') ); // https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
		
		$this->client = new J1saApiClient(get_option('j1sa-username'), get_option('j1sa-password'));
	}
	
	function add_action_links ( $links ) {
		// var_dump($links);exit();
		$links[] = '<a href="' . admin_url( 'options-general.php?page=j1sa-woocommerce' ) . '">Settings</a>';
		return $links;
	}


	function admin_menu() {
		add_options_page(
			'J1SA for WooCommerce',
			'J1SA for WooCommerce',
			'manage_options',
			'j1sa-woocommerce',
			array(
				$this,
				'admin_page'
			)
		);
	}
	
	function admin_notice() {
		global $pagenow;
		
		if (get_option('j1sa-sender-id') && get_option('j1sa-sender-display') && get_option('j1sa-recipient') && get_option('j1sa-username') && get_option('j1sa-password')) {
			if ($pagenow === 'index.php' || ($pagenow === 'options-general.php' && 'j1sa-woocommerce' === $_GET['page'])) {
				try {
					$balance = $this->client->get_balance();
				
					echo <<<HEREDOC
					<div class="notice notice-info is-dismissible">
						 <p>You have {$balance} credits left in your Just1StepAhead account. Top up by clicking <a href="https://www.just1stepahead.com/products/" target="_blank">here</a>.</p>
					 </div>
HEREDOC;
				} catch (Exception $e) {
					error_log('Error while loading J1SA balance: ' . print_r($e, true));
				}
			}

		} else {
			$url = get_admin_url(null, 'options-general.php?page=j1sa-woocommerce');
			echo <<<HEREDOC
			<div class="notice notice-warning is-dismissible">
				 <p>Your J1SA Plugin is not yet configured. <a href="{$url}">Configure now</a>.</p>
			 </div>
HEREDOC;
		}
	}

	function admin_page() {
		echo '<div class="wrap"><h1>J1SA Integration Settings</h1><form method="post" action="options.php">';
		echo <<<HEREDOC
		<style>
			input.j1sa, textarea.j1sa {
				width: 40vw;
			}
		</style>
		
HEREDOC;
		settings_fields( 'j1sa-all' );
		do_settings_sections('j1sa-woocommerce');
		
		/*$existing = array(
			'username' => get_option('j1sa-username'),
		);
		echo <<<HEREDOC
			<h3>General Settings</h3>
			<table>
			<tr valign="top">
			<th scope="row"><label for="j1sa-username">Label</label></th>
			<td><input type="text" id="j1sa-username" name="j1sa-username" value="{$existing['username']}" /></td>
			</tr>
			</table>
HEREDOC;*/
		submit_button();
		echo '</form></div>';
	}
	
	static function populate_existing_array($option_name, $defaults) {
		return @array_merge(array_fill_keys(array_keys($defaults), ''), @array_map(function ($val) { // may throw if $val is empty
				return $val ? 'checked' : '';
			}, get_option($option_name, $defaults)));
	}
	
	function register_styles() {
		wp_enqueue_style('j1sa-woocommerce', plugins_url('style.css',__FILE__ ));
		wp_enqueue_script('j1sa-woocommerce', plugins_url('admin.js',__FILE__ ));
	}
	
	function register_settings() {
		register_setting( 'j1sa-all', 'j1sa-username', 'string' );
		register_setting( 'j1sa-all', 'j1sa-password', 'string' );
		register_setting( 'j1sa-all', 'j1sa-sender-id', 'string' );
		register_setting( 'j1sa-all', 'j1sa-sender-display', 'string' );
		register_setting( 'j1sa-all', 'j1sa-recipient', 'string' );
		
		add_settings_section('j1sa-general', 'General Settings', function(){
			echo '<p>If you don\'t have an account with us yet, create one at <a href="https://www.just1stepahead.com/?referred=true&referrer=264&code=plugin" target="_blank">just1stepahead.com</a> and then return here.</p>';
		}, 'j1sa-woocommerce');
		add_settings_field('j1sa-username', 'Email', function() {
			$existing = esc_attr(get_option('j1sa-username'));
			echo "<input id='j1sa-username' name='j1sa-username' value='{$existing}' class='j1sa' required>";
		}, 'j1sa-woocommerce', 'j1sa-general');

		add_settings_field('j1sa-password', 'Password', function() {
			$existing = esc_attr(get_option('j1sa-password'));
			echo "<input id='j1sa-password' name='j1sa-password' value='{$existing}' type=password class='j1sa' required>";
		}, 'j1sa-woocommerce', 'j1sa-general');
		
		add_settings_field('j1sa-sync', 'Sender/Receiver', function() {
			$existing_id = esc_attr(get_option('j1sa-sender-id'));
			$existing_display = esc_attr(get_option('j1sa-sender-display'));
			$existing_recipient = esc_attr(get_option('j1sa-recipient'));
			echo "<input id='j1sa-sender-id' name='j1sa-sender-id' value='{$existing_id}' type=hidden>";
			echo "<input id='j1sa-recipient' name='j1sa-recipient' value='{$existing_recipient}' type=hidden>";
			echo "<input id='j1sa-sender-display' name='j1sa-sender-display' value='{$existing_display}' placeholder='Not Selected' class='j1sa-readonly-field' readonly>";
			echo "<input id='j1sa-email2' name='email' type=hidden><input id='j1sa-pwd2' name='pwd' type=hidden>"; // just a hack for J1SA integration
			echo "<input name='submitted' value='1' type=hidden>"; // another hack for J1SA integration
			echo '<input type="submit" id="pick-mobile-number" class="button" value="Select Mobile Number">';
			echo '<a href="https://www.just1stepahead.com/products/" target="_blank" class="button" value="Buy More Credits" style="margin-left: 10px;">Buy SMS Credits</a>';
			echo <<<HTMLHTMLHTML
	<script type="text/javascript">
		
	</script>
HTMLHTMLHTML;
		}, 'j1sa-woocommerce', 'j1sa-general');
		
		/* add_settings_field('j1sa-sender', 'Sender', function() use ($client) {
			$existing = (integer) get_option('j1sa-sender');
			echo "<select name='j1sa-sender'><option value='-1'></option>";
			foreach ($client->getPhoneNumbers() as $phoneId => $phoneNumber) {
				var_dump($phoneId);
				echo "<option value='{$phoneNumber['ID']}'";
				if ($phoneNumber['mob_val'] === 0) {
					echo " disabled";
				}
				if ($phoneId === $existing) {
					echo " selected";
				}
				echo ">{$phoneNumber['senderName']} ({$phoneNumber['mob']})";
				if ($phoneNumber['mob_val'] === 0) {
					echo " (Unverified)";
				}
				echo "</option>";
			}
			echo "</select>";
			echo '<p class="description">The sender list is updated <strong>after</strong> you click on <i>save changes</i> as you update your login credentials. Do not see your phone number here? Get it added at <a href="https://www.just1stepahead.com/sms/sms.php" target=_blank>the J1SA website</a>.</p>';
		}, 'j1sa-woocommerce', 'j1sa-general');
		
		add_settings_field('j1sa-recipient', 'Recipient', function() {
			$existing = esc_attr(get_option('j1sa-recipient'));
			echo "<input id='j1sa-recipient' name='j1sa-recipient' value='{$existing}' class='j1sa' pattern='00\\d+' required>";
			echo '<p class="description">Please enter it in the format: 00 (country code) (phone number)</p>';
		}, 'j1sa-woocommerce', 'j1sa-general'); */
				
		add_settings_section('j1sa-wcs', 'WooCommerce-Specific Settings', function(){
			echo '<p>Unchecking all the checkboxes in a section deactivates notification for that type of event.</p>';
		}, 'j1sa-woocommerce');
		
		foreach (array(
			array(
				'type_id' => 'new-order',
				'name' => 'New Order',
			),
			array(
				'type_id' => 'failed-order',
				'name' => 'Failed Order',
			),
			array(
				'type_id' => 'cancelled-order',
				'name' => 'Cancelled Order',
			),
			array(
				'type_id' => 'client',
				'name' => 'Clients on Status Change',
			),
			) as $type) {
				register_setting( 'j1sa-all', 'j1sa-wcs-template-'.$type['type_id']);
				add_settings_field('j1sa-wcs-template-'.$type['type_id'], 'Notifications for '.$type['name'], function() use ($type) {
					$existing = $this->populate_existing_array('j1sa-wcs-template-'.$type['type_id'], [
							'name' => '1',
							'email' => '1',
							'mobile' => '1',
							'products' => '1',
							'products_quantity' => '1',
							'total' => '1',
							'status' => '1',
							'address' => '1',
							'date' => '1',
							'order_id' => '1'
					]);
					
					echo '<fieldset>';
					
					echo <<<HEREDOC
						<fieldset>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[order_id]" value="1" {$existing['order_id']}> Order ID</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[total]" value="1" {$existing['total']}> Total</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[name]" value="1" {$existing['name']}> Name</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[email]" value="1" {$existing['email']}> Email</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[mobile]" value="1" {$existing['mobile']}> Mobile</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[address]" value="1" {$existing['address']}> Address</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[date]" value="1" {$existing['date']}> Date</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[products]" value="1" {$existing['products']}> Products</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[products_quantity]" value="1" {$existing['products_quantity']}> Product Quantity (Requires <i>products</i> to be selected)</label><br>
						<label><input type="checkbox" name="j1sa-wcs-template-{$type['type_id']}[status]" value="1" {$existing['status']}> Status</label><br>
HEREDOC;
					echo '</fieldset>';
				}, 'j1sa-woocommerce', 'j1sa-wcs');

			}
			
		// Out of stock (OOS) noti
		register_setting( 'j1sa-all', 'j1sa-wcs-template-ocs');
		add_settings_field('j1sa-wcs-template-ocs', 'Notifications for Out-of-stock Products', function() {
			$existing = $this->populate_existing_array('j1sa-wcs-template-ocs', array(
				'sku' => 1,
				'name' => 1,
			));
		
		echo '<fieldset>';
		
		echo <<<HEREDOC
			<fieldset>
			<label><input type="checkbox" name="j1sa-wcs-template-ocs[name]" value="1" {$existing['name']}> Product Name</label><br>
			<label><input type="checkbox" name="j1sa-wcs-template-ocs[sku]" value="1" {$existing['sku']}> Stock Keeping Unit (SKU)</label><br>
HEREDOC;
		echo '</fieldset>';
	}, 'j1sa-woocommerce', 'j1sa-wcs');
		
	}

}

class J1saApiClient {
	
	function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}
	
	/**
	* @param array $payload - containing keys: to, body, from_id
	* @return bool true on success
	* @throws RuntimeException
	*/
	function send($payload) {
		if (defined('WP_DEBUG') && true === WP_DEBUG) {
			file_put_contents(__DIR__ . '/good.txt', json_encode($payload));
		}
		
		delete_transient('j1sa-woocommerce_balance');

		$response = wp_remote_post( 'https://www.just1stepahead.com/api/messages', array(
			'body' => json_encode($payload),
			'timeout' => '15',
			'httpversion' => '1.1',
			'blocking' => true,
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
			)
		));
		
		//var_dump(array($response, curl_errno($ch), curl_getinfo($ch, CURLINFO_HTTP_CODE)));
		//exit('y');
		$http_status = wp_remote_retrieve_response_code($response);
		if ($http_status >= 500) {
			throw new RuntimeException('Server-side Error: ' . print_r($response, true));
		} else if ($http_status >= 400) {
			throw new RuntimeException('Client-side Error: ' . print_r($response, true));
		}
		return true;
	}
	
	function get_balance() {
		$result = get_transient( 'j1sa-woocommerce_balance' );
		if ($result !== false) {
			return $result;
		}
		
		$response = wp_remote_get( 'https://www.just1stepahead.com/api/balance', array(
			'timeout' => '6',
			'httpversion' => '1.1',
			'blocking' => true,
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
			)
		));
		
		$http_status = wp_remote_retrieve_response_code($response);
		if ($http_status >= 500) {
			throw new RuntimeException('Server-side Error: ' . print_r($response, true));
		} else if ($http_status >= 400) {
			throw new RuntimeException('Client-side Error: ' . print_r($response, true));
		}
		
		$result = json_decode(wp_remote_retrieve_body($response), true);
		
		set_transient( 'j1sa-woocommerce_balance', $result, 30*60 );
		
		return $result;
	}
}

new Configuration_Page;

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { // https://docs.woocommerce.com/document/create-a-plugin/#section-1
	require __DIR__ . '/vendor/autoload.php'; // composer dependencies for libphonenumber

	class WooCommerce_Interceptor {
		function __construct() {
			$this->phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
			
			add_action ( 'woocommerce_payment_complete', array($this, 'new_pending_order'), 100 );
			add_action ( 'woocommerce_order_status_failed', array($this, 'new_failed_order'), 100 );
			add_action ( 'woocommerce_order_status_cancelled', array($this, 'new_cancelled_order'), 100 );
			add_action ( 'woocommerce_order_status_changed', array($this, 'status_change'), 100 );
			add_action ( 'woocommerce_no_stock', array($this, 'oos_hook'), 100 );
		}
		
		function new_pending_order($id) {
			return $this->order_hook($id, 'new-order');
		}
		
		function new_failed_order($id) {
			return $this->order_hook($id, 'failed-order');
		}
		
		function new_cancelled_order($id) {
			return $this->order_hook($id, 'cancelled-order');
		}
		
		function status_change($id) {
			return $this->order_hook($id, 'client');
		}
		
		function oos_hook($type_id) {

			if (!get_option('j1sa-sender-id') || !get_option('j1sa-recipient') || !get_option('j1sa-username') || !get_option('j1sa-password')) {
				return; // not initialized
			} elseif (!@array_filter($fields = get_option('j1sa-wcs-template-ocs'))) {
				return; // not checked
			}
			
			$fields = array_merge(array('name' => '', 'sku' => ''), $fields);
			
			try {
				$product = wc_get_product($type_id);
				
				$body = html_entity_decode(get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8') . ': Product';
				
				if ($fields['sku'] && $product->get_sku('j1sa')) {
					$body .= ' #' . $product->get_sku('j1sa');
				}
				
				if ($fields['name'] && $product->get_name('name')) {
					$body .= ': ' . $product->get_name('name');
				}
				
				$body .= ' is now out of stock.';
												
				$client = new J1saApiClient(get_option('j1sa-username'), get_option('j1sa-password'));
							
				$recipient = $type_id === 'client' ? $this->phoneToJ1saFormat($order->get_billing_phone()) : get_option('j1sa-recipient');
				
				$client->send(array('to' => $recipient, 'body' => $body, 'from_id' => get_option('j1sa-sender-id')));
			} catch (Exception $e) {
				error_log('Error while sending J1SA text: ' . print_r($e, true));
				return new WP_Error('api_client_generic', 'Problems while sending text messages through J1SA', $e);
			}
		}
		
		function order_hook($id, $type_id) {

			if (!get_option('j1sa-sender-id') || !get_option('j1sa-username') || !get_option('j1sa-password')) {
				return; // not initialized
			} elseif (!@array_filter($fields = get_option('j1sa-wcs-template-' . $type_id))) {
				return; // not checked
			}
			
			$fields = array_merge(array('order_id' => '', 'total' => '', 'name' => '', 'email' => '', 'mobile' => '', 'address' => '', 'date' => '', 'products' => '', 'products_quantity' => '', 'status' => ''), $fields);
			
			try {
				$order = wc_get_order($id);
				
				$body = 'Order';
				if ($fields['order_id']) {
					$body .= " #$id";
				}
				
				$body .= ' on ' . html_entity_decode(get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				
				if ($fields['name']) {
					$body .= " from " . $order->get_formatted_billing_full_name();
				}
				
				if ($fields['email'] || $fields['mobile'] || $fields['address']) {
					$not_first_contact = 0;
					$body .= ' (';
					
					foreach (array('email', 'phone', 'address') as $key) {
						if ($not_first_contact) {
							$body .= ', ';
						} else {
							$not_first_contact = 1;
						}
						
						switch ($key) {
							case 'email':
								$body .= $order->get_billing_email();
								break;
							case 'phone':
								$body .= $order->get_billing_phone();
								break;
							case 'address':
								$body .= 'Deliver to: ';
								$formatted_addr = $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();
								$body .= strip_tags(str_replace('<br/>', ', ', $formatted_addr));
								break;
						}
					}
					
					$body .= ')';
				}

				if ($fields['total']) {
					$body .= ". Total " . html_entity_decode(get_woocommerce_currency_symbol($order->get_currency()), ENT_QUOTES | ENT_HTML5, 'UTF-8') . $order->get_total();
				}
			
				if ($fields['date']) {
					$body .= " at " . $order->get_date_modified()->date('Y-m-d H:i:s');
				}
				
				if ($fields['status']) {
					$body .= '. Status: ' . $order->get_status();
				}
				
				if ($fields['products']) {
					$body .= '. Items: ';
					
					$items = array();
					foreach ($order->get_items() as $item) {
						$items[] = $item->get_name('j1sa-woocommerce') . ($fields['products_quantity'] ? ' x' . $item->get_quantity() : '');
					}
					
					$body .= implode(', ', $items);
				}
								
				$client = new J1saApiClient(get_option('j1sa-username'), get_option('j1sa-password'));
							
				$recipient = $type_id === 'client' ? $this->phoneToJ1saFormat($order->get_billing_phone()) : get_option('j1sa-recipient');
				
				$client->send(array('to' => $recipient, 'body' => $body, 'from_id' => get_option('j1sa-sender-id')));
			} catch (Exception $e) {
				error_log('Error while sending J1SA text: ' . print_r($e, true));
				return new WP_Error('api_client_generic', 'Problems while sending text messages through J1SA', $e);
			}
		}
		
		protected function phoneToJ1saFormat($phone, $default_country = 'GB') {
			$phoneNumberInstance = $this->phoneUtil->parse($phone, $default_country);
			$formatted = $this->phoneUtil->format($phoneNumberInstance, \libphonenumber\PhoneNumberFormat::E164);
			return '00' . substr($formatted, 1);
		}
	}

	new WooCommerce_Interceptor;
	
}
