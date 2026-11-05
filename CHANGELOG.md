# Changelog

## [0.3.3] - Unreleased
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
