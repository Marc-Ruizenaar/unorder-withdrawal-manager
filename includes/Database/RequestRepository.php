<?php
/**
 * Read/write access to withdrawal request rows.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Database;

// Withdrawal rows use this plugin's custom table — intentional $wpdb CRUD without object cache wrappers.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * The withdrawal requests table (physical name {@see Schema::TABLE_REQUESTS}).
 */
final class RequestRepository {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Inserts a pending withdrawal request. `items` is stored as JSON (line item id => quantity).
	 *
	 * For guest orders, pass the consumed token in `$guest_token` so it is stored for audit.
	 *
	 * @param array<int|string, float> $items       Line item id => quantity to withdraw.
	 * @param string|null              $guest_token 64-char hex token (guest orders only), or null.
	 * @return int Inserted row id, or 0 on failure.
	 */
	public static function insert_request( int $order_id, int $user_id, array $items, ?string $reason, ?string $guest_token = null ): int {
		global $wpdb;

		$payload = array(
			'order_id'     => $order_id,
			'user_id'      => $user_id,
			'guest_token'  => $guest_token,
			'items'        => wp_json_encode( $items, JSON_UNESCAPED_UNICODE ),
			'reason'       => $reason,
			'status'       => self::STATUS_PENDING,
			'submitted_at' => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( Schema::get_requests_table_name(), $payload, $formats );

		if ( ! $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether any withdrawal request row exists for the order (any status).
	 */
	public static function order_has_request( int $order_id ): bool {
		global $wpdb;

		$table = Schema::get_requests_table_name();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM %i WHERE order_id = %d LIMIT 1',
				$table,
				$order_id
			)
		);

		return null !== $found;
	}

	/**
	 * Total quantity already committed per line item in pending or approved requests.
	 * Rejected requests do not count (customer can request those units again).
	 *
	 * @return array<int, float> Line item id => sum of quantities
	 */
	public static function get_committed_quantities_by_line( int $order_id ): array {
		global $wpdb;

		$table = Schema::get_requests_table_name();
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT items FROM %i WHERE order_id = %d AND status IN ( %s, %s )',
				$table,
				$order_id,
				self::STATUS_PENDING,
				self::STATUS_APPROVED
			)
		);
		$sums = array();
		foreach ( (array) $rows as $json ) {
			if ( ! is_string( $json ) || '' === $json ) {
				continue;
			}
			$decoded = json_decode( $json, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			foreach ( $decoded as $lid => $qty ) {
				$k = (int) $lid;
				$sums[ $k ] = ( $sums[ $k ] ?? 0.0 ) + (float) wc_format_decimal( is_scalar( $qty ) ? (string) $qty : '0' );
			}
		}
		return $sums;
	}

