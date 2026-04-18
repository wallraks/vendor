<?php defined( 'ABSPATH' ) || exit;

$item_id    = absint( $_GET['id'] ?? 0 );
$item       = $item_id ? CVT_Item::get( $item_id ) : null;
$is_edit    = (bool) $item;
$page_title = $is_edit
	? __( 'Edit Item', 'corido-vendor-tracker' )
	: __( 'Add Item', 'corido-vendor-tracker' );

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

$categories      = CVT_Settings::categories();
$agents          = CVT_Roles::get_agents();
$images          = $is_edit ? CVT_Item::get_images( $item_id ) : array();
$global_rate     = CVT_Settings::commission_rate();
$default_fee     = CVT_Settings::default_listing_fee();
// Item-level rate: explicit value on edit, null on add (will inherit global).
$item_rate       = ( $is_edit && $item->commission_rate !== null ) ? (float) $item->commission_rate : null;
$eff_rate        = $item_rate ?? $global_rate;
$comm_rate       = $global_rate; // kept for legacy template references
$selling_price   = (float) ( $item->selling_price ?? 0 );
$is_listing      = ( $is_edit && $item->deal_type === 'listing' );
$item_fee        = ( $is_edit && $item->listing_fee !== null ) ? (float) $item->listing_fee : null;
$calcs           = $is_listing
	? array( 'commission' => (float) ( $item->listing_fee ?? 0 ), 'payout' => max( 0, $selling_price - (float) ( $item->listing_fee ?? 0 ) ) )
	: CVT_Payout::calculate( $selling_price, $eff_rate );
