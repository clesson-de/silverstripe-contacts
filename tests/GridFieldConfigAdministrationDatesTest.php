<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Tests;

use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_AddressTypesInContactManager;
use Clesson\Silverstripe\Contacts\Forms\GridFieldConfig_ContactTagsInContactManager;
use Clesson\Silverstripe\Contacts\Models\ContactTag;
use Clesson\Silverstripe\Forms\GridField\GridFieldRecordDates;
use Clesson\Silverstripe\Geocoding\Models\AddressType;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Model\List\ArrayList;

/**
 * Covers the administration lists' combined timestamp columns.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Tests
 */
class GridFieldConfigAdministrationDatesTest extends SapphireTest
{
    /** Both configurations replace separate dates and retain timestamp sorting. */
    public function testCombinedDateColumns(): void
    {
        $configs = [
            ContactTag::class => GridFieldConfig_ContactTagsInContactManager::create(),
            AddressType::class => GridFieldConfig_AddressTypesInContactManager::create(),
        ];
        foreach ($configs as $model => $config) {
            $grid = GridField::create('Records', '', ArrayList::create(), $config);
            $grid->setModelClass($model);
            self::assertContains(GridFieldRecordDates::COLUMN, $grid->getColumns());
            self::assertNotContains('Created', $grid->getColumns());
            self::assertNotContains('LastEdited', $grid->getColumns());
            self::assertSame('LastEdited', $config->getComponentByType(GridFieldSortableHeader::class)->getFieldSorting()[GridFieldRecordDates::COLUMN]);
        }
    }
}
