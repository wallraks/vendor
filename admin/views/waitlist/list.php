<?php defined( 'ABSPATH' ) || exit;

$agents     = CVT_Roles::get_agents();
$categories = CVT_Settings::categories();

// Filters from query string.
$filter_status   = sanitize_key( $_GET['status']   ?? '' );
$filter_category = sanitize_text_field( $_GET['category'] ?? '' );
$filter_agent    = absint( $_GET['agent_id'] ?? 0 );
$filter_search   = sanitize_text_field( $_GET['s'] ?? '' );
$paged           = max( 1, absint( $_GET['paged'] ?? 1 ) );

$result = CVT_Waitlist::get_all( array(
	'search'   => $filter_search,
	'status'   => $filter_status,
	'category' => $filter_category,
	'agent_id' => $filter_agent,
	'per_page' => 20,
	'paged'    => $paged,
) );

$entries    = $result['items'];
$total      = $result['total'];
$total_pages = ceil( $total / 20 );

$status_labels = array(
	'open'      => array( 'label' => 'Open',      'class' => 'cvt-badge--review' ),
	'matched'   => array( 'label' => 'Matched',   'class' => 'cvt-badge--inquiry' ),
	'fulfilled' => array( 'label' => 'Fulfilled', 'class' => 'cvt-badge--sold' ),
	'cancelled' => array( 'label' => 'Cancelled', 'class' => 'cvt-badge--withdrawn' ),
);
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Waiting List', 'corido-vendor-tracker' ); ?></h1>
		<div class="cvt-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&action=add' ) ); ?>" class="button button-primary">
				+ <?php esc_html_e( 'Add Entry', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<!-- Filters -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="cvt-filter-bar">
		<input type="hidden" name="page" value="cvt-waitlist">
		<input type="text" name="s" value="<?php echo esc_attr( $filter_search ); ?>"
			placeholder="<?php esc_attr_e( 'Search name, phone, description…', 'corido-vendor-tracker' ); ?>"
			class="cvt-filter-search">

		<select name="status">
			<option value=""><?php esc_html_e( 'All statuses', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( $status_labels as $slug => $info ) : ?>
			<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filter_status, $slug ); ?>>
				<?php echo esc_html( $info['label'] ); ?>
			</option>
			<?php endforeach; ?>
		</select>

		<?php if ( $categories ) : ?>
		<select name="category">
			<option value=""><?php esc_html_e( 'All categories', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( $categories as $cat ) : ?>
			<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $filter_category, $cat ); ?>>
				<?php echo esc_html( $cat ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php endif; ?>

		<?php if ( $agents ) : ?>
		<select name="agent_id">
			<option value=""><?php esc_html_e( 'All agents', 'corido-vendor-tracker' ); ?></option>
			<?php foreach ( $agents as $agent ) : ?>
			<option value="<?php echo esc_attr( $agent->ID ); ?>" <?php selected( $filter_agent, $agent->ID ); ?>>
				<?php echo esc_html( $agent->display_name ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php endif; ?>

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'corido-vendor-tracker' ); ?></button>
		<?php if ( $filter_status || $filter_category || $filter_search || $filter_agent ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'corido-vendor-tracker' ); ?></a>
		<?php endif; ?>
	</form>

	<div class="cvt-card" style="margin-top:16px;">
		<?php if ( empty( $entries ) ) : ?>
		<p class="cvt-empty"><?php esc_html_e( 'No waiting list entries found.', 'corido-vendor-tracker' ); ?></p>
		<?php else : ?>
		<table class="cvt-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Client', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Looking For', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Budget (KES)', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Agent', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Added', 'corido-vendor-tracker' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) :
					$si    = $status_labels[ $entry->status ] ?? array( 'label' => $entry->status, 'class' => '' );
					$budget = '';
					if ( $entry->budget_min && $entry->budget_max ) {
						$budget = CVT_Settings::format_currency( $entry->budget_min ) . ' – ' . CVT_Settings::format_currency( $entry->budget_max );
					} elseif ( $entry->budget_max ) {
						$budget = 'up to ' . CVT_Settings::format_currency( $entry->budget_max );
					} elseif ( $entry->budget_min ) {
						$budget = 'from ' . CVT_Settings::format_currency( $entry->budget_min );
					}
				?>
				<tr>
					<td><strong><?php echo esc_html( $entry->client_name ); ?></strong></td>
					<td>
						<a href="tel:<?php echo esc_attr( $entry->phone ); ?>"><?php echo esc_html( $entry->phone ); ?></a>
					</td>
					<td>
						<?php if ( $entry->category ) : ?>
						<span class="cvt-role-chip"><?php echo esc_html( $entry->category ); ?></span>
						<?php endif; ?>
						<?php echo esc_html( wp_trim_words( $entry->description ?? '', 12, '…' ) ); ?>
					</td>
					<td><?php echo $budget ? esc_html( $budget ) : '—'; ?></td>
					<td><?php echo esc_html( $entry->quantity ); ?></td>
					<td>
						<span class="cvt-badge <?php echo esc_attr( $si['class'] ); ?>">
							<?php echo esc_html( $si['label'] ); ?>
						</span>
						<?php if ( $entry->matched_item_id ) : ?>
						<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=view&id=' . $entry->matched_item_id ) ); ?>" class="cvt-muted" style="font-size:11px;">
							<?php esc_html_e( 'View matched item', 'corido-vendor-tracker' ); ?>
						</a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $entry->agent_name ?: '—' ); ?></td>
					<td><?php echo esc_html( date_i18n( 'd M Y', strtotime( $entry->created_at ) ) ); ?></td>
					<td class="cvt-row-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&action=edit&id=' . $entry->id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Edit', 'corido-vendor-tracker' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url(
							admin_url( 'admin-post.php?action=cvt_delete_waitlist&id=' . $entry->id ),
							'cvt_delete_waitlist'
						) ); ?>" class="button button-small cvt-delete-link">
							<?php esc_html_e( 'Delete', 'corido-vendor-tracker' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				$base_url = admin_url( 'admin.php?page=cvt-waitlist'
					. ( $filter_status   ? '&status=' . urlencode( $filter_status )   : '' )
					. ( $filter_category ? '&category=' . urlencode( $filter_category ) : '' )
					. ( $filter_search   ? '&s=' . urlencode( $filter_search )         : '' ) );
				echo paginate_links( array(
					'base'      => $base_url . '&paged=%#%',
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				) );
				?>
			</div>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
