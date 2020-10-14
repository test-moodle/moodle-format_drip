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
 * Renderer for outputting the drip course format.
 *
 * @package    format
 * @subpackage drip
 * @copyright  2020 onwards Solin (https://solin.co)
 * @author     Martijn (info@solin.nl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for drip format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_drip_renderer extends format_section_renderer_base {
    private $sectiondata = array(DRIPTYPE_DAYS => array(), DRIPTYPE_DATE => array());

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_drip_renderer::section_edit_control_items() only displays the 'Highlight' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing
        // capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'topics drip'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('driptopicoutline', 'format_drip');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        if (!$this->page->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                                               'name' => $highlightoff,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                                               'name' => $highlight,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();
        $numsections = course_get_format($course)->get_last_section_number();

        $format = course_get_format($course);
        $driptype = $format->get_course_driptype_with_default($course);

        $enrolstart = $format->get_enrolstart_with_default($driptype);

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others.
                if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display,
            // OR it is hidden but the course has a setting to display hidden sections as unavilable.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                    (!$thissection->visible && !$course->hiddensections);
            if (!$showsection) {
                continue;
            }

            $cancaccessformatsection = $format->can_access_section($thissection, $enrolstart);
            // Check if this section is not yet visible for the user based on the drip format settings.
            if (!$cancaccessformatsection && empty($course->showhiddendripsections)) {
                continue;
            }

            $showdripdaysinfo = (!$cancaccessformatsection && !empty($course->showhiddendripsections));

            if (!$this->page->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($showdripdaysinfo) {
                    echo $this->section_dripinfo($thissection, $course);
                } else if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($this->page->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // This is not stealth section or it is empty.
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

    }

    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            $format = course_get_format($course);
            $driptype = $format->get_course_driptype_with_default($course);
            $enrolstart = $format->get_enrolstart_with_default($driptype);

            $cancaccessformatsection = $format->can_access_section($section, $enrolstart);
            $showdripdaysinfo = (!$cancaccessformatsection && !empty($course->showhiddendripsections));

            // Only in the non-general sections.
            if (!$section->visible || $showdripdaysinfo) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $o .= html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => 'section main clearfix'.$sectionstyle,
            'role' => 'region',
            'aria-labelledby' => "sectionid-{$section->id}-title",
            'data-sectionid' => $section->section,
            'data-sectionreturnid' => $sectionreturn
        ]);

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o .= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o .= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null.
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one.
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));
        $o .= $this->output->heading($sectionname, 3, 'sectionname' . $classes, "sectionid-{$section->id}-title");

        $o .= $this->section_availability($section);

        $o .= html_writer::start_tag('div', array('class' => 'summary'));
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o .= html_writer::end_tag('div');

        return $o;
    }

    /**
     * Generate the display of the section drip info.
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    protected function section_dripinfo($section, $course) {
        $html = "";

        $format = course_get_format($course);
        $driptype = $format->get_course_driptype_with_default($course);

        $html = $format->get_dripsection_available_info($section->id, $driptype);
        return html_writer::tag('div', $html, array('class' => 'dripsection_info'));
    }

    /**
     * Generate a summary of a section for display on the 'course index page'
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param array    $mods (argument not used)
     * @return string HTML to output.
     */
    protected function section_summary($section, $course, $mods) {
        $classattr = 'section main section-summary clearfix';
        $linkclasses = '';

        $format = course_get_format($course);
        $driptype = $format->get_course_driptype_with_default($course);
        $enrolstart = $format->get_enrolstart_with_default($driptype);

        $cancaccessformatsection = $format->can_access_section($section, $enrolstart);
        if (!$cancaccessformatsection && empty($course->showhiddendripsections)) {
            return '';
        }

        $showdripdaysinfo = (!$cancaccessformatsection && !empty($course->showhiddendripsections));

        // If section is hidden then display grey section link.
        if (!$section->visible || $showdripdaysinfo) {
            $classattr .= ' hidden';
            $linkclasses .= ' dimmed_text';
        } else if (course_get_format($course)->is_section_current($section)) {
            $classattr .= ' current';
        }

        $title = get_section_name($course, $section);
        $o = '';
        $o .= html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => $classattr,
            'role' => 'region',
            'aria-label' => $title,
            'data-sectionid' => $section->section
        ]);

        $o .= html_writer::tag('div', '', array('class' => 'left side'));
        $o .= html_writer::tag('div', '', array('class' => 'right side'));
        $o .= html_writer::start_tag('div', array('class' => 'content'));

        if ($section->uservisible && $cancaccessformatsection) {
            $title = html_writer::tag('a', $title,
                    array('href' => course_get_url($course, $section->section), 'class' => $linkclasses));
        }
        $o .= $this->output->heading($title, 3, 'section-title');

        $o .= $this->section_availability($section);
        $o .= html_writer::start_tag('div', array('class' => 'summarytext'));

        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting
            // "Hidden sections are shown in collapsed form".
            $o .= $this->format_summary_text($section);
        }
        $o .= html_writer::end_tag('div');
        if ($showdripdaysinfo) {
            $o .= $this->section_dripinfo($section, $course);
        } else {
            $o .= $this->section_activity_summary($section, $course, null);
        }

        $o .= html_writer::end_tag('div');
        $o .= html_writer::end_tag('li');

        return $o;
    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $format = course_get_format($course);
        $driptype = $format->get_course_driptype_with_default($course);

        $enrolstart = $format->get_enrolstart_with_default($driptype);

        $sectioninfo = $modinfo->get_section_info($displaysection);
        $cancaccessformatsection = $format->can_access_section($sectioninfo, $enrolstart);

        // Can we view the section in question?
        if (!$sectioninfo || !$sectioninfo->uservisible || !$cancaccessformatsection) {
            // This section doesn't exist or is not available for the user.
            // We actually already check this in course/view.php but just in case exit from this function as well.
            print_error('unknowncoursesection', 'error', course_get_url($course),
                format_string($course->fullname));
        }

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, $displaysection);
        $thissection = $modinfo->get_section_info(0);
        if ($thissection->summary or !empty($modinfo->sections[0]) or $this->page->user_is_editing()) {
            echo $this->start_section_list();
            echo $this->section_header($thissection, $course, true, $displaysection);
            echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
            echo $this->courserenderer->course_section_add_cm_control($course, 0, $displaysection);
            echo $this->section_footer();
            echo $this->end_section_list();
        }

        // Start single-section div.
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        // Title attributes.
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        echo $sectiontitle;

        // Now the list of sections..
        echo $this->start_section_list();

        echo $this->section_header($thissection, $course, true, $displaysection);
        // Show completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();

        echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
        echo $this->courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection);
        echo $this->section_footer();
        echo $this->end_section_list();

        // Display section bottom navigation.
        $sectionbottomnav = '';
        $sectionbottomnav .= html_writer::start_tag('div', array('class' => 'section-navigation mdl-bottom'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        $sectionbottomnav .= html_writer::tag('div', $this->section_nav_selection($course, $sections, $displaysection),
            array('class' => 'mdl-align'));
        $sectionbottomnav .= html_writer::end_tag('div');
        echo $sectionbottomnav;

        // Close single-section div.
        echo html_writer::end_tag('div');
    }

    /**
     * Generate next/previous section links for naviation
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections The course_sections entries from the DB
     * @param int $sectionno The section number in the course which is being displayed
     * @return array associative array with previous and next section link
     */
    protected function get_nav_links($course, $sections, $sectionno) {
        // FIXME: This is really evil and should by using the navigation API.
        $course = course_get_format($course)->get_course();
        $canviewhidden = has_capability('moodle/course:viewhiddensections', context_course::instance($course->id))
            or !$course->hiddensections;

        $format = course_get_format($course);
        $driptype = $format->get_course_driptype_with_default($course);
        $modinfo = get_fast_modinfo($course);

        $enrolstart = $format->get_enrolstart_with_default($driptype);

        $links = array('previous' => '', 'next' => '');
        $back = $sectionno - 1;
        while ($back > 0 and empty($links['previous'])) {
            $sectioninfo = $modinfo->get_section_info($back);
            $cancaccessformatsection = $format->can_access_section($sectioninfo, $enrolstart);

            if ($canviewhidden || ($sections[$back]->uservisible && $cancaccessformatsection)) {
                $params = array();
                if (!$sections[$back]->visible) {
                    $params = array('class' => 'dimmed_text');
                }
                $previouslink = html_writer::tag('span', $this->output->larrow(), array('class' => 'larrow'));
                $previouslink .= get_section_name($course, $sections[$back]);
                $links['previous'] = html_writer::link(course_get_url($course, $back), $previouslink, $params);
            }
            $back--;
        }

        $forward = $sectionno + 1;
        $numsections = course_get_format($course)->get_last_section_number();
        while ($forward <= $numsections and empty($links['next'])) {
            $sectioninfo = $modinfo->get_section_info($forward);
            $cancaccessformatsection = $format->can_access_section($sectioninfo, $enrolstart);

            if ($canviewhidden || ($sections[$forward]->uservisible && $cancaccessformatsection)) {
                $params = array();
                if (!$sections[$forward]->visible) {
                    $params = array('class' => 'dimmed_text');
                }
                $nextlink = get_section_name($course, $sections[$forward]);
                $nextlink .= html_writer::tag('span', $this->output->rarrow(), array('class' => 'rarrow'));
                $links['next'] = html_writer::link(course_get_url($course, $forward), $nextlink, $params);
            }
            $forward++;
        }

        return $links;
    }

    /**
     * Generate the html for the 'Jump to' menu on a single section page.
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections The course_sections entries from the DB
     * @param $displaysection the current displayed section number.
     *
     * @return string HTML to output.
     */
    protected function section_nav_selection($course, $sections, $displaysection) {
        global $CFG;
        $o = '';
        $sectionmenu = array();
        $sectionmenu[course_get_url($course)->out(false)] = get_string('maincoursepage');
        $modinfo = get_fast_modinfo($course);
        $section = 1;
        $numsections = course_get_format($course)->get_last_section_number();

        $format = course_get_format($course);
        $driptype = $format->get_course_driptype_with_default($course);

        $enrolstart = $format->get_enrolstart_with_default($driptype);

        while ($section <= $numsections) {
            $thissection = $modinfo->get_section_info($section);
            $showsection = $thissection->uservisible or !$course->hiddensections;
            $cancaccessformatsection = $format->can_access_section($thissection, $enrolstart);

            if (($showsection) && ($section != $displaysection) &&
                ($url = course_get_url($course, $section)) && $cancaccessformatsection) {
                $sectionmenu[$url->out(false)] = get_section_name($course, $section);
            }
            $section++;
        }

        $select = new url_select($sectionmenu, '', array('' => get_string('jumpto')));
        $select->class = 'jumpmenu';
        $select->formid = 'sectionmenu';
        $o .= $this->output->render($select);

        return $o;
    }
}
