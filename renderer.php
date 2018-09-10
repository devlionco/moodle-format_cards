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
 * Defines renderer for course format cards
 *
 * @package    format_cards
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Renderer for cards format.
 *
 * @copyright 2012 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_cards_renderer extends plugin_renderer_base {
    /** @var core_course_renderer Stores instances of core_course_renderer */
    protected $courserenderer = null;

    /**
     * Constructor
     *
     * @param moodle_page $page
     * @param type $target
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courserenderer = $page->get_renderer('core', 'course');
    }

    /**
     * Generate the section title (with link if section is collapsed)
     *
     * @param int|section_info $section
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course, $supresslink = false) {
        global $CFG;
        if ((float)$CFG->version >= 2016052300) {
            // For Moodle 3.1 or later use inplace editable for displaying section name.
            $section = course_get_format($course)->get_section($section);
            return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, !$supresslink));
        }
        $title = get_section_name($course, $section);
        if (!$supresslink) {
            $url = course_get_url($course, $section, array('navigation' => true));
            if ($url) {
                $title = html_writer::link($url, $title);
            }
        }
        return $title;
    }

    /**
     * Calculates section progress in percents
     *
     * @param stdClass $section The course_section entry from DB.
     * @return int Progress in percents without sign '%'
     */
    protected function sectionprogress($section) {
        global $DB, $USER, $modinfo, $course;

        // get all current user's completions on current course
        $usercourseallcmcraw = $DB->get_records_sql("
        SELECT
            cmc.*
        FROM
            {course_modules} cm
            INNER JOIN {course_modules_completion} cmc ON cmc.coursemoduleid=cm.id
        WHERE
            cm.course=? AND cmc.userid=?", array($course->id, $USER->id));
        $usercmscompletions = array();
        foreach ($usercourseallcmcraw as $record) {
            //$usercourseallcmc[$record->coursemoduleid] = (array)$record;
            if ($record->completionstate <> 0) {
                $usercmscompletions[] = $record->coursemoduleid;
            }
        }

        // get current course's completable cms
        $ccompetablecms = array();
        $coursefminfo = get_fast_modinfo($course);
        foreach ($coursefminfo->get_cms() as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
                $ccompetablecms[] = $cm->id;
            }
        }

        $completedactivitiescount = 0;
        @$scms = $modinfo->sections[$section->section];     // get current section activities
        if (!empty($scms)) {
            $allcmsinsectioncount = count($scms);           // first count all cms in section
            foreach ($scms as $arid=>$scmid) {              // for each acivity in section
                if (!in_array($scmid, $ccompetablecms)) {
                    unset($scms[$arid]);                    // unset cms that are not  completable
                } else {
                    if (in_array($scmid, $usercmscompletions)) {
                        $completedactivitiescount++;        // if cm is compledted - count it
                    }
                }
            }
            $completablecmsinsectioncount = count($scms);   // count completable activities in section
            if (!empty($completablecmsinsectioncount)) {    // if section has at least 1 completable activity
                $csectionprogress = round($completedactivitiescount/$completablecmsinsectioncount*100);
            } else {
                $csectionprogress = 0;
            }
            return $csectionprogress;
        } else {
            return $csectionprogress = 0;
        }
    }

    /**
     * Generate html for a section summary text
     *
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function format_summary_text($section) {
        $context = context_course::instance($section->course);
        $summarytext = file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php',
            $context->id, 'course', 'section', $section->id);

        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        return format_text($summarytext, $section->summaryformat, $options);
    }

    /**
     * Display section and all its activities
     *
     * @param int|stdClass $course
     * @param int|section_info $section
     * @param int $sr section to return to (for building links)
     */
    public function display_section($course, $section, $sr, $sectioncounter = null) {
        global $PAGE;
        $course = course_get_format($course)->get_course();
        $section = course_get_format($course)->get_section($section);
        $context = context_course::instance($course->id);
        $contentvisible = true;
        $sectionnum = $section->section;

        if (!$section->uservisible || !course_get_format($course)->is_section_real_available($section)) {
            if ($section->visible && !$section->available && $section->availableinfo) {
                // Still display section but without content.
                $contentvisible = false;
            } else {
                return '';
            }
        }
        $movingsection = course_get_format($course)->is_moving_section();

        // if ($level === 0) {
        //     $cancelmovingcontrols = course_get_format($course)->get_edit_controls_cancelmoving();
        //     foreach ($cancelmovingcontrols as $control) {
        //         echo $this->render($control);
        //     }
        // }

        echo html_writer::start_tag('li',
                array('class' => "section main".
                    (course_get_format($course)->get_section($section)->pinned == FORMAT_cards_PINNED ? ' pinned ' : '').
                    (course_get_format($course)->is_section_current($section) ? ' current' : '').
                    (($section->visible && $contentvisible) ? '' : ' hidden'),
                    'id' => 'section-'.$sectionnum));

        // display controls except for expanded/collapsed
        $controls = course_get_format($course)->get_section_edit_controls($section, $sr);
        $leftcontent = $this->section_left_content($section, $course, false); // set $onsectionpage to false as here we have no section pages
        echo html_writer::tag('div', $leftcontent, array('class' => 'left side'));
        $collapsedcontrol = null;
        $pincontrol = '';

        $controlsstr = '';

        foreach ($controls as $idxcontrol => $control) {

            if ($control->class === 'expanded' || $control->class === 'collapsed') {
                $collapsedcontrol = $control;
            } else if ($control->class === 'pinned' || $control->class === 'unpinned' ) {
                if ($section->parent == 0) {
                    $pincontrol .= $this->render($control);
                }
            } else {
                $controlsstr .= $this->render($control);
            }
        }
        if (!empty($pincontrol) && !empty($controlsstr)) {
            $controlsstr = $pincontrol . $controlsstr;
        }
        if (!empty($controlsstr)) {
            echo html_writer::tag('div', $controlsstr, array('class' => 'controls'));
        }

        // display section content
        echo html_writer::start_tag('div', array('class' => 'content'));
        // display section name and expanded/collapsed control
        // if ($sectionnum != 0) {
            echo html_writer::start_tag('div', array('class' => 'section_wrap'));
            if ($title = $this->section_title($sectionnum, $course, !$contentvisible)) {
              if ($collapsedcontrol) {
                  $title = $this->render($collapsedcontrol). $title;
              }
              echo html_writer::tag('span', '', array('class' => 'sectionicon'));
              // echo html_writer::tag('span', $sectionnum ? $sectionnum : '', array('class' => 'sectionnumber'));
              echo html_writer::tag('span', $sectioncounter ? $sectioncounter : '', array('class' => 'sectionnumber'));
              echo html_writer::tag('h3', $title, array('class' => 'sectionname'));
              echo html_writer::tag('span', '', array('class' => 'sectiontoggle', 'data-handler' => 'toggleSection'));
          }
        echo html_writer::end_tag('div'); //end section_wrap


        echo $this->section_availability_message($section,
            has_capability('moodle/course:viewhiddensections', $context));

        // add progress bar to section header
        echo html_writer::start_tag('div', array('class' => 'sectionprogress-wrapper'));
            echo html_writer::start_tag('div', array('class' => 'sectionprogress'));
              echo html_writer::tag('span', $this->sectionprogress($section).'%', array(
                  'class' => 'sectionprogress-percent',
                  'style' => "width: ".$this->sectionprogress($section)."%",
              ));
              echo html_writer::tag('div', '',
                array(
                  'class' => 'sectionprogress-bar',
                  'role'  => "progressbar",
                  'style' => "width: ".$this->sectionprogress($section)."%",
                  'aria-valuenow' => $this->sectionprogress($section),
                  'aria-valuemin' => "0",
                  'aria-valuemax' => "100",
                )
              );
            echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');

        // display section description (if needed)
        if ($sectionnum == 0) {
          if ($contentvisible && ($summary = $this->format_summary_text($section))) {
              echo html_writer::tag('div', $summary, array('class' => 'summary'));
          } else {
              echo html_writer::tag('div', '', array('class' => 'summary nosummary'));
          }
        }
        if ($sectionnum != 0) {
            // get section custom image covers
            $sectionimageurl = '';
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'course', 'section', $section->id)) {
                foreach ($files as $file) {
                    if ($file->get_filename() != '.'){
                        $sectionimageurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                    }
                }
            }

                // render section cover images
                if ($contentvisible && $sectionimageurl) {
                echo html_writer::tag('div', '', array('class' => 'summary nosummary', 'style' => 'background-image: url('.$sectionimageurl.');'));
                    // show link to edit custom section image
                    if ($PAGE->user_is_editing()) {
                        $edimicon = new pix_icon('t/edit', '', 'moodle');
                        $edimurl = new moodle_url('/course/format/cards/editimage.php', array('courseid' => $course->id, 'sectionid' => $section->id));
                        $edimaction = new action_link($edimurl, get_string('editimage', 'format_cards'), null, array('class'=>'change_image'), $edimicon);
                        echo $this->render($edimaction);
                    }
                } else {
                // $defaultimageurl = $this->courserenderer->image_url('section-default-bg', 'format_cards');
                $defaultimageurl = $this->courserenderer->image_url('random/'.rand(1,8), 'format_cards');
                echo html_writer::tag('div', '', array('class' => 'summary nosummary', 'style' => 'background-image: url('.$defaultimageurl.');'));
                // show link to edit custom section image
                if ($PAGE->user_is_editing()) {
                    $edimicon = new pix_icon('t/edit', '', 'moodle');
                    $edimurl = new moodle_url('/course/format/cards/editimage.php', array('courseid' => $course->id, 'sectionid' => $section->id));
                    $edimaction = new action_link($edimurl, get_string('editimage', 'format_cards'), null, array('class'=>'change_image'), $edimicon);
                    echo $this->render($edimaction);
                }
            }
        }

        // display section contents (activities and subsections)
        if ($contentvisible) {
            // display resources and activities
            // if ($sectionnum != 0) {

            if ($PAGE->user_is_editing()) {
                  // a little hack to allow use drag&drop for moving activities if the section is empty
                  // if (empty(get_fast_modinfo($course)->sections[$sectionnum])) {
                  //     echo "<ul class=\"section img-text\">\n</ul>\n";
                  // }
                  //echo $this->courserenderer->course_section_add_cm_control($course, $sectionnum, $sr);
              }
              echo $this->courserenderer->course_section_cm_list($course, $section, $sr);
            // }


        }
        echo html_writer::end_tag('div'); // .content
        echo html_writer::end_tag('li'); // .section
    }

    /**
     * Display all sections
     *
     * @param int|stdClass $course
     * @param int|section_info $section
     * @param int $sr section to return to (for building links)
     */
    public function display_sections ($course, $sr) {
      global $PAGE;

      $course = course_get_format($course)->get_course();
      // $section = course_get_format($course)->get_section($section);
      $context = context_course::instance($course->id);
      $contentvisible = true;
      // $sectionnum = $section->section;

      if (!$PAGE->user_is_editing()) {
          $topunit_btn = '<span class = "openall">'.get_string('openall', 'format_cards').'</span><spam class = "closeall">'.get_string('closeall', 'format_cards').'</span>';
          echo html_writer::start_tag('ul', array('class' => 'flexsections'));
          echo html_writer::start_tag('div', array('class' => 'topunit display__none'));
          echo html_writer::tag('span', get_string('topunit', 'format_cards'), array('class' => 'topunit_name'));
          // echo html_writer::tag('button', $topunit_btn, array('class' => 'topunit_btn', 'data-handler' => 'openall'));
          echo html_writer::end_tag('div');
      }
      // display sections
      // $children = course_get_format($course)->get_subsections($sectionnum);
      $sections = course_get_format($course)->get_sections();
      if (!empty($sections)) {
          echo html_writer::start_tag('ul', array('class' => 'sections'));

          foreach ($sections as $section) {
            if (course_get_format($course)->get_section($section)->section == 0) {
              $this->display_section($course, $section, $sr);
            }
          }
          $pinendcount = 0;
          foreach ($sections as $section) {
            if (course_get_format($course)->get_section($section)->pinned == FORMAT_cards_PINNED) {
              $this->display_section($course, $section, $sr);
              $pinendcount++;
            }
          }
          if ($pinendcount) echo html_writer::tag('li', '<div class = "summary"></div>', array('class'=>'section', 'style'=>'width: '. 20*(4-$pinendcount).'%'));

          $sectioncounter = 1;
          foreach ($sections as $section) {
            if (course_get_format($course)->get_section($section)->pinned == FORMAT_cards_UNPINNED && course_get_format($course)->get_section($section)->section) {
              $this->display_section($course, $section, $sr, $sectioncounter);
              $sectioncounter++;
            }

          }
          echo html_writer::end_tag('ul');
      }

    // this code is for adding subsections - here we have no subsections in format_cards
    //   if ($addsectioncontrol = course_get_format($course)->get_add_section_control($sectionnum) and $sectionnum == 0) {
    //     echo $this->render($addsectioncontrol);
    //   }

    }

    /**
     * Generate the content to displayed on the left part of a section
     * before course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_left_content($section, $course, $onsectionpage) {
        $o = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (course_get_format($course)->is_section_current($section)) {
                $o = get_accesshide(get_string('currentsection', 'format_'.$course->format));
            }
        }

        return $o;
    }

    /**
     * Displays the target div for moving section (in 'moving' mode only)
     *
     * @param int|stdClass $courseorid current course
     * @param int|section_info $parent new parent section
     * @param null|int|section_info $before number of section before which we want to insert (or null if in the end)
     */
    // protected function display_insert_section_here($courseorid, $parent, $before = null, $sr = null) {
    //     if ($control = course_get_format($courseorid)->get_edit_control_movehere($parent, $before, $sr)) {
    //         echo $this->render($control);
    //     }
    // }

    /**
     * renders HTML for format_cards_edit_control
     *
     * @param format_cards_edit_control $control
     * @return string
     */
    protected function render_format_cards_edit_control(format_cards_edit_control $control) {
        if (!$control) {
            return '';
        }

        if ($control->class === 'movehere') {
            $icon = new pix_icon('movehere', $control->text, 'moodle', array('class' => 'movetarget', 'title' => $control->text));
            $action = new action_link($control->url, $icon, null, array('class' => $control->class));
            return html_writer::tag('li', $this->render($action), array('class' => 'movehere'));
        } else if ($control->class === 'cancelmovingsection' || $control->class === 'cancelmovingactivity') {
            return html_writer::tag('div', html_writer::link($control->url, $control->text),
                    array('class' => 'cancelmoving '.$control->class));
        } else if ($control->class === 'addsection') {
            $icon = new pix_icon('t/add', '', 'moodle', array('class' => 'iconsmall'));
            $text = $this->render($icon). html_writer::tag('span', $control->text, array('class' => $control->class.'-text'));
            $action = new action_link($control->url, $text, null, array('class' => $control->class));
            return html_writer::tag('div', $this->render($action), array('class' => 'mdl-right'));
        } else if ($control->class === 'backto') {
            $icon = new pix_icon('t/up', '', 'moodle');
            $text = $this->render($icon). html_writer::tag('span', $control->text, array('class' => $control->class.'-text'));
            return html_writer::tag('div', html_writer::link($control->url, $text),
                    array('class' => 'header '.$control->class));
        } else if ($control->class === 'settings' || $control->class === 'marker' || $control->class === 'marked') {
            $icon = new pix_icon('i/'. $control->class, $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'move' || $control->class === 'expanded' || $control->class === 'collapsed' ||
                $control->class === 'hide' || $control->class === 'show' || $control->class === 'delete') {
            $icon = new pix_icon('t/'. $control->class, $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'mergeup') {
            $icon = new pix_icon('mergeup', $control->text, 'format_cards', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'pinned') {
            $icon = new pix_icon('i/unlock', $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'unpinned') {
            $icon = new pix_icon('i/lock', $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        }
        if (isset($icon)) {
            if ($control->url) {
                // icon with a link
                $action = new action_link($control->url, $icon, null, array('class' => $control->class));
                return $this->render($action);
            } else {
                // just icon
                return html_writer::tag('span', $this->render($icon), array('class' => $control->class));
            }
        }
        // unknown control
        return ' '. html_writer::link($control->url, $control->text, array('class' => $control->class)). '';
    }

    /**
     * If section is not visible, display the message about that ('Not available
     * until...', that sort of thing). Otherwise, returns blank.
     *
     * For users with the ability to view hidden sections, it shows the
     * information even though you can view the section and also may include
     * slightly fuller information (so that teachers can tell when sections
     * are going to be unavailable etc). This logic is the same as for
     * activities.
     *
     * @param stdClass $section The course_section entry from DB
     * @param bool $canviewhidden True if user can view hidden sections
     * @return string HTML to output
     */
    protected function section_availability_message($section, $canviewhidden) {
        global $CFG;
        $o = '';
        if (!$section->uservisible) {
            // Note: We only get to this function if availableinfo is non-empty,
            // so there is definitely something to print.
            $formattedinfo = \core_availability\info::format_info(
                $section->availableinfo, $section->course);
            $o .= html_writer::div($formattedinfo, 'availabilityinfo');
        } else if ($canviewhidden && !empty($CFG->enableavailability) && $section->visible) {
            $ci = new \core_availability\info_section($section);
            $fullinfo = $ci->get_full_information();
            if ($fullinfo) {
                $formattedinfo = \core_availability\info::format_info(
                    $fullinfo, $section->course);
                $o .= html_writer::div($formattedinfo, 'availabilityinfo');
            }
        }
        return $o;
    }

    /**
     * Displays a confirmation dialogue when deleting the section (for non-JS mode)
     *
     * @param stdClass $course
     * @param int $sectionreturn
     * @param int $deletesection
     */
    public function confirm_delete_section($course, $sectionreturn, $deletesection) {
        echo $this->box_start('noticebox');
        $courseurl = course_get_url($course, $sectionreturn);
        $optionsyes = array('confirm' => 1, 'deletesection' => $deletesection, 'sesskey' => sesskey());
        $formcontinue = new single_button(new moodle_url($courseurl, $optionsyes), get_string('yes'));
        $formcancel = new single_button($courseurl, get_string('no'), 'get');
        echo $this->confirm(get_string('confirmdelete', 'format_cards'), $formcontinue, $formcancel);
        echo $this->box_end();
    }
}
