# Corido Vendor Tracker

Internal WordPress plugin for [Corido Marketplace](https://corido.co.ke) — a Kenya-based recommerce platform. Gives the Customer Care team a clean, auditable system to manage vendor intake, item listings, deal tracking, commissions, and payouts entirely within WordPress admin.

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

To uninstall cleanly: deactivate the plugin, then delete it. The database tables are **not** dropped on deactivation — only on full plugin deletion (this prevents accidental data loss). If you want to drop tables on delete, add a `uninstall.php` file that calls `CVT_Roles::remove()` and drops each `wp_cvt_*` table.

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

- **No custom post types are registered.** All data lives in five dedicated `wp_cvt_*` tables. Listivo's `wp_posts`/`wp_postmeta` tables are untouched.
- **CSS/JS assets load only on CVT admin pages** (`page=cvt-*`). They do not affect the front end or any other admin page.
- **All CSS classes use the `.cvt-` prefix.** No style conflicts with Listivo's admin or front-end CSS.
- **No global hooks.** The plugin does not hook into `save_post`, `wp_head`, or other wide-scope hooks.
- **Capability names use the `cvt_` prefix.** No collision with Listivo's own capabilities or roles.

**Listing URL field:** When an agent posts an item on the Corido website (as a Listivo listing), they paste the listing URL into the item's *Listivo Listing URL* field. The plugin stores it as a plain link — no sync, no dependency on Listivo's internal structure. This keeps the integration stable across Listivo theme updates.

**Auto-sync (future):** If you want to auto-create Listivo listings when an item moves to *Posted*, that can be built as an optional add-on module that hooks into `CVT_Item::update_status()`. This is intentionally out of scope for v1 to avoid tight coupling.

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
| `deal_type` | enum | consignment, agency |
| `status` | enum | under_review, posted, inquiry_received, sold, closed, withdrawn |
| `assigned_agent_id` | bigint → wp_users.ID | nullable |
| `agreement_attachment_id` | bigint → wp_posts.ID | nullable, signed consignment agreement |
| `listivo_listing_url` | varchar(500) | manual paste, no sync |
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
| `entity_type` | enum | vendor, item, payout |
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
│   ├── class-cvt-waitlist.php          # Waiting list model (CRUD, match detection)
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
│   │   │   ├── list.php                # Waiting list entries with filters
│   │   │   └── form.php                # Add / edit waiting list entry
│   │   ├── settings.php                # Commission rate, categories, agent overview
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

### Extending with Listivo auto-sync

To auto-create a Listivo listing when an item moves to `Posted`, hook into the `CVT_Item::update_status()` method's side-effects. The cleanest approach is to add a WordPress action in a separate file:

```php
// In a separate file (not in the core plugin).
add_action( 'cvt_item_status_changed', function( $item_id, $old_status, $new_status ) {
    if ( $new_status !== 'posted' ) return;
    $item = CVT_Item::get( $item_id );
    // Create Listivo listing CPT here...
    // Store returned post ID back to cvt_items.listivo_listing_url
}, 10, 3 );
```

You would also need to fire `do_action( 'cvt_item_status_changed', $id, $old, $new )` at the end of `CVT_Item::update_status()`.

---

## Changelog

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
