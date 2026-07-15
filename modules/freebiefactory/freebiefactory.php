<?php
/**
 * FreebieFactory Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Freebiefactory extends Agency_Nexus_Base_Module {

	protected $name = 'FreebieFactory';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}

		$page = isset($_GET['page']) ? $_GET['page'] : '';

		if ( 'an-manage-marketplace' === $page && current_user_can('manage_options') ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_resources';
			$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

			if ( isset($_POST['an_save_marketplace_item']) && check_admin_referer('an_save_marketplace_nonce') ) {
				$data = [
					'title'           => sanitize_text_field($_POST['title']),
					'type'            => sanitize_text_field($_POST['type']),
					'category'        => sanitize_text_field($_POST['category']),
					'price'           => floatval($_POST['price']),
					'version'         => sanitize_text_field($_POST['version']),
					'compatible_with' => sanitize_text_field($_POST['compatible_with']),
					'content'         => wp_kses_post($_POST['content']),
					'image_url'       => esc_url_raw($_POST['image_url']),
					'file_url'        => esc_url_raw($_POST['file_url']),
					'is_marketplace'  => 1,
					'last_updated_at' => current_time('mysql')
				];
				if ($id) {
					$wpdb->update($table_name, $data, ['id' => $id]);
				} else {
					$data['created_at'] = current_time('mysql');
					$wpdb->insert($table_name, $data);
				}
				wp_redirect(admin_url('admin.php?page=an-manage-marketplace&msg=saved'));
				exit;
			}
		}

		if ( 'an-questionnaires' === $page ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_resources';
			$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

			if ( isset( $_POST['an_save_questionnaire'] ) && check_admin_referer( 'an_save_questionnaire_nonce' ) ) {
				$questions = isset($_POST['questions']) ? array_map('sanitize_text_field', $_POST['questions']) : [];
				$data = [
					'title'      => sanitize_text_field( $_POST['title'] ),
					'type'       => 'questionnaire',
					'content'    => json_encode($questions),
				];
				if ( $id ) {
					$wpdb->update( $table_name, $data, [ 'id' => $id ] );
				} else {
					$data['created_at'] = current_time( 'mysql' );
					$wpdb->insert( $table_name, $data );
				}
				wp_redirect( admin_url( 'admin.php?page=an-questionnaires' ) );
				exit;
			}
		}

		if ( 'an-resources' === $page || 'an-manage-marketplace' === $page || 'an-questionnaires' === $page ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'an_resources';
			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
			$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

			if ( 'delete' === $action && $id ) {
				if ( ! Agency_Nexus_Permissions::is_admin() ) {
					wp_die( 'Unauthorized' );
				}
				check_admin_referer( 'an_delete_resource_' . $id );
				$wpdb->delete( $table_name, [ 'id' => $id ] );
				$redirect = admin_url( 'admin.php?page=' . $page . '&msg=deleted' );
				wp_redirect( $redirect );
				exit;
			}

			if ( isset( $_POST['an_save_resource'] ) && check_admin_referer( 'an_save_resource_nonce' ) ) {
				$data = [
					'title'      => sanitize_text_field( $_POST['title'] ),
					'type'       => sanitize_text_field( $_POST['type'] ),
					'file_url'   => esc_url_raw( $_POST['file_url'] ),
					'content'    => wp_kses_post( $_POST['content'] ),
				];
				if ( $id ) {
					$wpdb->update( $table_name, $data, [ 'id' => $id ] );
					$msg = 'updated';
				} else {
					$data['created_at'] = current_time( 'mysql' );
					$wpdb->insert( $table_name, $data );
					$msg = 'added';
				}
				wp_redirect( admin_url( 'admin.php?page=an-resources&msg=' . $msg ) );
				exit;
			}
		}
	}

	public function enqueue_scripts( $hook ) {
		$pages = [
			'agency-nexus_page_an-resources',
			'agency-nexus_page_an-manage-marketplace'
		];

		if ( ! in_array( $hook, $pages ) ) {
			return;
		}
		wp_enqueue_media();
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'project_management' ) ) {
			return;
		}

		add_submenu_page(
			'agency-nexus',
			__( 'Resource Library', 'agency-nexus' ),
			__( 'Resource Library', 'agency-nexus' ),
			'read',
			'an-resources',
			[ $this, 'render_resources' ]
		);

		add_submenu_page(
			'agency-nexus',
			__( 'Marketplace', 'agency-nexus' ),
			__( 'Marketplace', 'agency-nexus' ),
			'read',
			'an-resource-marketplace',
			[ $this, 'render_marketplace' ]
		);

		if ( Agency_Nexus_Permissions::is_admin() ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Manage Marketplace', 'agency-nexus' ),
				__( 'Manage Marketplace', 'agency-nexus' ),
				'manage_options',
				'an-manage-marketplace',
				[ $this, 'render_manage_marketplace' ]
			);
		}

		add_submenu_page(
			'agency-nexus',
			__( 'Questionnaires', 'agency-nexus' ),
			__( 'Questionnaires', 'agency-nexus' ),
			'read',
			'an-questionnaires',
			[ $this, 'render_questionnaires' ]
		);
	}

	public function render_marketplace() {
		global $wpdb;
		$items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}an_resources WHERE is_marketplace = 1 ORDER BY created_at DESC" );

		if ( empty($items) ) {
			return '<p>' . __( 'The marketplace is currently empty. Visit Settings to seed sample data.', 'agency-nexus' ) . '</p>';
		}

		?>
		<div class="agency-nexus-wrap">
			<h1><?php _e( 'Template Marketplace', 'agency-nexus' ); ?></h1>
			<p class="description"><?php _e( 'Buy and sell premium agency assets. From standard contracts to full project workflows.', 'agency-nexus' ); ?></p>

			<div class="an-marketplace-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
				<?php foreach ( $items as $item ) : ?>
					<div class="postbox" style="padding: 20px; text-align: center;">
						<div style="background: #eee; height: 150px; border-radius: 8px; margin-bottom: 15px; display:flex; align-items:center; justify-content:center; color:#999;">Preview</div>
						<h3><?php echo esc_html($item->title); ?></h3>
						<p style="font-size: 1.5rem; font-weight: bold; color: var(--an-indigo-600);">$<?php echo number_format($item->price, 2); ?></p>
						<p><span style="color:#fbc02d;">★★★★★</span> (4.9)</p>
						<button class="button button-primary button-large an-buy-button" data-product-id="<?php echo esc_attr($item->id); ?>" style="width:100%;"><?php _e( 'Buy Now', 'agency-nexus' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($){
			$('.an-buy-button').click(function(e){
				e.preventDefault();
				var btn = $(this);
				var productId = btn.data('product-id');

				btn.prop('disabled', true).text('<?php _e("Processing...", "agency-nexus"); ?>');

				$.post(ajaxurl, {
					action: 'an_marketplace_purchase',
					product_id: productId,
					security: '<?php echo wp_create_nonce("an_marketplace_purchase_nonce"); ?>'
				}, function(response){
					if (response.success && response.data && response.data.redirect_url) {
						window.location.href = response.data.redirect_url;
					} else {
						var msg = (response.data && response.data.message) ? response.data.message : '<?php _e("An error occurred.", "agency-nexus"); ?>';
						alert(msg);
						btn.prop('disabled', false).text('<?php _e("Buy Now", "agency-nexus"); ?>');
					}
				}).fail(function(){
					alert('<?php _e("Server error. Please check your connection.", "agency-nexus"); ?>');
					btn.prop('disabled', false).text('<?php _e("Buy Now", "agency-nexus"); ?>');
				});
			});
		});
		</script>
		<?php
	}

	public function render_manage_marketplace() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_resources';
		$action = isset($_GET['action']) ? $_GET['action'] : 'list';
		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

		if ( isset($_GET['msg']) && 'saved' === $_GET['msg'] ) {
			echo '<div class="updated"><p>Product saved!</p></div>';
		}

		if ($action === 'add' || $action === 'edit') {
			$item = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="wrap">
				<h1><?php echo $id ? __('Edit Product', 'agency-nexus') : __('Add New Product', 'agency-nexus'); ?></h1>
				<form method="post">
					<?php wp_nonce_field('an_save_marketplace_nonce'); ?>
					<table class="form-table">
						<tr>
							<th>Title</th>
							<td>
								<input type="text" name="title" id="an_product_title" value="<?php echo $item ? esc_attr($item->title) : ''; ?>" required class="regular-text">
								<a href="#" class="an-ai-improve-link" data-target="#an_product_title" data-type="product_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
							</td>
						</tr>
						<tr><th>Type</th><td>
							<select name="type">
								<option value="template" <?php selected($item ? $item->type : '', 'template'); ?>>Template</option>
								<option value="swipe" <?php selected($item ? $item->type : '', 'swipe'); ?>>Swipe File</option>
							</select>
						</td></tr>
						<tr><th>Category</th><td><input type="text" name="category" value="<?php echo $item ? esc_attr($item->category) : ''; ?>" class="regular-text" placeholder="e.g. SEO, Web Design"></td></tr>
						<tr><th>Version</th><td><input type="text" name="version" value="<?php echo $item ? esc_attr($item->version) : '1.0.0'; ?>" class="regular-text"></td></tr>
						<tr><th>Compatible With</th><td><input type="text" name="compatible_with" value="<?php echo $item ? esc_attr($item->compatible_with) : ''; ?>" class="regular-text" placeholder="e.g. Elementor, Divi, WP 6.0+"></td></tr>
						<tr><th>Price ($)</th><td><input type="number" step="0.01" name="price" value="<?php echo $item ? esc_attr($item->price) : '0.00'; ?>" required></td></tr>
						<tr>
							<th>Description</th>
							<td>
								<textarea name="content" id="an_product_desc" class="regular-text" rows="5"><?php echo $item ? esc_textarea($item->content) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#an_product_desc" data-type="product_description" style="text-decoration: none;">✨ <?php _e('AI Improve Description', 'agency-nexus'); ?></a>
							</td>
						</tr>
						<tr><th>Product Image URL</th><td>
							<input type="text" name="image_url" id="image_url" value="<?php echo $item ? esc_attr($item->image_url) : ''; ?>" class="regular-text">
							<button type="button" id="upload_image_btn" class="button">Upload/Select Image</button>
						</td></tr>
						<tr><th>File URL (Optional)</th><td>
							<input type="text" name="file_url" id="file_url" value="<?php echo $item ? esc_attr($item->file_url) : ''; ?>" class="regular-text">
							<button type="button" id="upload_file_btn" class="button">Upload/Select File</button>
						</td></tr>
					</table>
					<input type="submit" name="an_save_marketplace_item" class="button button-primary" value="Save Product">
				</form>
			</div>
			<script>
			jQuery(document).ready(function($){
				$('#upload_image_btn').click(function(e) {
					e.preventDefault();
					var frame = wp.media({ title: 'Product Image', multiple: false }).open().on('select', function(e){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#image_url').val(attachment.url);
					});
				});
				$('#upload_file_btn').click(function(e) {
					e.preventDefault();
					var frame = wp.media({ title: 'Product File', multiple: false }).open().on('select', function(e){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#file_url').val(attachment.url);
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

		$items = $wpdb->get_results( "SELECT * FROM $table_name WHERE is_marketplace = 1 ORDER BY created_at DESC" );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e('Manage Marketplace Products', 'agency-nexus'); ?></h1>
			<a href="?page=an-manage-marketplace&action=add" class="page-title-action">Add New Product</a>
			<hr class="wp-header-end">
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Price</th><th>Type</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach($items as $item): ?>
						<tr>
							<td><?php echo $item->image_url ? '<img src="'.esc_url($item->image_url).'" style="width:50px; height:auto; border-radius:4px;">' : '<em>No image</em>'; ?></td>
							<td><strong><?php echo esc_html($item->title); ?></strong></td>
							<td><?php echo esc_html($item->category); ?></td>
							<td>$<?php echo number_format($item->price, 2); ?></td>
							<td><?php echo ucfirst($item->type); ?></td>
							<td>
								<a href="?page=an-manage-marketplace&action=edit&id=<?php echo $item->id; ?>">Edit</a> |
								<a href="<?php echo wp_nonce_url('?page=an-manage-marketplace&action=delete&id='.$item->id, 'an_delete_resource_'.$item->id); ?>" style="color:red;" onclick="return confirm('Delete?')">Delete</a>
							</td>
						</tr>
					<?php endforeach; if(empty($items)) echo '<tr><td colspan="4">No products found.</td></tr>'; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_questionnaires() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_resources';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ($action === 'add' || $action === 'edit') {
			$q = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			$questions = ($q && $q->content) ? json_decode($q->content, true) : [''];
			if (!is_array($questions)) $questions = [''];
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Questionnaire', 'agency-nexus') : __('Add New Questionnaire', 'agency-nexus'); ?></h1>
				<form method="post">
					<?php wp_nonce_field( 'an_save_questionnaire_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Title', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="title" id="an_q_title" value="<?php echo $q ? esc_attr($q->title) : ''; ?>" class="regular-text" required>
								<a href="#" class="an-ai-improve-link" data-target="#an_q_title" data-type="questionnaire_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Questions', 'agency-nexus'); ?></label></th>
							<td id="questions-list">
								<?php foreach($questions as $question): ?>
									<div style="margin-bottom:10px;"><input type="text" name="questions[]" value="<?php echo esc_attr($question); ?>" class="large-text an-q-input"></div>
								<?php endforeach; ?>
								<button type="button" class="button" onclick="jQuery('#questions-list').append('<div style=\'margin-bottom:10px;\'><input type=\'text\' name=\'questions[]\' class=\'large-text an-q-input\'></div>')">+ Add Question</button>
								<button type="button" class="button" id="an-ai-improve-questions" style="margin-left:10px;">✨ <?php _e('AI Improve Questions', 'agency-nexus'); ?></button>
							</td>
						</tr>
					</table>

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

						$('#an-ai-improve-questions').on('click', function(e) {
							e.preventDefault();
							var questions = [];
							$('.an-q-input').each(function() {
								var val = $(this).val();
								if (val.trim()) {
									questions.push(val);
								}
							});

							var textToSend = questions.join("\n");
							if (!textToSend.trim()) {
								textToSend = "agency discovery onboarding questions";
							}

							var $btn = $(this);
							$btn.prop('disabled', true).text('<?php _e("Polishing...", "agency-nexus"); ?>');

							$.post(ajaxurl, {
								action: 'an_ai_improve_content',
								text: textToSend,
								field_type: 'onboarding_questions'
							}, function(response) {
								$btn.prop('disabled', false).text('<?php _e("✨ AI Improve Questions", "agency-nexus"); ?>');
								if (response.success && response.data.improved) {
									var improvedQs = response.data.improved.split("\n");
									$('.an-q-input').parent().remove();
									improvedQs.forEach(function(q) {
										q = q.trim();
										if (q) {
											$('#questions-list').prepend('<div style="margin-bottom:10px;"><input type="text" name="questions[]" value="' + q.replace(/"/g, '&quot;') + '" class="large-text an-q-input"></div>');
										}
									});
								} else {
									alert('AI improvement failed. Ensure your AI Copilot is fully configured.');
								}
							});
						});
					});
					</script>
					<input type="submit" name="an_save_questionnaire" class="button button-primary" value="Save Questionnaire">
					<a href="?page=an-questionnaires" class="button">Cancel</a>
				</form>
			</div>
			<?php
			return;
		}

		$questionnaires = $wpdb->get_results( "SELECT * FROM $table_name WHERE type = 'questionnaire' ORDER BY created_at DESC" );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Discovery Questionnaires', 'agency-nexus' ); ?></h1>
			<a href="?page=an-questionnaires&action=add" class="page-title-action"><?php _e('Add New', 'agency-nexus'); ?></a>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid var(--an-indigo-600); padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<p><?php _e( 'Discovery questionnaires help you extract deep insights from clients during onboarding. Create sets of questions to reuse across projects.', 'agency-nexus' ); ?></p>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Title</th><th>Questions Count</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach($questionnaires as $q):
						$qs = json_decode($q->content, true);
						$count = is_array($qs) ? count($qs) : 0;
					?>
						<tr>
							<td><strong><?php echo esc_html($q->title); ?></strong></td>
							<td><?php echo $count; ?></td>
							<td>
								<a href="?page=an-questionnaires&action=edit&id=<?php echo $q->id; ?>">Edit</a> |
								<a href="<?php echo wp_nonce_url('?page=an-questionnaires&action=delete&id='.$q->id, 'an_delete_resource_'.$q->id); ?>" style="color:red;" onclick="return confirm('Delete this questionnaire?')">Delete</a>
							</td>
						</tr>
					<?php endforeach; if(empty($questionnaires)) echo '<tr><td colspan="3">No questionnaires found.</td></tr>'; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_resources() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'an_resources';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'added':   $m = 'Resource added!'; break;
				case 'updated': $m = 'Resource updated!'; break;
				case 'deleted': $m = 'Resource deleted!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'edit' || $action === 'add') {
			$resource = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)) : null;
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Resource', 'agency-nexus') : __('Add New Resource', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Add an asset to your agency library. These can be templates for your team or files for client download.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'an_save_resource_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="title"><?php _e('Title', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="title" id="an_resource_title" value="<?php echo $resource ? esc_attr($resource->title) : ''; ?>" class="regular-text" required>
								<a href="#" class="an-ai-improve-link" data-target="#an_resource_title" data-type="resource_title" style="margin-left: 10px; text-decoration: none;">✨ <?php _e('AI Improve Title', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Descriptive name of the resource. e.g., Standard Service Agreement', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="type"><?php _e('Type', 'agency-nexus'); ?></label></th>
							<td>
								<select name="type" id="type">
									<option value="template" <?php selected($resource ? $resource->type : '', 'template'); ?>>Template</option>
									<option value="swipe" <?php selected($resource ? $resource->type : '', 'swipe'); ?>>Swipe File</option>
							<option value="contract_clause" <?php selected($resource ? $resource->type : '', 'contract_clause'); ?>>Contract Clause</option>
								</select>
								<p class="description"><?php _e('Templates are frameworks for work; Swipe files are inspiration or examples.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="file_url"><?php _e('File URL', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="file_url" id="file_url" value="<?php echo $resource ? esc_attr($resource->file_url) : ''; ?>" class="regular-text">
								<button type="button" id="upload_resource_btn" class="button">Upload File</button>
								<p class="description"><?php _e('Optional: Upload a document (PDF, Word) or provide a link.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="content"><?php _e('Text Content / Description', 'agency-nexus'); ?></label></th>
							<td>
								<textarea name="content" id="an_resource_content" class="regular-text"><?php echo $resource ? esc_textarea($resource->content) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#an_resource_content" data-type="resource_content" style="text-decoration: none;">✨ <?php _e('AI Improve Content', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('The text body of the template or a brief overview of how to use this resource.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="an_save_resource" class="button button-primary" value="Save Resource">
						<a href="?page=an-resources" class="button">Cancel</a>
					</p>
				</form>
			</div>
			<script>
			jQuery(document).ready(function($){
				$('#upload_resource_btn').click(function(e) {
					e.preventDefault();
					var frame = wp.media({ title: 'Upload Resource', multiple: false }).open().on('select', function(e){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#file_url').val(attachment.url);
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

		$resources = $wpdb->get_results( "SELECT * FROM $table_name WHERE is_marketplace = 0 ORDER BY created_at DESC" );
		$templates = array_filter( $resources, function($r) { return $r->type === 'template'; } );
		$swipes    = array_filter( $resources, function($r) { return $r->type === 'swipe'; } );

		$is_team = Agency_Nexus_Permissions::is_team_member();
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Agency Resource Library', 'agency-nexus' ); ?></h1>
			<?php if ($is_team) : ?>
			<a href="?page=an-resources&action=add" class="page-title-action"><?php _e('Add New', 'agency-nexus'); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid #fbc02d; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Scale Your Agency with IP', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'A resource library is your agency\'s competitive advantage. By storing reusable assets, you reduce "reinventing the wheel" for every client project.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Templates:</strong> Standardize your workflows with reusable project plans, contracts, and questionnaires.</li>
					<li><strong>Swipe Files:</strong> Create a "hall of fame" for your best-performing ad copy, emails, and designs.</li>
					<li><strong>Easy Access:</strong> Your team can quickly find and download the latest versions of any asset.</li>
				</ul>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Type', 'agency-nexus'); ?></th>
						<th><?php _e('Title', 'agency-nexus'); ?></th>
						<th><?php _e('Actions', 'agency-nexus'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ($resources) : foreach ($resources as $res) : ?>
						<tr>
							<td><span class="badge"><?php echo esc_html(ucfirst($res->type)); ?></span></td>
							<td><strong><?php echo esc_html($res->title); ?></strong></td>
							<td>
								<?php if ($res->file_url) : ?><a href="<?php echo esc_url($res->file_url); ?>" target="_blank"><?php _e('View', 'agency-nexus'); ?></a><?php endif; ?>
								<?php if ($is_team) : ?>
								| <a href="?page=an-resources&action=edit&id=<?php echo $res->id; ?>"><?php _e('Edit', 'agency-nexus'); ?></a> |
								<?php if ( Agency_Nexus_Permissions::is_admin() ) : ?>
								<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=an-resources&action=delete&id=' . $res->id), 'an_delete_resource_' . $res->id ); ?>" style="color:red;" onclick="return confirm('Are you sure?')"><?php _e('Delete', 'agency-nexus'); ?></a>
								<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="3"><?php _e('No resources found.', 'agency-nexus'); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'project_management' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			return;
		}
		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'FreebieFactory', 'agency-nexus' ); ?></h2>
			<p><?php _e( 'Need a template? 2 new swipe files added this week.', 'agency-nexus' ); ?></p>
			<a href="<?php echo admin_url( 'admin.php?page=an-resources' ); ?>" class="button"><?php _e( 'Browse Resources', 'agency-nexus' ); ?></a>
		</div>
		<?php
	}
}
