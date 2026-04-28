<?php

namespace Clesson\Silverstripe\Contacts\Helpers;

use DateTime;

/**
 * Helper class for date-related utility methods.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Helpers
 */
class DateHelper
{

    /**
     * Determines the human-readable duration between two date strings.
     *
     * @param string|null $from
     * @param string|null $to
     * @return string
     */
    public static function duration($from, $to): string
    {
        $from = $from ? strtotime($from) : null;
        $to = $from && $to ? strtotime($to) : ($from ? time() : null);
        if (!is_null($from) && !is_null($to)) {
            $fromDT = new DateTime();
            $fromDT->setTimestamp($from);
            $toDT = new DateTime();
            $toDT->setTimestamp($to);

            $years = $fromDT->diff($toDT)->y;
            if ($years) {
                return _t('Duration.Years', '{n} years', ['n' => $years]);
            }

            $months = $fromDT->diff($toDT)->m;
            if ($months) {
                return _t('Duration.Months', '{n} months', ['n' => $months]);
            }

            $days = $fromDT->diff($toDT)->d;
            if ($days) {
                return _t('Duration.Days', '{n} days', ['n' => $days]);
            }

        }
        return '';
    }

}
