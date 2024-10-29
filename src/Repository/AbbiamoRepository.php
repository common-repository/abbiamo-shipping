<?

namespace WooCommerce\Abbiamo\Repository;

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}


class AbbiamoRepository {
  const TABLE_NAME      = 'woocommerce_abbiamolog';

  public static function create( $order_id, $tracking ) {
    global $wpdb;
    $table_name = $wpdb->prefix.self::TABLE_NAME;

    $wpdb->insert($table_name, array(
      'order_id' => $order_id,
      'tracking'  => $tracking,
    ));
  }

  public static function get_all() {
      global $wpdb;
      $table_name = $wpdb->prefix.self::TABLE_NAME;

      $result = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC");
      return $result;
  }

  public static function get_one_by_id( $order_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix.self::TABLE_NAME;

    $result = $wpdb->get_results("SELECT * FROM {$table_name} where order_id = {$order_id}");

    if ( isset( $result[0] ) ) {
      return $result[0];
    }

    return null;
  }
}
