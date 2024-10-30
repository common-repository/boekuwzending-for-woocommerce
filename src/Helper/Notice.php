<?php

namespace Boekuwzending\WooCommerce\Helper;

/**
 * Class Notice
 * @package Boekuwzending\WooCommerce\Helper
 */
class Notice
{
    private static $optionKey = 'wc_buz_admin_notices';

    /**
     * @param $message
     * @param string $type
     */
    public static function frontend($message, $type = 'notice'): void
    {
        $type = in_array($type, ['notice', 'error', 'success']) ? $type : 'notice';

        wc_add_notice($message, $type);
    }

    /**
     * @return bool
     */
    public static function hasNotices(): bool
    {
        return !empty(get_option(self::$optionKey));
    }

    /**
     * @param string $message
     * @param string $type
     * @param bool $dismissible
     */
    public static function admin(string $message, $type = 'notice', bool $dismissible = false): void
    {
        $type = in_array($type, ['info', 'warning', 'error', 'success']) ? $type : 'info';

        $notices = get_option(self::$optionKey, []);

        $dismissible_text = ( $dismissible ) ? 'is-dismissible' : '';

        $notices[] = [
            'notice' => $message,
            'type' => $type,
            'dismissible' => $dismissible_text
        ];

        update_option(self::$optionKey, $notices );
    }

    /**
     * Renders all notices
     * @param bool $skipXhrCheck
     */
    public static function render(bool $skipXhrCheck = false): void
    {
        if (false === $skipXhrCheck && is_ajax()) {
            return;
        }

        $notices = get_option(self::$optionKey, []);

        foreach ($notices as $notice) {
            echo sprintf(
                '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
                $notice['type'],
                $notice['dismissible'],
                $notice['notice']
            );
        }

        if (!empty($notices)) {
            delete_option(self::$optionKey);
        }
    }
}