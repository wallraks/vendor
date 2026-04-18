<?php defined( 'ABSPATH' ) || exit;

// ── Data ────────────────────────────────────────────────────────────────────
$counts       = CVT_Item::get_status_counts();
$active_total = array_sum( array_diff_key( $counts, array_flip( array( 'closed', 'withdrawn' ) ) ) );
$pending_pay  = CVT_Payout::pending_count();
$recent_logs  = CVT_Activity_Log::get_recent( 12 );

global $wpdb;
$vendor_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cvt_vendors" );
$sold_month   = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}cvt_items WHERE status IN ('sold','closed') AND updated_at >= %s",
	date( 'Y-m-01 00:00:00' )
) );

// Waiting-list data.
$wl_open   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cvt_waitlist WHERE status = 'open'" );
$wl_counts = array();
foreach ( array( 'open', 'matched', 'fulfilled', 'cancelled' ) as $ws ) {
	$wl_counts[ $ws ] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}cvt_waitlist WHERE status = %s", $ws
	) );
}
$wl_recent = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}cvt_waitlist WHERE status = 'open' ORDER BY created_at DESC LIMIT 6"
);

// ── Widget order (persisted via WP postbox drag-and-drop) ───────────────────
$screen    = get_current_screen();
$screen_id = $screen ? $screen->id : 'toplevel_page_cvt-dashboard';
$all_wids  = array( 'cvt-db-status', 'cvt-db-activity', 'cvt-db-waitlist' );

$saved      = get_user_option( 'meta-box-order_' . $screen_id );
$col_normal = $all_wids;
$col_side   = array();

if ( is_array( $saved ) ) {
	$n = array_values( array_filter( explode( ',', $saved['normal'] ?? '' ) ) );
	$s = array_values( array_filter( explode( ',', $saved['side']   ?? '' ) ) );
	// Ensure every widget appears exactly once.
	$placed = array_merge( $n, $s );
	foreach ( $all_wids as $wid ) {
		if ( ! in_array( $wid, $placed, true ) ) {
			$n[] = $wid;
		}
	}
	$col_normal = $n;
	$col_side   = $s;
}

