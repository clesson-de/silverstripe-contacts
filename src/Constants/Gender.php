<?php

namespace Clesson\Silverstripe\Contacts\Constants;

/**
 * Definition of genders.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Constants
 */
class Gender
{

    /**
     *
     */
    public const NOT_KNOWN = 0;
    public const MALE = 1;
    public const FEMALE = 2;
    public const NOT_APPLICABLE = 9;

    /**
     * @return array
     */
    public static function options(): array
    {
        return [
            self::NOT_KNOWN => _t(__CLASS__ . '.NOT_KNOWN', 'Not known'),
            self::MALE => _t(__CLASS__ . '.MALE', 'Male'),
            self::FEMALE => _t(__CLASS__ . '.FEMALE', 'Female'),
            self::NOT_APPLICABLE => _t(__CLASS__ . '.NOT_APPLICABLE', 'Not applicable'),
        ];
    }

    /**
     * @param int $value
     * @return string
     */
    public static function label(int $value): string
    {
        $options = self::options();
        return isset($options[$value]) ? $options[$value] : (string)$value;
    }

    /**
     * @param int $value
     * @return string|null
     */
    public static function salutation(int $value): ?string
    {
        if ($value === self::MALE) {
            return _t(__CLASS__ . '.SALUTATION_MALE', 'Mr.');
        } else if ($value === self::FEMALE) {
            return _t(__CLASS__ . '.SALUTATION_FEMALE', 'Mrs.');
        }
        return null;
    }

    /**
     * @param int $value
     * @return string
     */
    public static function icon(int $value): string
    {
        $icons = [
            self::MALE => '<span class="gender-male">M</span>',
            self::FEMALE => '<span class="gender-female">F</span>',
            self::NOT_APPLICABLE => '<span class="gender-not-applicable">n/a</span>',
        ];
        return isset($icons[$value]) ? $icons[$value] : '';
    }

    /**
     * Returns the gender symbol (♂ or ♀).
     *
     * @param int $value
     * @return string
     */
    public static function symbol(int $value): string
    {
        if ($value === self::MALE) {
            return '♂';
        }

        if ($value === self::FEMALE) {
            return '♀';
        }

        return '';
    }

}
