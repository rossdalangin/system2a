<?php
/**
 * ContentMatrix Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Contentmatrix extends Agency_Nexus_Base_Module {

	protected $name = 'ContentMatrix';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
		add_action( 'wp_ajax_an_update_content_date', [ $this, 'handle_update_content_date' ] );
		add_action( 'wp_ajax_an_create_content', [ $this, 'handle_create_content' ] );
		add_action( 'wp_ajax_an_delete_content', [ $this, 'handle_delete_content' ] );
		add_action( 'wp_ajax_an_ai_generate_titles', [ $this, 'handle_ai_generate_titles' ] );
		add_action( 'wp_ajax_an_ai_generate_gap_draft', [ $this, 'handle_ai_generate_gap_draft' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::can_access_nexus() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( 'an-content-list' === $page ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_content';
			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
			$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

			if ( 'delete' === $action && $id ) {
				check_admin_referer( 'an_delete_content_' . $id );
				$wpdb->delete( $table_name, [ 'id' => $id ] );
				wp_redirect( admin_url( 'admin.php?page=an-content-list&msg=deleted' ) );
				exit;
			}

			if ( isset( $_POST['an_save_content'] ) && check_admin_referer( 'an_save_content_nonce' ) ) {
				$project_id = intval( $_POST['project_id'] );
				if ( ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
					wp_die( 'Unauthorized' );
				}
				$platform = sanitize_text_field( $_POST['platform'] );
				$is_pillar = isset( $_POST['is_pillar'] ) ? 1 : 0;

				// Forecasting Algorithm
				$predicted = 100; // Base
				if ( $is_pillar ) $predicted *= 2.5;
				if ( in_array(strtolower($platform), ['instagram', 'linkedin']) ) $predicted *= 1.8;

				$data = [
					'project_id'     => $project_id,
					'title'          => sanitize_text_field( $_POST['title'] ),
					'content'        => wp_kses_post( $_POST['content'] ),
					'media_url'      => esc_url_raw( $_POST['media_url'] ),
					'status'         => sanitize_text_field( $_POST['status'] ),
					'scheduled_date' => ! empty( $_POST['scheduled_date'] ) ? date( 'Y-m-d H:i:s', strtotime( $_POST['scheduled_date'] ) ) : null,
					'platform'       => $platform,
					'is_pillar'      => $is_pillar,
					'pillar_id'      => intval( $_POST['pillar_id'] ),
					'predicted_engagement' => intval($predicted)
				];
				if ( $id ) {
					// Save version before update
					$old_content = $wpdb->get_var( $wpdb->prepare( "SELECT content FROM $table_name WHERE id = %d", $id ) );
					if ( $old_content !== $data['content'] ) {
						$version = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}an_content_versions WHERE content_id = %d", $id ) ) + 1;
						$wpdb->insert( $wpdb->prefix . 'an_content_versions', [
							'content_id' => $id,
							'version_number' => $version,
							'content' => $old_content,
							'created_by' => get_current_user_id(),
							'created_at' => current_time( 'mysql' )
						] );
					}

					$wpdb->update( $table_name, $data, [ 'id' => $id ] );
					$msg = 'updated';
					do_action( 'agency_nexus_content_status_updated', $id, $data['status'] );
				} else {
					$wpdb->insert( $table_name, $data );
					$msg = 'added';
					do_action( 'agency_nexus_content_status_updated', $wpdb->insert_id, $data['status'] );
				}
				wp_redirect( admin_url( 'admin.php?page=an-content-list&msg=' . $msg ) );
				exit;
			}
		}

		if ( 'an-batch-automation' === $page ) {
			if ( isset( $_POST['an_run_batch'] ) && check_admin_referer( 'an_batch_nonce' ) ) {
				global $wpdb;
				$project_id = intval( $_POST['project_id'] );
				$count = isset( $_POST['batch_count'] ) ? intval( $_POST['batch_count'] ) : 0;
				$titles = isset( $_POST['batch_titles'] ) ? explode( "\n", $_POST['batch_titles'] ) : [];

				foreach ( $titles as $t ) {
					$t = trim( $t );
					if ( empty( $t ) ) continue;
					$wpdb->insert( $wpdb->prefix . 'an_content', [
						'project_id' => $project_id,
						'title'      => $t,
						'content'    => 'Batch generated draft content.',
						'status'     => 'draft',
						'platform'   => 'wordpress',
						'created_at' => current_time( 'mysql' )
					] );
				}
				wp_redirect( admin_url( 'admin.php?page=an-content-list&msg=added' ) );
				exit;
			}
		}
	}

	public function enqueue_scripts( $hook ) {
		$pages = [
			'agency-nexus_page_an-content-calendar',
			'agency-nexus_page_an-content-list',
			'agency-nexus_page_an-batch-automation',
			'agency-nexus_page_an-keyword-gap'
		];

		if ( ! in_array( $hook, $pages ) ) {
			return;
		}
		wp_enqueue_media();
		wp_localize_script( 'jquery', 'an_contentmatrix', [
			'security' => wp_create_nonce( 'an_calendar_nonce' )
		] );
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'content_calendar' ) ) {
			return;
		}

		add_submenu_page(
			'agency-nexus',
			__( 'Content Calendar', 'agency-nexus' ),
			__( 'Content Calendar', 'agency-nexus' ),
			'read',
			'an-content-calendar',
			[ $this, 'render_calendar' ]
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Content List', 'agency-nexus' ),
			__( 'Content List', 'agency-nexus' ),
			'read',
			'an-content-list',
			[ $this, 'render_content_list' ]
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Pillar Architect', 'agency-nexus' ),
			__( 'Pillar Architect', 'agency-nexus' ),
			'read',
			'an-pillar-architect',
			[ $this, 'render_pillar_architect' ]
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Batch Automation', 'agency-nexus' ),
			__( 'Batch Automation', 'agency-nexus' ),
			'read',
			'an-batch-automation',
			[ $this, 'render_batch_automation' ]
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Keyword Gap', 'agency-nexus' ),
			__( 'Keyword Gap', 'agency-nexus' ),
			'read',
			'an-keyword-gap',
			[ $this, 'render_keyword_gap' ]
		);
	}

	public function render_keyword_gap() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_keyword_gap';
		$projects_table = $wpdb->prefix . 'an_projects';

		if ( isset( $_POST['an_save_keyword'] ) && check_admin_referer( 'an_save_keyword_nonce' ) ) {
			$wpdb->insert( $table_name, [
				'project_id'      => intval( $_POST['project_id'] ),
				'keyword'         => sanitize_text_field( $_POST['keyword'] ),
				'competitor_rank' => intval( $_POST['competitor_rank'] ),
				'our_rank'        => intval( $_POST['our_rank'] ),
				'search_volume'   => intval( $_POST['search_volume'] ),
				'difficulty'      => intval( $_POST['difficulty'] ),
				'status'          => sanitize_text_field( $_POST['status'] ),
				'created_at'      => current_time( 'mysql' )
			] );
			echo '<div class="updated"><p>Keyword added to gap analysis.</p></div>';
		}

		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		$query = "SELECT k.*, p.title as project_title FROM $table_name k JOIN $projects_table p ON k.project_id = p.id";
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$query .= " WHERE 1=0";
			} else {
				$query .= " WHERE k.project_id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
			}
		}
		$gaps = $wpdb->get_results( $query );
		$projects = $wpdb->get_results( "SELECT id, title FROM $projects_table" );

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Keyword Gap & Competitor Analysis', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Identify opportunities where your competitors are outranking you, or find "low-hanging fruit" keywords to target in your content clusters.', 'agency-nexus' ); ?></p>

			<div class="an-flex-row" style="display: flex; gap: 20px; margin-top: 30px;">
				<div class="postbox" style="flex: 1; padding: 20px;">
					<h3><?php _e( 'Add Keyword for Analysis', 'agency-nexus' ); ?></h3>
					<form method="post">
						<?php wp_nonce_field( 'an_save_keyword_nonce' ); ?>
						<table class="form-table">
							<tr>
								<td>
									<select name="project_id" style="width:100%;" required>
										<option value=""><?php _e( '-- Select Project --', 'agency-nexus' ); ?></option>
										<?php foreach ( $projects as $p ) : ?>
											<option value="<?php echo $p->id; ?>"><?php echo esc_html( $p->title ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td><input type="text" name="keyword" placeholder="Keyword" style="width:100%;" required></td>
							</tr>
							<tr>
								<td>
									<div style="display:flex; gap:10px;">
										<input type="number" name="competitor_rank" placeholder="Competitor Rank" style="flex:1;">
										<input type="number" name="our_rank" placeholder="Our Rank" style="flex:1;">
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<div style="display:flex; gap:10px;">
										<input type="number" name="search_volume" placeholder="Search Volume" style="flex:1;">
										<input type="number" name="difficulty" placeholder="Difficulty (0-100)" style="flex:1;">
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<select name="status" style="width:100%;">
										<option value="gap">Gap (They rank, we don't)</option>
										<option value="opportunity">Opportunity (Low competition)</option>
										<option value="weak">Weak (We rank lower)</option>
									</select>
								</td>
							</tr>
						</table>
						<p class="submit">
							<input type="submit" name="an_save_keyword" class="button button-primary" value="Add to Analysis">
						</p>
					</form>
				</div>

				<div class="postbox" style="flex: 2; padding: 20px;">
					<h3><?php _e( 'Analysis Dashboard', 'agency-nexus' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Keyword</th>
								<th>Project</th>
								<th>Gap Status</th>
								<th>Search Volume</th>
								<th>Difficulty</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $gaps as $gap ) :
								$color = 'gray';
								if ( $gap->status === 'gap' ) $color = '#e53e3e';
								if ( $gap->status === 'opportunity' ) $color = '#38a169';
								if ( $gap->status === 'weak' ) $color = '#d69e2e';
							?>
								<tr>
									<td><strong><?php echo esc_html( $gap->keyword ); ?></strong></td>
									<td><?php echo esc_html( $gap->project_title ); ?></td>
									<td><span style="color:<?php echo $color; ?>; font-weight:bold;"><?php echo ucfirst($gap->status); ?></span></td>
									<td><?php echo number_format($gap->search_volume); ?></td>
									<td>
										<div style="background:#eee; height:8px; border-radius:4px; width:60px;">
											<div style="background:var(--an-indigo-600); height:8px; border-radius:4px; width:<?php echo $gap->difficulty; ?>%;"></div>
										</div>
									</td>
									<td>
										<button class="button button-small an-ai-gap-btn" data-keyword="<?php echo esc_attr($gap->keyword); ?>" data-project-id="<?php echo $gap->project_id; ?>"><?php _e( 'Generate Draft', 'agency-nexus' ); ?></button>
									</td>
								</tr>
							<?php endforeach; if(empty($gaps)) echo '<tr><td colspan="6">No keywords analyzed yet.</td></tr>'; ?>
						</tbody>
					</table>
				</div>
			</div>

			<script>
			jQuery(document).ready(function($) {
				$('.an-ai-gap-btn').on('click', function(e) {
					e.preventDefault();
					var $btn = $(this);
					var keyword = $btn.data('keyword');
					var projectId = $btn.data('project-id');

					$btn.prop('disabled', true).text('Generating...');

					$.post(ajaxurl, {
						action: 'an_ai_generate_gap_draft',
						project_id: projectId,
						keyword: keyword,
						security: an_contentmatrix.security
					}, function(response) {
						if (response.success) {
							alert(response.data.msg);
							$btn.text('Draft Generated!').css('background', '#46b450').css('color', '#fff');
						} else {
							alert('Draft generation failed. Ensure your AI Copilot is active.');
							$btn.prop('disabled', false).text('Generate Draft');
						}
					});
				});
			});
			</script>

			<div class="postbox" style="margin-top: 20px; padding: 20px;">
				<h3><?php _e( 'Competitor Content Tracker', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Monitor top performing content from competitors and plan counter-strategies.', 'agency-nexus' ); ?></p>
				<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
					<div style="border:1px solid #ddd; padding:15px; border-radius:8px;">
						<h4 style="margin-top:0;">Competitor A</h4>
						<p><a href="#">How to build a SaaS in 2024</a></p>
						<p><small>Strategy: Create a "Deep Dive" version with better case studies.</small></p>
						<span class="badge" style="background:#fed7d7; color:#822727;">High Competition</span>
					</div>
					<div style="border:1px solid #ddd; padding:15px; border-radius:8px;">
						<h4 style="margin-top:0;">Competitor B</h4>
						<p><a href="#">Social Media Marketing for Law Firms</a></p>
						<p><small>Strategy: Niche down to "Personal Injury Law" specifically.</small></p>
						<span class="badge" style="background:#c6f6d5; color:#22543d;">Opportunity</span>
					</div>
					<div style="border:1px solid #ddd; padding:15px; border-radius:8px;">
						<h4 style="margin-top:0;">Competitor C</h4>
						<p><a href="#">WP Plugin Development Tutorial</a></p>
						<p><small>Strategy: Record a video version to complement our text guide.</small></p>
						<span class="badge" style="background:#feebc8; color:#744210;">Counter-Attack</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_batch_automation() {
		global $wpdb;
		$projects = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}an_projects" );

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Batch Content Automation', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Create multiple content drafts at once to save time on high-volume projects.', 'agency-nexus' ); ?></p>

			<div class="postbox" style="padding: 20px; max-width: 600px; margin-top: 30px;">
				<form method="post">
					<?php wp_nonce_field( 'an_batch_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e( 'Project', 'agency-nexus' ); ?></label></th>
							<td>
								<select name="project_id" required>
									<?php foreach ( $projects as $p ) : ?>
										<option value="<?php echo $p->id; ?>"><?php echo esc_html($p->title); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'Titles (one per line)', 'agency-nexus' ); ?></label></th>
							<td>
								<textarea name="batch_titles" id="batch_titles" rows="10" class="regular-text" required placeholder="Post Title 1&#10;Post Title 2"></textarea>
									<br><button type="button" class="button" id="an_ai_generate_titles" style="margin-top: 10px;"><?php _e( '🪄 Generate with AI Copilot', 'agency-nexus' ); ?></button>
									<span id="an_ai_loading" style="display:none; margin-left:10px; color:#666; font-style:italic;"><?php _e( 'Generating...', 'agency-nexus' ); ?></span>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="an_run_batch" class="button button-primary" value="Generate Batch Drafts">
					</p>
				</form>
			</div>
			<script>
			jQuery(document).ready(function($) {
				$('#an_ai_generate_titles').on('click', function(e) {
					e.preventDefault();
					var projectId = $('select[name="project_id"]').val();
					if (!projectId) {
						alert('Please select a project first.');
						return;
					}
					$('#an_ai_generate_titles').prop('disabled', true);
					$('#an_ai_loading').show();
					$.post(ajaxurl, {
						action: 'an_ai_generate_titles',
						project_id: projectId,
						security: an_contentmatrix.security
					}, function(response) {
						$('#an_ai_generate_titles').prop('disabled', false);
						$('#an_ai_loading').hide();
						if (response.success && response.data.titles) {
							$('#batch_titles').val(response.data.titles);
						} else {
							alert('AI generation failed or not fully configured. Falling back to local ideas.');
						}
					});
				});
			});
			</script>
		</div>
		<?php
	}

	public function render_pillar_architect() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_content';
		$pillars = $wpdb->get_results( "SELECT * FROM $table_name WHERE is_pillar = 1" );
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Pillar Content Architect', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Visualize and manage your topic clusters. Pillar content acts as the foundation for your SEO strategy, with related cluster posts linking back to it.', 'agency-nexus' ); ?></p>

			<div class="an-pillar-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; margin-top: 30px;">
				<?php foreach ( $pillars as $pillar ) :
					$clusters = $wpdb->get_results( $wpdb->prepare( "SELECT title FROM $table_name WHERE pillar_id = %d", $pillar->id ) );
				?>
					<div class="postbox" style="padding: 20px; border-top: 4px solid var(--an-indigo-600);">
						<h3 style="margin-top: 0;"><?php echo esc_html( $pillar->title ); ?> <span class="badge" style="background: var(--an-indigo-600); color: #fff;">Pillar</span></h3>
						<p><small><?php echo count($clusters); ?> cluster items</small></p>
						<hr>
						<ul style="list-style: circle; margin-left: 20px;">
							<?php foreach ( $clusters as $c ) : ?>
								<li><?php echo esc_html( $c->title ); ?></li>
							<?php endforeach; if(empty($clusters)) echo '<li><em>No cluster content linked.</em></li>'; ?>
						</ul>
						<div style="margin-top: 20px;">
							<a href="?page=an-content-list&action=edit&id=<?php echo $pillar->id; ?>" class="button button-small">Edit Pillar</a>
						</div>
					</div>
				<?php endforeach; if(empty($pillars)) echo '<p>No pillar content defined. Mark a post as "Pillar Content" in the Content List to start.</p>'; ?>
			</div>
		</div>
		<?php
	}

	public function render_calendar() {
		global $wpdb;
		$content_query = "SELECT c.* FROM {$wpdb->prefix}an_content c JOIN {$wpdb->prefix}an_projects p ON c.project_id = p.id";
		$projects_query = "SELECT id, title FROM {$wpdb->prefix}an_projects";

		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$content_query .= " WHERE 1=0";
				$projects_query .= " WHERE 1=0";
			} else {
				$in_clause = "(" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
				$content_query .= " WHERE c.project_id IN $in_clause";
				$projects_query .= " WHERE id IN $in_clause";
			}
		}

		$content_items = $wpdb->get_results( $content_query );
		$projects      = $wpdb->get_results( $projects_query );
		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Content Calendar', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'A visual overview of your cross-platform content strategy. Drag unscheduled items onto the calendar to set a publication date, or move existing items to reschedule.', 'agency-nexus' ); ?></p>
		</div>
		<?php
		$this->get_template( 'calendar', [ 'content_items' => $content_items, 'projects' => $projects ] );
	}

	public function render_content_list() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_content';
		$projects_table = $wpdb->prefix . 'an_projects';
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $action === 'versions' && $id ) {
			$versions = $wpdb->get_results( $wpdb->prepare( "SELECT v.*, u.display_name FROM {$wpdb->prefix}an_content_versions v JOIN {$wpdb->users} u ON v.created_by = u.ID WHERE v.content_id = %d ORDER BY v.version_number DESC", $id ) );
			$content_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM $table_name WHERE id = %d", $id ) );
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo sprintf( __( 'Version History: %s', 'agency-nexus' ), esc_html( $content_title ) ); ?></h1>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr><th>Version</th><th>Author</th><th>Date</th><th>Preview</th></tr></thead>
					<tbody>
						<?php foreach ( $versions as $v ) : ?>
							<tr>
								<td>#<?php echo $v->version_number; ?></td>
								<td><?php echo esc_html( $v->display_name ); ?></td>
								<td><?php echo $v->created_at; ?></td>
								<td><button type="button" class="button button-small" onclick="alert('<?php echo esc_js($v->content); ?>')">View Content</button></td>
							</tr>
						<?php endforeach; if(empty($versions)) echo '<tr><td colspan="4">No previous versions found.</td></tr>'; ?>
					</tbody>
				</table>
				<p><a href="?page=an-content-list" class="button">Back to List</a></p>
			</div>
			<?php
			return;
		}

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'added': $m = 'Content added!'; break;
				case 'updated': $m = 'Content updated!'; break;
				case 'deleted': $m = 'Content item deleted!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'edit' || $action === 'add') {
			$content = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			$projects = $wpdb->get_results("SELECT id, title FROM $projects_table");
			$pillars = $wpdb->get_results("SELECT id, title FROM $table_name WHERE is_pillar = 1");
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Content', 'agency-nexus') : __('Add New Content', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Create a content piece for a specific project. This can be a blog post, social media update, or newsletter.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field('an_save_content_nonce'); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Project', 'agency-nexus'); ?></label></th>
							<td>
								<select name="project_id" required>
									<?php foreach ($projects as $p) : ?>
										<option value="<?php echo $p->id; ?>" <?php selected($content ? $content->project_id : 0, $p->id); ?>><?php echo esc_html($p->title); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Which project does this content belong to?', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Title', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="title" id="an_content_title" value="<?php echo $content ? esc_attr($content->title) : ''; ?>" required class="regular-text">
								<a href="#" class="an-ai-improve-link" data-target="#an_content_title" data-type="title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Internal name or headline for the content.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Body Content', 'agency-nexus'); ?></label></th>
							<td>
								<textarea name="content" id="an_content_body" class="regular-text" rows="10"><?php echo $content ? esc_textarea($content->content) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#an_content_body" data-type="content" style="text-decoration: none;">✨ <?php _e('AI Improve Content', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('The actual text or copy for the post.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Media URL', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="media_url" id="media_url" value="<?php echo $content ? esc_attr($content->media_url) : ''; ?>" class="regular-text">
								<button type="button" id="upload_media_btn" class="button">Upload/Select Media</button>
								<p class="description"><?php _e('URL of an image or video asset from the Media Library.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Platform', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="platform" value="<?php echo $content ? esc_attr($content->platform) : 'wordpress'; ?>" class="regular-text">
								<p class="description"><?php _e('Where will this be published? e.g., WordPress, Instagram, LinkedIn', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Status', 'agency-nexus'); ?></label></th>
							<td>
								<select name="status">
									<option value="draft" <?php selected($content ? $content->status : '', 'draft'); ?>>Draft</option>
									<option value="pending_approval" <?php selected($content ? $content->status : '', 'pending_approval'); ?>>Pending Approval</option>
									<option value="approved" <?php selected($content ? $content->status : '', 'approved'); ?>>Approved</option>
									<option value="published" <?php selected($content ? $content->status : '', 'published'); ?>>Published</option>
								</select>
								<p class="description"><?php _e('Clients can only approve items set to "Pending Approval".', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Pillar Content?', 'agency-nexus'); ?></label></th>
							<td>
								<input type="checkbox" name="is_pillar" value="1" <?php checked($content ? $content->is_pillar : 0, 1); ?>>
								<p class="description"><?php _e('Mark this as a high-level "Pillar" piece that other content will link to.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Parent Pillar', 'agency-nexus'); ?></label></th>
							<td>
								<select name="pillar_id">
									<option value="0"><?php _e('None', 'agency-nexus'); ?></option>
									<?php foreach ($pillars as $p) : ?>
										<?php if ($id && $p->id == $id) continue; ?>
										<option value="<?php echo $p->id; ?>" <?php selected($content ? $content->pillar_id : 0, $p->id); ?>><?php echo esc_html($p->title); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('If this is cluster content, link it to a parent pillar piece.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Scheduled Date', 'agency-nexus'); ?></label></th>
							<td>
								<input type="datetime-local" name="scheduled_date" value="<?php echo ($content && $content->scheduled_date) ? date('Y-m-d\TH:i', strtotime($content->scheduled_date)) : ''; ?>">
								<p class="description"><?php _e('When should this content go live?', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<input type="submit" name="an_save_content" class="button button-primary" value="Save Content">
					<a href="?page=an-content-list" class="button">Cancel</a>
				</form>
			</div>
			<script>
			jQuery(document).ready(function($){
				$('#upload_media_btn').click(function(e) {
					e.preventDefault();
					var frame = wp.media({ title: 'Select Media', multiple: false }).open().on('select', function(e){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#media_url').val(attachment.url);
					});
				});

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

		$query = "SELECT c.*, p.title as project_title FROM $table_name c JOIN $projects_table p ON c.project_id = p.id";
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$query .= " WHERE 1=0";
			} else {
				$query .= " WHERE c.project_id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
			}
		}
		$query .= " ORDER BY c.created_at DESC";
		$items = $wpdb->get_results($query);
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e('Content Management', 'agency-nexus'); ?></h1>
			<p><?php _e( 'Guidance: Manage all your content assets here. Use the "Content Calendar" for a visual overview of your publishing schedule.', 'agency-nexus' ); ?></p>
			<?php if ( Agency_Nexus_Permissions::is_team_member() ) : ?>
			<a href="?page=an-content-list&action=add" class="page-title-action">Add New</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Title</th><th>Project</th><th>Platform</th><th>Status</th><th>Date</th><th>Forecast</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach ($items as $item) : ?>
						<tr>
							<td><strong><?php echo esc_html($item->title); ?></strong></td>
							<td><?php echo esc_html($item->project_title); ?></td>
							<td><?php echo esc_html(ucfirst($item->platform)); ?></td>
							<td><span class="badge status-<?php echo $item->status; ?>"><?php echo ucfirst(str_replace('_', ' ', $item->status)); ?></span></td>
							<td><?php echo $item->scheduled_date; ?></td>
							<td>
								<span title="<?php _e('Predicted Interactions', 'agency-nexus'); ?>">📈 <?php echo number_format($item->predicted_engagement); ?></span>
							</td>
							<td>
								<a href="?page=an-content-list&action=edit&id=<?php echo $item->id; ?>">Edit</a> |
								<a href="?page=an-content-list&action=versions&id=<?php echo $item->id; ?>">Versions</a>
								<?php if ( Agency_Nexus_Permissions::is_team_member() ) : ?>
								| <a href="<?php echo wp_nonce_url('?page=an-content-list&action=delete&id=' . $item->id, 'an_delete_content_' . $item->id); ?>" style="color:red;" onclick="return confirm('Delete item?')">Delete</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX handler for creating content.
	 */
	public function handle_create_content() {
		check_ajax_referer( 'an_calendar_nonce', 'security' );

		$project_id = intval( $_POST['project_id'] );
		if ( ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;
		$title      = sanitize_text_field( $_POST['title'] );
		$content    = isset($_POST['content']) ? sanitize_textarea_field( $_POST['content'] ) : '';
		$media_url  = esc_url_raw( $_POST['media_url'] );

		$wpdb->insert(
			$wpdb->prefix . 'an_content',
			[
				'project_id' => $project_id,
				'title'      => $title,
				'content'    => $content,
				'media_url'  => $media_url,
				'status'     => 'pending_approval'
			]
		);

		wp_send_json_success( [ 'id' => $wpdb->insert_id, 'title' => $title ] );
	}

	/**
	 * AJAX handler for deleting content.
	 */
	public function handle_delete_content() {
		check_ajax_referer( 'an_calendar_nonce', 'security' );

		global $wpdb;
		$item_id = intval( $_POST['item_id'] );
		$project_id = $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM {$wpdb->prefix}an_content WHERE id = %d", $item_id ) );

		if ( ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$wpdb->delete( $wpdb->prefix . 'an_content', [ 'id' => $item_id ] );
		wp_send_json_success();
	}

	/**
	 * AJAX handler for drag and drop scheduling.
	 */
	public function handle_update_content_date() {
		check_ajax_referer( 'an_calendar_nonce', 'security' );

		global $wpdb;
		$item_id = intval( $_POST['item_id'] );
		$project_id = $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM {$wpdb->prefix}an_content WHERE id = %d", $item_id ) );

		if ( ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$new_date = sanitize_text_field( $_POST['new_date'] );

		$wpdb->update(
			$wpdb->prefix . 'an_content',
			[ 'scheduled_date' => $new_date ],
			[ 'id' => $item_id ]
		);

		wp_send_json_success();
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'content_calendar' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			return;
		}
		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'ContentMatrix', 'agency-nexus' ); ?></h2>
			<p><?php _e( 'Plan and schedule your content across platforms.', 'agency-nexus' ); ?></p>
			<a href="<?php echo admin_url( 'admin.php?page=an-content-calendar' ); ?>" class="button"><?php _e( 'Open Calendar', 'agency-nexus' ); ?></a>
		</div>
		<?php
	}

	/**
	 * AJAX handler for generating titles using AI.
	 */
	public function handle_ai_generate_titles() {
		check_ajax_referer( 'an_calendar_nonce', 'security' );
		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
		global $wpdb;
		$project_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$wpdb->prefix}an_projects WHERE id = %d", $project_id ) );

		$prompt = "Generate 5 high-converting, viral blog post or social media content titles for an agency project named: " . $project_title;
		$titles = Agency_Nexus_AI_Copilot::generate( $prompt, 'content_batch' );
		wp_send_json_success( [ 'titles' => $titles ] );
	}

	/**
	 * AJAX handler for generating Keyword Gap draft using AI.
	 */
	public function handle_ai_generate_gap_draft() {
		check_ajax_referer( 'an_calendar_nonce', 'security' );
		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
		$keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

		if ( empty($keyword) || ! $project_id ) {
			wp_send_json_error( 'Missing parameters.' );
		}

		global $wpdb;

		$prompt = "Write a high-quality, comprehensive blog post content draft targeting the SEO keyword: \"" . $keyword . "\". Include an attention-grabbing headline, subheadings, and a strong call-to-action for an agency project.";
		$draft_content = Agency_Nexus_AI_Copilot::generate( $prompt, 'keyword_gap_draft' );

		$wpdb->insert( $wpdb->prefix . 'an_content', [
			'project_id' => $project_id,
			'title'      => 'AI Draft: ' . ucfirst($keyword),
			'content'    => $draft_content,
			'status'     => 'draft',
			'platform'   => 'wordpress',
			'created_at' => current_time('mysql')
		] );

		wp_send_json_success( [ 'msg' => 'Draft successfully generated and saved to Content Management!' ] );
	}
}
