<?php
// This file is part of Moodle Course Administration Plugin
//
// Moodle is free software...

/**
 * @package     local_courseadministration
 * @author      
 * @license     
 */

 require_once(__DIR__ . '/../../config.php');

  // page configuration
  $PAGE->set_url('/local/courseadministration/index.php');
  $PAGE->set_context(context_system::instance());
  $PAGE->set_title('Course Administration');

  global $DB, $CFG, $USER;
  
 
 // Sørg for at brukeren er logget inn.
 require_login();
 

 
// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $courseid = required_param('courseid', PARAM_INT);
    $action = required_param('action', PARAM_ALPHA);

    //check current status of course
    $is_favorite = $DB->record_exists('local_courseadministration', ['userid' => $USER->id, 'courseid' => $courseid]);

    if ($action === 'favorite' && !$is_favorite) {
        // Add the course to favorites.
        $record = new stdClass();
        $record->userid = $USER->id;
        $record->courseid = $courseid;
        $record->timecreated = time();

        // Attempt to insert the record, handle duplicates.
        try {
            // check if course is already in favorites
            $DB->insert_record('local_courseadministration', $record);
            $messagetext = "Course ID $courseid has been added to favorites.";
            $messagetype = \core\output\notification::NOTIFY_SUCCESS;
        } catch (dml_duplicate_exception $e) {
            $messagetext = "Course ID $courseid is already in favorites.";
            $messagetype = \core\output\notification::NOTIFY_INFO;
        }
    } elseif ($action === 'unfavorite' && $is_favorite) {
        // Remove the course from favorites.
        $DB->delete_records('local_courseadministration', array('userid' => $USER->id, 'courseid' => $courseid));
        $messagetext = "Course ID $courseid has been removed from favorites.";
        $messagetype = \core\output\notification::NOTIFY_SUCCESS;
    }

    // Add the notification.
    \core\notification::add($messagetext, $messagetype);
}
 

 // include CSS
 $PAGE->requires->css('/local/courseadministration/styles.css');
 
 // Render header
 echo $OUTPUT->header();
 

 // Retrieve courses from database
 $courses = enrol_get_users_courses($USER->id, false, 'id, fullname, startdate, enddate', 'fullname ASC');


 foreach ($courses as &$course) {
    // Henter alle innmeldte brukere for kurset.
    $enrolled_users = enrol_get_course_users($course->id);

    // Opprett en array for å lagre kun fornavn og etternavn
    $course->users = [];

    // Gå gjennom hver bruker og legg til fornavn og etternavn i resultatet
    foreach ($enrolled_users as $user) {
        $course->users[] = (object)[
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
        ];
    }
}



    // Retrieve favorites from database
    $favorite_sql = "SELECT courseid 
    FROM {local_courseadministration} 
    WHERE userid = :userid";

    $favorites = $DB->get_fieldset_sql($favorite_sql, ['userid' => $USER->id]);

 //Check if the same course appear twice in $courses - if so drop the second one
    $unique_courses = [];
    $filtered_courses = [];

    foreach ($courses as $course) {
        if (!in_array($course->id, $unique_courses)) {
            // If course ID is not already in the unique_courses array, add it
            $unique_courses[] = $course->id;
            // Add the course to the filtered array
            $filtered_courses[] = $course;
        }
    }
    // Replace original $courses array with the filtered one
    $courses = $filtered_courses;


 //convert dates and check if course is favorite
 foreach ($courses as $course) {
     $course->startdate = ($course->startdate != 0) ? date('d/m/Y', (int)$course->startdate) : get_string('no_s_date', 'local_courseadministration');
     $course->enddate = ($course->enddate != 0) ? date('d/m/Y', (int)$course->enddate) : get_string('no_e_date', 'local_courseadministration');
 
     //check if course is favorite
     $course->is_favorite = in_array($course->id, $favorites);
 }

 //order corses by favorite
    usort($courses, function($a, $b) {
        if ($a->is_favorite === $b->is_favorite) {
            return 0;
        }
        return $a->is_favorite ? -1 : 1;
    });

 
 // prepare template context
 $templatecontext = (object)[
     'header' => get_string('courseadministration', 'local_courseadministration'),
     'cname' => get_string('cname', 'local_courseadministration'),
     'users' => get_string('users', 'local_courseadministration'),
     'category' => get_string('category', 'local_courseadministration'),
     'startdate' => get_string('startdate', 'local_courseadministration'),
     'enddate' => get_string('enddate', 'local_courseadministration'),
     'favorite' => get_string('favorite', 'local_courseadministration'),
     'show' => get_string('show', 'local_courseadministration'),
     'wwwroot' => $CFG->wwwroot,
     'courses' => array_values($courses),
 ];
 
 // Render the template
 echo $OUTPUT->render_from_template('local_courseadministration/index', $templatecontext);
 
 // Render footer
 echo $OUTPUT->footer();
 