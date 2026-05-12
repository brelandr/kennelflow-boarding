=== Kennel Press ===
Contributors: brelandr
Tags: pets, boarding, booking, kennels
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Foundation plugin for pet boarding: pets, kennels, locations, bookings, and availability via REST API. Built by Land Tech Web Designs.

== Description ==

Kennel Press registers custom post types for pets, kennels, and bookings, a location taxonomy, and REST endpoints to query which kennels are available for a date range at a given location. Use it as the data layer for boarding sites; extend with **Kennel Press Pro** for WooCommerce-powered checkout when you are ready.

**REST API**

The plugin exposes REST routes for availability and locations so themes and headless front ends can build booking flows on top of Kennel Press data.

**Mobile report card (staff PWA)**

Authorized staff can send a daily photo + checklist email to the pet owner for boarding stays:

* **Who can use it:** WordPress users with `edit_posts` or `kennelpress_send_reports` (the capability is granted to administrator, editor, and groomer roles by default; filter `kennelpress_roles_with_send_reports_cap` to adjust).
* **Clean staff URL (no wp-admin chrome):** `https://yoursite.com/kennelflow-mobile` — you must use “pretty” permalinks (not “Plain”). After installing or changing rules, open **Settings → Permalinks** and click **Save** once so the route is registered.
* **Fallback URL:** if you cannot use rewriting, open your home URL with `?kf_pwa=1` (for example `https://yoursite.com/?kf_pwa=1`). Guests are redirected to the login screen; after login, staff see the same app.
* **Inside WordPress admin:** **Pets → Mobile Report Card** loads the same React app in a full-width screen with the admin menu hidden.
* **What it does:** Lists **boarding** bookings with status **checked in** for **today** (site timezone). Tap a pet, take a photo (camera on supported phones), set mood (happy / calm / anxious), breakfast yes/no, bathroom yes/no, notes, then **Send Report Card**. The image is saved to the Media Library (attached to the booking), and the owner receives an HTML email via `wp_mail` (pet owner must be linked via KennelFlow Core pet meta).
* **REST (for integrations):**
  * `POST /wp-json/kennelpress/v1/report-cards` — `multipart/form-data`: `booking_id`, `photo`, `mood`, `ate_food`, `bathroom`, `notes`. Cookie or Application Password auth; same capability rules as above.
  * `GET /wp-json/kennelpress/v1/bookings` — optional query args `booking_kind`, `status` (e.g. `boarding` and `checked_in`) in addition to required `start` / `end` UTC range.
* **Build the PWA bundle (developers):** from the plugin directory run `npm install` then `npm run build` (runs both facility settings and `build/pwa`) or `npm run build:pwa` only. The file `build/pwa-report-card.js` must exist for the admin screen and public URL to work.

== Kennel Press Pro ==

Upgrade for WooCommerce integration and premium booking features:

https://landtechwebdesigns.com/

== Installation ==

1. Upload the `kennel-press` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure pets, kennels, locations, and bookings under the relevant admin menus.

== User guide ==

Step-by-step setup and daily use for the KennelFlow stack (Hub calendar, Kennel Press kennels and REST, resource types, booking flows) are documented in **docs/PLATFORM_GUIDE.md** at the KennelFlow repository root. Kennel Press requires **KennelFlow Core** to be installed and active first.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. The free plugin does not require WooCommerce. WooCommerce is optional and used when **Kennel Press Pro** is installed.

= Who develops Kennel Press? =

Kennel Press is developed and supported by Land Tech Web Designs.

== Changelog ==

= 0.1.0 =
* Initial release.
* Mobile Report Card PWA: public `/kennelflow-mobile` endpoint, admin screen, `report-cards` REST, booking list filters for roster (documented above).
