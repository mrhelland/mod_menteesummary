<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/backup/moodle2/restore_activity_structure_step.class.php');

class restore_menteesummary_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('menteesummary', '/activity/menteesummary');
        return $this->prepare_activity_structure($paths);
    }

    protected function process_menteesummary($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('menteesummary', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function after_execute() {
        // Nothing extra.
    }
}
