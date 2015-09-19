<?php

/**
* Map Connec Customer Participant representation to/from LimeSurvey Contact
*/
class ContactMapper extends BaseMapper {
  protected $customer_organization_mapper = null;

  public function __construct() {
    parent::__construct();

    $this->connec_entity_name = 'Participant';
    $this->local_entity_name = 'Participant';
    $this->connec_resource_name = 'people';
    $this->connec_resource_endpoint = 'people';
  }

  // Return the Participant local id
  protected function getId($participant) {
    return $participant->participant_id;
  }

  // Return a local Participant by id
  protected function loadModelById($local_id) {
    return Participant::model()->findByPk($local_id);
  }

  // Map the Connec resource attributes onto the LimeSurvey Participant
  protected function mapConnecResourceToModel($person_hash, $participant) {
    // Default values
    $participant->participant_id = $person_hash['id'];
    $participant->language = 'en';
    $participant->blacklisted = 'N';
    $participant->owner_uid = 1;

    // Map hash attributes to Participant
    if(array_key_exists('first_name', $person_hash)) { $participant->firstname = $person_hash['first_name']; }
    if(array_key_exists('last_name', $person_hash)) { $participant->lastname = $person_hash['last_name']; }
    if(array_key_exists('email', $person_hash) && array_key_exists('address', $person_hash['email'])) { $participant->email = $person_hash['email']['address']; }
  }

  // Map the LimeSurvey Participant to a Connec resource hash
  protected function mapModelToConnecResource($participant) {
    $person_hash = array();

    // Save as Customer
    $person_hash['is_customer'] = true;

    // Map attributes
    $person_hash['first_name'] = $participant->firstname;
    $person_hash['last_name'] = $participant->lastname;
    $person_hash['email'] = array('address' => $participant->email);

    return $person_hash;
  }

  // Persist the LimeSurvey Participant
  protected function persistLocalModel($participant, $resource_hash) {
    $participant->save(false);
  }
}