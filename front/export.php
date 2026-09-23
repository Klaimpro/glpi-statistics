<?php

define('GLPI_ROOT', dirname(__DIR__, 3));

include GLPI_ROOT . '/inc/includes.php';

use GlpiPlugin\Dsistats\Dashboard;
use GlpiPlugin\Dsistats\PdfExporter;
use GlpiPlugin\Dsistats\StatsService;

Dashboard::checkAccess();

$view = $_REQUEST['view'] ?? 'tables';

if ($view === 'graphs') {
    Session::checkCSRF();
    $filters = StatsService::normalizeGraphFilters($_REQUEST);
    $graph_data = StatsService::getGraphData($filters);
    PdfExporter::outputGraphsPdf($graph_data, $_POST['chart_image'] ?? null);
}

$filters = StatsService::normalizeFilters($_REQUEST);
$dashboard_data = StatsService::getDashboardData($filters);
PdfExporter::outputTablesPdf($dashboard_data);
