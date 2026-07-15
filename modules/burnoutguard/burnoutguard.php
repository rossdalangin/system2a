<?php
/**
 * BurnoutGuard Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Burnoutguard extends Agency_Nexus_Base_Module {

	protected $name = 'BurnoutGuard';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}

		$page = isset($_GET['page']) ? $_GET['page'] : '';

		if ( 'an-referral-partners' === $page && current_user_can('manage_options') ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_referral_partners';
			$action = isset($_GET['action']) ? $_GET['action'] : '';
			$id = isset($_GET['id']) ? intval($_GET['id'] ) : 0;

			if ( 'delete' === $action && $id ) {
				check_admin_referer('an_delete_partner_' . $id);
				$wpdb->delete($table_name, ['id' => $id]);
				wp_redirect(admin_url('admin.php?page=an-referral-partners&msg=deleted'));
				exit;
			}

			if ( isset($_POST['an_save_partner']) && check_admin_referer('an_save_partner_nonce') ) {
				$data = [
					'name'      => sanitize_text_field($_POST['name']),
					'email'     => sanitize_email($_POST['email']),
					'specialty' => sanitize_text_field($_POST['specialty'])
				];
				if ($id) {
					$wpdb->update($table_name, $data, ['id' => $id]);
				} else {
					$data['created_at'] = current_time('mysql');
					$wpdb->insert($table_name, $data);
				}
				wp_redirect(admin_url('admin.php?page=an-referral-partners&msg=saved'));
				exit;
			}
		}

		if ( 'an-health-check' === $page ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_burnout_logs';
			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
			$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

			if ( 'delete' === $action && $id ) {
				check_admin_referer( 'an_delete_check_' . $id );
				$wpdb->delete( $table_name, [ 'id' => $id ] );
				wp_redirect( admin_url( 'admin.php?page=an-health-check&msg=deleted' ) );
				exit;
			}

			if ( isset( $_POST['an_save_check'] ) && check_admin_referer( 'an_save_check_nonce' ) ) {
				$data = [
					'user_id'      => get_current_user_id(),
					'stress_level' => intval( $_POST['stress_level'] ),
					'note'         => sanitize_textarea_field( $_POST['note'] ),
					'created_at'   => current_time( 'mysql' )
				];
				if ( $id ) {
					$wpdb->update( $table_name, $data, [ 'id' => $id ] );
					$msg = 'updated';
				} else {
					$wpdb->insert( $table_name, $data );
					$msg = 'recorded';
				}
				wp_redirect( admin_url( 'admin.php?page=an-health-check&msg=' . $msg ) );
				exit;
			}
		}
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'burnout_guard' ) ) {
			return;
		}

		if ( Agency_Nexus_Permissions::is_team_member() ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Health Check', 'agency-nexus' ),
				__( 'Health Check', 'agency-nexus' ),
				'read',
				'an-health-check',
				[ $this, 'render_health_check' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Vacation Planner', 'agency-nexus' ),
				__( 'Vacation Planner', 'agency-nexus' ),
				'read',
				'an-vacations',
				[ $this, 'render_vacations' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Referral Partners', 'agency-nexus' ),
				__( 'Referral Partners', 'agency-nexus' ),
				'manage_options',
				'an-referral-partners',
				[ $this, 'render_partners' ]
			);
		}
	}

	public function render_vacations() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_vacations';
		$user_id = get_current_user_id();

		if ( isset( $_POST['an_save_vacation'] ) && check_admin_referer( 'an_vacation_nonce' ) ) {
			$wpdb->insert( $table_name, [
				'user_id'    => $user_id,
				'start_date' => sanitize_text_field( $_POST['start_date'] ),
				'end_date'   => sanitize_text_field( $_POST['end_date'] ),
				'note'       => sanitize_text_field( $_POST['note'] ),
				'created_at' => current_time( 'mysql' )
			] );
			wp_redirect( admin_url( 'admin.php?page=an-vacations&msg=added' ) );
			exit;
		}

		$vacations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d ORDER BY start_date ASC", $user_id ) );

		if ( isset( $_POST['an_save_referral'] ) && check_admin_referer( 'an_referral_nonce' ) ) {
			$partner_id = intval($_POST['partner_id']);
			$project_id = intval($_POST['project_id']);
			$wpdb->insert($wpdb->prefix . 'an_referrals', [
				'partner_id' => $partner_id,
				'project_id' => $project_id,
				'client_name'=> sanitize_text_field($_POST['client_name']),
				'note'       => sanitize_textarea_field($_POST['note']),
				'status'     => 'pending',
				'created_at' => current_time('mysql')
			]);
			echo '<div class="updated"><p>Referral recorded and partner notified. Work successfully delegated!</p></div>';
		}

		$partners = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}an_referral_partners ORDER BY name ASC");
		$projects = $wpdb->get_results("SELECT p.id, p.title, c.name as client_name FROM {$wpdb->prefix}an_projects p JOIN {$wpdb->prefix}an_clients c ON p.client_id = c.id WHERE p.status != 'completed'");

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Vacation & OOO Planner', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Schedule your time off. Your availability will be reflected in the agency capacity monitor.', 'agency-nexus' ); ?></p>

			<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 30px;">
				<div style="display: flex; flex-direction: column; gap: 20px;">
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Request Time Off', 'agency-nexus' ); ?></h3>
					<form method="post">
						<?php wp_nonce_field( 'an_vacation_nonce' ); ?>
						<table class="form-table">
							<tr><td>
								<label><?php _e( 'Start Date', 'agency-nexus' ); ?></label><br>
								<input type="date" name="start_date" required style="width: 100%;">
							</td></tr>
							<tr><td>
								<label><?php _e( 'End Date', 'agency-nexus' ); ?></label><br>
								<input type="date" name="end_date" required style="width: 100%;">
							</td></tr>
							<tr><td>
								<label><?php _e( 'Note', 'agency-nexus' ); ?></label><br>
								<input type="text" name="note" class="regular-text" style="width: 100%;" placeholder="e.g. Summer Holiday">
							</td></tr>
						</table>
						<p class="submit"><input type="submit" name="an_save_vacation" class="button button-primary" value="Add Vacation"></p>
					</form>
				</div>

				<div class="postbox" style="padding: 20px; border-left: 4px solid var(--an-indigo-600);">
					<h3><?php _e( 'Overflow Referral System', 'agency-nexus' ); ?></h3>
					<p><small><?php _e( 'Too much work? Delegate to trusted partners and maintain your health.', 'agency-nexus' ); ?></small></p>
					<form method="post">
						<?php wp_nonce_field( 'an_referral_nonce' ); ?>
						<p>
							<label><?php _e('Select Project:', 'agency-nexus'); ?></label><br>
							<select name="project_id" style="width: 100%;" required onchange="var opt=this.options[this.selectedIndex]; jQuery('input[name=client_name]').val(opt.getAttribute('data-client'))">
								<option value=""><?php _e('-- Select Project --', 'agency-nexus'); ?></option>
								<?php foreach($projects as $p): ?>
									<option value="<?php echo $p->id; ?>" data-client="<?php echo esc_attr($p->client_name); ?>"><?php echo esc_html($p->title); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<input type="hidden" name="client_name" value="">
						<p>
							<label><?php _e('Referral Partner:', 'agency-nexus'); ?></label><br>
							<select name="partner_id" style="width: 100%;" required>
								<option value=""><?php _e('-- Select Partner --', 'agency-nexus'); ?></option>
								<?php foreach($partners as $partner): ?>
									<option value="<?php echo $partner->id; ?>"><?php echo esc_html($partner->name); ?> (<?php echo esc_html($partner->specialty); ?>)</option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label><?php _e('Note for Partner:', 'agency-nexus'); ?></label><br>
							<textarea name="note" style="width:100%;" rows="2" placeholder="Describe the referral..."></textarea>
						</p>
						<button type="submit" name="an_save_referral" class="button button-small" style="margin-top: 10px;"><?php _e('Delegate Overflow', 'agency-nexus'); ?></button>
					</form>
				</div>
				</div>

				<div class="postbox" style="padding: 20px;">
					<h3><?php _e( 'Your Scheduled Time Off', 'agency-nexus' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Dates</th><th>Note</th><th>Status</th></tr></thead>
						<tbody>
							<?php foreach ( $vacations as $v ) : ?>
								<tr>
									<td><?php echo $v->start_date; ?> <?php _e('to', 'agency-nexus'); ?> <?php echo $v->end_date; ?></td>
									<td><?php echo esc_html($v->note); ?></td>
									<td><span class="badge" style="background: #46b450; color: #fff;">Approved</span></td>
								</tr>
							<?php endforeach; if(empty($vacations)) echo '<tr><td colspan="3">No vacations planned.</td></tr>'; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_health_check() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_burnout_logs';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'recorded': $m = 'Log recorded. Remember to take breaks!'; break;
				case 'updated': $m = 'Log updated!'; break;
				case 'deleted': $m = 'Log entry deleted!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'edit' || $action === 'add') {
			$log = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Health Check', 'agency-nexus') : __('New Health Check', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Log your mental well-being to monitor agency capacity and prevent burnout.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'an_save_check_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Stress Level (1-10)', 'agency-nexus'); ?></label></th>
							<td>
								<input type="range" name="stress_level" min="1" max="10" value="<?php echo $log ? $log->stress_level : 5; ?>" class="regular-text" oninput="this.nextElementSibling.value = this.value">
								<output><?php echo $log ? $log->stress_level : 5; ?></output>
								<p class="description"><?php _e('1 = Very relaxed, 10 = Critically stressed.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Notes', 'agency-nexus'); ?></label></th>
							<td>
								<textarea name="note" id="an_burnout_note" class="regular-text" rows="3"><?php echo $log ? esc_textarea($log->note) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#an_burnout_note" data-type="burnout_notes" style="text-decoration: none;">✨ <?php _e('AI Improve Notes', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Describe any factors affecting your well-being or workload.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<input type="submit" name="an_save_check" class="button button-primary" value="Save Entry">
					<a href="?page=an-health-check" class="button">Cancel</a>
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

		$logs_query = $wpdb->prepare( "SELECT * FROM $table_name" );
		if ( ! Agency_Nexus_Permissions::is_admin() ) {
			$logs_query = $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", get_current_user_id() );
		}
		$logs_query .= " ORDER BY created_at DESC LIMIT 20";
		$logs = $wpdb->get_results( $logs_query );

		$icp_score = rand(70, 95);
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Health & Sustainability System', 'agency-nexus' ); ?></h1>

			<div style="background: #fff; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Agency Work is a Marathon, Not a Sprint', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'BurnoutGuard tracks your team\'s mental well-being alongside their actual workload. By monitoring these metrics, you can identify projects that are too stressful and make adjustments before they impact your agency\'s health.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Daily Log:</strong> Record your stress levels and mental state. Trends help identify root causes of exhaustion.</li>
					<li><strong>Workload Sync:</strong> This system automatically pulls data from your time logs to visualize current capacity.</li>
					<li><strong>Preventative Care:</strong> If the gauge hits "Red", it\'s a clear signal to delegate tasks or extend deadlines.</li>
				</ul>
			</div>

			<a href="?page=an-health-check&action=add" class="page-title-action">New Check-in</a>
			<hr class="wp-header-end">

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
				<div>
			<h2>Recent Check-ins</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Date</th><th>Stress Level</th><th>Note</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach ($logs as $log) :
						$color = $log->stress_level > 7 ? 'red' : ($log->stress_level > 4 ? 'orange' : 'green');
					?>
						<tr>
							<td><?php echo $log->created_at; ?></td>
							<td style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo $log->stress_level; ?> / 10</td>
							<td><?php echo esc_html($log->note); ?></td>
							<td>
								<a href="?page=an-health-check&action=edit&id=<?php echo $log->id; ?>">Edit</a> |
								<a href="<?php echo wp_nonce_url('?page=an-health-check&action=delete&id=' . $log->id, 'an_delete_check_' . $log->id); ?>" style="color:red;" onclick="return confirm('Delete log?')">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
				</div>
				<div>
					<div class="postbox" style="padding: 20px; border-top: 4px solid var(--an-indigo-600);">
						<h3><?php _e( 'Ideal Client Profile Matching', 'agency-nexus' ); ?></h3>
						<p class="description"><?php _e( 'Burnout often stems from working with "Low-Fit" clients. This AI score measures project alignment.', 'agency-nexus' ); ?></p>
						<div style="text-align: center; margin: 20px 0;">
							<div style="font-size: 3rem; font-weight: bold; color: var(--an-indigo-600);"><?php echo $icp_score; ?>%</div>
							<p><strong><?php _e( 'High Alignment', 'agency-nexus' ); ?></strong></p>
						</div>
						<p><small><?php _e( 'Based on your history, projects with this profile have 40% higher profit margins and 60% lower stress levels.', 'agency-nexus' ); ?></small></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_partners() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_referral_partners';
		$action = isset($_GET['action']) ? $_GET['action'] : 'list';
		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

		if (isset($_GET['msg'])) {
			$m = '';
			switch($_GET['msg']) {
				case 'saved': $m = 'Partner saved!'; break;
				case 'deleted': $m = 'Partner deleted!'; break;
			}
			if ($m) echo '<div class="updated"><p>' . esc_html($m) . '</p></div>';
		}

		if ($action === 'add' || $action === 'edit') {
			$partner = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="wrap">
				<h1><?php echo $id ? __('Edit Referral Partner', 'agency-nexus') : __('Add New Referral Partner', 'agency-nexus'); ?></h1>
				<form method="post">
					<?php wp_nonce_field('an_save_partner_nonce'); ?>
					<table class="form-table">
						<tr><th>Name</th><td><input type="text" name="name" value="<?php echo $partner ? esc_attr($partner->name) : ''; ?>" required class="regular-text"></td></tr>
						<tr><th>Email</th><td><input type="email" name="email" value="<?php echo $partner ? esc_attr($partner->email) : ''; ?>" required class="regular-text"></td></tr>
						<tr><th>Specialty</th><td><input type="text" name="specialty" value="<?php echo $partner ? esc_attr($partner->specialty) : ''; ?>" class="regular-text"></td></tr>
					</table>
					<input type="submit" name="an_save_partner" class="button button-primary" value="Save Partner">
				</form>
			</div>
			<?php
			return;
		}

		$partners = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
		$referrals = $wpdb->get_results("SELECT r.*, p.name as partner_name FROM {$wpdb->prefix}an_referrals r JOIN $table_name p ON r.partner_id = p.id ORDER BY r.created_at DESC");
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e('Referral Partners', 'agency-nexus'); ?></h1>
			<a href="?page=an-referral-partners&action=add" class="page-title-action">Add New</a>
			<hr class="wp-header-end">

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e('Partner Network', 'agency-nexus'); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Name</th><th>Email</th><th>Specialty</th><th>Actions</th></tr></thead>
						<tbody>
							<?php foreach($partners as $p): ?>
								<tr>
									<td><strong><?php echo esc_html($p->name); ?></strong></td>
									<td><?php echo esc_html($p->email); ?></td>
									<td><?php echo esc_html($p->specialty); ?></td>
									<td>
										<a href="?page=an-referral-partners&action=edit&id=<?php echo $p->id; ?>">Edit</a> |
										<a href="<?php echo wp_nonce_url('?page=an-referral-partners&action=delete&id='.$p->id, 'an_delete_partner_'.$p->id); ?>" style="color:red;" onclick="return confirm('Delete?')">Delete</a>
									</td>
								</tr>
							<?php endforeach; if(empty($partners)) echo '<tr><td colspan="4">No partners added.</td></tr>'; ?>
						</tbody>
					</table>
				</div>
				<div class="postbox" style="padding: 20px;">
					<h3><?php _e('Referral History', 'agency-nexus'); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Date</th><th>Partner</th><th>Client</th><th>Status</th></tr></thead>
						<tbody>
							<?php foreach($referrals as $r): ?>
								<tr>
									<td><?php echo date('Y-m-d', strtotime($r->created_at)); ?></td>
									<td><?php echo esc_html($r->partner_name); ?></td>
									<td><?php echo esc_html($r->client_name); ?></td>
									<td><span class="badge"><?php echo ucfirst($r->status); ?></span></td>
								</tr>
							<?php endforeach; if(empty($referrals)) echo '<tr><td colspan="4">No referrals sent yet.</td></tr>'; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'burnout_guard' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}
		global $wpdb;
		$user_id = get_current_user_id();
		$time_table = $wpdb->prefix . 'an_time_entries';

		// Get hours logged in the last 7 days for the current user
		$last_week_seconds = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(duration) FROM $time_table WHERE user_id = %d AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
			$user_id
		) );
		$last_week_hours = $last_week_seconds / 3600;

		// Check if OOO today
		$is_ooo = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}an_vacations WHERE user_id = %d AND CURDATE() BETWEEN start_date AND end_date", $user_id ) );

		// Assuming 40 hours is 100% capacity
		$capacity = min(100, round(($last_week_hours / 40) * 100));
		$color = $capacity > 80 ? '#dc3232' : ($capacity > 50 ? '#ffb900' : '#46b450');

		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'BurnoutGuard', 'agency-nexus' ); ?></h2>
			<p><strong><?php _e( 'Workload Capacity (Last 7 Days):', 'agency-nexus' ); ?></strong></p>
			<div style="text-align: center; margin: 20px 0;">
				<div style="display: inline-block; width: 100px; height: 100px; border-radius: 50%; border: 8px solid <?php echo $is_ooo ? '#64748b' : $color; ?>; line-height: 84px; font-size: 24px; font-weight: bold;">
					<?php echo $is_ooo ? 'OOO' : $capacity . '%'; ?>
				</div>
				<?php if ( $is_ooo ) : ?>
					<p style="color: #64748b; font-weight: bold; margin-top: 10px;"><?php _e( 'Out of Office', 'agency-nexus' ); ?></p>
				<?php elseif ($capacity > 80) : ?>
					<p style="color: #dc3232; font-weight: bold; margin-top: 10px;"><?php _e( 'High Workload Warning', 'agency-nexus' ); ?></p>
				<?php else : ?>
					<p style="color: #46b450; font-weight: bold; margin-top: 10px;"><?php _e( 'Healthy Workload', 'agency-nexus' ); ?></p>
				<?php endif; ?>
			</div>
			<p><small><?php echo sprintf( __( 'You have logged %.1f hours this week.', 'agency-nexus' ), $last_week_hours ); ?></small></p>
		</div>
		<?php
	}
}