	/**
	 * For each line item, quantity still available for a new withdrawal (order line qty minus committed).
	 *
	 * @return array<int, float> Line item id => remaining quantity
	 */
	public static function get_remaining_quantities( \WC_Order $order ): array {
		$committed = self::get_committed_quantities_by_line( $order->get_id() );
		$out       = array();
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$line_id = (int) $item_id;
			$line_q  = (float) $item->get_quantity();
			$used    = (float) ( $committed[ $line_id ] ?? 0.0 );
			$rem     = $line_q - $used;
			if ( $rem < 0.0 ) {
				$rem = 0.0;
			}
			$q_is_whole = abs( $line_q - (float) (int) round( $line_q ) ) < 0.000001;
			if ( $q_is_whole && $line_q >= 0.5 ) {
				$rem = (float) max( 0, (int) round( $rem ) );
			} else {
				$rem = max( 0.0, round( $rem, 4 ) );
			}
			$out[ $line_id ] = $rem;
		}
		return $out;
	}

	/**
	 * True if the customer can start at least one new withdrawal (some line has remaining quantity).
	 */
	public static function order_has_any_withdrawable_quantity( \WC_Order $order ): bool {
		foreach ( self::get_remaining_quantities( $order ) as $q ) {
			if ( $q > 0.0000001 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Single request row, or null if not found.
	 *
	 * @return array<string, string|int|float|bool|null>|null
	 */
	public static function get_by_id( int $id ): ?array {
		global $wpdb;
		$table = Schema::get_requests_table_name();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) || ! isset( $row['id'] ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Counts grouped by status (pending, approved, rejected only).
	 *
	 * @return array<string, int> status => count
	 */
	public static function count_by_status(): array {
		global $wpdb;
		$table = Schema::get_requests_table_name();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS c FROM %i GROUP BY status',
				$table
			),
			ARRAY_A
		);
		$out  = array(
			self::STATUS_PENDING  => 0,
			self::STATUS_APPROVED => 0,
			self::STATUS_REJECTED => 0,
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['status'], $row['c'] ) ) {
					continue;
				}
				$s = (string) $row['status'];
				if ( isset( $out[ $s ] ) ) {
					$out[ $s ] = (int) $row['c'];
				}
			}
		}
		return $out;
	}

	/**
	 * Total rows in the table (any status).
	 */
	public static function count_all(): int {
		global $wpdb;
		$table = Schema::get_requests_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				$table
			)
		);
	}

	/**
	 * Paged list for admin.
	 *
	 * @param 'all'|'pending'|'approved'|'rejected' $status_filter
	 * @return array{ items: list<array<string, mixed>>, total: int }
	 */
	public static function get_list( string $status_filter, int $per_page, int $paged, string $orderby, string $order ): array {
		global $wpdb;
		$table           = Schema::get_requests_table_name();
		$allowed_orderby = array( 'id' => 'id', 'submitted_at' => 'submitted_at' );
		$ob              = $allowed_orderby[ $orderby ] ?? 'id';
		$dir             = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';
		$per_page        = max( 1, min( 200, $per_page ) );
		$offset          = ( max( 1, $paged ) - 1 ) * $per_page;

		$status_filtered = in_array( $status_filter, array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED ), true );

		if ( $status_filtered ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s',
					$table,
					$status_filter
				)
			);
			$rows = self::get_list_rows(
				$wpdb,
				$table,
				true,
				$status_filter,
				$ob,
				$dir,
				$per_page,
				$offset
			);
		} else {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE 1=1',
					$table
				)
			);
			$rows = self::get_list_rows(
				$wpdb,
				$table,
				false,
				'',
				$ob,
				$dir,
				$per_page,
				$offset
			);
		}
		$items    = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					$items[] = $row;
				}
			}
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Paged SELECT for admin list (ORDER BY uses %i; direction is literal ASC/DESC only).
	 *
	 * @param \wpdb $wpdb WordPress database abstraction object.
	 * @param 'ASC'|'DESC' $dir Sort direction.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_list_rows(
		\wpdb $wpdb,
		string $table,
		bool $status_filtered,
		string $status_filter,
		string $order_column,
		string $dir,
		int $per_page,
		int $offset
	): array {
		$asc = 'ASC' === $dir;
		if ( $status_filtered ) {
			if ( $asc ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE status = %s ORDER BY %i ASC LIMIT %d OFFSET %d',
						$table,
						$status_filter,
						$order_column,
						$per_page,
						$offset
					),
					ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE status = %s ORDER BY %i DESC LIMIT %d OFFSET %d',
						$table,
						$status_filter,
						$order_column,
						$per_page,
						$offset
					),
					ARRAY_A
				);
			}
		} elseif ( $asc ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 ORDER BY %i ASC LIMIT %d OFFSET %d',
					$table,
					$order_column,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 ORDER BY %i DESC LIMIT %d OFFSET %d',
					$table,
					$order_column,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Set status to approved or rejected. Only from pending; optional note stored for reject.
	 */
	public static function set_status( int $id, string $new_status, ?string $decision_note = null ): bool {
		$new_status = self::normalise_status( $new_status );
		if ( null === $new_status ) {
			return false;
		}

		$row = self::get_by_id( $id );
		if ( null === $row ) {
			return false;
		}
		$st = isset( $row['status'] ) ? strtolower( trim( (string) $row['status'] ) ) : '';
		if ( self::STATUS_PENDING !== $st ) {
			return false;
		}

		return self::force_set_status( $id, $new_status, $decision_note );
	}

	/**
	 * @param list<int> $ids
	 */
	public static function set_status_bulk( array $ids, string $new_status, ?string $decision_note = null ): int {
		$new_status = self::normalise_status( $new_status );
		if ( null === $new_status || count( $ids ) < 1 ) {
			return 0;
		}

		$done = 0;
		foreach ( $ids as $id ) {
			$rid = absint( (string) $id );
			if ( $rid < 1 ) {
				continue;
			}
			if ( self::set_status( $rid, $new_status, $decision_note ) ) {
				++$done;
			}
		}
		return $done;
	}

	/**
	 * @return self::STATUS_*|null
	 */
	private static function normalise_status( string $s ): ?string {
		$s = strtolower( trim( $s ) );
		if ( self::STATUS_APPROVED === $s || self::STATUS_REJECTED === $s ) {
			return $s;
		}
		return null;
	}

	/**
	 * Direct status update (after pending check) for a single id.
	 */
	private static function force_set_status( int $id, string $new_status, ?string $decision_note = null ): bool {
		global $wpdb;

		$now = current_time( 'mysql' );
		$note = self::STATUS_REJECTED === $new_status
			? ( is_string( $decision_note ) ? $decision_note : '' )
			: '';

		$payload = array(
			'status'        => $new_status,
			'processed_at'  => $now,
			'decision_note' => $note,
		);
		$formats = array( '%s', '%s', '%s' );

		$updated = $wpdb->update(
			Schema::get_requests_table_name(),
			$payload,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return is_int( $updated ) && $updated > 0;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
