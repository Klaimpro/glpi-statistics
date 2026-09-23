<?php

define('GLPI_ROOT', dirname(__DIR__, 3));

include GLPI_ROOT . '/inc/includes.php';

use GlpiPlugin\Dsistats\Dashboard;

Dashboard::checkAccess();

Html::header(__('DSI statistics', 'dsistats'), $_SERVER['PHP_SELF'], 'plugins', 'dsistats');
Dashboard::showPage();
Html::footer();
