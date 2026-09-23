<?php

namespace GlpiPlugin\Dsistats;

use CommonDBTM;
use CommonGLPI;
use Html;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

/**
 * Manage plugin rights in GLPI profiles.
 */
class Profile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function getTypeName($nb = 0): string
    {
        return __('DSI Stats', 'dsistats');
    }

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => self::class,
                'label'    => __('DSI statistics', 'dsistats'),
                'field'    => 'plugin_dsistats',
                'rights'   => [
                    READ   => __('Read'),
                    UPDATE => __('Update'),
                ],
            ],
        ];
    }

    public static function installRights(): void
    {
        global $DB;

        $profile_ids = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => \Profile::getTable(),
        ]) as $profile_row) {
            $profile_ids[] = (int) $profile_row['id'];
        }

        foreach (self::getAllRights() as $right) {
            $existing_profile_ids = [];
            foreach ($DB->request([
                'SELECT' => ['profiles_id'],
                'FROM'   => 'glpi_profilerights',
                'WHERE'  => [
                    'name'        => $right['field'],
                    'profiles_id' => $profile_ids,
                ],
            ]) as $existing_row) {
                $existing_profile_ids[(int) $existing_row['profiles_id']] = true;
            }

            foreach ($profile_ids as $profile_id) {
                if (!isset($existing_profile_ids[$profile_id])) {
                    $DB->insert('glpi_profilerights', [
                        'profiles_id' => $profile_id,
                        'name'        => $right['field'],
                        'rights'      => 0,
                    ]);
                }
            }
        }

        $active_profile_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($active_profile_id > 0) {
            ProfileRight::updateProfileRights($active_profile_id, [
                'plugin_dsistats' => READ | UPDATE,
            ]);
        }
    }

    public static function removeRights(): void
    {
        foreach (self::getAllRights() as $right) {
            ProfileRight::deleteProfileRights([$right['field']]);
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof GlpiProfile && $item->getID() > 0) {
            return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ): bool {
        if ($item instanceof GlpiProfile) {
            self::showForProfile($item);
        }

        return true;
    }

    public static function showForProfile(GlpiProfile $profile): void
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            Html::displayRightError();
        }

        echo '<div class="spaced">';
        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit'       => Session::haveRight(self::$rightname, UPDATE),
            'default_class' => 'tab_bg_2',
            'title'         => self::getTypeName(),
        ]);
        echo '</div>';
    }
}
