<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Contacts\Forms\GridField;

use Clesson\Silverstripe\Contacts\Models\Person;
use Clesson\Silverstripe\Forms\GridField\GridFieldMenuItemProvider;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Model\List\SS_List;
use TCPDF;

/**
 * GridField action component that exports an immerwährender Geburtstagskalender
 * as a DIN A3 landscape PDF using TCPDF.
 *
 * Months are displayed as columns (January–December). Each column lists days
 * 1–31; if a Person from the current filtered list has a birthday on that date,
 * their name is shown in the corresponding cell. Pagination is ignored.
 *
 * @package Clesson\Silverstripe\Contacts
 * @subpackage Forms\GridField
 */
class GridFieldAction_BirthdayPdfExport implements GridField_HTMLProvider, GridField_URLHandler, GridFieldMenuItemProvider
{
    use Injectable;
    use ContactDownloadMenuProvider;

    /** English fallback for the existing translated download label. */
    private const DOWNLOAD_LABEL = 'Birthdays (PDF)';

    /** Icon used by the download menu entry. */
    private const DOWNLOAD_ICON = 'print';

    /** Fragment in which the export button is rendered. */
    private const FRAGMENT    = 'buttons-before-right';

    /** URL segment for the export handler. */
    private const URL_SEGMENT = 'export-birthdays-pdf';

