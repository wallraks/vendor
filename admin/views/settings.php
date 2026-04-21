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

		<!-- Waiting List Tags -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Waiting List Tags', 'corido-vendor-tracker' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Define the tags that agents can attach to waiting-list entries. One tag per line. These tags appear as selectable chips on the Add/Edit Entry form and power the Demand by Tag summary on the list page.', 'corido-vendor-tracker' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cvt_save_settings' ); ?>
				<input type="hidden" name="action" value="cvt_save_settings">
				<div class="cvt-field" style="margin-top:12px;">
					<label for="cvt_waitlist_tags"><?php esc_html_e( 'Tag List', 'corido-vendor-tracker' ); ?></label>
					<textarea id="cvt_waitlist_tags" name="cvt_waitlist_tags" rows="10" class="widefat"><?php
						echo esc_textarea( get_option( 'cvt_waitlist_tags', '' ) );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'One tag per line. Example: TV, Fridge, Washing Machine…', 'corido-vendor-tracker' ); ?>
					</p>
				</div>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Tags', 'corido-vendor-tracker' ); ?>
				</button>
			</form>
		</div>

		<!-- Listivo Slug Finder -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Listivo Slug Finder', 'corido-vendor-tracker' ); ?></h2>
			<p class="description" style="margin-bottom:14px;">
				<?php esc_html_e( 'If the post type or taxonomy slugs above show "not found", use these tables to find the correct values registered by your Listivo theme. Look for entries that mention "listing", "listivo", or "CT" and copy their slugs into the fields above.', 'corido-vendor-tracker' ); ?>
			</p>

			<?php
			// Post types — public or explicitly shown in UI, sorted by slug.
			$all_post_types = get_post_types( array(), 'objects' );
			ksort( $all_post_types );
			$skip_post_types = array( 'attachment', 'nav_menu_item', 'wp_block', 'wp_template',
				'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face',
				'wp_global_styles', 'wp_pattern_directory', 'revision', 'custom_css',
				'customize_changeset', 'oembed_cache', 'user_request', 'scheduled-action' );

			$all_taxonomies = get_taxonomies( array(), 'objects' );
			ksort( $all_taxonomies );
			$skip_taxonomies = array( 'nav_menu', 'link_category', 'post_format', 'wp_theme',
				'wp_template_part_area', 'wp_pattern_category' );
			?>

			<div class="cvt-slug-finder-grid">
				<!-- Post Types -->
				<div>
					<h3 style="margin:0 0 8px;font-size:13px;font-weight:600;"><?php esc_html_e( 'Registered Post Types', 'corido-vendor-tracker' ); ?></h3>
					<table class="cvt-table widefat striped" style="font-size:12px;">
						<thead><tr>
							<th><?php esc_html_e( 'Slug', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Label', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Published', 'corido-vendor-tracker' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $all_post_types as $slug => $pt ) :
							if ( in_array( $slug, $skip_post_types, true ) ) continue;
							$count   = wp_count_posts( $slug )->publish;
							$is_used = ( $slug === CVT_Settings::get_listivo_post_type() );
						?>
						<tr <?php echo $is_used ? 'style="background:#e8f5e9;"' : ''; ?>>
							<td>
								<code><?php echo esc_html( $slug ); ?></code>
								<?php if ( $is_used ) echo ' <span style="color:#2e7d32;font-weight:600;">← current</span>'; ?>
							</td>
							<td><?php echo esc_html( $pt->label ); ?></td>
							<td><?php echo esc_html( number_format( $count ) ); ?></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Taxonomies -->
				<div>
					<h3 style="margin:0 0 8px;font-size:13px;font-weight:600;"><?php esc_html_e( 'Registered Taxonomies', 'corido-vendor-tracker' ); ?></h3>
					<table class="cvt-table widefat striped" style="font-size:12px;">
						<thead><tr>
							<th><?php esc_html_e( 'Slug', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Label', 'corido-vendor-tracker' ); ?></th>
							<th><?php esc_html_e( 'Terms', 'corido-vendor-tracker' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $all_taxonomies as $slug => $tax ) :
							if ( in_array( $slug, $skip_taxonomies, true ) ) continue;
							$count   = wp_count_terms( array( 'taxonomy' => $slug, 'hide_empty' => false ) );
							$count   = is_wp_error( $count ) ? 0 : (int) $count;
							$is_used = ( $slug === CVT_Settings::get_listivo_taxonomy() );
						?>
						<tr <?php echo $is_used ? 'style="background:#e8f5e9;"' : ''; ?>>
							<td>
								<code><?php echo esc_html( $slug ); ?></code>
								<?php if ( $is_used ) echo ' <span style="color:#2e7d32;font-weight:600;">← current</span>'; ?>
							</td>
							<td><?php echo esc_html( $tax->label ); ?></td>
							<td><?php echo esc_html( number_format( $count ) ); ?></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Agent Role Configuration -->
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Plugin Access & Agent Roles', 'corido-vendor-tracker' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Choose which WordPress roles can access the Business Suite. Users with a checked role will see the plugin menus in wp-admin and will appear in the Assigned Agent dropdown. Roles you do not check will have no access regardless of their WordPress permissions. At least one role must be selected.', 'corido-vendor-tracker' ); ?>
			</p>
			<p class="description" style="margin-top:6px;">
				<?php esc_html_e( 'After saving, go to Users → All Users → Edit a user to assign them one of these roles.', 'corido-vendor-tracker' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cvt_save_settings' ); ?>
				<input type="hidden" name="action" value="cvt_save_settings">
				<input type="hidden" name="cvt_roles_submitted" value="1">

				<?php
				$all_roles      = wp_roles()->roles;
				$selected_roles = CVT_Settings::get_assignable_roles();
				// Native roles have fixed capability tiers — label them accordingly.
				$native_tiers = array(
					'administrator'    => __( 'Full admin', 'corido-vendor-tracker' ),
					'cvt_admin'        => __( 'CR Admin', 'corido-vendor-tracker' ),
					'cvt_senior_agent' => __( 'CR Senior Agent', 'corido-vendor-tracker' ),
					'cvt_junior_agent' => __( 'CR Junior Agent', 'corido-vendor-tracker' ),
				);
				ksort( $all_roles );
				?>
				<div class="cvt-field" style="margin-top:12px;">
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Plugin access roles', 'corido-vendor-tracker' ); ?></legend>
						<?php foreach ( $all_roles as $role_slug => $role_data ) :
							$is_selected = in_array( $role_slug, $selected_roles, true );
							$is_native   = isset( $native_tiers[ $role_slug ] );
							$tier_label  = $is_native
								? $native_tiers[ $role_slug ]
								: __( 'Agent access', 'corido-vendor-tracker' );
						?>
						<label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<input type="checkbox"
								name="cvt_assignable_roles[]"
								value="<?php echo esc_attr( $role_slug ); ?>"
								<?php checked( $is_selected ); ?>>
							<strong><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></strong>
							<code style="font-size:11px;color:#666;"><?php echo esc_html( $role_slug ); ?></code>
							<?php if ( $is_selected ) : ?>
							<span class="cvt-badge cvt-badge--sold" style="font-size:10px;padding:1px 6px;">
								<?php echo esc_html( $tier_label ); ?>
							</span>
							<?php else : ?>
							<span style="font-size:11px;color:#999;"><?php esc_html_e( 'No access', 'corido-vendor-tracker' ); ?></span>
							<?php endif; ?>
						</label>
						<?php endforeach; ?>
					</fieldset>
				</div>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save & Apply Access', 'corido-vendor-tracker' ); ?>
				</button>
			</form>
		</div>

		<!-- Vendor Reassignment Tool -->
		<?php
		// Count vendors currently assigned to out-of-role agents.
		$reassign_valid_ids = array_map( 'intval', (array) get_users( array(
			'role__in' => CVT_Settings::get_assignable_roles(),
			'fields'   => 'ID',
		) ) );
		$reassign_valid_ids = array_filter( $reassign_valid_ids );

		global $wpdb;
		if ( empty( $reassign_valid_ids ) ) {
			$orphan_count = (int) $wpdb->get_var(
				'SELECT COUNT(*) FROM ' . CVT_DB::vendors() . ' WHERE assigned_agent_id IS NOT NULL AND assigned_agent_id != 0'
			);
		} else {
			$in_list      = implode( ',', $reassign_valid_ids );
			$orphan_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM " . CVT_DB::vendors() . " WHERE assigned_agent_id IS NOT NULL AND assigned_agent_id != 0 AND assigned_agent_id NOT IN ($in_list)"
			);
		}
		?>
		<div class="cvt-card">
			<h2 class="cvt-card-title"><?php esc_html_e( 'Vendor Reassignment Tool', 'corido-vendor-tracker' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'When an agent\'s role is removed from the configuration above, their existing vendor assignments remain unchanged. Use this tool to manually reassign those vendors in bulk.', 'corido-vendor-tracker' ); ?>
			</p>

			<?php if ( $orphan_count > 0 ) : ?>
			<div class="notice notice-warning inline" style="margin:12px 0;">
				<p>
					<?php echo esc_html( sprintf(
						_n(
							'%d vendor is assigned to an agent whose role is no longer in the allowed set.',
							'%d vendors are assigned to agents whose roles are no longer in the allowed set.',
							$orphan_count,
							'corido-vendor-tracker'
						),
						$orphan_count
					) ); ?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cvt_bulk_reassign' ); ?>
				<input type="hidden" name="action" value="cvt_bulk_reassign">

				<div class="cvt-field">
					<label for="reassign_to">
						<?php esc_html_e( 'Reassign all affected vendors to:', 'corido-vendor-tracker' ); ?>
					</label>
					<select id="reassign_to" name="reassign_to" class="regular-text">
						<option value="0"><?php esc_html_e( '— Unassign (leave blank) —', 'corido-vendor-tracker' ); ?></option>
						<?php foreach ( $agents as $agent ) : ?>
						<option value="<?php echo esc_attr( $agent->ID ); ?>">
							<?php echo esc_html( $agent->display_name . ' (' . $agent->user_email . ')' ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>

				<p class="description" style="color:#b32d2e;">
					<?php esc_html_e( 'Warning: this action cannot be undone. All out-of-role vendor assignments will be updated immediately.', 'corido-vendor-tracker' ); ?>
				</p>

				<button type="submit" class="button button-primary"
					onclick="return confirm('<?php echo esc_js( __( 'This will reassign all affected vendors. Are you sure?', 'corido-vendor-tracker' ) ); ?>')">
					<?php echo esc_html( sprintf(
						_n( 'Reassign %d Vendor', 'Reassign %d Vendors', $orphan_count, 'corido-vendor-tracker' ),
						$orphan_count
					) ); ?>
				</button>
			</form>
			<?php else : ?>
			<div class="notice notice-success inline" style="margin:12px 0;">
				<p><?php esc_html_e( 'All vendor assignments are in order — no out-of-role agents found.', 'corido-vendor-tracker' ); ?></p>
			</div>
			<?php endif; ?>
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
							'cvt_admin'        => 'CR Admin',
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
