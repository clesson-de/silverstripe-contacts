<?php

namespace Clesson\Contacts\Extensions;

use Clesson\Contacts\Models\Contact;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Core\Extension;

/**
 * Extends the Member model to allow linking a Member to a Contact record.
 *
 * @property int $ContactID
 * @method Contact Contact()
 *
 * @package Clesson\Contacts
 * @subpackage Extensions
 */
class ContactableMember extends Extension
{

    /**
     * @inheritdoc
     */
    private static $belongs_to = [
        'Contact' => Contact::class,
    ];

    /**
     * Adds translated labels for fields and relations contributed by this extension.
     *
     * @param array $labels
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['Contact'] = _t(__CLASS__ . '.CONTACT', 'Linked contact');
        $labels['ContactID'] = _t(__CLASS__ . '.CONTACT', 'Linked contact');
    }

    /**
     * Adds the linked Contact dropdown to the Member CMS edit form.
     *
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $contactField = DropdownField::create(
            'ContactID',
            $this->owner->fieldLabel('Contact'),
            Contact::get()->sort('Name ASC')->map('ID', 'Name')
        );
        $contactField->setEmptyString('');
        $fields->addFieldToTab('Root.Main', $contactField);
    }

}
