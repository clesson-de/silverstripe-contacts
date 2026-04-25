<?php

namespace Clesson\Contacts\Forms;

use Clesson\Contacts\Models\Contact;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;

/**
 * GridField configuration for displaying Contact records in a ContactTag.
 *
 * @package Clesson\Contacts
 * @subpackage Forms
 */
class GridFieldConfig_ContactsInContactTag extends GridFieldConfig
{


    /**
     * @param $itemsPerPage
     * @param $showPagination
     * @param $showAdd
     */
    public function __construct($itemsPerPage = null, $showPagination = null, $showAdd = null)
    {
        parent::__construct();

        $this->addComponent(GridFieldButtonRow::create('before'));
        $this->addComponent(GridFieldToolbarHeader::create());

        $this->addComponent($dataColumns = GridFieldDataColumns::create());

        $dataColumns->setDisplayFields([
            'Icon' => [
                'title' => _t(Contact::class . '.Icon', 'Icon'),
                'callback' => function($record, $column, $grid) {
                    return $record->Icon;
                }
            ],
            'Name' => [
                'title' => _t(Contact::class . '.NAME', 'Name'),
                'callback' => function($record, $column, $grid) {
                    return $record->Name;
                }
            ],
        ]);

        $this->addComponent(GridFieldDetailForm::create(null, $showPagination, $showAdd));

        $this->extend('updateConfig');
    }

}
