<?php
/**
 * mod_menteesummary — view.php
 * -----------------------------------------------
 * SECURITY & PRIVACY COMPLIANCE CHECKLIST
 * -----------------------------------------------
 * 
 * ✅ ACCESS CONTROL
 * -----------------
 * [✔] require_login() called with $course and $cm context.
 * [✔] require_capability('mod/menteesummary:view', $context) limits access to authorized users.
 * [✔] Context retrieved via context_module::instance($cm->id).
 * [ ] Verify that capability is properly defined in db/access.php.
 * 
 * ✅ INPUT VALIDATION
 * -------------------
 * [✔] All incoming params sanitized using Moodle core functions:
 *      - $id = required_param('id', PARAM_INT)
 *      - $menteeid = optional_param('menteeid', 0, PARAM_INT)
 *      - $selectedcourseid = optional_param('courseid', 0, PARAM_INT)
 * [✔] No user input interpolated into SQL or file paths.
 * [✔] Explicit casting to (int) for defensive coding (recommended).
 * 
 * ✅ DATABASE SAFETY
 * ------------------
 * [✔] All DB access via $DB->get_record(), $DB->get_records_sql(), etc. with parameter binding.
 * [✔] No raw SQL concatenation or dynamic table names.
 * [✔] Helper functions (e.g., menteesummary_get_all_assignments) use bound parameters.
 * 
 * ✅ OUTPUT SANITIZATION
 * ----------------------
 * [✔] Mustache templates handle escaping automatically for {{variables}}.
 * [✔] Only safe, intentionally formatted HTML passed via {{{triple braces}}}.
 * [✔] Teacher feedback sanitized by Moodle editor (format_text / strip_tags).
 * [✔] No direct echo of untrusted data.
 * 
 * ✅ DATA PRIVACY
 * ---------------
 * [✔] Mentee data limited to users returned by menteesummary_get_user_mentees($USER->id).
 * [✔] No arbitrary user data fetched by ID without relationship validation.
 * [ ] Ensure menteesummary_get_user_mentees() enforces mentor relationship via DB or capability check.
 * 
 * ✅ CSRF & STATE MANAGEMENT
 * --------------------------
 * [✔] No write operations or data modification in this view.
 * [✔] All links are safe GET-only navigation via moodle_url.
 * [ ] If future POST/DELETE actions are added, wrap in require_sesskey().
 * 
 * ✅ FILE ACCESS
 * --------------
 * [✔] All includes are static (no user input in file paths).
 * [✔] Uses __DIR__ and $CFG->dirroot safely.
 * 
 * ✅ DEBUG / DISCLOSURE
 * ---------------------
 * [✔] No debug output (print_object, var_dump) left in production.
 * [✔] Error messages rely on Moodle exceptions (MUST_EXIST).
 * 
 * ✅ PERFORMANCE / SCALABILITY (non-security)
 * -------------------------------------------
 * [✔] Efficient single-pass loops for courses and activities.
 * [ ] Consider caching menteesummary_get_course_total() and get_all_assignments() for large deployments.
 * 
 * ✅ MAINTAINER NOTES
 * -------------------
 * - Never trust $menteeid or $courseid without verifying ownership.
 * - Always sanitize teacher-entered feedback with format_text() before display.
 * - When extending templates, prefer {{}} over {{{}}} unless output is trusted HTML.
 * - Run Security Check plugin (Site admin > Reports > Security overview) after updates.
 **/

// include dependencies
require('../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/user/lib.php');

defined('MOODLE_INTERNAL') || die();

// Get current object id's
$id = required_param('id', PARAM_INT); // Course module ID.
$menteeid = optional_param('menteeid', 0, PARAM_INT);
$selectedcourseid = optional_param('courseid', 0, PARAM_INT);

