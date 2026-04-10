<?php defined( 'ABSPATH' ) || exit;

require_once CVT_PLUGIN_DIR . 'admin/class-cvt-payouts-list-table.php';
$table = new CVT_Payouts_List_Table();
$table->prepare_items();

global $wpdb;
$pending_total = (float) $wpdb->get_var(
	"SELECT SUM(payout_amount) FROM {$wpdb->prefix}cvt_payouts WHERE status = 'pending'"
);
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Payouts', 'corido-vendor-tracker' ); ?></h1>
		<?php if ( $pending_total > 0 ) : ?>
		<div class="cvt-alert cvt-alert--warning">
			<?php echo esc_html( sprintf(
				__( 'Total pending payout: %s', 'corido-vendor-tracker' ),
				CVT_Settings::format_currency( $pending_total )
			) ); ?>
		</div>
		<?php endif; ?>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<form method="get">
		<input type="hidden" name="page" value="cvt-payouts">
		<?php $table->display(); ?>
	</form>
</div>
