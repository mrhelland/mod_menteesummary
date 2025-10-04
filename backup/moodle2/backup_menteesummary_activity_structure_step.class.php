<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/backup/moodle2/backup_activity_structure_step.class.php');

class backup_menteesummary_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        // Define a single element for the activity table.
        $menteesummary = new backup_nested_element('menteesummary', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified'
        ]);

        // Define sources (where data comes from).
        $menteesummary->set_source_table('menteesummary', ['id' => backup::VAR_ACTIVITYID]);

        // No children, no extra data.

        return $this->prepare_activity_structure($menteesummary);
    }
}
