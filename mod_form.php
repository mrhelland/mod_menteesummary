<?php
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_menteesummary_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        // === Activity name (required field) ===
        $mform->addElement('text', 'name', get_string('activityname', 'mod_menteesummary'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'activityname', 'mod_menteesummary');

        $this->standard_intro_elements();




        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
