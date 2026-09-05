<?php
/**
 * ABAppointments - Monthly URSSAF revenue declaration report
 *
 * Builds the list of transactions (confirmed/completed appointments) for a
 * given month - the same "chiffre d'affaires" definition already used on the
 * admin dashboard - and renders it as a PDF an auto-entrepreneur can keep as
 * a record when declaring revenue to URSSAF.
 */
class UrssafReport {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * @return array{year:int,month:int,label:string,total:float,count:int,transactions:array}
     */
    public function getData(int $year, int $month): array {
        $sql = "SELECT a.id, a.start_datetime, a.status,
                       c.first_name AS cf, c.last_name AS cl,
                       s.name AS sn, s.price AS sp
                FROM ab_appointments a
                JOIN ab_customers c ON a.customer_id = c.id
                JOIN ab_services s ON a.service_id = s.id
                WHERE MONTH(a.start_datetime) = ? AND YEAR(a.start_datetime) = ?
                  AND a.status IN ('confirmed', 'completed')
                ORDER BY a.start_datetime";
        $rows = $this->db->fetchAll($sql, [$month, $year]);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) $row['sp'];
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => $this->monthLabel($year, $month),
            'total' => $total,
            'count' => count($rows),
            'transactions' => $rows,
        ];
    }

    public function monthName(int $month): string {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        return $months[$month] ?? (string) $month;
    }

    public function monthLabel(int $year, int $month): string {
        return $this->monthName($month) . ' ' . $year;
    }

    /** Renders the report as a PDF and returns the raw file content. */
    public function renderPdf(array $data): string {
        $pdf = new PdfWriter();
        $margin = 40.0;
        $pageWidth = $pdf->width();
        $pageHeight = $pdf->height();
        $contentWidth = $pageWidth - 2 * $margin;

        // Columns: date | client | prestation | montant
        $colDate = $margin;
        $colDateW = 70;
        $colClient = $colDate + $colDateW;
        $colClientW = 165;
        $colService = $colClient + $colClientW;
        $colServiceW = 175;
        $colAmount = $colService + $colServiceW;
        $colAmountW = $contentWidth - $colDateW - $colClientW - $colServiceW;

        $businessName = ab_setting('business_name', 'Auto-entrepreneur');
        $rowHeight = 20;
        $y = 0;

        $drawHeader = function () use ($pdf, $margin, $contentWidth, $businessName, $data, &$y) {
            $pdf->addPage();
            $y = $margin;
            $pdf->text($margin, $y, $businessName, 16, true);
            $y += 22;
            $pdf->text($margin, $y, 'Déclaration de chiffre d\'affaires - ' . $data['label'], 12, true);
            $y += 18;
            $pdf->text($margin, $y, 'Document généré le ' . date('d/m/Y à H:i') . ' pour usage interne (déclaration URSSAF).', 9);
            $y += 22;
            $pdf->line($margin, $y, $margin + $contentWidth, $y);
            $y += 20;
        };

        $drawTableHeader = function () use ($pdf, $margin, $contentWidth, $colDate, $colClient, $colService, $colAmount, &$y) {
            $pdf->setFillColor(0.9, 0.9, 0.9);
            $pdf->rect($margin, $y, $contentWidth, 22, true, false);
            $pdf->setFillColor(0, 0, 0);
            $pdf->text($colDate + 4, $y + 15, 'Date', 9, true);
            $pdf->text($colClient + 4, $y + 15, 'Client', 9, true);
            $pdf->text($colService + 4, $y + 15, 'Prestation', 9, true);
            $pdf->text($colAmount + 4, $y + 15, 'Montant', 9, true);
            $y += 26;
        };

        $drawHeader();
        $drawTableHeader();

        foreach ($data['transactions'] as $t) {
            if ($y > $pageHeight - $margin - $rowHeight) {
                $drawHeader();
                $drawTableHeader();
            }

            $date = date('d/m/Y', strtotime($t['start_datetime']));
            $client = trim($t['cf'] . ' ' . $t['cl']);
            $service = $t['sn'];
            $amount = ab_format_price((float) $t['sp']);

            $pdf->text($colDate + 4, $y + 13, $pdf->fitText($date, $colDateW - 8, 9), 9);
            $pdf->text($colClient + 4, $y + 13, $pdf->fitText($client, $colClientW - 8, 9), 9);
            $pdf->text($colService + 4, $y + 13, $pdf->fitText($service, $colServiceW - 8, 9), 9);
            $amountX = $colAmount + $colAmountW - 4 - $pdf->textWidth($amount, 9);
            $pdf->text($amountX, $y + 13, $amount, 9);

            $y += $rowHeight;
            $pdf->line($margin, $y, $margin + $contentWidth, $y);
        }

        if (empty($data['transactions'])) {
            $pdf->text($margin + 4, $y + 13, 'Aucune transaction sur cette période.', 9);
            $y += $rowHeight;
        }

        $y += 20;
        if ($y > $pageHeight - $margin - 60) {
            $drawHeader();
        }

        $pdf->line($margin, $y, $margin + $contentWidth, $y);
        $y += 24;
        $pdf->text($margin, $y, 'Nombre de rendez-vous facturés : ' . $data['count'], 10);
        $y += 20;
        $pdf->text($margin, $y, 'TOTAL CHIFFRE D\'AFFAIRES DU MOIS : ' . ab_format_price($data['total']), 13, true);
        $y += 26;
        $pdf->text($margin, $y, 'Pensez à déclarer ce montant sur autoentrepreneur.urssaf.fr avant la date limite applicable à votre échéance.', 9);

        return $pdf->output();
    }

    /** Builds the PDF for the given month and saves it under storage/. Returns the absolute file path. */
    public function generateAndStore(int $year, int $month): string {
        $data = $this->getData($year, $month);
        $pdfContent = $this->renderPdf($data);

        $dir = __DIR__ . '/../storage/urssaf_declarations';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = sprintf('declaration-urssaf-%04d-%02d.pdf', $year, $month);
        $path = $dir . '/' . $filename;
        file_put_contents($path, $pdfContent);

        return $path;
    }
}
