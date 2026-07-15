<?php
/**
 * ClientSync Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Clientsync extends Agency_Nexus_Base_Module {

	protected $name = 'ClientSync';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'wp_ajax_an_send_message', [ $this, 'handle_send_message' ] );
		add_action( 'wp_ajax_an_get_messages', [ $this, 'handle_get_messages' ] );
		add_action( 'wp_ajax_an_share_file', [ $this, 'handle_share_file' ] );
		add_action( 'wp_ajax_an_get_files', [ $this, 'handle_get_files' ] );
		add_action( 'wp_ajax_an_delete_message', [ $this, 'handle_delete_message' ] );
		add_action( 'wp_ajax_an_delete_file', [ $this, 'handle_delete_file' ] );
		add_action( 'wp_ajax_an_ai_suggest_response', [ $this, 'handle_ai_suggest_response' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'agency_nexus_project_status_updated', [ $this, 'auto_send_satisfaction_check' ], 10, 2 );
	}

	public function auto_send_satisfaction_check( $project_id, $status ) {
		if ( 'completed' !== $status ) return;

		global $wpdb;
		$client_id = $wpdb->get_var( $wpdb->prepare( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id = %d", $project_id ) );
		if ( ! $client_id ) return;

		$wpdb->insert( $wpdb->prefix . 'an_messages', [
			'client_id'  => $client_id,
			'sender_id'  => 0, // System
			'message'    => __( "Congratulations on completing your project! We'd love to hear about your experience. How did we do?", 'agency-nexus' ),
			'created_at' => current_time( 'mysql' )
		] );
	}

	public function enqueue_scripts( $hook ) {
		if ( 'agency-nexus_page_an-messages' !== $hook ) {
			return;
		}
		wp_enqueue_media();
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'messaging' ) ) {
			return;
		}

		add_submenu_page(
			'agency-nexus',
			__( 'Messages', 'agency-nexus' ),
			__( 'Messages', 'agency-nexus' ),
			'read',
			'an-messages',
			[ $this, 'render_messages' ]
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Meetings', 'agency-nexus' ),
			__( 'Meetings', 'agency-nexus' ),
			'read',
			'an-meetings',
			[ $this, 'render_meetings' ]
		);
	}

	public function render_meetings() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_meetings';
		$clients_table = $wpdb->prefix . 'an_clients';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_POST['an_save_meeting'] ) && check_admin_referer( 'an_meeting_nonce' ) ) {
			$data = [
				'client_id'  => intval( $_POST['client_id'] ),
				'title'      => sanitize_text_field( $_POST['title'] ),
				'start_time' => date( 'Y-m-d H:i:s', strtotime( $_POST['start_time'] ) ),
				'end_time'   => date( 'Y-m-d H:i:s', strtotime( $_POST['end_time'] ) ),
				'timezone'   => sanitize_text_field( $_POST['timezone'] ),
				'location'   => sanitize_text_field( $_POST['location'] ),
				'created_at' => current_time( 'mysql' )
			];
			$wpdb->insert( $table_name, $data );
			wp_redirect( admin_url( 'admin.php?page=an-meetings&msg=added' ) );
			exit;
		}

		$clients = $wpdb->get_results( "SELECT id, name FROM $clients_table" );
		$meetings = $wpdb->get_results( "SELECT m.*, c.name as client_name FROM $table_name m JOIN $clients_table c ON m.client_id = c.id ORDER BY m.start_time ASC" );

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Meeting Scheduler', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Coordinate with clients across timezones. Schedule strategy sessions, demos, and project reviews.', 'agency-nexus' ); ?></p>

			<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 30px;">
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Schedule New Meeting', 'agency-nexus' ); ?></h3>
					<form method="post">
						<?php wp_nonce_field( 'an_meeting_nonce' ); ?>
						<table class="form-table">
							<tr><td>
								<label><?php _e( 'Client', 'agency-nexus' ); ?></label><br>
								<select name="client_id" required style="width: 100%;">
									<?php foreach ( $clients as $c ) : ?>
										<option value="<?php echo $c->id; ?>"><?php echo esc_html($c->name); ?></option>
									<?php endforeach; ?>
								</select>
							</td></tr>
							<tr><td>
								<label><?php _e( 'Title', 'agency-nexus' ); ?></label><br>
								<input type="text" name="title" required class="regular-text" style="width: 100%;">
							</td></tr>
							<tr><td>
								<label><?php _e( 'Start Time', 'agency-nexus' ); ?></label><br>
								<input type="datetime-local" name="start_time" required style="width: 100%;">
							</td></tr>
							<tr><td>
								<label><?php _e( 'End Time', 'agency-nexus' ); ?></label><br>
								<input type="datetime-local" name="end_time" required style="width: 100%;">
							</td></tr>
							<tr><td>
								<label><?php _e( 'Timezone', 'agency-nexus' ); ?></label><br>
								<select name="timezone" style="width: 100%;">
									<option value="UTC">UTC</option>
									<option value="America/New_York">EST (New York)</option>
									<option value="Europe/London">GMT (London)</option>
									<option value="Asia/Tokyo">JST (Tokyo)</option>
								</select>
							</td></tr>
							<tr><td>
								<label><?php _e( 'Location (Link or Address)', 'agency-nexus' ); ?></label><br>
								<input type="text" name="location" class="regular-text" style="width: 100%;" placeholder="e.g. Google Meet Link">
							</td></tr>
						</table>
						<p class="submit"><input type="submit" name="an_save_meeting" class="button button-primary" value="Schedule Meeting"></p>
					</form>
				</div>
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Upcoming Meetings', 'agency-nexus' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Date & Time</th><th>Client</th><th>Title</th><th>Location</th></tr></thead>
						<tbody>
							<?php foreach ( $meetings as $m ) : ?>
								<tr>
									<td><?php echo date('M d, H:i', strtotime($m->start_time)); ?> (<?php echo $m->timezone; ?>)</td>
									<td><?php echo esc_html($m->client_name); ?></td>
									<td><strong><?php echo esc_html($m->title); ?></strong></td>
									<td><?php echo $m->location ? '<a href="'.esc_url($m->location).'" target="_blank">Join</a>' : '<em>None</em>'; ?></td>
								</tr>
							<?php endforeach; if(empty($meetings)) echo '<tr><td colspan="4">No meetings scheduled.</td></tr>'; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_messages() {
		global $wpdb;
		$is_team = Agency_Nexus_Permissions::is_team_member();
		$is_admin = Agency_Nexus_Permissions::is_admin();
		?>
		<div class="wrap">
			<h1><?php _e('Messaging Hub', 'agency-nexus'); ?></h1>
			<p class="description"><?php _e('Collaborate with clients in real-time. Share project updates, files, and feedback within a secure, dedicated environment.', 'agency-nexus'); ?></p>
		</div>
		<?php
		if ( $is_admin ) {
			$clients = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}an_clients" );
			$responses = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}an_canned_responses" );
		} elseif ( $is_team ) {
			$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
			if ( ! empty($authorised_ids) ) {
				$authorised_client_ids = $wpdb->get_col( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")" );
				if ( ! empty($authorised_client_ids) ) {
					$clients = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}an_clients WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_client_ids ) ) . ")" );
				} else {
					$clients = [];
				}
			} else {
				$clients = [];
			}
			$responses = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}an_canned_responses" );
		} else {
			$client_id = Agency_Nexus_Permissions::get_client_id_for_user( get_current_user_id() );
			$clients = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$wpdb->prefix}an_clients WHERE id = %d", $client_id ) );
			$responses = []; // Clients don't get canned responses
		}

		$this->get_template( 'messages', [ 'clients' => $clients, 'responses' => $responses, 'is_team' => $is_team ] );
	}

	/**
	 * AJAX handler for fetching messages.
	 */
	public function handle_get_messages() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );

		$client_id = intval( $_POST['client_id'] );
		if ( ! Agency_Nexus_Permissions::can_access_messages( $client_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;

		$messages = $wpdb->get_results( $wpdb->prepare( "
			SELECT m.*, u.display_name as sender_name
			FROM {$wpdb->prefix}an_messages m
			LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
			WHERE m.client_id = %d
			ORDER BY m.created_at ASC
		", $client_id ) );

		wp_send_json_success( $messages );
	}

	/**
	 * AJAX handler for sharing files.
	 */
	public function handle_share_file() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );

		$client_id = intval( $_POST['client_id'] );
		if ( ! Agency_Nexus_Permissions::can_access_messages( $client_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$file_url  = esc_url_raw( $_POST['file_url'] );
		$file_name = sanitize_text_field( $_POST['file_name'] );

		$wpdb->insert(
			$wpdb->prefix . 'an_shared_files',
			[
				'client_id' => $client_id,
				'user_id'   => get_current_user_id(),
				'file_url'  => $file_url,
				'file_name' => $file_name
			]
		);

		wp_send_json_success();
	}

	/**
	 * AJAX handler for deleting a message.
	 */
	public function handle_delete_message() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );
		if ( ! Agency_Nexus_Permissions::is_team_member() ) wp_send_json_error( 'Unauthorized' );
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'an_messages', [ 'id' => intval( $_POST['message_id'] ) ] );
		wp_send_json_success();
	}

	/**
	 * AJAX handler for deleting a file.
	 */
	public function handle_delete_file() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );
		if ( ! Agency_Nexus_Permissions::is_team_member() ) wp_send_json_error( 'Unauthorized' );
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'an_shared_files', [ 'id' => intval( $_POST['file_id'] ) ] );
		wp_send_json_success();
	}

	/**
	 * AJAX handler for fetching shared files.
	 */
	public function handle_get_files() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );

		$client_id = intval( $_POST['client_id'] );
		if ( ! Agency_Nexus_Permissions::can_access_messages( $client_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;

		$files = $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}an_shared_files
			WHERE client_id = %d
			ORDER BY created_at DESC
		", $client_id ) );

		wp_send_json_success( $files );
	}

	/**
	 * AJAX handler for sending messages.
	 */
	public function handle_send_message() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );

		$client_id = intval( $_POST['client_id'] );
		if ( ! Agency_Nexus_Permissions::can_access_messages( $client_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error( 'Message content is required.' );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'an_messages',
			[
				'client_id'  => $client_id,
				'sender_id'  => get_current_user_id(),
				'message'    => $message,
				'created_at' => current_time( 'mysql' )
			]
		);

		if ( false === $result ) {
			wp_send_json_error( 'Failed to save message to the database: ' . $wpdb->last_error );
		}

		// After-hours Auto-responder
		if ( Agency_Nexus_Permissions::is_client() ) {
			$now = current_time( 'H:i' );
			$start = get_option( 'an_comm_start', '09:00' );
			$end = get_option( 'an_comm_end', '17:00' );

			if ( $now < $start || $now > $end ) {
				$wpdb->insert( $wpdb->prefix . 'an_messages', [
					'client_id'  => $client_id,
					'sender_id'  => 0, // System/Auto
					'message'    => get_option( 'an_after_hours_msg', "I am currently away. Office hours: $start - $end." ),
					'created_at' => current_time( 'mysql', 1 ) // Slightly offset
				] );
			}
		}

		wp_send_json_success();
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'messaging' ) ) {
			return;
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$where = "WHERE is_read = 0 AND sender_id != $user_id";

		if ( Agency_Nexus_Permissions::is_client() ) {
			$client_id = Agency_Nexus_Permissions::get_client_id_for_user($user_id);
			$where .= $wpdb->prepare(" AND client_id = %d", $client_id);
		} else {
			$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
			if ( is_array( $authorised_ids ) ) {
				if ( empty( $authorised_ids ) ) {
					$where .= " AND 1=0";
				} else {
					$client_ids = $wpdb->get_col( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")" );
					if ( ! empty( $client_ids ) ) {
						$where .= " AND client_id IN (" . implode( ',', array_map( 'intval', array_unique($client_ids) ) ) . ")";
					} else {
						$where .= " AND 1=0";
					}
				}
			}
		}

		$unread_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}an_messages $where" );
		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'ClientSync', 'agency-nexus' ); ?></h2>
			<p><?php echo sprintf( _n( 'You have %d unread message.', 'You have %d unread messages.', $unread_count, 'agency-nexus' ), $unread_count ); ?></p>
			<a href="<?php echo admin_url( 'admin.php?page=an-messages' ); ?>" class="button"><?php _e( 'Open Inbox', 'agency-nexus' ); ?></a>
		</div>
		<?php
	}

	/**
	 * AJAX handler for suggesting a message response using AI.
	 */
	public function handle_ai_suggest_response() {
		check_ajax_referer( 'an_clientsync_nonce', 'security' );
		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
		global $wpdb;
		// Retrieve the last message from the client (not sent by current user)
		$last_client_msg = $wpdb->get_var( $wpdb->prepare( "
			SELECT message
			FROM {$wpdb->prefix}an_messages
			WHERE client_id = %d AND sender_id != %d
			ORDER BY created_at DESC
			LIMIT 1
		", $client_id, get_current_user_id() ) );

		if ( empty( $last_client_msg ) ) {
			$prompt = "Generate a professional, friendly check-in or introductory message for a client.";
		} else {
			$prompt = "Draft a professional, friendly response to the following client message: \"" . $last_client_msg . "\"";
		}

		$suggestion = Agency_Nexus_AI_Copilot::generate( $prompt, 'message' );
		wp_send_json_success( [ 'suggestion' => $suggestion ] );
	}
}
