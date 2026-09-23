<?php

define('PLUGIN_DSISTATS_VERSION', '0.1.0');
define('PLUGIN_DSISTATS_MIN_GLPI', '11.0.0');
define('PLUGIN_DSISTATS_MAX_GLPI', '12.0.0');

use Glpi\Http\Firewall;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Dsistats\Config;
use GlpiPlugin\Dsistats\Dashboard;
use GlpiPlugin\Dsistats\Profile;

/**
 * Initialize plugin hooks.
 */
function plugin_init_dsistats(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['dsistats'] = true;
    $PLUGIN_HOOKS[Hooks::CHANGE_PROFILE]['dsistats'] = 'plugin_change_profile_dsistats';
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['dsistats'][] = 'public/js/graphs.js';

    Plugin::registerClass(Config::class, ['addtabon' => ['Config']]);
    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

    Firewall::addPluginStrategyForLegacyScripts(
        'dsistats',
        '#^/front/dashboard.php$#',
        Firewall::STRATEGY_CENTRAL_ACCESS
    );
    Firewall::addPluginStrategyForLegacyScripts(
        'dsistats',
        '#^/front/graphs.php$#',
        Firewall::STRATEGY_CENTRAL_ACCESS
    );
    Firewall::addPluginStrategyForLegacyScripts(
        'dsistats',
        '#^/front/export.php$#',
        Firewall::STRATEGY_CENTRAL_ACCESS
    );

    if (Dashboard::canView()) {
        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['dsistats'] = [
            'plugins' => Dashboard::class,
        ];
    }
}

/**
 * Plugin metadata.
 */
function plugin_version_dsistats(): array
{
    return [
        'name'         => __('DSI Stats', 'dsistats'),
        'version'      => PLUGIN_DSISTATS_VERSION,
        'author'       => 'Codex',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_DSISTATS_MIN_GLPI,
                'max' => PLUGIN_DSISTATS_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Check prerequisites before install/activation.
 */
function plugin_dsistats_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_DSISTATS_MIN_GLPI, 'lt')) {
        echo __('DSI Stats requires GLPI 11.0.0 or newer.', 'dsistats');
        return false;
    }

    if (version_compare(GLPI_VERSION, PLUGIN_DSISTATS_MAX_GLPI, 'ge')) {
        echo __('DSI Stats is not declared compatible with this GLPI major version yet.', 'dsistats');
        return false;
    }

    return true;
}

/**
 * Check plugin runtime configuration.
 */
function plugin_dsistats_check_config(bool $verbose = false): bool
{
    return true;
}
