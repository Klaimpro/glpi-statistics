<?php

use GlpiPlugin\Dsistats\Config;
use GlpiPlugin\Dsistats\Profile;

/**
 * Install hook.
 */
function plugin_dsistats_install(): bool
{
    Profile::installRights();
    Config::setConfigurationValues('plugin:dsistats', [
        'initialized' => 1,
    ]);

    return true;
}

/**
 * Uninstall hook.
 */
function plugin_dsistats_uninstall(): bool
{
    Profile::removeRights();

    $config = new Config();
    $config->deleteByCriteria([
        'context' => 'plugin:dsistats',
    ]);

    return true;
}

/**
 * Refresh profile-dependent session data.
 */
function plugin_change_profile_dsistats(): void
{
    // Native profile rights are reloaded by GLPI; nothing custom is needed yet.
}
