<?php

namespace Clesson\Contacts\Models;

use Clesson\Contacts\Forms\GridFieldConfig_ContactsInContactTag;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Core\Validation\ValidationResult;

/**
 * Represents a tag that can be assigned to contacts.
 *
 * @property string $Name
 * @property string $Ukey
 * @method \SilverStripe\ORM\ManyManyListContact[] Contacts()
 *
 * @package Clesson\Contacts
 * @subpackage Models
 */
class ContactTag extends DataObject
{

    /**
     * @inheritdoc
     */
    private static $table_name = 'Contacts_ContactTag';

    /**
     * @inheritdoc
     */
    private static $default_sort = 'Name ASC';

    /**
     * @config
     * @var array
     */
    private static $default_tags = [];

    /**
     * @inheritdoc
     */
    private static $db = [
        'Name' => 'Varchar',
        'Ukey' => 'Varchar',
    ];

    /**
     * @inheritdoc
     */
    private static $belongs_many_many = [
        'Contacts' => Contact::class,
    ];

    /**
     * @inheritdoc
     */
    private static $summary_fields = [
        'Name',
        'Created',
        'LastEdited',
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Title'] = _t(__CLASS__ . '.TITLE', 'Title');
        $labels['Name'] = _t(__CLASS__ . '.NAME', 'Name');
        $labels['Ukey'] = _t(__CLASS__ . '.UKEY', 'Ukey');
        $labels['Contacts'] = _t(__CLASS__ . '.CONTACTS', 'Contacts');
        $labels['Created'] = _t('Clesson\Contacts\Common.CREATED', 'Created');
        $labels['LastEdited'] = _t('Clesson\Contacts\Common.LASTEDITED', 'Last edited');
        $labels['ID'] = _t('Clesson\Contacts\Common.ID', 'ID');
        return $labels;
    }

    /**
     * @inheritdoc
     */
    public function  getCMSFields()
    {
        $fields = new FieldList();

        $nameField = TextField::create('Name', $this->fieldLabel('Name'));
        $fields->add($nameField);

        $ukeyField = TextField::create('Ukey', $this->fieldLabel('Ukey'));
        $fields->add($ukeyField);

        $contactsField = GridField::create('Contacts', $this->fieldLabel('Contacts'), $this->Contacts());
        $contactsField->setConfig(new GridFieldConfig_ContactsInContactTag());
        $fields->add($contactsField);

        return $fields;
    }

    /**
     * Validate that Name and Ukey are present.
     *
     * Ukey wird dabei mit dem gleichen Filter geprüft wie in onBeforeWrite,
     * damit Leerzeichen-only Eingaben, die später normalisiert würden, nicht fälschlich fehlschlagen.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (trim((string)$this->Name) === '') {
            $result->addError(_t(Form::class . '.FIELDISREQUIRED', '{name} is required', ['name' => $this->fieldLabel('Name')]));
        }

        $filteredUkey = static::normalize_ukey($this->Ukey);
        if ($filteredUkey === '') {
            $result->addError(_t(Form::class . '.FIELDISREQUIRED', '{name} is required', ['name' => $this->fieldLabel('Ukey')]));
        } else {
            $existingTag = ContactTag::get_by_ukey($filteredUkey);
            if ($existingTag && $existingTag->ID !== $this->ID) {
                $result->addError(_t(Form::class . '.FIELDMUSTBEUNIQUE', '{name} must be unique', ['name' => $this->fieldLabel('Ukey')]));

            }
        }

        return $result;
    }

    /**
     * Get a ContactTag by its Ukey.
     *
     * @param string $ukey The Ukey of the ContactTag.
     * @return ContactTag|null The ContactTag if found, null otherwise.
     */
    public static function get_by_ukey(string $ukey) : ?ContactTag
    {
        return ContactTag::get()->where(['Ukey' => $ukey])->first();
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->Ukey = static::normalize_ukey($this->Ukey);
    }

    /**
     * @return void
     * @throws \SilverStripe\Core\Validation\ValidationException
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (static::class === ContactTag::class) {
            $defaultRecords = $this->config()->get('default_tags');
            foreach($defaultRecords as $ukey => $name) {
                $ukey = static::normalize_ukey($ukey);
                $tag = ContactTag::get_by_ukey($ukey);
                if (!$tag) {
                    $tag = ContactTag::create();
                    $tag->Ukey = $ukey;
                    $tag->Name = $name;
                    $validationResult = $tag->validate();
                    if (!$validationResult->isValid()) {
                        DB::alteration_message('Could not create default contact tag ' . $ukey, 'error');
                        continue;
                    }
                    $tag->write();
                    DB::alteration_message('Contact tag ' . $tag->Ukey . ' created', 'created');
                }
            }
        }
    }

    /**
     * Sanitize the Ukey by filtering and formatting it.
     *
     * @param string $ukey The Ukey to sanitize.
     * @return string The sanitized Ukey.
     */
    public static function normalize_ukey(string $ukey): string
    {
        $filter = URLSegmentFilter::create();
        $sanitizedUkey = $filter->filter($ukey);
        return strtoupper(trim($sanitizedUkey, '_-'));
    }
}
