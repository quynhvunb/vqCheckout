<?php
/**
 * Admin REST Controller
 *
 * @package VQCheckout\API
 */

namespace VQCheckout\API;

use VQCheckout\Core\Service_Container;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD endpoints for admin
 */
class Admin_Controller extends \WP_REST_Controller {
	protected $namespace = 'vqcheckout/v1';
	private $container;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/admin/rates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rates' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_rate' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_rate_schema(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/rates/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rate' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_rate' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_rate_schema(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_rate' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/rates/reorder',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder_rates' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'order' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/shipping-methods',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_shipping_methods' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'zone_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	public function get_rates( $request ) {
		$rate_repo = $this->container->get( 'rate_repository' );

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$rates = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority ASC", ARRAY_A );

		foreach ( $rates as &$rate ) {
			if ( ! empty( $rate['conditions'] ) ) {
				$rate['conditions'] = json_decode( $rate['conditions'], true );
			}

			$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';
			$ward_codes      = $wpdb->get_col(
				$wpdb->prepare( "SELECT ward_code FROM {$locations_table} WHERE rate_id = %d", $rate['id'] )
			);
			$rate['ward_codes'] = $ward_codes;
		}

		return rest_ensure_response( $rates );
	}

	public function get_rate( $request ) {
		$rate_id   = $request->get_param( 'id' );
		$rate_repo = $this->container->get( 'rate_repository' );

		$rate = $rate_repo->get_rate( $rate_id );

		if ( ! $rate ) {
			return new \WP_Error( 'not_found', __( 'Rate not found', 'vq-checkout' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';
		$ward_codes      = $wpdb->get_col(
			$wpdb->prepare( "SELECT ward_code FROM {$locations_table} WHERE rate_id = %d", $rate_id )
		);
		$rate['ward_codes'] = $ward_codes;

		return rest_ensure_response( $rate );
	}

	public function create_rate( $request ) {
		$rate_repo = $this->container->get( 'rate_repository' );

		$data = array(
			'zone_id'          => $request->get_param( 'zone_id' ),
			'instance_id'      => $request->get_param( 'instance_id' ),
			'title'            => $request->get_param( 'title' ),
			'cost'             => $request->get_param( 'cost' ),
			'priority'         => $request->get_param( 'priority' ) ?? 0,
			'is_blocked'       => $request->get_param( 'is_blocked' ) ?? 0,
			'stop_after_match' => $request->get_param( 'stop_after_match' ) ?? 0,
			'conditions'       => $request->get_param( 'conditions' ) ?? array(),
			'ward_codes'       => $request->get_param( 'ward_codes' ) ?? array(),
		);

		$rate_id = $rate_repo->create_rate( $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'rate_id' => $rate_id,
				'message' => __( 'Rate created', 'vq-checkout' ),
			)
		);
	}

	public function update_rate( $request ) {
		$rate_id   = $request->get_param( 'id' );
		$rate_repo = $this->container->get( 'rate_repository' );

		$data = array(
			'title'            => $request->get_param( 'title' ),
			'cost'             => $request->get_param( 'cost' ),
			'priority'         => $request->get_param( 'priority' ),
			'is_blocked'       => $request->get_param( 'is_blocked' ) ?? 0,
			'stop_after_match' => $request->get_param( 'stop_after_match' ) ?? 0,
			'conditions'       => $request->get_param( 'conditions' ) ?? array(),
			'ward_codes'       => $request->get_param( 'ward_codes' ) ?? array(),
		);

		$rate_repo->update_rate( $rate_id, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rate updated', 'vq-checkout' ),
			)
		);
	}

	public function delete_rate( $request ) {
		$rate_id   = $request->get_param( 'id' );
		$rate_repo = $this->container->get( 'rate_repository' );

		$rate_repo->delete_rate( $rate_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rate deleted', 'vq-checkout' ),
			)
		);
	}

	public function reorder_rates( $request ) {
		$order = $request->get_param( 'order' );

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		foreach ( $order as $priority => $rate_id ) {
			$wpdb->update(
				$table,
				array( 'priority' => $priority ),
				array( 'id' => $rate_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$cache = $this->container->get( 'cache' );
		$cache->flush();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Order updated', 'vq-checkout' ),
			)
		);
	}

	public function get_shipping_methods( $request ) {
		$zone_id = $request->get_param( 'zone_id' );
		$zone    = \WC_Shipping_Zones::get_zone( $zone_id );

		if ( ! $zone ) {
			return new \WP_Error( 'not_found', __( 'Zone not found', 'vq-checkout' ), array( 'status' => 404 ) );
		}

		$methods = array();
		foreach ( $zone->get_shipping_methods() as $method ) {
			if ( $method->id === 'vqcheckout_ward_rate' ) {
				$methods[] = array(
					'instance_id' => $method->get_instance_id(),
					'title'       => $method->get_title(),
					'enabled'     => $method->is_enabled(),
				);
			}
		}

		return rest_ensure_response( $methods );
	}

	private function get_rate_schema() {
		return array(
			'zone_id'          => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'instance_id'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'title'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'cost'             => array(
				'type'              => 'number',
				'sanitize_callback' => 'floatval',
			),
			'priority'         => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'is_blocked'       => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'stop_after_match' => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'conditions'       => array(
				'type' => 'object',
			),
			'ward_codes'       => array(
				'type'  => 'array',
				'items' => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		);
	}
}
