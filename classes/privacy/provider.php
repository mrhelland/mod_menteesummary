<?php
namespace mod_menteesummary\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for mod_menteesummary.
 *
 * This plugin does not store any personal data; it only reads information
 * from core Moodle tables (assignments, quizzes, grades) that are managed
 * by Moodle core components.
 *
 * @package   mod_menteesummary
 * @category  privacy
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider class for mod_menteesummary.
 */
class provider implements null_provider {

    /**
     * Returns the language string identifier explaining why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
