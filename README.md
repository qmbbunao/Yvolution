# Yvolution Custom Apparel — Module 1: Database + Auth Foundation

## What's in this module
- `database/schema.sql` — full MySQL schema (users, roles, products, services,
  packages, promotions, orders, quotations, design uploads, inventory,
  homepage CMS, audit logs, API settings, etc.)
- `database/seed_superadmin.php` — one-time script to create your first Super Admin
- `config/database.php` — PDO connection
- `config/app.php` — session bootstrap, constants
- `config/api_keys.php` — placeholder credentials for the 5 APIs (fill these in)
- `includes/functions.php`, `includes/auth_guard.php` — helpers + role guards
- `includes/header.php`, `includes/footer.php` — shared layout
- `auth/register.php`, `auth/login.php`, `auth/logout.php` — working auth flow
- `public/index.php` — placeholder landing page (confirms everything is wired)
- `customer/dashboard.php`, `admin/dashboard.php`, `superadmin/dashboard.php` — role-protected stubs

## Setup (XAMPP + phpMyAdmin)

1. Copy the entire `yvolution` folder into `C:\xampp\htdocs\` (Windows) or
   `/Applications/XAMPP/htdocs/` (Mac).
2. Start Apache and MySQL in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`, click **Import**, and import
   `database/schema.sql`. This creates the `yvolution_db` database and all tables.
4. Visit `http://localhost/yvolution/database/seed_superadmin.php` once in your
   browser. It creates the first Super Admin:
   - Email: `superadmin@yvolution.com`
   - Temp password: `SuperAdmin123!`
   **Delete `seed_superadmin.php` after running it once.**
5. Open `config/database.php` and confirm the credentials match your MySQL
   setup (default XAMPP is user `root`, empty password — already set).
6. If your project folder is named something other than `yvolution`, update
   `BASE_URL` in `config/app.php`.
7. Add your real `logo.png` to `assets/images/logo/logo.png` (used in the
   navbar, favicon, and auth pages).
8. Visit `http://localhost/yvolution/public/index.php` — you should see the
   landing page. Try registering a customer account, then logging in.

## API keys to fill in later (config/api_keys.php)
| API | Free tier | Used for |
|---|---|---|
| Gmail SMTP (via PHPMailer) | Free | Order/status/quotation emails |
| Cloudinary | Free tier (25GB) | Design file & product image uploads |
| QR Server API | Free, no key needed | Order tracking QR codes |
| OpenStreetMap Nominatim + Leaflet.js | Free, no key needed | Delivery address lookup & map |
| Google Calendar API | Free (Google Cloud Console) | Production scheduling |

## Module 2 additions (public landing page + email)
- `public/index.php` — full landing page: hero video, about, services, products,
  packages, active promotions, approved testimonials, contact form
- `public/product_details.php` — full product view (price, sizes, colors, stock, related items)
- `public/contact_submit.php` — saves inquiries to `contact_inquiries` + emails your shop
- `vendor/phpmailer/` — PHPMailer library (downloaded directly, no Composer needed)
- `includes/mailer.php` — `Mailer` class wrapping PHPMailer + Gmail SMTP
- `includes/functions.php` — added `email_template()` for branded HTML emails

### To make the contact form actually send email
1. In Gmail: enable 2-Step Verification, then create an **App Password**
   (Google Account → Security → App Passwords).
2. Open `config/api_keys.php` and fill in `smtp.username` (your Gmail) and
   `smtp.password` (the 16-character App Password) — not your real Gmail password.

### Hero video & images
- Drop an `.mp4` at `assets/videos/hero.mp4` for the homepage hero background,
  or set `video_url` on the `hero` row in `homepage_content` (via phpMyAdmin
  for now — the Admin CMS UI comes in Module 4) to point at any video URL.
- Add product photos to `assets/images/products/` and reference them from the
  `products.image_url` column (URLs will normally come from Cloudinary once
  the Admin product form is built).
- A `placeholder.jpg` under `assets/images/products/` is used as a fallback
  when a product has no image yet — add one so broken-image icons don't show.

## Module 3 additions (Customer module)
- `customer/dashboard.php` — real order stats + recent orders
- `customer/orders/create.php` — order form: item type (product/service/package/custom),
  size/color/qty/notes, design file uploads, delivery address with a live map
  (Nominatim geocoding + Leaflet preview)
- `customer/orders/store.php` — saves the order, uploads design files to Cloudinary,
  generates a QR tracking code, sends a confirmation email
- `customer/orders/index.php` — order history
- `customer/orders/view.php` — full order detail: items, quotation (accept/decline),
  uploaded designs, QR code, status timeline, feedback form (once completed)
