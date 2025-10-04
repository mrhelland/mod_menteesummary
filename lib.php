<?php
// This file is part of Moodle - http://moodle.org/
//
// Library of functions for the menteesummary module.
// Only Moodle callbacks should be here.
// Put helper functions in locallib.php or classes/.

// Make sure this file is not accessed directly.
defined('MOODLE_INTERNAL') || die();

/**
 * Add a new menteesummary instance.
 *
 * @param stdClass $data Data from the form.
 * @param mod_form|null $mform Optional form object.
 * @return int The new instance ID.
 */
function menteesummary_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    // Ensure intro fields exist (mod_form usually sets these).
    if (!isset($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    return $DB->insert_record('menteesummary', $data);
}

/**
 * Update an existing menteesummary instance.
 *
 * @param stdClass $data Data from the form.
 * @param mod_form|null $mform Optional form object.
 * @return bool True on success.
 */
function menteesummary_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('menteesummary', $data);
}

/**
 * Delete a menteesummary instance.
 *
 * @param int $id ID of the instance to delete.
 * @return bool True on success.
 */
function menteesummary_delete_instance($id) {
    global $DB;

    if (!$record = $DB->get_record('menteesummary', ['id' => $id])) {
        return false;
    }

    // Remove dependent data if needed here.

    $DB->delete_records('menteesummary', ['id' => $id]);
    return true;
}

/**
 * Indicates which Moodle features the module supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false/null depending on support.
 */
function menteesummary_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Returns information about the module for the course view page.
 *
 * @param cm_info $cm Course module info.
 * @return cached_cm_info|null
 */
function menteesummary_get_coursemodule_info($cm) {
    global $DB;

    $info = new cached_cm_info();
    if ($record = $DB->get_record('menteesummary', ['id' => $cm->instance], 'id, name, intro, introformat')) {
        $info->name = $record->name;
        if (!empty($record->intro)) {
            $info->content = format_module_intro('menteesummary', $record, $cm->id, false);
        }
    }
    return $info;
}



