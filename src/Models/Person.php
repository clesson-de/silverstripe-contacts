<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Models;

use Clesson\Silverstripe\Contacts\Constants\Gender;
use Clesson\Silverstripe\Contacts\Helpers\DateHelper;
use Clesson\Silverstripe\Geocoding\Models\Address;
use JeroenDesloovere\VCard\VCard;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;

/**
 * Represents an individual person.
 *
 * @property int    $Gender
 * @property string $PersonTitle
 * @property string $FirstName
 * @property string $LastName
 * @property string $Nickname
 * @property string $SecondName
 * @property string $Suffix
 * @property string $Birthday
 * @property string $DayOfDeath
 * @property string $Anniversary
 * @property string $MaritalStatus
 * @property int $AddressID
 * @method Address Address()
 * @property-read string $Age
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Models
 */
class Person extends Contact
{

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Contacts_Person';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Gender'        => 'Int',
        'PersonTitle'   => 'Varchar(50)',
        'FirstName'     => 'Varchar(100)',
        'LastName'      => 'Varchar(100)',
        'Nickname'      => 'Varchar(100)',
        'SecondName'    => 'Varchar(100)',
        'Suffix'        => 'Varchar(50)',
        'Birthday'      => 'Date',
        'DayOfDeath'    => 'Date',
        'Anniversary'   => 'Varchar(50)',
        'MaritalStatus' => 'Varchar(50)',
    ];

    /**
     * @inheritdoc
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();
        $this->Gender = Gender::NOT_KNOWN;
    }

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Gender']        = _t(__CLASS__ . '.GENDER', 'Gender');
        $labels['PersonTitle']   = _t(__CLASS__ . '.PERSON_TITLE', 'Title');
        $labels['FirstName']     = _t(__CLASS__ . '.FIRST_NAME', 'First name');
        $labels['LastName']      = _t(__CLASS__ . '.LAST_NAME', 'Last name');
        $labels['Nickname']      = _t(__CLASS__ . '.NICKNAME', 'Nickname');
        $labels['SecondName']    = _t(__CLASS__ . '.SECOND_NAME', 'Middle name');
        $labels['Suffix']        = _t(__CLASS__ . '.SUFFIX', 'Suffix');
        $labels['Birthday']      = _t(__CLASS__ . '.BIRTHDAY', 'Date of birth');
        $labels['DayOfDeath']    = _t(__CLASS__ . '.DAY_OF_DEATH', 'Date of death');
        $labels['Anniversary']   = _t(__CLASS__ . '.ANNIVERSARY', 'Anniversary');
        $labels['MaritalStatus'] = _t(__CLASS__ . '.MARITAL_STATUS', 'Marital status');
        $labels['Age']           = _t(__CLASS__ . '.AGE', 'Age');
        $labels['FullName']      = _t(__CLASS__ . '.FULL_NAME', 'Full name');
        $labels['Address']       = _t(__CLASS__ . '.ADDRESS', 'Home address');
        return $labels;
    }

    /**
     * Validates the record before writing.
     * LastName is required.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!$this->LastName) {
            $result->addError(_t(Form::class . '.FIELDISREQUIRED', '{name} is required', ['name' => $this->fieldLabel('LastName')]));
        }
        return $result;
    }

    /**
     * Prevents deletion when this person is linked to one or more employees.
     *
     * @param \SilverStripe\Security\Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        if (Employee::get()->filter('PersonID', (int) $this->ID)->exists()) {
            return false;
        }
        return parent::canDelete($member);
    }

    /**
     * Returns the full formatted name (salutation, title, first, middle, last, suffix).
     *
     * @return string
     */
    public function getFullName(): string
    {
        return implode(' ', array_filter([
            Gender::salutation($this->Gender),
            $this->PersonTitle,
            $this->FirstName,
            $this->SecondName,
            $this->LastName,
            $this->Suffix,
        ], fn(mixed $s) => $s && strlen(trim((string) $s)) > 0));
    }

    /**
     * Returns the age or duration since birthday.
     *
     * @return string
     */
    public function getAge(): string
    {
        return DateHelper::duration($this->Birthday, $this->DayOfDeath);
    }

    /**
     * Returns a vCard for this person.
     *
     * @return VCard
     */
    public function getVCard(): VCard
    {
        $card = new VCard();
        $card->addName($this->LastName, $this->FirstName, $this->SecondName, $this->PersonTitle, $this->Suffix);
        return $card;
    }

    /**
     * Returns the display title with birth/death year suffix for persons with a known birthday.
     *
     * @return string
     */
    public function getTitle(): string
    {
        $name = (string) $this->Name;

        if (!$this->Birthday) {
            return $name;
        }

        $suffix = ' *' . date('Y', strtotime($this->Birthday));
        if ($this->DayOfDeath) {
            $suffix .= '; †' . date('Y', strtotime($this->DayOfDeath));
        }

        return $name . $suffix;
    }

    /**
     * @inheritdoc
     */
    protected function createDefaultName(): string
    {
        return implode(' ', array_filter([
            Gender::salutation($this->Gender),
            $this->PersonTitle,
            $this->FirstName,
            $this->SecondName,
            $this->LastName,
            $this->Suffix,
        ], fn(mixed $s) => $s && strlen(trim((string) $s)) > 0));
    }

    /**
     * @inheritdoc
     */
    protected function updateSortingName(): void
    {
        $this->SortingName = implode(' ', array_filter(
            [(string) $this->LastName, (string) $this->FirstName, (string) $this->SecondName],
            fn(string $s) => strlen(trim($s)) > 0
        ));
    }

    /**
     * @inheritdoc
     */
    protected function updateInitials(): void
    {
        $parts = array_filter(
            [(string) $this->FirstName, (string) $this->SecondName, (string) $this->LastName],
            fn(string $s) => strlen(trim($s)) > 0
        );

        $this->Initials = substr(implode('', array_map(
            fn(string $s) => substr(strtoupper(trim($s)), 0, 1),
            $parts
        )), 0, 2);
    }

    /**
     * Returns the CMS fields for the person detail tab.
     *
     * @return FieldList
     */
    protected function getMainCMSFields(): FieldList
    {
        $fields = FieldList::create();

        /** @var DropdownField $genderField */
        $genderField = DropdownField::create('Gender', $this->fieldLabel('Gender'), Gender::options());
        $genderField->addExtraClass('gender');

        /** @var TextField $titleField */
        $titleField = TextField::create('PersonTitle', $this->fieldLabel('PersonTitle'));
        $titleField->addExtraClass('title');

        /** @var TextField $suffixField */
        $suffixField = TextField::create('Suffix', $this->fieldLabel('Suffix'));
        $suffixField->addExtraClass('suffix');

        /** @var FieldGroup $prefixField */
        $prefixField = FieldGroup::create($genderField, $titleField, $suffixField);
        $prefixField->addExtraClass('prefix');
        $fields->add($prefixField);

        /** @var TextField $firstNameField */
        $firstNameField = TextField::create('FirstName', $this->fieldLabel('FirstName'));
        $firstNameField->addExtraClass('firstname raised');

        /** @var TextField $secondNameField */
        $secondNameField = TextField::create('SecondName', $this->fieldLabel('SecondName'));
        $secondNameField->addExtraClass('secondname');

        /** @var TextField $lastNameField */
        $lastNameField = TextField::create('LastName', $this->fieldLabel('LastName'));
        $lastNameField->addExtraClass('lastname raised');

        /** @var FieldGroup $nameField */
        $nameField = FieldGroup::create($firstNameField, $secondNameField, $lastNameField);
        $nameField->addExtraClass('name');
        $fields->add($nameField);

        /** @var DateField $birthdayField */
        $birthdayField = DateField::create('Birthday', $this->fieldLabel('Birthday'));

        /** @var DateField $dayOfDeathField */
        $dayOfDeathField = DateField::create('DayOfDeath', $this->fieldLabel('DayOfDeath'));

        /** @var FieldGroup $datesField */
        $datesField = FieldGroup::create($birthdayField, $dayOfDeathField);
        $fields->add($datesField);

        if ($this->exists()) {
            /** @var DropdownField $addressField */
            $addressField = DropdownField::create('AddressID', $this->fieldLabel('Address'), Address::get()->map('ID', 'Title'));
            $addressField->setEmptyString('');
            $fields->add($addressField);
        }

        return $fields;
    }

    /**
     * Adds the Age field to the marginal sidebar, below the Avatar.
     *
     * @return FieldList
     */
    protected function getMarginalCMSFields(): FieldList
    {
        $fields = parent::getMarginalCMSFields();

        if ($this->exists() && $this->Birthday) {
            /** @var TextField $ageField */
            $ageField = TextField::create('Age', $this->fieldLabel('Age'));
            $ageField->addExtraClass('horizontal-field');
            $ageField->setDisabled(true);
            $ageField->setReadonly(true);
            $fields->insertAfter('Avatar', $ageField);
        }

        return $fields;
    }

}

