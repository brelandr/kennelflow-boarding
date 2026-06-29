=== KennelFlow Boarding ===
Contributors: brelandr
Tags: pets, boarding, booking, kennels
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.6
Text Domain: kennelflow-boarding
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Boarding for KennelFlow: kennels, bookings, availability REST API, kennel rules, and a Mobile Report Card for staff. Requires KennelFlow Core.

== Description ==

KennelFlow Boarding (KennelPress) is the boarding companion for **KennelFlow Core**. It registers kennels and bookings, checks availability, exposes REST endpoints for custom booking flows, and adds front-desk tools for daily operations.

**What you get**

* **KennelPress Front Desk** — top-level menu with links to bookings and the kennel calendar.
* **Kennel bookings** — create and manage boarding reservations (pet, kennel, location, dates, status).
* **Kennels** — define runs and rooms with location, resource type, and capacity.
* **Kennel calendar** — REST-fed calendar view for a selected UTC date range.
* **Boarding staff calendar (front-end)** — Hub occupancy grid with kennel rows and Add booking via `[kennelflow_boarding_calendar]` (requires KennelFlow Core).
* **Kennel rules** — per-location hours, holiday closures, blackout windows, and boarding windows (React UI under KennelFlow).
* **Mobile Report Card** — staff PWA to send daily photo + checklist emails to pet owners for checked-in boarding stays.
* **REST API** — availability, locations, kennels, bookings, facility settings, and report cards under `kennelflow-boarding/v1` (legacy `kennelpress/v1` routes remain registered for compatibility).

Pets and physical locations use **KennelFlow Core** (`kf_pet`, `kf_location`). WooCommerce is optional; use **KennelFlow Boarding Pro** for checkout and payment links.

== Try It Live - Preview This Plugin Instantly ==

Preview KennelFlow Boarding in WordPress Playground: the blueprint installs **KennelFlow Core** and **KennelFlow Boarding** from WordPress.org, seeds demo pets and the owner portal, adds a **Book Boarding** page with `[ltkf_booking]`, and opens **KennelPress Front Desk** in wp-admin. Log in as **admin** / **password** (demo owner: **demoowner** / **password**).

[Preview on WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/brelandr/kennelflow-boarding/main/blueprint.json)

The blueprint ships as `blueprint.json` and `assets/blueprints/blueprint.json`. WordPress.org also serves a copy from plugin SVN for directory live preview.

== Mobile report card (staff PWA) ==

Authorized staff can send a daily photo + checklist email to the pet owner for boarding stays:

* **Who can use it:** WordPress users with `edit_posts` or `kennelpress_send_reports`.
* **Staff URL (no wp-admin chrome):** `https://yoursite.com/kennelflow-mobile` — requires pretty permalinks (not “Plain”). After install, open **Settings → Permalinks** and click **Save** once if the URL 404s.
* **Fallback URL:** `https://yoursite.com/?kf_pwa=1`
* **WordPress admin:** **KennelPress → Mobile Report Card** (same app, full-width, admin menu hidden).
* **What it does:** Lists **boarding** bookings with status **checked in** for **today** (site timezone). Take a photo, set mood, breakfast, bathroom, notes, then **Send Report Card**. The image is saved to the Media Library and the owner receives an HTML email (pet owner must be linked via KennelFlow Core).
* **REST:** `POST /wp-json/kennelflow-boarding/v1/report-cards` (multipart: `booking_id`, `photo`, `mood`, `ate_food`, `bathroom`, `notes`).

== KennelFlow Boarding Pro ==

Premium add-on for WooCommerce checkout and booking payment links:

https://landtechwebdesigns.com/

== Installation ==

1. Install and activate **KennelFlow Core** (required).
2. Install KennelFlow Boarding through the WordPress.org plugin directory or upload the zip under **Plugins → Add New → Upload Plugin**.
3. Activate KennelFlow Boarding.
4. Add kennels under **KennelFlow → Kennels** and manage bookings under **KennelPress → Kennel bookings**.

= Build assets (developers) =

From the plugin directory: `npm install` then `npm run build` (facility settings + Mobile Report Card PWA). Requires `build/pwa-report-card.js` and `assets/dist/facility-settings.js` for those screens to work.

== User guide ==

Step-by-step setup for the KennelFlow stack (Hub calendar, kennels, REST, booking flows) is in **docs/PLATFORM_GUIDE.md** at the KennelFlow repository root.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. The free plugin does not require WooCommerce. WooCommerce is optional and used with **KennelFlow Boarding Pro**.

= Does Boarding work without KennelFlow Core? =

No. Activate KennelFlow Core first. WordPress lists Core as a required plugin when supported by your site.

= Where is the Mobile Report Card? =

Use **KennelPress → Mobile Report Card** in wp-admin, or open `https://yoursite.com/kennelflow-mobile` while logged in as staff.

= How do boarding desk staff use the front-end calendar? =

