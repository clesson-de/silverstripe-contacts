<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Models;

use JeroenDesloovere\VCard\VCard;
use Clesson\Silverstripe\Geocoding\Models\Address;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;

/**
 * Represents a company or organisation.
 *
 * @property string $Name1
 * @property string $Name2
 * @property int $AddressID
 * @method Address Address()
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Models
 */
class Company extends Contact
{

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Contacts_Company';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Name1' => 'Varchar(150)',
        'Name2' => 'Varchar(150)',
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Name1']   = _t(__CLASS__ . '.NAME1', 'Company name');
        $labels['Name2']   = _t(__CLASS__ . '.NAME2', 'Company name suffix');
        $labels['Address'] = _t(__CLASS__ . '.ADDRESS', 'Business address');
        return $labels;
    }

    /**
     * Validates the record before writing.
     * Name1 is required.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!$this->Name1) {
            $result->addError(_t(Form::class . '.FIELDISREQUIRED', '{name} is required', ['name' => $this->fieldLabel('Name1')]));
        }
        return $result;
    }

    /**
     * Prevents deletion when this company is linked to one or more employees.
     *
     * @param \SilverStripe\Security\Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        if (Employee::get()->filter('CompanyID', (int) $this->ID)->exists()) {
            return false;
        }
        return parent::canDelete($member);
    }

    /**
     * Returns the company's primary name (Name1).
     *
     * @return string
     */
    public function getFullName(): string
    {
        return (string) $this->Name1;
    }

    /**
     * Returns a vCard for this company.
     *
     * @return VCard
     */
    public function getVCard(): VCard
    {
        $card = new VCard();
        $card->addCompany($this->Name1);
        return $card;
    }

    /**
     * @inheritdoc
     */
    protected function createDefaultName(): string
    {
        return (string) $this->Name1;
    }

    /**
     * @inheritdoc
     */
    protected function updateSortingName(): void
    {
        $this->SortingName = implode(' ', array_filter(
            [(string) $this->Name1, (string) $this->Name2],
            fn(string $s) => strlen(trim($s)) > 0
        ));
    }

    /**
     * @inheritdoc
     */
    protected function updateInitials(): void
    {
        $parts = array_filter(
            [(string) $this->Name1],
            fn(string $s) => strlen(trim($s)) > 0
        );

        $this->Initials = substr(implode('', array_map(
            fn(string $s) => substr(strtoupper(trim($s)), 0, 1),
            $parts
        )), 0, 2);
    }

    /**
     * Returns the CMS fields for the company detail tab.
     *
     * @return FieldList
     */
    protected function getMainCMSFields(): FieldList
    {
        $fields = FieldList::create();

        /** @var TextField $name1Field */
        $name1Field = TextField::create('Name1', $this->fieldLabel('Name1'));
        $name1Field->addExtraClass('raised');
        $fields->add($name1Field);

        /** @var TextField $name2Field */
        $name2Field = TextField::create('Name2', $this->fieldLabel('Name2'));
        $fields->add($name2Field);

        if ($this->exists()) {
            /** @var DropdownField $addressField */
            $addressField = DropdownField::create('AddressID', $this->fieldLabel('Address'), Address::get()->map('ID', 'Title'));
            $addressField->setEmptyString('');
            $fields->add($addressField);
        }

        return $fields;
    }

}

