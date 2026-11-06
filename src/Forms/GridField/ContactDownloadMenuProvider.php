<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Forms\GridField;

use Clesson\Silverstripe\Contacts\Models\Contact;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuItem;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\GridField;

/**
 * Shares menu presentation and contact access checks across the download components.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms
 */
trait ContactDownloadMenuProvider
{
    /** Allows existing integrations to retain their standalone export buttons. */
    public function __construct(private bool $menuOnly = false)
    {
    }

    /** Offers the original download route with the current GridField filter state. */
    public function getMenuItems(GridField $grid): array
    {
        if (!$this->canDownload()) {
            return [];
        }
        return [GridFieldMenuItem::link(
            self::URL_SEGMENT,
            _t(__CLASS__ . '.BUTTON_LABEL', self::DOWNLOAD_LABEL),
            $grid->addAllStateToUrl($grid->Link(self::URL_SEGMENT)),
            download: true,
            icon: self::DOWNLOAD_ICON
        )];
    }

    /** Uses the same contact access permission as the managed records. */
    private function canDownload(): bool
    {
        return (bool)Contact::singleton()->canView();
    }

    /** Rechecks access at the endpoint, independently of menu visibility. */
    private function requireDownloadPermission(): void
    {
        if (!$this->canDownload()) {
            throw new HTTPResponse_Exception('Contact download access denied.', 403);
        }
    }
}
