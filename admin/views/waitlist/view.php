<?php defined( 'ABSPATH' ) || exit;

$entry_id = absint( $_GET['id'] ?? 0 );
if ( ! $entry_id ) {
	wp_die( esc_html__( 'No entry specified.', 'corido-vendor-tracker' ) );
}
$entry = CVT_Waitlist::get( $entry_id );
if ( ! $entry ) {
	wp_die( esc_html__( 'Waiting list entry not found.', 'corido-vendor-tracker' ) );
}

// Log access for non-admin users so all views are audited.
if ( ! current_user_can( 'cvt_manage_settings' ) ) {
	CVT_Activity_Log::log( 'waitlist', $entry->id, 'viewed' );
}

$status_labels = array(
	'open'      => array( 'label' => 'Open',      'class' => 'cvt-badge--review' ),
	'matched'   => array( 'label' => 'Matched',   'class' => 'cvt-badge--inquiry' ),
	'fulfilled' => array( 'label' => 'Fulfilled', 'class' => 'cvt-badge--sold' ),
	'cancelled' => array( 'label' => 'Cancelled', 'class' => 'cvt-badge--withdrawn' ),
);
$si   = $status_labels[ $entry->status ] ?? array( 'label' => $entry->status, 'class' => '' );
$tags = CVT_Waitlist::decode_tags( $entry->tags ?? '' );

$budget = '';
if ( $entry->budget_min && $entry->budget_max ) {
	$budget = CVT_Settings::format_currency( $entry->budget_min ) . ' – ' . CVT_Settings::format_currency( $entry->budget_max );
} elseif ( $entry->budget_max ) {
	$budget = __( 'Up to', 'corido-vendor-tracker' ) . ' ' . CVT_Settings::format_currency( $entry->budget_max );
} elseif ( $entry->budget_min ) {
	$budget = __( 'From', 'corido-vendor-tracker' ) . ' ' . CVT_Settings::format_currency( $entry->budget_min );
}

