<?php

namespace Clesson\Contacts\Extensions;

use Clesson\Contacts\Helpers\CustomerNumberHelper;
use Clesson\Contacts\Models\Contact;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * An extension for the SiteConfig class.
 * Allows to define a contact as the owner of the website and to configure
 * the customer number template used for auto-generating customer numbers.
 *
 * @property int    $Contacts_SiteOwnerID
 * @property string $Contacts_CustomerNumberTemplate
 * @method Contact Contacts_SiteOwner()
 *
 * @package Clesson\Contacts
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
    private static $db = [
        'Contacts_CustomerNumberTemplate' => 'Varchar(255)',
    ];

    /**
     * Sets the default customer number template when no value is stored yet.
     *
     * @return void
     */
    public function populateDefaults(): void
    {
        if (!$this->getOwner()->Contacts_CustomerNumberTemplate) {
            $this->getOwner()->Contacts_CustomerNumberTemplate = CustomerNumberHelper::DEFAULT_TEMPLATE;
        }
    }

    /**
     * @inheritdoc
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['Contacts_SiteOwner']                  = _t(__CLASS__ . '.SITE_OWNER', 'Owner of website');
        $labels['Contacts_CustomerNumberTemplate']     = _t(__CLASS__ . '.CUSTOMER_NUMBER_TEMPLATE', 'Customer number template');
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

        /** @var TextField $templateField */
        $templateField = TextField::create(
            'Contacts_CustomerNumberTemplate',
            $this->getOwner()->fieldLabel('Contacts_CustomerNumberTemplate')
        );
        $templateField->setAttribute('placeholder', CustomerNumberHelper::DEFAULT_TEMPLATE);
        $templateField->setDescription(
            _t(
                __CLASS__ . '.CUSTOMER_NUMBER_TEMPLATE_DESCRIPTION',
                'Template for auto-generating customer numbers. '
                . 'Available variables: '
                . '{Y} year (4-digit), {y} year (2-digit), {m} month, {d} day, {H} hour, {i} minute, {s} second — '
                . '{N:3} random digits (length 3), '
                . '{A:2} random letters (length 2), '
                . '{X:4} random letters+digits (length 4). '
                . 'Example: K-{Y}-{N:3} → K-2026-047'
            )
        );

        $fields->addFieldToTab(
            'Root.' . _t(__CLASS__ . '.TAB_CONTACTS', 'Contacts'),
            $templateField
        );
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
