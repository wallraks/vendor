<?php defined( 'ABSPATH' ) || exit;

require_once CVT_PLUGIN_DIR . 'admin/class-cvt-items-list-table.php';
$table = new CVT_Items_List_Table();
$table->prepare_items();

$categories = CVT_Settings::categories();
$agents     = CVT_Roles::get_agents();
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
			<option value=""><?php esc_html_e( '— Category —', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( $categories as $cat ) : ?>
			<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $_GET['category'] ?? '', $cat ); ?>>
				<?php echo esc_html( $cat ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<select name="deal_type" class="cvt-filter-select">
			<option value=""><?php esc_html_e( '— Deal Type —', 'corido-vendor-tracker' ); ?></option>
			<option value="consignment" <?php selected( $_GET['deal_type'] ?? '', 'consignment' ); ?>><?php esc_html_e( 'Consignment', 'corido-vendor-tracker' ); ?></option>
			<option value="agency"      <?php selected( $_GET['deal_type'] ?? '', 'agency' );      ?>><?php esc_html_e( 'Agency', 'corido-vendor-tracker' ); ?></option>
			<option value="listing"     <?php selected( $_GET['deal_type'] ?? '', 'listing' );     ?>><?php esc_html_e( 'Listing', 'corido-vendor-tracker' ); ?></option>
		</select>
		<?php if ( $agents ) : ?>
		<select name="agent_id" class="cvt-filter-select">
			<option value=""><?php esc_html_e( '— Agent —', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( $agents as $agent ) : ?>
			<option value="<?php echo esc_attr( $agent->ID ); ?>" <?php selected( absint( $_GET['agent_id'] ?? 0 ), $agent->ID ); ?>>
				<?php echo esc_html( $agent->display_name ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php endif; ?>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'corido-vendor-tracker' ); ?></button>
		<?php if ( ! empty( $_GET['category'] ) || ! empty( $_GET['deal_type'] ) || ! empty( $_GET['agent_id'] ) || ! empty( $_GET['status'] ) || ! empty( $_GET['s'] ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items' ) ); ?>" class="button">
			<?php esc_html_e( 'Clear', 'corido-vendor-tracker' ); ?>
		</a>
		<?php endif; ?>
	</form>

	<?php $table->display(); ?>
</div>
