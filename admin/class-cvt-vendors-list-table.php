<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CVT_Vendors_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'vendor',
			'plural'   => 'vendors',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox">',
			'name'           => __( 'Name', 'corido-vendor-tracker' ),
			'phone_primary'  => __( 'Phone', 'corido-vendor-tracker' ),
			'intake_channel' => __( 'Channel', 'corido-vendor-tracker' ),
			'item_count'     => __( 'Items', 'corido-vendor-tracker' ),
			'agent_name'     => __( 'Agent', 'corido-vendor-tracker' ),
			'created_at'     => __( 'Added', 'corido-vendor-tracker' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '—' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="vendor_ids[]" value="%d">', absint( $item->id ) );
	}

	protected function column_name( $item ) {
		$view_url = admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $item->id );
		$edit_url = admin_url( 'admin.php?page=cvt-vendors&action=edit&id=' . $item->id );
		$del_url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=cvt_delete_vendor&id=' . $item->id ),
			'cvt_delete_vendor'
		);

		$actions = array(
			'view' => '<a href="' . esc_url( $view_url ) . '">' . __( 'View', 'corido-vendor-tracker' ) . '</a>',
			'edit' => '<a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'corido-vendor-tracker' ) . '</a>',
		);

		if ( current_user_can( 'cvt_delete_vendors' ) ) {
			$actions['delete'] = '<a href="' . esc_url( $del_url ) . '" class="cvt-delete-link">'
				. __( 'Delete', 'corido-vendor-tracker' ) . '</a>';
		}

		return '<strong><a href="' . esc_url( $view_url ) . '">' . esc_html( $item->name ) . '</a></strong>'
			. $this->row_actions( $actions );
	}

	protected function column_intake_channel( $item ) {
		return esc_html( CVT_Settings::intake_channel_label( $item->intake_channel ) );
	}

	protected function column_item_count( $item ) {
		$url = admin_url( 'admin.php?page=cvt-items&vendor_id=' . $item->id );
		return '<a href="' . esc_url( $url ) . '">' . absint( $item->item_count ) . '</a>';
	}

	protected function column_created_at( $item ) {
		return esc_html( date_i18n( 'd M Y', strtotime( $item->created_at ) ) );
	}

	protected function column_agent_name( $item ) {
		return esc_html( $item->agent_name ?: '—' );
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$result = CVT_Vendor::get_all( array(
			'search'         => sanitize_text_field( $_GET['s'] ?? '' ),
			'agent_id'       => absint( $_GET['agent_id'] ?? 0 ),
			'intake_channel' => sanitize_key( $_GET['intake_channel'] ?? '' ),
			'orderby'        => sanitize_key( $_GET['orderby'] ?? 'created_at' ),
			'order'          => sanitize_key( $_GET['order'] ?? 'DESC' ),
			'per_page'       => $per_page,
			'paged'          => $paged,
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
		return current_user_can( 'cvt_delete_vendors' )
			? array( 'delete' => __( 'Delete', 'corido-vendor-tracker' ) )
			: array();
	}
}
