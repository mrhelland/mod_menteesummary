<?php
defined('MOODLE_INTERNAL') || die();


/**
 * Determine if a mentor/parent can view a given mentee in a course.
 *
 * @param int $mentorid The current viewer (USER->id)
 * @param int $menteeid The target user being viewed
 * @param int $courseid The course context
 * @return bool True if allowed
 */
function menteesummary_user_can_view(int $mentorid, int $menteeid, int $courseid): bool {
    global $DB;

    // 1. Mentors can always view themselves (for testing)
    if ($mentorid === $menteeid) {
        return true;
    }

    // 2. Confirm mentor/mentee relationship (by role assignment).
    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.userid = :mentorid
               AND ctx.contextlevel = :usercontext
               AND ctx.instanceid = :menteeid";
    $params = [
        'mentorid' => $mentorid,
        'usercontext' => CONTEXT_USER,
        'menteeid' => $menteeid
    ];

    return $DB->record_exists_sql($sql, $params);
}

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
               gi.categoryid, 
               cm.id AS cmid,
               m.name AS modname,
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

    // 💠 ADDED: Preload all grade categories for this course (efficient lookup)
    $categories = $DB->get_records('grade_categories', ['courseid' => $courseid], '', 'id, fullname');
    $categorymap = [];
    foreach ($categories as $cat) {
        $categorymap[$cat->id] = $cat->fullname;
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
        $a->graded = !is_null($a->grade) && $a->grade !== '' && $a->grade !== false;
        $a->missing = !$a->graded && !$a->submitted;

        if ($a->graded && !$a->submitted) {
            $a->submitted = true;
        }

        // 💠 ADDED: Attach category name (fallback to "Uncategorised")
        if (!empty($a->categoryid) && isset($categorymap[$a->categoryid])) {
            $a->categoryname = $categorymap[$a->categoryid];
            $a->categoryweight = menteesummary_get_category_weight_percent($courseid, $a->categoryid);
        } else {
            $a->categoryname = get_string('uncategorised', 'grades');
            $a->categoryweight = "--";
        }


        // ✅ Fetch feedback if graded.
        if ($a->graded) {
            $feedbackArray = menteesummary_get_assignment_feedback($userid, $courseid, $a->id);
                    // Mustache expects an array of associative arrays if iterating
            $a->feedback = array_map(function($text) {
                                return ['text' => $text];
                            }, $feedbackArray);
            $a->hasfeedback = !empty($feedbackArray);
        } else {
            $a->feedback = [];
            $a->hasfeedback = false;
        }
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
                gi.categoryid,
                cm.id AS cmid,
                m.name as modname,
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

    // 💠 ADDED: Preload all grade categories for this course.
    $categories = $DB->get_records('grade_categories', ['courseid' => $courseid], '', 'id, fullname');
    $categorymap = [];
    foreach ($categories as $cat) {
        $categorymap[$cat->id] = $cat->fullname;
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
        $q->feedback = [];
        $q->hasfeedback = false;

        // 💠 ADDED: Attach the grading category name
        if (!empty($q->categoryid) && isset($categorymap[$q->categoryid])) {
            $q->categoryname = $categorymap[$q->categoryid];
            $q->categoryweight = menteesummary_get_category_weight_percent($courseid, $q->categoryid);
        } else {
            $q->categoryname = get_string('uncategorised', 'grades');
            $q->categoryweight = "--";
        }
    }

    

    return array_values($quizzes);
}

/**
 * Get teacher feedback for a single assignment as an array (unique entries only).
 *
 * @param int $userid
 * @param int $courseid
 * @param int $assignid
 * @return array Array of unique feedback strings.
 */
function menteesummary_get_assignment_feedback($userid, $courseid, $assignid) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $cm = get_coursemodule_from_instance('assign', $assignid, $courseid, false, IGNORE_MISSING);
    if (!$cm) {
        return [];
    }

    $context = context_module::instance($cm->id);
    $assign = new assign($context, $cm, $cm->course);

    // Get the user's grade record
    $grade = $DB->get_record('assign_grades', [
        'assignment' => $assignid,
        'userid' => $userid
    ]);

    if (!$grade) {
        return [];
    }

    $feedbacktexts = [];

    // --- 1. Gather from visible feedback plugins ---
    foreach ($assign->get_feedback_plugins() as $plugin) {
        if ($plugin->is_enabled() && $plugin->is_visible()) {
            $output = $plugin->view($grade);
            if (!empty($output)) {
                $feedbacktexts[] = trim(strip_tags($output));
            }
        }
    }

    // --- 2. Get direct comments (avoiding duplication) ---
    $comments = $DB->get_records('assignfeedback_comments', ['grade' => $grade->id]);
    foreach ($comments as $c) {
        if (!empty($c->commenttext)) {
            $text = trim(strip_tags($c->commenttext));
            if (!in_array($text, $feedbacktexts, true)) { // prevent duplicates
                $feedbacktexts[] = $text;
            }
        }
    }

    // --- 3. Return unique non-empty feedback texts ---
    $feedbacktexts = array_filter(array_unique($feedbacktexts));

    return array_values($feedbacktexts);
}

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

