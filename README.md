# Ticky CRM

An Open Source, privacy-first Customer Relationship Management (CRM) app built from the ground up natively for **Nextcloud**. Manage your clients, track interactions, and collaborate with your team without leaving your digital sovereignty behind.

[License](LICENSE)  
[Nextcloud Compatibility](https://nextcloud.com)

---

## ✨ Features

- **Client Management:** CRUD operations for clients with differentiation between companies and individuals.
- **Contacts and Address book:** Central address book for clients and contacts with full integration with Nextcloud.
- **Master Data:** Dedicated fields for contact info, billing details, VAT IDs, and commercial registration.
- **Interaction Timeline:** Chronological logging for notes, calls, and meetings with inline editing.
- **User Mentions:** Mentions (`@`) in notes using Nextcloud autocomplete and visual badges.
- **Access Control:** Settings to restrict app access to specific Nextcloud groups or users.
- **Security Middleware:** Backend validation blocking unauthorized API requests and views.
- **Localization:** Full English (`en`) and German (`de`) support including pluralization rules.
- **Nextcloud UI:** Built with official Vue components.

---

## 🚀 Roadmap &amp; Milestones

- ✅ **Customer Management**
- ✅ **Customer Notes**
- ✅ **Contacts Integration**
- ✅ **Address book for CRM**
- 🔄 **Relations between customers**
- 🔄 **Products**
- 🔄 **File Integration**
- ⏳ **Helpdesk**
- ⏳ **Quotes/Offers**

---

## 📋 Requirements

- **Nextcloud:** v30.x to v33.x
- **PHP:** v8.2 to v8.4
- **Database:** MariaDB/MySQL or PostgreSQL (supported by Nextcloud Core)

---
## Development
```
npm run dev
npm run lint
npm run lint:fix
npm run build
npm run build:translations


php occ files:cleanup

```
---

⚖️ License  
This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the LICENSE file for details.

👤 Author  
Developed and maintained with ❤️ by IdentSpace.