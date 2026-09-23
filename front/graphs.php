<?php

define('GLPI_ROOT', dirname(__DIR__, 3));

include GLPI_ROOT . '/inc/includes.php';

use GlpiPlugin\Dsistats\Dashboard;

Dashboard::checkAccess();

Html::requireJs('charts');

Html::header(__('Graphiques DSI', 'dsistats'), $_SERVER['PHP_SELF'], 'plugins', 'dsistats');
Dashboard::showGraphsPage();
Html::footer();
