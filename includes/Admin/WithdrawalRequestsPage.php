<?php
/**
 * WooCommerce → Withdrawal Requests admin screen.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Admin;

defined( 'ABSPATH' ) || exit;

use UnOrder\Database\RequestRepository;
use UnOrder\Database\Schema;

/**
 * Registers the submenu, assets, and request handlers.
 */
final class WithdrawalRequestsPage {

	public const SLUG    = 'un-order-withdrawals';
	public const JS_HANDLE = 'un-order-withdrawal-requests';

	/**
	 * Approve / reject via admin-post.php — register always so {@see has_action()} is not false (WP returns 400).
	 */
	public function register_request_handlers(): void {
		// Approve via admin.php (admin_init) — avoids admin-post.php empty HTTP 400 if admin_post_{action} is not registered.
		add_action( 'admin_init', array( $this, 'maybe_handle_approve_from_admin_screen' ), 1 );
		// Back-compat for bookmarked / old Approve links that still hit admin-post.php.
		add_action( 'admin_post_un_order_approve_withdrawal', array( $this, 'handle_post_approve' ) );
		add_action( 'admin_post_un_order_reject_withdrawal', array( $this, 'handle_post_reject' ) );
	}

	/**
	 * WooCommerce submenu and list screen (only when WC is active).
	 */
	public function register_woocommerce_screens(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 60 );
		add_action( 'load-woocommerce_page_' . self::SLUG, array( $this, 'on_load_page' ) );
	}

	/**
	 * Submenu under WooCommerce.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Withdrawal Requests', 'un-order' ),
			__( 'Withdrawal Requests', 'un-order' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue and bulk process before the list renders.
	 */
	public function on_load_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		\UnOrder\Database\Schema::maybe_upgrade();
		$this->process_bulk();
	}

	/**
	 * Scripts for reject modal and bulk-reject.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'woocommerce_page_un-order-withdrawals' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'un-order-withdrawal-requests',
			UN_ORDER_PLUGIN_URL . 'assets/css/admin-withdrawal-requests.css',
			array(),
			UN_ORDER_VERSION
		);
		wp_enqueue_script(
			self::JS_HANDLE,
			UN_ORDER_PLUGIN_URL . 'assets/js/admin-withdrawal-requests.js',
			array( 'jquery' ),
			UN_ORDER_VERSION,
			true
		);
	}

	/**
	 * Approve: GET to admin.php (see {@see WithdrawalRequestsListTable::approve_url()}), processed on admin_init.
	 */
	public function maybe_handle_approve_from_admin_screen(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'GET' !== strtoupper( $method ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in run_approve_from_query() via wp_verify_nonce().
		if ( ! isset( $_GET['page'], $_GET['un_order_action'] ) || ! isset( $_GET['request_id'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same as above.
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( (string) $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same as above.
		$act = isset( $_GET['un_order_action'] ) ? sanitize_key( (string) wp_unslash( (string) $_GET['un_order_action'] ) ) : '';
		if ( self::SLUG !== $page || 'approve' !== $act ) {
			return;
		}
		$this->run_approve_from_query();
	}

	/**
	 * Approve (GET to admin-post.php + nonce) — legacy; delegates to the same flow as admin.php.
	 */
	public function handle_post_approve(): void {
		$this->run_approve_from_query();
	}

	/**
	 * Shared approval flow (query args: request_id, _wpnonce).
	 */
	private function run_approve_from_query(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_die( esc_html__( 'WooCommerce is required for this action.', 'un-order' ) );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'un-order' ) );
		}
		Schema::maybe_upgrade();
		$request_id = isset( $_REQUEST['request_id'] ) ? absint( (string) wp_unslash( (string) $_REQUEST['request_id'] ) ) : 0;
		$nonce      = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( (string) wp_unslash( (string) $_REQUEST['_wpnonce'] ) ) : '';
		if ( $request_id < 1 || ! wp_verify_nonce( $nonce, 'un_order_withdrawal_approve_' . $request_id ) ) {
			wp_die( esc_html__( 'Invalid or expired link.', 'un-order' ) );
		}
		if ( ! $this->approve_one( $request_id ) ) {
			$this->redirect_url( $this->get_success_redirect( 'un_order_msg', 'approve_failed' ) );
		}
		$this->redirect_url( $this->get_success_redirect( 'un_order_msg', 'approved' ) );
	}

	/**
	 * Reject from modal (POST to admin-post.php).
	 */
	public function handle_post_reject(): void {
		if ( ! class_exists( \WooCommerce::class ) ) {
			wp_die( esc_html__( 'WooCommerce is required for this action.', 'un-order' ) );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'un-order' ) );
		}
		Schema::maybe_upgrade();
		check_admin_referer( 'un_order_reject_withdrawal' );

		$request_id = isset( $_POST['request_id'] ) ? absint( (string) wp_unslash( $_POST['request_id'] ) ) : 0;
		$reason     = isset( $_POST['un_order_reject_reason'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['un_order_reject_reason'] ) ) : '';

		if ( $request_id < 1 ) {
			wp_die( esc_html__( 'Invalid request.', 'un-order' ) );
		}

		if ( ! $this->reject_one( $request_id, $reason ) ) {
			$this->redirect_url( $this->get_success_redirect( 'un_order_msg', 'reject_failed' ) );
		}
		$this->redirect_url( $this->get_success_redirect( 'un_order_msg', 'rejected' ) );
	}

	/**
	 * Bulk actions from the list table form.
	 */
	private function process_bulk(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== strtoupper( $method ) || ! isset( $_POST['un_order_withdrawal_bulk'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		check_admin_referer( 'bulk-un_order_withdrawal_requests' );

		$post_action = isset( $_POST['action'] ) ? sanitize_text_field( (string) wp_unslash( (string) $_POST['action'] ) ) : '';
		$action      = ( '' !== $post_action && '-1' !== $post_action ) ? sanitize_key( $post_action ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $action ) {
			$post_action2 = isset( $_POST['action2'] ) ? sanitize_text_field( (string) wp_unslash( (string) $_POST['action2'] ) ) : '';
			if ( '' !== $post_action2 && '-1' !== $post_action2 ) {
				$action = sanitize_key( $post_action2 );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = isset( $_POST['un_order_withdrawal_requests'] ) && is_array( $_POST['un_order_withdrawal_requests'] ) ? array_map( 'absint', $_POST['un_order_withdrawal_requests'] ) : array();
		$ids = array_values( array_filter( $ids ) );
		if ( $ids === array() || ( 'approve' !== $action && 'reject' !== $action ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reason = isset( $_POST['un_order_bulk_reject_reason'] ) ? sanitize_textarea_field( (string) wp_unslash( (string) $_POST['un_order_bulk_reject_reason'] ) ) : '';

		$base = $this->get_list_page_url_with_referer_status();

		if ( 'reject' === $action ) {
			$done = 0;
			foreach ( $ids as $rid ) {
				if ( $this->reject_one( (int) $rid, $reason ) ) {
					++$done;
				}
			}
			$this->redirect_url( add_query_arg( 'bulk_rejected', (string) $done, $base ) );
		} else {
			$done = 0;
			foreach ( $ids as $rid ) {
				if ( $this->approve_one( (int) $rid ) ) {
					++$done;
				}
			}
			$this->redirect_url( add_query_arg( 'bulk_approved', (string) $done, $base ) );
		}
	}

	/**
	 * @return bool Whether the status was updated and the customer email triggered.
	 */
	private function approve_one( int $request_id ): bool {
		if ( ! RequestRepository::set_status( $request_id, RequestRepository::STATUS_APPROVED, null ) ) {
			return false;
		}
		// Send email after redirect (shutdown) so a mail/template error does not block the response.
		$rid = $request_id;
		add_action(
			'shutdown',
			static function () use ( $rid ) {
				do_action( 'un_order_withdrawal_approved', $rid );
			},
			1
		);
		return true;
	}

	/**
	 * @return bool Whether the status was updated and the customer email triggered.
	 */
	private function reject_one( int $request_id, string $reason ): bool {
		if ( ! RequestRepository::set_status( $request_id, RequestRepository::STATUS_REJECTED, $reason ) ) {
			return false;
		}
		$rid = $request_id;
		add_action(
			'shutdown',
			static function () use ( $rid ) {
				do_action( 'un_order_withdrawal_rejected', $rid );
			},
			1
		);
		return true;
	}

	/**
	 * @return void
	 */
	private function redirect_url( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * List table URL, preserving status/paged from the referer when it points at this screen.
	 */
	private function get_list_page_url_with_referer_status(): string {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		$ref = wp_get_referer();
		if ( ! is_string( $ref ) || '' === $ref ) {
			return $url;
		}
		$host = wp_parse_url( $ref, PHP_URL_HOST );
		$here = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( $host && $here && 0 !== strcasecmp( (string) $host, (string) $here ) ) {
			return $url;
		}
		$q = wp_parse_url( $ref, PHP_URL_QUERY );
		if ( ! is_string( $q ) || '' === $q ) {
			return $url;
		}
		parse_str( $q, $args );
		if ( ! is_array( $args ) ) {
			return $url;
		}
		if ( isset( $args['status'] ) && is_scalar( $args['status'] ) ) {
			$url = add_query_arg( 'status', sanitize_key( (string) $args['status'] ), $url );
		}
		if ( isset( $args['paged'] ) ) {
			$p = absint( (string) $args['paged'] );
			if ( $p > 1 ) {
				$url = add_query_arg( 'paged', (string) $p, $url );
			}
		}
		return $url;
	}

	/**
	 * Safe redirect to the list with a success query arg.
	 *
	 * @param string $key Query key (e.g. un_order_msg).
	 * @param string $val Unencoded value.
	 */
	private function get_success_redirect( string $key, string $val ): string {
		return add_query_arg( $key, $val, $this->get_list_page_url_with_referer_status() );
	}

	/**
	 * Render the list table.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'un-order' ) );
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		/*
		 * Nonce verification is not required for these GET parameters: they only drive dismissible
		 * notices and hidden form defaults on this screen, which is already gated by current_user_can().
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['un_order_msg'] ) ) {
			$this->render_notices( sanitize_key( (string) wp_unslash( (string) $_GET['un_order_msg'] ) ) );
		}
		if ( ! empty( $_GET['bulk_approved'] ) ) {
			$bulk_appr = absint( wp_unslash( (string) $_GET['bulk_approved'] ) );
			/* translators: %s: number of requests */
			printf( '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Approved %s request(s).', 'un-order' ) . '</p></div>', esc_html( (string) $bulk_appr ) );
		}
		if ( ! empty( $_GET['bulk_rejected'] ) ) {
			$bulk_rej = absint( wp_unslash( (string) $_GET['bulk_rejected'] ) );
			/* translators: %s: number of requests */
			printf( '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rejected %s request(s).', 'un-order' ) . '</p></div>', esc_html( (string) $bulk_rej ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$table = new WithdrawalRequestsListTable();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawal requests', 'un-order' ); ?></h1>
			<hr class="wp-header-end" />
			<?php
			?>
			<form id="un-order-withdrawal-requests" method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<?php
				$table->views();
				// Bulk nonce: wp_nonce_field( 'bulk-un_order_withdrawal_requests' ) is output by $table->display() (tablenav top).
				echo '<input type="hidden" name="page" value="un-order-withdrawals" />';
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Hidden fields mirroring read-only list filters (capability-checked above).
				if ( isset( $_GET['status'] ) ) {
					echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( (string) wp_unslash( (string) $_GET['status'] ) ) ) . '" />';
				}
				$search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( (string) $_GET['s'] ) ) : '';
				if ( '' !== $search ) {
					echo '<input type="hidden" name="s" value="' . esc_attr( $search ) . '" />';
				}
				if ( ! empty( $_GET['paged'] ) ) {
					echo '<input type="hidden" name="paged" value="' . esc_attr( (string) absint( wp_unslash( (string) $_GET['paged'] ) ) ) . '" />';
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
				?>
				<input type="hidden" name="un_order_withdrawal_bulk" value="1" />
				<input type="hidden" name="un_order_bulk_reject_reason" id="un_order_bulk_reject_reason" value="" />
				<?php
				$table->display();
				?>
			</form>
			<?php
			$this->print_withdrawal_reason_data_script( $table );
			$this->render_reject_form();
			$this->render_reason_view_dialog();
			?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	private function render_notices( string $msg_key ): void {
		$messages = array(
			'approved'      => array(
				'class'   => 'notice-success',
				'message' => __( 'The withdrawal was approved. The customer has been notified.', 'un-order' ),
			),
			'rejected'      => array(
				'class'   => 'notice-success',
				'message' => __( 'The withdrawal was rejected. The customer has been notified.', 'un-order' ),
			),
			'approve_failed' => array(
				'class'   => 'notice-error',
				'message' => __( 'The withdrawal could not be approved. It may no longer be pending, or the database could not be updated. Try again or check that the plugin database is up to date.', 'un-order' ),
			),
			'reject_failed'  => array(
				'class'   => 'notice-error',
				'message' => __( 'The withdrawal could not be rejected. It may no longer be pending, or the database could not be updated. Try again or check that the plugin database is up to date.', 'un-order' ),
			),
		);
		if ( isset( $messages[ $msg_key ] ) ) {
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $msg_key ]['class'] ),
				esc_html( $messages[ $msg_key ]['message'] )
			);
		}
	}

	/**
	 * Injects `window.unOrderWithdrawalReasons` (request id => full text) for the long-reason modal.
	 * Must run after the list table is displayed so the map is complete.
	 */
	private function print_withdrawal_reason_data_script( WithdrawalRequestsListTable $table ): void {
		$map = $table->get_modal_reasons_for_script();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is safe in script; flags escape to JS string literals.
		printf(
			'<script>window.unOrderWithdrawalReasons = %s;</script>' . "\n",
			wp_json_encode( $map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Hidden form posted to admin-post for single reject from modal.
	 */
	private function render_reject_form(): void {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div id="un-order-reject-dialog" class="un-order-reject-dialog" hidden>
			<div class="un-order-reject-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="un-order-reject-title">
				<h2 id="un-order-reject-title"><?php esc_html_e( 'Reject withdrawal', 'un-order' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The customer will receive this message by email.', 'un-order' ); ?></p>
				<div id="un-order-reject-single">
					<form id="un-order-reject-form" method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
						<?php wp_nonce_field( 'un_order_reject_withdrawal' ); ?>
						<input type="hidden" name="action" value="un_order_reject_withdrawal" />
						<input type="hidden" name="request_id" id="un-order-reject-request-id" value="" />
						<p>
							<label for="un_order_reject_reason" class="screen-reader-text"><?php esc_html_e( 'Reason', 'un-order' ); ?></label>
							<textarea name="un_order_reject_reason" id="un_order_reject_reason" class="large-text" rows="5" required></textarea>
						</p>
						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Reject and notify customer', 'un-order' ); ?></button>
							<button type="button" class="button" id="un-order-reject-cancel"><?php esc_html_e( 'Cancel', 'un-order' ); ?></button>
						</p>
					</form>
				</div>
				<div id="un-order-reject-bulk" class="un-order-reject-bulk" hidden>
					<p>
						<label for="un_order_bulk_reject_textarea" class="screen-reader-text"><?php esc_html_e( 'Reason (bulk)', 'un-order' ); ?></label>
						<textarea id="un_order_bulk_reject_textarea" class="large-text" rows="5"></textarea>
					</p>
					<p class="submit">
						<button type="button" class="button button-primary" id="un-order-bulk-reject-apply"><?php esc_html_e( 'Reject and notify', 'un-order' ); ?></button>
						<button type="button" class="button" id="un-order-bulk-reject-cancel"><?php esc_html_e( 'Cancel', 'un-order' ); ?></button>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Read-only modal for long customer withdrawal reason text (triggered from the list table).
	 */
	private function render_reason_view_dialog(): void {
		?>
		<div id="un-order-reason-dialog" class="un-order-reason-dialog" hidden>
			<div class="un-order-reason-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="un-order-reason-title" tabindex="-1">
				<h2 id="un-order-reason-title"><?php esc_html_e( 'Withdrawal reason', 'un-order' ); ?></h2>
				<div id="un-order-reason-body" class="un-order-reason-dialog__body"></div>
				<p class="submit">
					<button type="button" class="button button-primary" id="un-order-reason-close"><?php esc_html_e( 'Close', 'un-order' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}
}
