<?php

namespace Clesson\Contacts\Models;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Represents a type/category for addresses (e.g. home, work, billing).
 *
 * @property string $Name
 * @property string $Ukey
 *
 * @package Clesson\Contacts
 * @subpackage Models
 */
class AddressType extends DataObject
{

    /**
     * @inheritdoc
     */
    private static $table_name = 'Contacts_AddressType';

    /**
     * @inheritdoc
     */
    private static $default_sort = 'Name ASC';

    /**
     * @config
     * @var array
     */
    private static $default_types = [];

    /**
     * @inheritdoc
     */
    private static $general_search_field = 'Name';

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
        $labels['Addresses'] = _t(__CLASS__ . '.ADDRESSES', 'Addresses');
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
        return $fields;
    }

    /**
     * @inheritdoc
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
            $existingTag = static::get_by_ukey($filteredUkey);
            if ($existingTag && $existingTag->ID !== $this->ID) {
                $result->addError(_t(Form::class . '.FIELDMUSTBEUNIQUE', '{name} must be unique', ['name' => $this->fieldLabel('Ukey')]));

            }
        }

        return $result;
    }

    public static function get_by_ukey(string $ukey) : ?AddressType
    {
        $filteredUkey = static::normalize_ukey($ukey);
        return AddressType::get()->filter('Ukey', $filteredUkey)->first() ?: null;
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
        if (static::class === AddressType::class) {
            $defaultRecords = $this->config()->get('default_tags');
            foreach($defaultRecords as $ukey => $name) {
                $ukey = static::normalize_ukey($ukey);
                $tag = static::get_by_ukey($ukey);
                if (!$tag) {
                    $tag = AddressType::create();
                    $tag->Ukey = $ukey;
                    $tag->Name = $name;
                    $validationResult = $tag->validate();
                    if (!$validationResult->isValid()) {
                        DB::alteration_message('Could not create default address type ' . $ukey, 'error');
                        continue;
                    }
                    $tag->write();
                    DB::alteration_message('Addresstype ' . $tag->Ukey . ' created', 'created');
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
