<?php

namespace Clesson\Silverstripe\Contacts\Extensions;

use Clesson\Silverstripe\Contacts\Models\Contact;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * An extension for the SiteConfig class.
 * Allows to define a contact as the owner of the website.
 *
 * @property int    $Contacts_SiteOwnerID
 * @method Contact Contacts_SiteOwner()
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Extensions
 */
class SiteConfigOwner extends Extension implements TemplateGlobalProvider
{

    /**
     * @inheritdoc
     */
    private static $has_one = [
        'Contacts_SiteOwner' => Contact::class,
    ];

    /**
     * @inheritdoc
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['Contacts_SiteOwner'] = _t(__CLASS__ . '.SITE_OWNER', 'Owner of website');
    }

    /**
     * @inheritdoc
     */
    public function updateCMSFields(FieldList $fields): void
    {
        /** @var DropdownField $siteOwnerField */
        $siteOwnerField = DropdownField::create(
            'Contacts_SiteOwnerID',
            $this->getOwner()->fieldLabel('Contacts_SiteOwner'),
            Contact::get()->Sort('Name ASC')->Map('ID', 'Name')
        );
        $siteOwnerField->setEmptyString('...');

        if (!Permission::check('ACCESS_CONTACTS')) {
            $siteOwnerField->setDisabled(true);
        }

        $fields->addFieldToTab('Root.Main', $siteOwnerField);
    }

    /**
     * @return Contact|null
     */
    public static function current_site_owner(): ?Contact
    {
        return SiteConfig::current_site_config()->Contacts_SiteOwner();
    }

    /**
     * @return string[]
     */
    public static function get_template_global_variables(): array
    {
        return [
            'siteOwner' => 'current_site_owner',
        ];
    }

}
