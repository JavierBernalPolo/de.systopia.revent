<?php
/*-------------------------------------------------------+
| SYSTOPIA REMOTE EVENT REGISTRATION                     |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/**
 * Create/update a Remote Event with the provided data
 */
function civicrm_api3_remote_event_create($params) {
  $params['remote_event_connection.external_identifier'] = $params['external_identifier'];
  unset($params['external_identifier']);

  // resolve event type
  if (!empty($params['entity_type'])) {
    $entity_type = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'event_type',
      'return'          => 'id',
      'options.limit'   => 2));
    if ($entity_type['id']) {
      $params['event_type_id'] = $entity_type['id'];
    }
  }

  // fallback event type (not found or not submitted)
  if (empty($params['event_type_id'])) {
    // TODO: what's the default?
    $params['event_type_id'] = 1;
  }

  // see if the group already exists
  $existing_event = civicrm_api3('Event', 'get', array(
    'external_identifier' => $params['remote_event_connection.external_identifier'],
    'return'              => 'id',
    'options.limit'       => 2));
  if (!empty($existing_event['id'])) {
    $params['id'] = $existing_event['id'];
  }

  CRM_Revent_CustomData::resolveCustomFields($params);

  $result = civicrm_api3('Event', 'create', $params);
  return civicrm_api3('RemoteEvent', 'get', array('id' => $result['id']));
}

/**
 * Create Remote Event
 */
function _civicrm_api3_remote_event_create_spec(&$params) {
  $params['external_identifier'] = array(
    'name'         => 'external_identifier',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Event External Identifier (e.g. Drupal Node)',
    );
  $params['start_date'] = array(
    'name'         => 'start_date',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_DATE,
    'title'        => 'Remote Event start date',
  );
  $params['title'] = array(
    'name'         => 'title',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Remote Event title',
  );
  $params['event_type'] = array(
    'name'         => 'event_type',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Remote Event Type',
  );
  $params['remote_event_connection.edit_link'] = array(
    'name'         => 'remote_event_connection.edit_link',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Remote Event Edit Link',
  );
  $params['remote_event_connection.registration_link'] = array(
    'name'         => 'remote_event_connection.registration_link',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Remote Event Registration Link',
  );
}
