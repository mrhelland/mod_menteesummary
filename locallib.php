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
$sql = "SELECT a.id,
               a.name,
               a.duedate,
               gi.grademax AS maxgrade,
               g.finalgrade AS grade,
               cm.id AS cmid,
               cm.section,
               cs.section AS sectionnumber,
               FIND_IN_SET(cm.id, cs.sequence)
                   + (cs.section * 100) AS position  -- ✅ absolute course order
          FROM {assign} a
     JOIN {course_modules} cm ON cm.instance = a.id
     JOIN {course_sections} cs ON cs.id = cm.section
     JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
     JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid
         WHERE a.course = :courseid
           AND cm.visible = 1
      ORDER BY position ASC";

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
        $a->graded = !is_null($a->grade);
        $a->missing = !$a->graded && !$a->submitted;
    }

    return $assignments;
}

/**
 * Get all quizzes for a mentee in a course with grades and visibility rules.
 * Includes user/group overrides and absolute course position.
 */
function menteesummary_get_all_quizzes(int $userid, int $courseid): array {
    global $DB;

    // ✅ Get all visible quizzes with correct grade linkage
    $sql = "SELECT 
                CONCAT('quiz-', q.id) AS uniqueid,  -- unique key for Moodle array index
                q.id AS id,
                q.name,
                COALESCE(uo.timeclose, go.timeclose, q.timeclose) AS duedate,
                gi.grademax AS maxgrade,
                gg.finalgrade AS grade,
                cm.id AS cmid,
                cs.section AS sectionnumber,
                FIND_IN_SET(cm.id, cs.sequence) + (cs.section * 1000) AS position
            FROM {quiz} q
            JOIN {course_modules} cm ON cm.instance = q.id
            JOIN {course_sections} cs ON cs.id = cm.section
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
       LEFT JOIN {grade_items} gi 
                 ON gi.courseid = q.course 
                AND gi.iteminstance = q.id 
                AND gi.itemmodule = 'quiz'
       LEFT JOIN {grade_grades} gg 
                 ON gg.itemid = gi.id 
                AND gg.userid = :userid
       LEFT JOIN {quiz_overrides} uo 
                 ON uo.quiz = q.id 
                AND uo.userid = :userid1
       LEFT JOIN {groups_members} gm 
                 ON gm.userid = :userid2
       LEFT JOIN {quiz_overrides} go 
                 ON go.quiz = q.id 
                AND go.groupid = gm.groupid
           WHERE q.course = :courseid
             AND cm.visible = 1
        ORDER BY position ASC";

    $params = [
        'userid'  => $userid,
        'userid1' => $userid,
        'userid2' => $userid,
        'courseid'=> $courseid
    ];

    $quizzes = $DB->get_records_sql($sql, $params);

    if (empty($quizzes)) {
        return [];
    }

    // ✅ Find user’s finished quiz attempts
    $quizids = array_map(fn($q) => $q->id, $quizzes);
    list($insql, $inparams) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED);
    $inparams['userid'] = $userid;

    $attemptsql = "SELECT 
                       CONCAT('attempt-', qa.id) AS uniqueid,
                       qa.quiz,
                       qa.userid,
                       qa.state
                   FROM {quiz_attempts} qa
                  WHERE qa.quiz $insql
                    AND qa.userid = :userid
                    AND qa.state = 'finished'";

    $attempts = $DB->get_records_sql($attemptsql, $inparams);


     $submitted_quizids = [];
    foreach ($attempts as $attempt) {
        $submitted_quizids[$attempt->quiz] = true;
    }

    // ✅ Normalize each quiz record
    foreach ($quizzes as &$q) {
        $q->submitted = isset($attempts[$q->id]);

        // Handle grades gracefully
        $q->grade = is_null($q->grade) ? '-' : format_float($q->grade, 1);
        $q->maxgrade = is_null($q->maxgrade) ? '-' : format_float($q->maxgrade, 1);
        $q->submitted = !empty($submitted_quizids[$q->id]);
        $q->graded = $q->submitted;
        $q->missing = !$q->submitted;
    }

    

    return array_values($quizzes);
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
        // Otherwise fall back to raw grade value (round/format).
        if (isset($g->grade) && $g->grade !== null) {
            return format_float($g->grade, 1);
        }
        // Prefer the already-formatted string if present (str_grade).
        if (!empty($g->str_grade)) {
            return (string)$g->str_grade;
        }


    }

    return '-';
}

/**
 * Returns a CSS color (HEX) based on percentage.
 *
 * @param float|int $percent The percentage value (0–100).
 * @return string A CSS color value.
 */
function get_score_color($percent) {
    // Clamp the input between 0 and 100 just in case.
    $percent = max(0, min(100, $percent));

    if ($percent < 50) {
        return '#ff4d4d'; // red
    } else if ($percent < 70) {
        return '#ff9900'; // orange
    } else if ($percent < 80) {
        return '#ffeb3b'; // yellow
    } else {
        return '#4caf50'; // green
    }
}

/**
 * Returns a CSS color (HSL) that is red below 50%,
 * and transitions smoothly from red to green above 50%.
 *
 * @param float|int $percent The percentage value (0–100).
 * @return string CSS color string (e.g., "hsl(90, 100%, 45%)")
 */
function get_score_color2($percent) {
    // Clamp input between 0 and 100
    $percent = max(0, min(100, $percent));

    // Below 50%: always red
    if ($percent <= 50) {
        $hue = 0; // red
    } else {
        // Map 50–100% → 0–120° hue (red → green)
        $hue = (($percent - 50) / 50) * 120;
    }

    // Use full saturation and medium lightness for vivid colors
    return "hsl($hue, 100%, 45%)";
}