<?

namespace WooCommerce\Abbiamo\Http;

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class AbbiamoHttpHandler {
  private const OAUTH_URL = 'https://oauth.abbiamo.io/auth/gentoken';
  private const SHIPPING_URL = 'https://shipping.abbiamo.io/shipping';
  private const ORDER_URL = 'https://api.abbiamo.io/v1/order';
  private const ORDER_SANDBOX_URL = 'https://sandbox-api.abbiamo.io/v1/order';

  public function __construct() {
    $this->client = new Client();
  }

  public function create_abbiamo_order( $order_request ) {
    try {
      $access_token = $this->get_access_token();
      $order_url    = get_option('wc_settings_tab_abbiamolog_sandbox') != 'yes' ? self::ORDER_URL : self::ORDER_SANDBOX_URL;

      $response = $this->client->post($order_url, [
        'body' => json_encode($order_request),
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => "Bearer {$access_token}",
        ],
      ]);

      $response_body = json_decode((string) $response->getBody(), true);

      return $response_body['invoice_created'];
    } catch (ClientException $e) {
      error_log($e->getMessage());
      return null;
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return null;
    }
  }

  public function get_shipping_rate( $postcode, $price, $weight ) {
    try {
      $access_token = $this->get_access_token();
      $response = $this->client->get(
        self::SHIPPING_URL . "?zip_code={$postcode}&weight={$weight}&amount={$price}",
        [
          'headers' => [
            'Authorization' => "Bearer {$access_token}",
          ],
        ],
      );
      $response_body = json_decode((string) $response->getBody(), true);

      return $response_body['amount'];
    } catch (ClientException $e) {
      return null;
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return null;
    }
  }

  private function get_access_token() {
    try {
      $response = $this->client->post(self::OAUTH_URL, [
        'body' => json_encode([
          'username' => get_option('wc_settings_tab_abbiamolog_client_id'),
          'password' => get_option('wc_settings_tab_abbiamolog_secret_key'),
        ]),
        'headers' => [
          'Content-Type' => 'application/json',
        ],
      ]);
      $response_body = json_decode((string) $response->getBody(), true);

      return $response_body['access_token'];
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return null;
    }
  }
}
