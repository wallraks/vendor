<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers admin menus, enqueues assets, and routes page requests to views.
 * Also handles all form (POST) submissions via admin_post_* hooks.
 */
class CVT_Admin {

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Form submission handlers.
		add_action( 'admin_post_cvt_save_vendor',   array( $this, 'handle_save_vendor' ) );
		add_action( 'admin_post_cvt_save_item',     array( $this, 'handle_save_item' ) );
		add_action( 'admin_post_cvt_update_status', array( $this, 'handle_update_status' ) );
		add_action( 'admin_post_cvt_save_payout',   array( $this, 'handle_save_payout' ) );
		add_action( 'admin_post_cvt_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_cvt_delete_vendor', array( $this, 'handle_delete_vendor' ) );
		add_action( 'admin_post_cvt_delete_item',   array( $this, 'handle_delete_item' ) );
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function register_menus() {
		$pending = CVT_Payout::pending_count();
		$badge   = $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';

		add_menu_page(
			__( 'Corido Vendors', 'corido-vendor-tracker' ),
			__( 'Corido Vendors', 'corido-vendor-tracker' ),
			'cvt_add_vendors',
			'cvt-dashboard',
			array( $this, 'page_dashboard' ),
			'dashicons-store',
			30
		);

		add_submenu_page( 'cvt-dashboard', __( 'Dashboard', 'corido-vendor-tracker' ),
			__( 'Dashboard', 'corido-vendor-tracker' ),
			'cvt_add_vendors', 'cvt-dashboard', array( $this, 'page_dashboard' ) );

		add_submenu_page( 'cvt-dashboard', __( 'Vendors', 'corido-vendor-tracker' ),
			__( 'Vendors', 'corido-vendor-tracker' ),
			'cvt_add_vendors', 'cvt-vendors', array( $this, 'page_vendors' ) );

		add_submenu_page( 'cvt-dashboard', __( 'Items', 'corido-vendor-tracker' ),
			__( 'Items', 'corido-vendor-tracker' ),
			'cvt_add_items', 'cvt-items', array( $this, 'page_items' ) );

		add_submenu_page( 'cvt-dashboard', __( 'Payouts', 'corido-vendor-tracker' ),
			__( 'Payouts', 'corido-vendor-tracker' ) . $badge,
			'cvt_view_payouts', 'cvt-payouts', array( $this, 'page_payouts' ) );

		add_submenu_page( 'cvt-dashboard', __( 'Reports', 'corido-vendor-tracker' ),
			__( 'Reports', 'corido-vendor-tracker' ),
			'cvt_view_reports', 'cvt-reports', array( $this, 'page_reports' ) );

		add_submenu_page( 'cvt-dashboard', __( 'Settings', 'corido-vendor-tracker' ),
			__( 'Settings', 'corido-vendor-tracker' ),
			'cvt_manage_settings', 'cvt-settings', array( $this, 'page_settings' ) );
	}

	// -------------------------------------------------------------------------
	// Asset enqueuing (scoped to CVT pages only)
	// -------------------------------------------------------------------------

