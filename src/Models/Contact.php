<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Models;

use Clesson\Silverstripe\Contacts\Admins\ContactManager;
use Clesson\Silverstripe\Contacts\Helpers\CustomerNumberHelper;
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
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use Clesson\Silverstripe\Geocoding\Models\Address;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Abstract base class for all contact types.
 * Concrete subclasses: Company, Person, Employee.
 *
 * Not declared with PHP's `abstract` keyword because Silverstripe's ORM
 * uses `new $dataClass()` directly in TableBuilder (dev/build) and therefore
 * cannot handle abstract classes. Contact serves as a conceptual base class —
 * direct instantiation via `Contact::create()` or `new Contact()` must not
 * be used in application code; use Company, Person, or Employee instead.
 *
 * @property string $SortingName
 * @property string $Name
 * @property string $Slug
 * @property string $Initials
 * @property string $Note
 * @property string $CustomerNumber
 * @property string $CustomerSince
 * @property int $AccountID
 * @property int $AvatarID
 * @property int $AddressID
 * @method Member Account()
 * @method Image Avatar()
 * @method Address Address()
 * @method \SilverStripe\ORM\ManyManyList|ContactTag[] Tags()
 * @property-read string $Title
 * @property-read string $FullName
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Models
 */
class Contact extends DataObject implements PermissionProvider
{

