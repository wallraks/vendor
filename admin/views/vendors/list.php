<?php defined( 'ABSPATH' ) || exit;

require_once CVT_PLUGIN_DIR . 'admin/class-cvt-vendors-list-table.php';
$table = new CVT_Vendors_List_Table();
$table->prepare_items();
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Vendors', 'corido-vendor-tracker' ); ?></h1>
		<?php if ( current_user_can( 'cvt_add_vendors' ) ) : ?>
		<div class="cvt-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=add' ) ); ?>" class="button button-primary">
				+ <?php esc_html_e( 'Add Vendor', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
		<?php endif; ?>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<form method="get">
		<input type="hidden" name="page" value="cvt-vendors">
		<?php $table->search_box( __( 'Search Vendors', 'corido-vendor-tracker' ), 'vendor' ); ?>
		<?php $table->display(); ?>
	</form>
</div>
