<?php

namespace Clesson\Contacts\Admins;

use Clesson\Contacts\Forms\GridFieldConfig_AddressesInContactManager;
use Clesson\Contacts\Forms\GridFieldConfig_AddressTypesInContactManager;
use Clesson\Contacts\Forms\GridFieldConfig_ContactsInContactManager;
use Clesson\Contacts\Forms\GridFieldConfig_ContactTagsInContactManager;
use Clesson\Contacts\Models\Address;
use Clesson\Contacts\Models\AddressType;
use Clesson\Contacts\Models\Contact;
use Clesson\Contacts\Models\ContactTag;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridFieldConfig;

/**
 * ModelAdmin for managing contacts, addresses, tags and address types.
 *
 * @package Clesson\Contacts
 * @subpackage Admins
 */
class ContactManager extends ModelAdmin
{

    /**
     * Allowed actions for this admin.
     */
    private static $allowed_actions = [
        'downloadVCard' => 'DOWNLOAD_CONTACTS',
    ];

    /**
     * The URL segment for this admin.
     */
    private static $url_segment = 'contacts';

    /**
     * The menu title for this admin.
     */
    private static $menu_title = 'Contacts';

    /**
     * The menu priority for this admin.
     */
    private static $menu_priority = -0.1;

    /**
     * The menu icon class for this admin.
     */
    private static $menu_icon_class = 'font-icon-address-card';

    /**
     * Disable the import form.
     *
     * @var bool
     */
    public $showImportForm = false;

    /**
     * The models managed by this admin.
     */
    private static $managed_models = [
        Contact::class,
        Address::class,
        ContactTag::class,
        AddressType::class,
    ];

    /**
     * Disable model importers.
     */
    private static $model_importers = [];

    /**
     * Returns the appropriate GridFieldConfig for the current model class.
     *
     * @return GridFieldConfig
     */
    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();

        $modelClass = $this->getModelClass();
        if ($modelClass === Contact::class) {
            $config = GridFieldConfig_ContactsInContactManager::create();
        } elseif ($modelClass === Address::class) {
            $config = GridFieldConfig_AddressesInContactManager::create();
        } elseif ($modelClass === ContactTag::class) {
            $config = GridFieldConfig_ContactTagsInContactManager::create();
        } elseif ($modelClass === AddressType::class) {
            $config = GridFieldConfig_AddressTypesInContactManager::create();
        }

        return $config;
    }

    /**
     * Adds an extra CSS class to the edit form based on the current model class.
     *
     * @param int|null $id
     * @param \SilverStripe\Forms\FieldList|null $fields
     * @return \SilverStripe\Forms\Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $sanitisedClass = lcfirst(ClassInfo::shortName($this->getModelClass()));
        $form->addExtraClass('contact-manager--' . $sanitisedClass);

        return $form;
    }

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
