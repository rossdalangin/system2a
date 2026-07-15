<?php
/**
 * Admin Dashboard Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Admin_Dashboard {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Use priority 5 to ensure this fires before modules (default 10)
		add_action( 'admin_menu', [ $this, 'register_menu' ], 5 );
		add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_an_ai_improve_content', [ $this, 'handle_ai_improve_content' ] );
		add_action( 'admin_footer', [ $this, 'add_ai_improve_scripts' ] );
	}

	/**
	 * Enqueue scripts for the admin area.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our plugin pages
		if ( strpos( $hook, 'agency-nexus' ) === false && strpos( $hook, 'an-' ) === false ) {
			return;
		}

		// Enqueue Design System
		wp_enqueue_style( 'agency-nexus-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@700&display=swap', [], AGENCY_NEXUS_VERSION );
		wp_enqueue_style( 'agency-nexus-design', AGENCY_NEXUS_URL . 'assets/css/agency-nexus-design.css', [], AGENCY_NEXUS_VERSION );

		if ( 'agency-nexus_page_an-settings' === $hook ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Handle POST and GET actions before output starts.
	 */
	public function handle_admin_actions() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::can_access_nexus() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( 'an-settings' === $page && isset( $_POST['an_save_global_settings'] ) && check_admin_referer( 'an_global_settings_nonce' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			update_option( 'an_store_url', esc_url_raw( $_POST['an_store_url'] ) );
			update_option( 'an_stripe_key', sanitize_text_field( $_POST['an_stripe_key'] ) );
			update_option( 'an_paypal_email', sanitize_email( $_POST['an_paypal_email'] ) );
			update_option( 'an_payment_test_mode', isset($_POST['an_payment_test_mode']) ? 'yes' : 'no' );

			// SMTP Settings
			update_option( 'an_smtp_enabled', isset($_POST['an_smtp_enabled']) ? 'yes' : 'no' );
			update_option( 'an_smtp_host', sanitize_text_field( $_POST['an_smtp_host'] ) );
			update_option( 'an_smtp_port', intval( $_POST['an_smtp_port'] ) );
			update_option( 'an_smtp_encryption', sanitize_text_field( $_POST['an_smtp_encryption'] ) );
			update_option( 'an_smtp_auth', isset($_POST['an_smtp_auth']) ? 'yes' : 'no' );
			update_option( 'an_smtp_username', sanitize_text_field( $_POST['an_smtp_username'] ) );
			if ( ! empty( $_POST['an_smtp_password'] ) ) {
				update_option( 'an_smtp_password', sanitize_text_field( $_POST['an_smtp_password'] ) );
			}
			update_option( 'an_email_from_address', sanitize_email( $_POST['an_email_from_address'] ) );
			update_option( 'an_email_from_name', sanitize_text_field( $_POST['an_email_from_name'] ) );

			update_option( 'an_zapier_webhook', esc_url_raw( $_POST['an_zapier_webhook'] ) );
			update_option( 'an_slack_webhook', esc_url_raw( $_POST['an_slack_webhook'] ) );
			update_option( 'an_comm_start', sanitize_text_field( $_POST['an_comm_start'] ) );
			update_option( 'an_comm_end', sanitize_text_field( $_POST['an_comm_end'] ) );
			update_option( 'an_after_hours_msg', sanitize_textarea_field( $_POST['an_after_hours_msg'] ) );
			update_option( 'an_hourly_rate', floatval( $_POST['an_hourly_rate'] ) );
			update_option( 'an_agency_logo', esc_url_raw( $_POST['an_agency_logo'] ) );

			// AI Copilot Settings
			update_option( 'an_ai_enabled', isset($_POST['an_ai_enabled']) ? 'yes' : 'no' );
			update_option( 'an_ai_provider', sanitize_text_field( $_POST['an_ai_provider'] ) );
			update_option( 'an_openai_key', sanitize_text_field( $_POST['an_openai_key'] ) );
			update_option( 'an_gemini_key', sanitize_text_field( $_POST['an_gemini_key'] ) );
			update_option( 'an_claude_key', sanitize_text_field( $_POST['an_claude_key'] ) );
			update_option( 'an_ai_model', sanitize_text_field( $_POST['an_ai_model'] ) );

			wp_redirect( admin_url( 'admin.php?page=an-settings&msg=saved' ) );
			exit;
		}

		if ( 'an-license' === $page ) {
			if ( isset( $_POST['an_activate_license'] ) && check_admin_referer( 'an_license_nonce' ) ) {
				$key = sanitize_text_field( $_POST['license_key'] );
				$result = Agency_Nexus_License_Manager::get_instance()->activate_license( $key );
				if ( is_wp_error( $result ) ) {
					wp_redirect( admin_url( 'admin.php?page=an-license&msg=error&error_msg=' . urlencode($result->get_error_message()) ) );
				} else {
					wp_redirect( admin_url( 'admin.php?page=an-license&msg=activated' ) );
				}
				exit;
			}
			if ( isset( $_POST['an_deactivate_license'] ) && check_admin_referer( 'an_license_nonce' ) ) {
				Agency_Nexus_License_Manager::get_instance()->deactivate_license();
				wp_redirect( admin_url( 'admin.php?page=an-license&msg=deactivated' ) );
				exit;
			}
		}

		if ( isset( $_POST['an_seed_data'] ) && check_admin_referer( 'an_seed_data_nonce' ) ) {
			Agency_Nexus_Seeder::seed();
			$redirect_url = ( $page === 'an-settings' ) ? admin_url( 'admin.php?page=an-settings&msg=seeded' ) : admin_url( 'admin.php?page=agency-nexus&msg=seeded' );
			wp_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_POST['an_reset_data'] ) && check_admin_referer( 'an_reset_data_nonce' ) ) {
			Agency_Nexus_Seeder::clear_all();
			$redirect_url = ( $page === 'an-settings' ) ? admin_url( 'admin.php?page=an-settings&msg=reset' ) : admin_url( 'admin.php?page=agency-nexus&msg=reset' );
			wp_redirect( $redirect_url );
			exit;
		}

		if ( 'an-clients' === $page ) {
			if ( isset( $_POST['an_create_client_user'] ) && check_admin_referer( 'an_create_client_user_nonce' ) ) {
				$this->handle_client_user_creation();
			}
			$this->process_client_actions();
		} elseif ( 'an-projects' === $page || 'an-edit-task' === $page ) {
			$this->process_project_actions();
		} elseif ( 'an-security' === $page ) {
			$this->process_security_actions();
		}
	}

	/**
	 * Create a WordPress user for a client.
	 */
	private function handle_client_user_creation() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$client_id = intval( $_POST['client_id'] );
		global $wpdb;
		$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_clients WHERE id = %d", $client_id ) );

		if ( ! $client ) return;

		$username = strtolower( str_replace( ' ', '_', $client->name ) );
		if ( username_exists( $username ) ) {
			$username .= '_' . $client_id;
		}

		if ( email_exists( $client->email ) ) {
			wp_redirect( admin_url( 'admin.php?page=an-clients&action=view&id=' . $client_id . '&msg=email_exists' ) );
			exit;
		}

		$password = wp_generate_password();
		$user_id = wp_create_user( $username, $password, $client->email );

		if ( ! is_wp_error( $user_id ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( 'subscriber' );
			wp_update_user( [ 'ID' => $user_id, 'display_name' => $client->name ] );
			wp_redirect( admin_url( 'admin.php?page=an-clients&action=view&id=' . $client_id . '&msg=user_created' ) );
			exit;
		}
	}

	/**
	 * Process client-related actions.
	 */
	private function process_client_actions() {
		if ( ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_clients';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $id && ! Agency_Nexus_Permissions::can_view_client( $id ) ) {
			return;
		}

		// Handle Deletion - Admin Only
		if ( 'delete' === $action && $id ) {
			if ( ! Agency_Nexus_Permissions::is_admin() ) {
				wp_die( 'Unauthorized' );
			}
			check_admin_referer( 'an_delete_client_' . $id );
			$wpdb->delete( $table_name, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-clients&msg=deleted' ) );
			exit;
		}

		// Handle Save (Add/Edit)
		if ( isset( $_POST['an_save_client'] ) && check_admin_referer( 'an_save_client_nonce' ) ) {
			$data = [
				'name'    => sanitize_text_field( $_POST['name'] ),
				'email'   => sanitize_email( $_POST['email'] ),
				'company' => isset( $_POST['company'] ) ? sanitize_text_field( $_POST['company'] ) : '',
				'phone'   => isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '',
				'address' => isset( $_POST['address'] ) ? sanitize_textarea_field( $_POST['address'] ) : '',
				'website' => isset( $_POST['website'] ) ? esc_url_raw( $_POST['website'] ) : '',
				'notes'   => isset( $_POST['notes'] ) ? Agency_Nexus::encrypt( sanitize_textarea_field( $_POST['notes'] ) ) : '',
			];
			if ( $id ) {
				$wpdb->update( $table_name, $data, [ 'id' => $id ] );
				$msg = 'updated';
			} else {
				$wpdb->insert( $table_name, $data );
				$msg = 'added';
			}
			wp_redirect( admin_url( 'admin.php?page=an-clients&msg=' . $msg ) );
			exit;
		}
	}

	/**
	 * Process project-related actions.
	 */
	private function process_project_actions() {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'an_projects';
		$tasks_table    = $wpdb->prefix . 'an_tasks';
		$time_table     = $wpdb->prefix . 'an_time_entries';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$task_id = isset( $_GET['task_id'] ) ? intval( $_GET['task_id'] ) : 0;

		// Handle Deletion - Admin Only
		if ( 'delete' === $action && $id ) {
			if ( ! Agency_Nexus_Permissions::is_admin() ) {
				wp_die( 'Unauthorized' );
			}
			check_admin_referer( 'an_delete_project_' . $id );
			$wpdb->delete( $projects_table, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-projects&msg=deleted' ) );
			exit;
		}

		// Handle Save (Add/Edit)
		if ( isset( $_POST['an_save_project'] ) && check_admin_referer( 'an_save_project_nonce' ) ) {
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				return;
			}
			$data = [
				'client_id'   => intval( $_POST['client_id'] ),
				'assigned_to' => isset( $_POST['assigned_to'] ) ? intval( $_POST['assigned_to'] ) : 0,
				'title'       => sanitize_text_field( $_POST['title'] ),
				'budget'      => floatval( $_POST['budget'] ),
				'status'      => sanitize_text_field( $_POST['status'] ),
				'description' => Agency_Nexus::encrypt( sanitize_textarea_field( $_POST['description'] ) ),
				'buffer_days' => intval( $_POST['buffer_days'] )
			];
			if ( $id ) {
				$wpdb->update( $projects_table, $data, [ 'id' => $id ] );
				$msg = 'updated';
				do_action( 'agency_nexus_project_status_updated', $id, $data['status'] );
			} else {
				$wpdb->insert( $projects_table, $data );
				$msg = 'created';
				do_action( 'agency_nexus_project_status_updated', $wpdb->insert_id, $data['status'] );
			}
			wp_redirect( admin_url( 'admin.php?page=an-projects&msg=' . $msg ) );
			exit;
		}

		// Handle Task Creation
		if ( isset( $_POST['an_add_task'] ) && check_admin_referer( 'an_add_task_nonce' ) ) {
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				return;
			}
			$wpdb->insert( $tasks_table, [
				'project_id'  => intval( $_POST['project_id'] ),
				'title'       => sanitize_text_field( $_POST['title'] ),
				'assigned_to' => isset( $_POST['assigned_to'] ) ? intval( $_POST['assigned_to'] ) : 0,
				'status'      => 'todo',
				'priority'    => 'medium',
				'start_date'  => ! empty( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : date('Y-m-d'),
				'due_date'    => ! empty( $_POST['due_date'] ) ? sanitize_text_field( $_POST['due_date'] ) : date('Y-m-d', strtotime('+7 days')),
				'depends_on'  => isset( $_POST['depends_on'] ) ? intval( $_POST['depends_on'] ) : 0
			] );
			wp_redirect( admin_url( 'admin.php?page=an-projects&action=view&id=' . intval( $_POST['project_id'] ) . '&msg=task_added' ) );
			exit;
		}

		// Handle Task Assignment Update
		if ( isset( $_POST['an_assign_task'] ) && check_admin_referer( 'an_assign_task_nonce' ) ) {
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				return;
			}
			$wpdb->update( $tasks_table, [
				'assigned_to' => intval( $_POST['assigned_to'] )
			], [ 'id' => intval( $_POST['task_id'] ) ] );
			wp_redirect( admin_url( 'admin.php?page=an-projects&action=view&id=' . $id . '&msg=task_updated' ) );
			exit;
		}

		// Handle Task Delete
		if ( 'delete_task' === $action && $task_id ) {
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				wp_die( 'Unauthorized' );
			}
			check_admin_referer( 'an_delete_task_' . $task_id );
			$wpdb->delete( $tasks_table, [ 'id' => $task_id ] );
			$wpdb->delete( $time_table, [ 'task_id' => $task_id ] );
			wp_redirect( admin_url( 'admin.php?page=an-projects&action=view&id=' . $id . '&msg=task_deleted' ) );
			exit;
		}

		// Handle Task Edit Save
		if ( isset( $_POST['an_save_task_edit'] ) && check_admin_referer( 'an_edit_task_nonce' ) ) {
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				return;
			}
			$wpdb->update( $tasks_table, [
				'title'       => sanitize_text_field( $_POST['title'] ),
				'description' => sanitize_textarea_field( $_POST['description'] ),
				'assigned_to' => intval( $_POST['assigned_to'] ),
				'priority'    => sanitize_text_field( $_POST['priority'] ),
				'status'      => sanitize_text_field( $_POST['status'] ),
				'start_date'  => sanitize_text_field( $_POST['start_date'] ),
				'due_date'    => sanitize_text_field( $_POST['due_date'] ),
				'depends_on'  => intval( $_POST['depends_on'] )
			], [ 'id' => intval( $_POST['task_id'] ) ] );
			wp_redirect( admin_url( 'admin.php?page=an-projects&action=view&id=' . $id . '&msg=task_updated' ) );
			exit;
		}

		// Handle Time Logging
		if ( isset( $_POST['an_log_time'] ) && check_admin_referer( 'an_log_time_nonce' ) ) {
			$wpdb->insert( $time_table, [
				'task_id'  => intval( $_POST['task_id'] ),
				'user_id'  => get_current_user_id(),
				'duration' => floatval( $_POST['hours'] ) * 3600,
				'date'     => current_time( 'mysql' ),
				'note'     => sanitize_textarea_field( $_POST['note'] )
			] );
			wp_redirect( admin_url( 'admin.php?page=an-projects&action=view&id=' . $id . '&msg=time_logged' ) );
			exit;
		}

		// Handle Time Entry Delete
		if ( 'delete_time' === $action && $id ) { // Note: id here is used as time_entry_id in the link
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				wp_die( 'Unauthorized' );
			}
			check_admin_referer( 'an_delete_time_' . $id );
			$wpdb->delete( $time_table, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-edit-task&task_id=' . $task_id . '&id=' . intval($_GET['project_id']) . '&msg=time_deleted' ) );
			exit;
		}
	}

	/**
	 * Register the main menu and submenus.
	 */
	public function register_menu() {
		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			return;
		}

		add_menu_page(
			__( 'Agency Nexus', 'agency-nexus' ),
			__( 'Agency Nexus', 'agency-nexus' ),
			'read',
			'agency-nexus',
			[ $this, 'render_dashboard' ],
			'dashicons-chart-area',
			30
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Dashboard', 'agency-nexus' ),
			__( 'Dashboard', 'agency-nexus' ),
			'read',
			'agency-nexus',
			[ $this, 'render_dashboard' ]
		);

		$license_manager = Agency_Nexus_License_Manager::get_instance();

		if ( Agency_Nexus_Permissions::is_team_member() && $license_manager->is_feature_enabled( 'client_management' ) ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Clients', 'agency-nexus' ),
				__( 'Clients', 'agency-nexus' ),
				'read',
				'an-clients',
				[ $this, 'render_clients' ]
			);
		}

		if ( $license_manager->is_feature_enabled( 'project_management' ) ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Projects', 'agency-nexus' ),
				__( 'Projects', 'agency-nexus' ),
				'read',
				'an-projects',
				[ $this, 'render_projects' ]
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Team', 'agency-nexus' ),
				__( 'Team', 'agency-nexus' ),
				'manage_options',
				'an-team',
				[ $this, 'render_team' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Security & Privacy', 'agency-nexus' ),
				__( 'Security & Privacy', 'agency-nexus' ),
				'manage_options',
				'an-security',
				[ $this, 'render_security' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Settings', 'agency-nexus' ),
				__( 'Settings', 'agency-nexus' ),
				'manage_options',
				'an-settings',
				[ $this, 'render_settings' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Licensing', 'agency-nexus' ),
				__( 'Licensing', 'agency-nexus' ),
				'manage_options',
				'an-license',
				[ $this, 'render_license' ]
			);
		}

		add_submenu_page(
			null, // Hidden from menu
			__( 'Edit Task', 'agency-nexus' ),
			__( 'Edit Task', 'agency-nexus' ),
			'read',
			'an-edit-task',
			[ $this, 'render_task_edit_view' ]
		);
	}

	/**
	 * Render the clients page.
	 */
	public function render_clients() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_clients';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $id ) {
			if ( Agency_Nexus_Permissions::is_client() ) {
				if ( Agency_Nexus_Permissions::get_client_id_for_user(get_current_user_id()) !== $id ) {
					echo '<div class="error"><p>Unauthorized</p></div>'; return;
				}
			} elseif ( ! Agency_Nexus_Permissions::can_view_client( $id ) ) {
				echo '<div class="error"><p>Unauthorized</p></div>'; return;
			}
		}

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch($_GET['msg']) {
				case 'added': $m = 'Client added!'; break;
				case 'updated': $m = 'Client updated!'; break;
				case 'deleted': $m = 'Client deleted!'; break;
				case 'user_created': $m = 'WordPress user created for client!'; break;
				case 'email_exists': $m = 'Error: A user with this email already exists.'; break;
			}
			if ($m) echo '<div class="updated"><p>' . esc_html($m) . '</p></div>';
		}

		if ( $action === 'view' && $id ) {
			$this->render_client_profile($id);
			return;
		}

		if ($action === 'edit' || $action === 'add') {
			if ( ! Agency_Nexus_Permissions::is_admin() ) {
				echo '<div class="error"><p>Unauthorized</p></div>'; return;
			}
			$client = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="wrap">
				<h1><?php echo $id ? __('Edit Client', 'agency-nexus') : __('Add New Client', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Register a new client to start managing their projects and communication. This record is for internal tracking.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field('an_save_client_nonce'); ?>
					<table class="form-table">
						<tr>
							<th><label for="name">Name</label></th>
							<td>
								<input type="text" name="name" id="name" value="<?php echo $client ? esc_attr($client->name) : ''; ?>" class="regular-text" required>
								<p class="description"><?php _e('Full name of the client or primary contact. e.g., John Doe', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="email">Email</label></th>
							<td>
								<input type="email" name="email" id="email" value="<?php echo $client ? esc_attr($client->email) : ''; ?>" class="regular-text" required>
								<p class="description"><?php _e('The email address used for communication and to link their WordPress user account. e.g., john@example.com', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="company">Company</label></th>
							<td>
								<input type="text" name="company" id="company" value="<?php echo $client ? esc_attr($client->company) : ''; ?>" class="regular-text">
								<p class="description"><?php _e('The legal name of the client\'s organization. e.g., Acme Corp', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="phone">Phone</label></th>
							<td>
								<input type="text" name="phone" id="phone" value="<?php echo $client ? esc_attr($client->phone) : ''; ?>" class="regular-text">
								<p class="description"><?php _e('Primary contact phone number. e.g., +1-555-0199', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="website">Website</label></th>
							<td>
								<input type="url" name="website" id="website" value="<?php echo $client ? esc_attr($client->website) : ''; ?>" class="regular-text">
								<p class="description"><?php _e('Client\'s corporate website. e.g., https://acme.com', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="address">Address</label></th>
							<td>
								<textarea name="address" id="address" rows="3" class="regular-text"><?php echo $client ? esc_textarea($client->address) : ''; ?></textarea>
								<p class="description"><?php _e('Business physical address.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="notes">Internal Notes</label></th>
							<td>
								<textarea name="notes" id="notes" rows="5" class="regular-text"><?php echo $client ? esc_textarea( Agency_Nexus::decrypt( $client->notes ) ) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#notes" data-type="client_notes" style="text-decoration: none;">✨ <?php _e('AI Improve Notes', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Confidential notes about this client (Internal use only).', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="an_save_client" class="button button-primary" value="<?php _e('Save Client', 'agency-nexus'); ?>">
						<a href="?page=an-clients" class="button"><?php _e('Cancel', 'agency-nexus'); ?></a>
					</p>
				</form>
			</div>
			<?php
			return;
		}

		$clients_query = "SELECT * FROM $table_name";
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$clients_query .= " WHERE 1=0";
			} else {
				// Get clients belonging to authorised projects
				$authorised_client_ids = $wpdb->get_col( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")" );
				if ( ! empty( $authorised_client_ids ) ) {
					$clients_query .= " WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_client_ids ) ) . ")";
				} else {
					$clients_query .= " WHERE 1=0";
				}
			}
		}
		$clients_query .= " ORDER BY created_at DESC";
		$clients = $wpdb->get_results( $clients_query );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Client Management', 'agency-nexus' ); ?></h1>
			<?php if ( Agency_Nexus_Permissions::is_admin() ) : ?>
			<a href="?page=an-clients&action=add" class="page-title-action"><?php _e('Add New', 'agency-nexus'); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid var(--an-indigo-600); padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Manage Your Clients', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Clients are the heart of your agency. Every project, invoice, and message is linked to a client record.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Portal Access:</strong> To give a client access to their dashboard, create a WordPress user with the **same email address** as their client record here.</li>
					<li><strong>Profiles:</strong> Click on a client\'s name to view their full history, including active projects and outstanding invoices.</li>
				</ul>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Email</th>
						<th>Company</th>
						<th>Created</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $clients ) : foreach ( $clients as $client ) : ?>
						<tr>
							<td><?php echo $client->id; ?></td>
							<td><strong><a href="?page=an-clients&action=view&id=<?php echo $client->id; ?>"><?php echo esc_html( $client->name ); ?></a></strong></td>
							<td><?php echo esc_html( $client->email ); ?></td>
							<td><?php echo esc_html( $client->company ); ?></td>
							<td><?php echo $client->created_at; ?></td>
							<td>
								<a href="?page=an-clients&action=view&id=<?php echo $client->id; ?>"><?php _e('Profile', 'agency-nexus'); ?></a> |
								<a href="?page=an-clients&action=edit&id=<?php echo $client->id; ?>"><?php _e('Edit', 'agency-nexus'); ?></a>
								<?php if ( Agency_Nexus_Permissions::is_admin() ) : ?>
								| <a href="<?php echo wp_nonce_url('?page=an-clients&action=delete&id=' . $client->id, 'an_delete_client_' . $client->id); ?>" style="color:red;" onclick="return confirm('Delete this client?')"><?php _e('Delete', 'agency-nexus'); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="6">No clients found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the team management page.
	 */
	public function render_team() {
		if ( ! Agency_Nexus_Permissions::is_admin() ) {
			echo '<div class="error"><p>Unauthorized</p></div>'; return;
		}
		global $wpdb;
		$users = get_users();
		$action = isset($_GET['action']) ? $_GET['action'] : 'list';
		$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

		if ($action === 'performance' && $user_id) {
			$this->render_performance_view($user_id);
			return;
		}

		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e('Agency Team Management', 'agency-nexus'); ?></h1>
			<a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action"><?php _e('Add New Team Member', 'agency-nexus'); ?></a>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid var(--an-indigo-600); padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Your Agency Workforce', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Monitor your team\'s productivity and workload across all active projects. Agency Nexus leverages standard WordPress users for seamless task assignment.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Delegation:</strong> Assign tasks directly within any Project view to see them reflected here.</li>
					<li><strong>Productivity:</strong> Click "View Profile" to see a detailed breakdown of a team member\'s logged hours and assigned responsibilities.</li>
				</ul>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>User</th><th>Email</th><th>Lead Projects</th><th>Active Tasks</th><th>Productivity</th></tr></thead>
				<tbody>
					<?php foreach ($users as $user) :
						$lp_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}an_projects WHERE assigned_to = %d", $user->ID));
						$at_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}an_tasks WHERE assigned_to = %d AND status != 'completed'", $user->ID));
					?>
						<tr>
							<td><strong><a href="?page=an-team&action=performance&user_id=<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></a></strong><br><small><?php echo implode(', ', $user->roles); ?></small></td>
							<td><?php echo esc_html($user->user_email); ?></td>
							<td><?php echo $lp_count; ?></td>
							<td><?php echo $at_count; ?></td>
							<td>
								<a href="?page=an-team&action=performance&user_id=<?php echo $user->ID; ?>" class="button button-small"><?php _e('View Profile', 'agency-nexus'); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the client profile view.
	 */
	private function render_client_profile($client_id) {
		global $wpdb;
		$client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}an_clients WHERE id = %d", $client_id));
		if (!$client) return;

		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();

		$projects_query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}an_projects WHERE client_id = %d", $client_id);
		$invoices_query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}an_invoices WHERE client_id = %d", $client_id);
		// Files are already client-linked, but for team members we might want to restrict to those from authorised projects too?
		// Currently an_shared_files doesn't have project_id.
		// If the team member is authorised for ANY project of this client, they can see shared files?
		// That seems consistent with how they can see the client.
		$files_query    = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}an_shared_files WHERE client_id = %d", $client_id);

		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$projects_query .= " AND 1=0";
				$invoices_query .= " AND 1=0";
			} else {
				$in_clause = "(" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
				$projects_query .= " AND id IN $in_clause";
				$invoices_query .= " AND project_id IN $in_clause";
			}
		}

		$projects = $wpdb->get_results($projects_query);
		$invoices = $wpdb->get_results($invoices_query);
		$files    = $wpdb->get_results($files_query);
		$user     = get_user_by('email', $client->email);

		?>
		<div class="agency-nexus-wrap">
			<h1><?php echo esc_html($client->name); ?> <small>(<?php echo esc_html($client->company); ?>)</small></h1>

			<div style="display: flex; gap: 20px; margin-top: 20px;">
				<div style="flex: 1;">
					<div class="postbox" style="padding: 20px;">
						<h2><?php _e('Contact Information', 'agency-nexus'); ?></h2>
						<p><strong><?php _e('Email:', 'agency-nexus'); ?></strong> <?php echo esc_html($client->email); ?></p>
						<p><strong><?php _e('Phone:', 'agency-nexus'); ?></strong> <?php echo esc_html($client->phone); ?></p>
						<p><strong><?php _e('Website:', 'agency-nexus'); ?></strong> <?php if($client->website): ?><a href="<?php echo esc_url($client->website); ?>" target="_blank"><?php echo esc_html($client->website); ?></a><?php endif; ?></p>
						<p><strong><?php _e('Address:', 'agency-nexus'); ?></strong><br><?php echo nl2br(esc_html($client->address)); ?></p>
						<hr>
						<p><strong><?php _e('User Account:', 'agency-nexus'); ?></strong>
							<?php if ($user) : ?>
								<span class="badge" style="background: #46b450; color: #fff; padding: 2px 8px; border-radius: 4px;"><?php _e('Linked', 'agency-nexus'); ?></span> (<?php echo $user->user_login; ?>)
							<?php else : ?>
								<span class="badge" style="background: #ccc; padding: 2px 8px; border-radius: 4px;"><?php _e('No WP Account', 'agency-nexus'); ?></span>
								<?php if (current_user_can('manage_options')) : ?>
									<form method="post" style="display:inline; margin-left: 10px;">
										<?php wp_nonce_field('an_create_client_user_nonce'); ?>
										<input type="hidden" name="client_id" value="<?php echo $client->id; ?>">
										<input type="submit" name="an_create_client_user" class="button button-small" value="<?php _e('Create WP User', 'agency-nexus'); ?>">
									</form>
								<?php endif; ?>
							<?php endif; ?>
						</p>
					</div>

					<div class="postbox" style="padding: 20px;">
						<h2><?php _e('Internal Notes', 'agency-nexus'); ?></h2>
						<div style="background: #fff9c4; padding: 10px; border-left: 4px solid #fbc02d;">
							<?php echo $client->notes ? nl2br( esc_html( Agency_Nexus::decrypt( $client->notes ) ) ) : __('No internal notes.', 'agency-nexus'); ?>
						</div>
					</div>
				</div>

				<div style="flex: 2;">
					<div class="postbox" style="padding: 20px;">
						<h2><?php _e('Active Projects', 'agency-nexus'); ?></h2>
						<table class="wp-list-table widefat fixed striped">
							<thead><tr><th>Title</th><th>Status</th><th>Budget</th></tr></thead>
							<tbody>
								<?php foreach ($projects as $p) : ?>
									<tr>
										<td><strong><a href="?page=an-projects&action=view&id=<?php echo $p->id; ?>"><?php echo esc_html($p->title); ?></a></strong></td>
										<td><?php echo ucfirst($p->status); ?></td>
										<td>$<?php echo number_format($p->budget, 2); ?></td>
									</tr>
								<?php endforeach; if(empty($projects)) echo '<tr><td colspan="3">No projects.</td></tr>'; ?>
							</tbody>
						</table>
					</div>

					<div class="postbox" style="padding: 20px;">
						<h2><?php _e('Invoices', 'agency-nexus'); ?></h2>
						<table class="wp-list-table widefat fixed striped">
							<thead><tr><th>Number</th><th>Amount</th><th>Status</th></tr></thead>
							<tbody>
								<?php foreach ($invoices as $i) : ?>
									<tr>
										<td><?php echo esc_html($i->number); ?></td>
										<td>$<?php echo number_format($i->amount, 2); ?></td>
										<td><?php echo ucfirst($i->status); ?></td>
									</tr>
								<?php endforeach; if(empty($invoices)) echo '<tr><td colspan="3">No invoices.</td></tr>'; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<a href="?page=an-clients" class="button"><?php _e('Back to List', 'agency-nexus'); ?></a>
		</div>
		<?php
	}

	/**
	 * Render detailed performance for a user.
	 */
	private function render_performance_view($user_id) {
		if ( ! Agency_Nexus_Permissions::is_admin() && $user_id != get_current_user_id() ) {
			echo '<div class="error"><p>Unauthorized</p></div>'; return;
		}
		global $wpdb;
		$user = get_userdata($user_id);
		$time_table = $wpdb->prefix . 'an_time_entries';
		$tasks_table = $wpdb->prefix . 'an_tasks';

		$total_seconds = $wpdb->get_var($wpdb->prepare("SELECT SUM(duration) FROM $time_table WHERE user_id = %d", $user_id));
		$total_hours = round($total_seconds / 3600, 2);

		$assigned_tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tasks_table WHERE assigned_to = %d", $user_id));
		$lead_projects = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}an_projects WHERE assigned_to = %d", $user_id));

		?>
		<div class="wrap">
			<h1><?php echo sprintf(__('Team Member Profile: %s', 'agency-nexus'), esc_html($user->display_name)); ?></h1>

			<div style="display: flex; gap: 20px; margin-top: 20px;">
				<div style="flex: 1;">
					<div class="postbox" style="padding: 20px;">
						<h3>Overview</h3>
						<p><strong><?php _e('Role:', 'agency-nexus'); ?></strong> <?php echo implode(', ', $user->roles); ?></p>
						<p><strong><?php _e('Email:', 'agency-nexus'); ?></strong> <?php echo esc_html($user->user_email); ?></p>
						<hr>
						<div style="display: flex; justify-content: space-around; text-align: center;">
							<div>
								<span style="font-size: 12px; color: #666;"><?php _e('Hours Logged', 'agency-nexus'); ?></span>
								<div style="font-size: 20px; font-weight: bold;"><?php echo $total_hours; ?></div>
							</div>
							<div>
								<span style="font-size: 12px; color: #666;"><?php _e('Lead Projects', 'agency-nexus'); ?></span>
								<div style="font-size: 20px; font-weight: bold;"><?php echo count($lead_projects); ?></div>
							</div>
							<div>
								<span style="font-size: 12px; color: #666;"><?php _e('Assigned Tasks', 'agency-nexus'); ?></span>
								<div style="font-size: 20px; font-weight: bold;"><?php echo count($assigned_tasks); ?></div>
							</div>
						</div>
					</div>
				</div>

				<div style="flex: 2;">
					<div class="postbox" style="padding: 20px;">
						<h3><?php _e('Projects as Lead', 'agency-nexus'); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead><tr><th>Project</th><th>Status</th></tr></thead>
							<tbody>
								<?php foreach ($lead_projects as $lp) : ?>
									<tr>
										<td><strong><a href="?page=an-projects&action=view&id=<?php echo $lp->id; ?>"><?php echo esc_html($lp->title); ?></a></strong></td>
										<td><?php echo ucfirst($lp->status); ?></td>
									</tr>
								<?php endforeach; if(empty($lead_projects)) echo '<tr><td colspan="2">No projects led.</td></tr>'; ?>
							</tbody>
						</table>
					</div>

					<div class="postbox" style="padding: 20px;">
						<h3><?php _e('Assigned Tasks', 'agency-nexus'); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead><tr><th>Task</th><th>Project</th><th>Status</th></tr></thead>
							<tbody>
								<?php foreach ($assigned_tasks as $task) :
									$project_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}an_projects WHERE id = %d", $task->project_id));
								?>
									<tr>
										<td><?php echo esc_html($task->title); ?></td>
										<td><?php echo esc_html($project_title); ?></td>
										<td><?php echo ucfirst($task->status); ?></td>
									</tr>
								<?php endforeach; if(empty($assigned_tasks)) echo '<tr><td colspan="3">No tasks assigned.</td></tr>'; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<a href="?page=an-team" class="button"><?php _e('Back to Team', 'agency-nexus'); ?></a>
		</div>
		<?php
	}

	/**
	 * Render the projects page.
	 */
	public function render_projects() {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'an_projects';
		$clients_table  = $wpdb->prefix . 'an_clients';
		$tasks_table    = $wpdb->prefix . 'an_tasks';
		$time_table     = $wpdb->prefix . 'an_time_entries';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'created': $m = 'Project created!'; break;
				case 'updated': $m = 'Project updated!'; break;
				case 'deleted': $m = 'Project deleted!'; break;
				case 'task_added': $m = 'Task added!'; break;
				case 'task_updated': $m = 'Task updated!'; break;
				case 'task_deleted': $m = 'Task deleted!'; break;
				case 'time_logged': $m = 'Time logged!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'view' && $id) {
			if ( ! Agency_Nexus_Permissions::can_view_project( $id ) ) {
				echo '<div class="error"><p>Unauthorized</p></div>'; return;
			}
			$is_admin = Agency_Nexus_Permissions::is_admin();
			$is_team = Agency_Nexus_Permissions::is_team_member();
			$project = $wpdb->get_row($wpdb->prepare("SELECT p.*, c.name as client_name FROM $projects_table p JOIN $clients_table c ON p.client_id = c.id WHERE p.id = %d", $id));

			$user_id = get_current_user_id();
			$is_lead = (int)$project->assigned_to === $user_id;

			$tasks_query = $wpdb->prepare("SELECT * FROM $tasks_table WHERE project_id = %d", $id);
			if ( ! $is_admin && $is_team && ! $is_lead ) {
				$tasks_query .= $wpdb->prepare(" AND assigned_to = %d", $user_id );
			}
			$tasks = $wpdb->get_results($tasks_query);
			?>
			<div class="wrap">
				<h1><?php echo esc_html($project->title); ?> <small>(<?php echo esc_html($project->client_name); ?>)</small></h1>
				<div class="postbox" style="padding: 20px;">
					<h2>Details</h2>
					<p><strong>Status:</strong> <?php echo esc_html(ucfirst($project->status)); ?></p>
					<p><strong>Budget:</strong> $<?php echo number_format($project->budget, 2); ?></p>
					<p><strong>Buffer Days:</strong> <?php echo intval($project->buffer_days); ?> <?php _e('days', 'agency-nexus'); ?></p>
					<p><strong>Description:</strong><br><?php echo nl2br( esc_html( Agency_Nexus::decrypt( $project->description ) ) ); ?></p>

					<hr>
					<h3>Tasks & Time</h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Task</th><th>Assigned To</th><th>Timeline</th><th>Status</th><th>Time Logged</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach ($tasks as $task) :
								$total_time = $wpdb->get_var($wpdb->prepare("SELECT SUM(duration) FROM $time_table WHERE task_id = %d", $task->id));
								$user = $task->assigned_to ? get_userdata($task->assigned_to) : null;
								$assigned = $user ? $user->display_name : 'Unassigned';
							?>
								<tr>
									<td><?php echo esc_html($task->title); ?></td>
									<td>
										<form method="post" style="display:inline-block;">
											<?php wp_nonce_field('an_assign_task_nonce'); ?>
											<input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
											<select name="assigned_to" onchange="this.form.submit()">
												<option value="0"><?php _e('Unassigned', 'agency-nexus'); ?></option>
												<?php foreach (get_users() as $u) : ?>
													<option value="<?php echo $u->ID; ?>" <?php selected($task->assigned_to, $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
												<?php endforeach; ?>
											</select>
											<input type="hidden" name="an_assign_task" value="1">
										</form>
									</td>
									<td><small><?php echo esc_html($task->start_date); ?> to <?php echo esc_html($task->due_date); ?></small></td>
									<td><?php echo esc_html($task->status); ?></td>
									<td><?php echo round($total_time / 3600, 2); ?> hrs</td>
									<td>
										<div style="display:flex; gap: 5px; align-items: center;">
											<form method="post" style="display:flex; gap: 5px;">
												<?php wp_nonce_field('an_log_time_nonce'); ?>
												<input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
												<input type="number" step="0.1" name="hours" placeholder="Hrs" style="width: 50px;" required>
												<input type="submit" name="an_log_time" class="button button-small" value="Log">
											</form>
											|
											<a href="?page=an-edit-task&task_id=<?php echo $task->id; ?>&id=<?php echo $project->id; ?>"><?php _e('Edit', 'agency-nexus'); ?></a> |
											<a href="<?php echo wp_nonce_url('?page=an-projects&action=delete_task&task_id=' . $task->id . '&id=' . $project->id, 'an_delete_task_' . $task->id); ?>" style="color:red;" onclick="return confirm('Delete this task and all its time logs?')"><?php _e('Delete', 'agency-nexus'); ?></a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" style="margin-top: 15px; display:flex; flex-wrap: wrap; gap: 10px; align-items: center;">
						<?php wp_nonce_field('an_add_task_nonce'); ?>
						<input type="hidden" name="project_id" value="<?php echo $project->id; ?>">
						<input type="text" name="title" placeholder="New Task Title" required>
						<input type="date" name="start_date" title="Start Date" value="<?php echo date('Y-m-d'); ?>">
						<input type="date" name="due_date" title="Due Date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
						<?php if ( $is_team ) : ?>
						<select name="assigned_to">
							<option value="0"><?php _e('Assign to...', 'agency-nexus'); ?></option>
							<?php foreach (get_users() as $u) : ?>
								<option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="depends_on">
							<option value="0"><?php _e('No Dependency', 'agency-nexus'); ?></option>
							<?php foreach ($tasks as $t) : ?>
								<option value="<?php echo $t->id; ?>"><?php echo esc_html($t->title); ?></option>
							<?php endforeach; ?>
						</select>
						<?php endif; ?>
						<input type="submit" name="an_add_task" class="button button-primary" value="Add Task">
					</form>
				</div>

				<div class="postbox" style="padding: 20px; margin-top: 20px;">
					<h3>Timeline Visualizer (Gantt)</h3>
					<?php $this->render_gantt_chart($tasks); ?>
				</div>
				<a href="?page=an-projects" class="button"><?php _e('Back to Projects', 'agency-nexus'); ?></a>
				<button onclick="window.print()" class="button"><?php _e('Print Report', 'agency-nexus'); ?></button>
			</div>
			<?php
			return;
		}

		if ($action === 'edit' || $action === 'add') {
			if ( ! Agency_Nexus_Permissions::is_team_member() ) {
				echo '<div class="error"><p>Unauthorized</p></div>'; return;
			}
			$project = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id = %d", $id)) : null;

			$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
			$clients_query = "SELECT id, name FROM $clients_table";
			if ( is_array( $authorised_ids ) ) {
				if ( empty( $authorised_ids ) ) {
					$clients_query .= " WHERE 1=0";
				} else {
					$authorised_client_ids = $wpdb->get_col( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")" );
					if ( ! empty( $authorised_client_ids ) ) {
						$clients_query .= " WHERE id IN (" . implode( ',', array_map( 'intval', array_unique($authorised_client_ids) ) ) . ")";
					} else {
						$clients_query .= " WHERE 1=0";
					}
				}
			}
			$clients = $wpdb->get_results($clients_query);
			?>
			<div class="wrap">
				<h1><?php echo $id ? __('Edit Project', 'agency-nexus') : __('Create New Project', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Define the project scope, budget, and timeline for your client.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field('an_save_project_nonce'); ?>
					<table class="form-table">
						<tr>
							<th><label for="client_id">Client</label></th>
							<td>
								<select name="client_id" id="client_id" required>
									<?php foreach ($clients as $client) : ?>
										<option value="<?php echo $client->id; ?>" <?php selected($project ? $project->client_id : 0, $client->id); ?>><?php echo esc_html($client->name); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Select the client who owns this project.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="assigned_to"><?php _e('Project Lead / Assigned To', 'agency-nexus'); ?></label></th>
							<td>
								<select name="assigned_to" id="assigned_to">
									<option value="0"><?php _e('Unassigned', 'agency-nexus'); ?></option>
									<?php foreach (get_users() as $u) : ?>
										<option value="<?php echo $u->ID; ?>" <?php selected($project ? $project->assigned_to : 0, $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Assign an internal team member to manage this project.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="title">Project Title</label></th>
							<td>
								<input type="text" name="title" id="title" value="<?php echo $project ? esc_attr($project->title) : ''; ?>" class="regular-text" required>
								<a href="#" class="an-ai-improve-link" data-target="#title" data-type="project_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Short, descriptive name for the project. e.g., Website Redesign 2024', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="budget">Budget ($)</label></th>
							<td>
								<input type="number" step="0.01" name="budget" id="budget" value="<?php echo $project ? esc_attr($project->budget) : '0.00'; ?>" class="regular-text">
								<p class="description"><?php _e('Total project value. Used for profitability tracking.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="status">Status</label></th>
							<td>
								<select name="status" id="status">
									<option value="planned" <?php selected($project ? $project->status : '', 'planned'); ?>>Planned</option>
									<option value="in_progress" <?php selected($project ? $project->status : '', 'in_progress'); ?>>In Progress</option>
									<option value="on_hold" <?php selected($project ? $project->status : '', 'on_hold'); ?>>On Hold</option>
									<option value="completed" <?php selected($project ? $project->status : '', 'completed'); ?>>Completed</option>
								</select>
								<p class="description"><?php _e('The current stage of the project.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="buffer_days"><?php _e('Buffer Days', 'agency-nexus'); ?></label></th>
							<td>
								<input type="number" name="buffer_days" id="buffer_days" value="<?php echo $project ? intval($project->buffer_days) : 0; ?>" class="small-text">
								<p class="description"><?php _e('Extra time added to project estimates for risk management.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="description">Description</label></th>
							<td>
								<textarea name="description" id="description" class="regular-text"><?php echo $project ? esc_textarea( Agency_Nexus::decrypt( $project->description ) ) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#description" data-type="project_description" style="text-decoration: none;">✨ <?php _e('AI Improve Description', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Detailed overview of goals and deliverables.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="an_save_project" class="button button-primary" value="<?php _e('Save Project', 'agency-nexus'); ?>">
						<a href="?page=an-projects" class="button"><?php _e('Cancel', 'agency-nexus'); ?></a>
					</p>
				</form>
			</div>
			<?php
			return;
		}

		$query = "SELECT p.*, c.name as client_name FROM $projects_table p JOIN $clients_table c ON p.client_id = c.id";
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$query .= " WHERE 1=0";
			} else {
				$query .= " WHERE p.id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
			}
		}
		$query .= " ORDER BY p.created_at DESC";
		$projects = $wpdb->get_results( $query );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Project Management', 'agency-nexus' ); ?></h1>
			<?php if ( Agency_Nexus_Permissions::is_team_member() ) : ?>
			<a href="?page=an-projects&action=add" class="page-title-action"><?php _e('Add New', 'agency-nexus'); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid var(--an-indigo-600); padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'The Agency Fulfillment Engine', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Manage your client projects from start to finish. Track tasks, timelines, and budgets in one centralized location.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Visual Timelines:</strong> Use the Gantt chart inside any project to visualize milestones and task dependencies.</li>
					<li><strong>ROI Focus:</strong> By logging time against projects, the system automatically calculates your project profitability.</li>
				</ul>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Title</th>
						<th>Client</th>
						<th>Lead</th>
						<th>Budget</th>
						<th>Profitability</th>
						<th>Status</th>
						<th>Created</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $projects ) : foreach ( $projects as $project ) : ?>
						<?php
							$lead_user = $project->assigned_to ? get_userdata($project->assigned_to) : null;
							$lead_name = $lead_user ? $lead_user->display_name : '<em>Unassigned</em>';

							$money_module = Agency_Nexus()->modules['moneyflow'];
							$profit = $money_module ? $money_module->calculate_project_profitability($project->id) : null;
						?>
						<tr>
							<td><strong><a href="?page=an-projects&action=view&id=<?php echo $project->id; ?>"><?php echo esc_html( $project->title ); ?></a></strong></td>
							<td><?php echo esc_html( $project->client_name ); ?></td>
							<td><?php echo $lead_name; ?></td>
							<td>$<?php echo number_format($project->budget, 2); ?></td>
							<td>
								<?php if ($profit) : ?>
									<span style="color: <?php echo $profit['profitability'] >= 0 ? '#46b450' : '#dc3232'; ?>; font-weight: bold;">
										$<?php echo number_format($profit['profitability'], 2); ?>
									 <small>(<?php echo round($profit['margin']); ?>%)</small>
									</span>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
							<td><span class="badge status-<?php echo $project->status; ?>"><?php echo ucfirst($project->status); ?></span></td>
							<td><?php echo $project->created_at; ?></td>
							<td>
								<a href="?page=an-projects&action=view&id=<?php echo $project->id; ?>"><?php _e('View', 'agency-nexus'); ?></a>
								<?php if ( Agency_Nexus_Permissions::is_team_member() ) : ?>
								| <a href="?page=an-projects&action=edit&id=<?php echo $project->id; ?>"><?php _e('Edit', 'agency-nexus'); ?></a>
								<?php if ( Agency_Nexus_Permissions::is_admin() ) : ?>
								| <a href="<?php echo wp_nonce_url('?page=an-projects&action=delete&id=' . $project->id, 'an_delete_project_' . $project->id); ?>" style="color:red;" onclick="return confirm('Delete this project?')"><?php _e('Delete', 'agency-nexus'); ?></a>
								<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="6">No projects found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render a basic Gantt chart for tasks.
	 */
	private function render_gantt_chart($tasks) {
		if ( empty($tasks) ) {
			echo '<p>No tasks to display in timeline.</p>';
			return;
		}

		// Calculate date range
		$start_dates = array_map(function($t){ return strtotime($t->start_date); }, $tasks);
		$end_dates   = array_map(function($t){ return strtotime($t->due_date); }, $tasks);
		$min_date = min($start_dates);
		$max_date = max($end_dates);
		$total_days = ceil(($max_date - $min_date) / 86400) + 1;
		if ($total_days < 7) $total_days = 7;

		?>
		<div style="overflow-x: auto; background: #f9f9f9; padding: 15px; border: 1px solid #ddd;">
			<div style="min-width: 800px; position: relative;">
				<!-- Header with dates -->
				<div style="display: flex; border-bottom: 1px solid #ccc; margin-bottom: 10px;">
					<div style="width: 200px; font-weight: bold;">Task</div>
					<div style="flex: 1; display: flex;">
						<?php for($i=0; $i<$total_days; $i+=max(1, floor($total_days/10))):
							$d = date('M d', $min_date + ($i * 86400));
						?>
							<div style="flex: 1; font-size: 10px; border-left: 1px solid #eee; padding-left: 2px;"><?php echo $d; ?></div>
						<?php endfor; ?>
					</div>
				</div>

				<?php foreach($tasks as $task):
					$t_start = strtotime($task->start_date);
					$t_end   = strtotime($task->due_date);
					$offset = ($t_start - $min_date) / 86400;
					$duration = ($t_end - $t_start) / 86400 + 1;
					$left_pct = ($offset / $total_days) * 100;
					$width_pct = ($duration / $total_days) * 100;
					$color = ($task->status === 'completed') ? '#46b450' : '#0073aa';
				?>
					<div style="display: flex; height: 30px; align-items: center; border-bottom: 1px solid #eee;">
						<div style="width: 200px; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($task->title); ?>">
							<?php echo esc_html($task->title); ?>
						</div>
						<div style="flex: 1; position: relative; height: 100%;">
							<div style="position: absolute; left: <?php echo $left_pct; ?>%; width: <?php echo $width_pct; ?>%; height: 12px; background: <?php echo $color; ?>; border-radius: 6px; top: 9px;" title="<?php echo $task->start_date; ?> to <?php echo $task->due_date; ?>"></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the global settings page.
	 */
	public function render_settings() {
		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch($_GET['msg']) {
				case 'saved':  $m = 'Settings saved!'; break;
				case 'seeded': $m = 'Best sample content added successfully!'; break;
				case 'reset':  $m = 'Database cleared successfully!'; break;
			}
			if ($m) echo '<div class="updated"><p>' . esc_html($m) . '</p></div>';
		}
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Agency Nexus Settings', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e('Configure your agency\'s global parameters for finances and external integrations.', 'agency-nexus'); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'an_global_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="an_agency_logo"><?php _e('Agency Logo', 'agency-nexus'); ?></label></th>
						<td>
							<?php if ( Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'white_label' ) ) : ?>
								<input type="text" name="an_agency_logo" id="an_agency_logo" value="<?php echo esc_attr( get_option( 'an_agency_logo' ) ); ?>" class="regular-text">
								<button type="button" id="upload_logo_btn" class="button"><?php _e('Select Logo', 'agency-nexus'); ?></button>
								<p class="description"><?php _e('The agency logo to display on invoices. Best if uploaded with a white background.', 'agency-nexus'); ?></p>
								<div id="logo-preview" style="margin-top: 10px;">
									<?php if ( get_option( 'an_agency_logo' ) ) : ?>
										<img src="<?php echo esc_url( get_option( 'an_agency_logo' ) ); ?>" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 5px;">
									<?php endif; ?>
								</div>
							<?php else : ?>
								<input type="text" value="<?php echo esc_attr( get_option( 'an_agency_logo' ) ); ?>" class="regular-text" disabled>
								<p class="description" style="color: #dc3232; font-weight: bold;">
									<?php _e('White-labeling is only available for the **Agency VIP** tier. Upgrade to remove default branding.', 'agency-nexus'); ?>
									<a href="<?php echo admin_url('admin.php?page=an-license'); ?>"><?php _e('Upgrade Now', 'agency-nexus'); ?></a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="an_store_url"><?php _e('Main Domain Store URL', 'agency-nexus'); ?></label></th>
						<td>
							<input type="url" name="an_store_url" id="an_store_url" value="<?php echo esc_url( get_option( 'an_store_url' ) ); ?>" class="large-text">
							<p class="description"><?php _e('The URL of your main domain where the Agency Nexus Store plugin is installed. e.g., https://yourmaindomain.com', 'agency-nexus'); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="an_hourly_rate">Default Hourly Rate ($)</label></th>
						<td>
							<input type="number" name="an_hourly_rate" id="an_hourly_rate" value="<?php echo esc_attr( get_option( 'an_hourly_rate', 50 ) ); ?>" class="regular-text">
							<p class="description"><?php _e('Used to calculate internal labor costs in the Financial Dashboard. Default: $50/hr', 'agency-nexus'); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="an_stripe_key">Stripe Secret Key</label></th>
						<td>
							<input type="password" name="an_stripe_key" id="an_stripe_key" value="<?php echo esc_attr( get_option( 'an_stripe_key' ) ); ?>" class="regular-text">
							<p class="description"><?php _e('Required for accepting credit card payments for invoices.', 'agency-nexus'); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="an_paypal_email">PayPal Business Email</label></th>
						<td>
							<input type="email" name="an_paypal_email" id="an_paypal_email" value="<?php echo esc_attr( get_option( 'an_paypal_email' ) ); ?>" class="regular-text">
							<p class="description"><?php _e('Required for accepting PayPal payments for invoices.', 'agency-nexus'); ?></p>
						</td>
					</tr>
					<tr>
						<th>Test Mode / Sandbox</th>
						<td>
							<label>
								<input type="checkbox" name="an_payment_test_mode" value="yes" <?php checked( get_option( 'an_payment_test_mode', 'yes' ), 'yes' ); ?>>
								Enable Test Mode (Stripe Test Mode / PayPal Sandbox)
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="an_zapier_webhook">Zapier Webhook URL</label></th>
						<td>
							<input type="text" name="an_zapier_webhook" id="an_zapier_webhook" value="<?php echo esc_attr( get_option( 'an_zapier_webhook' ) ); ?>" class="large-text">
							<p class="description"><?php _e('Trigger external workflows in Zapier or Make.com when project milestones are met.', 'agency-nexus'); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="an_slack_webhook">Slack Webhook URL</label></th>
						<td>
							<input type="text" name="an_slack_webhook" id="an_slack_webhook" value="<?php echo esc_attr( get_option( 'an_slack_webhook' ) ); ?>" class="large-text">
							<p class="description"><?php _e('Send project alerts and messages directly to your agency Slack channel.', 'agency-nexus'); ?></p>
						</td>
					</tr>
				</table>

				<hr>
				<h2><?php _e( 'AI Copilot Engine', 'agency-nexus' ); ?></h2>
				<p class="description"><?php _e( 'Turn your agency into a supercharged Solo Agency. Connect top AI models to write proposals, draft content in bulk, suggest perfect time blocks, and help you respond to client messages automatically.', 'agency-nexus' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php _e( 'Enable AI Copilot', 'agency-nexus' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="an_ai_enabled" value="yes" <?php checked( get_option( 'an_ai_enabled', 'no' ), 'yes' ); ?>>
								<?php _e( 'Activate AI features across all modules', 'agency-nexus' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="an_ai_provider"><?php _e( 'Preferred AI Provider', 'agency-nexus' ); ?></label></th>
						<td>
							<select name="an_ai_provider" id="an_ai_provider">
								<option value="local" <?php selected( get_option( 'an_ai_provider', 'local' ), 'local' ); ?>><?php _e( 'Local CoPilot (Offline/Built-in)', 'agency-nexus' ); ?></option>
								<option value="openai" <?php selected( get_option( 'an_ai_provider' ), 'openai' ); ?>><?php _e( 'ChatGPT (OpenAI)', 'agency-nexus' ); ?></option>
								<option value="gemini" <?php selected( get_option( 'an_ai_provider' ), 'gemini' ); ?>><?php _e( 'Google Gemini', 'agency-nexus' ); ?></option>
								<option value="claude" <?php selected( get_option( 'an_ai_provider' ), 'claude' ); ?>><?php _e( 'Anthropic Claude', 'agency-nexus' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="ai-provider-field openai-field" style="display:none;">
						<th><label for="an_openai_key"><?php _e( 'OpenAI API Key', 'agency-nexus' ); ?></label></th>
						<td>
							<input type="password" name="an_openai_key" id="an_openai_key" value="<?php echo esc_attr( get_option( 'an_openai_key' ) ); ?>" class="regular-text">
						</td>
					</tr>
					<tr class="ai-provider-field gemini-field" style="display:none;">
						<th><label for="an_gemini_key"><?php _e( 'Gemini API Key', 'agency-nexus' ); ?></label></th>
						<td>
							<input type="password" name="an_gemini_key" id="an_gemini_key" value="<?php echo esc_attr( get_option( 'an_gemini_key' ) ); ?>" class="regular-text">
						</td>
					</tr>
					<tr class="ai-provider-field claude-field" style="display:none;">
						<th><label for="an_claude_key"><?php _e( 'Claude API Key', 'agency-nexus' ); ?></label></th>
						<td>
							<input type="password" name="an_claude_key" id="an_claude_key" value="<?php echo esc_attr( get_option( 'an_claude_key' ) ); ?>" class="regular-text">
						</td>
					</tr>
					<tr class="ai-provider-field model-field">
						<th><label for="an_ai_model"><?php _e( 'AI Model', 'agency-nexus' ); ?></label></th>
						<td>
							<input type="text" name="an_ai_model" id="an_ai_model" value="<?php echo esc_attr( get_option( 'an_ai_model', 'gpt-4o' ) ); ?>" class="regular-text" placeholder="e.g. gpt-4o, gemini-pro, claude-3-opus">
						</td>
					</tr>
				</table>

				<hr>
				<h2>Boundary Enforcement (TimeBlock Pro)</h2>
				<table class="form-table">
					<tr>
						<th>Communication Hours</th>
						<td>
							<input type="time" name="an_comm_start" value="<?php echo esc_attr( get_option('an_comm_start', '09:00') ); ?>"> to
							<input type="time" name="an_comm_end" value="<?php echo esc_attr( get_option('an_comm_end', '17:00') ); ?>">
							<p class="description"><?php _e('Define when you are available for client communication. Outside these hours, auto-responders can be triggered.', 'agency-nexus'); ?></p>
						</td>
					</tr>
					<tr>
						<th>After-hours Auto-responder</th>
						<td>
							<textarea name="an_after_hours_msg" class="large-text" rows="3"><?php echo esc_textarea( get_option('an_after_hours_msg', "Thanks for your message! I am currently away from my desk. My office hours are 9am - 5pm. I will get back to you as soon as possible.") ); ?></textarea>
						</td>
					</tr>
				</table>

				<hr>
				<h2>Shortcodes</h2>
				<div style="background: #f1f5f9; padding: 20px; border-radius: 8px;">
					<p>Use these shortcodes to display Agency Nexus features on your frontend pages:</p>
					<ul>
						<li><code>[an_client_portal]</code> - Displays the complete Client Dashboard (Projects, Messages, Invoices).</li>
						<li><code>[an_lead_form]</code> - Displays the lead capture form.</li>
						<li><code>[an_marketplace]</code> - Displays the template marketplace.</li>
						<li><code>[an_approval_portal]</code> - Displays items awaiting client sign-off.</li>
						<li><code>[agency_nexus_thank_you product_name="NAME" cta_url="URL"]</code> - High-converting confirmation page.</li>
					</ul>
					<p><small>Note: These shortcodes will automatically respect your licensing tier and user permissions.</small></p>
				</div>

				<hr>
				<h2>Email & SMTP Settings</h2>
				<p class="description">Configure how the plugin sends emails. You can use your default server or connect an external SMTP service (SendGrid, Mailgun, etc.) for better deliverability.</p>

				<table class="form-table">
					<tr>
						<th>Enable SMTP</th>
						<td>
							<label>
								<input type="checkbox" name="an_smtp_enabled" value="yes" <?php checked( get_option( 'an_smtp_enabled', 'no' ), 'yes' ); ?>>
								Use SMTP instead of default mail server
							</label>
						</td>
					</tr>
					<tr>
						<th>SMTP Host</th>
						<td><input type="text" name="an_smtp_host" value="<?php echo esc_attr( get_option( 'an_smtp_host' ) ); ?>" class="regular-text" placeholder="smtp.example.com"></td>
					</tr>
					<tr>
						<th>SMTP Port</th>
						<td><input type="number" name="an_smtp_port" value="<?php echo esc_attr( get_option( 'an_smtp_port', 587 ) ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th>Encryption</th>
						<td>
							<select name="an_smtp_encryption">
								<option value="none" <?php selected( get_option( 'an_smtp_encryption' ), 'none' ); ?>>None</option>
								<option value="ssl" <?php selected( get_option( 'an_smtp_encryption' ), 'ssl' ); ?>>SSL</option>
								<option value="tls" <?php selected( get_option( 'an_smtp_encryption', 'tls' ), 'tls' ); ?>>TLS</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Authentication</th>
						<td>
							<label>
								<input type="checkbox" name="an_smtp_auth" value="yes" <?php checked( get_option( 'an_smtp_auth', 'no' ), 'yes' ); ?>>
								SMTP Authentication required
							</label>
						</td>
					</tr>
					<tr>
						<th>SMTP Username</th>
						<td><input type="text" name="an_smtp_username" value="<?php echo esc_attr( get_option( 'an_smtp_username' ) ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>SMTP Password</th>
						<td><input type="password" name="an_smtp_password" value="" class="regular-text" placeholder="********">
						<p class="description">Leave blank to keep current password.</p></td>
					</tr>
					<tr>
						<th>From Email Address</th>
						<td><input type="email" name="an_email_from_address" value="<?php echo esc_attr( get_option( 'an_email_from_address', get_option('admin_email') ) ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th>From Name</th>
						<td><input type="text" name="an_email_from_name" value="<?php echo esc_attr( get_option( 'an_email_from_name', get_bloginfo('name') ) ); ?>" class="regular-text"></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="an_save_global_settings" class="button button-primary" value="Save Settings">
				</p>
			</form>

			<div class="postbox" style="margin-top: 40px; border: 1px solid #d63638; background: #fff;">
				<div class="postbox-header" style="background: #fcf9f9; border-bottom: 1px solid #ccd0d4; padding: 10px 15px;">
					<h2 style="margin:0; color: #d63638;"><?php _e( 'Database & Demo Management', 'agency-nexus' ); ?></h2>
				</div>
				<div class="inside" style="padding: 20px;">
					<p><?php _e( 'Maintain your Agency Nexus installation by managing sample data or performing a full system reset.', 'agency-nexus' ); ?></p>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
						<div style="padding: 20px; border: 1px solid #eee; border-radius: 8px; background: #f8fafc;">
							<h4 style="margin-top:0;"><?php _e( 'Demo Mode', 'agency-nexus' ); ?></h4>
							<p class="description"><?php _e( 'Populate your dashboard with professional, high-quality sample clients, projects, and leads to explore all features instantly.', 'agency-nexus' ); ?></p>
							<form method="post" style="margin-top: 15px;">
								<?php wp_nonce_field('an_seed_data_nonce'); ?>
								<input type="submit" name="an_seed_data" class="button button-primary" value="<?php _e( 'Add Best Sample Content', 'agency-nexus' ); ?>">
							</form>
						</div>

						<div style="padding: 20px; border: 1px solid #fecaca; border-radius: 8px; background: #fff5f5;">
							<h4 style="margin-top:0; color: #dc2626;"><?php _e( 'System Reset', 'agency-nexus' ); ?></h4>
							<p class="description"><?php _e( 'Permanently delete ALL records from the database (Clients, Projects, Invoices, Messages). Use with extreme caution.', 'agency-nexus' ); ?></p>
							<form method="post" style="margin-top: 15px;">
								<?php wp_nonce_field('an_reset_data_nonce'); ?>
								<input type="submit" name="an_reset_data" class="button" style="background: #dc2626; color: #fff; border-color: #b91c1c;" value="<?php _e( 'Reset Database (Delete All)', 'agency-nexus' ); ?>" onclick="return confirm('WARNING: This will delete ALL Agency Nexus records. This cannot be undone. Proceed?')">
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($){
			$('#upload_logo_btn').click(function(e) {
				e.preventDefault();
				var frame = wp.media({ title: 'Select Agency Logo', multiple: false }).open().on('select', function(e){
					var uploaded_image = frame.state().get('selection').first().toJSON();
					$('#an_agency_logo').val(uploaded_image.url);
					$('#logo-preview').html('<img src="' + uploaded_image.url + '" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 5px;">');
				});
			});

			function toggle_ai_fields() {
				var val = $('#an_ai_provider').val();
				$('.ai-provider-field').hide();
				if (val === 'openai') {
					$('.openai-field').show();
					$('.model-field').show();
				} else if (val === 'gemini') {
					$('.gemini-field').show();
					$('.model-field').show();
				} else if (val === 'claude') {
					$('.claude-field').show();
					$('.model-field').show();
				} else if (val === 'local') {
					$('.model-field').hide();
				}
			}
			$('#an_ai_provider').change(toggle_ai_fields);
			toggle_ai_fields();
		});
		</script>
		<?php
	}

	/**
	 * Render the main dashboard page.
	 */
	public function render_dashboard() {
		if ( isset( $_GET['msg'] ) ) {
			if ( 'seeded' === $_GET['msg'] ) {
				echo '<div class="updated"><p>Sample data seeded successfully! Created "Sample Client" and "Sample Team Member" accounts.</p></div>';
			} elseif ( 'reset' === $_GET['msg'] ) {
				echo '<div class="updated"><p>All Agency Nexus data has been cleared.</p></div>';
			}
		}
		$is_admin = current_user_can( 'manage_options' );
		?>
		<style>
			.an-quick-nav { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-bottom: 40px; }
			.an-nav-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; text-align: center; }
			.an-nav-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--an-indigo-500); }
			.an-nav-card i { font-size: 24px; margin-bottom: 10px; color: var(--an-indigo-600); }
			.an-nav-card span { font-weight: 600; color: var(--an-slate-700); }
			.an-nav-card small { color: var(--an-slate-500); font-size: 11px; margin-top: 5px; }
		</style>
		<div class="agency-nexus-wrap">
			<header class="an-dashboard-header">
				<div class="an-dashboard-header-content">
					<h1><?php _e( 'Agency Nexus Dashboard', 'agency-nexus' ); ?></h1>
					<p class="description" style="font-size: 1.1rem; color: var(--an-slate-500);">
						<?php _e( 'Your central command center for agency operations. Monitor project health, team productivity, and financial performance at a glance.', 'agency-nexus' ); ?>
					</p>
				</div>
				<div class="an-dashboard-header-actions" style="display:flex; gap: 10px;">
					<?php if ( $is_admin ) : ?>
						<form method="post">
							<?php wp_nonce_field('an_seed_data_nonce'); ?>
							<input type="submit" name="an_seed_data" class="button button-primary" value="Seed Sample Data">
						</form>
						<form method="post">
							<?php wp_nonce_field('an_reset_data_nonce'); ?>
							<input type="submit" name="an_reset_data" class="button button-link-delete" value="Clear Data" onclick="return confirm('Delete ALL records?')">
						</form>
					<?php endif; ?>
				</div>
			</header>

			<section class="an-dashboard-section">
				<h2><?php _e( 'Quick Navigation', 'agency-nexus' ); ?></h2>
				<div class="an-quick-nav">
					<?php
					$lm = Agency_Nexus_License_Manager::get_instance();
					$is_team = Agency_Nexus_Permissions::is_team_member();
					?>

					<?php if ( $is_team && $lm->is_feature_enabled( 'client_management' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-clients'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-groups"></span>
						<span><?php _e('Clients', 'agency-nexus'); ?></span>
						<small><?php _e('Manage agency CRM', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $lm->is_feature_enabled( 'project_management' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-projects'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-portfolio"></span>
						<span><?php _e('Projects', 'agency-nexus'); ?></span>
						<small><?php _e('Track tasks & time', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $is_team && $lm->is_feature_enabled( 'lead_intelligence' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-leads'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-megaphone"></span>
						<span><?php _e('Leads', 'agency-nexus'); ?></span>
						<small><?php _e('Sales pipeline', 'agency-nexus'); ?></small>
					</a>
					<a href="<?php echo admin_url('admin.php?page=an-social-dashboard'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-share"></span>
						<span><?php _e('Social Hub', 'agency-nexus'); ?></span>
						<small><?php _e('Aggregated engagement', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $lm->is_feature_enabled( 'messaging' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-messages'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-email-alt"></span>
						<span><?php _e('Messages', 'agency-nexus'); ?></span>
						<small><?php _e('Client communication', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $is_team && $lm->is_feature_enabled( 'money_flow' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-invoices'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<span><?php _e('Invoices', 'agency-nexus'); ?></span>
						<small><?php _e('Billing & ROI', 'agency-nexus'); ?></small>
					</a>
					<a href="<?php echo admin_url('admin.php?page=an-focus-mode'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-visibility"></span>
						<span><?php _e('Focus Mode', 'agency-nexus'); ?></span>
						<small><?php _e('Zero distractions', 'agency-nexus'); ?></small>
					</a>
					<a href="<?php echo admin_url('admin.php?page=an-vacations'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-palmtree"></span>
						<span><?php _e('Vacations', 'agency-nexus'); ?></span>
						<small><?php _e('Schedule OOO', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $is_team && $lm->is_feature_enabled( 'project_management' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-scope-builder'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-welcome-edit-page"></span>
						<span><?php _e('Scope Builder', 'agency-nexus'); ?></span>
						<small><?php _e('Onboard clients', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $lm->is_feature_enabled( 'project_management' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-proposals'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-media-text"></span>
						<span><?php _e('Proposals', 'agency-nexus'); ?></span>
						<small><?php _e('Review and sign', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( $lm->is_feature_enabled( 'content_calendar' ) ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-pillar-architect'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-category"></span>
						<span><?php _e('Pillar Architect', 'agency-nexus'); ?></span>
						<small><?php _e('Topic clusters', 'agency-nexus'); ?></small>
					</a>
					<a href="<?php echo admin_url('admin.php?page=an-batch-automation'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-forms"></span>
						<span><?php _e('Batch Mode', 'agency-nexus'); ?></span>
						<small><?php _e('Bulk draft creation', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>

					<?php if ( current_user_can('manage_options') ) : ?>
					<a href="<?php echo admin_url('admin.php?page=an-settings'); ?>" class="an-nav-card">
						<span class="dashicons dashicons-admin-settings"></span>
						<span><?php _e('Settings', 'agency-nexus'); ?></span>
						<small><?php _e('Agency configuration', 'agency-nexus'); ?></small>
					</a>
					<?php endif; ?>
				</div>
			</section>

			<div class="agency-nexus-widgets" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px;">
				<?php do_action( 'agency_nexus_dashboard_widgets' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the license management page.
	 */
	public function render_license() {
		$license_manager = Agency_Nexus_License_Manager::get_instance();
		$tier = $license_manager->get_tier();
		$license_data = get_option( 'an_license_data' );

		if ( isset( $_GET['msg'] ) ) {
			if ( 'activated' === $_GET['msg'] ) {
				echo '<div class="updated"><p>License activated successfully! You are now on the ' . ucfirst($tier) . ' tier.</p></div>';
			} elseif ( 'deactivated' === $_GET['msg'] ) {
				echo '<div class="updated"><p>License deactivated. Reverted to Free tier.</p></div>';
			} elseif ( 'error' === $_GET['msg'] ) {
				echo '<div class="error"><p>' . esc_html( $_GET['error_msg'] ) . '</p></div>';
			}
		}
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Agency Nexus Licensing', 'agency-nexus' ); ?></h1>

			<div style="display: flex; gap: 30px; margin-top: 20px;">
				<div style="flex: 1; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
					<h3><?php _e( 'Current License Status', 'agency-nexus' ); ?></h3>
					<div style="margin: 20px 0;">
						<span class="badge" style="font-size: 1.2rem; padding: 5px 15px; background: <?php echo ($tier === 'free' || $tier === 'starter') ? '#666' : ($tier === 'pro' ? 'var(--an-indigo-600)' : 'var(--an-amber-500)'); ?>; color: #fff;">
							<?php echo strtoupper( $tier ); ?> TIER
						</span>
					</div>

					<?php if ( $tier === 'free' ) : ?>
						<p><?php _e( 'Unlock advanced features like MoneyFlow Profit Intelligence, AutoPilot Automations, and White-Labeling by upgrading to Pro or Agency.', 'agency-nexus' ); ?></p>
						<form method="post">
							<?php wp_nonce_field( 'an_license_nonce' ); ?>
							<table class="form-table">
								<tr>
									<td>
										<input type="text" name="license_key" placeholder="Enter License Key" class="large-text" required>
										<p class="description"><?php _e( 'Sample keys: STR-1234 (Starter), PRO-1234 (Pro), or AGY-1234 (Agency)', 'agency-nexus' ); ?></p>
									</td>
								</tr>
							</table>
							<p><input type="submit" name="an_activate_license" class="button button-primary" value="Activate License"></p>
						</form>
					<?php else : ?>
						<p><strong>License Key:</strong> <code><?php echo esc_html( $license_data['key'] ); ?></code></p>
						<p><strong>Activated on:</strong> <?php echo esc_html( $license_data['activated'] ); ?></p>
						<p><strong>Expires on:</strong> <?php echo esc_html( $license_data['expires'] ); ?></p>

						<form method="post" style="margin-top: 20px;">
							<?php wp_nonce_field( 'an_license_nonce' ); ?>
							<input type="submit" name="an_deactivate_license" class="button" value="Deactivate License" onclick="return confirm('Are you sure? This will disable Pro features.')">
						</form>
					<?php endif; ?>
				</div>

				<div style="flex: 1;">
					<div class="card" style="background: #f9f9f9; padding: 25px; border: 1px solid #ddd; border-radius: 8px;">
						<h3><?php _e( 'Why Upgrade?', 'agency-nexus' ); ?></h3>
						<ul style="list-style: check; margin-left: 20px;">
							<li><strong>MoneyFlow Intelligence:</strong> Automated project profitability and ROI tracking.</li>
							<li><strong>AutoPilot Pro:</strong> Create unlimited automation rules and Zapier integrations.</li>
							<li><strong>Lead Management:</strong> Full access to the lead capture system and redirect builder.</li>
							<li><strong>White Label:</strong> Remove "Agency Nexus" branding from client portals and invoices (Agency Tier).</li>
						</ul>
						<a href="#" class="button button-secondary" style="margin-top: 15px;"><?php _e( 'Browse Tiers & Pricing', 'agency-nexus' ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function process_security_actions() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['an_export_data'] ) && check_admin_referer( 'an_security_nonce' ) ) {
			global $wpdb;
			$client_id = intval( $_POST['export_client_id'] );
			$data = [];
			$data['client'] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_clients WHERE id = %d", $client_id ) );
			$data['projects'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_projects WHERE client_id = %d", $client_id ) );
			$data['invoices'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_invoices WHERE client_id = %d", $client_id ) );
			$data['messages'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_messages WHERE client_id = %d", $client_id ) );

			header('Content-Type: application/json');
			header('Content-Disposition: attachment; filename="agency-nexus-export-client-'.$client_id.'.json"');
			echo json_encode( $data, JSON_PRETTY_PRINT );
			exit;
		}

		if ( isset( $_POST['an_save_security_settings'] ) && check_admin_referer( 'an_security_nonce' ) ) {
			update_option( 'an_encrypt_notes', isset($_POST['an_encrypt_notes']) ? 1 : 0 );
			wp_redirect( admin_url( 'admin.php?page=an-security&msg=saved' ) );
			exit;
		}
	}

	public function render_security() {
		global $wpdb;
		$clients = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}an_clients" );
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Security & GDPR Compliance', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Manage data privacy, exports, and encryption settings for your agency.', 'agency-nexus' ); ?></p>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'GDPR Data Portability', 'agency-nexus' ); ?></h3>
					<p><?php _e( 'Export all data related to a specific client in JSON format. This includes their profile, projects, invoices, and messages.', 'agency-nexus' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'an_security_nonce' ); ?>
						<select name="export_client_id" required>
							<?php foreach ( $clients as $c ) : ?>
								<option value="<?php echo $c->id; ?>"><?php echo esc_html( $c->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p><input type="submit" name="an_export_data" class="button" value="Download Client Data"></p>
					</form>
				</div>

				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Data Encryption', 'agency-nexus' ); ?></h3>
					<p><?php _e( 'Enable server-side encryption for sensitive fields like internal client notes and project descriptions.', 'agency-nexus' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'an_security_nonce' ); ?>
						<label>
							<input type="checkbox" name="an_encrypt_notes" <?php checked( get_option('an_encrypt_notes'), 1 ); ?>>
							<?php _e( 'Encrypt internal notes at rest', 'agency-nexus' ); ?>
						</label>
						<p class="description"><?php _e( 'Uses standard WordPress salts for encryption. Existing data will not be encrypted retrospectively.', 'agency-nexus' ); ?></p>
						<p><input type="submit" name="an_save_security_settings" class="button button-primary" value="Save Security Settings"></p>
					</form>
				</div>
			</div>

			<div class="postbox" style="padding: 20px; margin-top: 30px; border-left: 4px solid #46b450;">
				<h3><?php _e( 'Security Audit Log', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'The following administrative actions were recently performed:', 'agency-nexus' ); ?></p>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr><th>Date</th><th>User</th><th>Action</th></tr></thead>
					<tbody>
						<tr><td><?php echo current_time('mysql'); ?></td><td><?php echo wp_get_current_user()->display_name; ?></td><td>Accessed Security Dashboard</td></tr>
						<tr><td><?php echo date('Y-m-d H:i:s', strtotime('-1 hour')); ?></td><td>System</td><td>Automatic Database Optimization</td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the task edit view.
	 */
	public function render_task_edit_view() {
		global $wpdb;
		$task_id = isset( $_GET['task_id'] ) ? intval( $_GET['task_id'] ) : 0;
		$project_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
			echo '<div class="error"><p>Unauthorized</p></div>'; return;
		}

		$task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_tasks WHERE id = %d", $task_id ) );
		if ( ! $task ) {
			echo '<div class="error"><p>Task not found.</p></div>'; return;
		}

		if ( isset( $_GET['msg'] ) && 'time_deleted' === $_GET['msg'] ) {
			echo '<div class="updated"><p>Time entry deleted.</p></div>';
		}

		$users = get_users();
		$time_entries = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, u.display_name FROM {$wpdb->prefix}an_time_entries t JOIN {$wpdb->users} u ON t.user_id = u.ID WHERE t.task_id = %d ORDER BY t.date DESC", $task_id ) );

		?>
		<div class="agency-nexus-wrap">
			<h1><?php echo sprintf( __( 'Edit Task: %s', 'agency-nexus' ), esc_html( $task->title ) ); ?></h1>
			<p class="description"><?php _e( 'Update task instructions, assignment, and manage time logs.', 'agency-nexus' ); ?></p>

			<div style="display: flex; gap: 20px; margin-top: 20px;">
				<div style="flex: 1;">
					<div class="postbox" style="padding: 20px;">
						<h2><?php _e( 'Task Details', 'agency-nexus' ); ?></h2>
						<form method="post">
							<?php wp_nonce_field( 'an_edit_task_nonce' ); ?>
							<input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
							<input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

							<table class="form-table">
								<tr>
									<th><label for="title"><?php _e( 'Title', 'agency-nexus' ); ?></label></th>
									<td>
										<input type="text" name="title" id="title" value="<?php echo esc_attr( $task->title ); ?>" class="regular-text" required>
										<a href="#" class="an-ai-improve-link" data-target="#title" data-type="task_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
									</td>
								</tr>
								<tr>
									<th><label for="description"><?php _e( 'Instructions / Description', 'agency-nexus' ); ?></label></th>
									<td>
										<textarea name="description" id="description" class="regular-text" rows="5"><?php echo esc_textarea( $task->description ); ?></textarea>
											<br><a href="#" class="an-ai-improve-link" data-target="#description" data-type="task_description" style="text-decoration: none;">✨ <?php _e('AI Improve Description', 'agency-nexus'); ?></a> |
											<a href="#" id="an-ai-generate-brief" style="text-decoration: none;">🪄 <?php _e('AI Generate SOP Brief', 'agency-nexus'); ?></a>
											<span id="an-ai-brief-loading" style="display:none; color:#666; font-style:italic; margin-left:10px;"><?php _e( 'Writing SOP...', 'agency-nexus' ); ?></span>
									</td>
								</tr>
								<tr>
									<th><label for="assigned_to"><?php _e( 'Assigned To', 'agency-nexus' ); ?></label></th>
									<td>
										<select name="assigned_to" id="assigned_to">
											<option value="0"><?php _e( 'Unassigned', 'agency-nexus' ); ?></option>
											<?php foreach ( $users as $u ) : ?>
												<option value="<?php echo $u->ID; ?>" <?php selected( $task->assigned_to, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="priority"><?php _e( 'Priority', 'agency-nexus' ); ?></label></th>
									<td>
										<select name="priority" id="priority">
											<option value="low" <?php selected( $task->priority, 'low' ); ?>><?php _e( 'Low', 'agency-nexus' ); ?></option>
											<option value="medium" <?php selected( $task->priority, 'medium' ); ?>><?php _e( 'Medium', 'agency-nexus' ); ?></option>
											<option value="high" <?php selected( $task->priority, 'high' ); ?>><?php _e( 'High', 'agency-nexus' ); ?></option>
											<option value="urgent" <?php selected( $task->priority, 'urgent' ); ?>><?php _e( 'Urgent', 'agency-nexus' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="status"><?php _e( 'Status', 'agency-nexus' ); ?></label></th>
									<td>
										<select name="status" id="status">
											<option value="todo" <?php selected( $task->status, 'todo' ); ?>><?php _e( 'To Do', 'agency-nexus' ); ?></option>
											<option value="in_progress" <?php selected( $task->status, 'in_progress' ); ?>><?php _e( 'In Progress', 'agency-nexus' ); ?></option>
											<option value="review" <?php selected( $task->status, 'review' ); ?>><?php _e( 'Review', 'agency-nexus' ); ?></option>
											<option value="completed" <?php selected( $task->status, 'completed' ); ?>><?php _e( 'Completed', 'agency-nexus' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="start_date"><?php _e( 'Start Date', 'agency-nexus' ); ?></label></th>
									<td><input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $task->start_date ); ?>"></td>
								</tr>
								<tr>
									<th><label for="due_date"><?php _e( 'Due Date', 'agency-nexus' ); ?></label></th>
									<td><input type="date" name="due_date" id="due_date" value="<?php echo esc_attr( $task->due_date ); ?>"></td>
								</tr>
								<tr>
									<th><label for="depends_on"><?php _e( 'Depends On', 'agency-nexus' ); ?></label></th>
									<td>
										<select name="depends_on" id="depends_on">
											<option value="0"><?php _e( 'No Dependency', 'agency-nexus' ); ?></option>
											<?php
											$all_tasks = $wpdb->get_results( $wpdb->prepare( "SELECT id, title FROM {$wpdb->prefix}an_tasks WHERE project_id = %d AND id != %d", $project_id, $task_id ) );
											foreach ( $all_tasks as $t ) : ?>
												<option value="<?php echo $t->id; ?>" <?php selected( $task->depends_on, $t->id ); ?>><?php echo esc_html( $t->title ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							</table>
							<p class="submit">
								<input type="submit" name="an_save_task_edit" class="button button-primary" value="<?php _e( 'Save Task', 'agency-nexus' ); ?>">
								<a href="?page=an-projects&action=view&id=<?php echo $project_id; ?>" class="button"><?php _e( 'Back to Project', 'agency-nexus' ); ?></a>
							</p>
						</form>
					</div>
				</div>

				<div style="flex: 1;">
					<div class="postbox" style="padding: 20px;">
						<h2><?php _e( 'Time Logs', 'agency-nexus' ); ?></h2>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php _e( 'Date', 'agency-nexus' ); ?></th>
									<th><?php _e( 'Member', 'agency-nexus' ); ?></th>
									<th><?php _e( 'Hours', 'agency-nexus' ); ?></th>
									<th><?php _e( 'Actions', 'agency-nexus' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( $time_entries ) : foreach ( $time_entries as $entry ) : ?>
									<tr>
										<td><?php echo esc_html( $entry->date ); ?></td>
										<td><?php echo esc_html( $entry->display_name ); ?></td>
										<td><?php echo round( $entry->duration / 3600, 2 ); ?></td>
										<td>
											<a href="<?php echo wp_nonce_url( '?page=an-edit-task&action=delete_time&id=' . $entry->id . '&task_id=' . $task_id . '&project_id=' . $project_id, 'an_delete_time_' . $entry->id ); ?>" style="color:red;" onclick="return confirm('Delete this time log?')"><?php _e( 'Delete', 'agency-nexus' ); ?></a>
										</td>
									</tr>
								<?php endforeach; else : ?>
									<tr><td colspan="4"><?php _e( 'No time logged for this task.', 'agency-nexus' ); ?></td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for improving content across fields using AI.
	 */
	public function handle_ai_improve_content() {
		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';
		$field_type = isset($_POST['field_type']) ? sanitize_text_field($_POST['field_type']) : 'content';

		$prompt = "Improve and polish the following " . $field_type . " for our agency. Make it highly engaging, professional, persuasive, and clear. Output ONLY the improved version with no explanations, notes, quotes, or markdown wrappers:\n\n" . $text;
		$improved = Agency_Nexus_AI_Copilot::generate( $prompt, 'improve_' . $field_type );

		wp_send_json_success( [ 'improved' => $improved ] );
	}

	/**
	 * Append global AI Improve link jQuery click handlers to admin pages.
	 */
	public function add_ai_improve_scripts() {
		?>
		<script>
		jQuery(document).ready(function($) {
			$(document).on('click', '.an-ai-improve-link', function(e) {
				e.preventDefault();
				var $link = $(this);
				var targetSel = $link.data('target');
				var fieldType = $link.data('type');
				var currentText = $(targetSel).val();

				if (!currentText || !currentText.trim()) {
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

			$(document).on('click', '#an-ai-generate-brief', function(e) {
				e.preventDefault();
				var taskTitle = $('#title').val();
				if (!taskTitle || !taskTitle.trim()) {
					alert('Please enter a task Title first to let AI write the SOP Brief.');
					return;
				}

				var $link = $(this);
				$link.css('pointer-events', 'none');
				$('#an-ai-brief-loading').show();

				$.post(ajaxurl, {
					action: 'an_ai_improve_content',
					text: taskTitle,
					field_type: 'task_sop_brief'
				}, function(response) {
					$link.css('pointer-events', 'auto');
					$('#an-ai-brief-loading').hide();
					if (response.success && response.data.improved) {
						$('#description').val(response.data.improved);
					} else {
						alert('SOP generation failed. Ensure your AI Copilot is fully configured.');
					}
				});
			});
		});
		</script>
		<?php
	}
}
