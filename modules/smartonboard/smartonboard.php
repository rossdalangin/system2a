<?php
/**
 * SmartOnboard Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Smartonboard extends Agency_Nexus_Base_Module {

	protected $name = 'SmartOnboard';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
		add_action( 'wp_ajax_an_save_scope', [ $this, 'handle_save_scope' ] );
		add_action( 'wp_ajax_an_sign_proposal', [ $this, 'handle_sign_proposal' ] );
		add_action( 'wp_ajax_an_delete_proposal', [ $this, 'handle_delete_proposal' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}

		if ( isset( $_GET['page'] ) && 'an-scope-settings' === $_GET['page'] ) {
			if ( isset( $_POST['an_save_scope_settings'] ) && check_admin_referer( 'an_scope_settings_nonce' ) ) {
				$services_json     = json_decode( stripslashes( $_POST['services_json'] ), true );
				$scales_json       = json_decode( stripslashes( $_POST['scales_json'] ), true );
				$deliverables_json = json_decode( stripslashes( $_POST['deliverables_json'] ), true );

				if ( is_array( $services_json ) && is_array( $scales_json ) && is_array( $deliverables_json ) ) {
					update_option( 'an_scope_services', $services_json );
					update_option( 'an_scope_scales', $scales_json );
					update_option( 'an_scope_deliverables', $deliverables_json );
					wp_redirect( admin_url( 'admin.php?page=an-scope-settings&msg=saved' ) );
					exit;
				} else {
					wp_redirect( admin_url( 'admin.php?page=an-scope-settings&msg=error' ) );
					exit;
				}
			}
		}
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'project_management' ) ) {
			return;
		}

		add_submenu_page(
			'agency-nexus',
			__( 'Proposals', 'agency-nexus' ),
			__( 'Proposals', 'agency-nexus' ),
			'read',
			'an-proposals',
			[ $this, 'render_proposals' ]
		);

		if ( Agency_Nexus_Permissions::is_admin() ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Scope Builder', 'agency-nexus' ),
				__( 'Scope Builder', 'agency-nexus' ),
				'read',
				'an-scope-builder',
				[ $this, 'render_scope_builder' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Scope Settings', 'agency-nexus' ),
				__( 'Scope Settings', 'agency-nexus' ),
				'read',
				'an-scope-settings',
				[ $this, 'render_settings' ]
			);
		}
	}

	public function render_scope_builder() {
		global $wpdb;
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		$query = "SELECT id, name FROM {$wpdb->prefix}an_clients";
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$query .= " WHERE 1=0";
			} else {
				$authorised_client_ids = $wpdb->get_col( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")" );
				if ( ! empty( $authorised_client_ids ) ) {
					$query .= " WHERE id IN (" . implode( ',', array_map( 'intval', array_unique($authorised_client_ids) ) ) . ")";
				} else {
					$query .= " WHERE 1=0";
				}
			}
		}
		$clients = $wpdb->get_results( $query );

		$services = get_option( 'an_scope_services', [
			'seo' => 'SEO Strategy',
			'web_design' => 'Web Design',
			'social_media' => 'Social Media'
		] );
		$scales = get_option( 'an_scope_scales', [
			'small' => [ 'label' => 'Small', 'budget' => 1000 ],
			'medium' => [ 'label' => 'Medium', 'budget' => 5000 ],
			'large' => [ 'label' => 'Large', 'budget' => 15000 ]
		] );
		$deliverables = get_option( 'an_scope_deliverables', [
			'seo' => [ 'Keyword Report', 'Backlink Audit', 'On-page Optimization' ],
			'web_design' => [ 'Figma Mockups', 'WordPress Setup', 'Responsive Testing' ],
			'social_media' => [ 'Content Calendar', '30 Posts', 'Engagement Report' ]
		] );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Interactive Scope Builder', 'agency-nexus' ); ?></h1>
			<a href="<?php echo admin_url('admin.php?page=an-scope-settings'); ?>" class="page-title-action"><?php _e('Configure Services & Scales', 'agency-nexus'); ?></a>
			<hr class="wp-header-end">
			<p class="description"><?php _e( 'The Scope Builder helps you standardize your service offerings. Select a client, a service type, and the project scale to automatically generate a detailed project plan and budget.', 'agency-nexus' ); ?></p>
			<p><strong><?php _e('Pro Tip:', 'agency-nexus'); ?></strong> <?php _e('You can customize the budgets and deliverables for each service type in the Settings page.', 'agency-nexus'); ?></p>
		</div>
		<?php
		$this->get_template( 'scope-builder', [
			'clients' => $clients,
			'services' => $services,
			'scales' => $scales,
			'deliverables' => $deliverables
		] );
	}

	public function render_settings() {
		if ( isset( $_GET['msg'] ) ) {
			if ( 'saved' === $_GET['msg'] ) {
				echo '<div class="updated"><p>' . __( 'Settings saved!', 'agency-nexus' ) . '</p></div>';
			} elseif ( 'error' === $_GET['msg'] ) {
				echo '<div class="error"><p>' . __( 'Invalid JSON format. Please check your settings.', 'agency-nexus' ) . '</p></div>';
			}
		}

		$services = get_option( 'an_scope_services', [
			'seo' => 'SEO Strategy',
			'web_design' => 'Web Design',
			'social_media' => 'Social Media'
		] );
		$scales = get_option( 'an_scope_scales', [
			'small' => [ 'label' => 'Small', 'budget' => 1000 ],
			'medium' => [ 'label' => 'Medium', 'budget' => 5000 ],
			'large' => [ 'label' => 'Large', 'budget' => 15000 ]
		] );
		$deliverables = get_option( 'an_scope_deliverables', [
			'seo' => [ 'Keyword Report', 'Backlink Audit', 'On-page Optimization' ],
			'web_design' => [ 'Figma Mockups', 'WordPress Setup', 'Responsive Testing' ],
			'social_media' => [ 'Content Calendar', '30 Posts', 'Engagement Report' ]
		] );

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Scope Builder Settings', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Define your service packages here using JSON. This data powers the interactive Scope Builder dropdowns.', 'agency-nexus' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'an_scope_settings_nonce' ); ?>

				<h3><?php _e('Services', 'agency-nexus'); ?></h3>
				<p class="description"><?php _e('Format: "unique_key": "Display Label". e.g., "seo": "SEO Strategy"', 'agency-nexus'); ?></p>
				<textarea name="services_json" rows="5" class="large-text"><?php echo esc_textarea( json_encode( $services, JSON_PRETTY_PRINT ) ); ?></textarea>

				<h3><?php _e('Scales & Budgets', 'agency-nexus'); ?></h3>
				<p class="description"><?php _e('Define tiers like Small, Medium, Large with their associated budgets.', 'agency-nexus'); ?></p>
				<textarea name="scales_json" rows="8" class="large-text"><?php echo esc_textarea( json_encode( $scales, JSON_PRETTY_PRINT ) ); ?></textarea>

				<h3><?php _e('Deliverables by Service', 'agency-nexus'); ?></h3>
				<p class="description"><?php _e('List specific items included for each service type defined above.', 'agency-nexus'); ?></p>
				<textarea name="deliverables_json" rows="8" class="large-text"><?php echo esc_textarea( json_encode( $deliverables, JSON_PRETTY_PRINT ) ); ?></textarea>

				<p class="submit">
					<input type="submit" name="an_save_scope_settings" class="button button-primary" value="Save Settings">
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX handler to save scope as a proposal.
	 */
	public function handle_save_scope() {
		check_ajax_referer( 'an_scope_nonce', 'security' );

		if ( ! Agency_Nexus_Permissions::is_team_member() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$client_id    = intval( $_POST['client_id'] );
		$service_type = sanitize_text_field( $_POST['service_type'] );
		$scale_key    = sanitize_text_field( $_POST['scale'] );
		$scales       = get_option( 'an_scope_scales', [] );
		$budget       = isset( $scales[ $scale_key ]['budget'] ) ? $scales[ $scale_key ]['budget'] : 0;

		$addon_total  = isset( $_POST['addon_total'] ) ? floatval( $_POST['addon_total'] ) : 0;
		$budget      += $addon_total;

		$expiry_date   = isset( $_POST['expiry_date'] ) ? sanitize_text_field( $_POST['expiry_date'] ) : '';
		$special_terms = isset( $_POST['special_terms'] ) ? sanitize_textarea_field( $_POST['special_terms'] ) : '';

		// Compute discount dynamically from special terms text
		$discount = 0;
		if ( ! empty( $special_terms ) ) {
			// Check for percentage discount (e.g., "10%")
			if ( preg_match( '/(\\d+)\\s*%/i', $special_terms, $matches ) ) {
				$percentage = floatval( $matches[1] );
				$discount = $budget * ( $percentage / 100 );
			}
			// Check for flat dollar discount (e.g., "$500" or "500 discount" or "500 off")
			elseif ( preg_match( '/\\$\\s*(\\d+(\\.\\d{2})?)/i', $special_terms, $matches ) ) {
				$discount = floatval( $matches[1] );
			} elseif ( preg_match( '/(\\d+(\\.\\d{2})?)\\s*(off|discount|usd)/i', $special_terms, $matches ) ) {
				$discount = floatval( $matches[1] );
			}
		}

		$final_budget = max( 0, $budget - $discount );

		$deliverables = isset( $_POST['deliverables'] ) ? (array) $_POST['deliverables'] : [];

		$description = __( 'Generated from Scope Builder.', 'agency-nexus' ) . "\n\n";
		if ( ! empty( $deliverables ) ) {
			$description .= __( 'Selected Deliverables:', 'agency-nexus' ) . "\n- " . implode( "\n- ", array_map( 'sanitize_text_field', $deliverables ) ) . "\n\n";
		}
		if ( ! empty( $expiry_date ) ) {
			$description .= __( 'Proposal Expiry Date:', 'agency-nexus' ) . " " . $expiry_date . "\n";
		}
		if ( ! empty( $special_terms ) ) {
			$description .= __( 'Special Terms / Discounts:', 'agency-nexus' ) . " " . $special_terms . "\n";
			if ( $discount > 0 ) {
				$description .= sprintf( __( 'Applied Discount: -$%s', 'agency-nexus' ), number_format( $discount, 2 ) ) . "\n";
			}
		}
		$description .= sprintf( __( 'Total Price: $%s', 'agency-nexus' ), number_format( $final_budget, 2 ) ) . "\n";

		if ( get_option( 'an_ai_enabled', 'no' ) === 'yes' ) {
			$prompt = "Create a comprehensive, highly-converting professional project proposal based on: " . $description;
			$ai_proposal = Agency_Nexus_AI_Copilot::generate( $prompt, 'proposal' );
			if ( ! empty( $ai_proposal ) ) {
				$description = $ai_proposal;
			}
		}

		$wpdb->insert(
			$wpdb->prefix . 'an_proposals',
			[
				'client_id'   => $client_id,
				'title'       => sprintf( '%s Proposal (%s)', ucfirst( $service_type ), ucfirst( $scale_key ) ),
				'description' => $description,
				'budget'      => $final_budget,
				'status'      => 'sent',
				'created_at'  => current_time( 'mysql' )
			]
		);

		wp_send_json_success( [ 'proposal_id' => $wpdb->insert_id ] );
	}

	public function render_proposals() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_proposals';
		$clients_table = $wpdb->prefix . 'an_clients';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $id && ! Agency_Nexus_Permissions::can_access_messages( $wpdb->get_var( $wpdb->prepare( "SELECT client_id FROM $table_name WHERE id = %d", $id ) ) ) ) {
			echo '<div class="error"><p>Unauthorized</p></div>'; return;
		}

		if ( $action === 'view' && $id ) {
			$proposal = $wpdb->get_row( $wpdb->prepare( "SELECT p.*, c.name as client_name FROM $table_name p JOIN $clients_table c ON p.client_id = c.id WHERE p.id = %d", $id ) );
			$this->get_template( 'view-proposal', [ 'proposal' => $proposal ] );
			return;
		}

		$query = "SELECT p.*, c.name as client_name FROM $table_name p JOIN $clients_table c ON p.client_id = c.id";
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		if ( is_array( $authorised_ids ) ) {
			if ( Agency_Nexus_Permissions::is_client() ) {
				$client_id = Agency_Nexus_Permissions::get_client_id_for_user( get_current_user_id() );
				$query .= $wpdb->prepare( " WHERE p.client_id = %d", $client_id );
			} else {
				// Team members see proposals for clients they have projects with
				if ( empty( $authorised_ids ) ) {
					$query .= " WHERE 1=0";
				} else {
					$client_ids = $wpdb->get_col( "SELECT client_id FROM {$wpdb->prefix}an_projects WHERE id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")" );
					if ( ! empty( $client_ids ) ) {
						$query .= " WHERE p.client_id IN (" . implode( ',', array_map( 'intval', array_unique($client_ids) ) ) . ")";
					} else {
						$query .= " WHERE 1=0";
					}
				}
			}
		}
		$proposals = $wpdb->get_results( $query );

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Agency Proposals', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Review and manage your project proposals. Proposals accepted and signed by clients are automatically converted into Projects.', 'agency-nexus' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Title</th><th>Client</th><th>Budget</th><th>Status</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach ( $proposals as $prop ) : ?>
						<tr id="proposal-<?php echo $prop->id; ?>">
							<td><strong><?php echo esc_html( $prop->title ); ?></strong></td>
							<td><?php echo esc_html( $prop->client_name ); ?></td>
							<td>$<?php echo number_format( $prop->budget, 2 ); ?></td>
							<td><span class="badge status-<?php echo $prop->status; ?>"><?php echo ucfirst( $prop->status ); ?></span></td>
							<td>
								<a href="?page=an-proposals&action=view&id=<?php echo $prop->id; ?>"><?php _e( 'View', 'agency-nexus' ); ?></a>
								<?php if ( Agency_Nexus_Permissions::is_team_member() ) : ?>
								| <a href="#" onclick="deleteProposal(<?php echo $prop->id; ?>)" style="color:red;"><?php _e( 'Delete', 'agency-nexus' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; if(empty($proposals)) echo '<tr><td colspan="5">No proposals found.</td></tr>'; ?>
				</tbody>
			</table>
		</div>
		<script>
		function deleteProposal(id) {
			if(!confirm('Delete this proposal?')) return;
			jQuery.post(ajaxurl, { action: 'an_delete_proposal', id: id, security: '<?php echo wp_create_nonce("an_proposal_nonce"); ?>' }, function() {
				jQuery('#proposal-' + id).fadeOut();
			});
		}
		</script>
		<?php
	}

	public function handle_sign_proposal() {
		check_ajax_referer( 'an_proposal_nonce', 'security' );
		global $wpdb;
		$id = intval( $_POST['id'] );
		$signature = sanitize_text_field( $_POST['signature'] );
		$proposal = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_proposals WHERE id = %d", $id ) );

		if ( ! $proposal || ! Agency_Nexus_Permissions::is_client() ) wp_send_json_error( 'Unauthorized' );

		$wpdb->update( $wpdb->prefix . 'an_proposals', [
			'status' => 'accepted',
			'signature' => $signature,
			'signed_at' => current_time( 'mysql' )
		], [ 'id' => $id ] );

		// Convert to Project
		$wpdb->insert( $wpdb->prefix . 'an_projects', [
			'client_id' => $proposal->client_id,
			'title' => str_replace( 'Proposal', 'Project', $proposal->title ),
			'description' => $proposal->description . "\n\nSigned by: " . $signature . " at " . current_time( 'mysql' ),
			'budget' => $proposal->budget,
			'status' => 'planned',
			'created_at' => current_time( 'mysql' )
		] );

		wp_send_json_success();
	}

	public function handle_delete_proposal() {
		check_ajax_referer( 'an_proposal_nonce', 'security' );
		if ( ! Agency_Nexus_Permissions::is_team_member() ) wp_send_json_error( 'Unauthorized' );
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'an_proposals', [ 'id' => intval( $_POST['id'] ) ] );
		wp_send_json_success();
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'project_management' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}
		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'SmartOnboard', 'agency-nexus' ); ?></h2>
			<p><?php _e( 'Ready to onboard new clients? Use the Scope Builder to get started.', 'agency-nexus' ); ?></p>
			<a href="<?php echo admin_url( 'admin.php?page=an-scope-builder' ); ?>" class="button button-primary"><?php _e( 'Open Scope Builder', 'agency-nexus' ); ?></a>
		</div>
		<?php
	}
}
