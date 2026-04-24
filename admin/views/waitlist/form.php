<?php defined( 'ABSPATH' ) || exit;

$entry_id   = absint( $_GET['id'] ?? 0 );
$entry      = $entry_id ? CVT_Waitlist::get( $entry_id ) : null;
$is_edit    = (bool) $entry;
$title      = $is_edit ? __( 'Edit Entry', 'corido-vendor-tracker' ) : __( 'Add Waiting List Entry', 'corido-vendor-tracker' );
$agents     = CVT_Roles::get_agents();
$categories = CVT_Settings::categories_structured();

// Pre-load items for the table: use request_items if set, else migrate old fields.
$form_items = CVT_Waitlist::decode_items( $entry->request_items ?? '' );
if ( empty( $form_items ) && $is_edit ) {
	// Old entry — seed the first table row from legacy fields.
	$form_items = array( array(
		'desc'       => $entry->description ?? '',
		'category'   => $entry->category ?? '',
		'budget_max' => $entry->budget_max ?? null,
		'qty'        => $entry->quantity ?? 1,
	) );
}
if ( empty( $form_items ) ) {
	$form_items = array( array( 'desc' => '', 'category' => '', 'budget_max' => null, 'qty' => 1 ) );
}
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="cvt-back-link">
				← <?php esc_html_e( 'Waiting List', 'corido-vendor-tracker' ); ?>
			</a>
			<?php echo esc_html( $title ); ?>
		</h1>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cvt-form">
		<?php wp_nonce_field( 'cvt_save_waitlist' ); ?>
		<input type="hidden" name="action"      value="cvt_save_waitlist">
		<input type="hidden" name="waitlist_id" value="<?php echo esc_attr( $entry_id ); ?>">

		<div class="cvt-form-grid">
			<div class="cvt-form-main">

				<!-- Client details -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Client Details', 'corido-vendor-tracker' ); ?></h2>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="client_name"><?php esc_html_e( 'Client Name', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<input type="text" id="client_name" name="client_name" required class="widefat"
								value="<?php echo esc_attr( $entry->client_name ?? '' ); ?>">
						</div>
						<div class="cvt-field">
							<label for="phone"><?php esc_html_e( 'Phone', 'corido-vendor-tracker' ); ?> <span class="required">*</span></label>
							<input type="tel" id="phone" name="phone" required class="widefat"
								value="<?php echo esc_attr( $entry->phone ?? '' ); ?>"
								placeholder="+254 7XX XXX XXX">
						</div>
					</div>

					<div class="cvt-field-row">
						<div class="cvt-field">
							<label for="email"><?php esc_html_e( 'Email', 'corido-vendor-tracker' ); ?></label>
							<input type="email" id="email" name="email" class="widefat"
								value="<?php echo esc_attr( $entry->email ?? '' ); ?>">
						</div>
						<div class="cvt-field">
							<label for="timeframe"><?php esc_html_e( 'Timeframe', 'corido-vendor-tracker' ); ?></label>
							<input type="text" id="timeframe" name="timeframe" class="widefat"
								value="<?php echo esc_attr( $entry->timeframe ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. ASAP, within 2 weeks', 'corido-vendor-tracker' ); ?>">
						</div>
					</div>
				</div>

				<!-- Items requested -->
				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Items Requested', 'corido-vendor-tracker' ); ?></h2>

					<div class="cvt-items-table-wrap">
						<table class="cvt-items-table" id="cvt-items-table">
							<thead>
								<tr>
									<th class="col-desc"><?php esc_html_e( 'Item / Description', 'corido-vendor-tracker' ); ?> <span class="required">*</span></th>
									<th class="col-cat"><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></th>
									<th class="col-budget"><?php esc_html_e( 'Budget (KES)', 'corido-vendor-tracker' ); ?></th>
									<th class="col-qty"><?php esc_html_e( 'Qty', 'corido-vendor-tracker' ); ?></th>
									<th class="col-del"></th>
								</tr>
							</thead>
							<tbody id="cvt-items-body">
								<?php foreach ( $form_items as $item ) : ?>
								<tr class="cvt-item-row">
									<td class="col-desc">
										<input type="text" name="item_desc[]" class="widefat"
											value="<?php echo esc_attr( $item['desc'] ?? '' ); ?>"
											placeholder="<?php esc_attr_e( 'e.g. Samsung Washing Machine', 'corido-vendor-tracker' ); ?>">
									</td>
									<td class="col-cat">
										<select name="item_category[]" class="widefat">
											<option value=""><?php esc_html_e( '— Any —', 'corido-vendor-tracker' ); ?></option>
											<?php foreach ( $categories as $cat ) :
												$pfx = $cat['depth'] > 0 ? str_repeat( "\u{00a0}", 3 ) . '↳ ' : '';
											?>
											<option value="<?php echo esc_attr( $cat['name'] ); ?>"
												<?php selected( $item['category'] ?? '', $cat['name'] ); ?>>
												<?php echo esc_html( $pfx . $cat['name'] ); ?>
											</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="col-budget">
										<input type="number" name="item_budget_max[]" min="0" step="1"
											value="<?php echo esc_attr( $item['budget_max'] ?? '' ); ?>"
											placeholder="<?php esc_attr_e( 'Max', 'corido-vendor-tracker' ); ?>">
									</td>
									<td class="col-qty">
										<input type="number" name="item_qty[]" min="1" step="1"
											value="<?php echo esc_attr( $item['qty'] ?? 1 ); ?>">
									</td>
									<td class="col-del">
										<button type="button" class="cvt-item-del" title="<?php esc_attr_e( 'Remove row', 'corido-vendor-tracker' ); ?>">×</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<button type="button" class="button cvt-add-item-row" id="cvt-add-item-row">
						+ <?php esc_html_e( 'Add Item', 'corido-vendor-tracker' ); ?>
					</button>

					<!-- Tags -->
					<?php
					$defined_tags  = CVT_Settings::waitlist_tags();
					$selected_tags = CVT_Waitlist::decode_tags( $entry->tags ?? '' );
					?>
					<?php if ( ! empty( $defined_tags ) ) : ?>
					<div class="cvt-field" style="margin-top:20px;">
						<label><?php esc_html_e( 'Tags', 'corido-vendor-tracker' ); ?></label>
						<div class="cvt-tag-picker" id="cvt-tag-picker">
							<?php foreach ( $defined_tags as $tag ) :
								$checked = in_array( $tag, $selected_tags, true );
							?>
							<label class="cvt-tag-toggle<?php echo $checked ? ' cvt-tag-toggle--on' : ''; ?>">
								<input type="checkbox" name="tags[]"
									value="<?php echo esc_attr( $tag ); ?>"
									<?php checked( $checked ); ?>>
								<span class="cvt-tag-toggle-label"><?php echo esc_html( $tag ); ?></span>
								<span class="cvt-tag-toggle-x" aria-hidden="true">×</span>
							</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Click to select. Click again to remove.', 'corido-vendor-tracker' ); ?></p>
					</div>
					<?php endif; ?>

					<!-- Notes -->
					<div class="cvt-field" style="margin-top:16px;">
						<label for="notes"><?php esc_html_e( 'Internal Notes', 'corido-vendor-tracker' ); ?></label>
						<textarea id="notes" name="notes" rows="3" class="widefat"
							placeholder="<?php esc_attr_e( 'Source of lead, follow-up notes…', 'corido-vendor-tracker' ); ?>"><?php echo esc_textarea( $entry->notes ?? '' ); ?></textarea>
					</div>
				</div>

			</div><!-- .cvt-form-main -->

			<div class="cvt-form-sidebar">

				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Status', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field">
						<select id="status" name="status" class="widefat">
							<?php foreach ( array( 'open', 'matched', 'fulfilled', 'cancelled' ) as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $entry->status ?? 'open', $s ); ?>>
								<?php echo esc_html( ucfirst( $s ) ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="cvt-card">
					<h2 class="cvt-card-title"><?php esc_html_e( 'Assigned Agent', 'corido-vendor-tracker' ); ?></h2>
					<div class="cvt-field">
						<select id="assigned_agent_id" name="assigned_agent_id" class="widefat">
							<option value=""><?php esc_html_e( '— Unassigned —', 'corido-vendor-tracker' ); ?></option>
							<?php foreach ( $agents as $agent ) : ?>
							<option value="<?php echo esc_attr( $agent->ID ); ?>"
								<?php selected( $entry->assigned_agent_id ?? get_current_user_id(), $agent->ID ); ?>>
								<?php echo esc_html( $agent->display_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="cvt-card">
					<button type="submit" class="button button-primary button-hero cvt-submit-btn">
						<?php echo $is_edit
							? esc_html__( 'Update Entry', 'corido-vendor-tracker' )
							: esc_html__( 'Save Entry', 'corido-vendor-tracker' ); ?>
					</button>
					<?php if ( $is_edit ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="button cvt-cancel-btn">
						<?php esc_html_e( 'Cancel', 'corido-vendor-tracker' ); ?>
					</a>
					<?php endif; ?>
				</div>

			</div><!-- .cvt-form-sidebar -->
		</div>
	</form>
</div>
<script>
(function () {
	var tbody  = document.getElementById('cvt-items-body');
	var addBtn = document.getElementById('cvt-add-item-row');

	function attachDel(row) {
		row.querySelector('.cvt-item-del').addEventListener('click', function () {
			if (tbody.querySelectorAll('.cvt-item-row').length > 1) {
				row.remove();
			}
		});
	}

	document.querySelectorAll('.cvt-item-row').forEach(attachDel);

	if (addBtn) {
		addBtn.addEventListener('click', function () {
			var last = tbody.querySelector('.cvt-item-row:last-child');
			var row  = last.cloneNode(true);
			row.querySelectorAll('input').forEach(function (inp) {
				inp.value = (inp.getAttribute('name') === 'item_qty[]') ? '1' : '';
			});
			row.querySelectorAll('select').forEach(function (sel) { sel.selectedIndex = 0; });
			tbody.appendChild(row);
			attachDel(row);
			row.querySelector('input[name="item_desc[]"]').focus();
		});
	}

	// Tag chip picker.
	document.querySelectorAll('#cvt-tag-picker .cvt-tag-toggle').forEach(function (label) {
		var cb = label.querySelector('input[type="checkbox"]');
		label.addEventListener('click', function (e) {
			e.preventDefault();
			cb.checked = !cb.checked;
			label.classList.toggle('cvt-tag-toggle--on', cb.checked);
		});
	});
}());
</script>