Create a WordPress page (for example **Boarding Calendar**) and add the shortcode `[kennelflow_boarding_calendar]`. Logged-in front-desk staff see kennel rows, multi-day stays, drag-to-reschedule, and **Add booking**. Requires **KennelFlow Core** with the compiled `build/` calendar bundle (`npm run build` in Core).

You can also use the shared staff page shortcode `[ltkf_hub_calendar booking_kind="boarding" corner_label="Kennel"]`. Boarding desk users on a generic staff calendar page automatically default to the boarding view when no `booking_kind` is set.

== Screenshots ==

1. Kennel bookings list with pet, kennel, dates, and status columns.
2. Booking editor with pet, location, kennel, schedule, status, and boarding quote snapshot.
3. Kennel booking calendar loaded from the REST API for a selected date range.
4. WooCommerce boarding product setup (optional; pairs with KennelFlow Core revenue tools).
5. WooCommerce boarding product pricing tiers for multi-night stays.
6. WooCommerce boarding product variations for pet size and stay length.
7. Kennel rules — hours, boarding windows, holidays, and per-location pricing settings.
8. Mobile Report Card — today’s checked-in boarding roster on a phone-friendly staff screen.
9. Mobile Report Card — daily photo and checklist form emailed to the pet owner.

== Credits ==

**Developer:** Randy Breland ([brelandr](https://profiles.wordpress.org/brelandr/)), [Land Tech Web Designs](https://landtechwebdesigns.com). Contact: sales@landtechwebdesigns.com

== Changelog ==

= 0.2.6 =
* Staff calendar stay photos: grant `upload_files` to boarding desk roles; calendar upload permission uses boarding booking caps.

= 0.2.5 =
* Staff calendar **Stay photos** (check-in / check-out) on boarding stays: Take photo, Choose photo, admin booking meta box, and REST API.

= 0.2.4 =
* Fix kf_bookings index sync: UPDATE existing rows instead of REPLACE so calendar row IDs stay stable when status changes (e.g. Check in).

= 0.2.3 =
* Booking editor: **Reason for visit** and **Appointment notes** fields for clinic hub appointments (`kennelpress_booking`); REST exposes `appointment_notes`.

= 0.2.2 =
* Clinical staff (KennelFlow Vet roles) can open **clinic** kennelpress_booking rows from the Hub staff calendar in wp-admin.

= 0.2.1 =
* Boarding desk detection no longer treats every `edit_posts` user as front desk (fixes veterinarians seeing kennel calendar on the shared Staff Calendar page).

= 0.2.0 =
* Front-end **Boarding staff calendar** shortcode `[kennelflow_boarding_calendar]` (Hub React calendar with kennel rows and Add booking). Boarding desk staff on `[ltkf_hub_calendar]` default to the boarding view. Front Desk quick links include the page when found.

= 0.1.9 =
* Booking editor pet dropdown: show owner name beside pet name (requires KennelFlow Core 0.3.10+).

= 0.1.8 =
* Boarding desk staff may view the front-end Staff Calendar (`ltkf_user_can_view_hub_calendar` filter).

= 0.1.7 =
* Fix Hub menu capability filter: only apply boarding desk caps to users who have them (was blocking groomers and other staff).

= 0.1.6 =
* Front-desk roles: can open **any** kennel booking and Hub **pet** record (online bookings are no longer blocked by post author).
* KennelPress Front Desk: **Pets** submenu and quick link; bookings list **Open booking** row action; pet names link to the pet editor.
* Kennel bookings use dedicated capabilities (`edit_kennelflow_boarding_bookings`) for desk staff.

= 0.1.5 =
* Front Desk: **Pending approvals** table on the KennelPress home screen with one-click **Confirm**.
* Kennel bookings list: **Confirm** / **Check in** row actions; status column quick links.
* Booking editor: banner with **Confirm booking** when status is pending.
* Kennel calendar: **Confirm** / **Check in** buttons per row; fixed calendar menu link on Front Desk home.

= 0.1.4 =
* Boarding quotes and bookings: per-pet size map (`boarding_pet_sizes`) for multi-pet stays; facility hours validation respects emergency drop-off and extended pick-up flags on submit.

= 0.1.3 =
* REST bookings: accept optional `boarding_companion_pet_ids` array; store companion pet IDs in boarding choices JSON for multi-pet online bookings.

= 0.1.2 =
* Full boarding stack: Front Desk menu, kennels and bookings CPTs, kennel calendar, and kennel rules (facility settings).
* REST API under `kennelflow-boarding/v1` with legacy `kennelpress/v1` compatibility.
* Hub calendar bridge and boarding calendar resources for KennelFlow Core integration.
* Mobile Report Card PWA: public `/kennelflow-mobile`, admin screen, report-cards REST, and rewrite auto-flush.
* Booking transaction, availability, and admin booking editor improvements.
* WordPress.org listing assets: banners, icons, and screenshots.

= 0.1.1 =
* Internal release alignment and REST hardening.

= 0.1.0 =
* Initial WordPress.org release.
* Readme: **Tested up to: 7.0** (WordPress 7.0 release alignment).
