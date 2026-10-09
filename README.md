# Deliva

Deliva lists local service providers by service and area. Providers can create
accounts and submit listings for review; only approved, active listings appear
in the public directory.

## Local setup

1. Start Apache and MySQL in XAMPP.
2. Import `database/deliva.sql` into MySQL for a fresh installation.
3. For an existing installation, apply `database/migrations/001_provider_portal.sql`
   once using phpMyAdmin or the MySQL command line.
4. Check the database connection details in `config/database.php`.
5. For an existing installation, apply
   `database/migrations/002_provider_password_resets.sql` after the provider
   portal migration.
6. For an existing installation, apply
   `database/migrations/003_admin_password_resets.sql`.
7. For an existing installation, apply
   `database/migrations/004_service_requirements_and_provider_photos.sql`.
8. For an existing installation, apply
   `database/migrations/005_combine_photography_services.sql`.
9. For an existing installation, apply
   `database/migrations/006_provider_opening_schedule.sql`.
10. For an existing installation, apply
    `database/migrations/007_demo_provider_listings.sql`.
11. For an existing installation, apply
    `database/migrations/008_kisii_county_areas.sql`.
12. For an existing installation, apply
    `database/migrations/009_correct_kisii_area_names.sql`.
13. For an existing installation, apply
    `database/migrations/010_remove_sample_listing_labels.sql`.
14. From the project directory, create the first administrator using
   `php admin/create-admin.php`. This command only works in a local CLI and
   only while no admin account exists. Use a unique password with at least 12
   characters.
15. Open `http://localhost/deliva/`. Administrators sign in at
   `http://localhost/deliva/admin/`; providers use the **provide service**
   button on the home page to create an account, submit listings, and manage
   them.

Provider-submitted listings are hidden until an administrator approves them.
Editing a listing sends it back for approval. Administrators can also add
services and publish provider listings directly from the admin dashboard.
The admin dashboard is organized into tabs for adding services, editing service
requirements, adding providers, browsing providers by service, and reviewing
pending approvals. Service requirements control which provider fields are
required, whether photo uploads or delivery are supported, and whether a
service is online-only. Provider photos accept JPG, PNG, and WEBP files up to
5 MB each, with up to five photos per listing.
Photography is a single service with an indoor/outdoor listing option; its
provider form requires a business name, location, and owner phone number.
Provider schedules use separate opening and closing time controls with
weekday checkboxes for closed days.
To add up to 20 sample provider listings per active service, run
`php admin/seed-demo-providers.php` from the project directory. This downloads
placeholder photos from Lorem Picsum. Sample contact numbers are non-callable
and are not linked to phone or WhatsApp actions.
Provider locations are restricted to listed areas in Kisii County.

Provider sign-up and sign-in are separate pages. Password recovery sends a
single-use link that expires after 30 minutes. Configure the SMTP password in
the Apache environment as `DELIVA_SMTP_PASSWORD` before using password recovery
(for XAMPP, add `SetEnv DELIVA_SMTP_PASSWORD "your-smtp-password"` to Apache
configuration and restart Apache). The non-secret SMTP host, port, sender, and
public reset-link base URL are set in `config/mail.php`. Provider and admin
password-reset links are handled by separate pages.

To display a logo, place a PNG image at `img/logo.png`.