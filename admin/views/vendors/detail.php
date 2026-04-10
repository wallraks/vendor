<?php defined( 'ABSPATH' ) || exit;

$vendor_id = absint( $_GET['id'] ?? 0 );
$vendor    = CVT_Vendor::get( $vendor_id );
if ( ! $vendor ) {
	wp_die( esc_html__( 'Vendor not found.', 'corido-vendor-tracker' ) );
}

$items_result = CVT_Item::get_all( array( 'vendor_id' => $vendor_id, 'per_page' => 50 ) );
$items        = $items_result['items'];
$logs         = CVT_Activity_Log::get_for_entity( 'vendor', $vendor_id );
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Vendors', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $vendor->name ); ?>
		</h1>
		<div class="cvt-page-actions">
			<?php if ( current_user_can( 'cvt_edit_any_vendor' ) || (int) $vendor->created_by === get_current_user_id() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=edit&id=' . $vendor_id ) ); ?>" class="button">
				<?php esc_html_e( 'Edit Vendor', 'corido-vendor-tracker' ); ?>
			</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=add&vendor_id=' . $vendor_id ) ); ?>" class="button button-primary">
				+ <?php esc_html_e( 'Add Item', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<div class="cvt-detail-grid">
		<div class="cvt-detail-main">

			<!-- Vendor info card -->
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Vendor Information', 'corido-vendor-tracker' ); ?></h2>
				<div class="cvt-info-grid">
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Name', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( $vendor->name ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Primary Phone', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="tel:<?php echo esc_attr( $vendor->phone_primary ); ?>"><?php echo esc_html( $vendor->phone_primary ?: '—' ); ?></a>
						</span>
					</div>
					<?php if ( $vendor->phone_secondary ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Secondary Phone', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="tel:<?php echo esc_attr( $vendor->phone_secondary ); ?>"><?php echo esc_html( $vendor->phone_secondary ); ?></a>
						</span>
					</div>
					<?php endif; ?>
					<?php if ( $vendor->email ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Email', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="mailto:<?php echo esc_attr( $vendor->email ); ?>"><?php echo esc_html( $vendor->email ); ?></a>
						</span>
					</div>
					<?php endif; ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Location', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( $vendor->location ?: '—' ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Intake Channel', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( CVT_Settings::intake_channel_label( $vendor->intake_channel ) ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Added', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $vendor->created_at ) ) ); ?></span>
					</div>
					<?php if ( $vendor->notes ) : ?>
					<div class="cvt-info-row cvt-info-row--full">
						<span class="cvt-info-label"><?php esc_html_e( 'Notes', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo nl2br( esc_html( $vendor->notes ) ); ?></span>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Items -->
			<div class="cvt-card">
				<div class="cvt-card-header-row">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Items', 'corido-vendor-tracker' ); ?>
						<span class="cvt-count-chip"><?php echo count( $items ); ?></span>
					</h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=add&vendor_id=' . $vendor_id ) ); ?>" class="button button-small button-primary">
						+ <?php esc_html_e( 'Add Item', 'corido-vendor-tracker' ); ?>
					</a>
				</div>
				<?php if ( empty( $items ) ) : ?>
					<p class="cvt-empty"><?php esc_html_e( 'No items yet. Add the first item for this vendor.', 'corido-vendor-tracker' ); ?></p>
				<?php else : ?>
				<table class="cvt-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Item', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Price', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Deal', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Status', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Added', 'corido-vendor-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) :
							$info = CVT_Settings::status_info( $item->status );
							$view_url = admin_url( 'admin.php?page=cvt-items&action=view&id=' . $item->id );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $item->title ); ?></a></td>
							<td><?php echo esc_html( $item->category ); ?></td>
							<td><?php echo esc_html( CVT_Settings::format_currency( $item->selling_price ) ); ?></td>
							<td><?php echo esc_html( CVT_Settings::deal_type_label( $item->deal_type ) ); ?></td>
							<td><span class="cvt-badge <?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span></td>
							<td><?php echo esc_html( date_i18n( 'd M Y', strtotime( $item->created_at ) ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>

			<!-- Activity log -->
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Activity Log', 'corido-vendor-tracker' ); ?></h2>
				<?php if ( empty( $logs ) ) : ?>
					<p class="cvt-empty"><?php esc_html_e( 'No activity recorded yet.', 'corido-vendor-tracker' ); ?></p>
				<?php else : ?>
				<ul class="cvt-activity-feed cvt-activity-feed--full">
					<?php foreach ( $logs as $log ) : ?>
					<li class="cvt-activity-item">
						<span class="cvt-activity-dot"></span>
						<div class="cvt-activity-body">
							<span class="cvt-activity-desc">
								<?php echo wp_kses( CVT_Activity_Log::describe( $log ), array( 'strong' => array() ) ); ?>
							</span>
							<?php if ( $log->note ) : ?>
							<span class="cvt-activity-note">"<?php echo esc_html( $log->note ); ?>"</span>
							<?php endif; ?>
							<span class="cvt-activity-time">
								<?php echo esc_html( date_i18n( 'd M Y, H:i', strtotime( $log->created_at ) ) ); ?>
							</span>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>

		</div><!-- .cvt-detail-main -->
	</div><!-- .cvt-detail-grid -->
</div>
