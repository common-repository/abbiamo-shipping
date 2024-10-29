<?

use WooCommerce\Abbiamo\Http\AbbiamoHttpHandler;

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

function abbiamolog_shipping_method_init() {
	if ( ! class_exists( 'WC_Abbiamolog_Shipping_Method' ) ) {
		class WC_Abbiamolog_Shipping_Method extends WC_Shipping_Method {

			const METHOD_ID = 'ABBIAMOLOG';

			/**
			* Constructor.
			*
			* @param int $instance_id Instance ID.
			*/
			public function __construct( $instance_id = 0 ) {
				$this->id                 = self::METHOD_ID;
				$this->instance_id        = absint( $instance_id );
				$this->method_title       = 'Abbiamolog';
				$this->method_description = 'Entregando emoções';
				$this->supports           = array(
					'shipping-zones',
					'instance-settings',
					'instance-settings-modal',
				);
				$this->title              = 'Abbiamo';
				$this->init();
			}

			/**
			* Initialize local pickup.
			*/
			public function init() {

				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();

				// Actions.
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function init_settings() {
				$this->abbiamo_handler = new AbbiamoHttpHandler();
			}

			/**
			* Calculate local pickup shipping.
			*
			* @param array $package Package information.
			*/
			public function calculate_shipping( $package = array() ) {
				global $woocommerce;
				$postcode = $woocommerce->customer->get_shipping_postcode();

				if (empty($postcode)) {
					return;
				}

				$postcode = str_replace('-', '', $postcode);

				$items = $package['contents'];

				$volume = 0;
				$total_weight = 0;
				$price  = 0;
				foreach ($items as $item) {
					$quantity = intval( $item['quantity'] );
					$product  = wc_get_product($item['product_id']);

					$height = empty($product->get_height()) ? 100 : intval($product->get_height());
	        $length = empty($product->get_length()) ? 100 : intval($product->get_length());
	        $width  = empty($product->get_width()) ? 100 : intval($product->get_width());
					$volume = $volume + ($width * $length * $height) * $quantity;

					$weight       = empty($product->get_weight()) ? 500 : intval($product->get_weight()) * 1000;
					$total_weight = $total_weight + ($weight) * $quantity;

					$price  = $price + ( intval( (float) $product->get_price() * 100 ) ) * $quantity;
				}

				$cost = $this->abbiamo_handler->get_shipping_rate($postcode, $price, $total_weight);
				if (is_null($cost)) {
					return;
				}

				$this->add_rate(
					array(
						'id'			 => self::METHOD_ID,
						'label'    => $this->title,
						'cost'     => floatval( $cost / 100 ),
						'calc_tax' => 'per_ordem',
						'meta_data' => array(
							'abbiamo_delivery' => true,
						),
					)
				);
			}

			/**
			* Calculate Abbiamo shipping.
			*
			* @param string $poscode.
			* @param int $price.
			* @param int $weight
			* @return int|null
			*/
			private function calcule_abbiamo_shipping( $postcode, $price, $weight ) {
				$client = new Client(['base_uri' => get_option('wc_settings_tab_abbiamolog_shipping_url')]);

				try {
					$response = $client->request(
						'GET',
						"/prod/shipping?zip_code={$postcode}&weight={$weight}&price={$price}",
						[
							'headers' => [
								'Authorization' => "Bearer {$this->abbiammo_access_token}",
							],
						],
					);
					$response_body = json_decode((string) $response->getBody(), true);

					return $response_body['amount'];
				} catch (ClientException $e) {
					return null;
				}
			}
		}
	}
}

	add_action( 'woocommerce_shipping_init', 'abbiamolog_shipping_method_init' );

	function add_abbiamolog_shipping_method( $methods ) {
		$methods[WC_Abbiamolog_Shipping_Method::METHOD_ID] = 'WC_Abbiamolog_Shipping_Method';
		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'add_abbiamolog_shipping_method' );
}
