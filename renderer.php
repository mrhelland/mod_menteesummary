<?php
defined('MOODLE_INTERNAL') || die();

class mod_menteesummary_renderer extends plugin_renderer_base {
    /**
     * Renderer for \mod_menteesummary\output\view objects.
     *
     * Moodle will call this when you do $renderer->render($viewobj),
     * because the method name matches the class name with backslashes -> underscores.
     *
     * @param \mod_menteesummary\output\view $view
     * @return string HTML
     */
    public function render_mod_menteesummary_output_view(\mod_menteesummary\output\view $view) {
        return $this->render_from_template('mod_menteesummary/view', $view->export_for_template($this));
    }
}