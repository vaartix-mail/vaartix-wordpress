<?php
/**
 * Settings page: Settings → Vaartix.
 *
 * @package Vaartix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens, notices and the test-email action.
 */
final class Vaartix_Admin {

	const PAGE = 'vaartix';

	/**
	 * Registers admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_post_vaartix_test_email', array( __CLASS__, 'send_test_email' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VAARTIX_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds Settings → Vaartix.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Vaartix', 'vaartix' ),
			__( 'Vaartix', 'vaartix' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers the option with its sanitiser.
	 */
	public static function register_setting() {
		register_setting(
			self::PAGE,
			Vaartix_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Cleans submitted settings. An empty API key field keeps the saved key.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = Vaartix_Settings::get();

		$api_key = isset( $input['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $input['api_key'] ) ) ) : '';
		if ( ! empty( $input['clear_key'] ) ) {
			$api_key = '';
		} elseif ( '' === $api_key ) {
			$api_key = $current['api_key'];
		}

		$from_email = isset( $input['from_email'] ) ? sanitize_email( wp_unslash( $input['from_email'] ) ) : '';
		if ( isset( $input['from_email'] ) && '' !== trim( (string) $input['from_email'] ) && '' === $from_email ) {
			add_settings_error( Vaartix_Settings::OPTION, 'vaartix_from', __( 'The From email is not a valid email address, so it was not saved.', 'vaartix' ) );
		}

		return array(
			'enabled'    => ! empty( $input['enabled'] ),
			'api_key'    => $api_key,
			'from_email' => $from_email,
			'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( wp_unslash( $input['from_name'] ) ) : '',
			'force_from' => ! empty( $input['force_from'] ),
			'fallback'   => ! empty( $input['fallback'] ),
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Existing links.
	 * @return array<int|string, string>
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'vaartix' ) . '</a>' );
		return $links;
	}

	/**
	 * Reminds admins to add the API key.
	 */
	public static function setup_notice() {
		if ( ! current_user_can( 'manage_options' ) || '' !== Vaartix_Settings::api_key() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_' . self::PAGE === $screen->id ) {
			return;
		}
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: link to the settings page. */
			esc_html__( 'Vaartix is active but has no API key yet, so WordPress is still sending email itself. %s', 'vaartix' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Add your API key', 'vaartix' ) . '</a>'
		);
		echo '</p></div>';
	}

	/**
	 * Sends a test email through wp_mail(), so the whole path is tested.
	 */
	public static function send_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'vaartix' ), 403 );
		}
		check_admin_referer( 'vaartix_test_email' );

		$to = isset( $_POST['vaartix_test_to'] ) ? sanitize_email( wp_unslash( $_POST['vaartix_test_to'] ) ) : '';
		if ( '' === $to ) {
			$result = 'invalid';
		} else {
			$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$subject = sprintf(
				/* translators: %s: site name. */
				__( 'Test email from %s via Vaartix', 'vaartix' ),
				$site
			);
			$message = sprintf(
				/* translators: %s: site URL. */
				__( "This is a test email sent from %s through the Vaartix WordPress plugin.\n\nIf you can read this, your site's email is working.", 'vaartix' ),
				home_url()
			);
			$result = wp_mail( $to, $subject, $message ) ? 'sent' : 'failed';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE,
					'vaartix_test' => $result,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings       = Vaartix_Settings::get();
		$status         = Vaartix_Settings::status();
		$from_constant  = Vaartix_Settings::key_from_constant();
		$has_key        = '' !== Vaartix_Settings::api_key();
		$option         = Vaartix_Settings::OPTION;
		// Read-only display of the redirect result from send_test_email(), which checked the nonce.
		$test           = isset( $_GET['vaartix_test'] ) ? sanitize_key( wp_unslash( $_GET['vaartix_test'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$admin_email    = (string) get_option( 'admin_email' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vaartix', 'vaartix' ); ?></h1>
			<p>
				<?php esc_html_e( 'Send all WordPress email through the Vaartix transactional email API.', 'vaartix' ); ?>
				<a href="https://vaartix.com/docs/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'vaartix' ); ?></a>
			</p>

			<?php if ( 'sent' === $test ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Test email sent. Check the inbox, and the Emails page in your Vaartix dashboard.', 'vaartix' ); ?></p></div>
			<?php elseif ( 'failed' === $test ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The test email could not be sent. See "Last error" below.', 'vaartix' ); ?></p></div>
			<?php elseif ( 'invalid' === $test ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Enter a valid email address for the test.', 'vaartix' ); ?></p></div>
			<?php endif; ?>

			<?php settings_errors( $option ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Send with Vaartix', 'vaartix' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Send all WordPress email through Vaartix', 'vaartix' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vaartix-api-key"><?php esc_html_e( 'API key', 'vaartix' ); ?></label></th>
						<td>
							<?php if ( $from_constant ) : ?>
								<p><?php esc_html_e( 'Set in wp-config.php (VAARTIX_API_KEY).', 'vaartix' ); ?></p>
							<?php else : ?>
								<input type="password" id="vaartix-api-key" class="regular-text" autocomplete="off"
									name="<?php echo esc_attr( $option ); ?>[api_key]" value=""
									placeholder="<?php echo esc_attr( $has_key ? Vaartix_Settings::mask_key( $settings['api_key'] ) : 'vx_...' ); ?>" />
								<?php if ( $has_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[clear_key]" value="1" /> <?php esc_html_e( 'Remove the saved key', 'vaartix' ); ?></label>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Create a send-only key limited to your domain in the Vaartix dashboard. Leave empty to keep the saved key.', 'vaartix' ); ?>
									<?php esc_html_e( 'Safer: put define( \'VAARTIX_API_KEY\', \'vx_...\' ); in wp-config.php instead.', 'vaartix' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vaartix-from-email"><?php esc_html_e( 'From email', 'vaartix' ); ?></label></th>
						<td>
							<input type="email" id="vaartix-from-email" class="regular-text" name="<?php echo esc_attr( $option ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" placeholder="noreply@notify.example.com" />
							<p class="description"><?php esc_html_e( 'Must be on a domain you verified in Vaartix. Used when an email doesn\'t set its own From address.', 'vaartix' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vaartix-from-name"><?php esc_html_e( 'From name', 'vaartix' ); ?></label></th>
						<td><input type="text" id="vaartix-from-name" class="regular-text" name="<?php echo esc_attr( $option ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" placeholder="<?php echo esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Force From', 'vaartix' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[force_from]" value="1" <?php checked( $settings['force_from'] ); ?> />
								<?php esc_html_e( 'Always use the From email and name above, even when a plugin sets its own', 'vaartix' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Recommended: plugins often use addresses on domains Vaartix hasn\'t verified.', 'vaartix' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fallback', 'vaartix' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[fallback]" value="1" <?php checked( $settings['fallback'] ); ?> />
								<?php esc_html_e( 'If Vaartix can\'t send an email, let WordPress send it the default way', 'vaartix' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default: WordPress\'s own sending is often unauthenticated and lands in spam.', 'vaartix' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Send a test email', 'vaartix' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vaartix_test_email" />
				<?php wp_nonce_field( 'vaartix_test_email' ); ?>
				<input type="email" name="vaartix_test_to" class="regular-text" value="<?php echo esc_attr( $admin_email ); ?>" required />
				<?php submit_button( __( 'Send test email', 'vaartix' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Status', 'vaartix' ); ?></h2>
			<table class="widefat striped" style="max-width:760px">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Sending through Vaartix', 'vaartix' ); ?></td>
						<td><?php echo ( $settings['enabled'] && $has_key ) ? esc_html__( 'Yes', 'vaartix' ) : esc_html__( 'No (WordPress sends email itself)', 'vaartix' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Last email sent', 'vaartix' ); ?></td>
						<td>
							<?php
							if ( ! empty( $status['last_success']['time'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $status['last_success']['time'] ) . ' · ' . $status['last_success']['id'] );
							} else {
								esc_html_e( 'None yet', 'vaartix' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Last error', 'vaartix' ); ?></td>
						<td>
							<?php
							if ( ! empty( $status['last_error']['time'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $status['last_error']['time'] ) . ' · ' . $status['last_error']['message'] );
							} else {
								esc_html_e( 'None', 'vaartix' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
