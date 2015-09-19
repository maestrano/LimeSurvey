<?php

require_once '../init.php';
require_once MAESTRANO_ROOT . '/connec/init.php';

// Set default user for entities creation
global $current_user;
if(is_null($current_user)) { $current_user = (object) array(); }
if(!isset($current_user->id)) {
  $current_user->id = '1';
  $current_user->date_format = 'Y-m-d';
}

try {
  if(!Maestrano::param('connec.enabled')) { return false; }

  $client = new Maestrano_Connec_Client();

  $notification = json_decode(file_get_contents('php://input'), false);
  $entity_name = strtoupper(trim($notification->entity));
  $entity_id = $notification->id;

  error_log("Received notification = ". json_encode($notification));

  switch ($entity_name) {
    case "PERSONS":
      $contactMapper = new ContactMapper();
      $contactMapper->fetchConnecResource($entity_id);
      break;
  }
} catch (Exception $e) {
  error_log("Caught exception in subscribe " . json_encode($e->getMessage()));
}
