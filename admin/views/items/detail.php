<?php defined( 'ABSPATH' ) || exit;

$item_id     = absint( $_GET['id'] ?? 0 );
$item        = CVT_Item::get( $item_id );
if ( ! $item ) {
	wp_die( esc_html__( 'Item not found.', 'corido-vendor-tracker' ) );
}

$images      = CVT_Item::get_images( $item_id );
$logs        = CVT_Activity_Log::get_for_entity( 'item', $item_id );
$status_info = CVT_Settings::status_info( $item->status );
$valid_next  = CVT_Settings::valid_transitions( $item->status );

// Build status history: first time each status was entered, derived from activity log.
// Logs are newest-first; we iterate oldest-first to capture first-entry dates.
$status_dates = array( 'under_review' => $item->created_at );
foreach ( array_reverse( $logs ) as $log ) {
	if ( $log->action === 'status_changed' ) {
		$new    = json_decode( $log->new_value, true );
		$status = $new['status'] ?? '';
		if ( $status && ! isset( $status_dates[ $status ] ) ) {
			$status_dates[ $status ] = $log->created_at;
		}
	}
}

// Allowed next transitions filtered by capability.
$allowed_next = array();
foreach ( $valid_next as $ns ) {
	if ( $ns === 'sold'      && ! current_user_can( 'cvt_update_status_sold' ) )      continue;
	if ( $ns === 'withdrawn' && ! current_user_can( 'cvt_update_status_withdrawn' ) ) continue;
	$allowed_next[] = $ns;
}

// Pipeline stages for the stepper (excludes 'withdrawn' which is a side branch).
$pipeline       = CVT_Settings::pipeline_stages();
$current_index  = array_search( $item->status, $pipeline, true ); // false if withdrawn

// Payout record (if sold or closed).
$payout = null;
if ( in_array( $item->status, array( 'sold', 'closed' ), true ) ) {
	global $wpdb;
	$payout = $wpdb->get_row( $wpdb->prepare(
		"SELECT p.*, u.display_name AS processed_by_name
		 FROM {$wpdb->prefix}cvt_payouts p
		 LEFT JOIN {$wpdb->users} u ON u.ID = p.processed_by
		 WHERE p.item_id = %d ORDER BY p.id DESC LIMIT 1",
		$item_id
	) );
}

$can_edit = current_user_can( 'cvt_edit_any_item' )
	|| ( (int) $item->created_by === get_current_user_id() && current_user_can( 'cvt_edit_own_item' ) );

// Agreement attachment.
$agreement_att_id = (int) ( $item->agreement_attachment_id ?? 0 );
$agreement_url    = $agreement_att_id ? wp_get_attachment_url( $agreement_att_id ) : '';
$agreement_title  = $agreement_att_id ? get_the_title( $agreement_att_id ) : '';
$agreement_mime   = $agreement_att_id ? get_post_mime_type( $agreement_att_id ) : '';

// Price history — activity log entries where action = 'price_changed'.
$price_history = array_values( array_filter( $logs, function( $l ) {
	return $l->action === 'price_changed';
} ) );

// Waitlist matches (set as a transient after item creation).
$wl_matches = get_transient( 'cvt_wl_matches_' . $item_id );
if ( $wl_matches !== false ) {
	delete_transient( 'cvt_wl_matches_' . $item_id ); // show once
}

