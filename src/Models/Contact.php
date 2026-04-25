<?php

namespace Clesson\Contacts\Models;

use Clesson\Contacts\Admins\ContactManager;
use Clesson\Contacts\Constants\Gender;
use Clesson\Contacts\Helpers\CustomerNumberHelper;
use Clesson\Contacts\Helpers\DateHelper;
use JeroenDesloovere\VCard\VCard;
use LeKoala\CmsActions\CmsInlineFormAction;
use SilverStripe\Admin\CMSEditLinkExtension;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Unified contact model — represents both persons and companies.
 *
 * A contact is considered a company when Name1 or Name2 is filled,
 * otherwise it is treated as a person.
 *
 * @property string $SortingName
 * @property string $Name
 * @property string $Slug
 * @property string $Initials
 * @property string $Note
 * @property string $CustomerNumber
 * @property string $Since
 * @property string $Name1
 * @property string $Name2
 * @property int $Gender
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
 * @property int $AccountID
 * @property int $AvatarID
 * @property int $HomeAddressID
 * @property int $BusinessAddressID
 * @property int $OtherAddressID
 * @method Member Account()
 * @method Image Avatar()
 * @method Address HomeAddress()
 * @method Address BusinessAddress()
 * @method Address OtherAddress()
 * @method \SilverStripe\ORM\ManyManyList|ContactTag[] Tags()
 * @property-read string $Title
 * @property-read string $FullName
 * @property-read string $Age
 *
 * @package Clesson\Contacts
 * @subpackage Models
 */
class Contact extends DataObject implements PermissionProvider
{

    /**
     * @inheritdoc
     */
    private static $table_name = 'Contacts_Contact';

    /**
     * @inheritdoc
     */
    private static $default_sort = 'SortingName ASC';

    /**
     * @inheritdoc
     */
    private static $general_search_field = 'Name';

    /**
     * @inheritdoc
     */
    private static string $cms_edit_owner = ContactManager::class;

    /**
     * Additional CSS Class for the DetailForm (@see Clesson\Contacts\Extensions\ContactForm).
     */
    private static string $extra_form_class = 'contact-form';

    /**
     * @inheritdoc
     */
    private static $extensions = [
        CMSEditLinkExtension::class,
    ];

    /**
     * @inheritdoc
     */
    private static $db = [
        'SortingName' => 'Varchar(150)',
        'Name' => 'Varchar(150)',
        'Slug' => 'Varchar(150)',
        'Initials' => 'Varchar(5)',
        'Note' => 'Text',
        'CustomerNumber' => 'Varchar(64)',
        'Since' => 'Date',
        'Name1' => 'Varchar(150)',
        'Name2' => 'Varchar(150)',
        'Gender' => 'Int',
        'PersonTitle' => 'Varchar(50)',
        'FirstName' => 'Varchar(100)',
        'LastName' => 'Varchar(100)',
        'Nickname' => 'Varchar(100)',
        'SecondName' => 'Varchar(100)',
        'Suffix' => 'Varchar(50)',
        'Birthday' => 'Date',
        'DayOfDeath' => 'Date',
        'Anniversary' => 'Varchar(50)',
        'MaritalStatus' => 'Varchar(50)',
    ];

    /**
     * @inheritdoc
     */
    private static $indexes = [
        'CustomerNumberUnique' => [
            'type' => 'unique',
            'columns' => ['CustomerNumber'],
        ],
    ];

    /**
     * @inheritdoc
     */
    private static $has_one = [
        'Account' => Member::class,
        'Avatar' => Image::class,
        'HomeAddress' => Address::class,
        'BusinessAddress' => Address::class,
        'OtherAddress' => Address::class,
    ];

    /**
     * @inheritdoc
     */
    private static $many_many = [
        'Tags' => ContactTag::class,
    ];

    /**
     * @inheritdoc
     */
    private static $owns = [
        'Avatar',
    ];

    /**
     * @inheritdoc
     */
    private static $summary_fields = [
        'Icon',
        'Name',
        'CustomerNumber',
        'Created',
        'LastEdited',
    ];

