# CR Business Suite

Internal WordPress plugin for [Corido Marketplace](https://corido.co.ke) — a Kenya-based recommerce platform. Gives the Customer Care team a clean, auditable system to manage vendor intake, item listings, deal tracking, commissions, payouts, and waiting lists entirely within WordPress admin.

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting Started](#getting-started)
- [Features](#features)
- [Role & Permissions Reference](#role--permissions-reference)
- [Status Workflow](#status-workflow)
- [Commission & Payout Model](#commission--payout-model)
- [Listivo Compatibility](#listivo-compatibility)
- [Website Sold Sync](#website-sold-sync)
- [Database Schema](#database-schema)
- [File Structure](#file-structure)
- [Developer Notes](#developer-notes)
- [Changelog](#changelog)

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| MySQL / MariaDB | 5.6+ |
| Theme | Any (tested with Listivo) |

No external PHP packages, no Composer dependencies, no npm build step.

---

## Installation

1. Copy the `corido-vendor-tracker/` folder into `wp-content/plugins/`.
2. In WordPress admin, go to **Plugins → Installed Plugins**.
3. Click **Activate** next to *Corido Vendor Tracker*.
4. On activation the plugin automatically:
   - Creates 5 database tables (prefixed with `wp_cvt_`).
   - Registers three custom roles: `cvt_admin`, `cvt_senior_agent`, `cvt_junior_agent`.
   - Sets default commission rate to **12%**.
   - Pre-populates the category list with seven defaults.
5. The **Corido Vendors** menu appears in the WP admin sidebar.

To uninstall cleanly: deactivate the plugin, then delete it via **Plugins → Delete**. An `uninstall.php` handler drops all `wp_cvt_*` tables, removes plugin options, and unregisters the three custom roles when the plugin is deleted. Deactivation alone does **not** remove any data.

---

## Getting Started

### 1 — Assign agent accounts

Go to **Users → All Users**, edit each team member, and assign one of the Corido roles:

- **Corido Admin** — team lead / owner
- **Corido Senior Agent** — experienced agent
- **Corido Junior Agent** — new / limited-access agent

Existing WordPress `administrator` accounts automatically have full CVT access.

### 2 — Set your commission rate

Go to **Corido Vendors → Settings** and enter your current commission percentage (e.g. `12` for 12%). This rate is snapshotted onto each payout record at the time of sale, so changing it later does not affect past payouts.

### 3 — Add your first vendor

Go to **Corido Vendors → Vendors → Add Vendor**. Fill in name, phone, how they reached out, and save.

### 4 — Add an item for that vendor

From the vendor detail page, click **Add Item**. The vendor is pre-selected. Fill in title, category, selling price, deal type, and save. The item starts in **Under Review** status.

### 5 — Update status as the deal progresses

Open the item detail page. The **Update Status** panel on the right shows only the valid next steps for that item. Add an optional note with each transition — it appears in the activity log.

### 6 — Mark payouts when money moves

When an item is marked **Sold**, a payout record is auto-created. Go to the item detail page (or **Corido Vendors → Payouts**), enter the M-Pesa / bank reference number, and click **Mark as Paid & Close Item**. The item status advances to **Closed** automatically.

---

## Features

### Dashboard
- Stat cards: active items, sold this month, pending payouts, total vendors.
- Items-by-status horizontal bar breakdown (clickable, filters the items list).
- Recent activity feed (last 12 entries, across all entity types).
- Quick vendor search by name or phone.

### Vendors
- Full CRUD: name, phones (primary + secondary), email, location, intake channel, notes, assigned agent.
- Vendor detail page shows all their items in a table and a full chronological activity log.
- Intake channel options: Phone Call, WhatsApp, Email, Walk-in.

### Items
- Full CRUD: title, description, category, market value, selling price, deal type, date received, notes, website listing URL, assigned agent, images.
- Status filter tabs above the items list (Under Review / Posted / Inquiry Received / Sold / Closed / Withdrawn).
- Category and text search filters.
- WP media library integration for item images (multiple, removable).
- Live payout preview: as you type the selling price, commission and vendor payout update instantly via AJAX.
- Listivo listing URL field — paste the website URL once the item is posted publicly.

### Payouts
- Auto-created when item status changes to **Sold**, with commission rate snapshotted.
- Pending payout count shown as a badge on the Payouts menu item.
- Mark-paid form: reference number (M-Pesa code, bank ref), notes, processed-by recorded.
- Marking paid auto-closes the linked item.
- Total pending payout value shown at top of payouts list.

### Reports
- Monthly selector (last 12 months).
- Summary stats: items added, items sold, new vendors, commission earned.
- Revenue table: total sales revenue, commission, vendor payouts.
- Items by status (for the selected month).
- Items by category (all time).
- Agent performance table (Senior Agent and Admin only).
- CSV export for any month.

### Activity Log
- Every create, update, status change, image add/remove, and payout action is logged.
- Logs are shown chronologically on vendor detail and item detail pages.
- Each entry records: who did it, what changed (old → new as JSON), optional note, timestamp.

### Settings
- Commission rate (configurable without code changes).
- Category list (one per line, editable textarea).
- Website sold sync — field key, sold value and optional label taxonomy, plus a one-click backfill for items that sold before the sync was switched on.
- Agent accounts overview with role chips.
- Status workflow reference card.

---

## Role & Permissions Reference

Three custom roles are registered on activation. All capabilities are prefixed `cvt_` to avoid conflicts.

| Capability | Admin | Senior Agent | Junior Agent |
|---|:---:|:---:|:---:|
| `cvt_manage_settings` — change commission rate, categories | ✓ | | |
| `cvt_manage_users` — manage agent roles | ✓ | | |
| `cvt_view_reports` | ✓ | ✓ | |
| `cvt_view_all_logs` | ✓ | ✓ | |
| `cvt_add_vendors` | ✓ | ✓ | ✓ |
| `cvt_edit_own_vendor` — edit vendors they created | ✓ | ✓ | ✓ |
| `cvt_edit_any_vendor` — edit any vendor | ✓ | ✓ | |
| `cvt_delete_vendors` | ✓ | | |
| `cvt_add_items` | ✓ | ✓ | ✓ |
| `cvt_edit_own_item` — edit items they created | ✓ | ✓ | ✓ |
| `cvt_edit_any_item` — edit any item | ✓ | ✓ | |
| `cvt_delete_items` | ✓ | | |
| `cvt_update_status_posted` — move to Posted | ✓ | ✓ | ✓ |
| `cvt_update_status_sold` — mark as Sold | ✓ | ✓ | |
| `cvt_update_status_withdrawn` — withdraw item | ✓ | ✓ | |
| `cvt_view_payouts` | ✓ | ✓ | |
| `cvt_mark_payouts` — mark payout as paid | ✓ | ✓ | |

**Key design decisions:**

- Junior agents cannot mark items Sold — this creates a financial payout record and needs Senior+ accountability.
- Junior agents cannot delete anything. They can move items to Withdrawn once they have `cvt_update_status_withdrawn` (currently Senior+).
- Junior agents can see all vendors and items (read-only) so handoffs between agents work without losing context.
- Payout marking is restricted to Senior Agent and Admin — it represents confirming money has moved.
- Closing an item requires the payout to be marked Paid (system-enforced). Admins can override this by force-transitioning the status directly.
- WordPress `administrator` accounts receive all `cvt_*` capabilities automatically.

---

## Status Workflow

```
Under Review ──► Posted ──► Inquiry Received ──► Sold ──► Closed
     │              │               │
     └──────────────┴───────────────┴──► Withdrawn
```

| Status | Meaning | Who can set it |
|---|---|---|
| **Under Review** | Item received, being assessed. Default on creation. | — (set on create) |
| **Posted** | Listed on website or otherwise made available to buyers. | All agents |
| **Inquiry Received** | A buyer has shown interest / called about this item. | All agents |
| **Sold** | Transaction complete. Auto-creates pending payout record. | Senior Agent, Admin |
| **Closed** | Payout confirmed paid. Terminal state. | System (on mark-paid) |
| **Withdrawn** | Vendor pulled item before sale. Terminal state. | Senior Agent, Admin |

**Valid transitions (enforced server-side):**

| From | To (allowed) |
|---|---|
| Under Review | Posted, Withdrawn |
| Posted | Inquiry Received, Withdrawn |
| Inquiry Received | Posted *(fell through)*, Sold, Withdrawn |
| Sold | Closed *(via mark-paid only)* |
| Closed | — |
| Withdrawn | — |

Admins can bypass transition rules via the force flag — this is logged in the activity trail.

---

## Commission & Payout Model

The commission is a single configurable percentage applied uniformly to both consignment and agency deals.

```
Commission Amount = Selling Price × (Commission Rate ÷ 100)
Vendor Payout     = Selling Price − Commission Amount
```

**Example at 12%:**

| Selling Price | Commission (12%) | Vendor Payout |
|---|---|---|
| KES 5,000 | KES 600 | KES 4,400 |
| KES 25,000 | KES 3,000 | KES 22,000 |
| KES 150,000 | KES 18,000 | KES 132,000 |

**Payout lifecycle:**

1. Agent marks item **Sold** → system auto-creates payout record with:
   - `commission_rate` — snapshot of the rate at the time of sale
   - `commission_amount` — calculated at snapshot rate
   - `payout_amount` — selling price minus commission
   - `status` = `pending`
2. Payout appears in the **Payouts** list and on the item detail page.
3. Senior Agent or Admin enters the M-Pesa / bank reference number and clicks **Mark as Paid**.
4. Payout status → `paid`. Item status auto-advances to **Closed**.

The commission rate is snapshotted — changing the rate in Settings does not alter past payout records. This is intentional for auditability.

---

## Listivo Compatibility

The plugin is intentionally isolated from Listivo's data model:

- **No custom post types are registered.** All tracker data lives in five dedicated `wp_cvt_*` tables.
- **CSS/JS assets load only on CVT admin pages** (`page=cvt-*`). They do not affect the front end or any other admin page.
- **All CSS classes use the `.cvt-` prefix.** No style conflicts with Listivo's admin or front-end CSS.
- **No global hooks.** The plugin does not hook into `save_post`, `wp_head`, or other wide-scope hooks.
- **Capability names use the `cvt_` prefix.** No collision with Listivo's own capabilities or roles.

**Listing URL field:** When an agent posts an item on the Corido website (as a Listivo listing), they paste the listing URL into the item's *Listivo Listing URL* field. That link is the only thing tying a tracker item to a live listing.

### Website sold sync

Since v1.9.2, marking an item **Sold** also flags its listing on corido.co.ke. The listing stays published — it keeps its SEO value and shows buyers the item has gone — and only a sold flag is written.

| | |
|---|---|
| **Trigger** | `CVT_Item::update_status()` and `CVT_Payout::mark_paid()` both fire `cvt_item_status_changed`; `CVT_Listivo_Sync` listens. |
| **Marked sold when** | Item status becomes `sold` or `closed`. `sold → closed` changes nothing on the site — it is already flagged. |
| **Un-marked when** | An admin reverses a sale (`sold → posted`, `inquiry_received` or `withdrawn`). |
| **What is written** | The listing's sold field — auto-detected, or set explicitly in Settings — plus an optional taxonomy term for themes that render badges from a taxonomy. |
| **Where it writes** | Only the single post the item's listing URL resolves to, and only when that post is the configured Listivo post type. |
| **Skipped when** | The item has no listing URL, the URL points off-site, no listing matches it, or the listing's sold flag was set outside the tracker. |
| **Audit trail** | Every attempt is written to the item's activity log (`listing_marked_sold`, `listing_unmarked_sold`, `listing_sync_failed`). |

**Finding the sold field.** Listivo's CT framework prefixes listing fields with the post type, so the bare key is rarely right — the price lookup in `admin/class-cvt-ajax.php` walks the same shape of list for the same reason. Leave **Listing Field Key** blank in Settings and the sync finds it: it checks, in order, `_listivo1_listing_sold`, `listivo1_listing_sold`, `_sold`, `sold`, and uses whichever one your existing listings already carry. The result is cached for a day and re-detected whenever the key or the listing post type is saved.

Settings shows which key is in play and whether it was confirmed against real data. When no listing has been marked sold yet there is nothing to detect, so it says so rather than claiming a match — mark one sold by hand and reload. The **Sold Field Finder** card lists every listing field whose name mentions "sold", with how many listings carry it and a sample value, which names the real key and the value it holds.

Then test on a single item — mark it Sold and reload the listing on the front end. The sync writes the field and clears the post cache, but a theme or CDN layer that keeps its own index of listing fields may need that index rebuilt before the badge appears. If the badge does not show, the key is wrong or the theme reads its sold state from somewhere else; nothing else in the tracker is affected.

Two escape hatches:

- **Per item** — the item detail screen shows whether the listing is flagged on the site, with a *Mark Sold on site* button when the two are out of step (for example, when the listing URL was pasted in after the sale).
- **In bulk** — Settings offers a one-click backfill for items marked Sold before the sync existed.

The sync never deletes a flag it did not set: the value found before the sync is stored in a `_cvt_sold_synced` marker and restored on reversal.

---

## Database Schema

All tables use the site's table prefix (e.g. `wp_cvt_vendors`).

### `wp_cvt_vendors`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint UNSIGNED PK | |
| `name` | varchar(200) | |
| `phone_primary` | varchar(50) | |
| `phone_secondary` | varchar(50) | WhatsApp number |
| `email` | varchar(200) | |
| `location` | varchar(300) | |
| `apartment_name` | varchar(200) | nullable, building/estate name |
| `house_number` | varchar(100) | nullable, unit/door number |
| `intake_channel` | enum | phone, whatsapp, email, walkin |
| `notes` | text | |
| `assigned_agent_id` | bigint → wp_users.ID | nullable |
| `created_by` | bigint → wp_users.ID | |
| `created_at` / `updated_at` | datetime | |

### `wp_cvt_items`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint UNSIGNED PK | |
| `vendor_id` | bigint → cvt_vendors.id | |
| `title` | varchar(500) | |
| `description` | text | |
| `category` | varchar(100) | |
| `market_value` | decimal(12,2) | nullable, vendor estimate |
| `selling_price` | decimal(12,2) | |
| `commission_rate` | decimal(5,2) | nullable; NULL = use global rate |
| `listing_fee` | decimal(10,2) | nullable; flat fee for listing deal type |
| `deal_type` | enum | consignment, agency, listing |
| `status` | enum | under_review, posted, inquiry_received, sold, closed, withdrawn |
| `assigned_agent_id` | bigint → wp_users.ID | nullable |
| `agreement_attachment_id` | bigint → wp_posts.ID | nullable, signed consignment agreement |
| `listivo_listing_url` | varchar(500) | manual paste; resolved to a live listing by the sold sync |
| `date_received` / `date_posted` | date | nullable |
| `notes` | text | |
| `created_by` | bigint → wp_users.ID | |
| `created_at` / `updated_at` | datetime | |

### `wp_cvt_waitlist`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint UNSIGNED PK | |
| `client_name` | varchar(200) | |
| `phone` | varchar(50) | |
| `email` | varchar(200) | nullable |
| `description` | text | item specifications / requirements |
| `category` | varchar(100) | nullable, any category if blank |
| `budget_min` | decimal(12,2) | nullable |
| `budget_max` | decimal(12,2) | nullable |
| `quantity` | int UNSIGNED | default 1 |
| `timeframe` | varchar(200) | nullable, e.g. "within 2 weeks" |
| `notes` | text | internal agent notes |
| `tags` | text | JSON array of tag strings |
| `request_items` | text | JSON array of item rows `[{desc, category, budget_max, qty}]` |
| `status` | enum | open, matched, fulfilled, cancelled |
| `matched_item_id` | bigint → cvt_items.id | nullable, set when matched |
| `assigned_agent_id` | bigint → wp_users.ID | nullable |
| `created_by` | bigint → wp_users.ID | |
| `created_at` / `updated_at` | datetime | |

### `wp_cvt_item_images`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint UNSIGNED PK | |
| `item_id` | bigint → cvt_items.id | |
| `attachment_id` | bigint → wp_posts.ID | WP media library |
| `sort_order` | tinyint | |
| `created_at` | datetime | |

### `wp_cvt_payouts`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint UNSIGNED PK | |
| `item_id` | bigint → cvt_items.id | |
| `vendor_id` | bigint → cvt_vendors.id | |
| `item_title` | text | **snapshotted at payout creation** — preserved after item deletion |
| `selling_price` | decimal(12,2) | |
| `commission_rate` | decimal(5,2) | **snapshotted at time of sale** |
| `commission_amount` | decimal(12,2) | |
| `payout_amount` | decimal(12,2) | |
| `status` | enum | pending, paid |
| `payout_date` | date | nullable, set on mark-paid |
| `reference_number` | varchar(200) | M-Pesa code, bank ref, etc. |
| `notes` | text | |
| `processed_by` | bigint → wp_users.ID | nullable |
| `created_at` / `updated_at` | datetime | |

### `wp_cvt_activity_log`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint UNSIGNED PK | |
| `entity_type` | enum | vendor, item, payout, waitlist |
| `entity_id` | bigint | FK to the relevant table |
| `action` | varchar(100) | e.g. `created`, `status_changed`, `payout_marked_paid` |
| `old_value` | longtext | JSON snapshot of previous state |
| `new_value` | longtext | JSON snapshot of new state |
| `note` | text | optional agent note |
| `user_id` | bigint → wp_users.ID | who performed the action |
| `created_at` | datetime | |

---

## File Structure

```
corido-vendor-tracker/
├── corido-vendor-tracker.php           # Plugin header, constants, bootstrap
│
├── includes/
│   ├── class-cvt-activator.php         # DB table creation (dbDelta), default options
│   ├── class-cvt-db.php                # Table name helpers (CVT_DB::vendors(), etc.)
│   ├── class-cvt-settings.php          # wp_options wrapper, status/label helpers
│   ├── class-cvt-activity-log.php      # Write and read audit log entries
│   ├── class-cvt-vendor.php            # Vendor model (CRUD, search, capability checks)
│   ├── class-cvt-item.php              # Item model (CRUD, status transitions, images)
│   ├── class-cvt-payout.php            # Payout creation, mark-paid, calculations
│   ├── class-cvt-waitlist.php          # Waiting list model (CRUD, tag purge, match detection)
│   ├── class-cvt-rest-api.php          # REST API — corido/v1 namespace, five endpoints
│   ├── class-cvt-listivo-sync.php      # Flags the live Listivo listing Sold when an item sells
│   └── class-cvt-roles.php             # Custom role and capability registration
│
├── admin/
│   ├── class-cvt-admin.php             # Menu registration, page routing, form handlers
│   ├── class-cvt-ajax.php              # AJAX: vendor search, payout preview, image remove
│   ├── class-cvt-vendors-list-table.php   # WP_List_Table for vendors
│   ├── class-cvt-items-list-table.php     # WP_List_Table for items (with status tabs)
│   ├── class-cvt-payouts-list-table.php   # WP_List_Table for payouts
│   │
│   ├── views/
│   │   ├── dashboard.php               # Stat cards, status bars, activity feed, search
│   │   ├── vendors/
│   │   │   ├── list.php                # Vendors list
│   │   │   ├── form.php                # Add / edit vendor form
│   │   │   └── detail.php             # Vendor detail + items table + activity log
│   │   ├── items/
│   │   │   ├── list.php                # Items list with filters
│   │   │   ├── form.php                # Add / edit item form with live payout preview
│   │   │   └── detail.php             # Item detail + payout card + status updater + log
│   │   ├── payouts/
│   │   │   └── list.php                # Payouts list with pending total alert
│   │   ├── waitlist/
│   │   │   ├── list.php                # Waiting list entries with tag filter bar
│   │   │   ├── form.php                # Add / edit entry with multi-item table
│   │   │   └── view.php                # Read-only entry detail (access logged)
│   │   ├── settings.php                # Commission rate, categories, sold sync, agent overview
│   │   └── reports.php                 # Monthly reports + CSV export
│   │
│   ├── css/
│   │   └── cvt-admin.css               # Material-inspired styles, .cvt- prefixed
│   └── js/
│       └── cvt-admin.js                # Vendor typeahead, image upload, payout preview
```

---

## Developer Notes

### Adding a new capability

1. Add the capability to the `capability_map()` array in `class-cvt-roles.php`, listing which tiers should have it.
2. Run `CVT_Roles::register()` (or deactivate/reactivate the plugin) to sync it to existing roles.
3. Gate the relevant code with `current_user_can( 'cvt_your_new_cap' )`.

### Adding a new item status

1. Add the new value to the `status` enum in `class-cvt-activator.php` (re-run `dbDelta` via deactivate/reactivate or a migration).
2. Add it to the `all_statuses()` and `status_info()` arrays in `class-cvt-settings.php`.
3. Update the `valid_transitions()` map in `class-cvt-settings.php`.
4. Add a corresponding `.cvt-badge--yourvalue` CSS rule in `cvt-admin.css`.

### Dashboard stat cache

The status counts on the dashboard are cached in a WordPress transient (`cvt_status_counts`) for 5 minutes. The cache is busted automatically whenever an item status changes. To bust manually: `delete_transient( 'cvt_status_counts' )`.

### Security model

Every form submission:
- Verifies a WordPress nonce (`check_admin_referer`).
- Checks the relevant `cvt_*` capability before acting.
- Sanitizes all inputs (`sanitize_text_field`, `sanitize_textarea_field`, `absint`, `esc_url_raw`).

Every database query uses `$wpdb->prepare()`. All view output uses `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses()` as appropriate.

### AJAX endpoints

| Action | Method | Auth | Description |
|---|---|---|---|
| `cvt_vendor_search` | GET | `cvt_add_items` | Typeahead search, returns `[{id, name, phone_primary}]` |
| `cvt_payout_preview` | GET | logged in | Returns commission + payout for a given price |
| `cvt_remove_item_image` | POST | `cvt_edit_own_item` | Removes an image row by ID |

All AJAX calls require the `cvt_ajax` nonce passed as `nonce`.

### The `cvt_item_status_changed` action

Fired wherever an item's status changes, so a consumer sees every sale however it was made:

| Fired from | For |
|---|---|
| `CVT_Item::update_status()` | Any status change made in the admin UI |
| `CVT_Payout::mark_paid()` | The automatic `sold → closed` step |
| `POST /items/{id}/status` | Status changes made over the REST API |
| `POST /payouts/{id}/mark-paid` | The API's automatic `sold → closed` step |

The two REST endpoints write the status directly rather than calling `CVT_Item::update_status()`, so they fire the action themselves — without that, a sale made over the API would never reach the website listing.


```php
do_action( 'cvt_item_status_changed', $item_id, $old_status, $new_status );
```

`CVT_Listivo_Sync` is the first consumer. Use the same hook to extend the workflow — for example, to auto-create a Listivo listing when an item moves to `Posted`:

```php
// In a separate file (not in the core plugin).
add_action( 'cvt_item_status_changed', function( $item_id, $old_status, $new_status ) {
    if ( $new_status !== 'posted' ) return;
    $item = CVT_Item::get( $item_id );
    // Create Listivo listing CPT here...
    // Store returned post ID back to cvt_items.listivo_listing_url
}, 10, 3 );
```

### Website sold sync internals

`CVT_Listivo_Sync` (`includes/class-cvt-listivo-sync.php`):

| Method | Purpose |
|---|---|
| `mark_sold( $item_id )` / `clear_sold( $item_id )` | Write or undo the sold flag; return the listing post ID or a `WP_Error`. |
| `candidate_keys()` | The sold field keys tried, in priority order. |
| `meta_key()` / `key_is_confirmed()` | The key in use, and whether it came from real data or is an unverified fallback. |
| `detected_key()` / `scan_for_sold_key()` / `bust_key_cache()` | Day-cached detection of the site's sold key, the query behind it, and the cache reset. |
| `resolve_post_id( $item )` | `url_to_postid()` on the stored URL, falling back to a slug lookup scoped to the Listivo post type. Rejects off-site URLs and posts of the wrong type. |
| `site_state( $item )` | Read-only state for the item detail screen: `disabled`, `no_url`, `error`, `sold`, `not_sold`, plus `can_sync`. |
| `pending_items()` / `unsynced_count()` / `backfill()` | Find and fix items sold before the sync existed. The count is cached for five minutes. |

Settings options: `cvt_sold_sync_enabled`, `cvt_sold_sync_meta_key` (blank means auto-detect), `cvt_sold_sync_meta_value`, `cvt_sold_sync_taxonomy`, `cvt_sold_sync_term`.

---

## Changelog

### 1.9.2 — Mark items Sold on the website

**New features**
- Marking an item **Sold** now flags its Listivo listing on corido.co.ke. The listing stays published; only the sold flag is written, so the page keeps its SEO value and buyers can see the item has gone.
- Reversing a sale (`sold → posted` / `inquiry_received` / `withdrawn`) removes the flag again. `sold → closed` is a no-op — the listing is already flagged.
- New class `includes/class-cvt-listivo-sync.php`, driven by a new `cvt_item_status_changed` action fired from all four places an item's status changes: `CVT_Item::update_status()`, `CVT_Payout::mark_paid()`, `POST /items/{id}/status` and `POST /payouts/{id}/mark-paid`. The REST endpoints write status directly, so a sale made over the API syncs to the website too.
- **Settings → Website Sold Sync**: on/off, the listing field key (blank = auto-detect) and value (default `1`), and an optional taxonomy + term for themes that render badges from a taxonomy.
- The sold field key is detected from the site rather than guessed: the sync tries `_listivo1_listing_sold`, `listivo1_listing_sold`, `_sold`, `sold` and uses the one your listings carry, matching how the existing price lookup handles Listivo's prefixed field names. Settings reports whether the key was confirmed against real data or is an unverified fallback.
- New **Sold Field Finder** card in Settings lists every listing field whose name mentions "sold", with listing counts and a sample value — it names the real key without anyone opening a Custom Fields panel.
- Settings also offers a one-click backfill for items that were marked Sold before the sync existed, and shows how many listings currently carry the flag.
- The item detail screen shows whether the listing is flagged on the site, with a **Mark Sold on site** button (capability `cvt_update_status_sold`) when the tracker and the website disagree — for example when the listing URL was pasted in after the sale.

**Safety**
- Nothing is written unless the item's listing URL resolves to a post of the configured Listivo post type on this site. Off-site URLs, unmatched URLs and wrong post types are refused with a specific message.
- A sold flag the tracker did not set is never deleted. The pre-sync value is kept in a `_cvt_sold_synced` marker and restored on reversal.
- Every attempt is written to the item's activity log: `listing_marked_sold`, `listing_unmarked_sold`, `listing_sync_failed`.

---

### 1.9.0 — REST API: full items, payouts & waitlist coverage

**New endpoints**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/items` | List items — filters: `s`, `vendor_id`, `status`, `category`, `deal_type`, `page`, `per_page` |
| GET    | `/items/{id}` | Single item with images |
| PATCH  | `/items/{id}` | Update item fields |
| DELETE | `/items/{id}` | Delete item (cascades: images, pending payouts) |
| POST   | `/items/{id}/status` | Transition status; auto-creates payout on `sold`; voids pending payout on reverse; reverse requires `cvt_manage_settings` |
| GET    | `/payouts` | List payouts — filters: `s`, `status`, `vendor_id`, `page`, `per_page` |
| GET    | `/payouts/{id}` | Single payout |
| POST   | `/payouts/{id}/mark-paid` | Mark payout paid + auto-close item (`reference_number` required) |
| GET    | `/waitlist` | List entries — filters: `s`, `status`, `tag`, `page`, `per_page` |
| POST   | `/waitlist` | Create waitlist entry |
| GET    | `/waitlist/{id}` | Single entry (tags and request_items decoded to arrays) |
| PATCH  | `/waitlist/{id}` | Update entry |
| DELETE | `/waitlist/{id}` | Delete entry (owner or admin only) |

---

### 1.8.0 — REST API: DELETE vendor endpoint

**New endpoint**
- `DELETE /wp-json/corido/v1/vendors/{id}` — permanently removes a vendor and all their items. Cascade order: item images → pending payouts → items → vendor record. Paid payouts are kept (financial history; item title already snapshotted). Returns `{ success, message, deleted_items }`.

---

### 1.7.9 — REST API (`corido/v1`)

**New features**
- New class `includes/class-cvt-rest-api.php` registers five endpoints under `/wp-json/corido/v1/`:

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/vendors` | Paginated vendor list; supports `page`, `per_page` (max 100), `s` (name/phone/email search) |
| `POST` | `/vendors` | Create vendor; include `listing_title` to auto-create a linked `cvt_items` record in one request |
| `GET` | `/vendors/{id}` | Full vendor record plus all their items |
| `PATCH` | `/vendors/{id}` | Partial update — only fields supplied are changed |
| `POST` | `/vendors/{id}/items` | Add an item to an existing vendor |

- All routes require `current_user_can('edit_posts')` (401 if not logged in, 403 if insufficient capability).
- Every mutation is logged to `cvt_activity_log` with the same structure as admin-UI actions.
- `listing_id` is stored in the item's notes field as `Listivo listing ID: {value}` (no dedicated column required).
- DB errors surface in the response body alongside the relevant HTTP status code.

---

### 1.7.8 — Uniform own-record delete for waitlist

- `CVT_Waitlist::delete()` now requires the caller to be the entry's creator (`cvt_add_items` + ownership) or an admin (`cvt_manage_settings`) — matching the same pattern already used for items and vendors.
- Waitlist list view hides the **Delete** button for entries the current user did not create.
- Deletions are now logged to the activity log.

---

### 1.7.7 — Payout item title snapshot; cascade delete on item removal

**New features**
- `item_title` column added to `wp_cvt_payouts` — the item name is snapshotted at payout creation time so financial records remain accurate after the item is deleted.
- `CVT_Payout::get()` and `get_all()` use `COALESCE(i.title, p.item_title)` so the correct name always appears regardless of whether the item still exists.
- `CVT_Item::delete()` now cascade-deletes any **pending** payout records before removing the item. Paid payouts are intentionally preserved as an unalterable financial audit trail.

**Database changes**
- `wp_cvt_payouts`: added `item_title text NOT NULL DEFAULT ''`
- DB version bumped to 12; `maybe_upgrade()` applies a guarded `ALTER TABLE` on existing installs.

---

### 1.7.6 — Privacy blurring & own-record delete

**New features**
- **Privacy blurring in lists:** non-admin agents see other agents' vendor names blurred (`J××××`) in the items list, and both vendor name and phone blurred in the vendors list. Admins always see full detail.
- **Own-record delete:** any agent can now delete vendors and items they personally created. The **Delete** row action appears in both list tables for own records. Admins can delete anything as before.

---

### 1.7.5 — Waitlist: tag filter bar, multi-item form, relative dates

**New features**
- **Tag filter pill bar** — clickable tag pills with entry counts appear above the waitlist table; selecting a pill filters the list instantly.
- **Purge Old Tags** — admin-only button that strips any tag not currently in the defined tag list from all existing entries; returns a count of updated rows.
- **Relative dates** — the *Added* column now shows "3 days ago" style text; hover reveals the full date and time.
- **Multi-item request table** — the waitlist form now uses a dynamic table (Description | Category | Budget | Qty | ×) instead of single fields. Rows are added with **+ Add Item** and removed with the × button. Data is stored as JSON in the `request_items` column.

**Database changes**
- `wp_cvt_waitlist`: added `request_items text NOT NULL DEFAULT ''`
- DB version bumped to 11; `maybe_upgrade()` adds the column to existing installs.

---

### 1.7.4 — Waitlist: view page, privacy, compact form, category hierarchy

**New features**
- **View page** — read-only detail view for waitlist entries (`?action=view&id=`). Non-admin access is logged to the activity log.
- **Client privacy** — non-admin agents see client name (`Jo×××`) and phone (`+25×××××`) blurred in the list; full data is visible on the view page (access logged).
- **Compact form** — Email + Timeframe share a row; Budget Min + Budget Max share a row.
- **Hierarchical category selects** — categories pulled from the Listivo taxonomy display with `↳` indented children.
- **Demand by Tag** — only shows tags currently defined in Settings; historical free-text tags are filtered out.

**Database changes**
- `wp_cvt_activity_log`: `entity_type` enum extended to include `'waitlist'`
- DB version bumped to 10.

---

### 1.7.3 — Role permissions: all agents can mark Sold and Withdrawn

- `cvt_update_status_sold` and `cvt_update_status_withdrawn` capabilities granted to Junior Agents, removing the previous Senior Agent–only restriction on these transitions.

---

### 1.7.2 — Waitlist tags: predefined chip picker

- Free-text tag input replaced with a chip picker built from the tag list defined in Settings.
- Waitlist tag demand section on the waitlist page filtered to only show currently-defined tags.

---

### 1.7.1 — Waitlist tag demand & item form image sidebar

- Tag demand summary moved to its own section below the entries table.
- Item form images moved into a sidebar panel alongside deal and agent fields.

---

### 1.7.0 — Waitlist tags column

- `tags` column added to `wp_cvt_waitlist` for storing per-entry tag arrays (JSON).
- DB version bumped to 9.

---

### 1.6.0 — Listing Deal Type

**New features**
- **Listing deal type:** A new deal type option alongside *Consignment* and *Agency*. Select *Listing* in the Deal Type dropdown when a vendor is paying Corido to list an item on the platform rather than consigning it.
- **Listing Fee field:** When *Listing* is selected, the Commission Rate field is replaced by a *Listing Fee (KES)* field. Leave it blank or 0 for a free listing; enter a value (default KES 500) when the vendor is charged. The fee is stored at the item level and snapshotted into the payout record on sale.
- **Payout model for listings:** `commission_amount = listing_fee`, `payout_amount = selling_price − listing_fee`. Preserves the same accounting invariant as percentage-based deals. Both the payout preview (live AJAX) and the item detail payout card use *"Listing Fee"* as the label instead of *"CR Commission (X%)"*.
- **Deal info tooltip updated:** The item detail view shows *Listing Fee* row in place of *Commission Rate* row for listing-type items, with *Free* shown when the fee is 0 or null.

**Database changes**
- `wp_cvt_items`: added `listing_fee decimal(10,2) DEFAULT NULL`; deal_type enum extended to `('consignment','agency','listing')`
- DB version bumped to 6; `maybe_upgrade()` uses dbDelta for the new column and an explicit `ALTER TABLE` to extend the enum on existing installs

---

### 1.5.0 — Plugin renamed to CR Business Suite; Payment status & Deal completeness on items list

**Changes**
- **Plugin renamed** from *Corido Vendor Tracker* to *CR Business Suite*. Admin menu, dashboard heading, role display names (CR Admin / CR Senior Agent / CR Junior Agent), and all user-visible strings updated. Internal class names, file names, text domain, and capability slugs unchanged — no data migration needed.
- **Payment status column** added to the items list table. Shows *Paid* (green) or *Pending* (orange) for items with a payout record; blank for items not yet sold.
- **Deal completeness indicator** — a colour-coded dot in the rightmost column of the items list. Hover to see an inline tooltip listing:
  - Agreement signed / No agreement attached
  - Image count or no images
  - Listing URL set or missing
  - Payment status (if applicable)
  - Green dot = all complete; orange = one item missing; red = two or more missing.

---

### 1.4.0 — Per-item Commission Rate & Reverse Deal Transitions

**New features**
- **Per-item commission rate (P5):** Each item now carries its own `commission_rate` field (0–100%). Leave it blank to inherit the global default. When an item is marked Sold, the payout record uses the item's explicit rate if set, otherwise falls back to the global setting. Changing the rate on an existing item is logged in the activity trail. The live payout preview updates in real time as the commission rate field is edited.
- **Reverse deal transitions (P7):** Admins (`cvt_manage_settings`) can now move items backwards through the pipeline. New reverse paths: `Sold → Inquiry Received`, `Sold → Posted`, `Sold → Withdrawn`, `Withdrawn → Under Review`. When reversing from Sold, any pending (unpaid) payout record is automatically voided and the deletion is logged for audit. Closed items (payout already paid) cannot be reversed. Reverse-deal buttons appear in a separate dashed section of the item detail stepper, visible only to admins.

**Database changes**
- `wp_cvt_items`: added `commission_rate decimal(5,2) DEFAULT NULL` (`NULL` = use global rate)
- DB version bumped to 5; `maybe_upgrade()` applies the column to existing installs via `dbDelta`

---

### 1.3.0 — Consignment Agreements, Price History, Waitlist Tracker & Listing Search Fix

**New features**
- **Consignment agreement upload (P1):** Upload a signed agreement (image or PDF) per item via the WP Media Library. The file is stored as an attachment ID on the item record and shown on the item detail page with a download link. Multiple items can reference the same uploaded file. Server-side MIME validation ensures only `image/jpeg`, `image/png`, `image/gif`, `image/webp`, and `application/pdf` are accepted.
- **Price change history (P4):** Every selling price edit is logged to the activity trail with old price, new price, timestamp, and who changed it. An optional reason note field appears automatically in edit mode when the price is changed (hidden otherwise). The item detail page shows a dedicated price history table above the activity log when history exists.
- **Client waiting list / wanted items tracker (P5):** New `Waiting List` menu under Corido Vendors. Agents log client requests for items not in stock (category, budget range, quantity, timeframe, description). When a new item is created, the system auto-checks for open waitlist entries whose category and budget match — matches appear as a dismissal banner on the item detail page so agents can follow up. Waiting list entries support statuses: Open, Matched, Fulfilled, Cancelled.

**Bug fixes**
- **Listing search broken in Add Item form (P2):** `WP_Query` with the `s` parameter searched both `post_title` and `post_content` with double-sided `LIKE`, causing poor performance and unexpected mismatches. Replaced with a direct SQL query on `post_title LIKE '%term%'` — faster, predictable, index-friendly.

**Improvements**
- Vendor typeahead on the item form confirmed working end-to-end; AJAX error handler added for both listing search and vendor search dropdowns.
- DB version bumped to 4; `maybe_upgrade()` runs idempotently on `plugins_loaded`.

**Database changes**
- `wp_cvt_items`: added `agreement_attachment_id bigint UNSIGNED DEFAULT NULL`
- New table `wp_cvt_waitlist` (id, client_name, phone, email, description, category, budget_min, budget_max, quantity, timeframe, notes, status, matched_item_id, assigned_agent_id, created_by, created_at, updated_at)

---

### 1.2.1 — Vendor Location Details & WhatsApp Field

**New features**
- **Apartment / building name** and **house / unit number** fields added to vendor records (capture and display).
- **"Secondary Phone" renamed to "WhatsApp"** throughout forms and detail views; WhatsApp numbers render as `wa.me` deep links on the vendor detail page.

---

### 1.2.0 — Agent Role Configuration, Vendor Reassignment & Listing Search

**New features**
- **Real-time listing search in Add Item form:** Type a Listivo listing title to auto-fill item title, category, and selling price from the live website listing. Dropdown suggestions show thumbnail, price, and category. Keyboard navigation (↑ ↓ Enter Escape) supported. "Change" button resets selection.
- **Agent role configuration:** Settings page now includes a card to choose which WordPress roles are treated as CVT agents. Checkboxes for all registered roles; changes take effect immediately without code changes.
- **Vendor reassignment tool:** Settings page card shows count of vendors assigned to deleted/invalid agents (orphans) and provides a one-click bulk reassign to any active agent.
- **Listivo Slug Finder:** Settings page diagnostic card listing all registered post types and taxonomies, making it easy to identify the correct slug to enter in the Listivo integration settings.

---

### 1.1.0 — Security Hardening, Visual Status Stepper & Listivo Category Integration

**New features**
- **Visual status stepper** on item detail pages: a full-width horizontal timeline showing Under Review → Posted → Inquiry Received → Sold → Closed. Each stage displays the date it was first entered (derived from the activity log). Completed stages show a green checkmark; the active stage is highlighted in blue; future stages are greyed out.
- **Quick status action buttons** below the stepper: colour-coded buttons (green for Posted, orange for Inquiry, purple for Sold, grey for Closed, red for Withdrawn) wire directly to the status update form — no dropdown needed.
- **Listivo CT taxonomy integration**: `CVT_Settings::categories()` now checks `taxonomy_exists()` and calls `get_terms()` to pull categories live from the Listivo `listivo_category` taxonomy (configurable). Falls back to the manual textarea list if the taxonomy is absent or empty.
- **Settings page — Listivo Category Integration section**: taxonomy slug input, real-time connection status indicator (Connected / Empty / Not Found), and a collapsible category preview when connected.

**Security improvements**
- `wp_unslash()` applied to all `$_POST`/`$_GET` input at the controller boundary in `class-cvt-admin.php` and `class-cvt-ajax.php`, preventing double-slashed apostrophes in stored values.
- AJAX image removal (`cvt_remove_item_image`) now performs per-item ownership check before applying capability gate.
- Transient-based rate limiter (60 req/user/min) added to the vendor search AJAX endpoint.
- `selling_price` and `market_value` in item sanitization clamped to non-negative values with `max(0, ...)`.
- Date fields validated against `YYYY-MM-DD` format before storage.
- Field lengths capped in all model `sanitize()` methods via `CVT_Settings::max_lengths()`.

**Bug fixes (carried over from 1.0.1)**
- XSS fix in `CVT_Activity_Log::describe()` — display name and status labels now escaped with `esc_html()`.
- Dashboard activity feed payout links corrected (now points to Payouts list, not wrong item ID).
- Minimum WordPress version corrected to 6.2 (`%i` placeholder requirement).

### 1.0.1 — Bug Fixes
- **Security:** Escaped `$actor` (user display name) with `esc_html()` in `CVT_Activity_Log::describe()` to prevent XSS from maliciously crafted display names.
- **Bug fix:** Dashboard activity feed now correctly links payout log entries to the Payouts list instead of incorrectly using the payout ID as an item ID.
- **Bug fix:** AJAX image removal handler now verifies per-item ownership before acting, not just the global capability.
- **Compatibility:** Updated minimum WordPress requirement from 5.8 to 6.2 to match usage of `%i` identifier placeholder in `$wpdb->prepare()`.

### 1.0.0 — Initial Release
- Custom DB tables: vendors, items, item_images, payouts, activity_log.
- Three-tier RBAC: cvt_admin, cvt_senior_agent, cvt_junior_agent.
- Full vendor and item CRUD with ownership-aware permissions.
- Enforced status workflow with server-side transition validation.
- Auto-payout creation on Sold; auto-close on mark-paid.
- Commission rate snapshots per payout record.
- Vendor typeahead search (AJAX) in item form.
- Live payout preview as selling price is typed.
- WP Media Library integration for item images.
- Full audit trail on all mutations.
- Dashboard with stat cards, status breakdown, activity feed.
- Reports page with monthly summary and CSV export.
- Material Design-inspired admin CSS, scoped to `.cvt-` prefix.
- Mobile-responsive layout, no external CSS/JS dependencies.
- Fully compatible with Listivo theme (no CPTs, no shared CSS, no global hooks).
