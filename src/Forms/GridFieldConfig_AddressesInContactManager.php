<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Forms;

use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressCity;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressCountry;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressRegion;
use Clesson\Silverstripe\Constants\GridFieldToolbarLayout;
use Clesson\Silverstripe\Geocoding\Helpers\MapThumbnailHelper;
use Clesson\Silverstripe\Geocoding\Models\Address;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
use SilverStripe\Forms\GridField\GridFieldLazyLoader;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\ORM\FieldType\DBField;

/**
 * GridField configuration for displaying Address records in the ContactManager.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms
 */
class GridFieldConfig_AddressesInContactManager extends GridFieldConfig
{

    /**
     * Initialises the GridField configuration with all required components
     * and configures the display columns for Address records.
     *
     * @param int|null  $itemsPerPage
     * @param bool|null $showPagination
     * @param bool|null $showAdd
     */
    public function __construct($itemsPerPage = null, $showPagination = null, $showAdd = null)
    {
        parent::__construct();

        $this->addToolbar();
        $this->addComponent(GridFieldSortableHeader::create()->setFieldSorting([
            GridFieldRecordDates::COLUMN => 'LastEdited',
        ]));
        $this->addComponent($dataColumns = GridFieldDataColumns::create());
        $this->addComponent(GridFieldRecordDates::create());
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());

        $dataColumns->setDisplayFields($this->buildDisplayFields());

        $this->addComponent(GridFieldDetailForm::create(null, $showPagination, $showAdd));
        $this->extend('updateConfig');
    }

    /** Keeps the existing address filters together beside a consistent creation action. */
    private function addToolbar(): void
    {
        $toolbar = GridFieldToolbar::install($this);
        $filters = $toolbar->addItem('address-filters');
        $this->addComponent(GridFieldLazyLoader::create());
        $this->addComponent(GridFieldAddNewDropdown::create('buttons-before-right')->setClasses([Address::class]));
        $this->addComponent(GridFieldFilter_AddressCity::create($filters->getFragment()));
        $this->addComponent(GridFieldFilter_AddressRegion::create($filters->getFragment()));
        $this->addComponent(GridFieldFilter_AddressCountry::create($filters->getFragment()));
        $search = $toolbar->addItem('search', GridFieldToolbarLayout::LEFT, GridFieldToolbarLayout::GROW);
        $this->addComponent(GridFieldSearch::create(['Name', 'Summary'], $search->getFragment()));
    }

    /**
     * Builds the display field definitions for the Address GridField.
     *
     * The map thumbnail column is prepended as the first column so that
     * the geocoded position is immediately visible at a glance.
     *
     * @return array<string, mixed>
     */
    private function buildDisplayFields(): array
    {
        $thumbnailWidth  = 120;
        $thumbnailHeight = 80;
        $thumbnailZoom   = 14;
        $thumbnailLayer  = 'osm';

        $buildThumbnail = static function ($record) use (
            $thumbnailWidth,
            $thumbnailHeight,
            $thumbnailZoom,
            $thumbnailLayer
        ): string {
            /** @var DBGeoCoordinate $coord */
            $coord    = $record->dbObject('GeoCoordinates');
            $thumbHtml = '';

            if ($coord && $coord->exists()) {
                $url      = MapThumbnailHelper::urlFromCoordinate($coord, $thumbnailZoom, $thumbnailWidth, $thumbnailHeight, $thumbnailLayer);
                $safeUrl  = htmlspecialchars($url, ENT_QUOTES);
                $thumbHtml = '<img src="' . $safeUrl . '" width="' . $thumbnailWidth . '" height="' . $thumbnailHeight
                    . '" alt="" style="display:block;border-radius:3px;" loading="lazy" />';
            }

            $summary = htmlspecialchars((string) $record->getTitle(), ENT_QUOTES, 'UTF-8');

            return '<style>'
                . '.geocoding-addr-thumb{display:block}'
                . '.geocoding-addr-summary{display:none}'
                . '@media(max-width:767px){'
                . '.geocoding-addr-thumb{display:none}'
                . '.geocoding-addr-summary{display:block}'
                . '}'
                . '</style>'
                . '<span class="geocoding-addr-thumb">' . $thumbHtml . '</span>'
                . '<span class="geocoding-addr-summary">' . $summary . '</span>';
        };

        return [
            'GeoCoordinates' => [
                'title'    => '',
                'callback' => static function ($record) use ($buildThumbnail): DBField {
                    return DBField::create_field('HTMLText', $buildThumbnail($record));
                },
            ],
            'Name'           => [
                'title'    => _t(Address::class . '.NAME', 'Label'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->Name);
                },
            ],
            'AddressLine1'   => [
                'title'    => _t(Address::class . '.ADDRESS_LINE_1', 'Address line 1'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->AddressLine1);
                },
            ],
            'City'           => [
                'title'    => _t(Address::class . '.CITY', 'City'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->City);
                },
            ],
            'Region'         => [
                'title'    => _t(Address::class . '.REGION', 'Region'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->Region);
                },
            ],
            'CountryCode'    => [
                'title'    => _t(Address::class . '.COUNTRY_CODE', 'Country'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->getCountry() ?? $record->CountryCode);
                },
            ],
        ];
    }

}
