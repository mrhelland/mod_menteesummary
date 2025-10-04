<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Get all assignments for a mentee in a course with grades.
 */
function menteesummary_get_all_assignments($userid, $courseid) {
    global $DB;

    $sql = "SELECT a.id, a.name, a.duedate, gi.grademax AS maxgrade, g.finalgrade AS grade
              FROM {assign} a
              JOIN {course_modules} cm ON cm.instance = a.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
              JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
         LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid
             WHERE a.course = :courseid";

    return $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
}

// function menteesummary_get_user_mentees($userid) {
//     global $DB;
//     // Basic heuristic: find users where there is a role assignment in the same course contexts as $userid
//     $sql = "SELECT u.*
//           FROM {user} u
//           JOIN {role_assignments} ra ON ra.userid = u.id
//           JOIN {context} ctx ON ctx.id = ra.contextid
//          WHERE ctx.contextlevel = :contextlevel
//            AND EXISTS (
//               SELECT 1
//                 FROM {role_assignments} pra
//                WHERE pra.userid = :parentid
//                  AND pra.contextid = ctx.id
//            )";
//     return $DB->get_records_sql($sql, ['contextlevel' => CONTEXT_COURSE, 'parentid' => $userid]);
// }

function menteesummary_get_user_mentees(int $mentorid): array {
    global $DB;

    $sql = "SELECT u.*
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {user} u ON u.id = ctx.instanceid
             WHERE ra.userid = :mentorid
               AND ctx.contextlevel = :usercontext";

    return $DB->get_records_sql($sql, [
        'mentorid' => $mentorid,
        'usercontext' => CONTEXT_USER
    ]);
}


function menteesummary_get_mentee_courses($menteeid) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/course/lib.php');
    $courses = enrol_get_users_courses($menteeid, true, '*');
    $out = [];
    foreach ($courses as $c) {
        $out[] = [
            'id' => $c->id,
            'fullname' => format_string($c->fullname),
            'url' => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
        ];
    }
    return $out;
}