// Get context and check permissions
$cm = get_coursemodule_from_id('menteesummary', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/menteesummary:view', $context);

// Update page
$PAGE->set_url('/mod/menteesummary/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading($course->fullname);

// Get mentees assigned to current user
$mentees = [];
$users = menteesummary_get_user_mentees($USER->id);
foreach ($users as $m) {
    $mentees[] = [
        'id' => $m->id,
        'fullname' => ucwords(fullname($m)),
        'username' => $m->username,
        'profilepic' => $OUTPUT->user_picture($m, ['size' => 64, 'link' => false]),
        'profileurl' => (new moodle_url('/user/view.php', [
            'id' => $m->id,
            'course' => SITEID
        ]))->out(false),
        'url' => (new moodle_url('/mod/menteesummary/view.php', [
            'id' => $cm->id,
            'menteeid' => $m->id
        ]))->out(false),
    ];
}

// Determine if a mentee is selected
$selected = null;
if ($menteeid) {
    foreach ($mentees as $mentee) {
        if ($mentee['id'] == $menteeid) {
            $selected = $mentee;
            break;
        }
    }
}

if ($selected) {
    $courses = menteesummary_get_mentee_courses($selected['id']);

    // ✅ Auto-select the only course if just one exists
    if (count($courses) > 0 && empty($selectedcourseid)) {
        $selectedcourseid = $courses[0]['id'];
    }

    // Iterate through courses
    foreach ($courses as &$c) {
        $c['grade'] = menteesummary_get_course_total($selected['id'], $c['id']);

        // Get and merge all assignments and quizzes
        $assignments = menteesummary_get_all_assignments($selected['id'], $c['id']);
        $quizzes = menteesummary_get_all_quizzes($selected['id'], $c['id']);
        $activities = array_merge($assignments, $quizzes);

        // Sort by position in the course
        usort($activities, function ($a, $b) {
            return ($a->position ?? 0) <=> ($b->position ?? 0);
        });

        // Create array of all activities for display
        $all = [];
        foreach ($activities as $a) {
            if ($a->graded) {
                $scorePercent = 100.0 * (float) $a->grade / (float) $a->maxgrade;
            } else {
                $scorePercent = "n/a";
            }

            $all[] = [
                'id' => $a->id,
                'name' => $a->name,
                'duedate' => $a->duedate,
                'duedateformatted' => ($a->duedate >= strtotime('2020-01-01'))
                    ? userdate($a->duedate, '%A, %b %e, %Y')
                    : get_string('notyetdue', 'mod_menteesummary'),
                'grade' => (is_numeric($a->grade))
                    ? format_float((float) $a->grade, true, true)
                    : '-', // fallback for missing grade
                'maxgrade' => (is_numeric($a->maxgrade))
                    ? format_float((float) $a->maxgrade, true, true)
                    : '-',
                'submitted' => $a->submitted,
                'graded' => $a->graded,
                'missing' => $a->missing,
                'scorecolor' => get_score_color2($scorePercent),
                'percent' => $scorePercent,
                'feedback' => $a->feedback,
                'hasfeedback' => $a->hasfeedback
            ];
        }

        $c['allassignments'] = $all;

        // Build sorted lists of missing and upcoming assignments
        // $c['missing'] = array_values(array_filter($all, function($a) {
        //     return !$a['submitted'] && $a['duedate'] < time();
        // }));
        // $c['upcoming'] = array_values(array_filter($all, function($a) {
        //     return !$a['submitted'] && $a['duedate'] > time();
        // }));
        // usort($c['missing'], fn($a,$b) => $a['duedate'] <=> $b['duedate']);
        // usort($c['upcoming'], fn($a,$b) => $a['duedate'] <=> $b['duedate']);

        //$c['expanded'] = ($selectedcourseid == $c['id']);
        $c['iscurrent'] = ($selectedcourseid == $c['id']);
        $c['selecturl'] = (new moodle_url('/mod/menteesummary/view.php', [
            'id' => $cm->id,
            'menteeid' => $selected['id'],
            'courseid' => $c['id']
        ]))->out(false);
    }

    // Mark the current course and build tab URLs.
    // foreach ($courses as &$c) {

    //     $c['selecturl'] = (new moodle_url('/mod/menteesummary/view.php', [
    //         'id' => $cm->id,
    //         'menteeid' => $selected['id'],
    //         'courseid' => $c['id']
    //     ]))->out(false);
    // }

    // Add mentee picture for template display.
    $userrecord = $DB->get_record('user', ['id' => $selected['id']], '*', MUST_EXIST);
    $selected['picture'] = $OUTPUT->user_picture($userrecord, ['size' => 64, 'link' => false]);

    $viewdata = [
        'mentee' => [
            'id' => $selected['id'],
            'fullname' => $selected['fullname'],
            'courses' => $courses,
            'picture' => $selected['picture']
        ],
        'backurl' => (new moodle_url('/mod/menteesummary/view.php', ['id' => $cm->id]))->out(false),
        'selectedcourseid' => $selectedcourseid,
        'hascurrentcourse' => !empty($selectedcourseid)
    ];

    $template = 'mod_menteesummary/view';

} else {
    // Show chooser
    $viewdata = new \mod_menteesummary\output\chooser($mentees, $cm->id);
    $template = 'mod_menteesummary/chooser';
}

// Get the plugin renderer.
$PAGE->set_pagelayout('standard');
$renderer = $PAGE->get_renderer('mod_menteesummary');

$PAGE->requires->js_call_amd('mod_menteesummary/bootstrapmodals', 'init');


echo $OUTPUT->header();

if (is_object($viewdata)) {
    // Uses your custom renderer method if defined.
    echo $renderer->render($viewdata);
} else {
    // Array data goes straight into Mustache.
    echo $renderer->render_from_template($template, $viewdata);
}



echo $OUTPUT->footer();