    /**
     * @inheritdoc
     */
    private static $searchable_fields = [
        'Name' => [
            'field' => TextField::class,
            'filter' => 'PartialMatchFilter',
        ],
    ];

    /**
     * Returns a contact by its slug.
     *
     * @param string $slug
     * @return Contact|null
     */
    public static function get_by_slug(string $slug): ?Contact
    {
        return static::get()->where(['Slug' => $slug])->first();
    }

    /**
     * Returns contacts filtered by a tag unique key.
     *
     * @param string $tag
     * @return \SilverStripe\ORM\DataList
     */
    public static function get_by_tag(string $tag): \SilverStripe\ORM\DataList
    {
        return Contact::get()->filter(['Tags.Ukey' => $tag])->distinct();
    }

    /**
     * Returns true if this contact represents a company.
     *
     * @return bool
     */
    public function isCompany(): bool
    {
        return (bool) trim($this->Name1 ?? '') || (bool) trim($this->Name2 ?? '');
    }

    /**
     * Returns true if this contact represents a person.
     *
     * @return bool
     */
    public function isPerson(): bool
    {
        return !$this->isCompany();
    }

    /**
     * Returns the full formatted name.
     *
     * For a person: salutation, title, first name, second name, last name, suffix.
     * For a company: Name1.
     *
     * @return string
     */
    public function getFullName(): string
    {
        if ($this->isCompany()) {
            return (string) $this->Name1;
        }

        return implode(' ', array_filter([
            Gender::salutation($this->Gender),
            $this->PersonTitle,
            $this->FirstName,
            $this->SecondName,
            $this->LastName,
            $this->Suffix,
        ], function ($segment) {
            return $segment && strlen(trim($segment));
        }));
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
     * Creates the default display name for this contact.
     *
     * For a company: Name1.
     * For a person: full name from salutation, title, names and suffix.
     *
     * @return string
     */
    protected function createDefaultName(): string
    {
        if ($this->isCompany()) {
            return (string) $this->Name1;
        }

        return implode(' ', array_filter([
            Gender::salutation($this->Gender),
            $this->PersonTitle,
            $this->FirstName,
            $this->SecondName,
            $this->LastName,
            $this->Suffix,
        ], function ($segment) {
            return $segment && strlen(trim($segment));
        }));
    }

    /**
     * Sets default values for new records.
     *
     * @return void
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();

        $this->Gender = Gender::NOT_KNOWN;
        $this->Since = date('Y-m-d');

        if (!$this->CustomerNumber) {
            $template = SiteConfig::current_site_config()->Contacts_CustomerNumberTemplate
                ?: CustomerNumberHelper::DEFAULT_TEMPLATE;

            $this->CustomerNumber = CustomerNumberHelper::generateUnique($template);
        }
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->Name = $this->createDefaultName();
        $this->updateSortingName();
        $this->updateInitials();
        $this->updateSlug();
        $this->ensureCustomerNumber();
    }

    /**
     * Updates the SortingName based on the contact type.
     *
     * @return void
     */
    private function updateSortingName(): void
    {
        if ($this->isCompany()) {
            $this->SortingName = implode(' ', array_filter(
                [$this->Name1, $this->Name2],
                fn($s) => $s && strlen(trim($s))
            ));
        } else {
            $this->SortingName = implode(' ', array_filter(
                [$this->LastName, $this->FirstName, $this->SecondName],
                fn($s) => $s && strlen(trim($s))
            ));
        }
    }

    /**
     * Updates the Initials based on the contact type.
     *
     * @return void
     */
    private function updateInitials(): void
    {
        if ($this->isCompany()) {
            $parts = array_filter([$this->Name1], fn($s) => $s && strlen(trim($s)));
        } else {
            $parts = array_filter(
                [$this->FirstName, $this->SecondName, $this->LastName],
                fn($s) => $s && strlen(trim($s))
            );
        }

        $this->Initials = substr(implode('', array_map(
            fn($s) => substr(strtoupper(trim($s)), 0, 1),
            $parts
        )), 0, 2);
    }

    /**
     * Creates a unique slug for this contact.
     *
     * @return void
     */
    private function updateSlug(): void
    {
        $filter = URLSegmentFilter::create();
        $defaultName = $this->createDefaultName();
        $slug = $filter->filter($defaultName);
        $count = 0;

        while (Contact::get_by_slug($slug)) {
            $count++;
            $slug = $filter->filter($defaultName . ' ' . $count);
        }

        $this->extend('updateSlug', $slug);
        $this->Slug = $slug;
    }

    /**
     * Ensures a CustomerNumber is filled from the configured template.
     *
     * @return void
     */
    private function ensureCustomerNumber(): void
    {
        if ($this->CustomerNumber) {
            return;
        }

        $template = SiteConfig::current_site_config()->Contacts_CustomerNumberTemplate
            ?: CustomerNumberHelper::DEFAULT_TEMPLATE;

        $this->CustomerNumber = CustomerNumberHelper::generateUnique($template, (int) $this->ID);
    }

    /**
     * @inheritdoc
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->isPerson() && !$this->LastName) {
            $result->addError(_t(
                Form::class . '.FIELDISREQUIRED',
                '{name} is required',
                ['name' => $this->fieldLabel('LastName')]
            ));
        }

        if ($this->isCompany() && !$this->Name1) {
            $result->addError(_t(
                Form::class . '.FIELDISREQUIRED',
                '{name} is required',
                ['name' => $this->fieldLabel('Name1')]
            ));
        }

        if ($this->CustomerNumber && !CustomerNumberHelper::isUnique($this->CustomerNumber, (int) $this->ID)) {
            $result->addError(_t(
                __CLASS__ . '.CUSTOMER_NUMBER_NOT_UNIQUE',
                'The customer number "{number}" is already in use. Please choose a different one.',
                ['number' => $this->CustomerNumber]
            ));
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Icon'] = '';
        $labels['Name'] = _t(__CLASS__ . '.NAME', 'Display name');
        $labels['Note'] = _t(__CLASS__ . '.NOTE', 'Note');
        $labels['Tags'] = _t(__CLASS__ . '.TAGS', 'Tags');
        $labels['Account'] = _t(__CLASS__ . '.ACCOUNT', 'CMS Account');
        $labels['vCardLink'] = _t(__CLASS__ . '.DOWNLOAD_VCARD', 'Download vCard');
        $labels['CustomerNumber'] = _t(__CLASS__ . '.CUSTOMER_NUMBER', 'Customer number');
        $labels['Since'] = _t(__CLASS__ . '.SINCE', 'Customer since');
        $labels['Name1'] = _t(__CLASS__ . '.NAME1', 'Company name');
        $labels['Name2'] = _t(__CLASS__ . '.NAME2', 'Company name suffix');
        $labels['Gender'] = _t(__CLASS__ . '.GENDER', 'Gender');
        $labels['PersonTitle'] = _t(__CLASS__ . '.PERSON_TITLE', 'Title');
        $labels['FirstName'] = _t(__CLASS__ . '.FIRST_NAME', 'First name');
        $labels['LastName'] = _t(__CLASS__ . '.LAST_NAME', 'Last name');
        $labels['Nickname'] = _t(__CLASS__ . '.NICKNAME', 'Nickname');
        $labels['SecondName'] = _t(__CLASS__ . '.SECOND_NAME', 'Middle name');
        $labels['Suffix'] = _t(__CLASS__ . '.SUFFIX', 'Suffix');
        $labels['Birthday'] = _t(__CLASS__ . '.BIRTHDAY', 'Date of birth');
        $labels['DayOfDeath'] = _t(__CLASS__ . '.DAY_OF_DEATH', 'Date of death');
        $labels['Anniversary'] = _t(__CLASS__ . '.ANNIVERSARY', 'Anniversary');
        $labels['MaritalStatus'] = _t(__CLASS__ . '.MARITAL_STATUS', 'Marital status');
        $labels['Age'] = _t(__CLASS__ . '.AGE', 'Age');
        $labels['FullName'] = _t(__CLASS__ . '.FULL_NAME', 'Full name');
        $labels['HomeAddress'] = _t(__CLASS__ . '.HOME_ADDRESS', 'Home address');
        $labels['BusinessAddress'] = _t(__CLASS__ . '.BUSINESS_ADDRESS', 'Business address');
        $labels['OtherAddress'] = _t(__CLASS__ . '.OTHER_ADDRESS', 'Other address');
        $labels['ID'] = _t('Clesson\Contacts\Common.ID', 'ID');
        $labels['Created'] = _t('Clesson\Contacts\Common.CREATED', 'Created');
        $labels['LastEdited'] = _t('Clesson\Contacts\Common.LAST_EDITED', 'Last edited');
        return $labels;
    }

    /**
     * Returns an HTML icon for gridfields — avatar image or initials.
     *
     * @return DBField|string
     */
    public function getIcon(): DBField|string
    {
        if ($this->AvatarID && $avatar = $this->Avatar()) {
            return DBField::create_field('HTMLText', '<img class="contact-icon" src="' . $avatar->AbsoluteLink() . '">');
        }

        if ($initials = $this->Initials) {
            return DBField::create_field('HTMLText', '<div class="contact-icon">' . $initials . '</div>');
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getCMSFields(): FieldList
    {
        return FieldList::create(
            CompositeField::create(
                CompositeField::create(
                    CompositeField::create(
                        $this->getMarginalCMSFields()
                    )->addExtraClass('contact-marginal--dynamic'),
                    CompositeField::create(
                        $this->getFixedMarginalCMSFields()
                    )->addExtraClass('contact-marginal--fixed'),
                )->addExtraClass('contact-marginal'),
                CompositeField::create(
                    $this->getMainTabSet()
                )->addExtraClass('contact-main')
            )->addExtraClass('contact-holder')
        );
    }

    /**
     * Builds the main tab set with contact details and addresses tabs.
     *
     * @return TabSet
     */
    protected function getMainTabSet(): TabSet
    {
        $tabSet = new TabSet(
            'MainTabSet',
            $contactTab = new Tab(
                $this->i18n_singular_name(),
            ),
            $addressesTab = new Tab(
                _t(__CLASS__ . '.ADDRESSES', 'Addresses'),
            )
        );

        $mainFields = $this->getMainCMSFields()->toArray();
        foreach ($mainFields as $mainField) {
            $contactTab->FieldList()->add($mainField);
        }

        $this->addAddressFields($addressesTab);
        $this->extend('updateMainTabSet', $tabSet);

        return $tabSet;
    }

    /**
     * Builds the main CMS fields for the contact detail tab.
     *
     * @return FieldList
     */
    protected function getMainCMSFields(): FieldList
    {
        $fields = FieldList::create();

        $this->addPersonFields($fields);
        $this->addCompanyFields($fields);
        $this->addCustomerFields($fields);

        return $fields;
    }

    /**
     * Adds person-specific fields (gender, title, names, dates).
     *
     * @param FieldList $fields
     * @return void
     */
    private function addPersonFields(FieldList $fields): void
    {
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
    }

    /**
     * Adds company-specific fields (Name1, Name2).
     *
     * @param FieldList $fields
     * @return void
     */
    private function addCompanyFields(FieldList $fields): void
    {
        /** @var TextField $name1Field */
        $name1Field = TextField::create('Name1', $this->fieldLabel('Name1'));
        $fields->add($name1Field);

        /** @var TextField $name2Field */
        $name2Field = TextField::create('Name2', $this->fieldLabel('Name2'));
        $fields->add($name2Field);
    }

    /**
     * Adds customer-specific fields (CustomerNumber, Since).
     *
     * @param FieldList $fields
     * @return void
     */
    private function addCustomerFields(FieldList $fields): void
    {
        /** @var TextField $customerNumberField */
        $customerNumberField = TextField::create('CustomerNumber', $this->fieldLabel('CustomerNumber'));
        $customerNumberField->setDescription(
            _t(
                __CLASS__ . '.CUSTOMER_NUMBER_DESCRIPTION',
                'Auto-generated from the template configured in Settings. You may override it manually.'
            )
        );
        $fields->add($customerNumberField);

        /** @var DateField $sinceField */
        $sinceField = DateField::create('Since', $this->fieldLabel('Since'));
        $fields->add($sinceField);
    }

    /**
     * Adds address dropdown fields to the addresses tab.
     *
     * @param Tab $tab
     * @return void
     */
    private function addAddressFields(Tab $tab): void
    {
        $addressMap = Address::get()->map('ID', 'Title')->toArray();

        /** @var DropdownField $homeAddressField */
        $homeAddressField = DropdownField::create('HomeAddressID', $this->fieldLabel('HomeAddress'), $addressMap);
        $homeAddressField->setEmptyString('');
        $tab->FieldList()->add($homeAddressField);

        /** @var DropdownField $businessAddressField */
        $businessAddressField = DropdownField::create('BusinessAddressID', $this->fieldLabel('BusinessAddress'), $addressMap);
        $businessAddressField->setEmptyString('');
        $tab->FieldList()->add($businessAddressField);

        /** @var DropdownField $otherAddressField */
        $otherAddressField = DropdownField::create('OtherAddressID', $this->fieldLabel('OtherAddress'), $addressMap);
        $otherAddressField->setEmptyString('');
        $tab->FieldList()->add($otherAddressField);
    }

    /**
     * Builds the fixed marginal fields (Created, LastEdited).
     *
     * @return FieldList
     */
    private function getFixedMarginalCMSFields(): FieldList
    {
        $fields = FieldList::create();

        if ($this->exists()) {
            /** @var TextField $createdField */
            $createdField = TextField::create('Created', $this->fieldLabel('Created'));
            $createdField->addExtraClass('horizontal-field');
            $createdField->setDisabled(true);
            $createdField->setReadonly(true);
            $fields->add($createdField);

            if ($this->LastEdited && $this->Created !== $this->LastEdited) {
                /** @var TextField $lastEditedField */
                $lastEditedField = TextField::create('LastEdited', $this->fieldLabel('LastEdited'));
                $lastEditedField->addExtraClass('horizontal-field');
                $lastEditedField->setDisabled(true);
                $lastEditedField->setReadonly(true);
                $fields->add($lastEditedField);
            }
        }

        $this->extend('updateFixedMarginalCMSFields', $fields);

        return $fields;
    }

    /**
     * Builds the marginal sidebar fields (Avatar, Age, vCard, Tags, Account).
     *
     * @return FieldList
     */
    protected function getMarginalCMSFields(): FieldList
    {
        $fields = FieldList::create();

        if ($this->exists()) {
            /** @var UploadField $avatarField */
            $avatarField = UploadField::create('Avatar', $this->fieldLabel('Avatar'));
            $avatarField->setTitle('');
            $avatarField->setFolderName('avatar');
            $fields->add($avatarField);

            if ($this->isPerson() && $this->Birthday) {
                /** @var TextField $ageField */
                $ageField = TextField::create('Age', $this->fieldLabel('Age'));
                $ageField->addExtraClass('horizontal-field');
                $ageField->setDisabled(true);
                $ageField->setReadonly(true);
                $fields->add($ageField);
            }

            /** @var CmsInlineFormAction $vCardField */
            $vCardField = CmsInlineFormAction::create('downloadVCard', $this->fieldLabel('vCardLink'), 'btn-primary');
            $fields->add($vCardField);

            /** @var ListboxField $tagsField */
            $tagsField = ListboxField::create('Tags', $this->fieldLabel('Tags'), ContactTag::get()->Map('ID', 'Title'));
            $fields->add($tagsField);

            /** @var DropdownField $accountField */
            $accountField = DropdownField::create('AccountID', $this->fieldLabel('Account'), Member::get());
            $accountField->setEmptyString('');
            $fields->add($accountField);
        }

        $this->extend('updateMarginalCMSFields', $fields);

        return $fields;
    }

    /**
     * Creates an avatar preview image tag for gridfields.
     *
     * @return DBField
     */
    public function getAvatarPreview(): DBField
    {
        $avatar = $this->Avatar();
        $image = $avatar && $avatar->exists()
            ? '<img src="' . $avatar->Fill(42, 42)->AbsoluteLink() . '" style="border-radius: 50%;">'
            : '';

        return DBField::create_field('HTMLText', $image);
    }

    /**
     * Generates a vCard for this contact.
     *
     * @return VCard
     */
    public function getVCard(): VCard
    {
        $card = new VCard();

        if ($this->isCompany()) {
            $card->addCompany($this->Name1);
        } else {
            $card->addName($this->LastName, $this->FirstName, $this->SecondName, $this->PersonTitle, $this->Suffix);
        }

        return $card;
    }

    /**
     * Returns the absolute URL for the vCard download.
     *
     * @return string
     */
    public function getvCardLink(): string
    {
        $link = Director::absoluteURL(Controller::join_links('contact', 'vcard', $this->Slug));
        $this->extend('updatevCardLink', $link);

        return $link;
    }

    /**
     * Returns the display title of this contact.
     *
     * @return string
     */
    public function getTitle(): string
    {
        $name = (string) $this->Name;

        if ($this->isPerson() && $this->Birthday) {
            $suffix = ' *' . date('Y', strtotime($this->Birthday));
            if ($this->DayOfDeath) {
                $suffix .= '; †' . date('Y', strtotime($this->DayOfDeath));
            }
            return $name . $suffix;
        }

        return $name;
    }

    /**
     * Returns the primary address of this contact (Home > Business > Other).
     *
     * @return Address|null
     */
    public function getAddress(): ?Address
    {
        if ($this->HomeAddressID) {
            return $this->HomeAddress();
        }

        if ($this->BusinessAddressID) {
            return $this->BusinessAddress();
        }

        if ($this->OtherAddressID) {
            return $this->OtherAddress();
        }

        return null;
    }

    /**
     * A facade method to get the site owner contact.
     *
     * @return Contact|null
     */
    public static function current_site_owner(): ?Contact
    {
        return SiteConfig::current_site_config()->Contacts_SiteOwner();
    }

    /**
     * @inheritdoc
     */
    public function providePermissions(): array
    {
        return [
            'USE_CONTACTS' => [
                'name' => _t(__CLASS__ . '.USE_CONTACTS', 'Editing and using contacts'),
                'help' => _t(__CLASS__ . '.USE_CONTACTS_HELP', 'Allows editing and management of contact records'),
                'category' => _t(__CLASS__ . '.CONTACT_PERMISSIONS', 'Contact actions'),
                'sort' => 200,
            ],
            'DOWNLOAD_CONTACTS' => [
                'name' => _t(__CLASS__ . '.DOWNLOAD_CONTACTS', 'Download contacts'),
                'help' => _t(__CLASS__ . '.DOWNLOAD_CONTACTS_HELP', 'Allows downloading contact records as vCards'),
                'category' => _t(__CLASS__ . '.CONTACT_PERMISSIONS', 'Contact actions'),
                'sort' => 200,
            ],
        ];
    }

    /**
     * Allows viewing if the current member has the ACCESS_CONTACTS permission.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return Permission::check('ACCESS_CONTACTS', 'any', $member);
    }

    /**
     * Allows editing if the current member has the ACCESS_CONTACTS permission.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        return Permission::check('ACCESS_CONTACTS', 'any', $member);
    }

    /**
     * Allows deletion if the current member has the ACCESS_CONTACTS permission.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        return Permission::check('ACCESS_CONTACTS', 'any', $member);
    }

    /**
     * Allows creation if the current member has the ACCESS_CONTACTS permission.
     *
     * @param Member|null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('ACCESS_CONTACTS', 'any', $member);
    }

}
