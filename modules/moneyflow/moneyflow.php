<?php
/**
 * MoneyFlow Module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_Module_Moneyflow extends Agency_Nexus_Base_Module {

	protected $name = 'MoneyFlow';

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ] );
		add_action( 'agency_nexus_dashboard_widgets', [ $this, 'render_dashboard_widget' ] );
		add_action( 'admin_init', [ $this, 'handle_post' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'template_redirect', [ $this, 'handle_client_payment' ] );
		add_action( 'wp_ajax_an_marketplace_purchase', [ $this, 'handle_marketplace_purchase' ] );
		add_action( 'wp_ajax_nopriv_an_marketplace_purchase', [ $this, 'handle_marketplace_purchase' ] );
		add_action( 'wp_ajax_an_ai_invoice_reminder', [ $this, 'handle_ai_invoice_reminder' ] );
	}

	public function handle_post() {
		if ( ! is_admin() || ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( 'an-expenses' === $page ) {
			$this->process_expense_actions();
		} elseif ( 'an-invoices' === $page ) {
			$this->process_invoice_actions();
		}
	}

	private function process_expense_actions() {
		global $wpdb;
		$expenses_table = $wpdb->prefix . 'an_expenses';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( 'delete' === $action && $id ) {
			check_admin_referer( 'an_delete_expense_' . $id );
			$wpdb->delete( $expenses_table, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-expenses&msg=deleted' ) );
			exit;
		}

		if ( isset( $_POST['an_save_expense'] ) && check_admin_referer( 'an_save_expense_nonce' ) ) {
			$project_id = intval( $_POST['project_id'] );
			if ( $project_id && ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
				wp_die( 'Unauthorized' );
			}
			$data = [
				'project_id'  => $project_id,
				'amount'      => floatval( $_POST['amount'] ),
				'category'    => sanitize_text_field( $_POST['category'] ),
				'note'        => sanitize_textarea_field( $_POST['note'] ),
				'receipt_url' => esc_url_raw( $_POST['receipt_url'] ),
			];
			if ( $id ) {
				$wpdb->update( $expenses_table, $data, [ 'id' => $id ] );
				$msg = 'updated';
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $expenses_table, $data );
				$msg = 'recorded';
			}
			wp_redirect( admin_url( 'admin.php?page=an-expenses&msg=' . $msg ) );
			exit;
		}
	}

	private function process_invoice_actions() {
		global $wpdb;
		$invoices_table = $wpdb->prefix . 'an_invoices';
		$payments_table = $wpdb->prefix . 'an_payments';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_POST['an_apply_late_fees'] ) ) {
			$wpdb->query( "UPDATE {$wpdb->prefix}an_invoices SET amount = amount * 1.05, status = 'overdue' WHERE status = 'sent' AND due_date < CURDATE()" );
			wp_redirect( admin_url( 'admin.php?page=an-invoices&msg=late_fees_applied' ) );
			exit;
		}

		// Client is paying via Gateway
		if ( isset( $_POST['an_pay_invoice_gateway'] ) ) {
			$gateway = sanitize_text_field( $_POST['gateway'] );
			$this->initiate_gateway_payment( $id, $gateway );
			return;
		}

		if ( 'delete' === $action && $id ) {
			check_admin_referer( 'an_delete_invoice_' . $id );
			$wpdb->delete( $invoices_table, [ 'id' => $id ] );
			wp_redirect( admin_url( 'admin.php?page=an-invoices&msg=deleted' ) );
			exit;
		}

		if ( isset( $_POST['an_save_invoice'] ) && check_admin_referer( 'an_save_invoice_nonce' ) ) {
			$project_id = intval( $_POST['project_id'] );
			if ( ! Agency_Nexus_Permissions::can_view_project( $project_id ) ) {
				wp_die( 'Unauthorized' );
			}
			$data = [
				'project_id' => $project_id,
				'client_id'  => intval( $_POST['client_id'] ),
				'number'     => sanitize_text_field( $_POST['number'] ),
				'amount'     => floatval( $_POST['amount'] ),
				'status'     => sanitize_text_field( $_POST['status'] ),
				'due_date'   => sanitize_text_field( $_POST['due_date'] ),
			];
			if ( $id ) {
				$wpdb->update( $invoices_table, $data, [ 'id' => $id ] );
				$msg = 'updated';
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $invoices_table, $data );
				$msg = 'created';
			}
			wp_redirect( admin_url( 'admin.php?page=an-invoices&msg=' . $msg ) );
			exit;
		}

		if ( isset( $_POST['an_add_payment'] ) && check_admin_referer( 'an_add_payment_nonce' ) ) {
			$inv_id = intval( $_POST['id'] );
			$wpdb->insert( $payments_table, [
				'invoice_id'     => $inv_id,
				'amount'         => floatval( $_POST['pay_amount'] ),
				'method'         => 'other',
				'transaction_id' => '',
				'created_at'     => current_time( 'mysql' )
			] );
			$total_paid = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $payments_table WHERE invoice_id = %d", $inv_id ) );
			$inv_amount = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM $invoices_table WHERE id = %d", $inv_id ) );
			if ( $total_paid >= $inv_amount ) {
				$wpdb->update( $invoices_table, [ 'status' => 'paid' ], [ 'id' => $inv_id ] );
			}
			wp_redirect( admin_url( 'admin.php?page=an-invoices&msg=paid' ) );
			exit;
		}
	}

	public function register_submenu() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'money_flow' ) ) {
			return;
		}

		if ( Agency_Nexus_Permissions::is_team_member() ) {
			add_submenu_page(
				'agency-nexus',
				__( 'Expenses', 'agency-nexus' ),
				__( 'Expenses', 'agency-nexus' ),
				'read',
				'an-expenses',
				[ $this, 'render_expenses' ]
			);
		}

		// Always allow authorized users (including clients) to access invoices
		add_submenu_page(
			'agency-nexus',
			__( 'Invoices', 'agency-nexus' ),
			__( 'Invoices', 'agency-nexus' ),
			'read',
			'an-invoices',
			[ $this, 'render_invoices' ]
		);
	}

	public function enqueue_scripts( $hook ) {
		if ( 'agency-nexus_page_an-expenses' !== $hook ) {
			return;
		}
		wp_enqueue_media();
	}

	/**
	 * Render the expenses management page.
	 */
	public function render_expenses() {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'an_projects';
		$expenses_table = $wpdb->prefix . 'an_expenses';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'recorded': $m = 'Expense recorded!'; break;
				case 'updated':  $m = 'Expense updated!'; break;
				case 'deleted':  $m = 'Expense deleted!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ( $action === 'edit' || $action === 'add' ) {
			$expense = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $expenses_table WHERE id = %d", $id ) ) : null;
			$projects = $wpdb->get_results( "SELECT id, title FROM $projects_table" );
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __( 'Edit Expense', 'agency-nexus' ) : __( 'Add New Expense', 'agency-nexus' ); ?></h1>
				<p class="description"><?php _e('Record a business cost to track your agency\'s true profitability.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'an_save_expense_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="project_id"><?php _e('Project', 'agency-nexus'); ?></label></th>
							<td>
								<select name="project_id" id="project_id">
									<option value="0"><?php _e( 'General / No Project', 'agency-nexus' ); ?></option>
									<?php foreach ( $projects as $project ) : ?>
										<option value="<?php echo $project->id; ?>" <?php selected( $expense ? $expense->project_id : 0, $project->id ); ?>><?php echo esc_html( $project->title ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Optional: Link this cost to a specific client project.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="amount"><?php _e('Amount ($)', 'agency-nexus'); ?></label></th>
							<td>
								<input type="number" step="0.01" name="amount" id="amount" value="<?php echo $expense ? esc_attr($expense->amount) : ''; ?>" class="regular-text" required>
								<p class="description"><?php _e('Total cost including tax. e.g., 29.99', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="category"><?php _e('Category', 'agency-nexus'); ?></label></th>
							<td>
								<select name="category" id="category">
									<option value="software" <?php selected( $expense ? $expense->category : '', 'software' ); ?>><?php _e( 'Software / Tools', 'agency-nexus' ); ?></option>
									<option value="outsourcing" <?php selected( $expense ? $expense->category : '', 'outsourcing' ); ?>><?php _e( 'Outsourcing', 'agency-nexus' ); ?></option>
									<option value="marketing" <?php selected( $expense ? $expense->category : '', 'marketing' ); ?>><?php _e( 'Marketing', 'agency-nexus' ); ?></option>
									<option value="travel" <?php selected( $expense ? $expense->category : '', 'travel' ); ?>><?php _e( 'Travel', 'agency-nexus' ); ?></option>
									<option value="other" <?php selected( $expense ? $expense->category : '', 'other' ); ?>><?php _e( 'Other', 'agency-nexus' ); ?></option>
								</select>
								<p class="description"><?php _e('Used for financial reporting and tax categorization.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="receipt_url"><?php _e('Receipt', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="receipt_url" id="receipt_url" value="<?php echo $expense ? esc_attr($expense->receipt_url) : ''; ?>" class="regular-text">
								<button type="button" id="upload_receipt_button" class="button"><?php _e( 'Upload Receipt', 'agency-nexus' ); ?></button>
								<p class="description"><?php _e('Upload a PDF or image of the receipt for your records.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="note"><?php _e('Note', 'agency-nexus'); ?></label></th>
							<td>
								<textarea name="note" id="note" class="regular-text"><?php echo $expense ? esc_textarea($expense->note) : ''; ?></textarea>
									<br><a href="#" class="an-ai-improve-link" data-target="#note" data-type="expense_note" style="text-decoration: none;">✨ <?php _e('AI Improve Note', 'agency-nexus'); ?></a>
								<p class="description"><?php _e('Internal memo about this purchase.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="an_save_expense" class="button button-primary" value="<?php _e('Save Expense', 'agency-nexus'); ?>">
						<a href="?page=an-expenses" class="button"><?php _e('Cancel', 'agency-nexus'); ?></a>
					</p>
				</form>
			</div>
			<script>
			jQuery(document).ready(function($){
				$('#upload_receipt_button').click(function(e) {
					e.preventDefault();
					var frame = wp.media({ title: 'Upload Receipt', multiple: false }).open().on('select', function(e){
						var uploaded_image = frame.state().get('selection').first().toJSON();
						$('#receipt_url').val(uploaded_image.url);
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

		$expenses_query = "SELECT e.*, p.title as project_title FROM $expenses_table e LEFT JOIN $projects_table p ON e.project_id = p.id";
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$expenses_query .= " WHERE 1=0";
			} else {
				$expenses_query .= " WHERE e.project_id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
			}
		}
		$expenses_query .= " ORDER BY e.created_at DESC";
		$expenses = $wpdb->get_results( $expenses_query );

		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Expense Management', 'agency-nexus' ); ?></h1>
			<a href="?page=an-expenses&action=add" class="page-title-action"><?php _e('Add New', 'agency-nexus'); ?></a>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Maintain Healthy Margins', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Tracking expenses is crucial for calculating the true ROI of your projects. By logging every cost (software, outsourcing, etc.), you get an accurate picture of your agency\'s health.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Categorize:</strong> Assign expenses to categories to simplify tax preparation.</li>
					<li><strong>Link to Projects:</strong> Specific project costs are subtracted from the project budget in your <strong>True Profit</strong> calculation.</li>
					<li><strong>Digital Paper Trail:</strong> Upload receipts immediately so they are never lost.</li>
				</ul>
			</div>

			<h2><?php _e( 'Expense History', 'agency-nexus' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Date</th>
						<th>Project</th>
						<th>Category</th>
						<th>Amount</th>
						<th>Receipt</th>
						<th>Note</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $expenses ) : foreach ( $expenses as $expense ) : ?>
						<tr>
							<td><?php echo date( 'Y-m-d', strtotime( $expense->created_at ) ); ?></td>
							<td><?php echo $expense->project_id ? esc_html( $expense->project_title ) : '<em>General</em>'; ?></td>
							<td><?php echo esc_html( ucfirst( $expense->category ) ); ?></td>
							<td>$<?php echo number_format( floatval($expense->amount), 2 ); ?></td>
							<td><?php if ( $expense->receipt_url ) : ?><a href="<?php echo esc_url( $expense->receipt_url ); ?>" target="_blank">View Receipt</a><?php endif; ?></td>
							<td><?php echo esc_html( $expense->note ); ?></td>
							<td>
								<a href="?page=an-expenses&action=edit&id=<?php echo $expense->id; ?>"><?php _e('Edit', 'agency-nexus'); ?></a> |
								<a href="<?php echo wp_nonce_url('?page=an-expenses&action=delete&id=' . $expense->id, 'an_delete_expense_' . $expense->id); ?>" style="color:red;" onclick="return confirm('Delete this expense?')"><?php _e('Delete', 'agency-nexus'); ?></a>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="6">No expenses found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($){
			$('#upload_receipt_button').click(function(e) {
				e.preventDefault();
				var image = wp.media({
					title: 'Upload Receipt',
					multiple: false
				}).open()
				.on('select', function(e){
					var uploaded_image = image.state().get('selection').first();
					var image_url = uploaded_image.toJSON().url;
					$('#receipt_url').val(image_url);
				});
			});
		});
		</script>
		<?php
	}

	public function handle_client_payment() {
		if ( ! isset( $_GET['an_invoice_return'] ) ) {
			return;
		}

		global $wpdb;
		$gateway = sanitize_text_field( $_GET['an_invoice_return'] );
		$invoice_id = intval( $_GET['invoice_id'] );
		$token = sanitize_text_field( $_GET['token'] );

		// Verify token to prevent spoofing
		$saved_token = get_transient( 'an_inv_pay_' . $invoice_id );
		if ( $token !== $saved_token ) {
			wp_die( 'Invalid payment session.' );
		}

		// Mark invoice as paid
		$wpdb->update( $wpdb->prefix . 'an_invoices', [ 'status' => 'paid' ], [ 'id' => $invoice_id ] );

		// Record payment
		$amount = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM {$wpdb->prefix}an_invoices WHERE id = %d", $invoice_id ) );
		$wpdb->insert( $wpdb->prefix . 'an_payments', [
			'invoice_id'     => $invoice_id,
			'amount'         => $amount,
			'method'         => $gateway,
			'transaction_id' => strtoupper( $gateway[0] ) . '-' . time(),
			'created_at'     => current_time( 'mysql' )
		] );

		delete_transient( 'an_inv_pay_' . $invoice_id );

		wp_redirect( admin_url( 'admin.php?page=an-invoices&msg=paid' ) );
		exit;
	}

	private function initiate_gateway_payment( $invoice_id, $gateway ) {
		global $wpdb;
		$invoice = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_invoices WHERE id = %d", $invoice_id ) );
		$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_clients WHERE id = %d", $invoice->client_id ) );

		$token = wp_generate_password( 32, false );
		set_transient( 'an_inv_pay_' . $invoice_id, $token, 3600 );

		$return_url = add_query_arg( [
			'an_invoice_return' => $gateway,
			'invoice_id'        => $invoice_id,
			'token'             => $token
		], admin_url( 'admin.php?page=an-invoices' ) );

		if ( $gateway === 'stripe' ) {
			$secret_key = get_option( 'an_stripe_key' );
			if ( empty( $secret_key ) ) wp_die( 'Stripe not configured.' );

			$response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', [
				'headers' => [ 'Authorization' => 'Bearer ' . $secret_key ],
				'body' => [
					'success_url' => $return_url,
					'cancel_url'  => admin_url( 'admin.php?page=an-invoices' ),
					'mode'        => 'payment',
					'customer_email' => $client->email,
					'line_items[0][price_data][currency]' => 'usd',
					'line_items[0][price_data][product_data][name]' => 'Invoice ' . $invoice->number,
					'line_items[0][price_data][unit_amount]' => intval( $invoice->amount * 100 ),
					'line_items[0][quantity]' => 1,
				]
			] );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $body['url'] ) ) {
				wp_redirect( $body['url'] );
				exit;
			}
		} else {
			$paypal_email = get_option( 'an_paypal_email' );
			if ( empty( $paypal_email ) ) wp_die( 'PayPal not configured.' );

			$test_mode = get_option( 'an_payment_test_mode', 'yes' );
			$paypal_url = ( $test_mode === 'yes' ) ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

			$args = [
				'cmd'           => '_xclick',
				'business'      => $paypal_email,
				'item_name'     => 'Invoice ' . $invoice->number,
				'amount'        => $invoice->amount,
				'currency_code' => 'USD',
				'return'        => $return_url,
				'cancel_return' => admin_url( 'admin.php?page=an-invoices' ),
			];
			wp_redirect( $paypal_url . '?' . http_build_query( $args ) );
			exit;
		}
		wp_die( 'Payment initiation failed.' );
	}

	/**
	 * Render the invoices management page.
	 */
	public function render_invoices() {
		global $wpdb;
		$invoices_table = $wpdb->prefix . 'an_invoices';
		$projects_table = $wpdb->prefix . 'an_projects';
		$clients_table  = $wpdb->prefix . 'an_clients';
		$payments_table = $wpdb->prefix . 'an_payments';

		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( isset( $_GET['msg'] ) ) {
			$m = '';
			switch ( $_GET['msg'] ) {
				case 'created': $m = 'Invoice created!'; break;
				case 'updated': $m = 'Invoice updated!'; break;
				case 'deleted': $m = 'Invoice deleted!'; break;
				case 'paid':    $m = 'Payment recorded!'; break;
				case 'late_fees_applied': $m = 'Late fees (5%) applied to all overdue invoices!'; break;
			}
			if ( $m ) echo '<div class="updated"><p>' . esc_html( $m ) . '</p></div>';
		}

		if ($action === 'print' && $id) {
			$invoice = $wpdb->get_row($wpdb->prepare("SELECT i.*, c.name as client_name, c.email as client_email, p.title as project_title FROM $invoices_table i JOIN $clients_table c ON i.client_id = c.id JOIN $projects_table p ON i.project_id = p.id WHERE i.id = %d", $id));

			$is_white_label = Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'white_label' );
			$logo = $is_white_label ? get_option( 'an_agency_logo' ) : '';

			?>
			<div class="wrap" id="printable-invoice" style="background: white; padding: 40px; font-family: sans-serif; max-width: 800px; margin: 20px auto; border: 1px solid #eee; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
				<div style="display:flex; justify-content: space-between; align-items: center;">
					<div id="agency-info">
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" style="max-width: 150px; height: auto; margin-bottom: 10px;">
						<?php else : ?>
							<h1 style="color: var(--an-indigo-600); margin: 0;">Agency Nexus</h1>
							<p><small><?php _e( 'Powered by Agency Nexus - Professional Operations', 'agency-nexus' ); ?></small></p>
						<?php endif; ?>
					</div>
					<div style="text-align:right;">
						<h2 style="margin:0;"><?php _e( 'INVOICE', 'agency-nexus' ); ?></h2>
						<strong><?php echo esc_html($invoice->number); ?></strong><br>
						Date: <?php echo date('Y-m-d', strtotime($invoice->created_at)); ?><br>
						Due: <?php echo esc_html($invoice->due_date); ?>
					</div>
				</div>
				<hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">
				<div style="margin: 40px 0; display: flex; justify-content: space-between;">
					<div>
						<strong>Bill To:</strong><br>
						<?php echo esc_html($invoice->client_name); ?><br>
						<?php echo esc_html($invoice->client_email); ?>
					</div>
					<div style="text-align:right;">
						<strong>Status:</strong><br>
						<span style="font-size: 1.2rem; font-weight: bold; color: <?php echo $invoice->status === 'paid' ? '#46b450' : '#dc3232'; ?>;">
							<?php echo strtoupper($invoice->status); ?>
						</span>
					</div>
				</div>
				<table style="width:100%; border-collapse: collapse; margin-bottom: 40px;">
					<thead><tr style="background:#f8fafc; border-bottom: 2px solid #e2e8f0;"><th style="padding:15px; text-align:left;">Description</th><th style="padding:15px; text-align:right;">Amount</th></tr></thead>
					<tbody>
						<tr>
							<td style="padding:15px; border-bottom:1px solid #edf2f7;"><?php echo esc_html($invoice->project_title); ?></td>
							<td style="padding:15px; border-bottom:1px solid #edf2f7; text-align:right;">$<?php echo number_format(floatval($invoice->amount), 2); ?></td>
						</tr>
					</tbody>
					<tfoot>
						<tr><td style="padding:15px; text-align:right;"><strong>Total:</strong></td><td style="padding:15px; text-align:right;"><strong>$<?php echo number_format(floatval($invoice->amount), 2); ?></strong></td></tr>
					</tfoot>
				</table>

				<?php if ( $invoice->status !== 'paid' ) : ?>
					<div class="no-print" style="background: #f1f5f9; padding: 30px; border-radius: 12px; text-align: center;">
						<h3><?php _e( 'Pay Securely Online', 'agency-nexus' ); ?></h3>
						<p><?php _e( 'Choose your preferred payment method below to settle this invoice immediately.', 'agency-nexus' ); ?></p>
						<div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
							<form method="post">
								<?php wp_nonce_field('an_save_invoice_nonce'); ?>
								<input type="hidden" name="gateway" value="stripe">
								<input type="submit" name="an_pay_invoice_gateway" class="button button-primary" value="Pay with Credit Card (Stripe)" style="background: #6366f1; border: none; padding: 10px 25px;">
							</form>
							<form method="post">
								<?php wp_nonce_field('an_save_invoice_nonce'); ?>
								<input type="hidden" name="gateway" value="paypal">
								<input type="submit" name="an_pay_invoice_gateway" class="button button-primary" value="Pay with PayPal" style="background: #0070ba; border: none; padding: 10px 25px;">
							</form>
						</div>
					</div>
				<?php endif; ?>

				<div style="margin-top: 50px; text-align:center;">
					<button onclick="window.print()" class="button no-print">Print PDF</button>
					<a href="?page=an-invoices" class="button no-print">Back to Invoices</a>
				</div>
				<style>@media print { .no-print { display:none; } #printable-invoice { border:none; box-shadow:none; margin:0; width:100%; max-width:none; } }</style>
			</div>
			<?php
			return;
		}

		if ($action === 'edit' || $action === 'add') {
			$invoice = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $id)) : null;
			$projects = $wpdb->get_results("SELECT id, title, client_id FROM $projects_table");
			$clients = $wpdb->get_results("SELECT id, name FROM $clients_table");
			?>
			<div class="agency-nexus-wrap">
				<h1><?php echo $id ? __('Edit Invoice', 'agency-nexus') : __('Create New Invoice', 'agency-nexus'); ?></h1>
				<p class="description"><?php _e('Generate a professional invoice for your client. Once saved, you can print it or record payments.', 'agency-nexus'); ?></p>
				<form method="post">
					<?php wp_nonce_field('an_save_invoice_nonce'); ?>
					<table class="form-table">
						<tr>
							<th><label><?php _e('Invoice Number', 'agency-nexus'); ?></label></th>
							<td>
								<input type="text" name="number" value="<?php echo $invoice ? esc_attr($invoice->number) : 'INV-' . time(); ?>" required>
								<p class="description"><?php _e('Unique identifier for this invoice. e.g., INV-2024-001', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Project', 'agency-nexus'); ?></label></th>
							<td>
								<select name="project_id" required>
									<?php foreach ($projects as $p) : ?>
										<option value="<?php echo $p->id; ?>" <?php selected($invoice ? $invoice->project_id : 0, $p->id); ?>><?php echo esc_html($p->title); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Link this invoice to a project for revenue tracking.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Client', 'agency-nexus'); ?></label></th>
							<td>
								<select name="client_id" required>
									<?php foreach ($clients as $c) : ?>
										<option value="<?php echo $c->id; ?>" <?php selected($invoice ? $invoice->client_id : 0, $c->id); ?>><?php echo esc_html($c->name); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Who is being billed?', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Amount ($)', 'agency-nexus'); ?></label></th>
							<td>
								<input type="number" step="0.01" name="amount" value="<?php echo $invoice ? esc_attr($invoice->amount) : ''; ?>" required>
								<p class="description"><?php _e('Total amount due.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Due Date', 'agency-nexus'); ?></label></th>
							<td>
								<input type="date" name="due_date" value="<?php echo $invoice ? esc_attr($invoice->due_date) : ''; ?>" required>
								<p class="description"><?php _e('Deadline for payment.', 'agency-nexus'); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php _e('Status', 'agency-nexus'); ?></label></th>
							<td>
								<select name="status">
									<option value="draft" <?php selected($invoice ? $invoice->status : '', 'draft'); ?>>Draft</option>
									<option value="sent" <?php selected($invoice ? $invoice->status : '', 'sent'); ?>>Sent</option>
									<option value="paid" <?php selected($invoice ? $invoice->status : '', 'paid'); ?>>Paid</option>
									<option value="overdue" <?php selected($invoice ? $invoice->status : '', 'overdue'); ?>>Overdue</option>
								</select>
								<p class="description"><?php _e('Current state of the invoice.', 'agency-nexus'); ?></p>
							</td>
						</tr>
					</table>
					<input type="submit" name="an_save_invoice" class="button button-primary" value="Save Invoice">
				</form>
			</div>
			<?php
			return;
		}

		$invoices_query = "SELECT i.*, c.name as client_name, p.title as project_title FROM $invoices_table i JOIN $clients_table c ON i.client_id = c.id JOIN $projects_table p ON i.project_id = p.id";
		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		if ( is_array( $authorised_ids ) ) {
			if ( empty( $authorised_ids ) ) {
				$invoices_query .= " WHERE 1=0";
			} else {
				$invoices_query .= " WHERE i.project_id IN (" . implode( ',', array_map( 'intval', $authorised_ids ) ) . ")";
			}
		}
		$invoices_query .= " ORDER BY i.created_at DESC";
		$invoices = $wpdb->get_results( $invoices_query );
		?>
		<div class="agency-nexus-wrap">
			<h1 class="wp-heading-inline"><?php _e('Invoices', 'agency-nexus'); ?></h1>
			<a href="?page=an-invoices&action=add" class="page-title-action">Add New</a>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field('an_save_invoice_nonce'); ?>
				<input type="submit" name="an_apply_late_fees" class="page-title-action" value="Apply Late Fees (5%)" onclick="return confirm('Apply 5% late fee to all overdue invoices?')">
			</form>
			<hr class="wp-header-end">

			<div style="background: #fff; border-left: 4px solid #46b450; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h3><?php _e( 'Streamline Your Cashflow', 'agency-nexus' ); ?></h3>
				<p><?php _e( 'Professional invoicing helps you get paid faster. MoneyFlow automates the generation of invoices based on your project data.', 'agency-nexus' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><strong>Professional Branding:</strong> Every invoice automatically pulls your logo from the Global Settings.</li>
					<li><strong>Payment Tracking:</strong> Record partial or full payments to keep your accounts receivable up to date.</li>
					<li><strong>Print & Save:</strong> Use the "Print" action to generate high-quality invoices for your clients.</li>
				</ul>
			</div>
			<hr class="wp-header-end">

			<script>
			jQuery(document).ready(function($) {
				$('.an-ai-reminder-link').on('click', function(e) {
					e.preventDefault();
					var $link = $(this);
					var invNum = $link.data('invoice-number');

					var originalText = $link.html();
					$link.text('<?php _e("Drafting...", "agency-nexus"); ?>').css('pointer-events', 'none');

					$.post(ajaxurl, {
						action: 'an_ai_invoice_reminder',
						invoice_number: invNum,
						security: '<?php echo wp_create_nonce("an_save_invoice_nonce"); ?>'
					}, function(response) {
						$link.html(originalText).css('pointer-events', 'auto');
						if (response.success && response.data.draft) {
							alert("AI-Drafted Collection Email:\n\n" + response.data.draft);
						} else {
							alert('Failed to draft reminder. Ensure your AI Copilot is fully configured.');
						}
					});
				});
			});
			</script>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Number</th><th>Project</th><th>Client</th><th>Amount</th><th>Status</th><th>Due</th><th>Actions</th></tr></thead>
				<tbody>
					<?php foreach ($invoices as $inv) :
						$total_paid = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM $payments_table WHERE invoice_id = %d", $inv->id));
					?>
						<tr>
							<td><strong><?php echo esc_html($inv->number); ?></strong></td>
							<td><?php echo esc_html($inv->project_title); ?></td>
							<td><?php echo esc_html($inv->client_name); ?></td>
							<td>$<?php echo number_format(floatval($inv->amount), 2); ?> <br><small>Paid: $<?php echo number_format(floatval($total_paid), 2); ?></small></td>
							<td><span class="badge status-<?php echo $inv->status; ?>"><?php echo ucfirst($inv->status); ?></span></td>
							<td><?php echo esc_html($inv->due_date); ?></td>
							<td>
								<a href="?page=an-invoices&action=print&id=<?php echo $inv->id; ?>">Print</a> |
								<a href="?page=an-invoices&action=edit&id=<?php echo $inv->id; ?>">Edit</a> |
								<a href="<?php echo wp_nonce_url('?page=an-invoices&action=delete&id=' . $inv->id, 'an_delete_invoice_' . $inv->id); ?>" style="color:red;">Delete</a>
								<?php if ( $inv->status !== 'paid' ) : ?>
									| <a href="#" class="an-ai-reminder-link" data-invoice-number="<?php echo esc_attr( $inv->number ); ?>" style="color:var(--an-indigo-600); font-weight:bold;"><?php _e( 'AI Email', 'agency-nexus' ); ?></a>
								<?php endif; ?>
								<br>
								<form method="post" style="display:inline-block; margin-top:5px;">
									<?php wp_nonce_field('an_add_payment_nonce'); ?>
									<input type="hidden" name="id" value="<?php echo $inv->id; ?>">
									<input type="number" step="0.01" name="pay_amount" placeholder="Amt" style="width:60px;">
									<input type="submit" name="an_add_payment" value="Pay" class="button button-small">
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Calculate project profitability.
	 * Formula: (Total Budget - Total Expenses - (Total Hours * Hourly Rate))
	 */
	public function calculate_project_profitability( $project_id ) {
		global $wpdb;
		$table_projects = $wpdb->prefix . 'an_projects';
		$table_time = $wpdb->prefix . 'an_time_entries';
		$table_tasks = $wpdb->prefix . 'an_tasks';

		// Get project budget
		$budget = $wpdb->get_var( $wpdb->prepare( "SELECT budget FROM $table_projects WHERE id = %d", $project_id ) );

		// Get total hours spent
		$total_seconds = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM(t.duration)
			FROM $table_time t
			JOIN $table_tasks tk ON t.task_id = tk.id
			WHERE tk.project_id = %d
		", $project_id ) );

		$total_hours = $total_seconds / 3600;

		// Use the global hourly rate setting or fallback to 50.
		$hourly_cost = get_option( 'an_hourly_rate', 50 );
		$labor_cost = $total_hours * $hourly_cost;

		// Get expenses for project
		$expenses = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$wpdb->prefix}an_expenses WHERE project_id = %d", $project_id ) );
		$expenses = $expenses ? $expenses : 0;

		$profitability = $budget - $labor_cost - $expenses;
		$tax_estimate = $profitability > 0 ? $profitability * 0.25 : 0; // 25% tax estimate

		return [
			'budget'        => $budget,
			'labor_cost'    => $labor_cost,
			'expenses'      => $expenses,
			'profitability' => $profitability,
			'tax_estimate'  => $tax_estimate,
			'net_profit'    => $profitability - $tax_estimate,
			'margin'        => $budget > 0 ? ( $profitability / $budget ) * 100 : 0
		];
	}

	/**
	 * AJAX Handler for Marketplace purchases.
	 */
	public function handle_marketplace_purchase() {
		check_ajax_referer( 'an_marketplace_purchase_nonce', 'security' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Please log in to purchase.', 'agency-nexus' ) ] );
		}

		$is_client = Agency_Nexus_Permissions::is_client();
		$is_admin  = Agency_Nexus_Permissions::is_admin();

		if ( ! $is_client && ! $is_admin ) {
			wp_send_json_error( [ 'message' => __( 'Access denied. Purchase is reserved for clients.', 'agency-nexus' ) ] );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'agency-nexus' ) ] );
		}

		global $wpdb;
		$product = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_resources WHERE id = %d AND is_marketplace = 1", $product_id ) );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'agency-nexus' ) ] );
		}

		$client_id = Agency_Nexus_Permissions::get_client_id_for_user( get_current_user_id() );

		if ( ! $client_id ) {
			// For Administrators testing the system, auto-create a client record if it doesn't exist.
			if ( current_user_can( 'manage_options' ) ) {
				$current_user = wp_get_current_user();
				$wpdb->insert( $wpdb->prefix . 'an_clients', [
					'name'       => $current_user->display_name,
					'email'      => $current_user->user_email,
					'company'    => 'Agency Admin (Test)',
					'address'    => 'Internal',
					'status'     => 'active',
					'created_at' => current_time( 'mysql' )
				] );
				$client_id = $wpdb->insert_id;
			} else {
				wp_send_json_error( [ 'message' => __( 'Your user account is not linked to a Client record. Please contact the administrator.', 'agency-nexus' ) ] );
			}
		}

		// 1. Ensure a "Marketplace Purchases" project exists for this client
		$project_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}an_projects WHERE client_id = %d AND title = 'Marketplace Purchases'", $client_id ) );
		if ( ! $project_id ) {
			$wpdb->insert( $wpdb->prefix . 'an_projects', [
				'client_id' => $client_id,
				'title'     => 'Marketplace Purchases',
				'status'    => 'active',
				'budget'    => 0,
				'created_at' => current_time( 'mysql' )
			] );
			$project_id = $wpdb->insert_id;
		}

		// 2. Create the invoice
		$invoice_number = 'MKT-' . strtoupper( wp_generate_password( 6, false ) );
		$wpdb->insert( $wpdb->prefix . 'an_invoices', [
			'project_id' => $project_id,
			'client_id'  => $client_id,
			'number'     => $invoice_number,
			'amount'     => $product->price,
			'status'     => 'sent',
			'due_date'   => current_time( 'Y-m-d' ),
			'created_at' => current_time( 'mysql' )
		] );
		$invoice_id = $wpdb->insert_id;

		$redirect_url = admin_url( 'admin.php?page=an-invoices&action=print&id=' . $invoice_id );

		wp_send_json_success( [
			'message'      => __( 'Invoice generated!', 'agency-nexus' ),
			'redirect_url' => $redirect_url
		] );
	}

	public function render_dashboard_widget() {
		if ( ! Agency_Nexus_License_Manager::get_instance()->is_feature_enabled( 'money_flow' ) ) {
			return;
		}

		if ( ! Agency_Nexus_Permissions::is_team_member() ) {
			return;
		}
		global $wpdb;

		$authorised_ids = Agency_Nexus_Permissions::get_authorised_project_ids();
		$query = "SELECT id, budget FROM {$wpdb->prefix}an_projects";
		if ( is_array($authorised_ids) ) {
			if ( empty($authorised_ids) ) {
				return; // Nothing to show
			}
			$query .= " WHERE id IN (" . implode(',', array_map('intval', $authorised_ids)) . ")";
		}

		$projects = $wpdb->get_results( $query );

		$total_budget   = 0;
		$total_labor    = 0;
		$total_expenses = 0;

		foreach ( $projects as $project ) {
			$profit = $this->calculate_project_profitability( $project->id );
			$total_budget   += $profit['budget'];
			$total_labor    += $profit['labor_cost'];
			$total_expenses += $profit['expenses'];
		}

		$total_profit = $total_budget - $total_labor - $total_expenses;
		$color = $total_profit >= 0 ? '#46b450' : '#dc3232';

		?>
		<div class="postbox" style="padding: 20px;">
			<h2><?php _e( 'MoneyFlow', 'agency-nexus' ); ?></h2>
			<p><?php _e( 'Real-time Profitability (All Projects):', 'agency-nexus' ); ?></p>
			<div style="font-size: 24px; font-weight: bold; color: <?php echo $color; ?>;">
				$<?php echo number_format( floatval($total_profit), 2 ); ?>
			</div>
			<p><small>
				<?php echo sprintf( __( 'Revenue: $%.2f | Labor Cost: $%.2f', 'agency-nexus' ), $total_budget, $total_labor ); ?>
			</small></p>
			<p><small>
				<?php
					$all_profit = $this->calculate_project_profitability(0); // Calculate for all
					echo sprintf( __( 'Est. Tax (25%%): $%.2f | Net: $%.2f', 'agency-nexus' ), $total_profit * 0.25, $total_profit * 0.75 );
				?>
			</small></p>
			<p><a href="<?php echo admin_url('admin.php?page=an-projects'); ?>"><?php _e( 'Manage Projects', 'agency-nexus' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * AJAX handler for drafting invoice payment reminder using AI.
	 */
	public function handle_ai_invoice_reminder() {
		if ( ! Agency_Nexus_Permissions::can_access_nexus() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$invoice_number = isset($_POST['invoice_number']) ? sanitize_text_field($_POST['invoice_number']) : '';
		if ( empty($invoice_number) ) {
			wp_send_json_error( 'Missing parameters.' );
		}

		global $wpdb;
		$invoice = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}an_invoices WHERE number = %s", $invoice_number ) );
		$client_name = $invoice ? $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}an_clients WHERE id = %d", $invoice->client_id ) ) : 'Client';

		$prompt = "Write a professional, friendly payment collection reminder email for invoice number \"" . $invoice_number . "\". The client's name is \"" . $client_name . "\". The email should be polite yet firm and clear.";
		$draft = Agency_Nexus_AI_Copilot::generate( $prompt, 'payment_reminder' );

		wp_send_json_success( [ 'draft' => $draft ] );
	}
}
