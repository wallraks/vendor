<?php defined( 'ABSPATH' ) || exit;

$item_id = absint( $_GET['id'] ?? 0 );
$item    = CVT_Item::get( $item_id );
if ( ! $item ) {
	wp_die( esc_html__( 'Item not found.', 'corido-vendor-tracker' ) );
}

$images     = CVT_Item::get_images( $item_id );
$logs       = CVT_Activity_Log::get_for_entity( 'item', $item_id );
$status_info = CVT_Settings::status_info( $item->status );
$valid_next = CVT_Settings::valid_transitions( $item->status );

// Payout record (if sold or closed).
$payout = null;
if ( in_array( $item->status, array( 'sold', 'closed' ), true ) ) {
	global $wpdb;
	$payout = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT p.*, u.display_name AS processed_by_name
			 FROM {$wpdb->prefix}cvt_payouts p
			 LEFT JOIN {$wpdb->users} u ON u.ID = p.processed_by
			 WHERE p.item_id = %d ORDER BY p.id DESC LIMIT 1",
			$item_id
		)
	);
}
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Items', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $item->title ); ?>
			<span class="cvt-badge <?php echo esc_attr( $status_info['class'] ); ?> cvt-heading-badge">
				<?php echo esc_html( $status_info['label'] ); ?>
			</span>
		</h1>
		<div class="cvt-page-actions">
			<?php
			$can_edit = current_user_can( 'cvt_edit_any_item' )
				|| ( (int) $item->created_by === get_current_user_id() && current_user_can( 'cvt_edit_own_item' ) );
			if ( $can_edit ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=edit&id=' . $item_id ) ); ?>" class="button">
				<?php esc_html_e( 'Edit Item', 'corido-vendor-tracker' ); ?>
			</a>
			<?php endif; ?>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<div class="cvt-detail-grid">
		<div class="cvt-detail-main">

			<!-- Item info -->
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Item Details', 'corido-vendor-tracker' ); ?></h2>
				<div class="cvt-info-grid">
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Vendor', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $item->vendor_id ) ); ?>">
								<?php echo esc_html( $item->vendor_name ); ?>
							</a>
							<?php if ( $item->vendor_phone ) : ?>
							· <a href="tel:<?php echo esc_attr( $item->vendor_phone ); ?>"><?php echo esc_html( $item->vendor_phone ); ?></a>
							<?php endif; ?>
						</span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( $item->category ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Deal Type', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( CVT_Settings::deal_type_label( $item->deal_type ) ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Market Value', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo $item->market_value ? esc_html( CVT_Settings::format_currency( $item->market_value ) ) : '—'; ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Selling Price', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value cvt-price"><?php echo esc_html( CVT_Settings::format_currency( $item->selling_price ) ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Agent', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( $item->agent_name ?: '—' ); ?></span>
					</div>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Date Received', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo $item->date_received ? esc_html( date_i18n( 'd M Y', strtotime( $item->date_received ) ) ) : '—'; ?></span>
					</div>
					<?php if ( $item->date_posted ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Date Posted', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $item->date_posted ) ) ); ?></span>
					</div>
					<?php endif; ?>
					<?php if ( $item->listivo_listing_url ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Website Listing', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="<?php echo esc_url( $item->listivo_listing_url ); ?>" target="_blank">
								<?php esc_html_e( 'View on site', 'corido-vendor-tracker' ); ?> ↗
							</a>
						</span>
					</div>
					<?php endif; ?>
					<?php if ( $item->description ) : ?>
					<div class="cvt-info-row cvt-info-row--full">
						<span class="cvt-info-label"><?php esc_html_e( 'Description', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo nl2br( esc_html( $item->description ) ); ?></span>
					</div>
					<?php endif; ?>
					<?php if ( $item->notes ) : ?>
					<div class="cvt-info-row cvt-info-row--full">
						<span class="cvt-info-label"><?php esc_html_e( 'Notes', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value"><?php echo nl2br( esc_html( $item->notes ) ); ?></span>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Images -->
			<?php if ( ! empty( $images ) ) : ?>
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Images', 'corido-vendor-tracker' ); ?></h2>
				<div class="cvt-image-grid">
					<?php foreach ( $images as $img ) :
						$url = wp_get_attachment_image_url( $img->attachment_id, 'medium' );
						$full = wp_get_attachment_url( $img->attachment_id );
					?>
					<a href="<?php echo esc_url( $full ); ?>" target="_blank" class="cvt-image-thumb cvt-image-thumb--view">
						<img src="<?php echo esc_url( $url ); ?>" alt="">
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Payout card -->
			<?php if ( $payout ) : ?>
			<div class="cvt-card" id="cvt-payout-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Payout', 'corido-vendor-tracker' ); ?>
					<?php if ( $payout->status === 'paid' ) : ?>
					<span class="cvt-badge cvt-badge--sold"><?php esc_html_e( 'Paid', 'corido-vendor-tracker' ); ?></span>
					<?php else : ?>
					<span class="cvt-badge cvt-badge--inquiry"><?php esc_html_e( 'Pending', 'corido-vendor-tracker' ); ?></span>
					<?php endif; ?>
				</h2>
				<div class="cvt-payout-summary">
					<div class="cvt-payout-row">
						<span><?php esc_html_e( 'Selling Price', 'corido-vendor-tracker' ); ?></span>
						<span><?php echo esc_html( CVT_Settings::format_currency( $payout->selling_price ) ); ?></span>
					</div>
					<div class="cvt-payout-row">
						<span><?php echo esc_html( sprintf( __( 'Corido Commission (%s%%)', 'corido-vendor-tracker' ), $payout->commission_rate ) ); ?></span>
						<span>− <?php echo esc_html( CVT_Settings::format_currency( $payout->commission_amount ) ); ?></span>
					</div>
					<div class="cvt-payout-row cvt-payout-row--total">
						<strong><?php esc_html_e( 'Vendor Payout', 'corido-vendor-tracker' ); ?></strong>
						<strong><?php echo esc_html( CVT_Settings::format_currency( $payout->payout_amount ) ); ?></strong>
					</div>
					<?php if ( $payout->status === 'paid' ) : ?>
					<div class="cvt-payout-paid-info">
						<?php if ( $payout->reference_number ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Reference: %s', 'corido-vendor-tracker' ), $payout->reference_number ) ); ?></p>
						<?php endif; ?>
						<?php if ( $payout->payout_date ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Paid on: %s', 'corido-vendor-tracker' ), date_i18n( 'd M Y', strtotime( $payout->payout_date ) ) ) ); ?></p>
						<?php endif; ?>
						<?php if ( $payout->processed_by_name ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Processed by: %s', 'corido-vendor-tracker' ), $payout->processed_by_name ) ); ?></p>
						<?php endif; ?>
					</div>
					<?php elseif ( current_user_can( 'cvt_mark_payouts' ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cvt-payout-form">
						<?php wp_nonce_field( 'cvt_save_payout' ); ?>
						<input type="hidden" name="action" value="cvt_save_payout">
						<input type="hidden" name="payout_id" value="<?php echo esc_attr( $payout->id ); ?>">
						<div class="cvt-field-row">
							<div class="cvt-field">
								<label for="reference_number"><?php esc_html_e( 'Reference (M-Pesa / Bank)', 'corido-vendor-tracker' ); ?></label>
								<input type="text" id="reference_number" name="reference_number" class="widefat"
									placeholder="<?php esc_attr_e( 'e.g. QHG3X2P4YZ', 'corido-vendor-tracker' ); ?>">
							</div>
							<div class="cvt-field">
								<label for="payout_notes"><?php esc_html_e( 'Notes', 'corido-vendor-tracker' ); ?></label>
								<input type="text" id="payout_notes" name="notes" class="widefat">
							</div>
						</div>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Mark as Paid & Close Item', 'corido-vendor-tracker' ); ?>
						</button>
					</form>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

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

		<!-- Sidebar -->
		<div class="cvt-detail-sidebar">

			<!-- Status update -->
			<?php if ( ! empty( $valid_next ) || current_user_can( 'cvt_manage_settings' ) ) : ?>
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Update Status', 'corido-vendor-tracker' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'cvt_update_status' ); ?>
					<input type="hidden" name="action" value="cvt_update_status">
					<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>">
					<div class="cvt-field">
						<label for="new_status"><?php esc_html_e( 'New Status', 'corido-vendor-tracker' ); ?></label>
						<select id="new_status" name="new_status" class="widefat">
							<?php foreach ( $valid_next as $ns ) :
								// Check capability for each status.
								if ( $ns === 'sold' && ! current_user_can( 'cvt_update_status_sold' ) ) continue;
								if ( $ns === 'withdrawn' && ! current_user_can( 'cvt_update_status_withdrawn' ) ) continue;
								$info = CVT_Settings::status_info( $ns );
							?>
							<option value="<?php echo esc_attr( $ns ); ?>"><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cvt-field">
						<label for="note"><?php esc_html_e( 'Note (optional)', 'corido-vendor-tracker' ); ?></label>
						<textarea id="note" name="note" rows="2" class="widefat"
							placeholder="<?php esc_attr_e( 'e.g. Buyer called at 2pm, interested in viewing…', 'corido-vendor-tracker' ); ?>"></textarea>
					</div>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Update Status', 'corido-vendor-tracker' ); ?>
					</button>
				</form>
			</div>
			<?php endif; ?>

		</div><!-- .cvt-detail-sidebar -->
	</div><!-- .cvt-detail-grid -->
</div>
