<div class="wrap">

	<h1><?php esc_html_e( 'Woo3pd HelpScout App Credentials', 'woo3pd-helpscout' );?></h1>

	<p><?php esc_html_e( 'Your webhook URL' ); ?>
		<code><?php echo esc_url( site_url( '?woo3pd-api=helpscout' ) ); ?></code>
	</p>

	<!-- Beginning of the Plugin Options Form -->
	<form method="post" action="<?php echo admin_url( 'options.php' );?>">

		<?php settings_fields( 'woo3pd_helpscout' ); ?>

		<?php
			$settings  = get_option( 'woo3pd_helpscout' ); 
			$appId     = isset( $settings['appId'] )     ? $settings['appId']     : '';
			$appSecret = isset( $settings['appSecret'] ) ? $settings['appSecret'] : '';
			$appSecretKey = isset( $settings['appSecretKey'] ) ? $settings['appSecretKey'] : '';
			$delete    = isset( $settings['delete'] )    ? $settings['delete']    : 'no';
		?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Helpscout App ID', 'woo3pd-helpscout' );?></th>
				<td>
					<input type="password" name="woo3pd_helpscout[appId]" value="<?php echo esc_attr( $appId ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Helpscout App Secret', 'woo3pd-helpscout' );?></th>
				<td>
					<input type="password" name="woo3pd_helpscout[appSecret]" value="<?php echo esc_attr( $appSecret ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Helpscout App Secret Key', 'woo3pd-helpscout' );?></th>
				<td>
					<input type="password" name="woo3pd_helpscout[appSecretKey]" value="<?php echo esc_attr( $appSecretKey ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Completely remove options on plugin removal', 'woo3pd-helpscout' );?></th>
				<td>
					<input type="checkbox" name="woo3pd_helpscout[delete]" value="1" <?php checked( $delete, 'yes' );?> />
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

</div>