// Button colour map per target status.
$action_btn_class = array(
	'posted'           => 'cvt-status-btn--posted',
	'inquiry_received' => 'cvt-status-btn--inquiry',
	'sold'             => 'cvt-status-btn--sold',
	'closed'           => 'cvt-status-btn--closed',
	'withdrawn'        => 'cvt-status-btn--withdrawn',
);
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
			<?php if ( $can_edit ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=edit&id=' . $item_id ) ); ?>" class="button">
				<?php esc_html_e( 'Edit Item', 'corido-vendor-tracker' ); ?>
			</a>
			<?php endif; ?>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<?php if ( ! empty( $wl_matches ) ) : ?>
	<div class="notice notice-warning is-dismissible cvt-wl-match-notice">
		<p>
			<strong><?php echo esc_html( sprintf(
				_n( '%d waiting list entry may match this item.', '%d waiting list entries may match this item.',
					count( $wl_matches ), 'corido-vendor-tracker' ),
				count( $wl_matches )
			) ); ?></strong>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>">
				<?php esc_html_e( 'View Waiting List →', 'corido-vendor-tracker' ); ?>
			</a>
		</p>
		<table class="cvt-table widefat striped" style="margin-top:8px;">
			<thead><tr>
				<th><?php esc_html_e( 'Client', 'corido-vendor-tracker' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'corido-vendor-tracker' ); ?></th>
				<th><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></th>
				<th><?php esc_html_e( 'Budget', 'corido-vendor-tracker' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $wl_matches as $wm ) :
				$budget = $wm->budget_max
					? 'up to ' . CVT_Settings::format_currency( $wm->budget_max )
					: ( $wm->budget_min ? 'from ' . CVT_Settings::format_currency( $wm->budget_min ) : '—' );
			?>
			<tr>
				<td><?php echo esc_html( $wm->client_name ); ?></td>
				<td><a href="tel:<?php echo esc_attr( $wm->phone ); ?>"><?php echo esc_html( $wm->phone ); ?></a></td>
				<td><?php echo esc_html( $wm->category ?: '—' ); ?></td>
				<td><?php echo esc_html( $budget ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'cvt_waitlist_mark' ); ?>
						<input type="hidden" name="action"       value="cvt_waitlist_mark">
						<input type="hidden" name="waitlist_id"  value="<?php echo esc_attr( $wm->id ); ?>">
						<input type="hidden" name="item_id"      value="<?php echo esc_attr( $item_id ); ?>">
						<input type="hidden" name="new_status"   value="matched">
						<button type="submit" class="button button-small button-primary">
							<?php esc_html_e( 'Mark Matched', 'corido-vendor-tracker' ); ?>
						</button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- ================================================================
	     STATUS STEPPER — full-width, shown above the detail grid
	     ================================================================ -->
	<div class="cvt-card cvt-stepper-card">

		<?php if ( $item->status === 'withdrawn' ) : ?>
		<!-- Withdrawn banner -->
		<div class="cvt-withdrawn-banner">
			<span class="dashicons dashicons-no-alt"></span>
			<?php
			$withdrawn_date = isset( $status_dates['withdrawn'] )
				? date_i18n( 'd M Y', strtotime( $status_dates['withdrawn'] ) )
				: '';
			echo esc_html( sprintf(
				__( 'This item was withdrawn%s.', 'corido-vendor-tracker' ),
				$withdrawn_date ? ' on ' . $withdrawn_date : ''
			) );
			?>
		</div>
		<?php endif; ?>

		<!-- Stepper steps -->
		<div class="cvt-stepper">
			<?php foreach ( $pipeline as $i => $stage ) :
				$info     = CVT_Settings::status_info( $stage );
				$visited  = isset( $status_dates[ $stage ] );
				$is_active = ( $item->status === $stage );
				// A step is "completed" if it was visited AND the current status is a later pipeline stage.
				$is_completed = $visited && ( $current_index !== false ) && ( $i < $current_index );
				$is_future    = ! $visited && ! $is_active;

				if ( $is_completed )  $step_class = 'cvt-step--completed';
				elseif ( $is_active ) $step_class = 'cvt-step--active';
				elseif ( $visited )   $step_class = 'cvt-step--visited'; // visited but not current linear position (e.g. went back)
				else                  $step_class = 'cvt-step--future';
			?>
			<div class="cvt-step <?php echo esc_attr( $step_class ); ?>">
				<div class="cvt-step-indicator">
					<?php if ( $is_completed ) : ?>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php elseif ( $is_active ) : ?>
					<span class="cvt-step-number"><?php echo esc_html( $i + 1 ); ?></span>
					<?php else : ?>
					<span class="cvt-step-number"><?php echo esc_html( $i + 1 ); ?></span>
					<?php endif; ?>
				</div>
				<div class="cvt-step-body">
					<div class="cvt-step-label"><?php echo esc_html( $info['label'] ); ?></div>
					<?php if ( $visited && isset( $status_dates[ $stage ] ) ) : ?>
					<div class="cvt-step-date">
						<?php echo esc_html( date_i18n( 'd M Y', strtotime( $status_dates[ $stage ] ) ) ); ?>
					</div>
					<?php elseif ( $is_future ) : ?>
					<div class="cvt-step-date cvt-step-date--pending">—</div>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $i < count( $pipeline ) - 1 ) : ?>
			<div class="cvt-step-connector <?php echo esc_attr( $is_completed ? 'cvt-step-connector--done' : '' ); ?>"></div>
			<?php endif; ?>
			<?php endforeach; ?>
		</div><!-- .cvt-stepper -->

		<!-- Quick status action buttons -->
		<?php if ( ! empty( $allowed_next ) && $item->status !== 'closed' ) : ?>
		<div class="cvt-status-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cvt-status-form">
				<?php wp_nonce_field( 'cvt_update_status' ); ?>
				<input type="hidden" name="action"   value="cvt_update_status">
				<input type="hidden" name="item_id"  value="<?php echo esc_attr( $item_id ); ?>">
				<input type="hidden" name="new_status" id="cvt-new-status-input" value="">

				<div class="cvt-status-actions-inner">
					<div class="cvt-status-actions-btns">
						<span class="cvt-status-actions-label"><?php esc_html_e( 'Move to:', 'corido-vendor-tracker' ); ?></span>
						<?php foreach ( $allowed_next as $ns ) :
							$ns_info = CVT_Settings::status_info( $ns );
							$btn_cls = $action_btn_class[ $ns ] ?? '';
						?>
						<button type="submit" class="cvt-status-btn <?php echo esc_attr( $btn_cls ); ?>"
							data-status="<?php echo esc_attr( $ns ); ?>">
							<?php echo esc_html( $ns_info['label'] ); ?>
						</button>
						<?php endforeach; ?>
					</div>
					<div class="cvt-status-actions-note">
						<input type="text" name="note" class="widefat"
							placeholder="<?php esc_attr_e( 'Add a note to this update (optional)…', 'corido-vendor-tracker' ); ?>">
					</div>
				</div>
			</form>
		</div>
		<?php elseif ( $item->status === 'closed' ) : ?>
		<p class="cvt-stepper-terminal">
			<?php esc_html_e( 'This item is closed. All done.', 'corido-vendor-tracker' ); ?>
		</p>
		<?php endif; ?>

		<?php
		// Reverse transitions — admins only, never from 'closed' (payout already paid).
		$reverse_next    = CVT_Settings::reverse_transitions( $item->status );
		$allowed_reverse = ( ! empty( $reverse_next ) && current_user_can( 'cvt_manage_settings' ) )
			? $reverse_next : array();
		?>
		<?php if ( ! empty( $allowed_reverse ) ) : ?>
		<div class="cvt-status-actions cvt-reverse-actions" style="margin-top:12px;border-top:1px dashed #ddd;padding-top:12px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cvt-reverse-form">
				<?php wp_nonce_field( 'cvt_update_status' ); ?>
				<input type="hidden" name="action"     value="cvt_update_status">
				<input type="hidden" name="item_id"    value="<?php echo esc_attr( $item_id ); ?>">
				<input type="hidden" name="new_status" id="cvt-reverse-status-input" value="">

				<div class="cvt-status-actions-inner">
					<div class="cvt-status-actions-btns">
						<span class="cvt-status-actions-label" style="color:#8c0000;">
							↩ <?php esc_html_e( 'Reverse deal:', 'corido-vendor-tracker' ); ?>
						</span>
						<?php foreach ( $allowed_reverse as $rs ) :
							$rs_info = CVT_Settings::status_info( $rs );
							$btn_cls = $action_btn_class[ $rs ] ?? '';
						?>
						<button type="button" class="cvt-status-btn <?php echo esc_attr( $btn_cls ); ?> cvt-reverse-btn"
							data-status="<?php echo esc_attr( $rs ); ?>">
							<?php echo esc_html( $rs_info['label'] ); ?>
						</button>
						<?php endforeach; ?>
					</div>
					<div class="cvt-status-actions-note">
						<input type="text" name="note" class="widefat"
							placeholder="<?php esc_attr_e( 'Reason for reversal (logged for audit trail)…', 'corido-vendor-tracker' ); ?>">
					</div>
				</div>
			</form>
		</div>
		<?php endif; ?>

	</div><!-- .cvt-stepper-card -->

	<!-- ================================================================
	     MAIN DETAIL GRID
	     ================================================================ -->
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
					<?php if ( $item->deal_type === 'listing' ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Listing Fee', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<?php if ( $item->listing_fee > 0 ) : ?>
							<?php echo esc_html( CVT_Settings::format_currency( $item->listing_fee ) ); ?>
							<?php else : ?>
							<span class="cvt-muted"><?php esc_html_e( 'Free', 'corido-vendor-tracker' ); ?></span>
							<?php endif; ?>
						</span>
					</div>
					<?php else : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Commission Rate', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<?php
							$eff_rate = ! is_null( $item->commission_rate )
								? (float) $item->commission_rate
								: CVT_Settings::commission_rate();
							echo esc_html( $eff_rate . '%' );
							if ( is_null( $item->commission_rate ) ) :
							?>
							<span class="cvt-muted"><?php esc_html_e( '(global default)', 'corido-vendor-tracker' ); ?></span>
							<?php endif; ?>
						</span>
					</div>
					<?php endif; ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Assigned Agent', 'corido-vendor-tracker' ); ?></span>
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
					<?php if ( $agreement_url ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Agreement', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<?php if ( $agreement_mime === 'application/pdf' ) : ?>
							<span class="dashicons dashicons-pdf" style="vertical-align:middle;color:#d63638;"></span>
							<?php else : ?>
							<span class="dashicons dashicons-format-image" style="vertical-align:middle;"></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( $agreement_url ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $agreement_title ?: basename( $agreement_url ) ); ?>
							</a>
							<a href="<?php echo esc_url( $agreement_url ); ?>" download class="button button-small" style="margin-left:8px;">
								↓ <?php esc_html_e( 'Download', 'corido-vendor-tracker' ); ?>
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
						$url  = wp_get_attachment_image_url( $img->attachment_id, 'medium' );
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
						<?php if ( $item->deal_type === 'listing' ) : ?>
						<span><?php esc_html_e( 'Listing Fee', 'corido-vendor-tracker' ); ?></span>
						<?php else : ?>
						<span><?php echo esc_html( sprintf( __( 'CR Commission (%s%%)', 'corido-vendor-tracker' ), $payout->commission_rate ) ); ?></span>
						<?php endif; ?>
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
						<input type="hidden" name="action"    value="cvt_save_payout">
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

			<!-- Price History -->
			<?php if ( ! empty( $price_history ) ) : ?>
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Price History', 'corido-vendor-tracker' ); ?></h2>
				<table class="cvt-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Old Price', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'New Price', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Changed By', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'corido-vendor-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $price_history as $ph ) :
							$old_v = json_decode( $ph->old_value, true );
							$new_v = json_decode( $ph->new_value, true );
						?>
						<tr>
							<td><?php echo esc_html( date_i18n( 'd M Y, H:i', strtotime( $ph->created_at ) ) ); ?></td>
							<td class="cvt-price"><?php echo esc_html( CVT_Settings::format_currency( $old_v['price'] ?? 0 ) ); ?></td>
							<td class="cvt-price"><strong><?php echo esc_html( CVT_Settings::format_currency( $new_v['price'] ?? 0 ) ); ?></strong></td>
							<td><?php echo esc_html( $ph->display_name ?: '—' ); ?></td>
							<td><?php echo $ph->note ? esc_html( $ph->note ) : '<span class="cvt-muted">—</span>'; ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
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

		<!-- Sidebar: vendor quick-ref and payout shortcut -->
		<div class="cvt-detail-sidebar">

			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Vendor', 'corido-vendor-tracker' ); ?></h2>
				<div class="cvt-info-grid cvt-info-grid--single">
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Name', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $item->vendor_id ) ); ?>">
								<?php echo esc_html( $item->vendor_name ?: '—' ); ?>
							</a>
						</span>
					</div>
					<?php if ( $item->vendor_phone ) : ?>
					<div class="cvt-info-row">
						<span class="cvt-info-label"><?php esc_html_e( 'Phone', 'corido-vendor-tracker' ); ?></span>
						<span class="cvt-info-value">
							<a href="tel:<?php echo esc_attr( $item->vendor_phone ); ?>"><?php echo esc_html( $item->vendor_phone ); ?></a>
						</span>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $payout && $payout->status === 'pending' && current_user_can( 'cvt_mark_payouts' ) ) : ?>
			<div class="cvt-card cvt-card--highlight">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Payout Due', 'corido-vendor-tracker' ); ?></h2>
				<p class="cvt-payout-due-amount"><?php echo esc_html( CVT_Settings::format_currency( $payout->payout_amount ) ); ?></p>
				<a href="#cvt-payout-card" class="button button-primary" style="width:100%;text-align:center;">
					<?php esc_html_e( 'Mark as Paid ↓', 'corido-vendor-tracker' ); ?>
				</a>
			</div>
			<?php endif; ?>

		</div><!-- .cvt-detail-sidebar -->
	</div><!-- .cvt-detail-grid -->
</div>

<script>
(function() {
	// Forward status buttons.
	var form  = document.getElementById('cvt-status-form');
	var input = document.getElementById('cvt-new-status-input');
	if ( form && input ) {
		form.addEventListener('click', function(e) {
			var btn = e.target.closest('.cvt-status-btn');
			if ( ! btn ) return;
			e.preventDefault();
			input.value = btn.dataset.status;
			form.submit();
		});
	}

	// Reverse-deal buttons (admin only).
	var rForm  = document.getElementById('cvt-reverse-form');
	var rInput = document.getElementById('cvt-reverse-status-input');
	if ( rForm && rInput ) {
		rForm.addEventListener('click', function(e) {
			var btn = e.target.closest('.cvt-reverse-btn');
			if ( ! btn ) return;
			e.preventDefault();
			rInput.value = btn.dataset.status;
			rForm.submit();
		});
	}
})();
</script>
