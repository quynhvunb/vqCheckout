<?php
/**
 * Debug Admin Notice
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

defined( 'ABSPATH' ) || exit;

class Debug_Notice {
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'show_debug_notice' ) );
	}

	public static function show_debug_notice() {
		$vendor_exists = file_exists( VQCHECKOUT_PATH . 'vendor/autoload.php' );
		$class_exists = class_exists( 'VQCheckout\\Admin\\Settings_Page' );

		?>
		<div class="notice notice-info">
			<p><strong>VQ Checkout Debug:</strong></p>
			<ul>
				<li>Plugin Path: <?php echo esc_html( VQCHECKOUT_PATH ); ?></li>
				<li>Vendor autoload exists: <?php echo $vendor_exists ? '✓ Yes' : '✗ No'; ?></li>
				<li>Settings_Page class exists: <?php echo $class_exists ? '✓ Yes' : '✗ No'; ?></li>
				<li>is_admin(): <?php echo is_admin() ? 'Yes' : 'No'; ?></li>
			</ul>
		</div>
		<?php
	}
}
