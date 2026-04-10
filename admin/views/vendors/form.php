<?php defined( 'ABSPATH' ) || exit;

$vendor_id = absint( $_GET['id'] ?? 0 );
$vendor    = $vendor_id ? CVT_Vendor::get( $vendor_id ) : null;
$is_edit   = (bool) $vendor;
$title     = $is_edit
	? __( 'Edit Vendor', 'corido-vendor-tracker' )
	: __( 'Add New Vendor', 'corido-vendor-tracker' );

// Permission check.
if ( $is_edit ) {
	$is_owner = $vendor && (int) $vendor->created_by === get_current_user_id();
	$cap      = $is_owner ? 'cvt_edit_own_vendor' : 'cvt_edit_any_vendor';
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'You do not have permission to edit this vendor.', 'corido-vendor-tracker' ) );
	}
}

$channels = array( 'phone', 'whatsapp', 'email', 'walkin' );
$agents   = CVT_Roles::get_agents();
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Vendors', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $title ); ?>
		</h1>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cvt-form">
		<?php wp_nonce_field( 'cvt_save_vendor' ); ?>
		<input type="hidden" name="action" value="cvt_save_vendor">
		<input type="hidden" name="vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>">

		<div class="cvt-form-grid">
			<!-- Main column -->
			<div class="cvt-form-main">
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Vendor Details', 'corido-vendor-tracker' ); ?></h2>

					<div class="cvt-field">
						<label for="name"><?php esc_html_e( 'Full Name', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
						<input type="text" id="name" name="name" required class="widefat"
							value="<?php echo esc_attr( $vendor->name ?? '' ); ?>">
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="phone_primary"><?php esc_html_e( 'Primary Phone', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<input type="tel" id="phone_primary" name="phone_primary" required class="widefat"
								value="<?php echo esc_attr( $vendor->phone_primary ?? '' ); ?>"
								placeholder="+254 7XX XXX XXX">
						</div>
						<div class="cvt-field">
							<label for="phone_secondary"><?php esc_html_e( 'Secondary Phone', 'corido-vendor-tracker' ); ?></label>
							<input type="tel" id="phone_secondary" name="phone_secondary" class="widefat"
								value="<?php echo esc_attr( $vendor->phone_secondary ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Optional', 'corido-vendor-tracker' ); ?>">
						</div>
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="email"><?php esc_html_e( 'Email', 'corido-vendor-tracker' ); ?></label>
							<input type="email" id="email" name="email" class="widefat"
								value="<?php echo esc_attr( $vendor->email ?? '' ); ?>">
						</div>
						<div class="cvt-field">
							<label for="location"><?php esc_html_e( 'Location / Area', 'corido-vendor-tracker' ); ?></label>
							<input type="text" id="location" name="location" class="widefat"
								value="<?php echo esc_attr( $vendor->location ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. Westlands, Nairobi', 'corido-vendor-tracker' ); ?>">
						</div>
					</div>

					<div class="cvt-field">
						<label for="intake_channel"><?php esc_html_e( 'How did they reach out?', 'corido-vendor-tracker' ); ?></label>
						<select id="intake_channel" name="intake_channel" class="widefat">
							<?php foreach ( $channels as $ch ) : ?>
							<option value="<?php echo esc_attr( $ch ); ?>"
								<?php selected( $vendor->intake_channel ?? 'phone', $ch ); ?>>
								<?php echo esc_html( CVT_Settings::intake_channel_label( $ch ) ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="cvt-field">
						<label for="notes"><?php esc_html_e( 'Notes', 'corido-vendor-tracker' ); ?></label>
						<textarea id="notes" name="notes" rows="4" class="widefat"
							placeholder="<?php esc_attr_e( 'Any context about this vendor…', 'corido-vendor-tracker' ); ?>"><?php echo esc_textarea( $vendor->notes ?? '' ); ?></textarea>
					</div>
				</div>
			</div>

			<!-- Sidebar column -->
			<div class="cvt-form-sidebar">
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Assignment', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field">
						<label for="assigned_agent_id"><?php esc_html_e( 'Assigned Agent', 'corido-vendor-tracker' ); ?></label>
						<select id="assigned_agent_id" name="assigned_agent_id" class="widefat">
							<option value=""><?php esc_html_e( '— Unassigned —', 'corido-vendor-tracker' ); ?></option>
							<?php foreach ( $agents as $agent ) : ?>
							<option value="<?php echo esc_attr( $agent->ID ); ?>"
								<?php selected( $vendor->assigned_agent_id ?? '', $agent->ID ); ?>>
								<?php echo esc_html( $agent->display_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="cvt-card">
					<button type="submit" class="button button-primary button-hero cvt-submit-btn">
						<?php echo $is_edit
							? esc_html__( 'Update Vendor', 'corido-vendor-tracker' )
							: esc_html__( 'Save Vendor', 'corido-vendor-tracker' ); ?>
					</button>
					<?php if ( $is_edit ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $vendor_id ) ); ?>"
						class="button cvt-cancel-btn">
						<?php esc_html_e( 'Cancel', 'corido-vendor-tracker' ); ?>
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</form>
</div>
