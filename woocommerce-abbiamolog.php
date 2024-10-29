<?
/**
 * Plugin Name: Abbiamolog Shipping
 * Plugin URI: https://www.abbiamolog.com
 * Description: Abbiamolog Shipping Module for WooCommerce 3 & 4
 * Version: 1.0.4
 * Author: Abbiamo
 * Author URI: https://www.abbiamolog.com
 *
 * WC requires at least: 3.9.3
 * WC tested up to: 5.6
 *
 * License: GNU General Public License Version 3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}

use WooCommerce\Abbiamo\Repository\AbbiamoRepository;

class WooCommerceAbbiamo {
  function init() {
      define('ABBIAMO_FILE_PATH', plugin_dir_path(__FILE__));
      define('ABBIAMO_URL', plugin_dir_url( __FILE__ ) . DIRECTORY_SEPARATOR );

      add_action('admin_menu', array($this, 'add_export_tab'));
      add_filter('woocommerce_settings_tabs_array',            array($this, 'add_settings_tab'), 50);
      add_action('woocommerce_settings_tabs_abbiamo_shipping',  array($this, 'settings_tab'));
      add_action('woocommerce_update_options_abbiamo_shipping', array($this, 'update_settings'));
      add_action('woocommerce_after_shipping_rate', array( $this, 'shipping_delivery_forecast' ), 100);
      add_action('woocommerce_order_details_after_order_table_items', array( $this, 'order_tracking' ), 1);

      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

      require_once(ABBIAMO_FILE_PATH . '/vendor/autoload.php');
      require_once(ABBIAMO_FILE_PATH . '/src/Shipping/AbbiamologShippingMethod.php');
      require_once(ABBIAMO_FILE_PATH . '/src/Order/AbbiamoOrder.php');
  }

  function activate() {
    global $wp_version;

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__)); /* Deactivate plugin */
        wp_die(__('You must run WooCommerce 3.x to install WooCommerce Abbiamolog plugin', 'abbiamolog'), __('WC not activated', 'abbiamolog'), array('back_link' => true));
        return;
    }

    if (!is_plugin_active('woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php')) {
        deactivate_plugins(plugin_basename(__FILE__)); /* Deactivate plugin */
        wp_die(__('You must run Brazilian Market on WooCommerce 3.7.x to install WooCommerce Abbiamolog plugin', 'abbiamolog'), __('Brazilian Market on WooCommerce not activated', 'abbiamolog'), array('back_link' => true));
        return;
    }

    if ((float)$wp_version < 3.5) {
        deactivate_plugins(plugin_basename(__FILE__)); /* Deactivate plugin */
        wp_die(__('You must run at least WordPress version 3.5 to install WooCommerce Abbiamolog plugin', 'abbiamolog'), __('WP not compatible', 'abbiamolog'), array('back_link' => true));
        return;
    }

    define('ABBIAMOLOG_FILE_PATH', dirname(__FILE__));

    include_once('src/Install/abbiamo-shipping-install-table.php');
    wc_abbiamo_install_table();
  }

  function deactivate() {

  }

  public function add_settings_tab( $settings_tabs ) {
      $settings_tabs['abbiamo_shipping'] = __( 'Abbiamo', 'abbiamo' );
      return $settings_tabs;
  }

  public function settings_tab() {
      echo "<style media=\"screen\" type=\"text/css\">
          #mainform label {
              display: block;
              font-weight: bold;
              padding: 10px 0 0 0;
          }
          </style>
          <div class=\"updated woocommerce-message\">
              <p><strong>".__('Por favor, faça a configuração do plugin Abbiamolog.', 'abbiamolog')."</strong></p>
          </div>";
      echo "<h3>".__('Configurações gerais', 'abbiamolog')."</h3>";
      woocommerce_admin_fields( $this->get_shipments_settings() );
      echo "<h3>".__('Habilitar modo de teste', 'abbiamolog')."</h3>";
      woocommerce_admin_fields( $this->get_sandbox_setting() );
      echo "<h3>".__('Configurações de Loja', 'abbiamolog')."</h3>";
      woocommerce_admin_fields( $this->get_shop_settings() );
      echo "<h3>".__('Endereço de coleta', 'abbiamolog')."</h3>";
      woocommerce_admin_fields( $this->get_pickup_settings() );
  }

  public function update_settings() {
      woocommerce_update_options( $this->get_shipments_settings() );
      woocommerce_update_options( $this->get_sandbox_setting() );
      woocommerce_update_options( $this->get_shop_settings() );
      woocommerce_update_options( $this->get_pickup_settings() );
  }

  public function get_shipments_settings() {
      return array(
          'ABBIAMOLOG_CLIENT_ID' => array(
              'name'     => __('Usuário', 'abbiamo'),
              'type'     => 'text',
              'css'      => 'width:500px;',
              'desc'     => '',
              'default'  => '',
              'id'       => 'wc_settings_tab_abbiamolog_client_id',
          ),
          'ABBIAMOLOG_SECRET_KEY' => array(
              'name'     => __('Senha', 'abbiamo'),
              'type'     => 'text',
              'css'      => 'width:500px;',
              'desc'     => '',
              'default'  => '',
              'id'       => 'wc_settings_tab_abbiamolog_secret_key',
          ),
      );
  }

  public function get_sandbox_setting() {
    return array(
      'ABBIAMOLOG_SANDBOX_ENABLE' => array(
          'name'     => '',
          'desc'     => __('Ativar modo teste', 'abbiamo'),
          'desc_tip' => __('Marque esta opção se deseja utilizar sandbox', 'jadlog'),
          'type'        => 'checkbox',
          'default'     => 'no',
          'id'          => 'wc_settings_tab_abbiamolog_sandbox',
      ),
    );
  }

  public function get_pickup_settings() {
    return array(
        'ABBIAMOLOG_PICKUP_ZIP_CODE' => array(
            'name'     => __('CEP', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => 'Sem pontuação. Ex: 01000000',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_zip_code',
        ),
        'ABBIAMOLOG_PICKUP_STATE' => array(
            'name'     => __('Estado', 'abbiamo'),
            'type'     => 'select',
            'options'  => [
              'AC' => 'Acre',
              'AL' => 'Alagoas',
              'AP' => 'Amapá',
              'AM' => 'Amazonas',
              'BA' => 'Bahia',
              'CE' => 'Ceará',
              'ES' => 'Espírito Santos',
              'GO' => 'Goiás',
              'MO' => 'Maranhão',
              'MA' => 'Mato Grosso',
              'MS' => 'Mato Grosso do Sul',
              'MG' => 'Minas Gerais',
              'PA' => 'Pará',
              'PB' => 'Paraíba',
              'PR' => 'Paraná',
              'PE' => 'Pernambuco',
              'PI' => 'Piauí',
              'RJ' => 'Rio de Janeiro',
              'RN' => 'Rio Grande do Norte',
              'RS' => 'Rio Grande do Sul',
              'RO' => 'Rondônia',
              'RR' => 'Roraima',
              'SC' => 'Santa Catarina',
              'SP' => 'São Paulo',
              'SE' => 'Sergipe',
              'TO' => 'Tocantins',
              'DF' => 'Distrito Federal',
             ],
            'default'  => 'SP',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_state',
        ),
        'ABBIAMOLOG_PICKUP_CITY' => array(
            'name'     => __('Cidade', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => '',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_city',
        ),
        'ABBIAMOLOG_PICKUP_NEIGHBORHOOD' => array(
            'name'     => __('Bairro', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => '',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_neighborhood',
        ),
        'ABBIAMOLOG_PICKUP_STREET' => array(
            'name'     => __('Endereço', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => '',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_street',
        ),
        'ABBIAMOLOG_PICKUP_STREET' => array(
            'name'     => __('Endereço', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => '',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_street',
        ),
        'ABBIAMOLOG_PICKUP_STREET_NUMBER' => array(
            'name'     => __('Número', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => '',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_street_number',
        ),
        'ABBIAMOLOG_PICKUP_STARTING_TIME' => array(
            'name'     => __('Início do horário de coleta', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => 'Ex: 12:00',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_starting_time',
        ),
        'ABBIAMOLOG_PICKUP_ENDING_TIME' => array(
            'name'     => __('Limite do horário de coleta', 'abbiamo'),
            'type'     => 'text',
            'css'      => 'width:500px;',
            'desc'     => 'Ex: 18:00',
            'default'  => '',
            'id'       => 'wc_settings_tab_abbiamolog_pickup_ending_time',
        ),
    );
  }

  public function get_shop_settings() {
    return array(
      'ABBIAMOLOG_SHOP_EMAIL' => array(
          'name'     => __('Email', 'abbiamo'),
          'type'     => 'text',
          'css'      => 'width:500px;',
          'desc'     => '',
          'default'  => '',
          'id'       => 'wc_settings_tab_abbiamolog_shop_email'
      ),
      'ABBIAMOLOG_SHOP_PHONE' => array(
          'name'     => __('Telefone', 'abbiamo'),
          'type'     => 'text',
          'css'      => 'width:500px;',
          'desc'     => '',
          'default'  => '',
          'id'       => 'wc_settings_tab_abbiamolog_shop_phone'
      ),
      'ABBIAMOLOG_SHOP_DOCUMENT' => array(
          'name'     => __('CNPJ', 'abbiamo'),
          'type'     => 'text',
          'css'      => 'width:500px;',
          'desc'     => 'Sem pontuação. Ex: 00000000000000',
          'default'  => '',
          'id'       => 'wc_settings_tab_abbiamolog_shop_document'
      ),
      'ABBIAMOLOG_SHOP_COMPANY_NAME' => array(
          'name'     => __('Razão Social', 'abbiamo'),
          'type'     => 'text',
          'css'      => 'width:500px;',
          'desc'     => 'Nome de registro da sua empresa que será usado em documentos oficiais como contrato social e notas fiscais. Ele precisa seguir as Leis de Registro de Empresas.',
          'default'  => '',
          'id'       => 'wc_settings_tab_abbiamolog_shop_company_name'
      ),
      'ABBIAMOLOG_SHOP_TRADING_NAME' => array(
          'name'     => __('Nome da Empresa', 'abbiamo'),
          'type'     => 'text',
          'css'      => 'width:500px;',
          'desc'     => 'Também chamado de nome fantasia, esse é o nome que será conhecido pelos seus clientes e será usado no dia a dia.',
          'default'  => '',
          'id'       => 'wc_settings_tab_abbiamolog_shop_trading_name'
      ),
    );
  }

  function add_export_tab() {
      add_submenu_page('woocommerce', __('Abbiamo', 'abbiamolog'), __('Abbiamo', 'abbiamolog'), 'manage_woocommerce', 'abbiamolog', array($this, 'display_export_page'), 8);
  }

  /**
    * @return string
    */
  function display_export_page() {
    ?>
      <div class="wrap">
        <table class="wp-list-table widefat fixed posts">
          <thead>
            <tr>
              <th scope="col" id="order_id"        class="manage-column column-order_number">
                  <?php echo 'Número do pedido'; ?>
              </th>
              <th scope="col" id="order_date"      class="manage-column column-order_tracking">
                  <?php echo 'Tracking do pedido'; ?>
              </th>
            </tr>
          </thead>
          <tbody id="the-list">
            <?
              $orders = AbbiamoRepository::get_all();
              foreach ($orders as $order) {
            ?>
            <tr>
              <td><?php echo $order->order_id; ?></td>
              <td><a href="http://meupedido.abbiamolog.com/<?php echo $order->tracking; ?>">Tracking - <?php echo $order->tracking; ?><a/></td>
            </tr>
            <? } ?>
          </tbody>
        </table>
      </div>
    <?
  }

  /**
    * @param array $shipping_method
    *
    * @return string
    */
  public function shipping_delivery_forecast( $shipping_method ) {
		$meta_data = $shipping_method->get_meta_data();
		$abbiamo   = isset($meta_data['abbiamo_delivery']) ? $meta_data['abbiamo_delivery'] : false ;

		if ( $abbiamo ) {
			echo '<p><small>Entrega em 1 dia útil após expedição</small></p>';
		}
	}

  /**
	 * Action links.
	 *
	 * @param  array $links Default plugin links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links   = array();
		$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=abbiamo_shipping' ) ) . '">Configurações</a>';

		return array_merge( $plugin_links, $links );
	}


  /**
   * Action hook fired after the order details.
   *
   * @param WC_Order $order Order data.
   */
  public function order_tracking ( $order ) {
    if ($order->get_shipping_method() != 'Abbiamo') {
      return;
    }

    $abbiamo_tracking = AbbiamoRepository::get_one_by_id( $order->get_id() );

    if ( isset($abbiamo_tracking->tracking) ) {
      wc_get_template(
        'tracking-link.php',
        array(
          'tracking_code' => $abbiamo_tracking->tracking,
        ),
        '',
        ABBIAMO_FILE_PATH . 'templates/'
      );
    }
  }
}

$abbiamo_woocommerce = new WooCommerceAbbiamo();

register_activation_hook(__FILE__, array($abbiamo_woocommerce, 'activate'));
register_deactivation_hook(__FILE__, array($abbiamo_woocommerce, 'deactivate'));

$abbiamo_woocommerce->init();