$log_entries = CVT_Activity_Log::get_for_entity( 'waitlist', $entry->id );
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Waiting List', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $entry->client_name ); ?>
		</h1>
		<div class="cvt-page-actions">
			<?php if ( current_user_can( 'cvt_add_items' ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&action=edit&id=' . $entry->id ) ); ?>" class="button">
				<?php esc_html_e( 'Edit Entry', 'corido-vendor-tracker' ); ?>
			</a>
			<?php endif; ?>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<div class="cvt-form-grid">
		<div class="cvt-form-main">

			<!-- Client details -->
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Client Details', 'corido-vendor-tracker' ); ?></h2>
				<div class="cvt-field-row">
					<div class="cvt-field">
						<label><?php esc_html_e( 'Client Name', 'corido-vendor-tracker' ); ?></label>
						<p class="cvt-detail-value"><strong><?php echo esc_html( $entry->client_name ); ?></strong></p>
					</div>
					<div class="cvt-field">
						<label><?php esc_html_e( 'Phone', 'corido-vendor-tracker' ); ?></label>
						<p class="cvt-detail-value">
							<a href="tel:<?php echo esc_attr( $entry->phone ); ?>"><?php echo esc_html( $entry->phone ); ?></a>
						</p>
					</div>
				</div>
				<?php if ( $entry->email || $entry->timeframe ) : ?>
				<div class="cvt-field-row">
					<?php if ( $entry->email ) : ?>
					<div class="cvt-field">
						<label><?php esc_html_e( 'Email', 'corido-vendor-tracker' ); ?></label>
						<p class="cvt-detail-value">
							<a href="mailto:<?php echo esc_attr( $entry->email ); ?>"><?php echo esc_html( $entry->email ); ?></a>
						</p>
					</div>
					<?php endif; ?>
					<?php if ( $entry->timeframe ) : ?>
					<div class="cvt-field">
						<label><?php esc_html_e( 'Desired Timeframe', 'corido-vendor-tracker' ); ?></label>
						<p class="cvt-detail-value"><?php echo esc_html( $entry->timeframe ); ?></p>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>

			<!-- Item request -->
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Item Request', 'corido-vendor-tracker' ); ?></h2>

				<div class="cvt-field-row">
					<?php if ( $entry->category ) : ?>
					<div class="cvt-field">
						<label><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></label>
						<p class="cvt-detail-value"><span class="cvt-role-chip"><?php echo esc_html( $entry->category ); ?></span></p>
					</div>
					<?php endif; ?>
					<div class="cvt-field">
						<label><?php esc_html_e( 'Quantity', 'corido-vendor-tracker' ); ?></label>
						<p class="cvt-detail-value"><?php echo esc_html( $entry->quantity ); ?></p>
					</div>
				</div>

				<?php if ( ! empty( $tags ) ) : ?>
				<div class="cvt-field">
					<label><?php esc_html_e( 'Tags', 'corido-vendor-tracker' ); ?></label>
					<p class="cvt-detail-value">
						<?php foreach ( $tags as $tag ) : ?>
						<span class="cvt-tag-pill"><?php echo esc_html( $tag ); ?></span>
						<?php endforeach; ?>
					</p>
				</div>
				<?php endif; ?>

				<?php if ( $budget ) : ?>
				<div class="cvt-field">
					<label><?php esc_html_e( 'Budget (KES)', 'corido-vendor-tracker' ); ?></label>
					<p class="cvt-detail-value"><?php echo esc_html( $budget ); ?></p>
				</div>
				<?php endif; ?>

				<?php if ( $entry->description ) : ?>
				<div class="cvt-field">
					<label><?php esc_html_e( 'Description / Specifications', 'corido-vendor-tracker' ); ?></label>
					<p class="cvt-detail-value"><?php echo nl2br( esc_html( $entry->description ) ); ?></p>
				</div>
				<?php endif; ?>

				<?php if ( $entry->notes ) : ?>
				<div class="cvt-field">
					<label><?php esc_html_e( 'Internal Notes', 'corido-vendor-tracker' ); ?></label>
					<p class="cvt-detail-value cvt-muted"><?php echo nl2br( esc_html( $entry->notes ) ); ?></p>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $log_entries ) ) : ?>
			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Activity Log', 'corido-vendor-tracker' ); ?></h2>
				<table class="cvt-table widefat">
					<tbody>
						<?php foreach ( $log_entries as $log ) : ?>
						<tr>
							<td class="cvt-log-date" style="white-space:nowrap;color:var(--cvt-muted);font-size:12px;">
								<?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $log->created_at ) ) ); ?>
							</td>
							<td><?php echo wp_kses( CVT_Activity_Log::describe( $log ), array( 'strong' => array() ) ); ?></td>
							<?php if ( $log->note ) : ?>
							<td class="cvt-muted"><?php echo esc_html( $log->note ); ?></td>
							<?php endif; ?>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

		</div><!-- .cvt-form-main -->

		<div class="cvt-form-sidebar">

			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Status', 'corido-vendor-tracker' ); ?></h2>
				<p>
					<span class="cvt-badge <?php echo esc_attr( $si['class'] ); ?>">
						<?php echo esc_html( $si['label'] ); ?>
					</span>
				</p>
				<?php if ( $entry->matched_item_id ) : ?>
				<p style="margin-top:8px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=view&id=' . $entry->matched_item_id ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View Matched Item', 'corido-vendor-tracker' ); ?>
					</a>
				</p>
				<?php endif; ?>
			</div>

			<div class="cvt-card">
				<h2 class="cvt-card-title"><?php esc_html_e( 'Assignment', 'corido-vendor-tracker' ); ?></h2>
				<p class="cvt-muted" style="font-size:11px;margin-bottom:2px;"><?php esc_html_e( 'Agent', 'corido-vendor-tracker' ); ?></p>
				<p><?php echo esc_html( $entry->agent_name ?: '—' ); ?></p>
				<p class="cvt-muted" style="font-size:11px;margin-top:10px;margin-bottom:2px;"><?php esc_html_e( 'Date Added', 'corido-vendor-tracker' ); ?></p>
				<p><?php echo esc_html( date_i18n( 'd M Y', strtotime( $entry->created_at ) ) ); ?></p>
			</div>

		</div><!-- .cvt-form-sidebar -->
	</div>
</div>
