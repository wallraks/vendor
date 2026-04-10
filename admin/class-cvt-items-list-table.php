<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CVT_Items_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'item',
			'plural'   => 'items',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox">',
			'title'         => __( 'Item', 'corido-vendor-tracker' ),
			'vendor_name'   => __( 'Vendor', 'corido-vendor-tracker' ),
			'category'      => __( 'Category', 'corido-vendor-tracker' ),
			'deal_type'     => __( 'Deal', 'corido-vendor-tracker' ),
			'selling_price' => __( 'Price', 'corido-vendor-tracker' ),
			'status'        => __( 'Status', 'corido-vendor-tracker' ),
			'agent_name'    => __( 'Agent', 'corido-vendor-tracker' ),
			'created_at'    => __( 'Added', 'corido-vendor-tracker' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'title'         => array( 'title', false ),
			'selling_price' => array( 'selling_price', false ),
			'status'        => array( 'status', false ),
			'created_at'    => array( 'created_at', true ),
		);
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '—' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="item_ids[]" value="%d">', absint( $item->id ) );
	}

	protected function column_title( $item ) {
		$view_url = admin_url( 'admin.php?page=cvt-items&action=view&id=' . $item->id );
		$edit_url = admin_url( 'admin.php?page=cvt-items&action=edit&id=' . $item->id );
		$del_url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=cvt_delete_item&id=' . $item->id ),
			'cvt_delete_item'
		);

		$actions = array(
			'view' => '<a href="' . esc_url( $view_url ) . '">' . __( 'View', 'corido-vendor-tracker' ) . '</a>',
			'edit' => '<a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'corido-vendor-tracker' ) . '</a>',
		);
		if ( current_user_can( 'cvt_delete_items' ) ) {
			$actions['delete'] = '<a href="' . esc_url( $del_url ) . '" class="cvt-delete-link">'
				. __( 'Delete', 'corido-vendor-tracker' ) . '</a>';
		}

		return '<strong><a href="' . esc_url( $view_url ) . '">' . esc_html( $item->title ) . '</a></strong>'
			. $this->row_actions( $actions );
	}

	protected function column_vendor_name( $item ) {
		$url = admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $item->vendor_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->vendor_name ?: '—' ) . '</a>';
	}

	protected function column_selling_price( $item ) {
		return esc_html( CVT_Settings::format_currency( $item->selling_price ) );
	}

	protected function column_deal_type( $item ) {
		return esc_html( CVT_Settings::deal_type_label( $item->deal_type ) );
	}

	protected function column_status( $item ) {
		$info = CVT_Settings::status_info( $item->status );
		return '<span class="cvt-badge ' . esc_attr( $info['class'] ) . '">' . esc_html( $info['label'] ) . '</span>';
	}

	protected function column_agent_name( $item ) {
		return esc_html( $item->agent_name ?: '—' );
	}

	protected function column_created_at( $item ) {
		return esc_html( date_i18n( 'd M Y', strtotime( $item->created_at ) ) );
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$result = CVT_Item::get_all( array(
			'search'    => sanitize_text_field( $_GET['s'] ?? '' ),
			'vendor_id' => absint( $_GET['vendor_id'] ?? 0 ),
			'status'    => sanitize_key( $_GET['status'] ?? '' ),
			'category'  => sanitize_text_field( $_GET['category'] ?? '' ),
			'orderby'   => sanitize_key( $_GET['orderby'] ?? 'created_at' ),
			'order'     => sanitize_key( $_GET['order'] ?? 'DESC' ),
			'per_page'  => $per_page,
			'paged'     => $paged,
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

	protected function get_bulk_actions() {
		return current_user_can( 'cvt_delete_items' )
			? array( 'delete' => __( 'Delete', 'corido-vendor-tracker' ) )
			: array();
	}

	/**
	 * Status filter links above the table.
	 */
	protected function get_views() {
		$counts  = CVT_Item::get_status_counts();
		$current = sanitize_key( $_GET['status'] ?? '' );
		$base    = admin_url( 'admin.php?page=cvt-items' );
		$views   = array();

		$total = array_sum( $counts );
		$class = $current === '' ? ' class="current"' : '';
		$views['all'] = '<a href="' . esc_url( $base ) . '"' . $class . '>'
			. __( 'All', 'corido-vendor-tracker' ) . " <span class=\"count\">($total)</span></a>";

		foreach ( CVT_Settings::all_statuses() as $status ) {
			$info  = CVT_Settings::status_info( $status );
			$count = $counts[ $status ] ?? 0;
			$class = $current === $status ? ' class="current"' : '';
			$url   = esc_url( add_query_arg( 'status', $status, $base ) );
			$views[ $status ] = "<a href=\"$url\"$class>{$info['label']} <span class=\"count\">($count)</span></a>";
		}

		return $views;
	}
}