// /**
//  * Get the final course total grade for a user in a course.
//  *
//  * @param int $userid User ID
//  * @param int $courseid Course ID
//  * @return float|string Final grade or '-' if not available
//  */
// function menteesummary_get_course_total($userid, $courseid) {
//     global $CFG;
//     require_once($CFG->libdir . '/gradelib.php');
//     require_once($CFG->dirroot . '/grade/querylib.php');

//     // $coursegrade = grade_get_course_grades($courseid, $userid);
//     // print_object($coursegrade);

//     // if (!empty($coursegrade) && isset($coursegrade->grade)) {
//     //     $percentage = $coursegrade->grade; // This is the final grade (usually already a percentage).
//     //     return format_float($percentage, 1);
//     // }

//     $grades = grade_get_course_grades($courseid, $userid);

//     // $grades->grades[$userid] contains the user's course total info.
//     if (!empty($grades) && !empty($grades->grades) && isset($grades->grades[$userid])) {
//         $g = $grades->grades[$userid];
//         // Otherwise fall back to raw grade value (round/format).
//         if (isset($g->grade) && $g->grade !== null) {
//             return format_float($g->grade, 1);
//         }
//         // Prefer the already-formatted string if present (str_grade).
//         if (!empty($g->str_grade)) {
//             return (string)$g->str_grade;
//         }


//     }

//     return '-';
// }

/**
 * Compute a user's final grade using category weighting based on raw points.
 *
 * Category grade = (earned / possible)
 * Final grade = Σ( category_percent × normalized_category_weight )
 * 
 * THIS FUNCTION IS NOT PORTABLE AND NEEDS TO BE REWRITTEN TO RESPECT GRADEBOOK SETUP. THIS IS A STOP-GAP.
 *
 * @param array $assignments  Array from menteesummary_get_all_assignments()
 * @param array $quizzes      Array from menteesummary_get_all_quizzes()
 * @param bool $countmissingaszero   Count missing items as 0 points earned
 * @param bool $countungradedaszero  Count submitted-but-ungraded items as 0 points earned
 * @return array ['total' => string, 'categories' => array, 'gradedcount' => int, 'pendingcount' => int, 'missingcount' => int]
 */
