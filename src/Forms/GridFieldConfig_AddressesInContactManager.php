<?php

namespace Clesson\Silverstripe\Contacts\Forms;

use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressCity;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressCountry;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldFilter_AddressRegion;
use Clesson\Silverstripe\Geocoding\Models\Address;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
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
     * @param int|null $itemsPerPage
     * @param bool|null $showPagination
     * @param bool|null $showAdd
     */
    public function __construct($itemsPerPage = null, $showPagination = null, $showAdd = null)
    {
        parent::__construct();

        $this->addComponent(GridFieldButtonRow::create('before'));
        $this->addComponent(GridFieldAddNewButton::create('buttons-before-right'));
        $this->addComponent(GridFieldFilter_AddressCity::create());
        $this->addComponent(GridFieldFilter_AddressRegion::create());
        $this->addComponent(GridFieldFilter_AddressCountry::create());
        $this->addComponent(GridFieldSortableHeader::create());
        $this->addComponent($dataColumns = GridFieldDataColumns::create());
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());

        $dataColumns->setDisplayFields([
            'Name' => [
                'title' => _t(Address::class . '.NAME', 'Label'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->Name);
                },
            ],
            'AddressLine1' => [
                'title' => _t(Address::class . '.ADDRESS_LINE_1', 'Address line 1'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->AddressLine1);
                },
            ],
            'City' => [
                'title' => _t(Address::class . '.CITY', 'City'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->City);
                },
            ],
            'Region' => [
                'title' => _t(Address::class . '.REGION', 'Region'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->Region);
                },
            ],
            'CountryCode' => [
                'title' => _t(Address::class . '.COUNTRY_CODE', 'Country'),
                'callback' => static function ($record): DBField {
                    return DBField::create_field('Varchar', $record->getCountry() ?? $record->CountryCode);
                },
            ],
            'Created' => [
                'title' => _t('Clesson\Silverstripe\Contacts\Common.CREATED', 'Created'),
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('DBDatetime', $record->Created);
                },
            ],
        ]);

        $this->addComponent(GridFieldDetailForm::create(null, $showPagination, $showAdd));
        $this->extend('updateConfig');
    }

}

