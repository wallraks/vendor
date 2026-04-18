<?php defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'cvt_view_reports' ) ) {
	wp_die( esc_html__( 'You do not have permission to view reports.', 'corido-vendor-tracker' ) );
}

global $wpdb;

// Month selector.
$selected_month = sanitize_text_field( $_GET['month'] ?? current_time( 'Y-m' ) );
if ( ! preg_match( '/^\d{4}-\d{2}$/', $selected_month ) ) {
	$selected_month = current_time( 'Y-m' );
}
$month_start = $selected_month . '-01 00:00:00';
$month_end   = date( 'Y-m-t 23:59:59', strtotime( $month_start ) );

// Summary stats for selected month.
$items_added = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}cvt_items WHERE created_at BETWEEN %s AND %s",
	$month_start, $month_end
) );
$items_sold = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}cvt_items WHERE status IN ('sold','closed') AND updated_at BETWEEN %s AND %s",
	$month_start, $month_end
) );
$vendors_added = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}cvt_vendors WHERE created_at BETWEEN %s AND %s",
	$month_start, $month_end
) );

// Revenue from payouts in selected month.
$revenue_row = $wpdb->get_row( $wpdb->prepare(
	"SELECT SUM(selling_price) AS revenue, SUM(commission_amount) AS commission, SUM(payout_amount) AS payouts
	 FROM {$wpdb->prefix}cvt_payouts WHERE status = 'paid' AND payout_date BETWEEN %s AND %s",
	$selected_month . '-01', date( 'Y-m-t', strtotime( $month_start ) )
) );
$revenue    = (float) ( $revenue_row->revenue ?? 0 );
$commission = (float) ( $revenue_row->commission ?? 0 );
$payouts    = (float) ( $revenue_row->payouts ?? 0 );

// Status breakdown for the month.
$status_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}cvt_items
	 WHERE created_at BETWEEN %s AND %s GROUP BY status",
	$month_start, $month_end
) );
$status_counts = array();
foreach ( CVT_Settings::all_statuses() as $s ) {
	$status_counts[ $s ] = 0;
}
foreach ( $status_rows as $row ) {
	$status_counts[ $row->status ] = (int) $row->cnt;
}

// Category breakdown (all time).
$cat_rows = $wpdb->get_results(
	"SELECT category, COUNT(*) AS cnt FROM {$wpdb->prefix}cvt_items GROUP BY category ORDER BY cnt DESC"
);

// Agent performance this month.
$agent_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT u.display_name, COUNT(*) AS items_added,
	 SUM( CASE WHEN i.status IN ('sold','closed') THEN 1 ELSE 0 END ) AS items_sold
	 FROM {$wpdb->prefix}cvt_items i
	 LEFT JOIN {$wpdb->users} u ON u.ID = i.assigned_agent_id
	 WHERE i.created_at BETWEEN %s AND %s AND i.assigned_agent_id IS NOT NULL
	 GROUP BY i.assigned_agent_id ORDER BY items_added DESC",
	$month_start, $month_end
) );

// Build month list for selector (last 12 months).
$months = array();
for ( $i = 0; $i < 12; $i++ ) {
	$months[] = date( 'Y-m', strtotime( "-$i months" ) );
}