    /**
     * Ukey of the ContactTag that marks a contact as a customer.
     * Must match the normalised Ukey stored in Contacts_ContactTag.Ukey.
     */
    public const CUSTOMER_TAG_UKEY = 'CUSTOMER';

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
     * Additional CSS class for the detail form.
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
        'CustomerNumber' => 'Varchar(50)',
        'CustomerSince'  => 'Date',
        'SortingName'    => 'Varchar(150)',
        'Name'           => 'Varchar(150)',
        'Slug'           => 'Varchar(150)',
        'Initials'       => 'Varchar(5)',
        'Note'           => 'Text',
    ];

    /**
     * @inheritdoc
     */
    private static $indexes = [];

    /**
     * @inheritdoc
     */
    private static $has_one = [
        'Account' => Member::class,
        'Avatar'  => Image::class,
        'Address' => Address::class,
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
        'Created',
        'LastEdited',
    ];

    /**
     * @inheritdoc
     */
    private static $searchable_fields = [
        'Name' => [
            'field'  => TextField::class,
            'filter' => 'PartialMatchFilter',
        ],
    ];

    // -------------------------------------------------------------------------
    // Base implementations — override in every concrete subclass.
    // -------------------------------------------------------------------------

    /**
     * Returns the full formatted display name for this contact.
     * Subclasses must override this to provide a meaningful value.
     *
     * @return string
     */
    public function getFullName(): string
    {
        return (string) $this->Name;
    }

    /**
     * Returns a vCard representation of this contact.
     * Subclasses must override this to provide a meaningful value.
     *
     * @return VCard
     */
    public function getVCard(): VCard
    {
        return new VCard();
    }

    /**
     * Builds the default value for the Name field before every write.
     * Subclasses must override this to provide a meaningful value.
     *
     * @return string
     */
    protected function createDefaultName(): string
    {
        return (string) $this->Name;
    }

    /**
     * Computes and sets the SortingName field.
     * Subclasses must override this to provide a meaningful value.
     *
     * @return void
     */
    protected function updateSortingName(): void
    {
        $this->SortingName = (string) $this->Name;
    }

    /**
     * Computes and sets the Initials field.
     * Subclasses must override this to provide a meaningful value.
     *
     * @return void
     */
    protected function updateInitials(): void
    {
    }

    /**
     * Returns the CMS fields for the main contact tab.
     * Subclasses must override this to provide type-specific fields.
     *
     * @return FieldList
     */
    protected function getMainCMSFields(): FieldList
    {
        return FieldList::create();
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

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
     * Returns the site owner contact from SiteConfig.
     *
     * @return Contact|null
     */
    public static function current_site_owner(): ?Contact
    {
        return SiteConfig::current_site_config()->Contacts_SiteOwner();
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

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
    }

    /**
     * Flag to prevent recursive writes when auto-generating the customer number.
     */
    private bool $generatingCustomerNumber = false;

    /**
     * After the record and its relations are persisted, auto-generate a unique
     * customer number if the contact belongs to the CUSTOMER group and does not
     * yet have a customer number.
     *
     * @inheritdoc
     */
    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();

        if (!$this->generatingCustomerNumber && $this->isCustomer() && !$this->CustomerNumber) {
            $this->generatingCustomerNumber = true;
            $this->CustomerNumber = CustomerNumberHelper::generateUnique(
                CustomerNumberHelper::DEFAULT_TEMPLATE,
                (int) $this->ID
            );
            $this->CustomerSince = date('Y-m-d');
            $this->write();
            $this->generatingCustomerNumber = false;
        }
    }

    /**
     * Returns true when this contact is tagged with the CUSTOMER group.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->Tags()->filter('Ukey', self::CUSTOMER_TAG_UKEY)->count() > 0;
    }

    /**
     * Creates a unique URL slug for this contact.
     *
     * @return void
     */
    private function updateSlug(): void
    {
        $filter      = URLSegmentFilter::create();
        $defaultName = $this->createDefaultName();
        $slug        = $filter->filter($defaultName);
        $count       = 0;

        while (Contact::get_by_slug($slug)) {
            $count++;
            $slug = $filter->filter($defaultName . ' ' . $count);
        }

        $this->extend('updateSlug', $slug);
        $this->Slug = $slug;
    }

    // -------------------------------------------------------------------------
    // Validation & labels
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function validate(): ValidationResult
    {
        return parent::validate();
    }

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Icon']           = '';
        $labels['Name']           = _t(__CLASS__ . '.NAME', 'Display name');
        $labels['Note']           = _t(__CLASS__ . '.NOTE', 'Note');
        $labels['Tags']           = _t(__CLASS__ . '.TAGS', 'Tags');
        $labels['Account']        = _t(__CLASS__ . '.ACCOUNT', 'CMS Account');
        $labels['vCardLink']      = _t(__CLASS__ . '.DOWNLOAD_VCARD', 'Download vCard');
        $labels['CustomerNumber'] = _t(__CLASS__ . '.CUSTOMER_NUMBER', 'Customer number');
        $labels['CustomerSince']  = _t(__CLASS__ . '.CUSTOMER_SINCE', 'Customer since');
        $labels['ID']             = _t('Clesson\Silverstripe\Contacts\Common.ID', 'ID');
        $labels['Created']        = _t('Clesson\Silverstripe\Contacts\Common.CREATED', 'Created');
        $labels['LastEdited']     = _t('Clesson\Silverstripe\Contacts\Common.LAST_EDITED', 'Last edited');
        return $labels;
    }

    // -------------------------------------------------------------------------
    // CMS form
    // -------------------------------------------------------------------------

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
     * Builds the main tab set with the contact detail tab and addresses tab.
     * Subclasses fill the contact tab via getMainCMSFields().
     *
     * @return TabSet
     */
    protected function getMainTabSet(): TabSet
    {
        $tabSet = new TabSet(
            'MainTabSet',
            $contactTab = new Tab($this->i18n_singular_name()),
        );

        foreach ($this->getMainCMSFields()->toArray() as $field) {
            $contactTab->FieldList()->add($field);
        }

        $this->extend('updateMainTabSet', $tabSet);

        return $tabSet;
    }

    /**
     * Builds the fixed marginal sidebar fields (Created, LastEdited).
     * Subclasses can extend via the updateFixedMarginalCMSFields hook.
     *
     * @return FieldList
     */
    protected function getFixedMarginalCMSFields(): FieldList
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
     * Builds the dynamic marginal sidebar fields (Avatar, vCard, Tags, Account).
     * Subclasses may override to insert additional fields (e.g. Person adds Age).
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

            /** @var CmsInlineFormAction $vCardField */
            $vCardField = CmsInlineFormAction::create('downloadVCard', $this->fieldLabel('vCardLink'), 'btn-primary');
            $fields->add($vCardField);

            /** @var ListboxField $tagsField */
            $tagsField = ListboxField::create('Tags', $this->fieldLabel('Tags'), ContactTag::get()->Map('ID', 'Title'));
            $fields->add($tagsField);

            if ($this->isCustomer()) {
                /** @var TextField $customerNumberField */
                $customerNumberField = TextField::create('CustomerNumber', $this->fieldLabel('CustomerNumber'));
                $customerNumberField->addExtraClass('horizontal-field');
                $fields->add($customerNumberField);

                /** @var DateField $customerSinceField */
                $customerSinceField = DateField::create('CustomerSince', $this->fieldLabel('CustomerSince'));
                $customerSinceField->addExtraClass('horizontal-field');
                $fields->add($customerSinceField);
            }

            $usedMemberIds = Contact::get()
                ->exclude('ID', (int) $this->ID)
                ->filter('AccountID:GreaterThan', 0)
                ->column('AccountID');

            $availableMembers = Member::get();
            if (!empty($usedMemberIds)) {
                $availableMembers = $availableMembers->exclude('ID', $usedMemberIds);
            }

            /** @var DropdownField $accountField */
            $accountField = DropdownField::create('AccountID', $this->fieldLabel('Account'), $availableMembers->map('ID', 'Name'));
            $accountField->setEmptyString('');
            $fields->add($accountField);
        }

        $this->extend('updateMarginalCMSFields', $fields);

        return $fields;
    }

    /**
     * Returns the primary address of this contact.
     *
     * @return Address|null
     */
    public function getAddress(): ?Address
    {
        return $this->AddressID ? $this->Address() : null;
    }

    /**
     * Returns the display title. Subclasses may override for richer formatting.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string) $this->Name;
    }

    /**
     * Returns an HTML icon for GridFields — avatar image or initials badge.
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
     * Returns an avatar preview image tag for GridFields.
     *
     * @return DBField
     */
    public function getAvatarPreview(): DBField
    {
        $avatar = $this->Avatar();
        $image  = $avatar && $avatar->exists()
            ? '<img src="' . $avatar->Fill(42, 42)->AbsoluteLink() . '" style="border-radius: 50%;">'
            : '';

        return DBField::create_field('HTMLText', $image);
    }

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function providePermissions(): array
    {
        return [
            'USE_CONTACTS' => [
                'name'     => _t(__CLASS__ . '.USE_CONTACTS', 'Editing and using contacts'),
                'help'     => _t(__CLASS__ . '.USE_CONTACTS_HELP', 'Allows editing and management of contact records'),
                'category' => _t(__CLASS__ . '.CONTACT_PERMISSIONS', 'Contact actions'),
                'sort'     => 200,
            ],
            'DOWNLOAD_CONTACTS' => [
                'name'     => _t(__CLASS__ . '.DOWNLOAD_CONTACTS', 'Download contacts'),
                'help'     => _t(__CLASS__ . '.DOWNLOAD_CONTACTS_HELP', 'Allows downloading contact records as vCards'),
                'category' => _t(__CLASS__ . '.CONTACT_PERMISSIONS', 'Contact actions'),
                'sort'     => 200,
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
     * @param array       $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('ACCESS_CONTACTS', 'any', $member);
    }

}
