<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/backup/moodle2/restore_activity_task.class.php');

class restore_menteesummary_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // No specific settings.
    }

    protected function define_my_steps() {
        $this->add_step(new restore_menteesummary_activity_structure_step('menteesummary_structure', 'menteesummary.xml'));
    }

    public static function define_decode_contents() {
        return [];
    }

    public static function define_decode_rules() {
        return [];
    }
}
