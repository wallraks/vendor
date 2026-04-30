<?php defined( 'ABSPATH' ) || exit;

$agents     = CVT_Roles::get_agents();
$categories = CVT_Settings::categories_structured();

// Filters from query string.
$filter_status   = sanitize_key( $_GET['status']   ?? '' );
$filter_category = sanitize_text_field( $_GET['category'] ?? '' );
$filter_agent    = absint( $_GET['agent_id'] ?? 0 );
$filter_search   = sanitize_text_field( $_GET['s'] ?? '' );
$filter_tag      = sanitize_text_field( $_GET['tag'] ?? '' );
$paged           = max( 1, absint( $_GET['paged'] ?? 1 ) );

$result = CVT_Waitlist::get_all( array(
	'search'   => $filter_search,
	'status'   => $filter_status,
	'category' => $filter_category,
	'tag'      => $filter_tag,
	'agent_id' => $filter_agent,
	'per_page' => 20,
	'paged'    => $paged,
) );

// Tag counts filtered to currently-defined tags only (open entries).
$defined_tags = CVT_Settings::waitlist_tags();
$all_tag_counts = CVT_Waitlist::get_tag_counts( 'open' );
$tag_counts = array();
foreach ( $defined_tags as $dtag ) {
	if ( isset( $all_tag_counts[ $dtag ] ) ) {
		$tag_counts[ $dtag ] = $all_tag_counts[ $dtag ];
	}
}

$entries     = $result['items'];
$total       = $result['total'];
$total_pages = ceil( $total / 20 );

