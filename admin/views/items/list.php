<?php defined( 'ABSPATH' ) || exit;

require_once CVT_PLUGIN_DIR . 'admin/class-cvt-items-list-table.php';
$table = new CVT_Items_List_Table();
$table->prepare_items();

$categories = CVT_Settings::categories();
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Items', 'corido-vendor-tracker' ); ?></h1>
		<?php if ( current_user_can( 'cvt_add_items' ) ) : ?>
		<div class="cvt-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=add' ) ); ?>" class="button button-primary">
				+ <?php esc_html_e( 'Add Item', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
		<?php endif; ?>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<!-- Filters bar -->
	<form method="get" class="cvt-filter-bar">
		<input type="hidden" name="page" value="cvt-items">
		<?php $table->search_box( __( 'Search Items', 'corido-vendor-tracker' ), 'item' ); ?>
		<select name="category" class="cvt-filter-select">
			<option value=""><?php esc_html_e( '— All Categories —', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( $categories as $cat ) : ?>
			<option value="<?php echo esc_attr( $cat ); ?>"
				<?php selected( $_GET['category'] ?? '', $cat ); ?>>
				<?php echo esc_html( $cat ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'corido-vendor-tracker' ); ?></button>
		<?php if ( ! empty( $_GET['category'] ) || ! empty( $_GET['status'] ) || ! empty( $_GET['s'] ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items' ) ); ?>" class="button">
			<?php esc_html_e( 'Clear', 'corido-vendor-tracker' ); ?>
		</a>
		<?php endif; ?>
	</form>

	<?php $table->display(); ?>
</div>
