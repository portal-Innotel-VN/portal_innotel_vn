<?php defined('BASEPATH') or exit('No direct script access allowed');


hooks()->add_action('app_admin_head', 'appointly_head_components');
hooks()->add_action('after_email_templates', 'add_appointly_email_templates');
hooks()->add_action('clients_init', 'appointly_clients_area_schedule_appointment');

if (!function_exists('appointly_clients_area_schedule_appointment')) {
    function appointly_clients_area_schedule_appointment()
    {
        // Item is available for all clients if enabled in Setup->Settings->Appointment
        if (get_option('appointly_show_clients_schedule_button') == 1 && !is_client_logged_in()) {
            add_theme_menu_item('schedule-appointment-id', [
                'name'     => _l('appointly_schedule_new_appointment'),
                'href'     => site_url('appointly/appointments_public/form?col=col-md-8+col-md-offset-2'),
                'position' => 10,
            ]);
        }

        // Item is available for logged in clients if enabled in Setup->Settings->Appointment
        if (is_client_logged_in()) {
            if (get_option('appointly_tab_on_clients_page') == 1) {
                add_theme_menu_item('schedule-appointment-logged-in-id', [
                    'name'     => _l('appointly_schedule_new_appointment'),
                    'href'     => site_url('appointly/appointments_public/form?col=col-md-8+col-md-offset-2'),
                    'position' => 1,
                ]);
            }
        }
    }
}


if (!function_exists('add_appointly_email_templates')) {
    /**
     * Init appointly email templates and assign languages
     * @return void
     */
    function add_appointly_email_templates()
    {
        $CI = &get_instance();

        $data['appointly_templates'] = $CI->emails_model->get(['type' => 'appointly', 'language' => 'english']);

        $CI->load->view('appointly/email_templates', $data);
    }
}


if (!function_exists('appointly_head_components')) {
    /**
     * Injects theme CSS
     * @return null
     */
    function appointly_head_components()
    {
        echo '<link href="' . module_dir_url(APPOINTLY_MODULE_NAME, 'assets/css/styles.css?v=' . time()) . '"  rel="stylesheet" type="text/css" >';
    }
}


if (!function_exists('appointly_get_staff_customers')) {
    /**
     * Fetches from database all staff assigned customers
     * If admin fetches all customers
     * @return array
     */
    function appointly_get_staff_customers()
    {
        $CI = &get_instance();

        $staffCanViewAllClients = staff_can('view', 'customers');

        $CI->db->select('firstname, lastname, ' . db_prefix() . 'contacts.id as contact_id, ' . get_sql_select_client_company());
        $CI->db->where(db_prefix() . 'clients.active', '1');
        $CI->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid=' . db_prefix() . 'contacts.userid', 'left');
        $CI->db->select(db_prefix() . 'clients.userid as client_id');

        if (!$staffCanViewAllClients) {
            $CI->db->where('(' . db_prefix() . 'clients.userid IN (SELECT customer_id FROM ' . db_prefix() . 'customer_admins WHERE staff_id=' . get_staff_user_id() . '))');
        }

        $result = $CI->db->get(db_prefix() . 'contacts')->result_array();

        foreach ($result as &$contact) {

            if ($contact['company'] == $contact['firstname'] . ' ' . $contact['lastname']) {
                $contact['company'] = _l('appointments_individual_contact');
            } else {
                $contact['company'] = "" . _l('appointments_company_for_select') . "(" . $contact['company'] . ")";
            }
        }

        if ($CI->db->affected_rows() !== 0) {
            return $result;
        } else {
            return [];
        }
    }
}

if (!function_exists('fetch_appointment_data')) {
    /**
     * Fetch current apppointment data
     *
     * @param [string] $appointment_id
     * @return array
     */
    function fetch_appointment_data($appointment_id)
    {
        $CI = &get_instance();

        $appointment = $CI->apm->get_appointment_data($appointment_id);

        if (!empty($appointment)) {
            $CI->load->model('staff_model');

            $appointment['selected_staff'] = array_map(function ($staff) {
                return $staff['staffid'];
            }, $appointment['attendees']);

            $appointment['selected_contact'] = $appointment['contact_id'];

            if (!empty($appointment['selected_contact'])) {
                $appointment['details'] = get_appointment_contact_details($appointment['selected_contact']);
            }

            $appointment['appointment_id'] = $appointment_id;

            return $appointment;
        }

        return [];
    }
}


if (!function_exists('redirect_after_event')) {
    /**
     * Helper redirect function with alert message
     *
     * @param [string] $type 'success' | 'danger'
     * @param [string] $message
     * @return void
     */
    function appointly_redirect_after_event($type, $message, $path = null)
    {
        $CI = &get_instance();

        $CI->session->set_flashdata('message-' . $type . '', $message);

        if ($path) {
            redirect('appointly/' . $path);
        } else {
            redirect('appointly/appointments');
        }
    }
}