$is_admin_user = current_user_can( 'cvt_manage_settings' );
$now_ts        = current_time( 'timestamp' );

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
			<?php if ( $is_admin_user && ! empty( $tag_counts ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'cvt_purge_waitlist_tags' ); ?>
				<input type="hidden" name="action" value="cvt_purge_waitlist_tags">
				<button type="submit" class="button cvt-purge-tags-btn"
					onclick="return confirm('<?php esc_attr_e( 'This removes tags no longer in the defined list from all entries. Continue?', 'corido-vendor-tracker' ); ?>')">
					<?php esc_html_e( 'Purge Old Tags', 'corido-vendor-tracker' ); ?>
				</button>
			</form>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&action=add' ) ); ?>" class="button button-primary">
				+ <?php esc_html_e( 'Add Entry', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<!-- Tag quick-filter pills -->
	<?php if ( ! empty( $tag_counts ) ) : ?>
	<div class="cvt-tag-filter-bar">
		<span class="cvt-tag-filter-label"><?php esc_html_e( 'Filter by tag:', 'corido-vendor-tracker' ); ?></span>
		<?php if ( $filter_tag ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>"
			class="cvt-tag-pill cvt-tag-pill--clear">
			<?php esc_html_e( '× All', 'corido-vendor-tracker' ); ?>
		</a>
		<?php endif; ?>
		<?php foreach ( $tag_counts as $tag => $count ) :
			$active = ( $filter_tag === $tag );
		?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&tag=' . urlencode( $tag ) ) ); ?>"
			class="cvt-tag-pill<?php echo $active ? ' cvt-tag-pill--active' : ''; ?>">
			<?php echo esc_html( $tag ); ?>
			<span class="cvt-tag-pill-count"><?php echo esc_html( $count ); ?></span>
		</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

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
			<?php foreach ( $categories as $cat ) :
				$prefix = $cat['depth'] > 0 ? str_repeat( "\u{00a0}", 3 ) . '↳ ' : '';
			?>
			<option value="<?php echo esc_attr( $cat['name'] ); ?>" <?php selected( $filter_category, $cat['name'] ); ?>>
				<?php echo esc_html( $prefix . $cat['name'] ); ?>
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

		<?php if ( $filter_tag ) : ?>
		<input type="hidden" name="tag" value="<?php echo esc_attr( $filter_tag ); ?>">
		<?php endif; ?>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'corido-vendor-tracker' ); ?></button>
		<?php if ( $filter_status || $filter_category || $filter_search || $filter_agent || $filter_tag ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'corido-vendor-tracker' ); ?></a>
		<?php endif; ?>
	</form>

	<!-- Entries table -->
	<div class="cvt-card" style="margin-top:12px;">
		<?php if ( empty( $entries ) ) : ?>
		<p class="cvt-empty"><?php esc_html_e( 'No waiting list entries found.', 'corido-vendor-tracker' ); ?></p>
		<?php else : ?>
		<table class="cvt-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Client', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Items Requested', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Agent', 'corido-vendor-tracker' ); ?></th>
					<th><?php esc_html_e( 'Added', 'corido-vendor-tracker' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) :
					$si         = $status_labels[ $entry->status ] ?? array( 'label' => $entry->status, 'class' => '' );
					$entry_tags = CVT_Waitlist::decode_tags( $entry->tags ?? '' );
					$req_items  = CVT_Waitlist::decode_items( $entry->request_items ?? '' );

					// Privacy: non-admins see blurred name and phone in list.
					if ( $is_admin_user ) {
						$display_name  = $entry->client_name;
						$display_phone = $entry->phone;
					} else {
						$display_name  = mb_substr( $entry->client_name, 0, 2 ) . '×××';
						$display_phone = mb_substr( $entry->phone, 0, 3 ) . '×××××';
					}

					// Relative time.
					$time_ago = human_time_diff( strtotime( $entry->created_at ), $now_ts ) . ' ' . __( 'ago', 'corido-vendor-tracker' );
					$date_full = date_i18n( 'd M Y H:i', strtotime( $entry->created_at ) );
				?>
				<tr>
					<td><strong><?php echo esc_html( $display_name ); ?></strong></td>
					<td>
						<?php if ( $is_admin_user ) : ?>
						<a href="tel:<?php echo esc_attr( $entry->phone ); ?>"><?php echo esc_html( $display_phone ); ?></a>
						<?php else : ?>
						<?php echo esc_html( $display_phone ); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( ! empty( $req_items ) ) :
							$show = array_slice( $req_items, 0, 3 );
							foreach ( $show as $it ) :
								$it_label = $it['desc'];
								if ( ! empty( $it['budget_max'] ) ) {
									$it_label .= ' — KES ' . number_format( $it['budget_max'] );
								}
							?>
							<div class="cvt-item-line"><?php echo esc_html( $it_label ); ?></div>
							<?php endforeach;
							if ( count( $req_items ) > 3 ) : ?>
							<div class="cvt-muted">+<?php echo esc_html( count( $req_items ) - 3 ); ?> more</div>
							<?php endif;
						else : ?>
							<?php if ( $entry->category ) : ?>
							<span class="cvt-role-chip"><?php echo esc_html( $entry->category ); ?></span>
							<?php endif; ?>
							<?php echo esc_html( wp_trim_words( $entry->description ?? '', 10, '…' ) ); ?>
						<?php endif; ?>
						<?php foreach ( $entry_tags as $tag ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&tag=' . urlencode( $tag ) ) ); ?>"
							class="cvt-tag-pill cvt-tag-pill--inline<?php echo ( $filter_tag === $tag ) ? ' cvt-tag-pill--active' : ''; ?>">
							<?php echo esc_html( $tag ); ?>
						</a>
						<?php endforeach; ?>
					</td>
					<td>
						<span class="cvt-badge <?php echo esc_attr( $si['class'] ); ?>">
							<?php echo esc_html( $si['label'] ); ?>
						</span>
						<?php if ( $entry->matched_item_id ) : ?>
						<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=view&id=' . $entry->matched_item_id ) ); ?>" class="cvt-muted" style="font-size:11px;">
							<?php esc_html_e( 'View match', 'corido-vendor-tracker' ); ?>
						</a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $entry->agent_name ?: '—' ); ?></td>
					<td>
						<span title="<?php echo esc_attr( $date_full ); ?>" style="cursor:default;">
							<?php echo esc_html( $time_ago ); ?>
						</span>
					</td>
					<td class="cvt-row-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&action=view&id=' . $entry->id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'View', 'corido-vendor-tracker' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist&action=edit&id=' . $entry->id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Edit', 'corido-vendor-tracker' ); ?>
						</a>
						<?php if ( $is_admin_user || ( (int) $entry->created_by === get_current_user_id() ) ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url(
							admin_url( 'admin-post.php?action=cvt_delete_waitlist&id=' . $entry->id ),
							'cvt_delete_waitlist'
						) ); ?>" class="button button-small cvt-delete-link">
							<?php esc_html_e( 'Delete', 'corido-vendor-tracker' ); ?>
						</a>
						<?php endif; ?>
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
					. ( $filter_status   ? '&status=' . urlencode( $filter_status )     : '' )
					. ( $filter_category ? '&category=' . urlencode( $filter_category ) : '' )
					. ( $filter_search   ? '&s=' . urlencode( $filter_search )           : '' )
					. ( $filter_tag      ? '&tag=' . urlencode( $filter_tag )            : '' ) );
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
