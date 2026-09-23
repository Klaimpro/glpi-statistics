<?php

namespace GlpiPlugin\Dsistats;

use CommonGLPI;
use Html;
use Session;
use Glpi\Application\View\TemplateRenderer;

/**
 * Plugin configuration tab integrated into GLPI configuration screens.
 */
class Config extends \Config
{
    public static $rightname = 'plugin_dsistats';

    public static function getTypeName($nb = 0): string
    {
        return __('DSI Stats', 'dsistats');
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function getConfig(): array
    {
        return parent::getConfigurationValues('plugin:dsistats');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof \Config) {
            return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ): bool {
        if ($item instanceof \Config) {
            self::showForConfig();
        }

        return true;
    }

    public static function showForConfig(): void
    {
        if (!self::canView()) {
            Html::displayRightError();
        }

        TemplateRenderer::getInstance()->display('@dsistats/pages/config.html.twig', [
            'can_edit'       => self::canUpdate(),
            'current_config' => self::getConfig(),
        ]);
    }
}
