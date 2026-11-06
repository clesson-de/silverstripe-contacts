<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Forms\GridField;

use Clesson\Silverstripe\Contacts\Models\Person;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuItemProvider;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Model\List\SS_List;

/**
 * GridField action component that exports all Person records with a birthday
 * from the currently filtered contact list as an Excel file (.xlsx).
 *
 * Pagination is ignored — all matching records across all pages are exported.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms\GridField
 */
class GridFieldAction_BirthdayExport implements GridField_HTMLProvider, GridField_URLHandler, GridFieldMenuItemProvider
{
    use Injectable;
    use ContactDownloadMenuProvider;

    /** English fallback for the existing translated download label. */
    private const DOWNLOAD_LABEL = 'Birthdays (Excel)';

    /** Icon used by the download menu entry. */
    private const DOWNLOAD_ICON = 'down-circled';

    /**
     * Fragment in which the export button is rendered.
     */
    private const FRAGMENT = 'buttons-before-right';

    /**
     * URL segment for the export handler.
     */
    private const URL_SEGMENT = 'export-birthdays';

    /**
     * Returns the HTML fragment containing the export button.
     *
     * @param GridField $gridField
     * @return array<string, string>
     */
    public function getHTMLFragments($gridField): array
    {
        if ($this->menuOnly || !$this->canDownload()) {
            return [];
        }
        $url   = $gridField->Link(self::URL_SEGMENT);
        $label = htmlspecialchars(
            _t(__CLASS__ . '.BUTTON_LABEL', 'Geburtstage (Excel)'),
            ENT_QUOTES,
            'UTF-8'
        );

        $html = '<a href="' . $url . '" class="btn btn-secondary btn-sm font-icon-down-circled">'
            . $label
            . '</a>';

        return [self::FRAGMENT => $html];
    }

    /**
     * Returns the URL handler mappings for this component.
     *
     * @param GridField $gridField
     * @return array<string, string>
     */
    public function getURLHandlers($gridField): array
    {
        return [self::URL_SEGMENT => 'handleExport'];
    }

    /**
     * Handles the export request. Applies all active filters (excluding pagination),
     * keeps only Person records that have a birthday set, and streams an Excel file.
     *
     * @param GridField   $gridField
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleExport(GridField $gridField, HTTPRequest $request): HTTPResponse
    {
        $this->requireDownloadPermission();
        $list     = $this->getFilteredList($gridField);
        $filename = 'Geburtstage_' . date('Y-m-d') . '.xlsx';

        return $this->buildDownloadResponse($this->buildSpreadsheet($list), $filename);
    }

    /**
     * Returns the GridField list with all DataManipulators applied except the paginator.
     *
     * @param GridField $gridField
     * @return SS_List
     */
    private function getFilteredList(GridField $gridField): SS_List
    {
        $list = $gridField->getList();

        foreach ($gridField->getComponents() as $component) {
            if ($component instanceof GridField_DataManipulator && !($component instanceof GridFieldPaginator)) {
                $list = $component->getManipulatedData($gridField, $list);
            }
        }

        return $list;
    }

    /**
     * Builds the Spreadsheet from the filtered list.
     * Only Person records with a non-empty Birthday are included.
     *
     * @param SS_List $list
     * @return Spreadsheet
     */
    private function buildSpreadsheet(SS_List $list): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $this->writeHeader($sheet);
        $this->writeRows($sheet, $list);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);

        return $spreadsheet;
    }

    /**
     * Writes the header row and applies bold formatting.
     *
     * @param Worksheet $sheet
     * @return void
     */
    private function writeHeader(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', _t(__CLASS__ . '.COL_NAME', 'Name'));
        $sheet->setCellValue('B1', _t(__CLASS__ . '.COL_BIRTHDAY', 'Geburtstag'));
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
    }

    /**
     * Writes one data row per Person that has a birthday set.
     *
     * @param Worksheet $sheet
     * @param SS_List   $list
     * @return void
     */
    private function writeRows(Worksheet $sheet, SS_List $list): void
    {
        $row = 2;

        foreach ($list as $contact) {
            if (!($contact instanceof Person) || !$contact->Birthday) {
                continue;
            }

            $birthday = date('d.m.Y', strtotime((string) $contact->Birthday));
            $sheet->setCellValue('A' . $row, (string) $contact->Name);
            $sheet->setCellValue('B' . $row, $birthday);
            $row++;
        }
    }

    /**
     * Renders the Spreadsheet into memory and wraps it in an HTTPResponse
     * configured for an .xlsx file download.
     *
     * @param Spreadsheet $spreadsheet
     * @param string      $filename
     * @return HTTPResponse
     */
    private function buildDownloadResponse(Spreadsheet $spreadsheet, string $filename): HTTPResponse
    {
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();

        return HTTPResponse::create()
            ->setStatusCode(200)
            ->addHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->addHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
    }
}
