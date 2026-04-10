<?php defined( 'ABSPATH' ) || exit;

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
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Corido Vendor Tracker', 'corido-vendor-tracker' ); ?></h1>
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
	<div class="cvt-card cvt-quick-search">
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
	</div>

	<div class="cvt-dashboard-grid">
		<!-- Items by status -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Items by Status', 'corido-vendor-tracker' ); ?></h2>
			<div class="cvt-status-breakdown">
				<?php
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
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Recent activity -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Recent Activity', 'corido-vendor-tracker' ); ?></h2>
			<?php if ( empty( $recent_logs ) ) : ?>
				<p class="cvt-empty"><?php esc_html_e( 'No activity yet.', 'corido-vendor-tracker' ); ?></p>
			<?php else : ?>
			<ul class="cvt-activity-feed">
				<?php foreach ( $recent_logs as $log ) :
					$entity_url = '#';
					if ( $log->entity_type === 'vendor' ) {
						$entity_url = admin_url( 'admin.php?page=cvt-vendors&action=view&id=' . $log->entity_id );
					} elseif ( $log->entity_type === 'item' || $log->entity_type === 'payout' ) {
						$entity_url = admin_url( 'admin.php?page=cvt-items&action=view&id=' . $log->entity_id );
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
			<?php endif; ?>
		</div>
	</div>
</div>
