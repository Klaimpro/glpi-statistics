<?php

namespace GlpiPlugin\Dsistats;

use CommonGLPI;
use Html;
use InvalidArgumentException;
use Session;
use Ticket;
use Glpi\Application\View\TemplateRenderer;

/**
 * Dashboard entry point and menu definition for the plugin.
 */
class Dashboard extends CommonGLPI
{
    public static $rightname = 'plugin_dsistats';

    public static function getTypeName($nb = 0): string
    {
        return __('DSI statistics', 'dsistats');
    }

    public static function getMenuName($nb = 0): string
    {
        return __('Statistiques DSI', 'dsistats');
    }

    public static function getIcon(): string
    {
        return 'ti ti-chart-bar';
    }

    public static function canView(): bool
    {
        return Session::getCurrentInterface() === 'central'
            && Session::haveRightsOr(Ticket::$rightname, [
                UPDATE,
                Ticket::ASSIGN,
                Ticket::STEAL,
                Ticket::OWN,
                Ticket::CHANGEPRIORITY,
            ]);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function checkAccess(): void
    {
        if (!self::canView()) {
            Html::displayRightError();
        }
    }

    public static function getMenuContent(): array
    {
        $dashboard_page = '/plugins/dsistats/front/dashboard.php';

        $menu = [
            'title' => self::getMenuName(),
            'page'  => $dashboard_page,
            'icon'  => self::getIcon(),
            'links' => [
                'search' => $dashboard_page,
            ],
        ];

        $menu['options']['dashboard'] = [
            'title' => __('Statistiques DSI', 'dsistats'),
            'page'  => $dashboard_page,
            'icon'  => self::getIcon(),
            'links' => [
                'search' => $dashboard_page,
            ],
        ];

        if (self::canUpdate()) {
            $menu['options']['config'] = [
                'title' => __('Configuration', 'dsistats'),
                'page'  => '/front/config.form.php?forcetab=GlpiPlugin%5CDsistats%5CConfig$1',
                'icon'  => 'ti ti-settings',
                'links' => [
                    'search' => '/front/config.form.php?forcetab=GlpiPlugin%5CDsistats%5CConfig$1',
                ],
            ];
        }

        return $menu;
    }

    public static function showPage(): void
    {
        $errors = [];

        try {
            $filters = StatsService::normalizeFilters($_GET);
        } catch (InvalidArgumentException $exception) {
            $filters = StatsService::getDefaultFilters();
            $errors[] = $exception->getMessage();
        }

        $dashboard_data = StatsService::getDashboardData($filters);

        TemplateRenderer::getInstance()->display('@dsistats/pages/dashboard.html.twig', [
            'page_title' => __('Statistiques DSI', 'dsistats'),
            'current_view' => 'tables',
            'month_choices' => StatsService::getMonthChoices(),
            'filters' => $dashboard_data['filters'],
            'groups' => $dashboard_data['groups'],
            'period_label' => $dashboard_data['period_label'],
            'visibility_note' => $dashboard_data['visibility_note'],
            'errors' => $errors,
        ]);
    }

    public static function showGraphsPage(): void
    {
        $errors = [];

        try {
            $filters = StatsService::normalizeGraphFilters($_GET);
        } catch (InvalidArgumentException $exception) {
            $filters = array_merge(StatsService::getDefaultFilters(), StatsService::getGraphDefaults());
            $errors[] = $exception->getMessage();
        }

        $graph_data = StatsService::getGraphData($filters);

        TemplateRenderer::getInstance()->display('@dsistats/pages/graphs.html.twig', [
            'page_title'      => __('Graphiques DSI', 'dsistats'),
            'current_view'    => 'graphs',
            'month_choices'   => StatsService::getMonthChoices(),
            'filters'         => $graph_data['filters'],
            'group_choices'   => $graph_data['group_choices'],
            'type_choices'    => $graph_data['type_choices'],
            'metric_choices'  => $graph_data['metric_choices'],
            'chart'           => $graph_data['chart'],
            'period_label'    => $graph_data['period_label'],
            'visibility_note' => $graph_data['visibility_note'],
            'csrf_token'      => Session::getNewCSRFToken(),
            'errors'          => $errors,
        ]);
    }
}
