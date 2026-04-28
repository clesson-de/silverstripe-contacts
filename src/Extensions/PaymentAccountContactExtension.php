<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Extensions;

use Clesson\Silverstripe\Contacts\Models\Contact;
use SilverStripe\Core\Extension;

/**
 * Extends the PaymentAccount model with the has_one relation to Contact.
 * This extension is registered in silverstripe-contacts to avoid a circular
 * dependency between silverstripe-contacts and silverstripe-payment-accounts.
 *
 * @property int $ContactID
 * @method Contact Contact()
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Extensions
 */
class PaymentAccountContactExtension extends Extension
{

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'Contact' => Contact::class,
    ];

    /**
     * Adds the translated label for the Contact relation.
     *
     * @param array $labels
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['Contact']   = _t(__CLASS__ . '.CONTACT', 'Contact');
        $labels['ContactID'] = _t(__CLASS__ . '.CONTACT', 'Contact');
    }

}

