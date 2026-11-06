<?php

namespace Clesson\Silverstripe\Contacts\Forms;

use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldAction_BirthdayExport;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldAction_BirthdayPdfExport;
use Clesson\Silverstripe\Contacts\Forms\GridField\GridFieldAction_PhoneListExport;
use Clesson\Silverstripe\Contacts\Models\Company;
use Clesson\Silverstripe\Contacts\Models\Contact;
use Clesson\Silverstripe\Contacts\Models\ContactTag;
use Clesson\Silverstripe\Contacts\Models\Employee;
use Clesson\Silverstripe\Contacts\Models\Person;
use Clesson\Silverstripe\Constants\GridFieldToolbarLayout;
use Clesson\Silverstripe\Forms\GridField\GridFieldSearch;
use Clesson\Silverstripe\Forms\GridField\GridFieldToolbar;
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Forms\GridField\GridFieldActionMenu;
use Clesson\Silverstripe\Forms\GridField\GridField_ButtonFilter;
use Clesson\Silverstripe\Forms\GridField\GridField_CharFilter;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldLazyLoader;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\ORM\FieldType\DBField;

/**
 * GridField configuration for displaying Contact records in the ContactManager.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms
 */
class GridFieldConfig_ContactsInContactManager extends GridFieldConfig
{

    /**
     * Initialises the GridField configuration with all required components
     * and configures the display columns for Contact records.
     *
     * @param int|null $itemsPerPage
     * @param bool|null $showPagination
     * @param bool|null $showAdd
     */
    public function __construct($itemsPerPage = null, $showPagination = null, $showAdd = null)
    {
        parent::__construct();

        $toolbar = GridFieldToolbar::install($this);
        $classFilterSlot = $toolbar->addItem('contact-types');
        $this->addSearch($toolbar);
        $tagFilterSlot = $toolbar->addItem('contact-tags');
        $charFilterSlot = $toolbar->addFullWidthItem('alphabet');
        $this->addComponent(GridFieldLazyLoader::create());
        $this->addComponent(GridFieldToolbarHeader::create());
        $this->addComponent(GridFieldSortableHeader::create()->setFieldSorting([
            GridFieldRecordDates::COLUMN => 'LastEdited',
        ]));
        $this->addComponent($dataColumns = GridFieldDataColumns::create());
        $this->addComponent(GridFieldRecordDates::create());
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());
        $this->addComponent(GridField_ActionMenu::create());
        $this->addComponent(GridFieldPageCount::create('toolbar-header-right'));
        $this->addComponent(GridFieldPaginator::create($itemsPerPage));
        $this->addComponent(GridFieldDetailForm::create(null, $showPagination, $showAdd));
        $this->addDownloadMenu();
        $this->addComponent(new GridField_CharFilter($charFilterSlot->getFragment(), 'SortingName', 'A-Z|0-9'));

        /** @var GridField_ButtonFilter $classFilter */
        $classFilter = new GridField_ButtonFilter($classFilterSlot->getFragment(), 'ClassName', [
            Company::class => _t(Company::class . '.PLURALNAME', 'Companies'),
            Person::class => _t(Person::class . '.PLURALNAME', 'Persons'),
            Employee::class => _t(Employee::class . '.PLURALNAME', 'Employees'),
        ]);
        $classFilter->setMultiselect(true);
        $classFilter->setSelectedValues([Company::class, Person::class]);
        $this->addComponent($classFilter);

        /** @var GridField_ButtonFilter $tagFilter */
        $tagFilter = new GridField_ButtonFilter($tagFilterSlot->getFragment(), 'Tags.ID', $this->buildTagFilterValues());
        $tagFilter->setMultiselect(true);
        $this->addComponent($tagFilter);

        /** @var GridFieldAddNewDropdown $addButton */
        $addButton = GridFieldAddNewDropdown::create('buttons-before-right');
        $addButton->setClasses([
            Company::class,
            Person::class,
            Employee::class,
        ]);
        $this->addComponent($addButton);

        $dataColumns->setDisplayFields([
            'Name' => [
                'title' => _t(Contact::class . '.NAME', 'Name'),
                'callback' => function ($record, $column, $grid) {
                    $typeIconClass = match ($record->ClassName) {
                        Company::class  => 'font-icon-home',
                        Employee::class => 'font-icon-sitemap',
                        default         => 'font-icon-torso',
                    };
                    $typeIconTitle = match ($record->ClassName) {
                        Company::class  => _t(Company::class . '.SINGULARNAME', 'Company'),
                        Employee::class => _t(Employee::class . '.SINGULARNAME', 'Employee'),
                        default         => _t(Person::class . '.SINGULARNAME', 'Person'),
                    };
                    $colStyle   = 'display:inline-flex;align-items:center;gap:5px;';
                    $iconStyle  = 'display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;flex-shrink:0;';
                    $contactIcon = $this->getContactIcon($record);

                    $typeIconHtml    = '<span class="' . $typeIconClass . '" title="' . htmlspecialchars($typeIconTitle, ENT_QUOTES, 'UTF-8') . '" style="' . $iconStyle . 'font-size:1.1em;color:#6c757d;"></span>';
                    $contactIconHtml = $contactIcon !== ''
                        ? '<span style="' . $iconStyle . '">' . $contactIcon . '</span>'
                        : '<span style="' . $iconStyle . '"></span>';

                    $nameText = '<span>' . htmlspecialchars((string) $record->Name, ENT_QUOTES, 'UTF-8');
                    if ($address = $record->Address) {
                        $nameText .= '<br><small style="color:#6c757d">' . htmlspecialchars((string) $address->Title, ENT_QUOTES, 'UTF-8') . '</small>';
                    }
                    $nameText .= '</span>';

                    return DBField::create_field('HTMLFragment', '<span style="' . $colStyle . '">' . $typeIconHtml . $contactIconHtml . $nameText . '</span>');
                },
            ],
            'Email' => [
                'title' => _t(__CLASS__ . '.EMAIL', 'Email'),
                'callback' => fn (Contact $record) => $this->emailColumn($record),
            ],
            'Phone' => [
                'title' => _t(Contact::class . '.PHONE', 'Phone'),
                'callback' => fn (Contact $record) => $this->phoneColumn($record),
            ],
        ]);

