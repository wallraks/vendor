<?php defined( 'ABSPATH' ) || exit;

require_once CVT_PLUGIN_DIR . 'admin/class-cvt-vendors-list-table.php';
$table = new CVT_Vendors_List_Table();
$table->prepare_items();

$agents = CVT_Roles::get_agents();
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

	<form method="get" class="cvt-filter-bar">
		<input type="hidden" name="page" value="cvt-vendors">
		<?php $table->search_box( __( 'Search Vendors', 'corido-vendor-tracker' ), 'vendor' ); ?>
		<select name="intake_channel" class="cvt-filter-select">
			<option value=""><?php esc_html_e( '— Channel —', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( array( 'phone' => 'Phone Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'walkin' => 'Walk-in' ) as $slug => $label ) : ?>
			<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $_GET['intake_channel'] ?? '', $slug ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
			<?php endforeach; ?>
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
		<?php if ( ! empty( $_GET['intake_channel'] ) || ! empty( $_GET['agent_id'] ) || ! empty( $_GET['s'] ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors' ) ); ?>" class="button">
			<?php esc_html_e( 'Clear', 'corido-vendor-tracker' ); ?>
		</a>
		<?php endif; ?>
		<?php $table->display(); ?>
	</form>
</div>
