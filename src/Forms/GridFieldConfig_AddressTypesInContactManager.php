<?php

namespace Clesson\Silverstripe\Contacts\Forms;

use Clesson\Silverstripe\Geocoding\Models\AddressType;
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
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
 * GridField configuration for displaying AddressType records in the ContactManager.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms
 */
class GridFieldConfig_AddressTypesInContactManager extends GridFieldConfig
{

    /**
     * Initialises the GridField configuration with all required components
     * and configures the display columns for AddressType records.
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
        $this->addComponent(GridFieldSortableHeader::create()->setFieldSorting([
            GridFieldRecordDates::COLUMN => 'LastEdited',
        ]));
        $this->addComponent($dataColumns = GridFieldDataColumns::create());
        $this->addComponent(GridFieldRecordDates::create());
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());

        $dataColumns->setDisplayFields([
            'Name' => [
                'title' => _t(AddressType::class . '.NAME', 'Name'),
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('Varchar', $record->Name);
                },
            ],
            'Ukey' => [
                'title' => _t(AddressType::class . '.UKEY', 'Key'),
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('Varchar', $record->Ukey);
                },
            ],
        ]);

        $this->addComponent(GridFieldDetailForm::create(null, $showPagination, $showAdd));
        $this->extend('updateConfig');
    }

}
