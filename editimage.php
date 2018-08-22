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
 * Cards Format 
 *
 * @package    course/format
 * @subpackage cards
 * @version    See the value of '$plugin->version' in version.php.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* Imports */
require_once('../../../config.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/course/format/cards/editimage_form.php');
require_once($CFG->dirroot . '/course/format/cards/lib.php');

/* Page parameters */
                                    // $contextid = optional_param('contextid', PARAM_INT); //required_param
$courseid = required_param('courseid', PARAM_INT);
$sectionid = required_param('sectionid', PARAM_INT); //required_param
                                    //$id = optional_param('id', null, PARAM_INT);
$context = context_course::instance($courseid);

                                    /* No idea, copied this from an example. Sets form data options but I don't know what they all do exactly */

                                    // $formdata = new stdClass();
                                    // $formdata->userid = required_param('userid', PARAM_INT);
                                    // $formdata->offset = optional_param('offset', null, PARAM_INT);
                                    // $formdata->forcerefresh = optional_param('forcerefresh', null, PARAM_INT);
                                    // $formdata->mode = optional_param('mode', null, PARAM_ALPHA);

                                    // $url = new moodle_url('/course/format/cards/editimage.php', array(
                                    //     'contextid' => $contextid,
                                    //     'id' => $id,
                                    //     'offset' => $formdata->offset,
                                    //     'forcerefresh' => $formdata->forcerefresh,
                                    //     'userid' => $formdata->userid,
                                    //     'mode' => $formdata->mode));

                                    /* Not exactly sure what this stuff does, but it seems fairly straightforward */
                                    // list($context, $course, $cm) = get_context_info_array($contextid);

require_login($courseid, false);
if (isguestuser()) {
    die();
}

                                    //$PAGE->set_url($url);
$PAGE->set_url(new moodle_url('/course/format/cards/editimage.php', array('courseid' => $courseid)));
$PAGE->set_context($context);

/* Functional part. Create the form and display it, handle results, etc */
$options = array(
    'subdirs' => 0,
    'maxfiles' => 1,
    'accepted_types' => array('gif', 'jpe', 'jpeg', 'jpg', 'png'),
    'return_types' => FILE_INTERNAL);

$mform = new cards_image_form(null, array(
    'contextid' => $context->id,
    'courseid'  => $courseid,
    //'userid' => $formdata->userid,
    'sectionid' => $sectionid,
    'options' => $options));

if ($mform->is_cancelled()) {
    // Someone has hit the 'cancel' button.
    redirect(new moodle_url($CFG->wwwroot . '/course/view.php?id=' . $courseid));
} else if ($formdata = $mform->get_data()) { // Form has been submitted.
    if ($formdata->deleteimage == 1) {
        // Delete the old images....
                            //$courseformat = course_get_format($courseid);
                            //$courseformat->delete_image($sectionid, $context->id);
        $fs = get_file_storage();
        if ($files = $fs->get_area_files($context->id, 'course', 'section', $sectionid)) {
            foreach ($files as $file) {
                $file->delete();    
            }
        }
    } else if ($newfilename = $mform->get_new_filename('imagefile')) {
        $fs = get_file_storage();

                                // We have a new file so can delete the old....
                                //TODO
                                // $courseformat = course_get_format($courseid);
                                // $sectionimage = $courseformat->get_image($courseid, $sectionid);
                                // if (isset($sectionimage->image)) {
                                //     if ($file = $fs->get_file($context->id, 'course', 'section', $sectionid, '/', $sectionimage->image)) {
                                //         $file->delete();
                                //     }
                                // }

                                // Resize the new image and save it...
                                // $storedfilerecord = $courseformat->create_original_image_record($context->id, $sectionid, $newfilename);
                                // $tempfile = $mform->save_stored_file(
                                //         'imagefile',
                                //         $storedfilerecord['contextid'],
                                //         $storedfilerecord['component'],
                                //         $storedfilerecord['filearea'],
                                //         $storedfilerecord['itemid'],
                                //         $storedfilerecord['filepath'],
                                //         'temp.' . $storedfilerecord['filename'],
                                //         true);

        $savedimage = $mform->save_stored_file(
            'imagefile',
            $context->id,
            'course',
            'section',
            $sectionid,
            '/cards/',
            $newfilename,
            true);

                                //$courseformat->create_section_image($tempfile, $storedfilerecord, $sectionimage);
    }
    redirect($CFG->wwwroot . "/course/view.php?id=" . $courseid);
}

/* Draw the form */
echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
