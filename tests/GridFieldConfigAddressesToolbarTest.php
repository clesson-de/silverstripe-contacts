<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Tests;

use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressCity;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressRegion;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressCountry;
use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_AddressesInContactManager;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use Clesson\Silverstripe\Geocoding\Models\Address;
use DOMDocument;
use DOMXPath;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldLazyLoader;
use SilverStripe\i18n\i18n;

/**
 * Covers address filter placement and the shared creation toolbar.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Tests
 */
class GridFieldConfigAddressesToolbarTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** Renders the real address configuration with an editable form. */
    private function grid(): GridField
    {
        $this->logInWithPermission('ADMIN');
        $grid = GridField::create('Addresses', '', Address::get(), GridFieldConfig_AddressesInContactManager::create());
        $grid->setModelClass(Address::class);
        $form = Form::create(Controller::curr(), 'AddressToolbarTest', FieldList::create($grid), FieldList::create());
        $form->setFormAction('/test');
        return $grid;
    }

    /** All filters stay registered and render exactly once inside the toolbar. */
    public function testFiltersAndCreationRenderInsideToolbar(): void
    {
        $grid = $this->grid();
        $config = $grid->getConfig();
        self::assertContains(GridFieldRecordDates::COLUMN, $grid->getColumns());
        self::assertNotContains('Created', $grid->getColumns());
        self::assertNotContains('LastEdited', $grid->getColumns());
        self::assertSame('LastEdited', $config->getComponentByType(GridFieldSortableHeader::class)->getFieldSorting()[GridFieldRecordDates::COLUMN]);
        self::assertInstanceOf(GridFieldToolbar::class, $config->getComponentByType(GridFieldButtonRow::class));
        self::assertCount(1, $config->getComponentsByType(GridFieldButtonRow::class));
        self::assertNotNull($config->getComponentByType(GridFieldLazyLoader::class));
        self::assertSame([Address::class => Address::singleton()->i18n_singular_name()], $config->getComponentByType(GridFieldAddNewDropdown::class)->getClasses($grid));
        $html = (string)$grid->FieldHolder();
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        self::assertCount(3, $xpath->query('//*[contains(@class, "gridfield-pro-toolbar")]//select'));
        self::assertCount(1, $xpath->query(
            '//*[contains(@class, "gridfield-pro-toolbar__group--left")]'
            . '/*[.//select[@name="gridfield_address_country_filter"]]'
            . '/following-sibling::*[1]//input[contains(@class, "gridfield-search__input")]'
        ));
        self::assertCount(1, $xpath->query('//*[contains(@class, "gridfield-pro-toolbar__group--right")]//a[contains(@class, "gridfield-add-new-dropdown__direct")]'));
        self::assertStringNotContainsString('$DefineFragment', $html);
    }

    /** Search uses localized model labels and matches either name or full address within the existing scope. */
    public function testSearchFieldsAndFiltering(): void
    {
        $grid = $this->grid();
        $search = $grid->getConfig()->getComponentByType(GridFieldSearch::class);
        self::assertSame(['Name', 'Summary'], $search->getSearchFields());
        self::assertSame(
            'Suche in Bezeichnung und Ganze Adresse',
            i18n::with_locale('de_DE', fn () => $search->getPlaceholder($grid))
        );
        $named = Address::create(['Name' => 'Needle office', 'City' => 'Berlin', 'CountryCode' => 'DE']);
        $named->write();
        $located = Address::create(['Name' => 'Office', 'City' => 'Needle town', 'CountryCode' => 'DE']);
        $located->write();
        $excluded = Address::create(['Name' => 'Needle excluded', 'City' => 'Hamburg', 'CountryCode' => 'DE']);
        $excluded->write();
        $search->handleAction($grid, GridFieldSearch::ACTION, [], ['Addresses' => ['SearchQuery' => 'needle']]);
        $list = Address::get()->filter('ID', [$named->ID, $located->ID]);
        self::assertEqualsCanonicalizing(
            [$named->ID, $located->ID],
            $search->getManipulatedData($grid, $list)->column('ID')
        );
    }

    /** Placement can change without altering legacy defaults or action behaviour. */
    public function testFilterFragmentsAndStateArePreserved(): void
    {
        $grid = $this->grid();
        foreach (['City', 'Region', 'Country'] as $name) {
            $type = match ($name) {
                'City' => GridFieldFilter_AddressCity::class,
                'Region' => GridFieldFilter_AddressRegion::class,
                'Country' => GridFieldFilter_AddressCountry::class,
            };
            $filter = $grid->getConfig()->getComponentByType($type);
            self::assertArrayHasKey('toolbar-before-address-filters', $filter->getHTMLFragments($grid));
            self::assertArrayHasKey('before', $type::create()->getHTMLFragments($grid));
            $key = 'gridfield_address_' . strtolower($name) . '_filter';
            $filter->handleAction($grid, $filter->getActions($grid)[0], [], [$key => 'example']);
            $stateKey = 'GridFieldFilter_Address' . $name;
            self::assertSame('example', $grid->State->$stateKey);
        }
    }
}
