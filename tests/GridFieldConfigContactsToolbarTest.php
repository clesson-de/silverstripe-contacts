<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Tests;

use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldAction_BirthdayExport;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldAction_BirthdayPdfExport;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldAction_PhoneListExport;
use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_ContactsInContactManager;
use Clesson\Silverstripe\Contacts\Models\Company;
use Clesson\Silverstripe\Contacts\Models\Contact;
use Clesson\Silverstripe\Contacts\Models\Person;
use Clesson\Silverstripe\Geocoding\Models\Address;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Forms\GridField\GridFieldActionMenu;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use Clesson\Silverstripe\Forms\GridField\GridField_ButtonFilter;
use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldLazyLoader;
use SilverStripe\i18n\i18n;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use DOMDocument;
use DOMXPath;

/**
 * Covers the contact manager's opt-in toolbar layout and legacy controls.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Tests
 */
class GridFieldConfigContactsToolbarTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** Filter placement changes must not change initial filtering or export registration. */
    public function testToolbarPlacementAndExistingComponents(): void
    {
        $config = GridFieldConfig_ContactsInContactManager::create();
        self::assertInstanceOf(GridFieldToolbar::class, $config->getComponentByType(GridFieldButtonRow::class));
        self::assertCount(1, $config->getComponentsByType(GridFieldButtonRow::class));
        self::assertNotNull($config->getComponentByType(GridFieldLazyLoader::class));
        $filters = $config->getComponentsByType(GridField_ButtonFilter::class)->toArray();
        self::assertSame('toolbar-before-contact-types', $filters[0]->getTargetFragment());
        self::assertSame([Company::class, Person::class], $filters[0]->getSelectedValues());
        self::assertSame('toolbar-before-contact-tags', $filters[1]->getTargetFragment());
        self::assertSame('toolbar-before-alphabet', $config->getComponentByType(GridField_CharFilter::class)->getTargetFragment());
        self::assertSame('buttons-before-right', $config->getComponentByType(GridFieldAddNewDropdown::class)->getFragment());
        self::assertSame('Downloads', $config->getComponentByType(GridFieldActionMenu::class)->getTitle());
        foreach ([GridFieldAction_PhoneListExport::class, GridFieldAction_BirthdayExport::class, GridFieldAction_BirthdayPdfExport::class] as $type) {
            self::assertNotNull($config->getComponentByType($type));
        }
    }

    /** The complete config resolves every slot and renders existing export links once. */
    public function testToolbarRendersWithExistingProviders(): void
    {
        $this->logInWithPermission('ADMIN');
        $grid = GridField::create('Contacts', 'Contacts', Contact::get(), GridFieldConfig_ContactsInContactManager::create());
        $grid->setModelClass(Contact::class);
        $columns = $grid->getColumns();
        $nameIndex = array_search('Name', $columns, true);
        $emailIndex = array_search('Email', $columns, true);
        $phoneIndex = array_search('Phone', $columns, true);
        $createdIndex = array_search(GridFieldRecordDates::COLUMN, $columns, true);
        self::assertNotFalse($nameIndex);
        self::assertNotFalse($emailIndex);
        self::assertNotFalse($phoneIndex);
        self::assertNotFalse($createdIndex);
        self::assertLessThan($emailIndex, $nameIndex);
        self::assertLessThan($phoneIndex, $emailIndex);
        self::assertLessThan($createdIndex, $phoneIndex);
        self::assertNotContains('Mobile', $columns);
        self::assertNotContains('Created', $grid->getColumns());
        self::assertNotContains('LastEdited', $grid->getColumns());
        self::assertSame('LastEdited', $grid->getConfig()->getComponentByType(GridFieldSortableHeader::class)->getFieldSorting()[GridFieldRecordDates::COLUMN]);
        $form = Form::create(Controller::curr(), 'ContactToolbarTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        $html = (string)$grid->FieldHolder();
        self::assertStringContainsString('gridfield-pro-toolbar__item--full', $html);
        self::assertStringContainsString('gridfield-charfilter', $html);
        self::assertStringContainsString('gridfield-search__input', $html);
        self::assertStringContainsString('Search in Display name, Email and Address', $html);
        self::assertSame(
            ['Name', 'Account.Email', 'Address.Summary'],
            $grid->getConfig()->getComponentByType(GridFieldSearch::class)->getSearchFields()
        );
        self::assertStringContainsString('gridfield-add-new-dropdown', $html);
        self::assertStringNotContainsString('gridfield-pro-toolbar__label', $html);
        self::assertStringNotContainsString('gridfield-charfilter__label', $html);
        self::assertStringContainsString('font-icon-plus', $html);
        self::assertStringContainsString('font-icon-down-circled', $html);
        self::assertStringContainsString('gridfield-charfilter__select', $html);
        self::assertStringContainsString('aria-pressed="true"', $html);
        self::assertStringNotContainsString('$DefineFragment', $html);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        self::assertCount(1, $xpath->query(
            '//*[contains(@class, "gridfield-pro-toolbar__group--left")]'
            . '/*[contains(@class, "gridfield-pro-toolbar__item")][1][div[@class="type-filter"]]'
            . '/following-sibling::*[1]//input[contains(@class, "gridfield-search__input")]'
        ));
        $icons = $xpath->query('//*[contains(@class, "gridfield-add-new-dropdown__menu")]//a/span[@aria-hidden="true"]');
        self::assertSame(
            ['font-icon-home', 'font-icon-torso', 'font-icon-sitemap'],
            array_map(static fn ($icon) => $icon->getAttribute('class'), iterator_to_array($icons))
        );
        $links = $xpath->query('//*[contains(@class, "gridfield-action-menu")]//*[@role="menu"]//a');
        self::assertCount(3, $links);
        foreach (['export-phone-list', 'export-birthdays', 'export-birthdays-pdf'] as $index => $route) {
            $url = $links->item($index)->getAttribute('href');
            self::assertStringContainsString('/' . $route . '?', $url);
            self::assertStringContainsString('gridState', $url);
            self::assertSame(1, substr_count($html, '/' . $route . '?'));
        }
    }

    /** The phone column prefers mobile, falls back to landline and emits safe telephone links. */
    public function testPhoneColumnPrefersMobileAndUsesTelephoneLinks(): void
    {
        $grid = GridField::create('Contacts', 'Contacts', ArrayList::create(), GridFieldConfig_ContactsInContactManager::create());
        $grid->setModelClass(Contact::class);
        $dataColumns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);

        $mobile = Contact::create(['Phone' => '+49 40 5678', 'Mobile' => '+49 30 1234']);
        $mobileContent = i18n::with_locale(
            'de_DE',
            fn () => (string) $dataColumns->getColumnContent($grid, $mobile, 'Phone')
        );
        self::assertStringContainsString('href="tel:+49301234"', $mobileContent);
        self::assertStringContainsString('>+49 30 1234</a><br><small>Mobil</small>', $mobileContent);
        self::assertStringNotContainsString('+49 40 5678', $mobileContent);

        $landline = Contact::create(['Phone' => '+49 40 5678']);
        $landlineContent = (string) $dataColumns->getColumnContent($grid, $landline, 'Phone');
        self::assertStringContainsString('href="tel:+49405678"', $landlineContent);
        self::assertStringContainsString('>+49 40 5678</a>', $landlineContent);
        self::assertStringNotContainsString('<small>', $landlineContent);

        $empty = Contact::create();
        self::assertSame('', (string) $dataColumns->getColumnContent($grid, $empty, 'Phone'));
    }

    /** The email column displays only the linked account's address as a mail link. */
    public function testEmailColumnUsesLinkedMemberAddress(): void
    {
        $grid = GridField::create('Contacts', 'Contacts', ArrayList::create(), GridFieldConfig_ContactsInContactManager::create());
        $grid->setModelClass(Contact::class);
        $dataColumns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);
        $member = Member::create(['Email' => 'contact@example.test']);
        $member->write();
        $contact = Person::create(['LastName' => 'Tester', 'AccountID' => $member->ID]);
        $contact->write();

        self::assertSame(
            '<a href="mailto:contact@example.test">contact@example.test</a>',
            (string) $dataColumns->getColumnContent($grid, $contact, 'Email')
        );

        $unlinked = Person::create(['LastName' => 'Unlinked']);
        self::assertSame('', (string) $dataColumns->getColumnContent($grid, $unlinked, 'Email'));
    }

    /** Contact search matches local names, linked login emails and the full linked address. */
    public function testContactSearchUsesConfiguredRelations(): void
    {
        $account = Member::create(['Email' => 'search-login@example.test']);
        $account->write();
        $address = Address::create(['City' => 'Searchtown', 'CountryCode' => 'DE']);
        $address->write();
        $contact = Person::create([
            'LastName' => 'Searchname', 'AccountID' => $account->ID, 'AddressID' => $address->ID,
        ]);
        $contact->write();
        $other = Person::create(['LastName' => 'Unrelated']);
        $other->write();
        $config = GridFieldConfig_ContactsInContactManager::create();
        $grid = GridField::create('Contacts', '', Contact::get()->filter('ID', [$contact->ID, $other->ID]), $config);
        $search = $config->getComponentByType(GridFieldSearch::class);
        foreach (['Searchname', 'search-login', 'Searchtown'] as $term) {
            $search->handleAction($grid, GridFieldSearch::ACTION, [], ['Contacts' => ['SearchQuery' => $term]]);
            self::assertSame(
                [(int) $contact->ID],
                array_map('intval', $search->getManipulatedData($grid, $grid->getList())->column('ID'))
            );
        }
    }

    /** Menu-only providers suppress duplicate buttons but retain their original routes. */
    public function testMenuOnlyProvidersKeepRoutes(): void
    {
        $this->logInWithPermission('ACCESS_CONTACTS');
        $config = GridFieldConfig_ContactsInContactManager::create();
        $grid = GridField::create('Contacts', 'Contacts', ArrayList::create(), $config);
        $form = Form::create(Controller::curr(), 'DownloadTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        foreach ([GridFieldAction_PhoneListExport::class, GridFieldAction_BirthdayExport::class, GridFieldAction_BirthdayPdfExport::class] as $type) {
            $component = $config->getComponentByType($type);
            self::assertSame([], $component->getHTMLFragments($grid));
            self::assertCount(1, $component->getURLHandlers($grid));
            self::assertCount(1, $component->getMenuItems($grid));
            self::assertNotEmpty($type::create()->getHTMLFragments($grid));
        }
    }

    /** Hidden downloads remain inaccessible through direct endpoint requests. */
    public function testDownloadsRequireContactAccess(): void
    {
        Security::setCurrentUser(null);
        $config = GridFieldConfig_ContactsInContactManager::create();
        $grid = GridField::create('Contacts', 'Contacts', ArrayList::create(), $config);
        foreach ([GridFieldAction_PhoneListExport::class, GridFieldAction_BirthdayExport::class, GridFieldAction_BirthdayPdfExport::class] as $type) {
            $component = $config->getComponentByType($type);
            self::assertSame([], $component->getMenuItems($grid));
            try {
                $component->handleExport($grid, new HTTPRequest('GET', '/download'));
                self::fail('A direct download without contact access must be denied.');
            } catch (HTTPResponse_Exception $exception) {
                self::assertSame(403, $exception->getResponse()->getStatusCode());
            }
        }
        self::assertSame([], $config->getComponentByType(GridFieldActionMenu::class)->getHTMLFragments($grid));
    }
}
