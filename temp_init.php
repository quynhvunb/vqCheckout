	private function init() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	public function plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->load_textdomain();

		if ( class_exists( 'VQCheckout\\Core\\Plugin' ) ) {
			\VQCheckout\Core\Plugin::instance();
			error_log( 'VQCheckout Bootstrap: Plugin initialized' );
		} else {
			error_log( 'VQCheckout Bootstrap: Plugin class not found!' );
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'vq-checkout',
			false,
			dirname( VQCHECKOUT_BASENAME ) . '/languages'
		);
	}
