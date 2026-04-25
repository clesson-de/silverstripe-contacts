<?php

namespace Clesson\Contacts\Models;

use ArgumentCountError;
use CommerceGuys\Addressing\Address as CommerceAddress;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use Throwable;
use Psr\Log\LoggerInterface;

/**
 * Represents a physical address associated with contacts.
 *
 * @property string $Name
 * @property string $AddressLine1
 * @property string $AddressLine2
 * @property string $PostalCode
 * @property string $City
 * @property string $Region
 * @property string $CountryCode
 * @property string $Summary
 * @property-read string $Country The full localised country name derived from CountryCode.
 *
 * @package Clesson\Contacts
 * @subpackage Models
 */
class Address extends DataObject
{
    /**
     * @inheritdoc
     */
    private static $table_name = 'Contacts_Address';

    /**
     * @inheritdoc
     */
    private static $default_sort = 'CountryCode ASC, Region ASC, City ASC, PostalCode ASC, AddressLine1 ASC';

    /**
     * @inheritdoc
     */
    private static $general_search_field = 'Name';

    /**
     * @inheritdoc
     */
    private static $db = [
        'Name' => 'Varchar(255)',   // Optional name
        'AddressLine1' => 'Varchar(150)',
        'AddressLine2' => 'Varchar(150)',
        'PostalCode' => 'Varchar(20)',
        'City' => 'Varchar(100)',
        'Region' => 'Varchar(100)',
        'CountryCode' => 'Varchar(2)',
        'Summary' => 'Text',           // Complete summary for search queries and overviews
    ];


    /**
     * @inheritdoc
     */
    private static $summary_fields = [
        'Summary',
        'Created',
        'LastEdited',
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Name'] = _t(__CLASS__ . '.NAME', 'Name');
        $labels['AddressLine1'] = _t(__CLASS__ . '.ADDRESS_LINE_1', 'Address line 1');
        $labels['AddressLine2'] = _t(__CLASS__ . '.ADDRESS_LINE_2', 'Address line 2');
        $labels['City'] = _t(__CLASS__ . '.CITY', 'City');
        $labels['PostalCode'] = _t(__CLASS__ . '.POSTAL_CODE', 'Postal code');
        $labels['Region'] = _t(__CLASS__ . '.REGION', 'Region');
        $labels['CountryCode'] = _t(__CLASS__ . '.COUNTRY_CODE', 'Country');
        $labels['Country'] = _t(__CLASS__ . '.COUNTRY_CODE', 'Country');
        $labels['Summary'] = _t(__CLASS__ . '.SUMMARY', 'Summary');
        $labels['Created'] = _t('Clesson\Contacts\Common.CREATED', 'Created');
        $labels['LastEdited'] = _t('Clesson\Contacts\Common.LASTEDITED', 'Last edited');
        $labels['ID'] = _t('Clesson\Contacts\Common.ID', 'ID');
        return $labels;
    }

    /**
     * Get the full country name based on the CountryCode.
     *
     * @return string|null The full country name or null if CountryCode is not set.
     */
    public function getCountry(): ?string
    {
        $countryCode = strtoupper(trim((string)$this->CountryCode));
        if ($countryCode === '') {
            return null;
        }
        $countryRepository = new CountryRepository();
        $country = $countryRepository->get($countryCode);
        $locale = i18n::get_locale();
        return $country->getName($locale);
    }

    /**
     * Validates the record before writing.
     * No required fields enforced by default.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        return $result;
    }

    /**
     * Sets default values when a new Address record is created.
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'Name',
            'AddressLine1',
            'AddressLine2',
            'PostalCode',
            'City',
            'Region',
            'CountryCode',
            'Summary',
        ]);

        /** @var TextField $nameField */
        $nameField = TextField::create('Name', $this->fieldLabel('Name'));
        $fields->addFieldToTab('Root.Main', $nameField);

        /** @var TextField $addressLine1Field */
        $addressLine1Field = TextField::create('AddressLine1', $this->fieldLabel('AddressLine1'));
        $fields->addFieldToTab('Root.Main', $addressLine1Field);

        /** @var TextField $addressLine2Field */
        $addressLine2Field = TextField::create('AddressLine2', $this->fieldLabel('AddressLine2'));
        $fields->addFieldToTab('Root.Main', $addressLine2Field);

        /** @var DropdownField $countryCodeField */
        $countryCodeField = DropdownField::create('CountryCode', $this->fieldLabel('CountryCode'), static::getCountryOptions());
        $countryCodeField->setEmptyString('');
        $fields->addFieldToTab('Root.Main', $countryCodeField);

        // Check if subdivisions are available for the selected country
        $subdivisionOptions = static::getSubdivisionOptions($this->CountryCode ?? '');

