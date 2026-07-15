<?php
/**
 * TimeBlock Pro Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Timeblockpro extends Agency_Nexus_Base_Module {

	protected $name = 'TimeBlockPro';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}

		if ( isset( $_GET['page'] ) && 'an-time-blocking' === $_GET['page'] ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_time_blocks';
			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
			$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

			if ( 'delete' === $action && $id ) {
				check_admin_referer( 'an_delete_block_' . $id );
				$wpdb->delete( $table_name, [ 'id' => $id ] );
				wp_redirect( admin_url( 'admin.php?page=an-time-blocking&msg=deleted' ) );
				exit;
			}

			if ( isset( $_POST['an_save_block'] ) && check_admin_referer( 'an_save_block_nonce' ) ) {
				$data = [
					'user_id'    => get_current_user_id(),
					'title'      => sanitize_text_field( $_POST['title'] ),
					'start_time' => ! empty( $_POST['start_time'] ) ? date( 'Y-m-d H:i:s', strtotime( $_POST['start_time'] ) ) : current_time( 'mysql' ),
					'end_time'   => ! empty( $_POST['end_time'] ) ? date( 'Y-m-d H:i:s', strtotime( $_POST['end_time'] ) ) : current_time( 'mysql' ),
					'type'       => sanitize_text_field( $_POST['type'] )
				];
				if ( $id ) {
					$wpdb->update( $table_name, $data, [ 'id' => $id ] );
					$msg = 'updated';
				} else {
					$wpdb->insert( $table_name, $data );
					$msg = 'added';
				}
				wp_redirect( admin_url( 'admin.php?page=an-time-blocking&msg=' . $msg ) );
				exit;
			}
		}
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'time_blocking' ) ) {
			return;
		}

		if ( Agency_Nexus_Permissions::is_team_member() ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Time Blocking', 'agency-nexus' ),
				__( 'Time Blocking', 'agency-nexus' ),
				'read',
				'an-time-blocking',
				[ $this, 'render_dashboard' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Productivity Reports', 'agency-nexus' ),
				__( 'Productivity Reports', 'agency-nexus' ),
				'read',
				'an-productivity-reports',
				[ $this, 'render_reports' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Focus Mode', 'agency-nexus' ),
				__( 'Focus Mode', 'agency-nexus' ),
				'read',
				'an-focus-mode',
				[ $this, 'render_focus_mode' ]
			);

			add_submenu_page(
				'agency-nexus',
				__( 'Smart Suggestions', 'agency-nexus' ),
				__( 'Smart Suggestions', 'agency-nexus' ),
				'read',
				'an-timeblock-suggestions',
				[ $this, 'render_suggestions' ]
			);
		}
	}

	public function render_dashboard() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_time_blocks';
		$user_id = get_current_user_id();
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'added':   $m = 'Block added!'; break;
				case 'updated': $m = 'Block updated!'; break;
				case 'deleted': $m = 'Block deleted!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'edit' || $action === 'add') {
			$block = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Time Block', 'agency-nexus') : __('Add New Time Block', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Schedule your focus periods to maximize productivity and avoid burnout.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field('an_save_block_nonce'); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Title', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="title" id="an_timeblock_title" value="<?php echo $block ? esc_attr($block->title) : ''; ?>" required class="regular-text">
								<a href="#" class="an-ai-improve-link" data-target="#an_timeblock_title" data-type="timeblock_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('What are you working on? e.g., Code Review, Client Call', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Start', 'agency-nexus'); ?></label></th>
							<td>
								<input type="datetime-local" name="start_time" value="<?php echo $block ? date('Y-m-d\TH:i', strtotime($block->start_time)) : ''; ?>" required>
								<p class="description"><?php _e('Beginning of the time block.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('End', 'agency-nexus'); ?></label></th>
							<td>
								<input type="datetime-local" name="end_time" value="<?php echo $block ? date('Y-m-d\TH:i', strtotime($block->end_time)) : ''; ?>" required>
								<p class="description"><?php _e('End of the time block.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Type', 'agency-nexus'); ?></label></th>
							<td>
								<select name="type">
									<option value="deep_work" <?php selected($block ? $block->type : '', 'deep_work'); ?>>Deep Work</option>
									<option value="shallow_work" <?php selected($block ? $block->type : '', 'shallow_work'); ?>>Shallow Work</option>
									<option value="meeting" <?php selected($block ? $block->type : '', 'meeting'); ?>>Meeting</option>
									<option value="break" <?php selected($block ? $block->type : '', 'break'); ?>>Break</option>
								</select>
								<p class="description"><?php _e('Deep Work is for intense concentration; Shallow Work is for administrative tasks.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<input type="submit" name="an_save_block" class="button button-primary" value="Save Block">
					<a href="?page=an-time-blocking" class="button">Cancel</a>
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

		$blocks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d ORDER BY start_time ASC", $user_id ) );
		$this->get_template( 'dashboard', [ 'blocks' => $blocks ] );
	}

	public function render_suggestions() {
		global $wpdb;
		if ( isset( $_POST['an_apply_suggestion'] ) && check_admin_referer( 'an_apply_suggestion_nonce' ) ) {
			$wpdb->insert( $wpdb->prefix . 'an_time_blocks', [
				'user_id'    => get_current_user_id(),
				'title'      => sanitize_text_field( $_POST['title'] ),
				'start_time' => sanitize_text_field( $_POST['start_time'] ),
				'end_time'   => sanitize_text_field( $_POST['end_time'] ),
				'type'       => sanitize_text_field( $_POST['type'] ),
				'created_at' => current_time( 'mysql' )
			] );
			echo '<div class="updated"><p>Smart suggestion applied to your schedule!</p></div>';
		}

		$suggestions_data = Agency_Nexus_AI_Copilot::generate( 'Generate 3 optimal time block suggestions for the current date', 'time_suggest' );
		$suggestions = json_decode( $suggestions_data, true );
		if ( ! is_array( $suggestions ) || empty( $suggestions ) ) {
			$suggestions = [
				[
					'title' => 'Morning Deep Work',
					'desc'  => 'Based on your circadian rhythm, your cognitive load capacity is highest now.',
					'type'  => 'deep_work',
					'start' => date('Y-m-d 09:00:00'),
					'end'   => date('Y-m-d 11:30:00'),
					'icon'  => '🧠'
				],
				[
					'title' => 'Post-Lunch Administrative Batch',
					'desc'  => 'Handle emails and shallow tasks during the afternoon energy dip.',
					'type'  => 'shallow_work',
					'start' => date('Y-m-d 14:00:00'),
					'end'   => date('Y-m-d 15:00:00'),
					'icon'  => '📥'
				],
				[
					'title' => 'Strategic Planning Break',
					'desc'  => 'Prevent burnout by scheduling a mandatory disconnect period.',
					'type'  => 'break',
					'start' => date('Y-m-d 11:30:00'),
					'end'   => date('Y-m-d 12:00:00'),
					'icon'  => '☕'
				]
			];
		}
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'AI-Powered Time Block Suggestions', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Our "Nexus AI" analyzes your productivity reports to suggest the ideal schedule for maximum output.', 'agency-nexus' ); ?></p>

			<div class="an-suggestions-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 30px;">
				<?php foreach ( $suggestions as $s ) : ?>
					<div class="postbox" style="padding: 20px; border-top: 4px solid var(--an-indigo-600);">
						<div style="font-size: 2rem; margin-bottom: 10px;"><?php echo $s['icon']; ?></div>
						<h3 style="margin:0;"><?php echo esc_html($s['title']); ?></h3>
						<p style="color:#666;"><?php echo esc_html($s['desc']); ?></p>
						<p><strong>Time:</strong> <?php echo date('H:i', strtotime($s['start'])); ?> - <?php echo date('H:i', strtotime($s['end'])); ?></p>
						<form method="post">
							<?php wp_nonce_field( 'an_apply_suggestion_nonce' ); ?>
							<input type="hidden" name="title" value="<?php echo esc_attr($s['title']); ?>">
							<input type="hidden" name="type" value="<?php echo esc_attr($s['type']); ?>">
							<input type="hidden" name="start_time" value="<?php echo $s['start']; ?>">
							<input type="hidden" name="end_time" value="<?php echo $s['end']; ?>">
							<input type="submit" name="an_apply_suggestion" class="button button-primary" value="Apply to Calendar">
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function render_focus_mode() {
		$pomodoro_time = get_option( 'an_pomodoro_interval', 25 );
		?>
		<div class="agency-nexus-wrap" style="height: 80vh; display: flex; flex-direction: column; justify-content: center; align-items: center; background: #0f172a; color: #fff; border-radius: 20px; margin-top: 20px;">
			<h1 style="color: #fff; font-size: 3rem; margin-bottom: 10px;">Focus Mode</h1>
			<p style="font-size: 1.2rem; color: #94a3b8; margin-bottom: 10px;">No distractions. Just you and the deep work.</p>

			<div style="margin-bottom: 30px;">
				<label>Interval (mins): </label>
				<input type="number" id="pomodoro-interval" value="<?php echo $pomodoro_time; ?>" style="width: 60px; background: transparent; color: #fff; border: 1px solid #444; text-align: center;">
				<button type="button" class="button" id="update-timer" style="background: #334155; color: #fff; border: none; margin-left: 10px;">Update</button>
			</div>

			<div id="focus-timer" style="font-size: 5rem; font-weight: bold; font-family: monospace; margin-bottom: 40px;"><?php echo $pomodoro_time; ?>:00</div>

			<div style="display: flex; gap: 20px;">
				<button type="button" class="button button-primary" id="start-focus" style="padding: 15px 40px; font-size: 1.1rem;"><?php _e( 'Start Work Block', 'agency-nexus' ); ?></button>
				<a href="?page=agency-nexus" class="button" style="padding: 15px 40px; font-size: 1.1rem; background: #334155; color: #fff; border: none;"><?php _e( 'Exit', 'agency-nexus' ); ?></a>
			</div>

			<div style="margin-top: 50px; text-align: center; max-width: 500px;">
				<p style="color: #64748b; italic">"Deep work is the ability to focus without distraction on a cognitively demanding task." - Cal Newport</p>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			var timer;
			var timeLeft = <?php echo $pomodoro_time * 60; ?>;

			$('#update-timer').click(function(){
				var mins = $('#pomodoro-interval').val();
				timeLeft = mins * 60;
				$('#focus-timer').text((mins < 10 ? '0' : '') + mins + ':00');
			});

			$('#start-focus').click(function() {
				if (timer) {
					clearInterval(timer);
					timer = null;
					$(this).text('Start Work Block');
				} else {
					$(this).text('Pause');
					timer = setInterval(function() {
						timeLeft--;
						var mins = Math.floor(timeLeft / 60);
						var secs = timeLeft % 60;
						$('#focus-timer').text((mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs);
						if (timeLeft <= 0) {
							clearInterval(timer);
							alert('Focus block complete! Take a break.');
						}
					}, 1000);
				}
			});
		});
		</script>
		<?php
	}

	public function render_reports() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_time_blocks';
		$user_id = get_current_user_id();

		// Stats for last 30 days
		$stats = $wpdb->get_results( $wpdb->prepare( "
			SELECT type, SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as duration
			FROM $table_name
			WHERE user_id = %d AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY type
		", $user_id ) );

		$total_seconds = 0;
		$type_stats = [ 'deep_work' => 0, 'shallow_work' => 0, 'meeting' => 0, 'break' => 0 ];
		foreach ( $stats as $s ) {
			$type_stats[$s->type] = (float)$s->duration;
			$total_seconds += (float)$s->duration;
		}

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Productivity Analytics', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'A breakdown of how you spent your scheduled time over the last 30 days.', 'agency-nexus' ); ?></p>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px;">
				<div class="postbox" style="padding: 20px; text-align: center;">
					<h3><?php _e( 'Deep Work', 'agency-nexus' ); ?></h3>
					<div style="font-size: 2rem; font-weight: bold; color: var(--an-indigo-600);"><?php echo round($type_stats['deep_work'] / 3600, 1); ?> hrs</div>
				</div>
				<div class="postbox" style="padding: 20px; text-align: center;">
					<h3><?php _e( 'Shallow Work', 'agency-nexus' ); ?></h3>
					<div style="font-size: 2rem; font-weight: bold; color: var(--an-slate-500);"><?php echo round($type_stats['shallow_work'] / 3600, 1); ?> hrs</div>
				</div>
				<div class="postbox" style="padding: 20px; text-align: center;">
					<h3><?php _e( 'Meetings', 'agency-nexus' ); ?></h3>
					<div style="font-size: 2rem; font-weight: bold; color: var(--an-amber-500);"><?php echo round($type_stats['meeting'] / 3600, 1); ?> hrs</div>
				</div>
				<div class="postbox" style="padding: 20px; text-align: center;">
					<h3><?php _e( 'Breaks', 'agency-nexus' ); ?></h3>
					<div style="font-size: 2rem; font-weight: bold; color: #46b450;"><?php echo round($type_stats['break'] / 3600, 1); ?> hrs</div>
				</div>
			</div>

			<div class="postbox" style="padding: 20px; margin-top: 30px;">
				<h3><?php _e( 'Focus Intensity', 'agency-nexus' ); ?></h3>
				<?php
				$ratio = $type_stats['deep_work'] > 0 ? ( $type_stats['deep_work'] / ($type_stats['deep_work'] + $type_stats['shallow_work']) ) * 100 : 0;
				?>
				<div style="height: 30px; background: #eee; border-radius: 15px; overflow: hidden; display: flex;">
					<div style="width: <?php echo $ratio; ?>%; background: var(--an-indigo-600); line-height: 30px; color: #fff; padding-left: 15px;">Deep Work (<?php echo round($ratio); ?>%)</div>
					<div style="flex: 1; background: var(--an-slate-300); line-height: 30px; color: #333; text-align: right; padding-right: 15px;">Shallow Work</div>
				</div>
				<p><small><?php _e( 'High-performing agency owners aim for at least 40% Deep Work to ensure strategic growth.', 'agency-nexus' ); ?></small></p>
			</div>
		</div>
		<?php
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'time_blocking' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}
		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'TimeBlock Pro', 'agency-nexus' ); ?></h2>
			<p><?php _e( 'Focus on what matters. Your next deep work block starts in 15 minutes.', 'agency-nexus' ); ?></p>
			<a href="<?php echo admin_url( 'admin.php?page=an-time-blocking' ); ?>" class="button"><?php _e( 'Manage Schedule', 'agency-nexus' ); ?></a>
		</div>
		<?php
	}
}