$agreement_id    = (int) ( $item->agreement_attachment_id ?? 0 );
$agreement_url   = $agreement_id ? wp_get_attachment_url( $agreement_id ) : '';
$agreement_title = $agreement_id ? get_the_title( $agreement_id ) : '';
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Items', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $page_title ); ?>
		</h1>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cvt-form" id="cvt-item-form">
		<?php wp_nonce_field( 'cvt_save_item' ); ?>
		<input type="hidden" name="action"  value="cvt_save_item">
		<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>">
		<input type="hidden" name="cvt_image_ids"          id="cvt_image_ids"          value="">
		<input type="hidden" name="agreement_attachment_id" id="cvt-agreement-att-id"
			value="<?php echo esc_attr( $agreement_id ?: '' ); ?>">
		<?php if ( ! $is_edit ) : ?>
		<!-- Populated by JS when a listing is selected -->
		<input type="hidden" name="description"         id="cvt-field-description">
		<input type="hidden" name="listivo_listing_url" id="cvt-field-listing-url">
		<?php endif; ?>

		<div class="cvt-form-grid">
			<div class="cvt-form-main">

			<?php if ( ! $is_edit ) : ?>
				<!-- ============================================================
				     ADD MODE
				     ============================================================ -->

				<!-- Step 1: Search for the Listivo listing -->
				<div class="cvt-card cvt-listing-search-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Search Listing', 'corido-vendor-tracker' ); ?></h2>

					<!-- Search input row -->
					<div class="cvt-field" id="cvt-listing-search-state">
						<div id="cvt-listing-search-wrap" style="position:relative;">
							<input type="text" id="cvt-listing-search" class="widefat"
								placeholder="<?php esc_attr_e( 'Start typing the item name…', 'corido-vendor-tracker' ); ?>"
								autocomplete="off">
							<div id="cvt-listing-suggestions" class="cvt-suggestions" hidden></div>
						</div>
						<p class="description" style="margin-top:6px;">
							<?php esc_html_e( 'Search published listings from the website. Fields below fill in automatically when you select one.', 'corido-vendor-tracker' ); ?>
						</p>
					</div>

					<!-- Selected listing chip (shown after selection) -->
					<div id="cvt-listing-selected" class="cvt-listing-chip" hidden>
						<img id="cvt-listing-thumb" src="" alt="" class="cvt-listing-thumb-img" hidden>
						<div class="cvt-listing-chip-body">
							<span class="cvt-listing-chip-title" id="cvt-listing-chip-title"></span>
							<a id="cvt-listing-chip-url" href="#" target="_blank" rel="noopener" class="cvt-muted">
								<?php esc_html_e( 'View on website ↗', 'corido-vendor-tracker' ); ?>
							</a>
						</div>
						<button type="button" id="cvt-listing-change" class="button button-small">
							<?php esc_html_e( 'Change', 'corido-vendor-tracker' ); ?>
						</button>
					</div>
				</div>

				<!-- Step 2: Item details — populated from listing, editable -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Item Details', 'corido-vendor-tracker' ); ?></h2>

					<div class="cvt-field">
						<label for="title"><?php esc_html_e( 'Item Title', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
						<input type="text" id="title" name="title" class="widefat" required
							placeholder="<?php esc_attr_e( 'Auto-filled when you select a listing above', 'corido-vendor-tracker' ); ?>">
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="category"><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></label>
							<select id="category" name="category" class="widefat">
								<option value=""><?php esc_html_e( '— Auto-filled from listing —', 'corido-vendor-tracker' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat ); ?>">
									<?php echo esc_html( $cat ); ?>
								</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cvt-field">
							<label for="deal_type"><?php esc_html_e( 'Deal Type', 'corido-vendor-tracker' ); ?></label>
							<select id="deal_type" name="deal_type" class="widefat">
								<option value="consignment"><?php esc_html_e( 'Consignment', 'corido-vendor-tracker' ); ?></option>
								<option value="agency"><?php esc_html_e( 'Agency', 'corido-vendor-tracker' ); ?></option>
								<option value="listing"><?php esc_html_e( 'Listing', 'corido-vendor-tracker' ); ?></option>
							</select>
						</div>
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="selling_price"><?php esc_html_e( 'Selling Price (KES)', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<input type="number" id="selling_price" name="selling_price" class="widefat" required min="0" step="0.01"
								placeholder="<?php esc_attr_e( 'Auto-filled from listing price', 'corido-vendor-tracker' ); ?>">
						</div>
						<div class="cvt-field">
							<label for="market_value"><?php esc_html_e( 'Market Value (KES)', 'corido-vendor-tracker' ); ?></label>
							<input type="number" id="market_value" name="market_value" class="widefat" min="0" step="0.01"
								placeholder="<?php esc_attr_e( 'Vendor\'s estimate', 'corido-vendor-tracker' ); ?>">
						</div>
					</div>

					<div id="cvt-commission-rate-wrap">
						<div class="cvt-field-row">
							<div class="cvt-field">
								<label for="commission_rate"><?php esc_html_e( 'Commission Rate (%)', 'corido-vendor-tracker' ); ?></label>
								<input type="number" id="commission_rate" name="commission_rate" class="widefat" min="0" max="100" step="0.01"
									value=""
									placeholder="<?php echo esc_attr( $global_rate ); ?>">
								<p class="description">
									<?php echo esc_html( sprintf(
										__( 'Leave blank to use the global default (%s%%).', 'corido-vendor-tracker' ),
										$global_rate
									) ); ?>
								</p>
							</div>
						</div>
					</div>

					<div id="cvt-listing-fee-wrap" style="display:none;">
						<div class="cvt-field-row">
							<div class="cvt-field">
								<label for="listing_fee"><?php esc_html_e( 'Listing Fee (KES)', 'corido-vendor-tracker' ); ?></label>
								<input type="number" id="listing_fee" name="listing_fee" class="widefat" min="0" step="0.01"
									value=""
									placeholder="<?php echo esc_attr( $default_fee ); ?>">
								<p class="description">
									<?php echo esc_html( sprintf(
										__( 'Leave blank or 0 for a free listing. Default: KES %s.', 'corido-vendor-tracker' ),
										number_format( $default_fee, 0 )
									) ); ?>
								</p>
							</div>
						</div>
					</div>

					<!-- Live payout preview -->
					<div class="cvt-payout-preview" id="cvt-payout-preview">
						<div class="cvt-payout-preview-row">
							<span>
								<span id="preview-commission-label"><?php esc_html_e( 'Commission', 'corido-vendor-tracker' ); ?> (<span id="preview-rate"><?php echo esc_html( $global_rate ); ?></span>%)</span>
								<span id="preview-listing-fee-label" style="display:none;"><?php esc_html_e( 'Listing Fee', 'corido-vendor-tracker' ); ?></span>
							</span>
							<strong id="preview-commission"><?php echo esc_html( CVT_Settings::format_currency( 0 ) ); ?></strong>
						</div>
						<div class="cvt-payout-preview-row cvt-payout-preview-row--total">
							<span><?php esc_html_e( 'Vendor Payout', 'corido-vendor-tracker' ); ?></span>
							<strong id="preview-payout"><?php echo esc_html( CVT_Settings::format_currency( 0 ) ); ?></strong>
						</div>
					</div>

					<div class="cvt-field-row" style="margin-top:16px;">
						<div class="cvt-field">
							<label for="date_received"><?php esc_html_e( 'Date Received', 'corido-vendor-tracker' ); ?></label>
							<input type="date" id="date_received" name="date_received" class="widefat"
								value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
						</div>
						<div class="cvt-field">
							<label for="notes"><?php esc_html_e( 'Internal Notes', 'corido-vendor-tracker' ); ?></label>
							<textarea id="notes" name="notes" rows="3" class="widefat"
								placeholder="<?php esc_attr_e( 'Condition, agent observations…', 'corido-vendor-tracker' ); ?>"></textarea>
						</div>
					</div>
				</div>

			<?php else : ?>
				<!-- ============================================================
				     EDIT MODE — All fields editable
				     ============================================================ -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Item Details', 'corido-vendor-tracker' ); ?></h2>

					<?php if ( $item->listivo_listing_url ) : ?>
					<div class="cvt-field cvt-listing-link-field">
						<label><?php esc_html_e( 'Website Listing', 'corido-vendor-tracker' ); ?></label>
						<a href="<?php echo esc_url( $item->listivo_listing_url ); ?>" target="_blank" rel="noopener" class="cvt-listing-ext-link">
							<?php echo esc_html( $item->title ); ?> ↗
						</a>
					</div>
					<?php endif; ?>

					<div class="cvt-field">
						<label for="title"><?php esc_html_e( 'Title', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
						<input type="text" id="title" name="title" required class="widefat"
							value="<?php echo esc_attr( $item->title ); ?>">
					</div>

					<div class="cvt-field">
						<label for="description"><?php esc_html_e( 'Description', 'corido-vendor-tracker' ); ?></label>
						<textarea id="description" name="description" rows="4" class="widefat"><?php echo esc_textarea( $item->description ); ?></textarea>
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="category"><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></label>
							<select id="category" name="category" class="widefat">
								<option value=""><?php esc_html_e( '— Select —', 'corido-vendor-tracker' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $item->category, $cat ); ?>>
									<?php echo esc_html( $cat ); ?>
								</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cvt-field">
							<label for="deal_type"><?php esc_html_e( 'Deal Type', 'corido-vendor-tracker' ); ?></label>
							<select id="deal_type" name="deal_type" class="widefat">
								<option value="consignment" <?php selected( $item->deal_type, 'consignment' ); ?>><?php esc_html_e( 'Consignment', 'corido-vendor-tracker' ); ?></option>
								<option value="agency"      <?php selected( $item->deal_type, 'agency' );      ?>><?php esc_html_e( 'Agency', 'corido-vendor-tracker' ); ?></option>
								<option value="listing"     <?php selected( $item->deal_type, 'listing' );     ?>><?php esc_html_e( 'Listing', 'corido-vendor-tracker' ); ?></option>
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
							<textarea id="notes" name="notes" rows="3" class="widefat"><?php echo esc_textarea( $item->notes ); ?></textarea>
						</div>
					</div>

					<div class="cvt-field">
						<label for="listivo_listing_url"><?php esc_html_e( 'Listivo Listing URL', 'corido-vendor-tracker' ); ?></label>
						<input type="url" id="listivo_listing_url" name="listivo_listing_url" class="widefat"
							value="<?php echo esc_attr( $item->listivo_listing_url ?? '' ); ?>"
							placeholder="https://...">
					</div>
				</div>

				<!-- EDIT MODE — Pricing -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Pricing', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="selling_price"><?php esc_html_e( 'Selling Price (KES)', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<input type="number" id="selling_price" name="selling_price" class="widefat" required min="0" step="0.01"
								value="<?php echo esc_attr( $item->selling_price ); ?>">
						</div>
						<div class="cvt-field">
							<label for="market_value"><?php esc_html_e( 'Market Value (KES)', 'corido-vendor-tracker' ); ?></label>
							<input type="number" id="market_value" name="market_value" class="widefat" min="0" step="0.01"
								value="<?php echo esc_attr( $item->market_value ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Vendor\'s estimate', 'corido-vendor-tracker' ); ?>">
						</div>
					</div>

					<div id="cvt-commission-rate-wrap"<?php echo $is_listing ? ' style="display:none;"' : ''; ?>>
						<div class="cvt-field-row">
							<div class="cvt-field">
								<label for="commission_rate"><?php esc_html_e( 'Commission Rate (%)', 'corido-vendor-tracker' ); ?></label>
								<input type="number" id="commission_rate" name="commission_rate" class="widefat" min="0" max="100" step="0.01"
									value="<?php echo esc_attr( $item_rate !== null ? $item_rate : '' ); ?>"
									placeholder="<?php echo esc_attr( $global_rate ); ?>">
								<p class="description">
									<?php echo esc_html( sprintf(
										__( 'Leave blank to use the global default (%s%%). Changing this will be logged.', 'corido-vendor-tracker' ),
										$global_rate
									) ); ?>
								</p>
							</div>
						</div>
					</div>

					<div id="cvt-listing-fee-wrap"<?php echo $is_listing ? '' : ' style="display:none;"'; ?>>
						<div class="cvt-field-row">
							<div class="cvt-field">
								<label for="listing_fee"><?php esc_html_e( 'Listing Fee (KES)', 'corido-vendor-tracker' ); ?></label>
								<input type="number" id="listing_fee" name="listing_fee" class="widefat" min="0" step="0.01"
									value="<?php echo esc_attr( $item_fee !== null ? $item_fee : '' ); ?>"
									placeholder="<?php echo esc_attr( $default_fee ); ?>">
								<p class="description">
									<?php echo esc_html( sprintf(
										__( 'Leave blank or 0 for a free listing. Default: KES %s.', 'corido-vendor-tracker' ),
										number_format( $default_fee, 0 )
									) ); ?>
								</p>
							</div>
						</div>
					</div>

					<div class="cvt-payout-preview" id="cvt-payout-preview">
						<div class="cvt-payout-preview-row">
							<span>
								<span id="preview-commission-label"<?php echo $is_listing ? ' style="display:none;"' : ''; ?>><?php esc_html_e( 'Commission', 'corido-vendor-tracker' ); ?> (<span id="preview-rate"><?php echo esc_html( $eff_rate ); ?></span>%)</span>
								<span id="preview-listing-fee-label"<?php echo $is_listing ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Listing Fee', 'corido-vendor-tracker' ); ?></span>
							</span>
							<strong id="preview-commission"><?php echo esc_html( CVT_Settings::format_currency( $calcs['commission'] ) ); ?></strong>
						</div>
						<div class="cvt-payout-preview-row cvt-payout-preview-row--total">
							<span><?php esc_html_e( 'Vendor Payout', 'corido-vendor-tracker' ); ?></span>
							<strong id="preview-payout"><?php echo esc_html( CVT_Settings::format_currency( $calcs['payout'] ) ); ?></strong>
						</div>
					</div>

					<!-- Price-change note — revealed by JS when selling_price changes in edit mode -->
					<div class="cvt-field" id="cvt-price-note-wrap" style="display:none;margin-top:12px;">
						<label for="price_change_note">
							<?php esc_html_e( 'Reason for price change', 'corido-vendor-tracker' ); ?>
						</label>
						<input type="text" id="price_change_note" name="price_change_note" class="widefat"
							placeholder="<?php esc_attr_e( 'e.g. Vendor renegotiated, market adjustment…', 'corido-vendor-tracker' ); ?>">
						<input type="hidden" id="cvt-original-price"
							value="<?php echo esc_attr( $item->selling_price ?? '' ); ?>">
					</div>
				</div>

			<?php endif; ?>

				<!-- Images (both modes) -->
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

				<!-- Assigned Agent -->
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

				<!-- Agreement -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Consignment Agreement', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field">
						<?php if ( $agreement_url ) : ?>
						<div id="cvt-agreement-selected" class="cvt-agreement-chip">
							<span class="dashicons dashicons-media-document"></span>
							<a href="<?php echo esc_url( $agreement_url ); ?>" target="_blank" rel="noopener" class="cvt-agreement-chip-link">
								<?php echo esc_html( $agreement_title ?: basename( $agreement_url ) ); ?> ↗
							</a>
							<button type="button" id="cvt-agreement-remove" class="button button-small">
								<?php esc_html_e( 'Remove', 'corido-vendor-tracker' ); ?>
							</button>
						</div>
						<?php else : ?>
						<div id="cvt-agreement-selected" class="cvt-agreement-chip" style="display:none;"></div>
						<?php endif; ?>
						<button type="button" id="cvt-add-agreement" class="button"
							<?php echo $agreement_url ? 'style="display:none;"' : ''; ?>>
							<?php esc_html_e( 'Upload / Select Agreement', 'corido-vendor-tracker' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Upload the signed consignment agreement (PDF or image). To reuse an agreement across multiple items, open the Media Library and select the previously uploaded file.', 'corido-vendor-tracker' ); ?>
						</p>
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
