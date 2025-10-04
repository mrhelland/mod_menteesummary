<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/backup/moodle2/backup_activity_task.class.php');

class backup_menteesummary_activity_task extends backup_activity_task {

    protected function define_my_settings() {
        // No specific settings for this plugin.
    }

    protected function define_my_steps() {
        // Only one step to define the structure of this activity.
        $this->add_step(new backup_menteesummary_activity_structure_step('menteesummary_structure', 'menteesummary.xml'));
    }
}