    /** Page dimensions and margins in mm (A3 landscape). */
    private const PAGE_W   = 420.0;
    private const PAGE_H   = 297.0;
    private const MARGIN   = 10.0;
    private const HEADER_H = 10.0;

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
        $label = htmlspecialchars(_t(__CLASS__ . '.BUTTON_LABEL', 'Geburtstage (PDF)'), ENT_QUOTES, 'UTF-8');
        $html  = '<a href="' . $url . '" class="btn btn-secondary btn-sm font-icon-print">' . $label . '</a>';

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
     * Handles the PDF export request.
     *
     * @param GridField   $gridField
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleExport(GridField $gridField, HTTPRequest $request): HTTPResponse
    {
        $this->requireDownloadPermission();
        $list        = $this->getFilteredList($gridField);
        $birthdayMap = $this->buildBirthdayMap($list);
        $content     = $this->buildPdf($birthdayMap);
        $filename    = 'Geburtstagskalender_' . date('Y-m-d') . '.pdf';

        return HTTPResponse::create()
            ->setStatusCode(200)
            ->addHeader('Content-Type', 'application/pdf')
            ->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->addHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
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
     * Builds a nested birthday map [month][day] => [name, ...] from the contact list.
     *
     * @param SS_List $list
     * @return array<int, array<int, list<string>>>
     */
    private function buildBirthdayMap(SS_List $list): array
    {
        $map = [];

        foreach ($list as $contact) {
            if (!($contact instanceof Person) || !$contact->Birthday) {
                continue;
            }

            [, $m, $d] = explode('-', (string) $contact->Birthday);
            $map[(int) $m][(int) $d][] = (string) $contact->Name;
        }

        return $map;
    }

    /**
     * Generates the PDF document and returns it as a binary string.
     *
     * @param array<int, array<int, list<string>>> $birthdayMap
     * @return string
     */
    private function buildPdf(array $birthdayMap): string
    {
        $pdf  = $this->createPdfDocument();
        $colW = (self::PAGE_W - 2 * self::MARGIN) / 12;
        $dayH = (self::PAGE_H - 2 * self::MARGIN - self::HEADER_H) / 31;

        $this->drawMonthHeaders($pdf, $colW);
        $this->drawDayRows($pdf, $birthdayMap, $colW, $dayH);

        return $pdf->Output('', 'S');
    }

    /**
     * Creates and configures a TCPDF instance for A3 landscape output.
     *
     * @return TCPDF
     */
    private function createPdfDocument(): TCPDF
    {
        $pdf = new TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);
        $pdf->SetCreator('Silverstripe Contacts');
        $pdf->SetTitle(_t(__CLASS__ . '.PDF_TITLE', 'Geburtstagskalender'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        return $pdf;
    }

    /**
     * Draws the month header row across all 12 columns.
     *
     * @param TCPDF $pdf
     * @param float $colW
     * @return void
     */
    private function drawMonthHeaders(TCPDF $pdf, float $colW): void
    {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(220, 220, 220);

        for ($m = 1; $m <= 12; $m++) {
            $x = self::MARGIN + ($m - 1) * $colW;
            $pdf->SetXY($x, self::MARGIN);
            $pdf->Cell($colW, self::HEADER_H, $this->getMonthName($m), 1, 0, 'C', true);
        }
    }

    /**
     * Draws all 31 day rows across all 12 columns.
     * The day number is only rendered when the month actually has that day.
     *
     * @param TCPDF                                $pdf
     * @param array<int, array<int, list<string>>> $birthdayMap
     * @param float                                $colW
     * @param float                                $dayH
     * @return void
     */
    private function drawDayRows(TCPDF $pdf, array $birthdayMap, float $colW, float $dayH): void
    {
        for ($day = 1; $day <= 31; $day++) {
            $y = self::MARGIN + self::HEADER_H + ($day - 1) * $dayH;

            for ($month = 1; $month <= 12; $month++) {
                $x       = self::MARGIN + ($month - 1) * $colW;
                $names   = $birthdayMap[$month][$day] ?? [];
                $showDay = $day <= $this->getMaxDaysForMonth($month);
                $this->drawDayCell($pdf, $x, $y, $colW, $dayH, $day, $names, $showDay);
            }
        }
    }

    /**
     * Draws a single day cell with its border, and optionally the day number and birthday names.
     * When $showDay is false only the border rectangle is rendered.
     *
     * @param TCPDF        $pdf
     * @param float        $x
     * @param float        $y
     * @param float        $colW
     * @param float        $dayH
     * @param int          $day
     * @param list<string> $names
     * @param bool         $showDay Whether this month has this day.
     * @return void
     */
    private function drawDayCell(TCPDF $pdf, float $x, float $y, float $colW, float $dayH, int $day, array $names, bool $showDay): void
    {
        $pdf->Rect($x, $y, $colW, $dayH, 'D');

        if (!$showDay) {
            return;
        }

        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetXY($x + 0.5, $y + 0.5);
        $pdf->Cell($colW - 1, 3.0, (string) $day, 0, 0, 'L');

        if (empty($names)) {
            return;
        }

        $pdf->SetFont('helvetica', '', 5);
        $nameY = $y + 3.5;
        $maxY  = $y + $dayH - 1.5;

        foreach ($names as $name) {
            if ($nameY > $maxY) {
                break;
            }
            $pdf->SetXY($x + 0.5, $nameY);
            $pdf->Cell($colW - 1, 1.8, $name, 0, 0, 'L');
            $nameY += 1.8;
        }
    }

    /**
     * Returns the maximum number of days for the given month in a perpetual calendar.
     * February is treated as having 29 days to cover leap years.
     *
     * @param int $month
     * @return int
     */
    private function getMaxDaysForMonth(int $month): int
    {
        return match ($month) {
            2              => 29,
            4, 6, 9, 11   => 30,
            default        => 31,
        };
    }

    /**
     * Returns the localised month name for the given month number (1–12).
     *
     * @param int $month
     * @return string
     */
    private function getMonthName(int $month): string
    {
        $names = [
            1  => _t(__CLASS__ . '.MONTH_01', 'Januar'),
            2  => _t(__CLASS__ . '.MONTH_02', 'Februar'),
            3  => _t(__CLASS__ . '.MONTH_03', 'März'),
            4  => _t(__CLASS__ . '.MONTH_04', 'April'),
            5  => _t(__CLASS__ . '.MONTH_05', 'Mai'),
            6  => _t(__CLASS__ . '.MONTH_06', 'Juni'),
            7  => _t(__CLASS__ . '.MONTH_07', 'Juli'),
            8  => _t(__CLASS__ . '.MONTH_08', 'August'),
            9  => _t(__CLASS__ . '.MONTH_09', 'September'),
            10 => _t(__CLASS__ . '.MONTH_10', 'Oktober'),
            11 => _t(__CLASS__ . '.MONTH_11', 'November'),
            12 => _t(__CLASS__ . '.MONTH_12', 'Dezember'),
        ];

        return $names[$month] ?? (string) $month;
    }
}