	public function enqueue_assets( $hook ) {
		// Only load on our own admin pages.
		if ( strpos( $hook, 'cvt-' ) === false && strpos( $hook, 'corido' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'cvt-admin',
			CVT_PLUGIN_URL . 'admin/css/cvt-admin.css',
			array(),
			CVT_VERSION
		);

		wp_enqueue_media(); // WP media uploader.

		wp_enqueue_script(
			'cvt-admin',
			CVT_PLUGIN_URL . 'admin/js/cvt-admin.js',
			array( 'jquery' ),
			CVT_VERSION,
			true
		);

		wp_localize_script( 'cvt-admin', 'CVT', array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'cvt_ajax' ),
			'commission' => CVT_Settings::commission_rate(),
			'i18n'       => array(
				'confirm_delete'   => __( 'Are you sure? This cannot be undone.', 'corido-vendor-tracker' ),
				'uploading'        => __( 'Uploading...', 'corido-vendor-tracker' ),
				'select_image'     => __( 'Select Image', 'corido-vendor-tracker' ),
				'use_image'        => __( 'Use this image', 'corido-vendor-tracker' ),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Page callbacks — each loads the appropriate view file
	// -------------------------------------------------------------------------

	public function page_dashboard() {
		$this->require_cap( 'cvt_add_vendors' );
		require CVT_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function page_vendors() {
		$this->require_cap( 'cvt_add_vendors' );
		$action = sanitize_key( $_GET['action'] ?? 'list' );
		switch ( $action ) {
			case 'add':
			case 'edit':
				require CVT_PLUGIN_DIR . 'admin/views/vendors/form.php';
				break;
			case 'view':
				require CVT_PLUGIN_DIR . 'admin/views/vendors/detail.php';
				break;
			default:
				require CVT_PLUGIN_DIR . 'admin/views/vendors/list.php';
		}
	}

	public function page_items() {
		$this->require_cap( 'cvt_add_items' );
		$action = sanitize_key( $_GET['action'] ?? 'list' );
		switch ( $action ) {
			case 'add':
			case 'edit':
				require CVT_PLUGIN_DIR . 'admin/views/items/form.php';
				break;
			case 'view':
				require CVT_PLUGIN_DIR . 'admin/views/items/detail.php';
				break;
			default:
				require CVT_PLUGIN_DIR . 'admin/views/items/list.php';
		}
	}

	public function page_payouts() {
		$this->require_cap( 'cvt_view_payouts' );
		require CVT_PLUGIN_DIR . 'admin/views/payouts/list.php';
	}

	public function page_reports() {
		$this->require_cap( 'cvt_view_reports' );
		require CVT_PLUGIN_DIR . 'admin/views/reports.php';
	}

	public function page_settings() {
		$this->require_cap( 'cvt_manage_settings' );
		require CVT_PLUGIN_DIR . 'admin/views/settings.php';
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	public function handle_save_vendor() {
		check_admin_referer( 'cvt_save_vendor' );
		$id  = absint( $_POST['vendor_id'] ?? 0 );
		$data = $_POST;

		if ( $id ) {
			$result = CVT_Vendor::update( $id, $data );
		} else {
			$result = CVT_Vendor::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$id = $result;
			}
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( 'cvt-vendors', $result->get_error_message(), $id ? "action=edit&id=$id" : 'action=add' );
			return;
		}

		$this->redirect_with_notice( admin_url( "admin.php?page=cvt-vendors&action=view&id=$id" ),
			__( 'Vendor saved successfully.', 'corido-vendor-tracker' ) );
	}

	public function handle_save_item() {
		check_admin_referer( 'cvt_save_item' );
		$id   = absint( $_POST['item_id'] ?? 0 );
		$data = $_POST;

		if ( $id ) {
			$result = CVT_Item::update( $id, $data );
		} else {
			$result = CVT_Item::create( $data );
			if ( ! is_wp_error( $result ) ) {
				$id = $result;
			}
		}

		// Handle image attachments submitted with the form.
		if ( ! is_wp_error( $result ) && $id && ! empty( $_POST['cvt_image_ids'] ) ) {
			$attachment_ids = array_map( 'absint', explode( ',', $_POST['cvt_image_ids'] ) );
			foreach ( $attachment_ids as $att_id ) {
				if ( $att_id ) {
					CVT_Item::add_image( $id, $att_id );
				}
			}
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( 'cvt-items', $result->get_error_message(), $id ? "action=edit&id=$id" : 'action=add' );
			return;
		}

		$this->redirect_with_notice( admin_url( "admin.php?page=cvt-items&action=view&id=$id" ),
			__( 'Item saved successfully.', 'corido-vendor-tracker' ) );
	}

	public function handle_update_status() {
		check_admin_referer( 'cvt_update_status' );
		$item_id    = absint( $_POST['item_id'] ?? 0 );
		$new_status = sanitize_key( $_POST['new_status'] ?? '' );
		$note       = sanitize_textarea_field( $_POST['note'] ?? '' );

		$result = CVT_Item::update_status( $item_id, $new_status, $note );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( 'cvt-items', $result->get_error_message(), "action=view&id=$item_id" );
			return;
		}

		$this->redirect_with_notice( admin_url( "admin.php?page=cvt-items&action=view&id=$item_id" ),
			__( 'Status updated.', 'corido-vendor-tracker' ) );
	}

	public function handle_save_payout() {
		check_admin_referer( 'cvt_save_payout' );
		$payout_id = absint( $_POST['payout_id'] ?? 0 );
		$reference = sanitize_text_field( $_POST['reference_number'] ?? '' );
		$notes     = sanitize_textarea_field( $_POST['notes'] ?? '' );

		$result = CVT_Payout::mark_paid( $payout_id, $reference, $notes );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
		}

		$payout = CVT_Payout::get( $payout_id );
		$redirect = $payout
			? admin_url( "admin.php?page=cvt-items&action=view&id={$payout->item_id}" )
			: admin_url( 'admin.php?page=cvt-payouts' );

		$this->redirect_with_notice( $redirect, __( 'Payout marked as paid. Item closed.', 'corido-vendor-tracker' ) );
	}

	public function handle_save_settings() {
		check_admin_referer( 'cvt_save_settings' );
		$result = CVT_Settings::save( $_POST );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( 'cvt-settings', $result->get_error_message() );
			return;
		}
		$this->redirect_with_notice( admin_url( 'admin.php?page=cvt-settings' ),
			__( 'Settings saved.', 'corido-vendor-tracker' ) );
	}

	public function handle_delete_vendor() {
		check_admin_referer( 'cvt_delete_vendor' );
		$id     = absint( $_GET['id'] ?? 0 );
		$result = CVT_Vendor::delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
		}
		$this->redirect_with_notice( admin_url( 'admin.php?page=cvt-vendors' ),
			__( 'Vendor deleted.', 'corido-vendor-tracker' ) );
	}

	public function handle_delete_item() {
		check_admin_referer( 'cvt_delete_item' );
		$id     = absint( $_GET['id'] ?? 0 );
		$result = CVT_Item::delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
		}
		$this->redirect_with_notice( admin_url( 'admin.php?page=cvt-items' ),
			__( 'Item deleted.', 'corido-vendor-tracker' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function require_cap( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'corido-vendor-tracker' ) );
		}
	}

	/**
	 * Redirect to a page with a success notice (stored in transient).
	 */
	private function redirect_with_notice( $url, $message ) {
		set_transient( 'cvt_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => $message ), 30 );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect back to a CVT page with an error notice.
	 */
	private function redirect_with_error( $page, $message, $extra_params = '' ) {
		set_transient( 'cvt_notice_' . get_current_user_id(), array( 'type' => 'error', 'message' => $message ), 30 );
		$url = admin_url( "admin.php?page={$page}" . ( $extra_params ? "&{$extra_params}" : '' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Echo a stored admin notice and clear the transient.
	 * Call this at the top of each view file.
	 */
	public static function render_notice() {
		$key    = 'cvt_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		$class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}
