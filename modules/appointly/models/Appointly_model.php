<?php defined('BASEPATH') or exit('No direct script access allowed');

class Appointly_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointly/appointly_attendees_model', 'atm');
    }


    /**
     * Insert new appointment
     *
     * @param [array] $data
     * @return boolean
     */
    public function insert_appointment($data)
    {
        $attendees = [];
        $relation = $data['rel_type'];
        $external_cid = null;
        unset($data['rel_type']);

        if ($relation == 'internal') {
            $data['created_by'] = get_staff_user_id();
            $data['source'] = 'internal';
            $attendees = $data['attendees'];
            unset($data['attendees']);
        } else {
            if (isset($data['attendees'])) {
                /**
                 * Means it is comming from inside crm form as External not internal (Contact)
                 */
                $data['created_by'] = get_staff_user_id();
                $external_cid = $data['contact_id'];
                $data['contact_id'] = NULL;
                /**
                 * We are setting source to external because it is relation is marked as an External Contact
                 */
                $data['source'] = 'external';
                $attendees = $data['attendees'];
                unset($data['attendees']);

                if (is_admin() || (staff_can('view_own', 'appointments') || staff_can('view', 'appointments'))) {
                    $data['approved'] = 1;
                }
            }
        }

        if (is_admin() && $relation == 'internal' || (staff_can('view_own', 'appointments') || staff_can('view', 'appointments')) && $relation == 'internal') {
            $data['approved'] = 1;
        }

        // Remove white spaces from phone number
        // In case is sent from external form as internal client when logged in 
        if (!empty($data['phone'])) {
            $data['phone'] = preg_replace('/\s+/', '', $data['phone']);
        }

        if ($data['source'] == 'internal' && empty($data['email'])) {
            $contact_data = get_appointment_contact_details($data['contact_id']);
            $data['email'] = $contact_data['email'];
            $data['name'] = $contact_data['full_name'];
            $data['phone'] = $contact_data['phone'];
        }

        if (appointlyGoogleAuth()) {
            if (isset($data['google'])) {
                $data['external_contact_id'] = $external_cid;

                $googleEvent = insertAppointmentToGoogleCalendar($data, $attendees);
                $data['google_event_id'] = $googleEvent['google_event_id'];
                $data['google_calendar_link'] = $googleEvent['htmlLink'];
                $data['google_added_by_id'] = get_staff_user_id();

                unset($data['google']);
                unset($data['external_contact_id']);
            }
        }

        $data = array_merge($data, $this->convertDateForDatabase($data['date']));

        $data = $this->handleDataReminderFields($data);

        $data['hash'] = app_generate_hash();

        $this->db->insert(db_prefix() . 'appointly_appointments', $data);

        $appointment_id = $this->db->insert_id();

        $this->atm->create($appointment_id, $attendees);

        $this->appointment_approve_notification_and_sms_triggers($appointment_id);

        $responsiblePerson = get_option('appointly_responsible_person');

        if (!empty($responsiblePerson)) {
            add_notification([
                'description'     => 'appointment_new_appointment_submitted',
                'touserid'        => $responsiblePerson,
                'fromcompany'     => true,
                'link'            => 'appointly/appointments/view?appointment_id=' . $appointment_id,
            ]);
        }

        return true;
    }

    public function add_event_to_google_calendar($data)
    {
        $result = [
            'result' => 'error',
            'message' => _l(
                'Oops, something went wrong, please try again...'
            )
        ];

        if (appointlyGoogleAuth()) {

            if (isset($data['google_added_by_id']) && $data['google_added_by_id'] == null) {
                unset($data['rel_type']);
                $responsiblePerson = get_option('appointly_responsible_person');

                $googleEvent = insertAppointmentToGoogleCalendar($data, $data['attendees']);

                $data['google_event_id'] = $googleEvent['google_event_id'];
                $data['google_calendar_link'] = $googleEvent['htmlLink'];
                $data['google_added_by_id'] = ($responsiblePerson) ? $responsiblePerson : get_staff_user_id();

                $data['id'] = $data['appointment_id'];

                if ($googleEvent) {
                    unset($data['appointment_id']);
                    unset($data['attendees']);
                    $this->db->where('id', $data['id']);
                    $this->db->update(db_prefix() . 'appointly_appointments', $data);
                    if ($this->db->affected_rows() !== 0) {
                        return [
                            'result' => 'success',
                            'message' => _l(
                                'appointments_added_to_google_calendar'
                            )
                        ];
                    }
                }
                return $result;
            }
        }
        return $result;
    }

    /** 
     * Inserts appointment submitted from external clients form
     * @param array $data
     * @return boolean
     */
    public function insert_external_appointment($data)
    {
        $data['hash'] = app_generate_hash();

        if ($data['phone']) {
            $data['phone'] = preg_replace('/\s+/', '', $data['phone']);
        }


        $data = array_merge($data, $this->convertDateForDatabase($data['date']));

        $responsiblePerson = get_option('appointly_responsible_person');

        if (get_option('appointly_client_meeting_approved_default')) {
            $data['approved'] = 1;
        }

        $this->db->insert(db_prefix() . 'appointly_appointments', $data);
        $appointment_id = $this->db->insert_id();

        if (get_option('appointly_client_meeting_approved_default')) {
            // If responsible person is set add as main attendee else first admin craeted with id 1
            $this->atm->create($appointment_id, [($responsiblePerson) ? $responsiblePerson : '1']);
        }


        if ($responsiblePerson !== '') {
            add_notification([
                'description'     => 'appointment_new_appointment_submitted',
                'touserid'        => $responsiblePerson,
                'fromcompany'     => true,
                'link'            => 'appointly/appointments/view?appointment_id=' . $appointment_id,
            ]);
        }
        return true;
    }

    /**
     * Update existing appointment
     *
     * @param [array] $data
     * @return boolean
     */
    public function update_appointment($data)
    {
        unset($data['rel_type']);

        if (isset($data['email'])) {
            $contact_form_email = $data['email']; // Current contact email sent from form
        }

        $current_contact = $this->atm->get_contact_email($data); // Current contact email saved in database

        $current_attendees = $this->atm->attendees($data['appointment_id']);

        // Remove white spaces 
        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/\s+/', '', $data['phone']);
        }

        $data = $this->handleDataReminderFields($data);

        if ($data['contact_id'] == 0) {
            unset($data['contact_id']);
        }

        if (appointlyGoogleAuth()) {
            // If appointments is in google calendar then -> update 
            if (isset($data['google_added_by_id']) && $data['google_added_by_id'] == get_staff_user_id()) {
                if (isset($data['google_event_id'])) {
                    updateAppointmentToGoogleCalendar($data);
                    unset($data['google_event_id']);
                }
                // Insert appointment in google calendar
            } else if (isset($data['google']) && !isset($data['created_by']) && !isset($data['google_event_id']) && $data['approved'] == '1') {
                $googleEvent = insertAppointmentToGoogleCalendar($data, $data['attendees']);
                $data['google_event_id'] = $googleEvent['google_event_id'];
                $data['google_calendar_link'] = $googleEvent['htmlLink'];
                $data['google_added_by_id'] = $googleEvent['google_added_by_id'];
            }
        }

        unset($data['google']);

        $data = array_merge($data, $this->convertDateForDatabase($data['date']));

        $attendees = $data['attendees'];

        $attendee_difference = array_diff($attendees, $current_attendees);

        $new_attendees = [];

        if (!empty($attendee_difference) && $data['approved'] == '1') {
            foreach ($attendee_difference as $new_attendee) {
                $new_attendees[] = appointly_get_staff($new_attendee);
            }

            $data['id'] = $data['appointment_id'];
            $this->atm->send_notifications_to_new_attenddees($new_attendees, $data);
            unset($data['id']);
        }

        $appointment_id = $data['appointment_id'];
        unset($data['appointment_id']);
        unset($data['attendees']);

        $this->db->where('id', $appointment_id);
        $this->db->update(db_prefix() . 'appointly_appointments', $data);

        $this->atm->update($appointment_id, $attendees);

        // Check if contact emails are different, if yes send notifications for newly added (edited) contact
        // Must wait to update the contact then do the checking if they are the same 
        // We have all values on top of function
        if ($data['source'] == 'external' && $data['approved']) {
            if ($current_contact['email'] !== $contact_form_email) {
                $new__updated_appointment = $this->get_appointment_data($appointment_id);

                $data['id'] = $appointment_id;
                $this->atm->send_notifications_to_new_contact($new__updated_appointment);
            }
        }

        return true;
    }

    /**
     * Delete appointment
     *
     * @param [string] $appointment_id
     * @return boolean
     */
    public function delete_appointment($appointment_id)
    {

        $_appointment = $this->get_appointment_data($appointment_id);

        if ($_appointment['created_by'] !== get_staff_user_id() && !is_admin()) {
            set_alert('danger', _l('appointments_no_delete_permissions'));

            if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                redirect(admin_url('access_denied'));
            }
        }

        if (get_option('appointly_also_delete_in_google_calendar') == 1) {
            if ($_appointment['google_event_id'] && $_appointment['google_added_by_id'] == get_staff_user_id()) {
                $this->load->model('googlecalendar');
                if (appointlyGoogleAuth()) {
                    $this->googlecalendar->deleteEvent($_appointment['google_event_id']);
                }
            }
        }

        $this->atm->deleteAll($appointment_id);

        $this->db->where('id', $appointment_id);

        if (!is_admin()) {
            $this->db->where('created_by', get_staff_user_id());
        }

        $this->db->delete(db_prefix() . 'appointly_appointments');

        if ($this->db->affected_rows() !== 0) {
            return true;
        }
        return false;
    }


    /**
     * Get todays appoinemtns
     *
     * @return array
     */
    public function fetch_todays_appointments()
    {
        $date = new DateTime();
        $today = $date->format('Y-m-d');

        $staff_has_permissions = !staff_can('view', 'appointments') || !staff_can('view_own', 'appointments');

        if ($staff_has_permissions) {
            $this->db->where('id IN (SELECT appointment_id FROM ' . db_prefix() . 'appointly_attendees WHERE staff_id=' . get_staff_user_id() . ')');
        }

        $this->db->where('date', $today);
        $this->db->where('approved', 1);

        return $this->db->get(db_prefix() . 'appointly_appointments')->result_array();
    }


    /**
     * Get all busy appointment dates
     *
     * @return json
     */
    public function getBusyTimes()
    {
        $time_format = get_option('time_format');
        $format = '';
        $time = '24';

        if ($time_format === '24') {
            $format = '"%H:%i"';
        } else {
            $time = '12';
            $format = '"%h:%i %p"';
        }

        $this->db->select('TIME_FORMAT(start_hour, ' . $format . ') as start_hour, date, source, created_by', FALSE);
        $this->db->from(db_prefix() . 'appointly_appointments');
        $this->db->where('approved', 1);

        $dates = $this->db->get()->result_array();

        if ($format == '"%h:%i %p"') {
            foreach ($dates as &$date) {
                $date['start_hour'] = substr($date['start_hour'], 1);
            }
        }


        if (appointlyGoogleAuth()) {

            $this->load->model('googlecalendar');

            $google_calendar_dates = $this->googlecalendar->getEvents();

            $convertedDates = [];

            if (count($google_calendar_dates) > 0) {
                foreach ($google_calendar_dates as &$gcdate) {

                    $gcdate['start'] = _dt($gcdate['start']);
                    $gcdate['start'] = explode(" ", $gcdate['start']);

                    if (!empty($gcdate['start'][0])) {
                        if (!in_array($gcdate['start'], $convertedDates)) {

                            if ($time_format == '24') {

                                array_push(
                                    $convertedDates,
                                    $this->convertDateForValidation($gcdate['start'][0] . ' ' . $gcdate['start'][1], $time)
                                );
                            } else {
                                array_push(
                                    $convertedDates,
                                    $this->convertDateForValidation($gcdate['start'][0] . ' ' . $gcdate['start'][1] . ' ' . $gcdate['start'][2], $time)
                                );
                            }
                        }
                    }
                }
                $dates = array_merge($dates, $convertedDates);
            }
        }

        echo json_encode($dates);
    }


    /**
     * Get all appointment data for calendar event
     *
     * @param [string] $start
     * @param [string] $end
     * @param [array] $data
     * @return array
     */
    public function getCalendarData($start, $end, $data)
    {
        $this->db->select('subject as title, date, hash, start_hour, id, type_id');
        $this->db->from('appointly_appointments');
        $this->db->where('finished = 0 AND cancelled = 0');


        if (!is_client_logged_in()) {
            $this->db->where('id IN (SELECT appointment_id FROM ' . db_prefix() . 'appointly_attendees WHERE staff_id=' . get_staff_user_id() . ')');
        } else {
            $this->db->where('id IN (SELECT appointment_id FROM ' . db_prefix() . 'appointly_attendees WHERE contact_id=' . get_contact_user_id() . ')');
        }

        $this->db->where('(CONCAT(date, " ", start_hour) BETWEEN "' . $start . '" AND "' . $end . '")');

        $appointments = $this->db->get()->result_array();

        foreach ($appointments as $key => $appointment) {

            $appointment['url'] = admin_url('appointly/appointments/view?appointment_id=' . $appointment['id']);

            if (is_client_logged_in()) {
                $appointment['url'] = admin_url('appointly/appointments_public/client_hash?hash=' . $appointment['hash']);
                $appointment['_tooltip'] = $appointment['title'];
            } else {
                $appointment['_tooltip'] = (get_appointment_type($appointment['type_id']))
                    ? _l('appointments_type_heading') . ": " . get_appointment_type($appointment['type_id'])
                    : $appointment['title'];
            }

            $appointment['date'] = $appointment['date'] . ' ' . $appointment['start_hour'] . ':00';
            $appointment['color'] = get_appointment_color_type($appointment['type_id']);
            $data[] = $appointment;
        }

        return $data;
    }

    /**
     * Fetch contact data and apply to fields in modal
     *
     * @param [string] $contact_id
     * @return function
     */
    function apply_contact_data($contact_id)
    {
        return $this->clients_model->get_contact($contact_id);
    }

    /**
     * Get single appointment data
     *
     * @param [string] $appointment_id
     * @return array
     */
    function get_appointment_data($appointment_id)
    {
        $this->db->where('id', $appointment_id);
        $appointment = $this->db->get(db_prefix() . 'appointly_appointments')->row_array();

        if ($this->db->affected_rows() > 0) {
            $appointment['attendees'] = $this->atm->get($appointment_id);
            return $appointment;
        }
        return false;
    }


    /**
     * Cancel appointment
     *
     * @param [string] $appointment_id
     * @return boolean
     */
    function cancel_appointment($appointment_id)
    {
        $appointment = $this->get_appointment_data($appointment_id);

        $notified_users = [];

        $attendees = $appointment['attendees'];

        foreach ($attendees as $staff) {

            if ($staff['staffid'] === get_staff_user_id()) {
                continue;
            }

            add_notification([
                'description'     => 'appointment_is_cancelled',
                'touserid'        => $staff['staffid'],
                'fromcompany'     => true,
                'link'            => 'appointly/appointments/view?appointment_id=' . $appointment['id'],
            ]);

            $notified_users[] = $staff['staffid'];
            send_mail_template('appointly_appointment_notification_cancelled_to_staff', 'appointly', array_to_object($appointment), array_to_object($staff));

            pusher_trigger_notification(array_unique($notified_users));
        }


        $template = mail_template('appointly_appointment_notification_cancelled_to_contact', 'appointly', array_to_object($appointment));

        if (!empty($appointment['phone'])) {
            $merge_fields =  $template->get_merge_fields();
            $this->app_sms->trigger(APPOINTLY_SMS_APPOINTMENT_CANCELLED_TO_CLIENT, $appointment['phone'], $merge_fields);
        }

        $template->send();

        $this->db->where('id', $appointment_id);
        $this->db->update(db_prefix() . 'appointly_appointments', ['cancelled' => 1]);

        header('Content-Type: application/json');
        if ($this->db->affected_rows() !== 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => true]);
        }
    }

    /**
     * Approve appointment
     *
     * @param [string] $appointment_id
     * @return boolean
     */
    function approve_appointment($appointment_id)
    {
        $this->appointment_approve_notification_and_sms_triggers($appointment_id);

        $this->db->where('id', $appointment_id);
        $this->db->update(db_prefix() . 'appointly_appointments', ['finished' => 0, 'approved' => 1, 'external_notification_date' => date('Y-m-d')]);

        return true;
    }


    /**
     * Check for external client hash token
     *
     * @param [string] $hash
     * @return void
     */
    function getByHash($hash)
    {
        $this->db->where('hash', $hash);
        $appointment = $this->db->get('appointly_appointments')->row_array();

        if ($appointment) {
            $appointment['selected_contact'] = $appointment['contact_id'];

            if (!empty($appointment['selected_contact'])) {
                $appointment['details'] = get_appointment_contact_details($appointment['selected_contact']);
            }
            $appointment['attendees'] = $this->atm->get($appointment['id']);
            return $appointment;
        }
        return false;
    }


    /** 
     * Marks appointment as finished
     * @param [string] $appointment_id
     * @return json
     */
    function mark_as_finished($id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'appointly_appointments', ['finished' => 1]);

        header('Content-Type: application/json');
        if ($this->db->affected_rows() !== 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }


    /** 
     * Marks appointment as ongoing
     * @param [string] $appointment_id
     * @return json
     */
    function mark_as_ongoing($appointment)
    {
        $this->appointment_approve_notification_and_sms_triggers($appointment['id']);

        $this->db->where('id', $appointment['id']);
        $this->db->update(db_prefix() . 'appointly_appointments', ['cancelled' => 0, 'finished' => 0, 'cancel_notes' => null]);

        header('Content-Type: application/json');
        if ($this->db->affected_rows() !== 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }


    /**
     * Send email and SMS notifications
     *
     * @param [string] $appointment_id
     * @return void
     */
    private function appointment_approve_notification_and_sms_triggers($appointment_id)
    {
        $appointment = $this->get_appointment_data($appointment_id);

        $notified_users = [];

        $attendees = $appointment['attendees'];

        if (count($attendees) == 0) {
            $this->atm->create($appointment_id, [get_staff_user_id()]);
            $attendees = $this->atm->get($appointment_id);
        }

        foreach ($attendees as $staff) {
            if ($staff['staffid'] === get_staff_user_id()) {
                continue;
            }

            add_notification([
                'description'     => 'appointment_is_approved',
                'touserid'        => $staff['staffid'],
                'fromcompany'     => true,
                'link'            => 'appointly/appointments/view?appointment_id=' . $appointment['id'],
            ]);


            $notified_users[] = $staff['staffid'];
            send_mail_template('appointly_appointment_approved_to_staff_attendees', 'appointly', array_to_object($appointment), array_to_object($staff));

            pusher_trigger_notification(array_unique($notified_users));
        }

        $template = mail_template('appointly_appointment_approved_to_contact', 'appointly', array_to_object($appointment));
        $merge_fields =  $template->get_merge_fields();

        if (!empty($appointment['phone'])) {
            $merge_fields =  $template->get_merge_fields();
            $this->app_sms->trigger(APPOINTLY_SMS_APPOINTMENT_APPROVED_TO_CLIENT, $appointment['phone'], $merge_fields);
        }

        $template->send();
    }

    /**
     * External appointment cancellation handler
     *
     * @param [string] $hash
     * @param [string] $notes
     * @return array
     */
    public function applyForAppointmentCancellation($hash, $notes)
    {
        $this->db->where('hash', $hash);
        $this->db->update(db_prefix() . 'appointly_appointments', ['cancel_notes' => $notes]);

        if ($this->db->affected_rows() !== 0) {
            return ['response' => [
                'message' => _l('appointments_thank_you_cancel_request'),
                'success' => true
            ]];
        }
    }


    /**
     * Check if cancellation is in progress already
     *
     * @param [appointment hash] $hash
     * @return array
     */
    public function checkIfCancellationIsInProgress($hash)
    {
        $this->db->select('cancel_notes');
        $this->db->where('hash', $hash);
        return $this->db->get(db_prefix() . 'appointly_appointments')->row_array();
    }


    /**
     * Convert dates for database insertion
     *
     * @param [string] $date
     * @return array
     */
    protected function convertDateForDatabase($date)
    {
        $date = to_sql_date($date, true);

        $toTime = strtotime($date);
        return [
            'date' => date('Y-m-d', $toTime),
            'start_hour' => date('H:i', $toTime),
        ];
    }

    /**
     * Convert dates for database insertion
     *
     * @param [string] $date
     * @return array
     */
    protected function convertDateForValidation($date, $time)
    {
        $date = to_sql_date($date, true);
        $convertor = 'H:i';

        if ($time == '12') {
            $convertor = 'g:i A';
        }

        $toTime = strtotime($date);
        return [
            'date' => date('Y-m-d', $toTime),
            'start_hour' => date($convertor, $toTime),
        ];
    }


    /**
     * Send appointment early reminders
     *
     * @param [string] $appointment_id
     * @return boolean
     */
    public function send_appointment_early_reminders($appointment_id)
    {
        $appointment = $this->get_appointment_data($appointment_id);

        foreach ($appointment['attendees'] as $staff) {
            add_notification([
                'description'       => 'appointment_you_have_new_appointment',
                'touserid'          => $staff['staffid'],
                'fromcompany'     => true,
                'link'               => 'appointly/appointments/view?appointment_id=' . $appointment_id,
            ]);

            $notified_users[] = $staff['staffid'];

            send_mail_template('appointly_appointment_cron_reminder_to_staff', 'appointly', array_to_object($appointment), array_to_object($staff));
        }

        $template = mail_template('appointly_appointment_cron_reminder_to_contact', 'appointly', array_to_object($appointment));

        $merge_fields =  $template->get_merge_fields();

        $template->send();

        if ($appointment['by_sms']) {
            $this->app_sms->trigger(APPOINTLY_SMS_APPOINTMENT_APPOINTMENT_REMINDER_TO_CLIENT, $appointment['phone'], $merge_fields);
        }

        return true;
    }


    /** 
     * Add new appointment type
     * @param [string] $type
     * @param [string] $color
     * @return boolean
     */
    public function new_appointment_type($type, $color)
    {
        return $this->db->insert(
            db_prefix() . 'appointly_appointment_types',
            [
                'type' => $type,
                'color' => $color
            ]
        );
    }


    /** 
     * Delete appointment type
     * @param [string] $id
     * @return json
     */
    public function delete_appointment_type($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'appointly_appointment_types');

        header('Content-Type: application/json');
        if ($this->db->affected_rows() !== 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }


    /** 
     * Update appointment types
     * @param [array] $data
     * @return void
     */
    public function update_appointment_types($data, $meta)
    {
        $data_color_types = [];

        foreach ($data as $key => $color) {
            if (strpos($key, 'type_id') === 0) {
                $key = substr($key, 8, 9);
                $data_color_types[$key]['id'] = $key;
                $data_color_types[$key]['color'] = $color;
            }
        }

        foreach ($data_color_types as $new_types) {
            $this->db->where('id', $new_types['id']);
            $this->db->update(db_prefix() . 'appointly_appointment_types', ['color' => $new_types['color']]);
        }
        hanleAppointlyUserMeta($meta);
    }

    private function handleDataReminderFields($data)
    {

        (isset($data['by_email']) && $data['by_email'] == 'on')
            ? $data['by_email'] = '1'
            : $data['by_email'] = NULL;

        (isset($data['by_sms']) && $data['by_sms'] == 'on')
            ? $data['by_sms'] = '1'
            : $data['by_sms'] = NULL;

        if ($data['by_email'] === null && $data['by_sms'] === null) {
            $data['reminder_before'] = null;
            $data['reminder_before_type'] = null;
        }

        if (isset($data['by_email']) || isset($data['by_sms'])) {
            if ($data['reminder_before'] == '') {
                $data['reminder_before'] = '30';
            }
        }
        return $data;
    }
}
