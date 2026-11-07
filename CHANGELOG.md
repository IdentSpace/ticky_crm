# Changelog

## [0.4.0] - Unreleased

- **Development:** Add `occ ticky_crm:dev:seed` with configurable counts, unique prefixes and dry-run support to create varied customers, products and customer-product assignments.
- **Contact status:** Visual follow-up indicators in the customer list and details: no follow-up required, contact soon, overdue, inactive and no contact recorded.
- **Automatic follow-ups:** Calculate status when loading customers from the last contact date, with global administrator-configurable intervals (30/60 days by default). No cron job or timeline evaluation is required.
- **Manual overrides:** Switch between automatic and manual status per customer. Manual overrides remain in place when only the contact date changes; inactive customers are excluded from automatic follow-ups.
- **Contact status editor:** Reusable, collapsible component with Nextcloud Vue controls for automatic/manual mode, manual status, last contact date and a "Contacted today" action.
- **Notes:** Optionally update the contact date, contact status or both when creating a note. Save the note and contact changes together in one transaction and update the customer list immediately.
- **Filtering:** Compact contact-status dropdown in the customer header with checkbox-based multiple selection, bounded width and truncated selection text.
- **Table sorting:** Reusable sortable column headers with direction indicators, custom comparison rules and configurable left/center/right alignment. Enable sorting for customers and products, including follow-up priority and precise decimal price comparisons.
- **Table design:** Icon-only customer contact status with tooltips, centered status and count columns, subtle row borders and horizontal scrolling on narrow screens.
- **Translations:** Fix corrupted German umlauts and quotation marks and regenerate the language files.
- **App Store:** Restructure the app description with Markdown headings, feature lists, planned features and feedback links.

## [0.3.3] - Unreleased
- Fix contact foreign key detection with the NC 35 schema API during installation.
- Wait for Nextcloud app-config cache refresh in the product options permission test.
- Use PHP 8.3 and matching Composer platform settings for NC 35 static analysis.
- Run CI on pushes to all branches and on pull requests.
- Support Nextcloud 32-35 with PHPStan and installation tests in CI.
- Product activity tab with create/update/delete and customer link/update/unlink events.
- Customer activities for changes to agreed positions and linked catalog products.
- Activities for product assignment, assignment removal and catalog deletion.
- Configurable product categories, types, procurement, status, units and currencies with defaults in settings.
- Customer product tab with quantities and independently stored agreed prices.
- Consistent comma decimal display in forms and product lists.
- Product navigation, list, create dialog and editable details with validated CRUD API.
- Product database with current sales and optional purchase prices, tax rates, units and categorization.

## [0.2.2] - 2026-07-30
- **Bug Fixes:** Migration exception
- **Bug Fixes:** replace deprecated execute() to executeQuery()

## [0.2.1] - 2026-07-19
- **Bug Fixes:** Migration naming issue.
- **Bug Fixes:** Contact search dropdown slot was not correctly passed to the component.

## [0.2.0] - 2026-07-19
- **Contact:** Implement contact relation between customer and contacts from contacts app.
- **Address book:** The CRM Inits now a address book for crm contacts.

## [0.1.1] - 2026-07-01

- **Multiple Addresses:** Added support for multiple addresses for clients.
- **Activity Feed:** Added activity feed for recent interactions.
- **Bug Fixes:** Some UI issues.

## [0.1.0] - 2026-06-27

- **Client Management:** Core CRUD operations for companies and individuals.
- **Interaction Notes:** Notes, calls, and meetings timeline with inline editing.
- **User Mentions (@):** Autocomplete dropdown and custom mention badges.
- **Access Control:** Admin settings to restrict access by groups or users.
- **Security Middleware:** Backend validation blocking unauthorized requests.
- **Localization:** Full English (`en`) and German (`de`) support.
- **Nextcloud UI:** Built with native Nextcloud Vue components.
