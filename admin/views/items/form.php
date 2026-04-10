<?php defined( 'ABSPATH' ) || exit;

$item_id    = absint( $_GET['id'] ?? 0 );
$item       = $item_id ? CVT_Item::get( $item_id ) : null;
$is_edit    = (bool) $item;
$title      = $is_edit
	? __( 'Edit Item', 'corido-vendor-tracker' )
	: __( 'Add New Item', 'corido-vendor-tracker' );

// Pre-select vendor if passed from vendor detail page.
$preselect_vendor_id = absint( $_GET['vendor_id'] ?? ( $item->vendor_id ?? 0 ) );
$preselect_vendor    = $preselect_vendor_id ? CVT_Vendor::get( $preselect_vendor_id ) : null;

if ( $is_edit ) {
	$is_owner = (int) $item->created_by === get_current_user_id();
	$cap      = $is_owner ? 'cvt_edit_own_item' : 'cvt_edit_any_item';
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'You do not have permission to edit this item.', 'corido-vendor-tracker' ) );
	}
}

$categories   = CVT_Settings::categories();
$agents       = CVT_Roles::get_agents();
$images       = $is_edit ? CVT_Item::get_images( $item_id ) : array();
$comm_rate    = CVT_Settings::commission_rate();
$selling_price = (float) ( $item->selling_price ?? 0 );
$calcs        = CVT_Payout::calculate( $selling_price, $comm_rate );
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Items', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $title ); ?>
		</h1>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cvt-form" id="cvt-item-form">
		<?php wp_nonce_field( 'cvt_save_item' ); ?>
		<input type="hidden" name="action" value="cvt_save_item">
		<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>">
		<input type="hidden" name="cvt_image_ids" id="cvt_image_ids" value="">

		<div class="cvt-form-grid">
			<div class="cvt-form-main">

				<!-- Item details -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Item Details', 'corido-vendor-tracker' ); ?></h2>

					<div class="cvt-field">
						<label for="title"><?php esc_html_e( 'Item Title', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
						<input type="text" id="title" name="title" required class="widefat"
							value="<?php echo esc_attr( $item->title ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. IKEA 3-seater sofa, good condition', 'corido-vendor-tracker' ); ?>">
					</div>

					<div class="cvt-field">
						<label for="description"><?php esc_html_e( 'Description', 'corido-vendor-tracker' ); ?></label>
						<textarea id="description" name="description" rows="4" class="widefat"
							placeholder="<?php esc_attr_e( 'Condition, dimensions, colour, any defects…', 'corido-vendor-tracker' ); ?>"><?php echo esc_textarea( $item->description ?? '' ); ?></textarea>
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="category"><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<select id="category" name="category" class="widefat" required>
								<option value=""><?php esc_html_e( '— Select —', 'corido-vendor-tracker' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat ); ?>"
									<?php selected( $item->category ?? '', $cat ); ?>>
									<?php echo esc_html( $cat ); ?>
								</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cvt-field">
							<label for="deal_type"><?php esc_html_e( 'Deal Type', 'corido-vendor-tracker' ); ?></label>
							<select id="deal_type" name="deal_type" class="widefat">
								<option value="consignment" <?php selected( $item->deal_type ?? 'consignment', 'consignment' ); ?>><?php esc_html_e( 'Consignment', 'corido-vendor-tracker' ); ?></option>
								<option value="agency" <?php selected( $item->deal_type ?? '', 'agency' ); ?>><?php esc_html_e( 'Agency', 'corido-vendor-tracker' ); ?></option>
							</select>
						</div>
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="date_received"><?php esc_html_e( 'Date Received', 'corido-vendor-tracker' ); ?></label>
							<input type="date" id="date_received" name="date_received" class="widefat"
								value="<?php echo esc_attr( $item->date_received ?? current_time( 'Y-m-d' ) ); ?>">
						</div>
						<div class="cvt-field">
							<label for="notes"><?php esc_html_e( 'Internal Notes', 'corido-vendor-tracker' ); ?></label>
							<textarea id="notes" name="notes" rows="2" class="widefat"><?php echo esc_textarea( $item->notes ?? '' ); ?></textarea>
						</div>
					</div>

					<div class="cvt-field">
						<label for="listivo_listing_url"><?php esc_html_e( 'Listivo Listing URL', 'corido-vendor-tracker' ); ?></label>
						<input type="url" id="listivo_listing_url" name="listivo_listing_url" class="widefat"
							value="<?php echo esc_attr( $item->listivo_listing_url ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'Paste URL once posted on website', 'corido-vendor-tracker' ); ?>">
					</div>
				</div>

				<!-- Pricing -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Pricing', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="market_value"><?php esc_html_e( 'Market Value (KES)', 'corido-vendor-tracker' ); ?></label>
							<input type="number" id="market_value" name="market_value" class="widefat" min="0" step="0.01"
								value="<?php echo esc_attr( $item->market_value ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Vendor\'s estimate', 'corido-vendor-tracker' ); ?>">
						</div>
						<div class="cvt-field">
							<label for="selling_price"><?php esc_html_e( 'Selling Price (KES)', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<input type="number" id="selling_price" name="selling_price" class="widefat" required min="0" step="0.01"
								value="<?php echo esc_attr( $item->selling_price ?? '' ); ?>">
						</div>
					</div>

					<!-- Live payout preview -->
					<div class="cvt-payout-preview" id="cvt-payout-preview">
						<div class="cvt-payout-preview-row">
							<span><?php esc_html_e( 'Commission', 'corido-vendor-tracker' ); ?>
								(<span id="preview-rate"><?php echo esc_html( $comm_rate ); ?></span>%)
							</span>
							<strong id="preview-commission"><?php echo esc_html( CVT_Settings::format_currency( $calcs['commission'] ) ); ?></strong>
						</div>
						<div class="cvt-payout-preview-row cvt-payout-preview-row--total">
							<span><?php esc_html_e( 'Vendor Payout', 'corido-vendor-tracker' ); ?></span>
							<strong id="preview-payout"><?php echo esc_html( CVT_Settings::format_currency( $calcs['payout'] ) ); ?></strong>
						</div>
					</div>
				</div>

				<!-- Images -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Images', 'corido-vendor-tracker' ); ?></h2>
					<div id="cvt-image-grid" class="cvt-image-grid">
						<?php foreach ( $images as $img ) :
							$thumb = wp_get_attachment_image_url( $img->attachment_id, 'thumbnail' );
						?>
						<div class="cvt-image-thumb" data-row-id="<?php echo esc_attr( $img->id ); ?>" data-item-id="<?php echo esc_attr( $item_id ); ?>">
							<img src="<?php echo esc_url( $thumb ); ?>" alt="">
							<button type="button" class="cvt-image-remove" title="<?php esc_attr_e( 'Remove', 'corido-vendor-tracker' ); ?>">×</button>
						</div>
						<?php endforeach; ?>
					</div>
					<button type="button" id="cvt-add-image" class="button">
						<?php esc_html_e( 'Add Image', 'corido-vendor-tracker' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Images are saved immediately when added. Removing them also takes effect straight away.', 'corido-vendor-tracker' ); ?></p>
				</div>

			</div><!-- .cvt-form-main -->

			<div class="cvt-form-sidebar">

				<!-- Vendor -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Vendor', 'corido-vendor-tracker' ); ?> <span class="required">*</span></h2>
					<div class="cvt-field">
						<input type="text" id="cvt-vendor-search" class="widefat"
							placeholder="<?php esc_attr_e( 'Type name or phone…', 'corido-vendor-tracker' ); ?>"
							value="<?php echo $preselect_vendor ? esc_attr( $preselect_vendor->name . ' (' . $preselect_vendor->phone_primary . ')' ) : ''; ?>"
							autocomplete="off">
						<input type="hidden" id="vendor_id" name="vendor_id" required
							value="<?php echo esc_attr( $preselect_vendor_id ); ?>">
						<div id="cvt-vendor-suggestions" class="cvt-suggestions" hidden></div>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=add' ) ); ?>" class="button button-small" target="_blank">
						+ <?php esc_html_e( 'Create new vendor', 'corido-vendor-tracker' ); ?>
					</a>
				</div>

				<!-- Assignment -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Assigned Agent', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field">
						<select id="assigned_agent_id" name="assigned_agent_id" class="widefat">
							<option value=""><?php esc_html_e( '— Unassigned —', 'corido-vendor-tracker' ); ?></option>
							<?php foreach ( $agents as $agent ) : ?>
							<option value="<?php echo esc_attr( $agent->ID ); ?>"
								<?php selected( $item->assigned_agent_id ?? get_current_user_id(), $agent->ID ); ?>>
								<?php echo esc_html( $agent->display_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Save -->
				<div class="cvt-card">
					<button type="submit" class="button button-primary button-hero cvt-submit-btn">
						<?php echo $is_edit
							? esc_html__( 'Update Item', 'corido-vendor-tracker' )
							: esc_html__( 'Save Item', 'corido-vendor-tracker' ); ?>
					</button>
					<?php if ( $is_edit ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=view&id=' . $item_id ) ); ?>"
						class="button cvt-cancel-btn">
						<?php esc_html_e( 'Cancel', 'corido-vendor-tracker' ); ?>
					</a>
					<?php endif; ?>
				</div>

			</div><!-- .cvt-form-sidebar -->
		</div><!-- .cvt-form-grid -->
	</form>
</div>
