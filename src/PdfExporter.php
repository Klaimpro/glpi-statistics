<?php

namespace GlpiPlugin\Dsistats;

use DateTimeImmutable;
use TCPDF;

class PdfExporter
{
    public static function outputTablesPdf(array $dashboard_data): never
    {
        $pdf = self::createPdf('P', __('Statistiques DSI', 'dsistats'));
        $pdf->AddPage();
        $pdf->writeHTML(self::buildTablesHtml($dashboard_data), true, false, true, false, '');

        self::outputPdf(
            $pdf,
            self::buildFilename('tableaux', $dashboard_data['filters'])
        );
    }

    public static function outputGraphsPdf(array $graph_data, ?string $chart_image): never
    {
        $pdf = self::createPdf('L', __('Graphiques DSI', 'dsistats'));
        $pdf->AddPage();
        $pdf->writeHTML(self::buildGraphsHeaderHtml($graph_data), true, false, true, false, '');

        $image_data = self::extractPngData($chart_image);
        if ($image_data !== null) {
            $pdf->Ln(2);
            $pdf->Image('@' . $image_data, 12, $pdf->GetY(), 273, 0, 'PNG');
        } else {
            $pdf->Ln(6);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(0, 8, __('Aperçu du graphique indisponible pour cet export.', 'dsistats'), 0, 'L', false, 1);
        }

        self::outputPdf(
            $pdf,
            self::buildFilename('graphiques', $graph_data['filters'])
        );
    }

    private static function createPdf(string $orientation, string $title): TCPDF
    {
        $generated_at = (new DateTimeImmutable('now'))->format('d/m/Y H:i');

        $pdf = new class($orientation, PDF_UNIT, 'A4', true, 'UTF-8', false, $generated_at) extends TCPDF {
            public function __construct(
                string $orientation,
                string $unit,
                string $format,
                bool $unicode,
                string $encoding,
                bool $diskcache,
                private readonly string $generatedAt
            ) {
                parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
            }

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('helvetica', '', 8);
                $footer = sprintf(
                    '%s - %s %s/%s',
                    __('Généré le', 'dsistats'),
                    $this->generatedAt,
                    __('Page', 'dsistats'),
                    $this->getAliasNumPage() . '/' . $this->getAliasNbPages()
                );
                $this->Cell(0, 10, $footer, 0, false, 'C');
            }
        };

        $pdf->SetCreator('GLPI dsistats');
        $pdf->SetAuthor('dsistats');
        $pdf->SetTitle($title);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFont('helvetica', '', 9);

