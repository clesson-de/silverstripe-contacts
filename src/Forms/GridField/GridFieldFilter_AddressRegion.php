<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Forms\GridField;

use Clesson\Silverstripe\Geocoding\Models\Address;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Model\List\SS_List;

/**
 * GridField component that renders a region dropdown filter for addresses.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms\GridField
 */
class GridFieldFilter_AddressRegion implements GridField_HTMLProvider, GridField_DataManipulator, GridField_ActionProvider
{
    use Injectable;

    /**
     * State key used to store the selected region.
     */
    private const STATE_KEY = 'GridFieldFilter_AddressRegion';

    /**
     * Returns the action names handled by this component.
     *
     * @param GridField $gridField
     * @return array<string>
     */
    public function getActions($gridField): array
    {
        return ['filteraddressbyregion'];
    }

    /**
     * Handles the filter action by storing the selected value in GridField state.
     *
     * @param GridField $gridField
     * @param string $actionName
     * @param array $arguments
     * @param array $data
     * @return void
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data): void
    {
        if ($actionName !== 'filteraddressbyregion') {
            return;
        }

        $state = $gridField->State;
        $fieldName = static::STATE_KEY;
        $state->$fieldName = $data['gridfield_address_region_filter'] ?? '';

        if (isset($state->GridFieldPaginator)) {
            $state->GridFieldPaginator->currentPage = 1;
        }
    }

    /**
     * Returns the HTML dropdown rendered in the before fragment.
     *
     * @param GridField $gridField
     * @return array<string, string>
     */
    public function getHTMLFragments($gridField): array
    {
        $options = $this->buildOptions();
        $selected = $this->getSelectedValue($gridField);

        $allLabel = _t(__CLASS__ . '.ALL_REGIONS', 'All regions');
        $dropdownOptions = ['' => $allLabel] + $options;

        /** @var DropdownField $dropdownField */
        $dropdownField = DropdownField::create('gridfield_address_region_filter', '', $dropdownOptions);
        $dropdownField->setValue($selected);
        $dropdownField->addExtraClass('no-change-track dropdown');
        $dropdownField->setAttribute('style', 'min-width:150px;appearance:auto;-webkit-appearance:menulist;');

        /** @var GridField_FormAction $actionField */
        $actionField = GridField_FormAction::create(
            $gridField,
            'filteraddressbyregion',
            _t(__CLASS__ . '.LABEL', 'Region'),
            'filteraddressbyregion',
            null
        );
        $actionField->setAttribute('style', 'display:none;');
        $actionField->addExtraClass('grid-field__address-region-filter-action');

        $dropdownField->setAttribute(
            'onchange',
            'this.closest(\'.grid-field\').querySelector(\'.grid-field__address-region-filter-action\').click();'
        );

        $html = '<div class="contacts-address-region-filter" style="display:inline-flex;align-items:center;gap:8px;padding:4px 0 8px;">'
            . $dropdownField->forTemplate()
            . $actionField->forTemplate()
            . '</div>';

        return ['before' => $html];
    }

    /**
     * Applies the region filter to the DataList.
     *
     * @param GridField $gridField
     * @param SS_List $dataList
     * @return SS_List
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList): SS_List
    {
        $selected = $this->getSelectedValue($gridField);

        if ($selected === '') {
            return $dataList;
        }

        return $dataList->filter('Region', $selected);
    }

    /**
     * Reads the selected value from GridField state.
     *
     * @param GridField $gridField
     * @return string
     */
    private function getSelectedValue(GridField $gridField): string
    {
        $state = $gridField->State;
        $fieldName = static::STATE_KEY;

        return (string) ($state->$fieldName ?? '');
    }

    /**
     * Builds the dropdown options from existing address regions.
     *
     * @return array<string, string>
     */
    private function buildOptions(): array
    {
        $regions = Address::get()
            ->filter('Region:not', '')
            ->columnUnique('Region');

        sort($regions);

        $result = [];
        foreach ($regions as $region) {
            $result[$region] = $region;
        }

        return $result;
    }
}

