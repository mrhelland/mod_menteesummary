<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Get all assignments for a mentee in a course with grades.
 */
/**
 * Get all visible assignments and quizzes for a mentee in a course.
 * Includes grades, submission status, and due dates.
 */

function menteesummary_get_all_assignments($userid, $courseid) {
    global $DB;

    // 1. Get all assignment info + grades.
    $sql = "SELECT a.id, a.name, a.duedate, gi.grademax AS maxgrade, g.finalgrade AS grade
              FROM {assign} a
              JOIN {course_modules} cm ON cm.instance = a.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
              JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
         LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid
             WHERE a.course = :courseid";

    $assignments = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

    if (empty($assignments)) {
        return [];
    }

    // 2. Get all assignment IDs in this course.
    $assignmentids = array_keys($assignments);

    // 3. Fetch all submissions for this user and those assignments in one query.
    list($insql, $params) = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;

    $submissions = $DB->get_records_select('assign_submission',
        "assignment $insql AND userid = :userid AND status = 'submitted'",
        $params,
        '',
        'assignment'
    );

    // 4. Mark each assignment as submitted or not.
    foreach ($assignments as &$a) {
        $a->submitted = isset($submissions[$a->id]);
    }

    return $assignments;
}

function menteesummary_get_all_quizzes($userid, $courseid) {
    global $DB;

    // 1. Get all quiz info + grades.
    $sql = "SELECT q.id, q.name, q.timeclose AS duedate, gi.grademax AS maxgrade, g.finalgrade AS grade
              FROM {quiz} q
              JOIN {course_modules} cm ON cm.instance = q.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              JOIN {grade_items} gi ON gi.iteminstance = q.id AND gi.itemmodule = 'quiz'
         LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid
             WHERE q.course = :courseid";

    $quizzes = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

    if (empty($quizzes)) {
        return [];
    }

    // 2. Get all quiz IDs in this course.
    $quizids = array_keys($quizzes);

    // 3. Fetch all finished quiz attempts for this user.
    list($insql, $params) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;

    $attempts = $DB->get_records_select('quiz_attempts',
        "quiz $insql AND userid = :userid AND state = 'finished'",
        $params,
        '',
        'quiz'
    );

    // 4. Mark each quiz as submitted or not.
    foreach ($quizzes as &$q) {
        $q->submitted = isset($attempts[$q->id]);
    }

    return $quizzes;
}


// function menteesummary_get_all_assignments($userid, $courseid) {
//     global $DB;

//     $sql = "SELECT a.id, a.name, a.duedate, gi.grademax AS maxgrade, g.finalgrade AS grade
//               FROM {assign} a
//               JOIN {course_modules} cm ON cm.instance = a.id
//               JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
//               JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
//          LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid
//              WHERE a.course = :courseid";

//     return $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
// }
// function menteesummary_get_all_assignments($userid, $courseid) {
//     global $DB;

//     $sql = "SELECT a.id, a.name, a.duedate, gi.grademax AS maxgrade, g.finalgrade AS grade
//               FROM {assign} a
//               JOIN {course_modules} cm ON cm.instance = a.id
//               JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
//               JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
//          LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid
//              WHERE a.course = :courseid";

//     $assignments = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

//     // Add submitted flag for each assignment.
//     foreach ($assignments as &$a) {
//         $a->submitted = $DB->record_exists('assign_submission', [
//             'assignment' => $a->id,
//             'userid' => $userid,
//             'status' => 'submitted'
//         ]);
//     }

//     return $assignments;
// }
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

/**
 * Get the final course total grade for a user in a course.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return float|string Final grade or '-' if not available
 */
function menteesummary_get_course_total($userid, $courseid) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/grade/querylib.php');

    // $coursegrade = grade_get_course_grades($courseid, $userid);
    // print_object($coursegrade);

    // if (!empty($coursegrade) && isset($coursegrade->grade)) {
    //     $percentage = $coursegrade->grade; // This is the final grade (usually already a percentage).
    //     return format_float($percentage, 1);
    // }

    $grades = grade_get_course_grades($courseid, $userid);

    // $grades->grades[$userid] contains the user's course total info.
    if (!empty($grades) && !empty($grades->grades) && isset($grades->grades[$userid])) {
        $g = $grades->grades[$userid];

        // Prefer the already-formatted string if present (str_grade).
        if (!empty($g->str_grade)) {
            return (string)$g->str_grade;
        }

        // Otherwise fall back to raw grade value (round/format).
        if (isset($g->grade) && $g->grade !== null) {
            return format_float($g->grade, 2);
        }
    }

    return '-';
}