<?

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}

use WooCommerce\Abbiamo\Http\AbbiamoHttpHandler;
use WooCommerce\Abbiamo\Repository\AbbiamoRepository;

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
  class AbbiamologOrder {
    /**
    * @param order_id int
    */
    public function __construct( $order_id ) {
      $this->order = wc_get_order( $order_id );
    }

    public function create_abbiamo_invoice() {
      if ($this->order->get_shipping_method() != 'Abbiamo') {
        return;
      }

      $order_request = array(
        'invoice_number' => $this->order->get_order_number(),
        'amount' => $this->get_total_amount(),
        'invoice_created_timestamp' => $this->order->get_date_created()->date('Y-m-d h:i:s'),
        'volumes' => [$this->get_volumes()],
        'customer' => $this->get_customer(),
        'seller' => $this->get_seller(),
        'origin_address' => $this->get_origin_address(),
        'destination_address' => $this->get_destination_address(),
      );

      $abbiamo_handle = new AbbiamoHttpHandler();
      $tracking = $abbiamo_handle->create_abbiamo_order($order_request);

      try {
        AbbiamoRepository::create( $this->order->get_order_number(), $tracking );
      } catch (\Exception $e) {
        error_log(print_r($e->getMessage()));
        return;
      }
    }

    /**
    * @return int
    */
    private function get_total_amount() {
      $total = 0;

      foreach ( $this->order->get_items() as $item ) {
        $product = $item->get_product();
        $total   = $total + ( intval ( (float) $product->get_price() * 100 ) );
      }

      return $total;
    }

    /**
    * @return array
    */
    private function get_volumes() {
      $total_weight = 0;
      $dimensions   = null;
      $items        = array();

      foreach ($this->order->get_items() as $item) {
        $product   = $item->get_product();

        $quantity = intval( $item->get_quantity() );
        $weight   = empty($product->get_weight()) ? 500 : intval( (float) $product->get_weight() * 1000 );
        $height   = empty($product->get_height()) ? 100 : intval($product->get_height());
        $length   = empty($product->get_length()) ? 100 : intval($product->get_length());
        $width    = empty($product->get_width()) ? 100 : intval($product->get_width());
        $sku      = empty($product->get_sku()) ? 'SKU' : $product->get_sku();
        $name     = empty($product->get_name()) ? 'Nome' : $product->get_name();
        $total_weight = $total_weight + ($weight * $quantity);

        $dimensions = [
          'height' => $height,
          'length' => $length,
          'width'  => $width,
        ];

        $items[] = [
          'name'      => $product->get_name() ?? 'name',
          'sku'       => $sku,
          'quantity'  => $quantity,
          'amount'    => intval( (float) $product->get_price() * 100 ),
          'weight'    => $weight,
        ];
      }

      return [
        'weight'     => $total_weight,
        'dimensions' => $dimensions,
        'items'      => $items,
      ];
    }

    /**
    * @return array
    */
    private function get_customer() {
      if ($this->order->get_meta('_billing_persontype') == '1' || !empty($this->order->get_meta('_billing_cpf'))) {
        return [
          'email' => $this->order->get_billing_email(),
          'phone' => $this->order->get_billing_phone(),
          'document_type' => 'cpf',
          'document_number' => $this->order->get_meta('_billing_cpf'),
          'first_name' => $this->order->get_shipping_first_name(),
          'last_name' => $this->order->get_shipping_last_name(),
        ];
      }

      return [
        'email' => $this->order->get_billing_email(),
        'phone' => $this->order->get_billing_phone(),
        'document_type' => 'cnpj',
        'document_number' => $this->order->get_meta('_billing_cnpj'),
        'company_name' => $this->order->get_shipping_company(),
        'trading_name' => $this->order->get_shipping_company(),
        'state_registration' => '',
      ];
    }

    /**
    * @return array
    */
    private function get_origin_address() {
      return [
        'zip_code' => get_option('wc_settings_tab_abbiamolog_pickup_zip_code'),
        'state' => get_option('wc_settings_tab_abbiamolog_pickup_state'),
        'city' => get_option('wc_settings_tab_abbiamolog_pickup_city'),
        'neighborhood' => get_option('wc_settings_tab_abbiamolog_pickup_neighborhood'),
        'street' => get_option('wc_settings_tab_abbiamolog_pickup_street'),
        'street_number' => get_option('wc_settings_tab_abbiamolog_pickup_street_number'),
      ];
    }

    /**
    * @return array
    */
    private function get_seller() {
      return [
        'email' => get_option('wc_settings_tab_abbiamolog_shop_email'),
        'phone' => get_option('wc_settings_tab_abbiamolog_shop_phone'),
        'document_type' => 'cnpj',
        'document_number' => get_option('wc_settings_tab_abbiamolog_shop_document'),
        'company_name' => get_option('wc_settings_tab_abbiamolog_shop_company_name'),
        'trading_name' => get_option('wc_settings_tab_abbiamolog_shop_trading_name'),
      ];
    }

    /**
    * @return array
    */
    private function get_destination_address() {
      $destination_address = [
        'zip_code' => str_replace('-', '', $this->order->get_shipping_postcode()),
        'state' => $this->order->get_shipping_state(),
        'city' => $this->order->get_shipping_city(),
        'street' => $this->order->get_shipping_address_1(),
        'street_number' => $this->order->get_meta('_shipping_number'),
        'complement' => $this->order->get_shipping_address_2(),
      ];

      if (!empty($this->order->get_meta('_shipping_neighborhood'))) {
        $destination_address['neighborhood'] = $this->order->get_meta('_shipping_neighborhood');
      }

      return $destination_address;
    }
  }

  function wc_abbiamo_create_order( $order_id ) {
    $abbiamo_order = new AbbiamologOrder( $order_id );
    $abbiamo_order->create_abbiamo_invoice();
  }

  add_action('woocommerce_payment_complete', 'wc_abbiamo_create_order');
}