if (!function_exists('get_appointment_contact_details')) {
    /**
     * Helper function to get contact specific data
     *
     * @param [string] $contact_id
     * @return array
     */
    function get_appointment_contact_details($contact_id)
    {
        $CI = &get_instance();
        $CI->db->select('email, phonenumber as phone, CONCAT(firstname, " " , lastname) AS full_name');
        $CI->db->where('id', $contact_id);
        return $CI->db->get(db_prefix() . 'contacts')->row_array();
    }
}


/**
 * Get staff 
 *
 * @param [string] $staffid
 * @return array
 */
function appointly_get_staff($staffid)
{
    $CI = &get_instance();
    $CI->db->where('staffid', $staffid);
    return $CI->db->get(db_prefix() . 'staff')->row_array();
}


/**
 * Include appointment view
 *
 * @param [string] view name
 * @return mixed
 */
function include_appointment_view($name)
{
    return require('modules/appointly/views/tables/' . $name . '.php');
}

/**
 * Get projects summary 
 *
 * @return array
 */
function get_appointments_summary()
{
    $CI = &get_instance();

    $staff_has_permissions = !staff_can('view', 'appointments') || !staff_can('view_own', 'appointments');

    if ($staff_has_permissions) {

        $CI->db->where('(' . db_prefix() . 'appointly_appointments.created_by=' . get_staff_user_id() . ') 
        OR ' . db_prefix() . 'appointly_appointments.id 
        IN (SELECT appointment_id FROM ' . db_prefix() . 'appointly_attendees WHERE staff_id=' . get_staff_user_id() . ')');
    }

    $appointments = $CI->db->get(db_prefix() . 'appointly_appointments')->result_array();

    $data = [
        'total_appointments' => 0,
        'upcoming' => [
            'total' =>  0,
            'name' => _l('appointment_upcoming'),
            'color' => 'rgb(86, 111, 236)'
        ],
        'not_approved' => [
            'total' =>  0,
            'name' => _l('appointment_pending_approval'),
            'color' => 'rgb(236, 169, 86)'
        ],
        'cancelled' => [
            'total' =>  0,
            'name' => _l('appointment_cancelled'),
            'color' => 'rgba(244, 3, 47, 0.59)'
        ],
        'missed' => [
            'total' =>  0,
            'name' => _l('appointment_missed_label'),
            'color' => 'rgba(244, 3, 47, 0.59)'
        ],
        'finished' => [
            'total' =>  0,
            'name' => _l('appointment_finished'),
            'color' => 'rgb(132, 197, 41)'
        ]
    ];

    if ($CI->db->count_all_results() > 0) {

        $data['total_appointments'] = count($appointments);

        foreach ($appointments as $appointment) {
            if ($appointment['cancelled']) {
                $data['cancelled']['total'] = $data['cancelled']['total'] + 1;
            } else if (
                !$appointment['approved']
                && !$appointment['cancelled']
                && date('Y-m-d H:i', strtotime($appointment['date'] . ' ' . $appointment['start_hour'])) > date('Y-m-d H:i')
            ) {
                $data['not_approved']['total'] = $data['not_approved']['total'] + 1;
            } else if (
                !$appointment['finished'] && !$appointment['cancelled']
                && date('Y-m-d H:i', strtotime($appointment['date'] . ' ' . $appointment['start_hour'])) < date('Y-m-d H:i')
            ) {
                $data['missed']['total'] = $data['missed']['total'] + 1;
            } else if (!$appointment['finished'] && !$appointment['cancelled']) {
                $data['upcoming']['total'] = $data['upcoming']['total'] + 1;
            } else {
                $data['finished']['total'] = $data['finished']['total'] + 1;
            }
        }
    }
    return $data;
}


/**
 * Get staff current role.
 * @param [type] $role_id
 * @return string
 */
function get_appointly_staff_userrole($role_id)
{
    $CI = &get_instance();
    $CI->db->select('name');
    $CI->db->where('roleid', $role_id);

    return $CI->db->get(db_prefix() . 'roles')->row_array()['name'];
}


/** 
 * Get all appointment types
 * @return array
 */
function get_appointment_types()
{
    $CI = &get_instance();
    return $CI->db->get(db_prefix() . 'appointly_appointment_types')->result_array();
}


/** 
 * Get single appointment type
 * @return array
 */
function get_appointment_type($type_id)
{
    $CI = &get_instance();
    $CI->db->select('type');
    $CI->db->where('id', $type_id);
    return $CI->db->get(db_prefix() . 'appointly_appointment_types')->row_array()['type'];
}


/** 
 * Get appointment assigned color type
 * @param [string] $type_id
 * @return string
 */
function get_appointment_color_type($type_id)
{
    $CI = &get_instance();
    $CI->db->where('id', $type_id);
    return $CI->db->get(db_prefix() . 'appointly_appointment_types')->row_array()['color'];
}

function get_appointments_table_filters()
{
    return [
        [
            'id' => 'all',
            'status' => 'All'
        ],
        [
            'id' => 'approved',
            'status' => _l('appointment_approved')
        ],
        [
            'id' => 'not_approved',
            'status' => _l('appointment_not_approved')
        ],
        [
            'id' => 'cancelled',
            'status' => _l('appointment_cancelled')
        ],
        [
            'id' => 'finished',
            'status' => _l('appointment_finished')
        ]
    ];
}


/** 
 * Get staff or contact email
 */
function appointly_get_user_email($id, $type = 'staff')
{
    $CI = &get_instance();
    $CI->db->select('email');
    $table = 'staff';
    $selector = 'staffid';

    if ($type == 'contact') {
        $table = 'contacts';
        $selector = 'id';
    }

    $CI->db->where($selector, $id);
    return $CI->db->get(db_prefix() . $table)->row_array()['email'];
}


/** 
 * Insert new appointment to google calendar
 * @return void
 */
function insertAppointmentToGoogleCalendar($data, $attendees)
{
    get_instance()->load->model('googlecalendar');

    if (appointlyGoogleAuth()) {

        $dateForGoogleCalendar = new DateTime($data['date']);

        $data['date'] = date_format($dateForGoogleCalendar, 'Y-m-d\TH:i:s');

        $insertDate =  $data['date'];

        $gmail_guests = [];
        $gmail_attendees = $attendees;


        foreach ($gmail_attendees as $attendee) {
            $gmail_guests[] = array('email' => appointly_get_user_email($attendee));
        }

        if (!empty($data['contact_id'])) {
            $gmail_guests[] =  array('email' => appointly_get_user_email($data['contact_id'], 'contact'));
        } else {
            $gmail_guests[] =  array('email' => $data['email']);
        }

        $response = get_instance()->googlecalendar->addEvent('primary', [
            'summary' => $data['subject'],
            'location' => $data['address'],
            'description' => $data['description'],
            'start' =>    $insertDate,
            'end' =>    $insertDate,
            'attendees' => $gmail_guests
        ]);

        if ($response) {
            $return_data = [];

            $return_data['google_event_id'] = $response['id'];
            $return_data['htmlLink'] = $response['htmlLink'];
            $return_data['google_added_by_id'] = get_staff_user_id();

            return $return_data;
        }
    }
}

function updateAppointmentToGoogleCalendar($data)
{
    get_instance()->load->model('googlecalendar');

    if (appointlyGoogleAuth()) {

        $dateForGoogleCalendar = new DateTime($data['date']);

        $data['date'] = date_format($dateForGoogleCalendar, 'Y-m-d\TH:i:s');

        $insertDate =  $data['date'];

        $gmail_guests = [];
        $gmail_attendees = $data['attendees'];


        foreach ($gmail_attendees as $attendee) {
            $gmail_guests[] = array('email' => appointly_get_user_email($attendee));
        }

        if (!empty($data['contact_id'])) {
            $gmail_guests[] =  array('email' => appointly_get_user_email($data['contact_id'], 'contact'));
        } else {
            $gmail_guests[] =  array('email' => $data['email']);
        }

        $response = get_instance()->googlecalendar->updateEvent($data['google_event_id'], [
            'summary' => $data['subject'],
            'location' => $data['address'],
            'description' => $data['description'],
            'start' =>    $insertDate,
            'end' =>    $insertDate,
            'attendees' => $gmail_guests
        ]);

        if ($response) {
            $return_data = [];

            $return_data['google_event_id'] = $response['id'];
            $return_data['htmlLink'] = $response['htmlLink'];

            return $return_data;
        }
    }
}


/** 
 * Check if user is authenticated with google calendar
 * Refresh access token
 */
function appointlyGoogleAuth()
{
    $CI = &get_instance();
    $CI->load->model('googlecalendar');
    $CI->load->library('googleplus');

    $account = $CI->googlecalendar->getAccountDetails();

    if (!$account) {
        return false;
    }

    $newToken = '';

    if ($account) {
        $account = $account[0];

        $currentToken = [
            'access_token' => $account->access_token,
            'expires_in' => $account->expires_in
        ];

        $CI->googleplus
            ->client
            ->setAccessToken($currentToken);

        $refreshToken = $account->refresh_token;
        // renew 5 minutes before token expire 
        if ($account->expires_in <= time() + 300) {

            if ($CI->googleplus->isAccessTokenExpired()) {

                $CI->googleplus
                    ->client
                    ->setAccessToken($currentToken);
            }

            if ($refreshToken) {
                // { "error": "invalid_grant", "error_description": "Token has been expired or revoked." }
                try {
                    $newToken = $CI->googleplus->client->refreshToken($refreshToken);
                } catch (Exception $e) {
                    if ($e->getCode() === 400) {
                        return false;
                    } else if ($e->getCode() === 401) {
                        return false;
                    }
                }

                $CI->googleplus
                    ->client
                    ->setAccessToken($newToken);

                if ($newToken) {
                    $CI->googlecalendar->saveNewTokenValues($newToken);
                }
            }
        } else {

            try {
                $newToken = $CI->googleplus->client->refreshToken($refreshToken);
            } catch (Exception $e) {
                if ($e->getCode() === 400) {
                    return false;
                } else if ($e->getCode() === 401) {
                    return false;
                }
            }
        }

        $CI->googleplus
            ->client
            ->setAccessToken(($newToken !== '') ? $newToken : $account->access_token);
    }

    if ($CI->googleplus->client->getAccessToken()) {
        return $CI->googleplus->client->getAccessToken();
    } else {
        return false;
    }
}

function getAppoinlyUserMeta()
{
    $data = [];

    $data['appointly_show_summary'] =
        get_meta('staff', get_staff_user_id(), 'appointly_show_summary');

    $data['appointly_default_table_filter'] =
        get_meta('staff', get_staff_user_id(), 'appointly_default_table_filter');

    return $data;
}


function hanleAppointlyUserMeta($meta)
{
    foreach ($meta as $key => $value) {
        update_meta(
            'staff',
            get_staff_user_id(),
            $key,
            $value
        );
    }
}

function getAppointmentHours()
{
    return [
        ['value' => '00:00', 'name' => '00:00 AM'],
        ['value' => '00:30', 'name' => '00:30 AM'],
        ['value' => '01:00', 'name' => '01:00 AM'],
        ['value' => '01:30', 'name' => '01:30 AM'],
        ['value' => '02:00', 'name' => '02:00 AM'],
        ['value' => '02:30', 'name' => '02:30 AM'],
        ['value' => '03:00', 'name' => '03:00 AM'],
        ['value' => '03:30', 'name' => '03:30 AM'],
        ['value' => '04:00', 'name' => '04:00 AM'],
        ['value' => '04:30', 'name' => '04:30 AM'],
        ['value' => '05:00', 'name' => '05:00 AM'],
        ['value' => '05:30', 'name' => '05:30 AM'],
        ['value' => '06:00', 'name' => '06:00 AM'],
        ['value' => '06:30', 'name' => '06:30 AM'],
        ['value' => '07:00', 'name' => '07:00 AM'],
        ['value' => '07:30', 'name' => '07:30 AM'],
        ['value' => '08:00', 'name' => '08:00 AM'],
        ['value' => '08:30', 'name' => '08:30 AM'],
        ['value' => '09:00', 'name' => '09:00 AM'],
        ['value' => '09:30', 'name' => '09:30 AM'],
        ['value' => '10:00', 'name' => '10:00 AM'],
        ['value' => '10:30', 'name' => '10:30 AM'],
        ['value' => '11:00', 'name' => '11:00 AM'],
        ['value' => '11:30', 'name' => '11:30 AM'],
        ['value' => '12:00', 'name' => '12:00 PM'],
        ['value' => '12:30', 'name' => '12:30 AM'],
        ['value' => '13:00', 'name' => '13:00 PM'],
        ['value' => '13:30', 'name' => '13:30 AM'],
        ['value' => '14:00', 'name' => '14:00 PM'],
        ['value' => '14:30', 'name' => '14:30 AM'],
        ['value' => '15:00', 'name' => '15:00 PM'],
        ['value' => '15:30', 'name' => '15:30 AM'],
        ['value' => '16:00', 'name' => '16:00 PM'],
        ['value' => '16:30', 'name' => '16:30 AM'],
        ['value' => '17:00', 'name' => '17:00 PM'],
        ['value' => '17:30', 'name' => '17:30 AM'],
        ['value' => '18:00', 'name' => '18:00 PM'],
        ['value' => '18:30', 'name' => '18:30 AM'],
        ['value' => '19:00', 'name' => '19:00 PM'],
        ['value' => '19:30', 'name' => '19:30 AM'],
        ['value' => '20:00', 'name' => '20:00 PM'],
        ['value' => '20:30', 'name' => '20:30 AM'],
        ['value' => '21:00', 'name' => '21:00 PM'],
        ['value' => '21:30', 'name' => '21:30 AM'],
        ['value' => '22:00', 'name' => '22:00 PM'],
        ['value' => '22:30', 'name' => '22:30 AM'],
        ['value' => '23:00', 'name' => '23:00 PM'],
        ['value' => '23:30', 'name' => '23:30 AM']
    ];
}
