<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Extensions;

use Clesson\Silverstripe\PaymentAccount\Forms\GridFieldConfig_PaymentAccountsInContact;
use Clesson\Silverstripe\PaymentAccount\Models\PaymentAccount;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\HasManyList;

/**
 * Extends the Contact model with payment account functionality.
 * Adds a has_many relation to PaymentAccount and provides the CMS GridField.
 *
 * @method HasManyList|PaymentAccount[] PaymentAccounts()
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Extensions
 */
class ContactPaymentAccountsExtension extends Extension
{

    /**
     * @inheritdoc
     */
    private static array $has_many = [
        'PaymentAccounts' => PaymentAccount::class . '.Contact',
    ];

    /**
     * Adds translated labels for fields and relations contributed by this extension.
     *
     * @param array $labels
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['PaymentAccounts'] = _t(__CLASS__ . '.PAYMENT_ACCOUNTS', 'Payment accounts');
    }

    /**
     * Adds the PaymentAccounts GridField to the CMS edit form.
     *
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields): void
    {
        if (!$this->owner->exists()) {
            return;
        }

        /** @var GridField $paymentAccountsField */
        $paymentAccountsField = GridField::create(
            'PaymentAccounts',
            $this->owner->fieldLabel('PaymentAccounts'),
            $this->owner->PaymentAccounts()
        );
        $paymentAccountsField->setConfig(new GridFieldConfig_PaymentAccountsInContact());
        $fields->addFieldToTab('Root.PaymentAccounts', $paymentAccountsField);
    }

    /**
     * Adds a PaymentAccounts tab to the main tab set in the Contact CMS form.
     *
     * @param mixed $tabSet
     */
    public function updateMainTabSet(&$tabSet): void
    {
        if (!$this->owner->exists()) {
            return;
        }

        /** @var GridField $paymentAccountsField */
        $paymentAccountsField = GridField::create(
            'PaymentAccounts',
            '',
            $this->owner->PaymentAccounts()
        );
        $paymentAccountsField->setConfig(new GridFieldConfig_PaymentAccountsInContact());

        $paymentAccountsTab = new Tab(
            $this->owner->fieldLabel('PaymentAccounts'),
            $paymentAccountsField
        );
        $tabSet->FieldList()->add($paymentAccountsTab);
    }

}