- `customer/orders/track.php` — public QR-scan tracking page (no login needed)
- `customer/orders/quotation_respond.php`, `feedback_submit.php` — form handlers
- `customer/profile/edit.php` — update info, upload profile photo, change password
- `includes/cloudinary.php` — signed Cloudinary upload/delete via cURL (no SDK)
- `includes/qr.php` — QR Server API helper
- `includes/geocode.php` — Nominatim geocoding helper

### To make uploads and maps work
1. Sign up free at cloudinary.com, then fill in `cloud_name`, `api_key`,
   `api_secret` in `config/api_keys.php` under `cloudinary`.
2. Nominatim and QR Server need no keys — they work out of the box.

## Module 4 additions (Admin module)
- `includes/admin_header.php`, `includes/admin_footer.php`, `assets/css/admin.css` —
  shared sidebar dashboard layout for Admin & Super Admin
- `admin/dashboard.php` — live stats (orders by status, revenue, customers, low
  stock alerts, pending design reviews, new inquiries)
- `admin/orders/` — full order workflow:
  - `index.php` — filterable/searchable order list
  - `view.php` — item details, approve/reject uploaded designs, create & send
    quotations, manual status control with customer email notifications
  - `design_review.php`, `quotation_create.php`, `status_update.php` — handlers
- `admin/products/`, `admin/services/`, `admin/packages/`, `admin/promotions/` —
  full CRUD (list, form, save, delete) each with Cloudinary image upload.
  Deleting an item that already has order history deactivates it instead of
  hard-deleting, so past orders stay intact.
- `admin/inventory/` — stock list, add items, +/- stock adjustments logged to `inventory_logs`
- `admin/customers/` — customer list with order counts/spend, activate/deactivate accounts
- `admin/homepage/` — edit hero tagline/video & about text, manage banners, moderate
  testimonials (approve/hide/feature) — customer feedback auto-feeds in as "pending"

### Note
Contact inquiries from the public form are saved and counted on the dashboard,
but there's no dedicated inbox page yet to browse/reply to them — that can be
added alongside the Super Admin module if you want it.

## Module 5 additions (Super Admin module)
- `superadmin/dashboard.php` — system-wide stats (users by role, total revenue),
  quick links to every tool, recent activity feed