        $this->extend('updateConfig');
    }

    /** Searches contact identity and the linked account/address without replacing existing filters. */
    private function addSearch(GridFieldToolbar $toolbar): void
    {
        $slot = $toolbar->addItem('search', GridFieldToolbarLayout::LEFT, GridFieldToolbarLayout::GROW);
        $this->addComponent(GridFieldSearch::create([
            'Name' => _t(Contact::class . '.NAME', 'Name'),
            'Account.Email' => _t(__CLASS__ . '.EMAIL', 'Email'),
            'Address.Summary' => _t(__CLASS__ . '.SEARCH_ADDRESS', 'Address'),
        ], $slot->getFragment()));
    }

    /** Renders the linked member's email address as a safe mail link. */
    private function emailColumn(Contact $record): DBField
    {
        if (!$record->AccountID) {
            return DBField::create_field('HTMLFragment', '');
        }

        $email = trim((string) $record->Account()->Email);
        if ($email === '') {
            return DBField::create_field('HTMLFragment', '');
        }

        $escapedEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        return DBField::create_field('HTMLFragment', '<a href="mailto:' . $escapedEmail . '">' . $escapedEmail . '</a>');
    }

    /** Renders the preferred phone number as a safe telephone link. */
    private function phoneColumn(Contact $record): DBField
    {
        $mobile = trim((string) $record->Mobile);
        $phone = $mobile !== '' ? $mobile : trim((string) $record->Phone);
        if ($phone === '') {
            return DBField::create_field('HTMLFragment', '');
        }

        $tel = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        $link = '<a href="tel:' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</a>';
        if ($mobile !== '') {
            $label = htmlspecialchars(_t(__CLASS__ . '.MOBILE_LABEL', 'Mobile'), ENT_QUOTES, 'UTF-8');
            $link .= '<br><small>' . $label . '</small>';
        }

        return DBField::create_field('HTMLFragment', $link);
    }

    /**
     * Returns the contact icon, using linked avatars as fallbacks for employees.
     *
     * @param Contact $record
     * @return string
     */
    private function getContactIcon(Contact $record): string
    {
        if (!($record instanceof Employee) || $this->hasAvatar($record)) {
            return trim((string) $record->Icon);
        }

        $person = $record->PersonID ? $record->Person() : null;
        if ($person && $this->hasAvatar($person)) {
            return trim((string) $person->Icon);
        }

        $company = $record->CompanyID ? $record->Company() : null;
        if ($company && $this->hasAvatar($company)) {
            return trim((string) $company->Icon);
        }

        return trim((string) $record->Icon);
    }

    /**
     * Checks whether a contact has an existing avatar image.
     *
     * @param Contact $contact
     * @return bool
     */
    private function hasAvatar(Contact $contact): bool
    {
        return $contact->AvatarID > 0 && $contact->Avatar()->exists();
    }

    /** Keeps download handlers registered while presenting their links in one menu. */
    private function addDownloadMenu(): void
    {
        $this->addComponent(GridFieldAction_PhoneListExport::create(true));
        $this->addComponent(GridFieldAction_BirthdayExport::create(true));
        $this->addComponent(GridFieldAction_BirthdayPdfExport::create(true));
        $menu = GridFieldActionMenu::create('buttons-before-right', 'downloads');
        $menu->setTitle(_t(__CLASS__ . '.DOWNLOADS', 'Downloads'));
        $menu->setIcon('down-circled');
        $menu->addProvider(GridFieldAction_PhoneListExport::class);
        $menu->addProvider(GridFieldAction_BirthdayExport::class);
        $menu->addProvider(GridFieldAction_BirthdayPdfExport::class);
        $this->addComponent($menu);
    }

    /**
     * Builds the associative array of tag filter values from the database.
     * Keys are tag IDs (as strings), values are the translated tag names.
     *
     * @return array<string, string>
     */
    private function buildTagFilterValues(): array
    {
        $values = [];
        foreach (ContactTag::get() as $tag) {
            $values[(string) $tag->ID] = (string) $tag->Name;
        }

        return $values;
    }
}
