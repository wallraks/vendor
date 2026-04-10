<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CVT_Payouts_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'payout',
			'plural'   => 'payouts',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'vendor_name'     => __( 'Vendor', 'corido-vendor-tracker' ),
			'item_title'      => __( 'Item', 'corido-vendor-tracker' ),
			'selling_price'   => __( 'Sale Price', 'corido-vendor-tracker' ),
			'commission_rate' => __( 'Rate', 'corido-vendor-tracker' ),
			'commission_amount' => __( 'Commission', 'corido-vendor-tracker' ),
			'payout_amount'   => __( 'Vendor Payout', 'corido-vendor-tracker' ),
			'status'          => __( 'Status', 'corido-vendor-tracker' ),
			'created_at'      => __( 'Created', 'corido-vendor-tracker' ),
			'actions'         => __( 'Actions', 'corido-vendor-tracker' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'payout_amount' => array( 'payout_amount', false ),
			'status'        => array( 'status', false ),
			'created_at'    => array( 'created_at', true ),
		);
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '—' );
	}

	protected function column_vendor_name( $item ) {
		$url = admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $item->vendor_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->vendor_name ) . '</a>';
	}

	protected function column_item_title( $item ) {
		$url = admin_url( 'admin.php?page=cvt-items&action=view&id=' . $item->item_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->item_title ) . '</a>';
	}

	protected function column_selling_price( $item ) {
		return esc_html( CVT_Settings::format_currency( $item->selling_price ) );
	}

	protected function column_commission_rate( $item ) {
		return esc_html( $item->commission_rate . '%' );
	}

	protected function column_commission_amount( $item ) {
		return esc_html( CVT_Settings::format_currency( $item->commission_amount ) );
	}

	protected function column_payout_amount( $item ) {
		return '<strong>' . esc_html( CVT_Settings::format_currency( $item->payout_amount ) ) . '</strong>';
	}

	protected function column_status( $item ) {
		if ( $item->status === 'paid' ) {
			return '<span class="cvt-badge cvt-badge--sold">' . __( 'Paid', 'corido-vendor-tracker' ) . '</span>';
		}
		return '<span class="cvt-badge cvt-badge--inquiry">' . __( 'Pending', 'corido-vendor-tracker' ) . '</span>';
	}

	protected function column_created_at( $item ) {
		return esc_html( date_i18n( 'd M Y', strtotime( $item->created_at ) ) );
	}

	protected function column_actions( $item ) {
		if ( $item->status === 'paid' ) {
			$ref = $item->reference_number ? esc_html( $item->reference_number ) : '—';
			return '<span class="cvt-muted">Paid · Ref: ' . $ref . '</span>';
		}
		if ( ! current_user_can( 'cvt_mark_payouts' ) ) {
			return '—';
		}
		$item_url = admin_url( 'admin.php?page=cvt-items&action=view&id=' . $item->item_id );
		return '<a href="' . esc_url( $item_url ) . '#cvt-payout-card" class="button button-small button-primary">'
			. __( 'Mark Paid', 'corido-vendor-tracker' ) . '</a>';
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$result = CVT_Payout::get_all( array(
			'status'  => sanitize_key( $_GET['status'] ?? '' ),
			'orderby' => sanitize_key( $_GET['orderby'] ?? 'created_at' ),
			'order'   => sanitize_key( $_GET['order'] ?? 'DESC' ),
			'per_page' => $per_page,
			'paged'   => $paged,
		) );

		$this->items = $result['items'];
		$this->set_pagination_args( array(
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $result['total'] / $per_page ),
		) );
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	protected function get_views() {
		global $wpdb;
		$base    = admin_url( 'admin.php?page=cvt-payouts' );
		$current = sanitize_key( $_GET['status'] ?? '' );

		$counts  = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}cvt_payouts GROUP BY status"
		);
		$totals  = array( 'pending' => 0, 'paid' => 0 );
		foreach ( $counts as $row ) {
			$totals[ $row->status ] = (int) $row->cnt;
		}
		$all = array_sum( $totals );

		$class_all = $current === '' ? ' class="current"' : '';
		$views['all'] = "<a href=\"" . esc_url( $base ) . "\"$class_all>"
			. __( 'All', 'corido-vendor-tracker' ) . " <span class=\"count\">($all)</span></a>";

		$class_p = $current === 'pending' ? ' class="current"' : '';
		$views['pending'] = "<a href=\"" . esc_url( add_query_arg( 'status', 'pending', $base ) ) . "\"$class_p>"
			. __( 'Pending', 'corido-vendor-tracker' ) . " <span class=\"count\">({$totals['pending']})</span></a>";

		$class_paid = $current === 'paid' ? ' class="current"' : '';
		$views['paid'] = "<a href=\"" . esc_url( add_query_arg( 'status', 'paid', $base ) ) . "\"$class_paid>"
			. __( 'Paid', 'corido-vendor-tracker' ) . " <span class=\"count\">({$totals['paid']})</span></a>";

		return $views;
	}
}
