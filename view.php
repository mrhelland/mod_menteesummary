<?php

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

global $CFG, $DB, $USER, $PAGE, $OUTPUT;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/user/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$menteeid = optional_param('menteeid', 0, PARAM_INT);
$selectedcourseid = optional_param('courseid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('menteesummary', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/menteesummary:view', $context);

$PAGE->set_url('/mod/menteesummary/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading($course->fullname);

$mentees = [];

// Get mentees assigned to current user
$users = menteesummary_get_user_mentees($USER->id); 

foreach ($users as $m) {
    $pic = new user_picture($m);
    $pic->size = 64;
    $pic->includetoken = true;

    $mentees[] = [
        'id'         => $m->id,
        'fullname'   => ucwords(strtolower(fullname($m))),
        'username'   => $m->username,
        'profilepic' => $OUTPUT->user_picture($m, ['size' => 64, 'link' => false]),
        'profileurl' => (new moodle_url('/user/view.php', [
                            'id' => $m->id,
                            'course' => SITEID
                        ]))->out(false),
        'url'        => (new moodle_url('/mod/menteesummary/view.php', [
                            'id' => $cm->id,
                            'menteeid' => $m->id
                        ]))->out(false),
    ];
}

// 🔑 FIX: remove array_key_exists, manually locate mentee
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
    // 🔑 FIX: use array access not ->id
    $courses = menteesummary_get_mentee_courses($selected['id']);

    // ✅ Auto-select the only course if just one exists
    // if (count($courses) === 1 && empty($selectedcourseid)) {
    //     $selectedcourseid = $courses[0]['id'];
    // } 

    if(count($courses) > 0 && empty($selectedcourseid)) {
        $selectedcourseid = $courses[0]['id'];
    }

    foreach ($courses as &$c) {
        $c['grade'] = menteesummary_get_course_total($selected['id'], $c['id']);

        // All assignments
        $assignments = menteesummary_get_all_assignments($selected['id'], $c['id']);
        usort($assignments, fn($a,$b) => $a->duedate <=> $b->duedate);

        $all = [];
        foreach ($assignments as $a) {
            $all[] = [
                'id' => $a->id,
                'name' => $a->name,
                'duedate' => $a->duedate,
                'duedateformatted' => userdate($a->duedate),
                'grade' => is_null($a->grade) ? '-' : format_float($a->grade, 1),
                'maxgrade' => format_float($a->maxgrade, 1) ?? '-',
                'submitted' => $a->submitted,
                'graded' => !is_null($a->grade),
                'missing' => is_null($a->grade) && !$a->submitted
            ];
        }
        $c['allassignments'] = $all;

        // $c['missing'] = array_values(array_filter($all, fn($a) => $a['grade'] === '-' && $a['duedate'] < time()));
        // $c['upcoming'] = array_values(array_filter($all, fn($a) => $a['duedate'] > time()));
        $c['missing'] = array_values(array_filter($all, function($a) {
            return !$a['submitted'] && $a['duedate'] < time();
        }));

        $c['upcoming'] = array_values(array_filter($all, function($a) {
            return !$a['submitted'] && $a['duedate'] > time();
        }));




        usort($c['missing'], fn($a,$b) => $a['duedate'] <=> $b['duedate']);
        usort($c['upcoming'], fn($a,$b) => $a['duedate'] <=> $b['duedate']);

        $c['expanded'] = ($selectedcourseid == $c['id']);
        $c['selecturl'] = (new moodle_url('/mod/menteesummary/view.php', [
            'id' => $cm->id,
            'menteeid' => $selected['id'],
            'courseid' => $c['id']
        ]))->out(false);

        // --- Get the mentee's course total grade ---
        $c['grade'] = menteesummary_get_course_total($selected['id'], $c['id']);
    }

    // Mark the current course and build tab URLs.
    foreach ($courses as &$c) {
        $c['iscurrent'] = ($selectedcourseid == $c['id']);
        $c['selecturl'] = (new moodle_url('/mod/menteesummary/view.php', [
            'id' => $cm->id,
            'menteeid' => $selected['id'],
            'courseid' => $c['id']
        ]))->out(false);
    }

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

echo $OUTPUT->header();

if (is_object($viewdata)) {
    // Uses your custom renderer method if defined.
    echo $renderer->render($viewdata);
} else {
    // Array data goes straight into Mustache.
    echo $renderer->render_from_template($template, $viewdata);
}

echo $OUTPUT->footer();
