<?php
/**
 * Notices.
 *
 * @since 1.0.0
 * @package Woo3pdHelpscout/Admin
 */
namespace Woo3pdHelpscout\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notices {

	/**
	 * PHP version failure notice
	 */
	public static function php_failure_notice() {

		$notice = sprintf(
			// Translators: %1$s link to documentation. %2$s minimum required PHP version number.
			__( 'Woo3pd Helpscout requires at least PHP <strong>%1$s</strong>. Learn <a href="%2$s">how to update PHP</a>.', 'woo3pd-helpscout' ),
			self::MIN_PHP,
			'https://docs.woocommerce.com/document/how-to-update-your-php-version/'
		);
		?>
		<div class="notice notice-warning">
			<p><?php echo wp_kses_post( $notice ); ?></p>
		</div>
		<?php
	}

} // End class.
