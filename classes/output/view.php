<?php
namespace mod_menteesummary\output;

require_once(__DIR__ . '/locallib.php');

use renderable;
use templatable;
use renderer_base;

class view implements renderable, templatable {
    private $mentees;

    public function __construct($mentees) {
        $this->mentees = $mentees;
    }

    public function export_for_template(renderer_base $output) {
        $data = ['mentees' => []];
        foreach ($this->mentees as $m) {
            $data['mentees'][] = [
                'id' => $m->id,
                'fullname' => fullname($m),
                'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $m->id]))->out(false),
                'courses' => menteesummary_get_mentee_courses($m->id),
            ];
        }
        return $data;
    }
}
