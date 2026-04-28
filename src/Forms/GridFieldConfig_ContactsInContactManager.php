<?php

namespace Clesson\Silverstripe\Contacts\Forms;

use Clesson\Silverstripe\Contacts\Models\Company;
use Clesson\Silverstripe\Contacts\Models\Contact;
use Clesson\Silverstripe\Contacts\Models\ContactTag;
use Clesson\Silverstripe\Contacts\Models\Employee;
use Clesson\Silverstripe\Contacts\Models\Person;
use Clesson\Silverstripe\Forms\GridField\GridField_ButtonFilter;
use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\ORM\FieldType\DBField;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

/**
 * GridField configuration for displaying Contact records in the ContactManager.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms
 */
class GridFieldConfig_ContactsInContactManager extends GridFieldConfig
{

    /**
     * Initialises the GridField configuration with all required components
     * and configures the display columns for Contact records.
     *
     * @param int|null $itemsPerPage
     * @param bool|null $showPagination
     * @param bool|null $showAdd
     */
    public function __construct($itemsPerPage = null, $showPagination = null, $showAdd = null)
    {
        parent::__construct();

        $this->addComponent(GridFieldButtonRow::create('before'));
        $this->addComponent(GridFieldToolbarHeader::create());
        $this->addComponent(GridFieldSortableHeader::create());
        $this->addComponent(GridFieldFilterHeader::create());
        $this->addComponent($dataColumns = GridFieldDataColumns::create());
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());
        $this->addComponent(GridField_ActionMenu::create());
        $this->addComponent(GridFieldPageCount::create('toolbar-header-right'));
        $this->addComponent(GridFieldPaginator::create($itemsPerPage));
        $this->addComponent(GridFieldDetailForm::create(null, $showPagination, $showAdd));
        $this->addComponent(new GridField_CharFilter('before', 'SortingName', 'A-Z|0-9'));

        /** @var GridField_ButtonFilter $classFilter */
        $classFilter = new GridField_ButtonFilter('before', 'ClassName', [
            Company::class => _t(Company::class . '.PLURALNAME', 'Companies'),
            Person::class => _t(Person::class . '.PLURALNAME', 'Persons'),
            Employee::class => _t(Employee::class . '.PLURALNAME', 'Employees'),
        ]);
        $classFilter->setMultiselect(true);
        $classFilter->setSelectedValues([Company::class, Person::class]);
        $this->addComponent($classFilter);

        /** @var GridField_ButtonFilter $tagFilter */
        $tagFilter = new GridField_ButtonFilter('before', 'Tags.ID', $this->buildTagFilterValues());
        $tagFilter->setMultiselect(true);
        $this->addComponent($tagFilter);

        /** @var GridFieldAddNewMultiClass $addButton */
        $addButton = GridFieldAddNewMultiClass::create('buttons-before-right');
        $addButton->setClasses([
            Company::class,
            Person::class,
            Employee::class,
        ]);
        $this->addComponent($addButton);

        $dataColumns->setDisplayFields([
            'Icon' => [
                'title' => '',
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('HTMLFragment', $record->Icon);
                },
            ],
            'Name' => [
                'title' => _t(Contact::class . '.NAME', 'Name'),
                'callback' => function ($record, $column, $grid) {
                    $html = ['<strong>' . $record->Name . '</strong>'];
                    if ($address = $record->Address) {
                        $html[] = '<small>' . $address->Title . '</small>';
                    }
                    return DBField::create_field('HTMLFragment', implode('<br>', $html));
                },
            ],
            'CustomerNumber' => [
                'title' => _t(Contact::class . '.CUSTOMER_NUMBER', 'Customer number'),
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('Varchar', $record->CustomerNumber);
                },
            ],
            'Created' => [
                'title' => _t('Clesson\Silverstripe\Contacts\Common.CREATED', 'Created'),
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('DBDatetime', $record->Created);
                },
            ],
            'LastEdited' => [
                'title' => _t('Clesson\Silverstripe\Contacts\Common.LAST_EDITED', 'Last edited'),
                'callback' => function ($record, $column, $grid) {
                    return DBField::create_field('DBDatetime', $record->LastEdited);
                },
            ],
        ]);

        $this->extend('updateConfig');
    }

    /**
     * Builds the associative array of tag filter values from the database.
     * Keys are tag IDs (as strings), values are the translated tag names.
     *
     * @return array<string, string>
     */
    private function buildTagFilterValues(): array
    {
        $values = [];
        foreach (ContactTag::get() as $tag) {
            $values[(string) $tag->ID] = (string) $tag->Name;
        }

        return $values;
    }
}

