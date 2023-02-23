<?php
use Woo3pdHelpscout\App;
use Woo3pdHelpscout\Api\Helpscout;
use Woo3pdHelpscout\Api\Sendgrid;
?>
<div class="wrap">

	<h1><?php esc_html_e( 'Woo3pd HelpScout Settings', 'woo3pdhelpscout' ); ?></h1>

	<!-- Beginning of the Plugin Options Form -->
	<form method="post" action="<?php echo admin_url( 'options.php' ); ?>">

		<?php settings_fields( App::OPTION ); ?>

		<?php

			$current_api      = App::instance()->get_setting( 'api' );
			$hs_client_id     = App::instance()->get_setting( 'client_id' );
			$hs_client_secret = App::instance()->get_setting( 'client_secret' );
			$hs_mailbox_id    = App::instance()->get_setting( 'mailbox_id' );
			$hs_secret_key    = Helpscout::instance()->get_setting( 'secret_key' );
			$sg_api_key       = Sendgrid::instance()->get_setting( 'api_key' );

			$debug            = App::instance()->get_setting( 'debug' );
			$delete           = App::instance()->get_setting( 'delete' );

		?>

		<script>
			jQuery( document ).ready(function($) {
				$('input[name="woo3pd_helpscout[api]"]').on( 'change', function() {
					var val = $(this).val();
					$('.toggle-api').hide();
					$('.show-if-' + val ).show();
				});
			});

		</script>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Delivery API', 'woo3pdhelpscout' ); ?></th>
				<td>
					<label for="helpscout_api">
						<input id="helpscout_api" type="radio" name="woo3pd_helpscout[api]" value="helpscout" <?php checked( $current_api, 'helpscout' ); ?>/>
						<?php esc_html_e( 'Helpscout Webhook', 'woo3pdhelpscout' ); ?>
					</label>
					
					<label for="sendgrid_api">
						<input id="sendgrid_api" type="radio" name="woo3pd_helpscout[api]" value="sendgrid" <?php checked( $current_api, 'sendgrid' ); ?>/>
						<?php esc_html_e( 'SendGrid API', 'woo3pdhelpscout' ); ?>
					</label>
				
					<p><?php esc_html_e( 'Your webhook URL', 'woo3pdhelpscout' ); ?>
					<?php foreach ( App::instance()->get_apis()  as $api ) : ?>
						<code class="toggle-api show-if-<?php echo esc_attr( $api ); ?>" <?php echo $api !== $current_api ? 'style="display:none"' : ''; ?>>
							<?php echo esc_url( add_query_arg( array( 'woo3pd-api' => $api ), site_url() ) ); ?>
						</code>
					<?php endforeach; ?>
					</p>

				</td>
			</tr>

			<!-- Shared Helpscout API settings -->

			<tr>
				<th scope="row"><label for="hs_client_id"><?php esc_html_e( 'Helpscout App ID', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="text" id="hs_client_id" class="regular-text" name="woo3pd_helpscout[client_id]" value="<?php echo esc_attr( $hs_client_id ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hs_client_secret"><?php esc_html_e( 'Helpscout App Secret', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="password" id="hs_client_secret" class="regular-text" name="woo3pd_helpscout[client_secret]" value="<?php echo esc_attr( $hs_client_secret ); ?>" />
				</td>
			</tr>

			<!-- Helpscout webhook settings -->

			<tr class="toggle-api show-if-helpscout" <?php echo 'helpscout' !== $current_api ? 'style="display:none"' : ''; ?> >
				<th scope="row"><label for="hs_secret_key"><?php esc_html_e( 'Helpscout Webhook Secret Key', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="password" id="hs_secret_key" class="regular-text" name="woo3pd_helpscout[helpscout][secret_key]" value="<?php echo esc_attr( $hs_secret_key ); ?>" />
				</td>
			</tr>

			<tr class="toggle-api show-if-sendgrid" <?php echo 'helpscout' === $current_api ? 'style="display:none"' : ''; ?> >
				<th scope="row"><label for="hs_mailbox_id"><?php esc_html_e( 'Helpscout Mailbox Id', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="text" id="hs_mailbox_id" name="woo3pd_helpscout[mailbox_id]" value="<?php echo esc_attr( $hs_mailbox_id ); ?>" />
				</td>
			</tr>

			<tr class="" style="border-top:1px solid black">
				<td colspan="2"></td>
			</tr>

			<!-- SendGrid API settings -->

			<tr class="toggle-api show-if-sendgrid" <?php echo 'sendgrid' !== $current_api ? 'style="display:none"' : ''; ?> >
				<th scope="row"><label for="sendgrid_api_key"><?php esc_html_e( 'SendGrid API Key', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="password" id="sendgrid_api_key" class="regular-text" name="woo3pd_helpscout[sendgrid][api_key]" value="<?php echo esc_attr( $sg_api_key ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for=""><?php esc_html_e( 'Enable debug mode', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="checkbox" name="woo3pd_helpscout[debug]" value="1" <?php checked( $debug, 'yes' ); ?> />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for=""><?php esc_html_e( 'Completely remove options on plugin removal', 'woo3pdhelpscout' ); ?></label></th>
				<td>
					<input type="checkbox" name="woo3pd_helpscout[delete]" value="1" <?php checked( $delete, 'yes' ); ?> />
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

</div>
