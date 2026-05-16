<?php
/**
 * List table: withdrawal requests.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Admin;

use UnOrder\Database\RequestRepository;
use WC_Order;
use WP_List_Table;

/**
 * Renders the withdrawal request admin list.
 */
final class WithdrawalRequestsListTable extends WP_List_Table {

	/**
	 * Up to this length (Unicode-safe), the full reason is shown in the table cell. Longer: truncated + modal.
	 */
	private const REASON_INLINE_MAX_CHARS = 150;

	/**
	 * Request id => full text for the read-only admin modal (long reasons only; avoids fragile JSON in HTML attributes).
	 *
	 * @var array<int, string>
	 */
	private array $modal_reasons_by_id = array();

	/**
	 * Counts per status for view tabs.
	 *
	 * @var array<string, int>
	 */
	private array $status_counts = array();

	/**
	 * Current view: all, actions (pending — approve/reject), approved, rejected. Legacy: pending.
	 */
	private string $status_filter = 'all';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'unordw_withdrawal_request',
				'plural'   => 'unordw_withdrawal_requests',
				'ajax'     => false,
				'screen'   => 'woocommerce_page_un-order-withdrawals',
			)
		);
		$this->status_counts = RequestRepository::count_by_status();
		$allowed             = array(
			'all',
			'actions',
			RequestRepository::STATUS_PENDING,
			RequestRepository::STATUS_APPROVED,
			RequestRepository::STATUS_REJECTED,
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status tab filter; screen requires manage_woocommerce.
		$status              = isset( $_GET['status'] ) ? sanitize_key( (string) wp_unslash( (string) $_GET['status'] ) ) : 'all';
		$this->status_filter = in_array( $status, $allowed, true ) ? $status : 'all';
	}

	/**
	 * Output filter tabs (status) with total counts.
	 *
	 * @return array<string, string>
	 */
	public function get_views() {
		$base = admin_url( 'admin.php' );
		$c    = $this->status_counts;
		$all  = RequestRepository::count_all();
		$st   = $this->status_filter;

		$make = function ( string $key, string $label, int $n ) use ( $base, $st ) {
			$url = add_query_arg( 'page', 'un-order-withdrawals', $base );
			$url = 'all' === $key ? $url : add_query_arg( 'status', $key, $url );
			$is_current = ( $st === $key );
			if ( 'actions' === $key ) {
				$is_current = ( 'actions' === $st || RequestRepository::STATUS_PENDING === $st );
			}
			$class = $is_current ? ' class="current"' : '';
			return sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( $url ), $class, $label, $n );
		};

		$pending_n = $c[ RequestRepository::STATUS_PENDING ];
		$views     = array(
			'all'      => $make( 'all', esc_html__( 'All', 'un-order' ), $all ),
			'actions'  => $make( 'actions', esc_html__( 'Actions', 'un-order' ), $pending_n ),
			'approved' => $make( RequestRepository::STATUS_APPROVED, esc_html__( 'Approved', 'un-order' ), $c[ RequestRepository::STATUS_APPROVED ] ),
			'rejected' => $make( RequestRepository::STATUS_REJECTED, esc_html__( 'Rejected', 'un-order' ), $c[ RequestRepository::STATUS_REJECTED ] ),
		);
		return $views;
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'id'           => esc_html__( 'Request ID', 'un-order' ),
			'order'        => esc_html__( 'Order', 'un-order' ),
			'customer'     => esc_html__( 'Customer', 'un-order' ),
			'items_text'   => esc_html__( 'Items withdrawn', 'un-order' ),
			'reason'       => esc_html__( 'Withdrawal reason', 'un-order' ),
			'submitted_at' => esc_html__( 'Submitted', 'un-order' ),
			'status'       => esc_html__( 'Status', 'un-order' ),
			'row_actions'  => esc_html__( 'Actions', 'un-order' ),
		);
	}

	/**
	 * @return array<string, list<string|bool>>
	 */
	public function get_sortable_columns() {
		return array(
			'id'           => array( 'id', true ),
			'submitted_at' => array( 'submitted_at', true ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_bulk_actions() {
		if (
			'all' === $this->status_filter
			|| 'actions' === $this->status_filter
			|| RequestRepository::STATUS_PENDING === $this->status_filter
		) {
			return array(
				'approve' => esc_html__( 'Approve', 'un-order' ),
				'reject'  => esc_html__( 'Reject', 'un-order' ),
			);
		}
		return array();
	}

	/**
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only WP_List_Table sort keys; capability-checked on render.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( (string) wp_unslash( (string) $_GET['orderby'] ) ) : 'submitted_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same as above.
		$order_raw = isset( $_GET['order'] ) ? sanitize_text_field( (string) wp_unslash( (string) $_GET['order'] ) ) : 'desc';
		$order     = 'asc' === strtolower( $order_raw ) ? 'asc' : 'desc';

		$status_arg = 'all' === $this->status_filter ? 'all' : $this->status_filter;
		if ( 'actions' === $status_arg ) {
			$status_arg = RequestRepository::STATUS_PENDING;
		}
		$found = RequestRepository::get_list( $status_arg, $per_page, $current_page, 'submitted_at' === $orderby ? 'submitted_at' : 'id', $order );
		$this->items = $found['items'];
		$total        = $found['total'];
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * @param array<string, mixed> $item Row.
	 */
	public function column_id( $item ): string {
		$id = isset( $item['id'] ) ? absint( (string) $item['id'] ) : 0;
		return (string) $id;
	}

	/**
	 * @param array<string, mixed> $item Row.
	 */
	public function column_order( $item ): string {
		$order_id = isset( $item['order_id'] ) ? absint( (string) $item['order_id'] ) : 0;
		if ( $order_id < 1 ) {
			return '—';
		}
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$num  = $order->get_order_number();
			$href = $this->order_admin_url( $order_id );
			return '<a href="' . esc_url( $href ) . '">#' . esc_html( (string) $num ) . '</a>';
		}
		return '<span>' . esc_html( (string) $order_id ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $item Row.
	 */
	public function column_customer( $item ): string {
		$order_id = isset( $item['order_id'] ) ? absint( (string) $item['order_id'] ) : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;
		if ( $order instanceof WC_Order ) {
			return esc_html( $order->get_formatted_billing_full_name() );
		}
		$uid = isset( $item['user_id'] ) ? absint( (string) $item['user_id'] ) : 0;
		if ( $uid > 0 ) {
			$u = get_userdata( $uid );
			if ( $u ) {
				return esc_html( $u->display_name );
			}
		}
		return '—';
	}

	/**
	 * @param array<string, mixed> $item Row.
	 */
	public function column_items_text( $item ): string {
		$order_id = isset( $item['order_id'] ) ? absint( (string) $item['order_id'] ) : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;
		$raw      = isset( $item['items'] ) && is_string( $item['items'] ) ? $item['items'] : '';
		if ( ! $order instanceof WC_Order || '' === $raw ) {
			return '—';
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || count( $decoded ) < 1 ) {
			return '—';
		}
		$parts = array();
		$i     = 0;
		foreach ( $decoded as $line_id => $qty ) {
			++$i;
			if ( $i > 3 ) {
				$rest = count( $decoded ) - 3;
				/* translators: %d: number of items not shown */
				$parts[] = sprintf( esc_html__( '… and %d more', 'un-order' ), (int) $rest );
				break;
			}
			$line = $order->get_item( (int) $line_id );
			$nm   = ( $line && is_object( $line ) && method_exists( $line, 'get_name' ) ) ? (string) $line->get_name() : (string) $line_id;
			$q    = (float) wc_format_decimal( is_scalar( $qty ) ? (string) $qty : '0' );
			$parts[] = esc_html( $nm . ' × ' . (string) $q );
		}
		return implode( ', ', $parts );
	}

	/**
	 * Customer’s optional explanation (from the withdrawal form).
	 *
	 * @param array<string, mixed> $item Row.
	 */
	public function column_reason( $item ): string {
		$raw = $item['reason'] ?? null;
		$r   = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $r ) {
			return '—';
		}

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $r, 'UTF-8' ) : strlen( $r );
		if ( $len <= self::REASON_INLINE_MAX_CHARS ) {
			return nl2br( esc_html( $r ), true );
		}

		$request_id = isset( $item['id'] ) ? absint( (string) $item['id'] ) : 0;
		if ( $request_id > 0 ) {
			$this->modal_reasons_by_id[ $request_id ] = $r;
		}

		$single = (string) preg_replace( '/\s+/u', ' ', $r );
		$cut    = function_exists( 'mb_substr' )
			? mb_substr( $single, 0, self::REASON_INLINE_MAX_CHARS, 'UTF-8' )
			: substr( $single, 0, self::REASON_INLINE_MAX_CHARS );
		$preview = $cut . '…';

		return sprintf(
			'<button type="button" class="button-link un-order-reason-open" data-reason-id="%1$d" aria-haspopup="dialog" aria-label="%2$s" title="%2$s">%3$s</button>',
			$request_id,
			esc_attr( __( 'View full withdrawal reason', 'un-order' ) ),
			esc_html( $preview )
		);
	}

	/**
	 * Full long reasons keyed by request id, for the admin modal (printed as JSON in a script tag on the list screen).
	 *
	 * @return array<int, string>
	 */
	public function get_modal_reasons_for_script(): array {
		return $this->modal_reasons_by_id;
	}

	/**
	 * @param array<string, mixed> $item Row.
	 */
	public function column_submitted_at( $item ): string {
		$s = isset( $item['submitted_at'] ) && is_string( $item['submitted_at'] ) ? $item['submitted_at'] : '';
		if ( '' === $s ) {
			return '—';
		}
		return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $s ) );
	}

	/**
	 * @param array<string, mixed> $item Row.
	 */
	public function column_status( $item ): string {
		$st = isset( $item['status'] ) ? (string) $item['status'] : '';
		return $this->format_status( $st );
	}

	/**
	 * Actions column: Approve + Reject buttons for pending rows.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	public function column_row_actions( $item ): string {
		$id = isset( $item['id'] ) ? absint( (string) $item['id'] ) : 0;
		$st = isset( $item['status'] ) ? (string) $item['status'] : '';

		if ( $id < 1 || RequestRepository::STATUS_PENDING !== $st ) {
			return '—';
		}

		$approve = sprintf(
			'<a href="%s" class="button button-small un-order-btn-approve">%s</a>',
			esc_url( $this->approve_url( $id ) ),
			esc_html__( 'Approve', 'un-order' )
		);

		$reject = sprintf(
			'<a href="#" class="button button-small un-order-btn-reject un-order-open-reject" data-request-id="%s">%s</a>',
			esc_attr( (string) $id ),
			esc_html__( 'Reject', 'un-order' )
		);

		return '<div class="un-order-row-actions">' . $approve . ' ' . $reject . '</div>';
	}

	/**
	 * Approve URL with nonce.
	 */
	private function approve_url( int $request_id ): string {
		$base = add_query_arg(
			array(
				'page'            => WithdrawalRequestsPage::SLUG,
				'unordw_action' => 'approve',
				'request_id'      => $request_id,
			),
			admin_url( 'admin.php' )
		);
		return wp_nonce_url( $base, 'unordw_withdrawal_approve_' . $request_id );
	}

	/**
	 * Order edit URL (WooCommerce-aware).
	 */
	private function order_admin_url( int $order_id ): string {
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			return $order->get_edit_order_url();
		}
		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	/**
	 * Human-readable status label.
	 */
	private function format_status( string $st ): string {
		$map = array(
			RequestRepository::STATUS_PENDING  => esc_html__( 'Pending', 'un-order' ),
			RequestRepository::STATUS_APPROVED => esc_html__( 'Approved', 'un-order' ),
			RequestRepository::STATUS_REJECTED => esc_html__( 'Rejected', 'un-order' ),
		);
		return $map[ $st ] ?? esc_html( $st );
	}

	/**
	 * Show bulk Approve / Reject even when the list is empty (core only prints bulk when has_items()).
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	protected function display_tablenav( $which ) {
		if ( 'bottom' === $which && ! $this->has_items() ) {
			return;
		}
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}
		$choices   = $this->get_bulk_actions();
		$show_bulk = ! empty( $choices ) && ( $this->has_items() || ( 'top' === $which && ! $this->has_items() ) );
		?>
	<div class="tablenav <?php echo esc_attr( $which ); ?>">

		<?php if ( $show_bulk ) : ?>
		<div class="alignleft actions bulkactions">
			<?php $this->bulk_actions( $which ); ?>
		</div>
		<?php endif; ?>
		<?php
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		?>

		<br class="clear" />
	</div>
		<?php
	}

	/**
	 * Helper line on the Actions tab (pending queue).
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$on_actions = 'actions' === $this->status_filter || RequestRepository::STATUS_PENDING === $this->status_filter;
		if ( ! $on_actions ) {
			return;
		}
		echo '<div class="un-order-withdrawal-actions-hint alignleft actions"><p class="description">';
		esc_html_e( 'Select one or more requests, then use the bulk action Approve or Reject. You can also use Approve or Reject on each row.', 'un-order' );
		echo '</p></div>';
	}

	/**
	 * Empty state text.
	 */
	public function no_items(): void {
		esc_html_e( 'No withdrawal requests found.', 'un-order' );
	}
}