function menteesummary_calculate_overall_grade(
    array $assignments,
    array $quizzes,
    bool $countmissingaszero = false,
    bool $countungradedaszero = false
): array {
    $result = [
        'total' => '-',
        'categories' => [],
        'gradedcount' => 0,
        'pendingcount' => 0,
        'missingcount' => 0
    ];

    $all = array_merge($assignments, $quizzes);
    if (empty($all)) {
        return $result;
    }

    // === 1. Aggregate raw earned/possible points per category ===
    $categories = [];

    foreach ($all as $item) {
        $catname = $item->categoryname ?? get_string('uncategorised', 'grades');
        $catweight = is_numeric($item->categoryweight) ? (float)$item->categoryweight : 1.0;

        if (!isset($categories[$catname])) {
            $categories[$catname] = [
                'earned' => 0.0,
                'possible' => 0.0,
                'weight' => $catweight,
                'gradedcount' => 0,
                'pendingcount' => 0,
                'missingcount' => 0
            ];
        }

        $earned = null;
        $possible = is_numeric($item->maxgrade) ? (float)$item->maxgrade : 0.0;

        // --- CASE 1: Graded ---
        if (!empty($item->graded) && is_numeric($item->grade)) {
            $earned = (float)$item->grade;
            $categories[$catname]['gradedcount']++;
            $result['gradedcount']++;
        }

        // --- CASE 2: Submitted but ungraded ---
        else if (!empty($item->submitted) && empty($item->graded)) {
            if ($countungradedaszero) {
                $earned = 0.0;
            } else {
                $categories[$catname]['pendingcount']++;
                $result['pendingcount']++;
                continue;
            }
        }

        // --- CASE 3: Missing (not submitted) ---
        else if (!empty($item->missing)) {
            if ($countmissingaszero) {
                $earned = 0.0;
            } else {
                $categories[$catname]['missingcount']++;
                $result['missingcount']++;
                continue;
            }
        } else {
            continue;
        }

        // Skip invalid items
        if ($possible <= 0) {
            continue;
        }

        $categories[$catname]['earned'] += $earned;
        $categories[$catname]['possible'] += $possible;
    }

    if (empty($categories)) {
        return $result;
    }

    // === 2. Compute total weight of all eligible categories ===
    $totalweight = 0.0;
    foreach ($categories as $c) {
        if ($c['possible'] > 0 && $c['weight'] > 0) {
            $totalweight += $c['weight'];
        }
    }
    if ($totalweight <= 0) {
        return $result;
    }

    // === 3. Compute category percentages and weighted contribution ===
    $finalpercent = 0.0;

    foreach ($categories as $catname => $c) {
        if ($c['possible'] <= 0) {
            $result['categories'][$catname] = [
                'percent' => '-',
                'weight' => $c['weight'],
                'effectiveweight' => 0,
                'gradedcount' => $c['gradedcount'],
                'pendingcount' => $c['pendingcount'],
                'missingcount' => $c['missingcount']
            ];
            continue;
        }

        $categorypercent = ($c['earned'] / $c['possible']) * 100.0;
        $effectiveweight = $c['weight'] / $totalweight;

        $finalpercent += $categorypercent * $effectiveweight;

        $result['categories'][$catname] = [
            'percent' => format_float($categorypercent, 1),
            'weight' => $c['weight'],
            'effectiveweight' => round($effectiveweight * 100, 1), // show as %
            'gradedcount' => $c['gradedcount'],
            'pendingcount' => $c['pendingcount'],
            'missingcount' => $c['missingcount'],
            'earned' => $c['earned'],
            'possible' => $c['possible']
        ];
    }

    // === 4. Store normalized total ===
    $result['total'] = format_float($finalpercent, 1);

    return $result;
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
 * Returns the effective percentage weight of a grade category within a course.
 *
 * This calculates the category’s relative contribution (as a percent)
 * of its siblings under the same parent category, based on the grade_items table.
 *
 * @param int $courseid The course ID.
 * @param int $categoryid The grade_categories.id of the category.
 * @return float|null The effective percentage weight (0–100), or null if not applicable.
 */
function menteesummary_get_category_weight_percent(int $courseid, int $categoryid): ?float {
    global $DB;

    // 1. Get the grade item corresponding to this category.
    $item = $DB->get_record('grade_items', [
        'courseid' => $courseid,
        'itemtype' => 'category',
        'iteminstance' => $categoryid
    ], 'id, categoryid, aggregationcoef', IGNORE_MISSING);

    if (!$item) {
        return null; // No category grade item found
    }

    // 2. Get all sibling category items under the same parent category.
    $siblings = $DB->get_records('grade_items', [
        'courseid' => $courseid,
        'itemtype' => 'category',
        'categoryid' => $item->categoryid
    ], '', 'id, aggregationcoef');

    if (empty($siblings)) {
        return null;
    }

    // 3. Compute total aggregation coefficients for normalization.
    $total = 0.0;
    foreach ($siblings as $sib) {
        $total += (float)$sib->aggregationcoef;
    }

    if ($total <= 0) {
        return null; // No meaningful weighting (e.g., Natural aggregation)
    }

    // 4. Calculate normalized percentage.
    $weight = ((float)$item->aggregationcoef / $total) * 100;

    // 5. Round for display
    return round($weight, 2);
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
    if ($percent < 50) {
        $hue = 0; // red
    } else if($percent >= 90) {
        $hue = 120;
    } else {
        // Map 50–100% → 0–120° hue (red → green)
        $hue = (($percent - 50) / 40) * 120;
    }

    // Use full saturation and medium lightness for vivid colors
    return "hsl($hue, 100%, 45%)";
}

/**
 * Returns an icon URL for a given category name.
 *
 * @param string $categoryname The name of the category.
 * @return string The pix URL of the icon.
 */
function menteesummary_get_category_icon(string $categoryname): string {
    global $OUTPUT;

    $name = strtolower(trim($categoryname));

    // Keyword-based icon matching.
    if (strpos($name, 'assignment') !== false || strpos($name, 'learning') !== false) {
        $icon = 'assignment_icon';
    } else if (strpos($name, 'test') !== false || strpos($name, 'quiz') !== false) {
        $icon = 'test_icon';
    } else if (strpos($name, 'final') !== false || strpos($name, 'summary') !== false) {
        $icon = 'final_icon';
    } else if (strpos($name, 'employability') !== false) {
        $icon = 'employability_icon';
    } else {
        // Optional: provide a default fallback icon.
        $icon = 'default_icon';
    }

    // Return a Moodle pix URL (e.g. pix_icon or image_url)
    return $OUTPUT->image_url($icon, 'mod_menteesummary')->out();
}