# Silverstripe Contacts Module

A central, flexible contact management system for Silverstripe 6. Manage companies, persons, and employees in a unified type hierarchy — complete with addresses, tags, customer numbers, payment accounts, avatars, and vCard export.

## Features

- **Contact type hierarchy** — abstract base class `Contact` with concrete subclasses `Company`, `Person`, and `Employee`
- **Company** — stores company name and optional suffix
- **Person** — stores gender, title, first/last name, nickname, birthday, marital status, and more
- **Employee** — links a `Person` to a `Company`, representing a working relationship
- **One address per contact** — each contact type has a single `has_one Address` relation; the semantic address type (home, business, work) is implied by the contact type
- **Tag system** — assign contacts to one or more groups via `ContactTag`
- **Payment accounts** — link bank accounts, credit cards, and online accounts via `ContactPaymentAccountsExtension` (requires `clesson-de/silverstripe-payment-accounts`)
- **Customer numbers** — auto-generated from a configurable template with date parts and random segments; only assigned to contacts in the CUSTOMER tag group
- **Avatar upload** — image upload with initials fallback
- **vCard download** — export any contact as a `.vcf` file (requires `DOWNLOAD_CONTACTS` permission)
- **CMS member linking** — optionally link a contact to a Silverstripe `Member` account
- **Website owner** — designate one contact as the site owner, accessible globally in templates
- **Permissions** — fine-grained CMS permissions (`USE_CONTACTS`, `DOWNLOAD_CONTACTS`)
- **Extensible CMS layout** — sidebar + main tabs layout with extension hooks
- **Delete protection** — `Company` and `Person` records linked to an `Employee` cannot be deleted

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.1` |
| silverstripe/framework | `^6` |
| clesson-de/silverstripe-geocoding | `^1` |
| clesson-de/silverstripe-payment-accounts | `^1` |
| jeroendesloovere/vcard | `^1.7` |
| giggsey/libphonenumber-for-php | `^8.13` |
| lekoala/silverstripe-cms-actions | `^2` |
| symbiote/silverstripe-gridfieldextensions | `^5` |
| clesson-de/silverstripe-gridfield-pro | `^2` |

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

## Data Model

### Contact hierarchy

```
Contact (base class)
├── Company        — Name1, Name2, Address (business)
├── Person         — Gender, Title, FirstName, LastName, …, Address (home)
└── Employee       — has_one Person, has_one Company, Address (work)
```

`Contact` is not declared with PHP's `abstract` keyword because Silverstripe's ORM requires concrete instantiation for `has_one` stubs. An Injector mapping in `_config/config.yml` maps `Contact` → `Person` as a fallback.

The CMS uses `GridFieldAddNewMultiClass` to offer `Company`, `Person`, and `Employee` as creation options.

### Shared fields (on `Contact`)

| Field | Type | Description |
|---|---|---|
| `Name` | `Varchar(255)` | Computed display name |
| `SortingName` | `Varchar(255)` | Computed sorting key |
| `Slug` | `Varchar(100)` | URL-safe unique slug |
| `Initials` | `Varchar(10)` | Computed initials |
| `Note` | `Text` | Free-text note |
| `CustomerNumber` | `Varchar(50)` | Auto-generated customer number |
| `CustomerSince` | `Date` | Date when customer number was assigned |

### Relations (on `Contact`)

| Relation | Type | Target |
|---|---|---|
| `Account` | `has_one` | `Member` |
| `Avatar` | `has_one` | `Image` |
| `Address` | `has_one` | `Address` |
| `Tags` | `many_many` | `ContactTag` |

### Extensions registered by this module

| Extension | Applied to | Adds |
|---|---|---|
| `ContactPaymentAccountsExtension` | `Contact` | `has_many PaymentAccounts` |
| `PaymentAccountContactExtension` | `PaymentAccount` | `has_one Contact` |
| `ContactableMember` | `Member` | Reverse link to `Contact` |
| `SiteConfigOwner` | `SiteConfig` | `has_one SiteOwner → Contact` |

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

Customer numbers are only auto-generated for contacts that belong to the `CUSTOMER` tag group and do not yet have a customer number.

### Contact tags

Define tags that are created automatically on `/dev/build`:

```yml
Clesson\Silverstripe\Contacts\Models\ContactTag:
  default_tags:
    client: 'Clients'
    customer: 'Customers'
```

### Address types

Address types are managed by the `clesson-de/silverstripe-geocoding` module. Configure default types in your project's `_config/config.yml`:

```yml
Clesson\Silverstripe\Geocoding\Models\AddressType:
  default_tags:
    invoice-address: 'Invoice address'
    delivery-address: 'Delivery address'
```

---

## Developer Documentation

### Query helpers

```php
use Clesson\Silverstripe\Contacts\Models\Contact;

// Get a single contact by its URL slug
$contact = Contact::get_by_slug('roy-orbison');

// Get all contacts with a specific tag (by unique key)
$clients = Contact::get_by_tag('client');

// Get the site owner
$owner = Contact::current_site_owner();
```

### Working with contact types

```php
use Clesson\Silverstripe\Contacts\Models\Company;
use Clesson\Silverstripe\Contacts\Models\Person;
use Clesson\Silverstripe\Contacts\Models\Employee;

// Create a company
$company = Company::create();
$company->Name1 = 'Acme Inc.';
$company->write();

// Create a person
$person = Person::create();
$person->FirstName = 'Jane';
$person->LastName  = 'Doe';
$person->write();

// Create an employee linking person and company
$employee = Employee::create();
$employee->PersonID  = $person->ID;
$employee->CompanyID = $company->ID;
$employee->write();

// Each contact type returns a formatted full name
echo $company->getFullName();  // "Acme Inc."
echo $person->getFullName();   // "Jane Doe"
echo $employee->getFullName(); // "Jane Doe (Acme Inc.)"
```

### vCard download

The module exposes a public route for vCard downloads:

```
/contact/vcard/{slug}
```

Requires the `DOWNLOAD_CONTACTS` permission. The `Contact` model provides a `getvCardLink()` method and a CMS action button.

### CMS structure

The **Contacts** CMS section is a `SingleRecordAdmin` backed by the `ContactConfig` singleton. It renders three top-level tabs:

| Tab | Content |
|---|---|
| **Contacts** | Full list of all `Contact` records (Company, Person, Employee) |
| **Addresses** | Full list of all `Address` records |
| **Administration** | Inner tabs for `ContactTag` and `AddressType` records |

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
| `USE_CONTACTS` | View, create, edit, and delete contacts |
| `DOWNLOAD_CONTACTS` | Download contacts as vCard files |

---

## Frontend Assets

The module ships with compiled admin CSS and JavaScript in `client/admin/dist/`.  
If you want to modify the styles, you need to recompile them.

The source files are in `client/admin/src/`.

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
