<?php
namespace mod_menteesummary\output;

use renderable;
use templatable;
use renderer_base;

/**
 * Renderable object for the mentee chooser screen.
 *
 * This provides data to the Mustache template templates/chooser.mustache.
 */
class chooser implements renderable, templatable {
    /** @var array mentees data (id, fullname, username, profilepicurl, etc.) */
    protected $mentees;

    /** @var int course module id */
    protected $cmid;

    /**
     * Constructor.
     *
     * @param array $mentees Array of mentee data
     * @param int $cmid Course module ID
     */
    public function __construct(array $mentees, int $cmid) {
        $this->mentees = $mentees;
        $this->cmid = $cmid;
    }

    /**
     * Export data for template rendering.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'mentees' => $this->mentees,
            'cmid'    => $this->cmid,
        ];
    }
}