// ── Widget renderer ─────────────────────────────────────────────────────────
$render_widget = function( $widget_id ) use ( $counts, $recent_logs, $wl_counts, $wl_recent, $wl_open, $wpdb ) {
	$titles = array(
		'cvt-db-status'   => __( 'Items by Status', 'corido-vendor-tracker' ),
		'cvt-db-activity' => __( 'Recent Activity', 'corido-vendor-tracker' ),
		'cvt-db-waitlist' => __( 'Waiting List', 'corido-vendor-tracker' ),
	);
	$title = $titles[ $widget_id ] ?? ucwords( str_replace( array( 'cvt-db-', '-' ), array( '', ' ' ), $widget_id ) );
	?>
	<div id="<?php echo esc_attr( $widget_id ); ?>" class="postbox">
		<div class="postbox-header">
			<h2 class="hndle"><span><?php echo esc_html( $title ); ?></span></h2>
			<div class="handle-actions hide-if-no-js">
				<button type="button" class="handlediv" aria-expanded="true">
					<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s', 'corido-vendor-tracker' ), $title ) ); ?></span>
					<span class="toggle-indicator" aria-hidden="true"></span>
				</button>
			</div>
		</div>
		<div class="inside">
		<?php

		if ( $widget_id === 'cvt-db-status' ) :
			$total_items = max( 1, array_sum( $counts ) );
			foreach ( CVT_Settings::all_statuses() as $status ) :
				$info  = CVT_Settings::status_info( $status );
				$count = $counts[ $status ] ?? 0;
				$pct   = round( ( $count / $total_items ) * 100 );
				$url   = admin_url( 'admin.php?page=cvt-items&status=' . $status );
			?>
			<div class="cvt-status-row">
				<span class="cvt-badge <?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span>
				<div class="cvt-status-bar-wrap">
					<div class="cvt-status-bar" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
				</div>
				<a href="<?php echo esc_url( $url ); ?>" class="cvt-status-count"><?php echo esc_html( $count ); ?></a>
			</div>
			<?php endforeach;

		elseif ( $widget_id === 'cvt-db-activity' ) :
			if ( empty( $recent_logs ) ) :
			?>
			<p class="cvt-empty"><?php esc_html_e( 'No activity yet.', 'corido-vendor-tracker' ); ?></p>
			<?php else : ?>
			<ul class="cvt-activity-feed">
				<?php foreach ( $recent_logs as $log ) :
					if ( $log->entity_type === 'vendor' ) {
						$entity_url = admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $log->entity_id );
					} elseif ( $log->entity_type === 'item' ) {
						$entity_url = admin_url( 'admin.php?page=cvt-items&action=view&id=' . $log->entity_id );
					} else {
						$entity_url = admin_url( 'admin.php?page=cvt-payouts' );
					}
				?>
				<li class="cvt-activity-item">
					<span class="cvt-activity-dot"></span>
					<div class="cvt-activity-body">
						<span class="cvt-activity-desc">
							<?php echo wp_kses( CVT_Activity_Log::describe( $log ), array( 'strong' => array() ) ); ?>
							<a href="<?php echo esc_url( $entity_url ); ?>" class="cvt-activity-entity-link">
								[<?php echo esc_html( ucfirst( $log->entity_type ) . ' #' . $log->entity_id ); ?>]
							</a>
						</span>
						<span class="cvt-activity-time"><?php echo esc_html( human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></span>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif;

		elseif ( $widget_id === 'cvt-db-waitlist' ) :
			$wl_labels = array(
				'open'      => array( 'label' => __( 'Open', 'corido-vendor-tracker' ),      'class' => 'cvt-badge--review' ),
				'matched'   => array( 'label' => __( 'Matched', 'corido-vendor-tracker' ),   'class' => 'cvt-badge--inquiry' ),
				'fulfilled' => array( 'label' => __( 'Fulfilled', 'corido-vendor-tracker' ), 'class' => 'cvt-badge--sold' ),
				'cancelled' => array( 'label' => __( 'Cancelled', 'corido-vendor-tracker' ), 'class' => 'cvt-badge--withdrawn' ),
			);
			$wl_total = max( 1, array_sum( $wl_counts ) );
			?>
			<div class="cvt-wl-summary-header">
				<?php foreach ( $wl_labels as $ws => $info ) : ?>
				<div class="cvt-wl-stat">
					<span class="cvt-badge <?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span>
					<strong><?php echo esc_html( $wl_counts[ $ws ] ?? 0 ); ?></strong>
				</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $wl_recent ) ) : ?>
			<table class="cvt-table widefat striped cvt-wl-mini-table" style="margin-top:12px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Client', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Budget', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Added', 'corido-vendor-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wl_recent as $wl ) :
						$budget = $wl->budget_max
							? 'up to ' . CVT_Settings::format_currency( $wl->budget_max )
							: ( $wl->budget_min ? 'from ' . CVT_Settings::format_currency( $wl->budget_min ) : '—' );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $wl->client_name ); ?></strong><br>
							<span class="cvt-muted" style="font-size:11px;"><?php echo esc_html( $wl->phone ); ?></span>
						</td>
						<td><?php echo esc_html( $wl->category ?: '—' ); ?></td>
						<td><?php echo esc_html( $budget ); ?></td>
						<td><?php echo esc_html( date_i18n( 'd M', strtotime( $wl->created_at ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p class="cvt-empty"><?php esc_html_e( 'No open waiting list entries.', 'corido-vendor-tracker' ); ?></p>
			<?php endif; ?>

			<p style="margin-top:10px;margin-bottom:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'Manage Waiting List →', 'corido-vendor-tracker' ); ?>
				</a>
			</p>
		<?php endif; ?>
		</div><!-- .inside -->
	</div><!-- .postbox -->
	<?php
};
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'CR Business Suite', 'corido-vendor-tracker' ); ?></h1>
		<div class="cvt-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors&action=add' ) ); ?>" class="button button-primary">
				+ <?php esc_html_e( 'New Vendor', 'corido-vendor-tracker' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&action=add' ) ); ?>" class="button button-secondary">
				+ <?php esc_html_e( 'New Item', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<!-- Quick search -->
	<div class="cvt-quick-search cvt-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="cvt-vendors">
			<div class="cvt-search-row">
				<span class="dashicons dashicons-search"></span>
				<input type="search" name="s" class="cvt-search-input"
					placeholder="<?php esc_attr_e( 'Search vendors by name or phone…', 'corido-vendor-tracker' ); ?>"
					value="<?php echo esc_attr( $_GET['s'] ?? '' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'corido-vendor-tracker' ); ?></button>
			</div>
		</form>
	</div>

	<!-- Stat cards -->
	<div class="cvt-stat-grid">
		<div class="cvt-stat-card cvt-stat-card--blue">
			<div class="cvt-stat-number"><?php echo esc_html( $active_total ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Active Items', 'corido-vendor-tracker' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items' ) ); ?>" class="cvt-stat-link">
				<?php esc_html_e( 'View all', 'corido-vendor-tracker' ); ?> →
			</a>
		</div>
		<div class="cvt-stat-card cvt-stat-card--green">
			<div class="cvt-stat-number"><?php echo esc_html( $sold_month ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Sold This Month', 'corido-vendor-tracker' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-items&status=sold' ) ); ?>" class="cvt-stat-link">
				<?php esc_html_e( 'View all', 'corido-vendor-tracker' ); ?> →
			</a>
		</div>
		<div class="cvt-stat-card cvt-stat-card--orange">
			<div class="cvt-stat-number"><?php echo esc_html( $pending_pay ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Pending Payouts', 'corido-vendor-tracker' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-payouts&status=pending' ) ); ?>" class="cvt-stat-link">
				<?php esc_html_e( 'View all', 'corido-vendor-tracker' ); ?> →
			</a>
		</div>
		<div class="cvt-stat-card cvt-stat-card--purple">
			<div class="cvt-stat-number"><?php echo esc_html( $vendor_total ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Total Vendors', 'corido-vendor-tracker' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-vendors' ) ); ?>" class="cvt-stat-link">
				<?php esc_html_e( 'View all', 'corido-vendor-tracker' ); ?> →
			</a>
		</div>
		<div class="cvt-stat-card cvt-stat-card--teal">
			<div class="cvt-stat-number"><?php echo esc_html( $wl_open ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Open Requests', 'corido-vendor-tracker' ); ?></div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cvt-waitlist' ) ); ?>" class="cvt-stat-link">
				<?php esc_html_e( 'View all', 'corido-vendor-tracker' ); ?> →
			</a>
		</div>
	</div>

	<!-- Draggable widget columns -->
	<div id="poststuff">
		<div id="post-body" class="metabox-holder cvt-dashboard-metabox-holder">

			<!-- Main column -->
			<div id="postbox-container-2" class="postbox-container">
				<div id="normal-sortables" class="meta-box-sortables">
					<?php foreach ( $col_normal as $wid ) { $render_widget( $wid ); } ?>
				</div>
			</div>

			<!-- Side column -->
			<div id="postbox-container-1" class="postbox-container">
				<div id="side-sortables" class="meta-box-sortables">
					<?php foreach ( $col_side as $wid ) { $render_widget( $wid ); } ?>
					<?php if ( empty( $col_side ) ) : ?>
					<div class="cvt-drop-hint"><?php esc_html_e( 'Drag widgets here', 'corido-vendor-tracker' ); ?></div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- #post-body -->
	</div><!-- #poststuff -->
</div>

<script>
jQuery(function(){
	if ( typeof postboxes !== 'undefined' ) {
		postboxes.add_postbox_toggles( pagenow );
	}
});
</script>
