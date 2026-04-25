# Silverstripe Contacts Module

A central, flexible contact management system for Silverstripe 6. Manage individuals and companies in a unified data model — complete with addresses, tags, customer numbers, avatars, and vCard export.

## Features

- **Unified contact model** — persons and companies in a single `Contact` record
- **Multiple addresses per contact** — home, business, and other address, each with international formatting via `commerceguys/addressing`
- **Tag system** — assign contacts to one or more groups via `ContactTag`
- **Customer numbers** — auto-generated from a configurable template with date parts and random segments
- **Avatar upload** — image upload with initials fallback
- **vCard download** — export any contact as a `.vcf` file (requires `DOWNLOAD_CONTACTS` permission)
- **CMS member linking** — optionally link a contact to a Silverstripe `Member` account
- **Website owner** — designate one contact as the site owner, accessible globally in templates
- **Permissions** — fine-grained CMS permissions (`ACCESS_CONTACTS`, `DOWNLOAD_CONTACTS`)
- **Extensible CMS layout** — sidebar + main tabs layout with extension hooks

---

## Installation

```bash
composer require clesson-de/silverstripe-contacts
```

```bash
composer vendor-expose
```

Then run `/dev/build?flush=all`.

---

## Configuration

### Website owner

The module adds a dropdown to your `SiteConfig` for selecting a contact as the website owner. The selected contact is available as a global template variable:

```html
<% if $siteOwner %>
    <% with $siteOwner %>
        <p>&copy; All rights reserved by $Name</p>
    <% end_with %>
<% end_if %>
```

Or via PHP:

```php
$owner = Contact::current_site_owner();
```

### Customer number template

The customer number template is configured in **Settings → Contacts**. The default is `K-{Y}-{N:3}`.

Available placeholders:

| Placeholder | Description | Example |
|---|---|---|
| `{Y}` | Year (4-digit) | `2026` |
| `{y}` | Year (2-digit) | `26` |
| `{m}` | Month | `04` |
| `{d}` | Day | `23` |
| `{H}` | Hour | `14` |
| `{i}` | Minute | `07` |
| `{s}` | Second | `52` |
| `{N:3}` | Random digits (length 3) | `047` |
| `{A:2}` | Random uppercase letters (length 2) | `KX` |
| `{X:4}` | Random uppercase letters + digits (length 4) | `A3K9` |

Examples:

```
K-{Y}-{N:3}    →  K-2026-047
{Y}{m}-{X:4}   →  202604-A7K2
{A:2}{N:4}     →  KX0391
```

### Contact tags

Define tags that are created automatically on `/dev/build`:

```yml
Clesson\Contacts\Models\ContactTag:
  default_tags:
    client: 'Clients'
    customer: 'Customers'
```

### Address types

```yml
Clesson\Contacts\Models\AddressType:
  default_tags:
    invoice-address: 'Invoice address'
    delivery-address: 'Delivery address'
```

---

## Developer Documentation

### Query helpers

```php
use Clesson\Contacts\Models\Contact;

// Get a single contact by its URL slug
$contact = Contact::get_by_slug('roy-orbison');

// Get all contacts with a specific tag (by unique key)
$clients = Contact::get_by_tag('client');
```

### Contact type detection

```php
$contact->isPerson();   // true if no company name is set
$contact->isCompany();  // true if Name1 or Name2 is filled

$contact->getFullName();  // formatted name with salutation, title, etc.
$contact->getAddress();   // primary address (Home > Business > Other)
```

### vCard download

The module exposes a public route for vCard downloads:

```
/contact/vcard/{slug}
```

Requires the `DOWNLOAD_CONTACTS` permission. The `Contact` model provides a `getvCardLink()` method and a CMS action button.

### CMS extension hooks

The contact detail form can be extended via Silverstripe extension hooks:

| Hook | Description |
|---|---|
| `updateMainTabSet(TabSet $tabSet)` | Add or modify main tabs |
| `updateMarginalCMSFields(FieldList $fields)` | Add fields to the sidebar |
| `updateFixedMarginalCMSFields(FieldList $fields)` | Add fields to the fixed sidebar area |
| `updateSlug(string &$slug)` | Modify the auto-generated slug before it is saved |

### Permissions

| Permission | Description |
|---|---|
| `ACCESS_CONTACTS` | View, create, edit, and delete contacts |
| `DOWNLOAD_CONTACTS` | Download contacts as vCard files |

---

## Frontend Assets

The module ships with compiled admin CSS in `client/admin/dist/`.  
If you want to modify the styles, you need to recompile them.

The source file is `client/admin/src/scss/ss-contacts.scss`.

#### Prerequisites

The required Node.js version is specified in `.nvmrc` (currently Node 22).  
If you use [nvm](https://github.com/nvm-sh/nvm), switch to the correct version with:

```bash
nvm use
```

#### Install dependencies

```bash
npm install
```

#### Build

```bash
npm run build
```

| Script | Input | Output |
|---|---|---|
| `build:js` | `client/admin/src/index.js` | `client/admin/dist/bundle.js` |
| `build:css` | `client/admin/src/scss/ss-contacts.scss` | `client/admin/dist/bundle.css` |

#### Watch mode (during development)

```bash
npm run watch
```

> **Note:** The output filenames `bundle.js` and `bundle.css` are **static** and must not be renamed — they are referenced by name in PHP.