// CSV export.
if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
	check_admin_referer( 'cvt_export_csv' );
	$filename = 'corido-report-' . $selected_month . '.csv';
	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Month', 'Vendors Added', 'Items Added', 'Items Sold', 'Revenue (KES)', 'Commission (KES)', 'Vendor Payouts (KES)' ) );
	fputcsv( $out, array( $selected_month, $vendors_added, $items_added, $items_sold, $revenue, $commission, $payouts ) );
	fclose( $out );
	exit;
}
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Reports', 'corido-vendor-tracker' ); ?></h1>
		<div class="cvt-page-actions">
			<a href="<?php echo esc_url( wp_nonce_url(
				add_query_arg( array( 'page' => 'cvt-reports', 'month' => $selected_month, 'export' => 'csv' ), admin_url( 'admin.php' ) ),
				'cvt_export_csv'
			) ); ?>" class="button">
				↓ <?php esc_html_e( 'Export CSV', 'corido-vendor-tracker' ); ?>
			</a>
		</div>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<!-- Month selector -->
	<form method="get" class="cvt-filter-bar">
		<input type="hidden" name="page" value="cvt-reports">
		<select name="month" class="cvt-filter-select">
			<?php foreach ( $months as $m ) : ?>
			<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $selected_month, $m ); ?>>
				<?php echo esc_html( date_i18n( 'F Y', strtotime( $m . '-01' ) ) ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'View', 'corido-vendor-tracker' ); ?></button>
	</form>

	<h2><?php echo esc_html( date_i18n( 'F Y', strtotime( $selected_month . '-01' ) ) ); ?></h2>

	<!-- Summary stat cards -->
	<div class="cvt-stat-grid">
		<div class="cvt-stat-card cvt-stat-card--blue">
			<div class="cvt-stat-number"><?php echo esc_html( $items_added ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Items Added', 'corido-vendor-tracker' ); ?></div>
		</div>
		<div class="cvt-stat-card cvt-stat-card--green">
			<div class="cvt-stat-number"><?php echo esc_html( $items_sold ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Items Sold', 'corido-vendor-tracker' ); ?></div>
		</div>
		<div class="cvt-stat-card cvt-stat-card--purple">
			<div class="cvt-stat-number"><?php echo esc_html( $vendors_added ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'New Vendors', 'corido-vendor-tracker' ); ?></div>
		</div>
		<div class="cvt-stat-card cvt-stat-card--orange">
			<div class="cvt-stat-number"><?php echo esc_html( CVT_Settings::format_currency( $commission ) ); ?></div>
			<div class="cvt-stat-label"><?php esc_html_e( 'Commission Earned', 'corido-vendor-tracker' ); ?></div>
		</div>
	</div>

	<div class="cvt-reports-grid">

		<!-- Revenue summary -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Revenue Summary', 'corido-vendor-tracker' ); ?></h2>
			<table class="cvt-table widefat">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Total Sales Revenue', 'corido-vendor-tracker' ); ?></td>
						<td><strong><?php echo esc_html( CVT_Settings::format_currency( $revenue ) ); ?></strong></td>
					</tr>
					<tr>
						<td><?php echo esc_html( sprintf( __( 'CR Commission (avg %s%%)', 'corido-vendor-tracker' ), CVT_Settings::commission_rate() ) ); ?></td>
						<td><?php echo esc_html( CVT_Settings::format_currency( $commission ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Total Vendor Payouts', 'corido-vendor-tracker' ); ?></td>
						<td><?php echo esc_html( CVT_Settings::format_currency( $payouts ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Status breakdown -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Items by Status (This Month)', 'corido-vendor-tracker' ); ?></h2>
			<table class="cvt-table widefat striped">
				<thead><tr><th><?php esc_html_e( 'Status', 'corido-vendor-tracker' ); ?></th><th><?php esc_html_e( 'Count', 'corido-vendor-tracker' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( CVT_Settings::all_statuses() as $status ) :
						$info = CVT_Settings::status_info( $status );
					?>
					<tr>
						<td><span class="cvt-badge <?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span></td>
						<td><?php echo esc_html( $status_counts[ $status ] ?? 0 ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Category breakdown -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Items by Category (All Time)', 'corido-vendor-tracker' ); ?></h2>
			<table class="cvt-table widefat striped">
				<thead><tr><th><?php esc_html_e( 'Category', 'corido-vendor-tracker' ); ?></th><th><?php esc_html_e( 'Count', 'corido-vendor-tracker' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $cat_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->category ?: __( '(Uncategorised)', 'corido-vendor-tracker' ) ); ?></td>
						<td><?php echo esc_html( $row->cnt ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $cat_rows ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'No data yet.', 'corido-vendor-tracker' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Agent performance -->
		<?php if ( current_user_can( 'cvt_view_all_logs' ) ) : ?>
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Agent Performance (This Month)', 'corido-vendor-tracker' ); ?></h2>
			<table class="cvt-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agent', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Items Added', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Items Sold', 'corido-vendor-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $agent_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->display_name ?: __( 'Unassigned', 'corido-vendor-tracker' ) ); ?></td>
						<td><?php echo esc_html( $row->items_added ); ?></td>
						<td><?php echo esc_html( $row->items_sold ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $agent_rows ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No data for this month.', 'corido-vendor-tracker' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

	</div>
</div>
