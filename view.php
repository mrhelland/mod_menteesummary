<?php



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
        'id'         => $m->id,
        'fullname'   => ucwords(fullname($m)),
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
    if(count($courses) > 0 && empty($selectedcourseid)) {
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
        usort($activities, function($a, $b) {
            return ($a->position ?? 0) <=> ($b->position ?? 0);
        });

        // Create array of all activities for display
        $all = [];
        foreach ($activities as $a) {
            if($a->graded) {
                $scorePercent = 100.0 * (float)$a->grade / (float)$a->maxgrade;            
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
                                ? format_float((float)$a->grade, true, true)
                                : '-', // fallback for missing grade
                'maxgrade' => (is_numeric($a->maxgrade))
                                ? format_float((float)$a->maxgrade, true, true)
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

echo $OUTPUT->header();

if (is_object($viewdata)) {
    // Uses your custom renderer method if defined.
    echo $renderer->render($viewdata);
} else {
    // Array data goes straight into Mustache.
    echo $renderer->render_from_template($template, $viewdata);
}

echo $OUTPUT->footer();
