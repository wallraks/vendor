<?php defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'cvt_manage_settings' ) ) {
	wp_die( esc_html__( 'You do not have permission to access settings.', 'corido-vendor-tracker' ) );
}

$agents      = CVT_Roles::get_agents();
$source_info = CVT_Settings::categories_source_info();
?>
<div class="wrap cvt-wrap">
	<div class="cvt-page-header">
		<h1 class="cvt-page-title"><?php esc_html_e( 'Settings', 'corido-vendor-tracker' ); ?></h1>
	</div>

	<?php CVT_Admin::render_notice(); ?>

	<div class="cvt-settings-grid">

		<!-- Commission Settings -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Commission', 'corido-vendor-tracker' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cvt_save_settings' ); ?>
				<input type="hidden" name="action" value="cvt_save_settings">

				<div class="cvt-field">
					<label for="commission_rate">
						<?php esc_html_e( 'Commission Rate (%)', 'corido-vendor-tracker' ); ?>
					</label>
					<input type="number" id="commission_rate" name="commission_rate"
						class="small-text" min="0" max="100" step="0.01"
						value="<?php echo esc_attr( CVT_Settings::commission_rate() ); ?>">
					<p class="description">
						<?php esc_html_e( 'Percentage Corido takes from the selling price. Applied uniformly to all deal types. Changing this only affects future payouts — existing payout records keep their original rate.', 'corido-vendor-tracker' ); ?>
					</p>
				</div>

				<div class="cvt-field cvt-field--preview">
					<p class="description">
						<strong><?php esc_html_e( 'Example:', 'corido-vendor-tracker' ); ?></strong>
						<?php
						$rate  = CVT_Settings::commission_rate();
						$price = 10000;
						$c     = CVT_Payout::calculate( $price, $rate );
						echo esc_html( sprintf(
							__( 'KES 10,000 sale → Corido earns %s → Vendor receives %s', 'corido-vendor-tracker' ),
							CVT_Settings::format_currency( $c['commission'] ),
							CVT_Settings::format_currency( $c['payout'] )
						) );
						?>
					</p>
				</div>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Commission Rate', 'corido-vendor-tracker' ); ?>
				</button>
			</form>
		</div>

		<!-- Listivo Category Integration -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Item Categories', 'corido-vendor-tracker' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cvt_save_settings' ); ?>
				<input type="hidden" name="action" value="cvt_save_settings">

				<div class="cvt-field">
					<label for="cvt_listivo_post_type">
						<?php esc_html_e( 'Listivo Listing Post Type', 'corido-vendor-tracker' ); ?>
					</label>
					<input type="text" id="cvt_listivo_post_type" name="cvt_listivo_post_type"
						class="regular-text"
						value="<?php echo esc_attr( CVT_Settings::get_listivo_post_type() ); ?>"
						placeholder="listivo1_listing">
					<p class="description">
						<?php
						$pt      = CVT_Settings::get_listivo_post_type();
						$pt_obj  = $pt ? get_post_type_object( $pt ) : null;
						$pt_count = $pt_obj ? wp_count_posts( $pt )->publish : 0;
						if ( $pt_obj ) {
							echo '<span class="cvt-taxonomy-status cvt-taxonomy-status--connected" style="display:inline-flex;margin-top:6px;">'
								. '<span class="dashicons dashicons-yes-alt"></span>'
								. esc_html( sprintf(
									/* translators: 1: post type label, 2: count */
									__( 'Connected — %1$s (%2$d published)', 'corido-vendor-tracker' ),
									$pt_obj->label,
									$pt_count
								) )
								. '</span>';
						} else {
							echo '<span class="cvt-taxonomy-status cvt-taxonomy-status--disconnected" style="display:inline-flex;margin-top:6px;">'
								. '<span class="dashicons dashicons-dismiss"></span>'
								. esc_html( sprintf(
									/* translators: %s: post type slug */
									__( 'Post type "%s" not found. Check the slug matches your Listivo installation.', 'corido-vendor-tracker' ),
									$pt
								) )
								. '</span>';
						}
						?>
					</p>
				</div>

				<div class="cvt-field">
					<label for="cvt_listivo_taxonomy">
						<?php esc_html_e( 'Listivo Category Taxonomy', 'corido-vendor-tracker' ); ?>
					</label>
					<input type="text" id="cvt_listivo_taxonomy" name="cvt_listivo_taxonomy"
						class="regular-text"
						value="<?php echo esc_attr( CVT_Settings::get_listivo_taxonomy() ); ?>"
						placeholder="listivo_category">
					<p class="description">
						<?php esc_html_e( 'Taxonomy slug that Listivo uses for item categories (CT custom taxonomy). When connected, item forms will pull categories directly from your Listivo setup — no manual list needed.', 'corido-vendor-tracker' ); ?>
					</p>
				</div>

				<!-- Connection status indicator -->
				<?php if ( $source_info['source'] === 'listivo' ) : ?>
				<div class="cvt-taxonomy-status cvt-taxonomy-status--connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php echo esc_html( sprintf(
						/* translators: 1: number of categories, 2: taxonomy slug */
						__( 'Connected — %1$d categories found in "%2$s"', 'corido-vendor-tracker' ),
						$source_info['count'],
						$source_info['taxonomy']
					) ); ?>
				</div>
				<details class="cvt-taxonomy-preview">
					<summary><?php esc_html_e( 'Preview categories', 'corido-vendor-tracker' ); ?></summary>
					<ul class="cvt-taxonomy-list">
						<?php foreach ( CVT_Settings::categories() as $cat ) : ?>
						<li><?php echo esc_html( $cat ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
				<?php elseif ( $source_info['source'] === 'listivo_empty' ) : ?>
				<div class="cvt-taxonomy-status cvt-taxonomy-status--warning">
					<span class="dashicons dashicons-warning"></span>
					<?php echo esc_html( sprintf(
						/* translators: %s: taxonomy slug */
						__( 'Taxonomy "%s" exists but has no terms. Add categories in Appearance → Taxonomies or use the manual list below.', 'corido-vendor-tracker' ),
						$source_info['taxonomy']
					) ); ?>
				</div>
				<?php else : ?>
				<div class="cvt-taxonomy-status cvt-taxonomy-status--disconnected">
					<span class="dashicons dashicons-dismiss"></span>
					<?php echo esc_html( sprintf(
						/* translators: %s: taxonomy slug */
						__( 'Taxonomy "%s" not found. Check the slug or install/activate Listivo. Using manual list below.', 'corido-vendor-tracker' ),
						$source_info['taxonomy']
					) ); ?>
				</div>
				<?php endif; ?>

				<?php if ( $source_info['source'] !== 'listivo' ) : ?>
				<!-- Manual fallback — shown only when Listivo taxonomy is not connected -->
				<div class="cvt-field" style="margin-top: 16px;">
					<label for="categories">
						<?php esc_html_e( 'Manual Category List', 'corido-vendor-tracker' ); ?>
					</label>
					<textarea id="categories" name="categories" rows="10" class="widefat"><?php
						echo esc_textarea( get_option( 'cvt_categories', '' ) );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'One category per line. Used as fallback when the Listivo taxonomy is not available.', 'corido-vendor-tracker' ); ?>
					</p>
				</div>
				<?php endif; ?>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Categories', 'corido-vendor-tracker' ); ?>
				</button>
			</form>
		</div>

		<!-- Agent accounts overview -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Agent Accounts', 'corido-vendor-tracker' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Assign WordPress users the roles below from Users → All Users → Edit User → Role. Three Corido roles are available:', 'corido-vendor-tracker' ); ?>
			</p>
			<ul class="cvt-role-list">
				<li>
					<strong><?php esc_html_e( 'Corido Admin', 'corido-vendor-tracker' ); ?></strong>
					— <?php esc_html_e( 'Full access: settings, all vendors/items, payouts, reports, role management.', 'corido-vendor-tracker' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Corido Senior Agent', 'corido-vendor-tracker' ); ?></strong>
					— <?php esc_html_e( 'Add/edit all records, mark payouts, view full logs. Cannot change settings.', 'corido-vendor-tracker' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Corido Junior Agent', 'corido-vendor-tracker' ); ?></strong>
					— <?php esc_html_e( 'Add vendors and items, update status to Posted. Cannot delete, mark payouts, or withdraw items.', 'corido-vendor-tracker' ); ?>
				</li>
			</ul>
			<h3><?php esc_html_e( 'Current Agents', 'corido-vendor-tracker' ); ?></h3>
			<table class="cvt-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Email', 'corido-vendor-tracker' ); ?></th>
						<th><?php esc_html_e( 'Role', 'corido-vendor-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $agents as $agent ) :
						$roles = array_intersect(
							array_keys( $agent->caps ),
							array( 'administrator', 'cvt_admin', 'cvt_senior_agent', 'cvt_junior_agent' )
						);
						$role_labels = array(
							'administrator'    => 'WordPress Admin',
							'cvt_admin'        => 'Corido Admin',
							'cvt_senior_agent' => 'Senior Agent',
							'cvt_junior_agent' => 'Junior Agent',
						);
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_user_link( $agent->ID ) ); ?>">
								<?php echo esc_html( $agent->display_name ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $agent->user_email ); ?></td>
						<td>
							<?php foreach ( $roles as $role ) : ?>
							<span class="cvt-role-chip"><?php echo esc_html( $role_labels[ $role ] ?? $role ); ?></span>
							<?php endforeach; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button">
					<?php esc_html_e( 'Manage Users', 'corido-vendor-tracker' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="button">
					<?php esc_html_e( 'Add New User', 'corido-vendor-tracker' ); ?>
				</a>
			</p>
		</div>

		<!-- Status workflow reference -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Status Workflow Reference', 'corido-vendor-tracker' ); ?></h2>
			<div class="cvt-workflow">
				<div class="cvt-workflow-step">
					<span class="cvt-badge cvt-badge--review"><?php esc_html_e( 'Under Review', 'corido-vendor-tracker' ); ?></span>
					<span class="cvt-workflow-arrow">→</span>
					<span class="cvt-badge cvt-badge--posted"><?php esc_html_e( 'Posted', 'corido-vendor-tracker' ); ?></span>
					<span class="cvt-workflow-arrow">→</span>
					<span class="cvt-badge cvt-badge--inquiry"><?php esc_html_e( 'Inquiry Received', 'corido-vendor-tracker' ); ?></span>
					<span class="cvt-workflow-arrow">→</span>
					<span class="cvt-badge cvt-badge--sold"><?php esc_html_e( 'Sold', 'corido-vendor-tracker' ); ?></span>
					<span class="cvt-workflow-arrow">→</span>
					<span class="cvt-badge cvt-badge--closed"><?php esc_html_e( 'Closed', 'corido-vendor-tracker' ); ?></span>
				</div>
				<p class="description">
					<?php esc_html_e( 'Marking an item Sold auto-creates a pending payout record. Marking the payout Paid auto-closes the item. Withdrawn is a terminal state accessible from any open status (Senior Agent and above).', 'corido-vendor-tracker' ); ?>
				</p>
			</div>
		</div>

	</div>
</div>