- `superadmin/users/` — manage Admin & Super Admin accounts: list/filter by
  role, create/edit, activate/deactivate (can't deactivate your own account)
- `superadmin/settings/index.php` — enable/disable each of the 5 integrations
  system-wide (actual credentials stay in `config/api_keys.php` — never shown
  in the browser). Flags integrations still using placeholder values.
- `superadmin/analytics/index.php` — revenue trend (6 mo.), order status
  breakdown, top products by quantity, new customer growth — all live from
  the database, no charting library needed
- `superadmin/analytics/audit_logs.php` — searchable, paginated system-wide
  activity trail (every module logs here via `log_audit()`)
- `superadmin/backups/` — run `mysqldump` backups on demand, view backup
  history, download `.sql` files, restore from an uploaded `.sql` file
  (`config/backup.php` holds the mysqldump/mysql binary paths — XAMPP
  bundles both; adjust if they're not on your system PATH)
- `database/backups/.htaccess` — blocks direct web access to raw backup files

### Backup/restore notes
- Backups run via PHP `exec()`, so your PHP install needs `exec` enabled
  (it usually is on XAMPP by default) and the `mysqldump`/`mysql` binaries
  reachable — see `config/backup.php`.
- Restoring **overwrites your current database** — there's a confirmation
  prompt, but there's no built-in "undo" beyond restoring an older backup.

## All modules complete
1. ✅ Database schema + auth + role guards
2. ✅ Full public landing page + contact form email
3. ✅ Customer module — orders, uploads, quotations, tracking, feedback
4. ✅ Admin module — orders workflow, catalog CRUD, inventory, customers, homepage CMS
5. ✅ Super Admin module — users/roles, API settings, analytics, audit logs, backup/restore

## Module 6 additions (Google Calendar, inquiries inbox, UI fixes)

### Google Calendar API — now wired up
- `includes/google_calendar.php` — OAuth2 + event CRUD via cURL (no SDK)
- `superadmin/settings/google_connect.php` / `google_callback.php` / `google_disconnect.php`
  — full OAuth consent flow, tokens stored in the `api_settings` table
- `superadmin/settings/index.php` — shows connection status, Connect/Disconnect button
- **Setup:** create OAuth credentials in Google Cloud Console (OAuth client ID,
  type "Web application"), add `http://localhost/yvolution/superadmin/settings/google_callback.php`
  as an authorized redirect URI, then fill `client_id`/`client_secret` into
  `config/api_keys.php` under `google_calendar`. Go to Super Admin → API
  Settings → Connect Google Calendar.
- **How it's used:** on an order's detail page, when Admin sets status to
  "In Production" and fills in a Production Date, a calendar event is created
  automatically (`orders.production_event_id` stores the Google event ID).
  The event is auto-removed if the order later moves to Completed or Cancelled.
  All of this is best-effort — if Calendar isn't connected, status updates
  still work normally, they just skip the calendar sync.

### Contact Inquiries Inbox
- `admin/inquiries/index.php` — filterable inbox (new/read/responded)
- `admin/inquiries/view.php` — full message, marks as read on open, reply
  by email (via PHPMailer) which marks the inquiry as "responded"
- Linked from the sidebar and the Admin dashboard's "Needs Attention" card

### UI fixes
1. **Logo stretching on login/register** — was being stretched by the auth
   page's flex column (default `align-items: stretch`). Fixed with explicit
   `width:auto`, `object-fit:contain`, and `align-self:flex-start`.
2. **Logout confirmation modal** — clicking "Log Out" (public navbar or admin
   sidebar) now opens a confirm/cancel modal instead of logging out immediately.
3. **Admin role clarified** — the three-tier role system (customer/admin/
   superadmin) was already in the schema since Module 1; added
   `database/seed_admin.php` (parallel to `seed_superadmin.php`) so you can
   spin up a test Admin account without needing to log in as Super Admin first.
   Admin is intentionally lower-privileged: no user management, API settings,
   analytics, audit logs, or backups — that's Super Admin only.
4. **Admin sidebar scrolling** — the nav list now scrolls independently
   (`overflow-y:auto` on just the nav), so the sidebar header and the
   user/logout control at the bottom are always visible without page scroll.
5. **Sidebar logout as popout** — the user chip at the bottom of the admin
   sidebar is now a toggle button; clicking it pops out "View Site" / "Log Out"
   above it instead of a permanently-visible logout button.
6. **Public navbar account dropdown** — logged-in users now see a single
   "[First Name] ▾" button that toggles a dropdown with My Account / My
   Orders / Log Out, instead of separate always-visible links.

## Known gaps (from Module 6)
- Google Calendar sync only covers the "In Production" milestone — you could
  extend it to also schedule "Ready for Pickup" or delivery dates the same way.

## Module 7 additions (fixes + big feature batch)

### ⚠️ Run this migration first
Your database already has data, so you need to run a migration in phpMyAdmin's
**SQL tab** before any of the features below will work (you'll get
"table/column doesn't exist" errors otherwise):

1. Open `http://localhost/phpmyadmin`, select `yvolution_db`, click **SQL**.
2. Open `database/migration_full.sql`, copy its entire contents, paste into
   the SQL box, and click **Go**.

This file is **idempotent** — safe to run more than once. If you already ran
an older copy of it before, re-running it will just skip whatever's already
there instead of failing. If phpMyAdmin reports an error on one of the very
last few statements (the ones adding `UNIQUE KEY`/`FOREIGN KEY` constraints
near the bottom of the file) on a re-run, that's expected and safe to ignore
— those two don't support "IF NOT EXISTS" the way columns do, but by the time
the script reaches them, every actual column/table the app needs has already
been added successfully.

Fresh installs don't need any migration — `schema.sql` already includes everything.

### Order cancellation (2-hour window)
- `customer/orders/cancel.php` — customer can cancel from the order detail
  page within 2 hours of placing it (checked server-side against `created_at`,
  not just hidden in the UI). Cancelling notifies them by email and logs to
  status history.

### Tarpaulin Printing service
- Seeded automatically by the migration. Any service can opt into the same
  behavior via a "Show event printing options" checkbox in Admin → Services —
  when checked, the order form shows Size / Event Type / Design Source
  (upload their own, or pick from `admin/design_templates/`, a new CRUD for
  ready-made designs you upload).

### Manual payment (GCash / bank transfer)
- `config/business.php` — put your real GCash number and bank details here.
- Customer order page shows those details plus an upload form for proof of
  payment (screenshot/receipt) → `payment_status` becomes `pending_verification`.
- Admin order page shows the proof image with Verify/Reject buttons.

### Returns & Refunds
- On a **completed** order, the customer sees a "Return / Refund" card:
  select a reason (damaged / lost in delivery / wrong item / other), describe
  it, optionally attach a photo.
- New Admin section (`admin/returns/`) to review, approve/reject, and mark
  as refunded with an amount — customer gets emailed at each step.

### Business location
- `config/business.php` also holds your shop address + coordinates, now
  shown in the site footer and as a live map on the Contact section.
  **Update the lat/lng** — the ones in there are an approximate center for
  Concepcion, Tarlac; search your exact address on openstreetmap.org and
  copy the precise coordinates from the URL.

### Product/customer UI changes
- Homepage product grid is now a fixed 3-per-row e-commerce card layout
  (2 on tablet, 1 on mobile) instead of stretching a single item full-width.
- Product images now show on the order form (`customer/orders/create.php`)
  when ordering a catalog product.
- Removed profile photo upload for customers (per request) — replaced with
  a **Home Address** section: search + pin your address once via map, then
  reuse it with a "Use My Home Address" button on future orders instead of
  re-typing every time.
- Generated real placeholder images (`assets/images/products/placeholder.jpg`,
  `assets/images/banners/hero-poster.jpg`) so a missing product photo shows
  a proper "No Image" box instead of a broken image icon.

### Social login (Google + Facebook) — step by step

Both are free. Neither needs a paid API tier for basic login.

**Google Sign-In:**
1. Go to https://console.cloud.google.com/ → create a project (or use an existing one).
2. Left sidebar → **APIs & Services → OAuth consent screen**. Choose "External", fill in app name/email, save (you can leave it in "Testing" mode for development — just add your own Google account under "Test users").
3. Left sidebar → **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
4. Application type: **Web application**.
5. Under "Authorized redirect URIs", add exactly: `http://localhost/yvolution/auth/google_callback.php`
6. Click Create — copy the **Client ID** and **Client Secret**.
7. Paste them into `config/api_keys.php` under `google_login` (`client_id`, `client_secret`).
8. Done — the "Continue with Google" button on login/register will now work.

*(Note: this is a separate OAuth client from the one used for Google Calendar in Super Admin → API Settings. You can reuse the same Google Cloud project, just create a second OAuth client ID so the redirect URIs don't clash.)*

**Facebook Login:**
1. Go to https://developers.facebook.com/ → **My Apps → Create App**.
2. Choose the "Consumer" use case (or "Authenticate and request data from users with Facebook Login").
3. In the app dashboard, add the **Facebook Login** product.
4. Under Facebook Login → Settings, add to "Valid OAuth Redirect URIs": `http://localhost/yvolution/auth/facebook_callback.php`
5. Go to **App Settings → Basic** — copy the **App ID** and **App Secret**.
6. Paste them into `config/api_keys.php` under `facebook_login` (`app_id`, `app_secret`).
7. While the app is in "Development" mode, only accounts added as Testers/Developers under **App Roles** can log in with it — add your own Facebook account there for testing. To let any Facebook user log in, you'd submit the app for Meta's App Review (not required for a school project demo).
8. Done — the "Continue with Facebook" button will now work for test accounts.

**How linking works:** if someone signs in with Google/Facebook using an email
that already has a local (password-based) account, it automatically links to
that same account instead of creating a duplicate — they can then log in
either way. Brand-new social sign-ins create a `customer`-role account with
no password (`password_hash` is now nullable) — if they later try logging in
with a password on that email, they get a friendly message pointing them
back to the correct button.

## All modules complete
1. ✅ Database schema + auth + role guards
2. ✅ Full public landing page + contact form email
3. ✅ Customer module — orders, uploads, quotations, tracking, feedback
4. ✅ Admin module — orders workflow, catalog CRUD, inventory, customers, homepage CMS
5. ✅ Super Admin module — users/roles, API settings, analytics, audit logs, backup/restore
6. ✅ Google Calendar, inquiries inbox, UI/UX fixes
7. ✅ Cancellation, tarpaulin/event printing, manual payments, returns/refunds, social login, business location

## Known gaps / good next steps for your report
- For a real production deploy (not localhost): move all of `config/api_keys.php`
  to environment variables, turn `display_errors` off in `config/app.php`,
  and put `database/`, `config/`, and `vendor/` outside the public web root.
- Facebook accounts without a verified email get a placeholder email
  (`fb_<id>@placeholder.yvolution.local`) since Facebook doesn't guarantee
  an email is returned — worth mentioning if asked in your defense.
- Refunds are recorded and emailed but not actually transferred — since this
  project deliberately excludes a real payment gateway API, "refunded" is a
  status the shop marks manually after sending the money themselves via
  GCash/bank, matching the same manual-payment approach used for receiving payment.
"# 50-" 
