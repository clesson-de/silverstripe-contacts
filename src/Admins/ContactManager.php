<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Admins;

use Clesson\Silverstripe\Contacts\Models\Contact;
use Clesson\Silverstripe\Contacts\Models\ContactConfig;
use SilverStripe\Admin\SingleRecordAdmin;
use SilverStripe\Control\HTTPRequest;

/**
 * SingleRecordAdmin for managing the central contacts configuration.
 *
 * All managed models (Contact, Address, ContactTag, AddressType) are rendered
 * as GridFields inside the ContactConfig singleton model's getCMSFields().
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Admins
 */
class ContactManager extends SingleRecordAdmin
{
    /**
     * Allowed actions for this admin.
     */
    private static array $allowed_actions = [
        'downloadVCard' => 'DOWNLOAD_CONTACTS',
    ];

    /**
     * @inheritdoc
     */
    private static string $url_segment = 'contacts';

    /**
     * @inheritdoc
     */
    private static string $menu_title = 'Contacts';

    /**
     * @inheritdoc
     */
    private static float $menu_priority = -0.1;

    /**
     * @inheritdoc
     */
    private static string $menu_icon_class = 'font-icon-address-card';

    /**
     * @inheritdoc
     */
    private static string $model_class = ContactConfig::class;

    /**
     * Enables exporting vCards from the current Contact inside the detail view.
     *
     * @param HTTPRequest $request
     * @return void
     */
    public function downloadVCard(HTTPRequest $request): void
    {
        if ($model = Contact::get_by_id($request->getVar('ID'))) {
            $this->redirect($model->getvCardLink());
        }

        $this->redirectBack();
    }

}
