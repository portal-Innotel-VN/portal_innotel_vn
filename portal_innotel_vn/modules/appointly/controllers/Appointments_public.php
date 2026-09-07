<?php defined('BASEPATH') or exit('No direct script access allowed');

class Appointments_public extends ClientsController
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('appointly_model', 'apm');
        $this->load->model('staff_model');
    }


    /**
     * Clients hash view
     *
     * @return view
     */
    public function client_hash()
    {
        $hash = $this->input->get('hash');

        if (!$hash) {
            show_404();
        }

        $appointment = $this->apm->getByHash($hash);

        if (!$appointment) {
            show_404();
        }

        $appointment['url'] = site_url('appointly/appointments_public/cancel_appointment');


        $this->load->view('clients/clients_hash', ['appointment' => $appointment]);
    }


    /**
     * Fetches contact data if client who requested meeting is already in the system
     *
     * @return json
     */
    public function external_fetch_contact_data()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        $id = $this->input->post('contact_id');

        header('Content-Type: application/json');
        echo json_encode($this->apm->apply_contact_data($id));
    }


    /**
     * Handles clients external public form
     *
     * @return view
     */
    public function form()
    {
        $form            = new stdClass();
        $form->language  = get_option('active_language');

        $this->lang->load($form->language . '_lang', $form->language);
        if (file_exists(APPPATH . 'language/' . $form->language . '/custom_lang.php')) {
            $this->lang->load('custom_lang', $form->language);
        }

        if ($this->input->post() && $this->input->is_ajax_request()) {
            $post_data = $this->input->post();

            $required = ['subject', 'description', 'name', 'email'];

            foreach ($required as $field) {
                if (!isset($post_data[$field]) || isset($post_data[$field]) && empty($post_data[$field])) {
                    $this->output->set_status_header(422);
                    die;
                }
            }

            $post_data = [
                'email'      => $post_data['email'],
                'name'       => $post_data['name'],
                'subject'    => $post_data['subject'],
                'description' => $post_data['description']
            ];
            die;
        }

        $data['form'] = $form;

        $this->load->view('forms/appointments_form', $data);
    }


    /**
     * Handles creation of an external appointment
     *
     * @return json
     */
    public function create_external_appointment()
    {
        $data = array();

        $data = $this->input->post();

        $data['source'] = $data['rel_type'];
        unset($data['rel_type']);

        if ($this->apm->insert_external_appointment($data)) {
            echo json_encode([
                'success' => true,
                'message' => _l('appointment_sent_successfully')
            ]);
        }
    }


    /**
     * Handles appointment cancelling
     *
     * @return void
     */
    public function cancel_appointment()
    {
        if ($this->input->get('hash')) {
            $hash = $this->input->get('hash');
            $notes = $this->input->get('notes');

            if ($notes == '') {
                return false;
            }

            if (!$hash) {
                show_404();
            }

            $appointment = $this->apm->getByHash($hash);

            if (!$appointment) {
                show_404();
            } else {

                $cancellation_in_progress =  $this->apm->checkIfCancellationIsInProgress($hash);

                header('Content-Type: application/json');
                if ($cancellation_in_progress['cancel_notes'] === NULL) {

                    $responsible_person = get_option('appointly_responsible_person');
                    $touserid = '';

                    if ($responsible_person != '') {
                        $touserid = $responsible_person;
                    } elseif ($responsible_person == '' && $appointment['created_by'] !== NULL) {
                        $touserid = $appointment['created_by'];
                    } else {
                        /** If none of above conditions are true
                         * Goes to default eg. first admin created with id of 1
                         */
                        $touserid = 1;
                    }

                    add_notification([
                        'description'     => 'appointment_cancel_notification',
                        'touserid'        => $touserid,
                        'fromcompany'     => true,
                        'link'            => 'appointly/appointments/view?appointment_id=' . $appointment['id'],
                    ]);

                    pusher_trigger_notification([$touserid]);
                    echo json_encode($this->apm->applyForAppointmentCancellation($hash, $notes));
                } else {

                    echo json_encode(['response' => [
                        'message' => _l('appointments_already_applied_for_cancelling'),
                        'success' => false
                    ]]);
                }
            }
        } else {
            show_404();
        }
    }

    /**
     * Get busy appointment times
     *
     * @return void
     */
    public function busyDates()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        return $this->apm->getBusyTimes();
    }
}
