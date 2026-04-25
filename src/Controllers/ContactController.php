<?php

namespace Clesson\Contacts\Controllers;

use Clesson\Contacts\Models\Contact;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;

/**
 * Controller actions for the Contact models.
 *
 * @package Clesson\Contacts
 * @subpackage Controllers
 */
class ContactController extends \SilverStripe\Control\Controller
{

    /**
     * @inheritdoc
     */
    private static $allowed_actions = [
        'vcard' => 'DOWNLOAD_CONTACTS'
    ];

    /**
     * Download contact as vCard file.
     * When using an iOS device < iOS 8 it will export as a .ics file because iOS devices don't support the default
     * .vcf files.
     */
    public function vcard(HTTPRequest $request)
    {
        if ($Slug = $this->getRequest()->param('Slug')) {
            if ($contact = Contact::get_by_slug($Slug)) {
                $card = $contact->getVCard();
                $card->download();
                exit;
            } else {
                $this->getResponse()->setStatusCode(404);
            }
        } else {
            $this->getResponse()->setStatusCode(400);
        }
        return $this->getResponse();
    }

}
