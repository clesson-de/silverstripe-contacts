<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Models;

use JeroenDesloovere\VCard\VCard;
use Clesson\Silverstripe\Geocoding\Models\Address;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;

/**
 * Represents the employment relationship between a Person and a Company.
 * Both relations are optional to allow incremental data entry.
 *
 * @property int $PersonID
 * @property int $CompanyID
 * @property int $AddressID
 * @method Person Person()
 * @method Company Company()
 * @method Address Address()
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Models
 */
class Employee extends Contact
{

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Contacts_Employee';

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'Person'  => Person::class,
        'Company' => Company::class,
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Person']    = _t(__CLASS__ . '.PERSON', 'Person');
        $labels['PersonID']  = _t(__CLASS__ . '.PERSON', 'Person');
        $labels['Company']   = _t(__CLASS__ . '.COMPANY', 'Company');
        $labels['CompanyID'] = _t(__CLASS__ . '.COMPANY', 'Company');
        $labels['Address']   = _t(__CLASS__ . '.ADDRESS', 'Work address');
        return $labels;
    }

    /**
     * Returns the full name of the linked person, or an empty string if none is set.
     *
     * @return string
     */
    public function getFullName(): string
    {
        return $this->PersonID ? (string) $this->Person()?->getFullName() : '';
    }

    /**
     * Returns a vCard for the linked person.
     * Falls back to an empty vCard if no person is linked.
     *
     * @return VCard
     */
    public function getVCard(): VCard
    {
        $person = $this->PersonID ? $this->Person() : null;
        return $person ? $person->getVCard() : new VCard();
    }

    /**
     * @inheritdoc
     */
    protected function createDefaultName(): string
    {
        $parts = array_filter([
            $this->PersonID  ? $this->Person()?->getFullName()  : null,
            $this->CompanyID ? $this->Company()?->getFullName() : null,
        ]);

        return implode(' @ ', $parts);
    }

    /**
     * @inheritdoc
     */
    protected function updateSortingName(): void
    {
        $this->SortingName = $this->createDefaultName();
    }

    /**
     * @inheritdoc
     */
    protected function updateInitials(): void
    {
        $person = $this->PersonID ? $this->Person() : null;
        $this->Initials = $person ? (string) $person->Initials : '';
    }

    /**
     * Returns the CMS fields for the employee detail tab.
     *
     * @return FieldList
     */
    protected function getMainCMSFields(): FieldList
    {
        $fields = FieldList::create();

        /** @var DropdownField $personField */
        $personField = DropdownField::create('PersonID', $this->fieldLabel('Person'), Person::get()->map('ID', 'Name'));
        $personField->setEmptyString('');
        $fields->add($personField);

        /** @var DropdownField $companyField */
        $companyField = DropdownField::create('CompanyID', $this->fieldLabel('Company'), Company::get()->map('ID', 'Name'));
        $companyField->setEmptyString('');
        $fields->add($companyField);

        if ($this->exists()) {
            /** @var DropdownField $addressField */
            $addressField = DropdownField::create('AddressID', $this->fieldLabel('Address'), Address::get()->map('ID', 'Title'));
            $addressField->setEmptyString('');
            $fields->add($addressField);
        }

        return $fields;
    }

}

