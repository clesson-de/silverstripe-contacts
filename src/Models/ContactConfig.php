<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Models;

use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_AddressesInContactManager;
use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_AddressTypesInContactManager;
use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_ContactsInContactManager;
use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_ContactTagsInContactManager;
use Clesson\Silverstripe\Geocoding\Models\Address;
use Clesson\Silverstripe\Geocoding\Models\AddressType;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * Singleton configuration record for the Contacts module.
 *
 * Acts as the root record for the ContactManager SingleRecordAdmin.
 * All managed models (Contact, Address, ContactTag, AddressType) are listed
 * directly via GridFields in getCMSFields().
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Models
 */
class ContactConfig extends DataObject
{
    /**
     * @inheritdoc
     */
    private static string $table_name = 'Contacts_ContactConfig';

    /**
     * @inheritdoc
     */
    private static string $default_sort = 'ID ASC';

    /**
     * @inheritdoc
     */
    private static string $general_search_field = 'ID';

    /**
     * Returns the single ContactConfig record, creating one if none exists.
     *
     * @return static
     */
    public static function currentRecord(): static
    {
        $record = static::get()->setUseCache(true)->first();

        if (!$record) {
            $record = static::create();
            $record->write();
        }

        return $record;
    }

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Contacts']       = _t(Contact::class . '.PLURALNAME', 'Contacts');
        $labels['Addresses']      = _t(Address::class . '.PLURALNAME', 'Addresses');
        $labels['ContactTags']    = _t(ContactTag::class . '.PLURALNAME', 'Contact tags');
        $labels['AddressTypes']   = _t(AddressType::class . '.PLURALNAME', 'Address types');
        $labels['Administration'] = _t(__CLASS__ . '.ADMINISTRATION', 'Administration');
        $labels['ID']           = _t('Clesson\Silverstripe\Contacts\Common.ID', 'ID');
        $labels['Created']      = _t('Clesson\Silverstripe\Contacts\Common.CREATED', 'Created');
        $labels['LastEdited']   = _t('Clesson\Silverstripe\Contacts\Common.LAST_EDITED', 'Last edited');

        return $labels;
    }

    /**
     * @inheritdoc
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        /** @var GridField $contactsField */
        $contactsField = GridField::create(
            'ContactsGrid',
            $this->fieldLabel('Contacts'),
            Contact::get(),
            GridFieldConfig_ContactsInContactManager::create()
        );

        /** @var GridField $addressesField */
        $addressesField = GridField::create(
            'AddressesGrid',
            $this->fieldLabel('Addresses'),
            Address::get(),
            GridFieldConfig_AddressesInContactManager::create()
        );

        /** @var GridField $contactTagsField */
        $contactTagsField = GridField::create(
            'ContactTagsGrid',
            $this->fieldLabel('ContactTags'),
            ContactTag::get(),
            GridFieldConfig_ContactTagsInContactManager::create()
        );

        /** @var GridField $addressTypesField */
        $addressTypesField = GridField::create(
            'AddressTypesGrid',
            $this->fieldLabel('AddressTypes'),
            AddressType::get(),
            GridFieldConfig_AddressTypesInContactManager::create()
        );

        // Root.Main → renamed to "Contacts", contains the contacts GridField
        $fields->addFieldToTab('Root.Main', $contactsField);
        $fields->fieldByName('Root.Main')->setTitle($this->fieldLabel('Contacts'));

        // Root.Addresses → addresses GridField
        $fields->addFieldToTab('Root.Addresses', $addressesField);
        $fields->fieldByName('Root.Addresses')->setTitle($this->fieldLabel('Addresses'));

        // Root.Administration → inner TabSet with ContactTags and AddressTypes
        $fields->addFieldToTab('Root.Administration', TabSet::create(
            'AdministrationTabSet',
            Tab::create(
                'ContactTagsTab',
                $this->fieldLabel('ContactTags'),
                $contactTagsField
            ),
            Tab::create(
                'AddressTypesTab',
                $this->fieldLabel('AddressTypes'),
                $addressTypesField
            )
        ));
        $fields->fieldByName('Root.Administration')->setTitle($this->fieldLabel('Administration'));

        return $fields;
    }

    /**
     * The singleton record must not be deleted.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        return false;
    }

    /**
     * Allows viewing if the current member has the USE_CONTACTS permission.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return Permission::check('USE_CONTACTS', 'any', $member);
    }

    /**
     * Allows editing if the current member has the USE_CONTACTS permission.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        return Permission::check('USE_CONTACTS', 'any', $member);
    }

    /**
     * Creation is not applicable for a singleton record.
     *
     * @param Member|null $member
     * @param array       $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }
}

