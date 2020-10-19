<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains main class for the course format Topic
 *
 * @package    format
 * @subpackage drip
 * @copyright  2020 onwards Solin (https://solin.co)
 * @author     Martijn (info@solin.nl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

define('DRIPTYPE_DAYS', 'dripdays');
define('DRIPTYPE_DATE', 'dripdate');

/**
 * Main class for the Drip course format
 *
 * @package    format
 * @subpackage drip
 * @copyright  2020 onwards Solin (https://solin.co)
 * @author     Martijn (info@solin.nl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_drip extends format_base {
    private static $sectionformatoptions;

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the drip course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_drip');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Drip format uses the following options:
     * - coursedisplay
     * - driptype
     * - showhiddendripsections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ),
                'driptype' => array(
                    'default' => DRIPTYPE_DAYS,
                    'type' => PARAM_TEXT,
                ),
                'showhiddendripsections' => array(
                    'default' => 0,
                    'type' => PARAM_BOOL,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = array(
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ),
                'driptype' => array(
                    'label' => new lang_string('driptype', 'format_drip'),
                    'help' => 'driptype',
                    'help_component' => 'format_drip',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            DRIPTYPE_DAYS => new lang_string('driptype_days', 'format_drip'),
                            DRIPTYPE_DATE => new lang_string('driptype_date', 'format_drip')
                        )
                    ),
                ),
                'showhiddendripsections' => array(
                    'label' => new lang_string('showhiddendripsections', 'format_drip'),
                    'help' => 'showhiddendripsections',
                    'help_component' => 'format_drip',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('no'),
                            1 => new lang_string('yes')
                        )
                    ),
                ),
            );

            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }

        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;

        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'drip', we try to copy option
     * 'coursedisplay' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }

        return $this->update_format_options($data);
    }

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_drip');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_drip', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'drip' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_drip');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }

    /**
     * Prepares values of course or section format options before storing them in DB
     *
     * If an option has invalid value it is not returned
     *
     * @param array $rawdata associative array of the proposed course/section format options
     * @param int|null $sectionid null if it is course format option
     * @return array array of options that have valid values
     */
    protected function validate_format_options(array $rawdata, int $sectionid = null) : array {

        if (!$sectionid) {
            $allformatoptions = $this->course_format_options(true);
        } else {
            $allformatoptions = $this->section_format_options(true);
        }
        $data = array_intersect_key($rawdata, $allformatoptions);
        foreach ($data as $key => $value) {
            $option = $allformatoptions[$key] + ['type' => PARAM_RAW, 'element_type' => null, 'element_attributes' => [[]]];
            $data[$key] = clean_param($value, $option['type']);
            if ($option['element_type'] === 'select' && !array_key_exists($data[$key], $option['element_attributes'][0])) {
                // Value invalid for select element, skip.
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Definitions of the additional options that this course format uses for section.
     *
     * @param bool $foreditform
     * @return array
     */
    public function section_format_options($foreditform = false) {
        static $sectionformatoptions = false;

        if ($sectionformatoptions === false) {
            if (!empty($this->course->driptype)) {
                $driptype = $this->course->driptype;
            } else if (!empty($this->courseid)) {
                $driptype = $this->get_course_driptype($this->courseid);
            }

            if (empty($driptype)) {
                $driptype = DRIPTYPE_DAYS;
            }

            switch ($driptype) {
                case DRIPTYPE_DAYS:
                    $sectionformatoptions = array(
                        'dripdays' => array(
                            'label' => new lang_string('dripdays', 'format_drip'),
                            'element_type' => 'text',
                            'help' => 'dripdayselement',
                            'help_component' => 'format_drip',
                            'type' => PARAM_INT,
                            'name' => 'dripdays'
                        ),
                    );
                    break;

                case DRIPTYPE_DATE:
                    $sectionformatoptions = array(
                        'dripdate' => array(
                            'label' => new lang_string('dripdate', 'format_drip'),
                            'element_type' => 'date_time_selector',
                            'help' => 'dripdateelement',
                            'help_component' => 'format_drip',
                            'type' => PARAM_INT,
                            'name' => 'dripdate',
                            'element_attributes' => array(
                                array('optional' => true)
                            )
                        ),
                    );
                    break;
            }
        }

        return $sectionformatoptions;
    }

    /**
     * Fills the section data with drip info.
     *
     */
    public function fill_sectiondata() {
        global $DB;

        $sectiondata = array();
        $driptype = $this->course->driptype;

        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->get_section_info_all() as $section) {
            $params = array('courseid' => $this->course->id, 'format' => $this->format,
                                'sectionid' => $section->id, 'name' => $driptype);
            $sectiondata[$section->id] = $DB->get_field('course_format_options', 'value', $params);
        }

        $this->dripsectiondata = $sectiondata;
    }

    /**
     * Checks if the current user can access the section.
     *
     * @param object - the section that is accessed.
     * @param int - the startdate of the enrolment.
     * @return bool - whether the user can access it.
     */
    public function can_access_section($section, $enrolstart) {
        global $USER, $DB, $PAGE;

        // Site admin can always access it.
        if (is_siteadmin()) {
            return true;
        }

        $context = context_course::instance($this->course->id);

        // Someone who is editing the course can always access.
        if (($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) ||
            has_capability('moodle/course:update', $context)) {
            return true;
        }

        if (empty($this->dripsectiondata)) {
            $this->fill_sectiondata();
        }

        $now = time();

        // Check the drip format settings.
        switch ($this->course->driptype) {
            case DRIPTYPE_DAYS:
                $days = 0;
                if (!empty($this->dripsectiondata[$section->id])) {
                    $days = $this->dripsectiondata[$section->id];
                }

                $opentime = mktime(0, 0, 0, date("n", $enrolstart), date("j", $enrolstart) + $days, date("Y", $enrolstart));
                break;

            case DRIPTYPE_DATE:
                $opentime = $this->dripsectiondata[$section->id];
                break;
        }

        if ($opentime < $now) {
            return true;
        }

        return false;
    }

    /**
     * Get the startdate of the enrolment for the current user.
     *
     * @return int - timestamp of the startdate.
     */
    public function get_enrolment_start() {
        global $USER, $DB;

        $sql = "SELECT ue.timestart
        FROM {user_enrolments} ue
        JOIN {enrol} e ON ue.enrolid = e.id
        WHERE e.courseid = :courseid
        AND ue.userid = :userid
        AND ue.status = :enrolstatus
        ORDER BY ue.timestart ASC
        LIMIT 1";
        $params = array('courseid' => $this->course->id, 'userid' => $USER->id, 'enrolstatus' => ENROL_USER_ACTIVE);

        return $DB->get_field_sql($sql, $params);
    }

    /**
     * Get the driptype of the current course.
     *
     * @param int - the id of the course.
     * @return string - the current driptype.
     */
    public function get_course_driptype($courseid) {
        global $DB;

        $params = array('courseid' => $courseid, 'format' => 'drip', 'sectionid' => 0, 'name' => 'driptype');
        return $DB->get_field('course_format_options', 'value', $params);
    }

    /**
     * Get the available info for a drip section.
     *
     * @param int - the id of the section.
     * @return string - the current driptype.
     */
    public function get_dripsection_available_info($sectionid, $driptype) {
        global $DB;

        switch ($driptype) {
            case DRIPTYPE_DAYS:
                $days = 0;
                if (!empty($this->dripsectiondata[$sectionid])) {
                    $days = $this->dripsectiondata[$sectionid];
                }

                $enrolstart = $this->get_enrolment_start();

                $opentime = mktime(0, 0, 0, date("n", $enrolstart), date("j", $enrolstart) + $days, date("Y", $enrolstart));
                break;

            case DRIPTYPE_DATE:
                $opentime = $this->dripsectiondata[$sectionid];
                break;
        }

        $info = get_string('dripsection_info', 'format_drip', userdate($opentime));

        return $info;
    }

    /**
     * Get the current driptype for a course or the default.
     *
     * @param stdClass - The course entry from DB
     * @return string - the current driptype.
     */
    public function get_course_driptype_with_default($course) {
        $dripformat = (!empty($course->driptype) ? $course->driptype : DRIPTYPE_DAYS);

        return $dripformat;
    }

    /**
     * Get the startdate of the enrolment for the current user with a default 0.
     *
     * @param string - the current driptype.
     * @return int - timestamp of the startdate.
     */
    public function get_enrolstart_with_default($driptype) {
        $enrolstart = 0;
        if ($driptype == DRIPTYPE_DAYS) {
            $enrolstart = $this->get_enrolment_start();
        }

        return $enrolstart;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_drip_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'drip'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}