<?php
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_menteesummary_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $this->standard_intro_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