        return $pdf;
    }

    private static function buildTablesHtml(array $dashboard_data): string
    {
        $html = self::buildDocumentStart(
            __('Statistiques DSI', 'dsistats'),
            $dashboard_data['period_label']
        );

        foreach ($dashboard_data['groups'] as $group) {
            $html .= '<h2>' . htmlescape($group['group_label']) . '</h2>';

            if ($group['group_status'] !== 'found') {
                $html .= '<p><em>' . htmlescape($group['group_status_message']) . '</em></p>';
                continue;
            }

            $html .= '<table cellpadding="4">';
            $html .= '<thead><tr>'
                . '<th width="20%">' . htmlescape(__('Mois', 'dsistats')) . '</th>'
                . '<th width="20%">' . htmlescape(__('Résolution Incident', 'dsistats')) . '</th>'
                . '<th width="20%">' . htmlescape(__('Résolution Demande', 'dsistats')) . '</th>'
                . '<th width="20%">' . htmlescape(__('Prise en compte Incident', 'dsistats')) . '</th>'
                . '<th width="20%">' . htmlescape(__('Prise en compte Demande', 'dsistats')) . '</th>'
                . '</tr></thead><tbody>';

            foreach ($group['average_rows'] as $row) {
                $html .= '<tr nobr="true">'
                    . '<td>' . htmlescape($row['month_label']) . '</td>'
                    . '<td>' . self::renderMetricHtml($row['incident_solve']) . '</td>'
                    . '<td>' . self::renderMetricHtml($row['request_solve']) . '</td>'
                    . '<td>' . self::renderMetricHtml($row['incident_take']) . '</td>'
                    . '<td>' . self::renderMetricHtml($row['request_take']) . '</td>'
                    . '</tr>';
            }

            $html .= '<tr class="summary" nobr="true">'
                . '<td>' . htmlescape($group['period_average_row']['label']) . '</td>'
                . '<td>' . self::renderMetricHtml($group['period_average_row']['incident_solve']) . '</td>'
                . '<td>' . self::renderMetricHtml($group['period_average_row']['request_solve']) . '</td>'
                . '<td>' . self::renderMetricHtml($group['period_average_row']['incident_take']) . '</td>'
                . '<td>' . self::renderMetricHtml($group['period_average_row']['request_take']) . '</td>'
                . '</tr>';
            $html .= '</tbody></table>';

            $html .= '<table cellpadding="4">';
            $html .= '<thead><tr>'
                . '<th width="40%">' . htmlescape(__('Mois', 'dsistats')) . '</th>'
                . '<th width="20%" align="right">' . htmlescape(__('Incidents', 'dsistats')) . '</th>'
                . '<th width="20%" align="right">' . htmlescape(__('Demandes', 'dsistats')) . '</th>'
                . '<th width="20%" align="right">' . htmlescape(__('Total', 'dsistats')) . '</th>'
                . '</tr></thead><tbody>';

            foreach ($group['volume_rows'] as $row) {
                $html .= '<tr nobr="true">'
                    . '<td>' . htmlescape($row['month_label']) . '</td>'
                    . '<td align="right">' . (int) $row['incident_count'] . '</td>'
                    . '<td align="right">' . (int) $row['request_count'] . '</td>'
                    . '<td align="right">' . (int) $row['total_count'] . '</td>'
                    . '</tr>';
            }

            $html .= '<tr class="summary" nobr="true">'
                . '<td>' . htmlescape($group['volume_totals']['label']) . '</td>'
                . '<td align="right">' . (int) $group['volume_totals']['incident_count'] . '</td>'
                . '<td align="right">' . (int) $group['volume_totals']['request_count'] . '</td>'
                . '<td align="right">' . (int) $group['volume_totals']['total_count'] . '</td>'
                . '</tr>';
            $html .= '</tbody></table>';
        }

        return $html . '</body></html>';
    }

    private static function buildGraphsHeaderHtml(array $graph_data): string
    {
        $group_labels = self::resolveSelectedLabels($graph_data['group_choices'], $graph_data['filters']['selected_groups']);
        $type_labels = self::resolveSelectedLabels($graph_data['type_choices'], array_map('strval', $graph_data['filters']['selected_types']));
        $metric_labels = self::resolveSelectedLabels($graph_data['metric_choices'], $graph_data['filters']['selected_metrics']);

        $html = self::buildDocumentStart(
            __('Graphiques DSI', 'dsistats'),
            $graph_data['period_label']
        );

        $html .= '<p><strong>' . htmlescape(__('Groupes', 'dsistats')) . ' :</strong> ' . htmlescape(implode(', ', $group_labels)) . '</p>';
        $html .= '<p><strong>' . htmlescape(__('Types', 'dsistats')) . ' :</strong> ' . htmlescape(implode(', ', $type_labels)) . '</p>';
        $html .= '<p><strong>' . htmlescape(__('Indicateurs', 'dsistats')) . ' :</strong> ' . htmlescape(implode(', ', $metric_labels)) . '</p>';

        return $html;
    }

    private static function buildDocumentStart(string $title, string $period_label): string
    {
        return '<html><head><style>'
            . 'body{font-family:helvetica;font-size:9pt;color:#1f2937;}'
            . 'h1{font-size:18pt;margin:0 0 6px 0;}'
            . 'h2{font-size:13pt;margin:16px 0 6px 0;}'
            . 'p{margin:0 0 6px 0;}'
            . 'table{width:100%;border-collapse:collapse;margin:0 0 10px 0;}'
            . 'th{background-color:#e9ecef;font-weight:bold;border:1px solid #bfc7d1;}'
            . 'td{border:1px solid #d8dee4;vertical-align:top;}'
            . '.summary td{background-color:#f5f6f8;font-weight:bold;}'
            . '.ok{color:#198754;}'
            . '.ko{color:#dc3545;}'
            . '.na{color:#6c757d;}'
            . '.meta{color:#6c757d;font-size:8.5pt;}'
            . '</style></head><body>'
            . '<h1>' . htmlescape($title) . '</h1>'
            . '<p><strong>' . htmlescape(__('Période', 'dsistats')) . ' :</strong> ' . htmlescape($period_label) . '</p>';
    }

    private static function renderMetricHtml(array $metric): string
    {
        $class = match ($metric['status']) {
            'success' => 'ok',
            'danger'  => 'ko',
            default   => 'na',
        };

        $label = match ($metric['status']) {
            'success' => __('Objectif respecté', 'dsistats'),
            'danger'  => __('Objectif dépassé', 'dsistats'),
            default   => __('Pas d’objectif ou donnée non calculable', 'dsistats'),
        };

        return '<span class="' . $class . '">●</span> '
            . htmlescape($metric['display'])
            . '<br><span class="meta">' . htmlescape($label) . '</span>';
    }

    private static function resolveSelectedLabels(array $choices, array $selected): array
    {
        $labels = [];
        $selected_map = array_fill_keys(array_map('strval', $selected), true);

        foreach ($choices as $choice) {
            if (isset($selected_map[(string) $choice['key']])) {
                $labels[] = (string) $choice['label'];
            }
        }

        return $labels;
    }

    private static function extractPngData(?string $chart_image): ?string
    {
        if (!is_string($chart_image) || $chart_image === '') {
            return null;
        }

        if (strlen($chart_image) > 10_000_000) {
            return null;
        }

        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $chart_image, $matches)) {
            return null;
        }

        $decoded = base64_decode($matches[1], true);

        return $decoded !== false ? $decoded : null;
    }

    private static function buildFilename(string $suffix, array $filters): string
    {
        return sprintf(
            'DSI-Stats_%s_%04d-%02d_%04d-%02d.pdf',
            preg_replace('/[^A-Za-z0-9_-]/', '-', $suffix),
            $filters['from_year'],
            $filters['from_month'],
            $filters['to_year'],
            $filters['to_month']
        );
    }

    private static function outputPdf(TCPDF $pdf, string $filename): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        exit;
    }
}
