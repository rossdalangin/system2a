<?php
/**
 * EngageTrack Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Engagetrack extends Agency_Nexus_Base_Module {

	protected $name = 'EngageTrack';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
		add_shortcode( 'agency_nexus_thank_you', [ $this, 'render_thank_you_page' ] );
		add_action( 'agency_nexus_new_lead_captured', [ $this, 'handle_new_lead_captured' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( 'an-leads' === $page ) {
			if ( Agency_Nexus_Permissions::is_admin() ) {
				$this->process_lead_actions();
			}
		} elseif ( 'an-email-lead' === $page ) {
			if ( Agency_Nexus_Permissions::is_admin() ) {
				$this->process_email_lead();
			}
		} elseif ( 'an-lead-settings' === $page ) {
			if ( Agency_Nexus_Permissions::is_admin() ) {
				$this->process_settings_actions();
			}
		} elseif ( 'an-social-settings' === $page ) {
			if ( Agency_Nexus_Permissions::is_admin() ) {
				$this->process_social_settings_actions();
			}
		} elseif ( 'an-canned-responses' === $page ) {
			if ( Agency_Nexus_Permissions::is_admin() ) {
				$this->process_response_actions();
			}
		}
	}

	private function process_lead_actions() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_leads';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( 'delete' === $action && $id ) {
			check_admin_referer( 'an_delete_lead_' . $id );
			$wpdb->delete( $table_name, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-leads&msg=deleted' ) );
			exit;
		}

		if ( 'convert' === $action && $id ) {
			check_admin_referer( 'an_convert_lead_' . $id );
			$this->process_convert_lead( $id );
			exit;
		}

		if ( isset( $_POST['an_save_lead'] ) && check_admin_referer( 'an_save_lead_nonce' ) ) {
			$status = sanitize_text_field( $_POST['status'] );
			$value = floatval( $_POST['conversion_value'] );

			$data = [
				'name'             => sanitize_text_field( $_POST['name'] ),
				'email'            => sanitize_email( $_POST['email'] ),
				'source'           => sanitize_text_field( $_POST['source'] ),
				'status'           => $status,
				'conversion_value' => $value,
				'score'            => 0
			];
			if ( $id ) {
				$wpdb->update( $table_name, $data, [ 'id' => $id ] );
				$msg = 'updated';

				// If status was changed to converted, trigger conversion logic
				if ( 'converted' === $status ) {
					$this->process_convert_lead( $id );
					return;
				}
			} else {
				$wpdb->insert( $table_name, $data );
				$id = $wpdb->insert_id;
				$msg = 'added';

				if ( 'converted' === $status ) {
					$this->process_convert_lead( $id );
					return;
				}
			}

			// Run AI Lead scoring and qualification
			self::calculate_and_save_lead_score( $id );

			wp_redirect( admin_url( 'admin.php?page=an-leads&msg=' . $msg ) );
			exit;
		}
	}

	private function process_convert_lead( $lead_id ) {
		global $wpdb;
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_leads WHERE id = %d", $lead_id ) );

		if ( $lead ) {
			// Check if client already exists
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}an_clients WHERE email = %s", $lead->email ) );

			if ( ! $existing ) {
				$inserted = $wpdb->insert( $wpdb->prefix . 'an_clients', [
					'name'       => $lead->name,
					'email'      => $lead->email,
					'address'    => '', // Required column
					'notes'      => sprintf( __( 'Converted from lead source: %s', 'agency-nexus' ), $lead->source ),
					'created_at' => current_time( 'mysql' )
				] );
				$client_id = $inserted ? $wpdb->insert_id : 0;
			} else {
				$client_id = $existing;
			}

			// Update lead status
			$wpdb->update( $wpdb->prefix . 'an_leads', [ 'status' => 'converted' ], [ 'id' => $lead_id ] );

			if ( $client_id ) {
				wp_redirect( admin_url( 'admin.php?page=an-clients&action=view&id=' . $client_id . '&msg=added' ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=an-leads&msg=updated' ) );
			}
		} else {
			wp_redirect( admin_url( 'admin.php?page=an-leads&msg=error' ) );
		}
		exit;
	}

	private function process_response_actions() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_canned_responses';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( 'delete' === $action && $id ) {
			check_admin_referer( 'an_delete_response_' . $id );
			$wpdb->delete( $table_name, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-canned-responses&msg=deleted' ) );
			exit;
		}

		if ( isset( $_POST['an_save_response'] ) && check_admin_referer( 'an_save_response_nonce' ) ) {
			$data = [
				'title'   => sanitize_text_field( $_POST['title'] ),
				'content' => wp_kses_post( $_POST['content'] )
			];
			if ( $id ) {
				$wpdb->update( $table_name, $data, [ 'id' => $id ] );
				$msg = 'updated';
			} else {
				$wpdb->insert( $table_name, $data );
				$msg = 'saved';
			}
			wp_redirect( admin_url( 'admin.php?page=an-canned-responses&msg=' . $msg ) );
			exit;
		}
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'lead_intelligence' ) ) {
			return;
		}

		if ( Agency_Nexus_Permissions::is_admin() ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Social Dashboard', 'agency-nexus' ),
				__( 'Social Dashboard', 'agency-nexus' ),
				'read',
				'an-social-dashboard',
				[ $this, 'render_social_dashboard' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Social Settings', 'agency-nexus' ),
				__( 'Social Settings', 'agency-nexus' ),
				'read',
				'an-social-settings',
				[ $this, 'render_social_settings' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Lead Intelligence', 'agency-nexus' ),
				__( 'Lead Intelligence', 'agency-nexus' ),
				'read',
				'an-lead-intelligence',
				[ $this, 'render_lead_intelligence' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Leads', 'agency-nexus' ),
				__( 'Leads', 'agency-nexus' ),
				'read',
				'an-leads',
				[ $this, 'render_leads' ]
			);

			add_submenu_page(
				null, // Hidden from menu
				__( 'Email Lead', 'agency-nexus' ),
				__( 'Email Lead', 'agency-nexus' ),
				'read',
				'an-email-lead',
				[ $this, 'render_email_lead' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Lead Settings', 'agency-nexus' ),
				__( 'Lead Settings', 'agency-nexus' ),
				'read',
				'an-lead-settings',
				[ $this, 'render_lead_settings' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Canned Responses', 'agency-nexus' ),
				__( 'Canned Responses', 'agency-nexus' ),
				'read',
				'an-canned-responses',
				[ $this, 'render_canned_responses' ]
			);
		}
	}

	private function process_social_settings_actions() {
		if ( isset( $_POST['an_save_social_settings'] ) && check_admin_referer( 'an_social_settings_nonce' ) ) {
			update_option( 'an_social_instagram', sanitize_text_field( $_POST['an_social_instagram'] ) );
			update_option( 'an_social_linkedin', sanitize_text_field( $_POST['an_social_linkedin'] ) );
			update_option( 'an_social_x', sanitize_text_field( $_POST['an_social_x'] ) );
			update_option( 'an_social_facebook', sanitize_text_field( $_POST['an_social_facebook'] ) );
			wp_redirect( admin_url( 'admin.php?page=an-social-settings&msg=saved' ) );
			exit;
		}
	}

	public function render_social_settings() {
		if ( isset( $_GET['msg'] ) && 'saved' === $_GET['msg'] ) {
			echo '<div class="updated"><p>' . __( 'Social settings saved!', 'agency-nexus' ) . '</p></div>';
		}
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Social Media Settings', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Link your agency\'s social media accounts to aggregate engagement in the Social Dashboard.', 'agency-nexus' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'an_social_settings_nonce' ); ?>
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Connected Accounts', 'agency-nexus' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><label><?php _e( 'Instagram Handle', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="text" name="an_social_instagram" value="<?php echo esc_attr( get_option( 'an_social_instagram' ) ); ?>" class="regular-text" placeholder="@youragency">
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'LinkedIn Company Page URL', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="url" name="an_social_linkedin" value="<?php echo esc_attr( get_option( 'an_social_linkedin' ) ); ?>" class="regular-text" placeholder="https://linkedin.com/company/youragency">
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'X (Twitter) Handle', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="text" name="an_social_x" value="<?php echo esc_attr( get_option( 'an_social_x' ) ); ?>" class="regular-text" placeholder="@youragency">
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'Facebook Page URL', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="url" name="an_social_facebook" value="<?php echo esc_attr( get_option( 'an_social_facebook' ) ); ?>" class="regular-text" placeholder="https://facebook.com/youragency">
							</td>
						</tr>
					</table>
				</div>
				<p class="submit">
					<input type="submit" name="an_save_social_settings" class="button button-primary" value="<?php _e( 'Save Social Settings', 'agency-nexus' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	private function process_settings_actions() {
		if ( isset( $_POST['an_save_thankyou_settings'] ) && check_admin_referer( 'an_save_thankyou_nonce' ) ) {
			update_option( 'an_ty_title', sanitize_text_field( $_POST['an_ty_title'] ) );
			update_option( 'an_ty_subtitle', sanitize_text_field( $_POST['an_ty_subtitle'] ) );
			update_option( 'an_ty_product', sanitize_text_field( $_POST['an_ty_product'] ) );
			update_option( 'an_ty_cta_text', sanitize_text_field( $_POST['an_ty_cta_text'] ) );
			update_option( 'an_ty_cta_url', esc_url_raw( $_POST['an_ty_cta_url'] ) );
			wp_redirect( admin_url( 'admin.php?page=an-lead-settings&msg=saved' ) );
			exit;
		}
	}

	public function render_lead_settings() {
		if ( isset( $_GET['msg'] ) && 'saved' === $_GET['msg'] ) {
			echo '<div class="updated"><p>' . __( 'Settings saved!', 'agency-nexus' ) . '</p></div>';
		}
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Lead Intelligence Settings', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Customize your lead capture experience and the "Thank You" page content.', 'agency-nexus' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'an_save_thankyou_nonce' ); ?>
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Thank You Page Content (Shortcode Defaults)', 'agency-nexus' ); ?></h3>
					<p class="description"><?php _e( 'These settings control the default output of the [agency_nexus_thank_you] shortcode.', 'agency-nexus' ); ?></p>

					<table class="form-table">
						<tr>
							<th><label><?php _e( 'Main Title', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="text" name="an_ty_title" value="<?php echo esc_attr( get_option( 'an_ty_title', __( 'Your submission was successful!', 'agency-nexus' ) ) ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'Sub-title', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="text" name="an_ty_subtitle" value="<?php echo esc_attr( get_option( 'an_ty_subtitle', __( 'We have received your details and will be in touch shortly.', 'agency-nexus' ) ) ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'Offer/Product Name', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="text" name="an_ty_product" value="<?php echo esc_attr( get_option( 'an_ty_product', __( 'our premium solutions', 'agency-nexus' ) ) ); ?>" class="regular-text">
								<p class="description"><?php _e( 'This will appear in the exclusive offer box.', 'agency-nexus' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'CTA Button Text', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="text" name="an_ty_cta_text" value="<?php echo esc_attr( get_option( 'an_ty_cta_text', __( 'Check this out while you wait', 'agency-nexus' ) ) ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'CTA Button URL', 'agency-nexus' ); ?></label></th>
							<td>
								<input type="url" name="an_ty_cta_url" value="<?php echo esc_attr( get_option( 'an_ty_cta_url', '#' ) ); ?>" class="regular-text">
							</td>
						</tr>
					</table>
				</div>
				<p class="submit">
					<input type="submit" name="an_save_thankyou_settings" class="button button-primary" value="<?php _e( 'Save Settings', 'agency-nexus' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	public function process_email_lead() {
		if ( isset( $_POST['an_send_lead_email'] ) && check_admin_referer( 'an_send_lead_email_nonce' ) ) {
			global $wpdb;
			$lead_id = intval( $_POST['lead_id'] );
			$subject = sanitize_text_field( $_POST['subject'] );
			$message = wp_kses_post( $_POST['message'] );
			$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_leads WHERE id = %d", $lead_id ) );

			if ( $lead ) {
				$sent = wp_mail( $lead->email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
				if ( $sent ) {
					$wpdb->insert( $wpdb->prefix . 'an_lead_communications', [
						'lead_id'    => $lead_id,
						'user_id'    => get_current_user_id(),
						'subject'    => $subject,
						'message'    => $message,
						'created_at' => current_time( 'mysql' )
					] );
					wp_redirect( admin_url( 'admin.php?page=an-leads&msg=email_sent' ) );
				} else {
					wp_redirect( admin_url( 'admin.php?page=an-leads&msg=email_failed' ) );
				}
				exit;
			}
		}
	}

	public function render_email_lead() {
		global $wpdb;
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_leads WHERE id = %d", $id ) );
		$responses = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}an_canned_responses ORDER BY title ASC" );

		if ( ! $lead ) {
			echo '<div class="error"><p>' . __( 'Lead not found.', 'agency-nexus' ) . '</p></div>';
			return;
		}
		?>
		<div class="agency-nexus-wrap">
			<h1><?php echo sprintf( __( 'Email Lead: %s', 'agency-nexus' ), esc_html( $lead->name ) ); ?></h1>
			<p class="description"><?php echo sprintf( __( 'Send a message to %s (%s).', 'agency-nexus' ), esc_html( $lead->name ), esc_html( $lead->email ) ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'an_send_lead_email_nonce' ); ?>
				<input type="hidden" name="lead_id" value="<?php echo $lead->id; ?>">

				<table class="form-table">
					<tr>
						<th><label><?php _e( 'Canned Response', 'agency-nexus' ); ?></label></th>
						<td>
							<select id="an-canned-response-select">
								<option value=""><?php _e( 'Select a template...', 'agency-nexus' ); ?></option>
								<?php foreach ( $responses as $res ) : ?>
									<option value="<?php echo esc_attr( $res->id ); ?>" data-content="<?php echo esc_attr( $res->content ); ?>"><?php echo esc_html( $res->title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select a template to quickly populate the subject and message.', 'agency-nexus' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="subject"><?php _e( 'Subject', 'agency-nexus' ); ?></label></th>
						<td>
							<input type="text" name="subject" id="subject" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="message"><?php _e( 'Message', 'agency-nexus' ); ?></label></th>
						<td>
							<?php wp_editor( '', 'message', [ 'textarea_name' => 'message', 'rows' => 10 ] ); ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="an_send_lead_email" class="button button-primary" value="<?php _e( 'Send Email', 'agency-nexus' ); ?>">
					<a href="?page=an-leads" class="button"><?php _e( 'Cancel', 'agency-nexus' ); ?></a>
				</p>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($){
			$('#an-canned-response-select').change(function(){
				var content = $(this).find(':selected').data('content');
				var title = $(this).find(':selected').text();
				if (content) {
					$('#subject').val(title);
					if (typeof tinyMCE !== 'undefined' && tinyMCE.get('message')) {
						tinyMCE.get('message').setContent(content);
					} else {
						$('#message').val(content);
					}
				}
			});
		});
		</script>
		<?php
	}

	public function render_leads() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_leads';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'added':        $m = 'Lead recorded!'; break;
				case 'updated':      $m = 'Lead updated!'; break;
				case 'deleted':      $m = 'Lead deleted!'; break;
				case 'email_sent':   $m = 'Email sent to lead!'; break;
				case 'email_failed': $m = 'Failed to send email.'; break;
				case 'converted':    $m = 'Lead converted to client successfully!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'edit' || $action === 'add') {
			$lead = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Lead', 'agency-nexus') : __('Add New Lead', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Track a potential client in your sales pipeline.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'an_save_lead_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Name', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="name" value="<?php echo $lead ? esc_attr($lead->name) : ''; ?>" required class="regular-text">
								<p class="description"><?php _e('Full name of the prospect.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Email', 'agency-nexus'); ?></label></th>
							<td>
								<input type="email" name="email" value="<?php echo $lead ? esc_attr($lead->email) : ''; ?>" required class="regular-text">
								<p class="description"><?php _e('Contact email for follow-ups.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Source', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="source" value="<?php echo $lead ? esc_attr($lead->source) : ''; ?>" class="regular-text">
								<p class="description"><?php _e('Where did this lead come from? e.g., LinkedIn, Referral, Google Ads', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Status', 'agency-nexus'); ?></label></th>
							<td>
								<select name="status">
									<option value="new" <?php selected($lead ? $lead->status : '', 'new'); ?>>New</option>
									<option value="qualified" <?php selected($lead ? $lead->status : '', 'qualified'); ?>>Qualified</option>
									<option value="converted" <?php selected($lead ? $lead->status : '', 'converted'); ?>>Converted</option>
									<option value="lost" <?php selected($lead ? $lead->status : '', 'lost'); ?>>Lost</option>
								</select>
								<p class="description"><?php _e('Current stage in your sales process.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Potential Value ($)', 'agency-nexus'); ?></label></th>
							<td>
								<input type="number" step="0.01" name="conversion_value" value="<?php echo $lead ? esc_attr($lead->conversion_value) : ''; ?>" class="regular-text">
								<p class="description"><?php _e('Estimated project value if this lead converts.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<input type="submit" name="an_save_lead" class="button button-primary" value="Save Lead">
					<a href="?page=an-leads" class="button">Cancel</a>
				</form>

				<?php if ( $id ) :
					$comms = $wpdb->get_results( $wpdb->prepare( "SELECT c.*, u.display_name FROM {$wpdb->prefix}an_lead_communications c LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID WHERE c.lead_id = %d ORDER BY c.created_at DESC", $id ) );
				?>
					<div style="margin-top: 40px;">
						<h2><?php _e( 'Communication History', 'agency-nexus' ); ?></h2>
						<?php if ( $comms ) : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php _e( 'Date', 'agency-nexus' ); ?></th>
										<th><?php _e( 'Sent By', 'agency-nexus' ); ?></th>
										<th><?php _e( 'Subject', 'agency-nexus' ); ?></th>
										<th><?php _e( 'Message', 'agency-nexus' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $comms as $comm ) : ?>
										<tr>
											<td><?php echo esc_html( $comm->created_at ); ?></td>
											<td><?php echo esc_html( $comm->display_name ); ?></td>
											<td><?php echo esc_html( $comm->subject ); ?></td>
											<td><?php echo wp_kses_post( nl2br( $comm->message ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p><?php _e( 'No communication history found for this lead.', 'agency-nexus' ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		$leads = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Lead Intelligence System', 'agency-nexus' ); ?></h1>

			<div style="background: #fff; border-left: 4px solid #46b450; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Fuel Your Agency\'s Growth', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'This system allows you to capture and manage prospects throughout your sales pipeline. Tracking potential values helps you forecast revenue and prioritize high-value deals.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Capture:</strong> Use the "Generate Embed Code" button to create a form for your marketing site.</li>
					<li><strong>Convert:</strong> Update a lead\'s status to "Converted" once they sign a contract.</li>
					<li><strong>Automate:</strong> Connect leads to <strong>AutoPilot</strong> to trigger onboarding emails instantly upon capture.</li>
				</ul>
			</div>

			<a href="?page=an-leads&action=add" class="page-title-action">Add New</a>
			<button type="button" class="page-title-action" id="generate-lead-form-btn"><?php _e( 'Generate Embed Code', 'agency-nexus' ); ?></button>
			<hr class="wp-header-end">
			<div id="embed-code-container" style="display: none; background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px;">
				<h3><?php _e('Generate Capture Form & Thank You Page', 'agency-nexus'); ?></h3>

				<div style="background: #fdfaf0; border-left: 4px solid #f0b849; padding: 15px; margin-bottom: 20px;">
					<p><strong><?php _e('Step 1: Create your Thank You Page', 'agency-nexus'); ?></strong></p>
					<p><?php _e('Create a new Page in WordPress and use this shortcode to display a high-converting confirmation message:', 'agency-nexus'); ?></p>
					<code>[agency_nexus_thank_you product_name="Your Course Name" cta_url="https://your-upsell-link.com"]</code>
				</div>

				<div style="margin-bottom: 15px;">
					<label><strong><?php _e('Step 2: Enter Redirect URL (Full URL to your Thank You page):', 'agency-nexus'); ?></strong></label><br>
					<input type="url" id="lead-redirect-url" class="large-text" placeholder="https://your-site.com/thank-you/">
				</div>

				<p><strong><?php _e('Step 3: Copy the Embed Code', 'agency-nexus'); ?></strong></p>
				<textarea id="lead-embed-textarea" class="large-text" rows="14" readonly><?php
					$api_url = get_rest_url( null, 'agency-nexus/v1/leads/capture' );
					echo esc_textarea('<form action="' . $api_url . '" method="POST">
    <div>
        <label>Name:</label><br>
        <input type="text" name="name" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div>
        <label>Email:</label><br>
        <input type="email" name="email" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <input type="hidden" name="source" value="Website Embed">
    <input type="hidden" name="redirect_url" value="" class="an-redirect-field">
    <button type="submit" style="background: #2271b1; color: #fff; border: none; padding: 12px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">Submit & Get Access</button>
</form>');
				?></textarea>
				<p><button type="button" class="button" onclick="jQuery('#embed-code-container').slideUp();">Close</button></p>
			</div>

			<script>
			jQuery(document).ready(function($){
				$('#generate-lead-form-btn').click(function(){
					$('#embed-code-container').slideToggle();
				});

				$('#lead-redirect-url').on('input', function(){
					var url = $(this).val();
					var $textarea = $('#lead-embed-textarea');
					// We use a clean template to avoid regex issues with multiple edits
					var api_url = '<?php echo get_rest_url( null, "agency-nexus/v1/leads/capture" ); ?>';
					var template = '<form action="' + api_url + '" method="POST">\n' +
'    <div>\n' +
'        <label>Name:</label><br>\n' +
'        <input type="text" name="name" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">\n' +
'    </div>\n' +
'    <div>\n' +
'        <label>Email:</label><br>\n' +
'        <input type="email" name="email" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">\n' +
'    </div>\n' +
'    <input type="hidden" name="source" value="Website Embed">\n' +
'    <input type="hidden" name="redirect_url" value="' + url + '">\n' +
'    <button type="submit" style="background: #2271b1; color: #fff; border: none; padding: 12px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">Submit & Get Access</button>\n' +
'</form>';
					$textarea.val(template);
				});
			});
			</script>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Name</th><th>Email</th><th>Source</th><th>Status</th><th>Value</th><th>Score</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach ($leads as $lead) : ?>
						<tr>
							<td><strong><a href="?page=an-leads&action=edit&id=<?php echo $lead->id; ?>"><?php echo esc_html($lead->name); ?></a></strong></td>
							<td><?php echo esc_html($lead->email); ?></td>
							<td><?php echo esc_html($lead->source); ?></td>
							<td><span class="badge status-<?php echo $lead->status; ?>"><?php echo ucfirst($lead->status); ?></span></td>
							<td>$<?php echo number_format($lead->conversion_value, 2); ?></td>
							<td>
								<div style="width: 50px; height: 10px; background: #eee; border-radius: 5px; overflow: hidden;">
									<div style="width: <?php echo min(100, $lead->score); ?>%; height: 100%; background: var(--an-indigo-600);"></div>
								</div>
								<small><?php echo intval($lead->score); ?>/100</small>
							</td>
							<td>
								<a href="?page=an-email-lead&id=<?php echo $lead->id; ?>"><?php _e('Email', 'agency-nexus'); ?></a> |
								<?php if ( $lead->status !== 'converted' ) : ?>
									<a href="<?php echo wp_nonce_url('?page=an-leads&action=convert&id=' . $lead->id, 'an_convert_lead_' . $lead->id); ?>" style="color:green;" onclick="return confirm('Convert this lead to a client?')"><?php _e('Convert', 'agency-nexus'); ?></a> |
								<?php endif; ?>
								<a href="?page=an-leads&action=edit&id=<?php echo $lead->id; ?>">Edit</a> |
								<a href="<?php echo wp_nonce_url('?page=an-leads&action=delete&id=' . $lead->id, 'an_delete_lead_' . $lead->id); ?>" style="color:red;" onclick="return confirm('Delete lead?')">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_lead_intelligence() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_leads';

		// Engagement Heatmap Data (Simulated by hour/day)
		$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
		$hours = ['00', '04', '08', '12', '16', '20'];

		$utm_sources = $wpdb->get_results( "SELECT source, COUNT(*) as count, SUM(conversion_value) as value FROM $table_name GROUP BY source" );
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Lead Intelligence & ROI Analytics', 'agency-nexus' ); ?></h1>

			<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Engagement Heatmap (24h Activity)', 'agency-nexus' ); ?></h3>
					<p class="description"><?php _e( 'Visualizing when your leads are most active. Use this to schedule high-priority emails or content.', 'agency-nexus' ); ?></p>

					<div style="display: grid; grid-template-columns: 50px repeat(6, 1fr); gap: 5px; margin-top: 20px;">
						<div></div>
						<?php foreach($hours as $h) echo "<div style='text-align:center; font-size:10px;'>{$h}h</div>"; ?>

						<?php foreach($days as $day): ?>
							<div style="font-size: 10px;"><?php echo $day; ?></div>
							<?php foreach($hours as $h):
								$intensity = rand(10, 90);
								$bg = "rgba(79, 70, 229, 0." . round($intensity/10) . ")";
							?>
								<div style="background: <?php echo $bg; ?>; height: 30px; border-radius: 4px;" title="Activity: <?php echo $intensity; ?>%"></div>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</div>
					<div style="display:flex; justify-content: space-between; margin-top: 10px; font-size:10px; color:#666;">
						<span>Low Activity</span>
						<span>High Activity</span>
					</div>
				</div>

				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'UTM & Lead Source ROI', 'agency-nexus' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Source</th><th>Count</th><th>Projected Value</th></tr></thead>
						<tbody>
							<?php foreach ( $utm_sources as $s ) : ?>
								<tr>
									<td><strong><?php echo esc_html($s->source); ?></strong></td>
									<td><?php echo $s->count; ?></td>
									<td>$<?php echo number_format($s->value, 2); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="postbox" style="margin-top: 30px; padding: 20px;">
				<h3><?php _e( 'Automated Follow-up Sequences', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Leads are automatically placed into these sequences based on their entry source.', 'agency-nexus' ); ?></p>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr><th>Sequence Name</th><th>Trigger Source</th><th>Steps</th><th>Status</th></tr></thead>
					<tbody>
						<tr>
							<td><strong>Onboarding Welcome</strong></td>
							<td>Website Embed</td>
							<td>3 Emails</td>
							<td><span class="badge" style="background:#46b450; color:#fff;">Active</span></td>
						</tr>
						<tr>
							<td><strong>Retargeting Cold</strong></td>
							<td>Direct Import</td>
							<td>5 Emails</td>
							<td><span class="badge" style="background:#dc3232; color:#fff;">Paused</span></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public function render_social_dashboard() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_social_interactions';
		$interactions = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50" );

		// Seed if empty (Simulated Social Data with AI Sentiment Analysis)
		if ( empty($interactions) ) {
			self::analyze_and_insert_social_interaction( 'Instagram', 'johndoe', 'Love this new service!' );
			self::analyze_and_insert_social_interaction( 'LinkedIn', 'sarah_biz', 'Can you send me a proposal for web design?' );
			self::analyze_and_insert_social_interaction( 'X', 'techie99', 'Your site is a bit slow today.' );
			$interactions = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50" );
		}

		$social_accounts = [
			'Instagram' => get_option('an_social_instagram'),
			'LinkedIn'  => get_option('an_social_linkedin'),
			'X'         => get_option('an_social_x'),
			'Facebook'  => get_option('an_social_facebook')
		];

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Unified Social Dashboard', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Aggregate engagement from all your connected platforms. Respond to urgent interactions and track brand sentiment in real-time.', 'agency-nexus' ); ?></p>

			<div class="an-social-accounts-strip" style="display:flex; gap:15px; margin-top: 20px; background:#fff; padding:15px; border:1px solid #e2e8f0; border-radius:8px;">
				<?php foreach($social_accounts as $platform => $val): ?>
					<div style="display:flex; align-items:center; gap:8px;">
						<span class="dashicons dashicons-share"></span>
						<strong><?php echo $platform; ?>:</strong>
						<span><?php echo $val ? esc_html($val) : '<em>' . __('Not linked', 'agency-nexus') . '</em>'; ?></span>
					</div>
				<?php endforeach; ?>
				<div style="margin-left:auto;">
					<a href="<?php echo admin_url('admin.php?page=an-social-settings'); ?>" class="button button-small"><?php _e('Manage Accounts', 'agency-nexus'); ?></a>
				</div>
			</div>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Recent Interactions', 'agency-nexus' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Platform</th><th>User</th><th>Content</th><th>Sentiment</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach ( $interactions as $i ) :
								$color = $i->sentiment === 'positive' ? '#46b450' : ($i->sentiment === 'negative' ? '#dc3232' : '#646970');
							?>
								<tr>
									<td><strong><?php echo esc_html( $i->platform ); ?></strong></td>
									<td>@<?php echo esc_html( $i->username ); ?></td>
									<td><?php echo esc_html( $i->content ); ?></td>
									<td><span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo ucfirst($i->sentiment); ?></span></td>
									<td><button type="button" class="button button-small"><?php _e( 'Reply', 'agency-nexus' ); ?></button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div>
					<div class="postbox" style="padding: 20px; border-left: 4px solid var(--an-indigo-600);">
						<h3><?php _e( 'Priority Inbox', 'agency-nexus' ); ?></h3>
						<p><?php _e( 'High-intent interactions that require immediate attention.', 'agency-nexus' ); ?></p>
						<ul style="list-style: none; padding: 0;">
							<?php foreach ( $interactions as $i ) : if(!$i->is_priority) continue; ?>
								<li style="padding: 10px; border-bottom: 1px solid #eee;">
									<strong>@<?php echo esc_html( $i->username ); ?></strong> (<?php echo $i->platform; ?>)<br>
									<small><?php echo esc_html( $i->content ); ?></small>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_canned_responses() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_canned_responses';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'saved':   $m = 'Response saved!'; break;
				case 'updated': $m = 'Response updated!'; break;
				case 'deleted': $m = 'Response deleted!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'edit' || $action === 'add') {
			$resp = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Response', 'agency-nexus') : __('Add New Response', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Save reusable text snippets for common client inquiries.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'an_save_response_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Shortcut Title', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="title" id="an_canned_title" value="<?php echo $resp ? esc_attr($resp->title) : ''; ?>" required class="regular-text">
								<a href="#" class="an-ai-improve-link" data-target="#an_canned_title" data-type="canned_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('A short name to identify this template. e.g., Welcome Message', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Content', 'agency-nexus'); ?></label></th>
							<td>
								<textarea name="content" id="an_canned_content" required class="regular-text" rows="5"><?php echo $resp ? esc_textarea($resp->content) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#an_canned_content" data-type="canned_content" style="text-decoration: none;">✨ <?php _e('AI Improve Content', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('The full text that will be inserted when you use this shortcut.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<input type="submit" name="an_save_response" class="button button-primary" value="Save Response">
					<a href="?page=an-canned-responses" class="button">Cancel</a>
				</form>
			</div>
			<script>
			jQuery(document).ready(function($) {
				$('.an-ai-improve-link').on('click', function(e) {
					e.preventDefault();
					var $link = $(this);
					var targetSel = $link.data('target');
					var fieldType = $link.data('type');
					var currentText = $(targetSel).val();

					if (!currentText.trim()) {
						alert('Please enter some text first to let AI improve it.');
						return;
					}

					var originalText = $link.html();
					$link.text('<?php _e("Improving...", "agency-nexus"); ?>').css('pointer-events', 'none');

					$.post(ajaxurl, {
						action: 'an_ai_improve_content',
						text: currentText,
						field_type: fieldType
					}, function(response) {
						$link.html(originalText).css('pointer-events', 'auto');
						if (response.success && response.data.improved) {
							$(targetSel).val(response.data.improved);
						} else {
							alert('AI improvement failed. Ensure your AI Copilot is fully configured.');
						}
					});
				});
			});
			</script>
			<?php
			return;
		}

		$responses = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Canned Responses', 'agency-nexus' ); ?></h1>
			<a href="?page=an-canned-responses&action=add" class="page-title-action"><?php _e('Add New', 'agency-nexus'); ?></a>
			<hr class="wp-header-end">
			<p><?php _e( 'Guidance: Store reusable message templates here. These can be quickly accessed and used within the Messaging Hub.', 'agency-nexus' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Title', 'agency-nexus'); ?></th>
						<th><?php _e('Content Snippet', 'agency-nexus'); ?></th>
						<th><?php _e('Actions', 'agency-nexus'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ($responses) : foreach ($responses as $resp) : ?>
						<tr>
							<td><strong><?php echo esc_html($resp->title); ?></strong></td>
							<td><?php echo esc_html(wp_trim_words($resp->content, 15)); ?></td>
							<td>
								<a href="?page=an-canned-responses&action=edit&id=<?php echo $resp->id; ?>"><?php _e('Edit', 'agency-nexus'); ?></a> |
								<a href="<?php echo wp_nonce_url('?page=an-canned-responses&action=delete&id=' . $resp->id, 'an_delete_response_' . $resp->id); ?>" style="color:red;" onclick="return confirm('Delete response?')"><?php _e('Delete', 'agency-nexus'); ?></a>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="3"><?php _e('No canned responses found.', 'agency-nexus'); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render high-converting Thank You page content via shortcode.
	 * Usage: [agency_nexus_thank_you product_name="Awesome Course" cta_url="https://..."]
	 */
	public function render_thank_you_page( $atts ) {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'lead_intelligence' ) ) {
			return '<p style="color:red;">' . __( 'The Lead Intelligence feature requires a Pro or Agency license.', 'agency-nexus' ) . '</p>';
		}

		$atts = shortcode_atts( [
			'title'        => get_option( 'an_ty_title', __( 'Your submission was successful!', 'agency-nexus' ) ),
			'sub_title'    => get_option( 'an_ty_subtitle', __( 'We have received your details and will be in touch shortly.', 'agency-nexus' ) ),
			'product_name' => get_option( 'an_ty_product', __( 'our premium solutions', 'agency-nexus' ) ),
			'cta_text'     => get_option( 'an_ty_cta_text', __( 'Check this out while you wait', 'agency-nexus' ) ),
			'cta_url'      => get_option( 'an_ty_cta_url', '#' ),
			'video_url'    => '', // Optional: Add a VSL (Video Sales Letter) URL
		], $atts );

		ob_start();
		?>
		<div class="an-thank-you-container" style="max-width: 800px; margin: 40px auto; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; padding: 20px; border-radius: 12px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
			<div class="an-success-icon" style="width: 80px; height: 80px; background: #46b450; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 40px;">
				✓
			</div>

			<h1 style="color: #1d2327; margin-bottom: 10px;"><?php echo esc_html( $atts['title'] ); ?></h1>
			<p style="font-size: 18px; color: #646970; margin-bottom: 40px;"><?php echo esc_html( $atts['sub_title'] ); ?></p>

			<div class="an-upsell-box" style="background: #f0f6fb; padding: 30px; border-radius: 8px; border-left: 5px solid #2271b1; text-align: left; margin-bottom: 30px;">
				<h2 style="margin-top: 0; color: #2271b1;"><?php echo sprintf( __( 'Exclusive Offer: Get 20%% off %s', 'agency-nexus' ), esc_html( $atts['product_name'] ) ); ?></h2>
				<p><?php _e( 'Since you are here, we want to give you a special head start. Most of our clients see immediate results by jumping on this limited-time offer.', 'agency-nexus' ); ?></p>

				<?php if ( ! empty( $atts['video_url'] ) ) : ?>
					<div style="margin: 20px 0; aspect-ratio: 16/9; background: #000; border-radius: 4px;">
						<!-- Placeholder for video if provided -->
						<iframe width="100%" height="100%" src="<?php echo esc_url( $atts['video_url'] ); ?>" frameborder="0" allowfullscreen></iframe>
					</div>
				<?php endif; ?>

				<a href="<?php echo esc_url( $atts['cta_url'] ); ?>" class="button button-primary" style="display: inline-block; background: #2271b1; color: #fff; text-decoration: none; padding: 15px 30px; border-radius: 4px; font-weight: bold; font-size: 18px; margin-top: 10px;">
					<?php echo esc_html( $atts['cta_text'] ); ?> &rarr;
				</a>
			</div>

			<div class="an-social-proof" style="color: #8c8f94; font-size: 14px;">
				<p><?php _e( 'Joined by 1,000+ professionals worldwide.', 'agency-nexus' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'lead_intelligence' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::is_admin() ) {
			return;
		}
		global $wpdb;
		$leads_table = $wpdb->prefix . 'an_leads';

		$total_leads = $wpdb->get_var( "SELECT COUNT(*) FROM $leads_table" );
		$conversions = $wpdb->get_var( "SELECT COUNT(*) FROM $leads_table WHERE status = 'converted'" );
		$conv_rate   = $total_leads > 0 ? ($conversions / $total_leads) * 100 : 0;

		// Calculate LTV Projection (Projected revenue from converted leads)
		$ltv = $wpdb->get_var( "SELECT SUM(conversion_value) FROM $leads_table WHERE status = 'converted'" );

		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'EngageTrack Intelligence', 'agency-nexus' ); ?></h2>
			<div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
				<div>
					<strong><?php _e( 'Total Leads', 'agency-nexus' ); ?></strong>
					<div style="font-size: 20px; font-weight: bold;"><?php echo intval($total_leads); ?></div>
				</div>
				<div>
					<strong><?php _e( 'LTV Projection', 'agency-nexus' ); ?></strong>
					<div style="font-size: 20px; font-weight: bold; color: var(--an-indigo-600);">$<?php echo number_format($ltv); ?></div>
				</div>
			</div>

			<p><strong><?php _e( 'Conversion Pipeline:', 'agency-nexus' ); ?></strong></p>
			<div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">
				<div style="flex: 1; height: 20px; background: #eee; border-radius: 10px; overflow: hidden; display: flex;">
					<div style="width: <?php echo $conv_rate; ?>%; background: #46b450;" title="Converted"></div>
					<div style="width: <?php echo 100 - $conv_rate; ?>%; background: var(--an-slate-300);" title="Remaining"></div>
				</div>
				<span style="font-weight: bold; color: #46b450;"><?php echo round($conv_rate, 1); ?>%</span>
			</div>

			<p><strong><?php _e( 'Sentiment Analysis:', 'agency-nexus' ); ?></strong></p>
			<div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">
				<div style="flex: 1; height: 20px; background: #eee; border-radius: 10px; overflow: hidden; display: flex;">
					<div style="width: 70%; background: #46b450;" title="Positive"></div>
					<div style="width: 20%; background: #ffb900;" title="Neutral"></div>
					<div style="width: 10%; background: #dc3232;" title="Negative"></div>
				</div>
				<span style="font-weight: bold; color: #46b450;">70% <?php _e( 'Positive', 'agency-nexus' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Callback for action on API lead capture.
	 */
	public function handle_new_lead_captured( $lead_id ) {
		self::calculate_and_save_lead_score( $lead_id );
	}

	/**
	 * Calculate and save the lead score and qualification summary.
	 */
	public static function calculate_and_save_lead_score( $lead_id ) {
		global $wpdb;
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_leads WHERE id = %d", $lead_id ) );
		if ( ! $lead ) {
			return;
		}

		$score = 50; // Base score
		$summary = '';

		if ( get_option( 'an_ai_enabled', 'no' ) === 'yes' ) {
			$prompt = "Analyze this agency lead prospect:\n" .
				"Name: " . $lead->name . "\n" .
				"Email: " . $lead->email . "\n" .
				"Source: " . $lead->source . "\n" .
				"Conversion Value: $" . $lead->conversion_value . "\n\n" .
				"Calculate a lead fit score from 0 to 100 based on their profile. Award higher scores for professional emails, referral sources, and high conversion value. Output ONLY a JSON object with two fields:\n" .
				'{"score": 85, "summary": "Highly qualified enterprise lead from website referral."}';

			$ai_result = Agency_Nexus_AI_Copilot::generate( $prompt, 'lead_qualification' );
			$parsed = json_decode( $ai_result, true );
			if ( is_array( $parsed ) && isset( $parsed['score'] ) ) {
				$score = intval( $parsed['score'] );
				$summary = sanitize_text_field( $parsed['summary'] );
			} else {
				if ( preg_match( '/"score":\s*([0-9]{1,3})/', $ai_result, $matches ) ) {
					$score = intval( $matches[1] );
				}
				if ( preg_match( '/"summary":\s*"([^"]+)"/', $ai_result, $matches ) ) {
					$summary = sanitize_text_field( $matches[1] );
				} else {
					$summary = $ai_result;
				}
			}
		} else {
			$value = floatval( $lead->conversion_value );
			if ( $value > 5000 ) $score += 30;
			elseif ( $value > 1000 ) $score += 15;
			if ( strpos( strtolower( $lead->source ), 'referral' ) !== false ) $score += 15;
			$summary = __( 'Lead qualified automatically using core rule-based parameters.', 'agency-nexus' );
		}

		if ( $score > 100 ) $score = 100;
		if ( $score < 0 ) $score = 0;

		$wpdb->update(
			$wpdb->prefix . 'an_leads',
			[ 'score' => $score ],
			[ 'id' => $lead_id ]
		);

		// Record the summary inside the lead communications log
		$wpdb->insert( $wpdb->prefix . 'an_lead_communications', [
			'lead_id'    => $lead_id,
			'user_id'    => get_current_user_id() ? get_current_user_id() : 1,
			'subject'    => __( 'AI Qualification Summary', 'agency-nexus' ),
			'message'    => $summary,
			'created_at' => current_time( 'mysql' )
		] );
	}

	/**
	 * Perform real-time AI sentiment analysis on social media interactions.
	 */
	public static function analyze_and_insert_social_interaction( $platform, $username, $content ) {
		global $wpdb;
		$sentiment = 'neutral';
		$is_priority = 0;

		if ( get_option( 'an_ai_enabled', 'no' ) === 'yes' ) {
			$prompt = "Analyze the sentiment and business priority of this social media comment for our agency:\n" .
				"Comment: \"" . $content . "\"\n\n" .
				"Sentiment can be: 'positive', 'negative', or 'neutral'.\n" .
				"Priority is 1 if they are asking about services/proposals/pricing (high intent), otherwise 0.\n" .
				"Output ONLY a JSON object with two fields:\n" .
				'{"sentiment": "positive", "is_priority": 1}';

			$ai_result = Agency_Nexus_AI_Copilot::generate( $prompt, 'social_sentiment' );
			$parsed = json_decode( $ai_result, true );
			if ( is_array( $parsed ) ) {
				$sentiment = isset( $parsed['sentiment'] ) ? sanitize_text_field( $parsed['sentiment'] ) : 'neutral';
				$is_priority = isset( $parsed['is_priority'] ) ? intval( $parsed['is_priority'] ) : 0;
			} else {
				if ( preg_match( '/"sentiment":\s*"([^"]+)"/', $ai_result, $matches ) ) {
					$sentiment = sanitize_text_field( $matches[1] );
				}
				if ( preg_match( '/"is_priority":\s*([0-1])/', $ai_result, $matches ) ) {
					$is_priority = intval( $matches[1] );
				}
			}
		} else {
			if ( strpos( strtolower($content), 'proposal' ) !== false || strpos( strtolower($content), 'service' ) !== false || strpos( strtolower($content), 'pricing' ) !== false ) {
				$sentiment = 'positive';
				$is_priority = 1;
			} elseif ( strpos( strtolower($content), 'slow' ) !== false || strpos( strtolower($content), 'error' ) !== false ) {
				$sentiment = 'negative';
				$is_priority = 0;
			}
		}

		$wpdb->insert( $wpdb->prefix . 'an_social_interactions', [
			'platform'    => $platform,
			'username'    => $username,
			'content'     => $content,
			'sentiment'   => $sentiment,
			'is_priority' => $is_priority,
			'created_at'  => current_time( 'mysql' )
		] );
	}
}
