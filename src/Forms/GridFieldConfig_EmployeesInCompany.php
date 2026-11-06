<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Forms;

use Clesson\Silverstripe\Contacts\Models\Contact;
use Clesson\Silverstripe\Contacts\Models\Employee;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\ORM\FieldType\DBField;

/**
 * GridField configuration for displaying Employee records within a Company.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms
 */
class GridFieldConfig_EmployeesInCompany extends GridFieldConfig
{
    /**
     * @param int|null $itemsPerPage
     */
    public function __construct(?int $itemsPerPage = 20)
    {
        parent::__construct();

        $this->addComponent(GridFieldButtonRow::create('before'));
        $this->addComponent(GridFieldSortableHeader::create());

        $this->addComponent($dataColumns = GridFieldDataColumns::create());
        $dataColumns->setDisplayFields([
            'Icon' => [
                'title'    => '',
                'callback' => fn($record) => $record instanceof Employee
                    ? $this->getEmployeeIcon($record)
                    : $record->getIcon(),
            ],
            'Name' => [
                'title'    => _t(Contact::class . '.NAME', 'Display name'),
                'callback' => fn($record) => DBField::create_field('Varchar', $record->Name),
            ],
            'Created' => [
                'title'    => _t('Clesson\Silverstripe\Contacts\Common.CREATED', 'Created'),
                'callback' => fn($record) => DBField::create_field('DBDatetime', $record->Created),
            ],
        ]);

        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDetailForm::create());
        $this->addComponent(GridFieldPaginator::create($itemsPerPage));

        $this->extend('updateConfig');
    }

    /**
     * Returns the employee icon, falling back to the linked person's avatar.
     *
     * @param Employee $employee
     * @return DBField|string
     */
    private function getEmployeeIcon(Employee $employee): DBField|string
    {
        if ($employee->AvatarID > 0 && $employee->Avatar()->exists()) {
            return $employee->getIcon();
        }

        $person = $employee->PersonID > 0 ? $employee->Person() : null;
        if ($person && $person->AvatarID > 0 && $person->Avatar()->exists()) {
            return $person->getIcon();
        }

        return $employee->getIcon();
    }
}