        if (!empty($subdivisionOptions)) {
            // Country has subdivisions - use dropdown
            /** @var DropdownField $regionField */
            $regionField = DropdownField::create('Region', $this->fieldLabel('Region'), $subdivisionOptions);
            $regionField->setEmptyString(' ');
            $regionField->addExtraClass('address-region-field');
            $regionField->setAttribute('data-depends-on', 'CountryCode');
            $fields->addFieldToTab('Root.Main', $regionField);
        } else {
            // Country has no subdivisions - use text field for manual input
            /** @var TextField $regionField */
            $regionField = TextField::create('Region', $this->fieldLabel('Region'));
            $regionField->addExtraClass('address-region-field-text');
            $regionField->setAttribute('data-depends-on', 'CountryCode');
            $fields->addFieldToTab('Root.Main', $regionField);
        }

        /** @var TextField $postalCodeField */
        $postalCodeField = TextField::create('PostalCode', '');
        $postalCodeField->setAttribute('placeholder', $this->fieldLabel('PostalCode'));

        /** @var TextField $cityField */
        $cityField = TextField::create('City', '');
        $cityField->setAttribute('placeholder', $this->fieldLabel('City'));

        /** @var FieldGroup $postalCodeCityGroup */
        $postalCodeCityGroup = FieldGroup::create(
            $this->fieldLabel('PostalCode') . ' & ' . $this->fieldLabel('City'),
            $postalCodeField,
            $cityField
        );
        $fields->addFieldToTab('Root.Main', $postalCodeCityGroup);

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $summary = '';
        if ($this->CountryCode) {
            $addressFormatRepository = new AddressFormatRepository();
            $countryRepository = new CountryRepository();
            $subdivisionRepository = new SubdivisionRepository();
            $formatter = new DefaultFormatter(
                $addressFormatRepository,
                $countryRepository,
                $subdivisionRepository
            );
            $address = new CommerceAddress(
                $this->CountryCode,
                $this->Region ?? '',
                $this->City ?? '',
                '',
                $this->PostalCode ?? '',
                '',
                $this->AddressLine1 ?? '',
                $this->AddressLine2 ?? '',
                '',
                $this->Name ?? ''
            );
            $summary = $formatter->format(
                $address,
                [
                    'html' => false,
                    'locale' => i18n::get_locale()
                ]
            );
            $summary = str_replace(PHP_EOL, ', ', trim($summary));
        }

        $this->Summary = $summary;
    }

    public static function getCountryOptions(): array
    {
        $repo = new CountryRepository();
        $locale = i18n::get_locale() ?: 'en';
        $list = [];

        foreach ($repo->getAll() as $code => $country) {
            $name = $code;
            if (method_exists($country, 'getName')) {
                try {
                    $name = $country->getName($locale);
                } catch (ArgumentCountError $e) {
                    $name = $country->getName();
                } catch (Throwable $e) {
                    $name = $code;
                }
            } elseif (method_exists($country, 'getNames')) {
                $names = $country->getNames();
                if (is_array($names)) {
                    $name = $names[$locale] ?? $names['en'] ?? (reset($names) ?: $code);
                }
            }
            $list[$code] = $name ?: $code;
        }

        // Alphabetisch nach Wert (ländertitel) sortieren, Schlüssel (Ländercode) erhalten
        natcasesort($list);

        return $list;
    }


    public static function getSubdivisionOptions(string $countryIso, ?string $locale = null): array
    {
        $countryIso = strtoupper(trim((string)$countryIso));
        if ($countryIso === '') {
            return [];
        }

        // Normalize locale (e.g. 'de_DE' → 'de', 'de-DE' → 'de', 'en_US' → 'en')
        if ($locale) {
            $locale = strtolower($locale);
            $locale = str_replace('_', '-', $locale);
            $localeParts = explode('-', $locale);
            $locale = $localeParts[0];
        } else {
            $currentLocale = i18n::get_locale() ?: 'en';
            $localeParts = explode('_', $currentLocale);
            $locale = strtolower($localeParts[0]);
        }

        $repo = new SubdivisionRepository();
        $list = [];

        try {
            // Use getList() which is specifically designed for dropdown lists
            // It returns an associative array of code => localized name
            $list = $repo->getList([$countryIso], $locale);

            // If empty, the country might not have subdivisions or locale is wrong
            if (empty($list)) {
                // Try with fallback to English
                $list = $repo->getList([$countryIso], 'en');
            }

            // Sort alphabetically by name
            if (!empty($list)) {
                natcasesort($list);
            }
        } catch (Throwable $e) {
            // Log error for debugging
            Injector::inst()->get(LoggerInterface::class)->error(
                'Failed to load subdivisions for country ' . $countryIso,
                [
                    'exception' => $e->getMessage(),
                    'locale' => $locale,
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        return $list;
    }

    public function getTitle(): string
    {
        $text = [];
        if ($this->AddressLine1) {
            $text[] = $this->AddressLine1;
        }
        if ($this->AddressLine2) {
            $text[] = $this->AddressLine2;
        }
        if ($this->PostalCode && $this->City) {
            $text[] = $this->PostalCode . ' ' . $this->City;
        } else if ($this->City) {
            $text[] = $this->City;
        }
        if ($Region = $this->Region) {
            $text[] = $Region;
        }
        if ($Country = $this->Country) {
            $text[] = $Country;
        }
        return implode(', ', $text);
    }

}
