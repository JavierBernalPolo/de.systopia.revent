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
 * Form to customise Event registrations
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Revent_Form_RegistrationCustomisation extends CRM_Core_Form {

  protected $registrationRenderer   = NULL;
  protected $default_data           = NULL;
  protected $data                   = NULL;
  protected $replacement_index      = NULL;

  public function buildQuickForm() {
    // get event ID
    $event_id = CRM_Utils_Request::retrieve('eid', 'Positive', $this);
    if (!$event_id) {
      CRM_Core_Error::fatal("No event ID (eid) given.");
    } else {
      $this->add('hidden', 'eid', $event_id);
    }

    // load currently customised data
    $this->registrationRenderer = new CRM_Revent_RegistrationFields(array('id' => $event_id));
    $data = $this->registrationRenderer->renderEventRegistrationForm();

    // sort after group weight
    usort($data['groups'], array('CRM_Revent_Form_RegistrationCustomisation', 'compareHelper'));
    // put fields to corrosponding sorted groups
    foreach($data['fields'] as $value) {
      foreach($data['groups'] as &$group) {
        if ($group['name'] == $value['group']) {
          $group['fields'][$value['name']] = $value;
          // FixMe: currently static field for language support.
          //        Needed lateron in the form creation
          // TODO: Add additional lanuages here in the future
          //       Also: language dependent fields need to be named __NAME_LANG (e.g. title_de)
          $group['fields'][$value['name']]['languages'] = array('de', 'en');
        }
      }
    }
    // sort associated groups according to weight
    foreach ($data['groups'] as &$group) {
      usort($group['fields'], array('CRM_Revent_Form_RegistrationCustomisation', 'compareHelper'));
    }

    // add arrays for option counts
    foreach ($data['groups'] as &$grp) {
      foreach ($grp['fields'] as &$fld) {
        if (isset($fld['options'])) {
          // create a counter array for the template
          $fld['option_count'] = range(1, count($fld['options']));
          // check if options are available for languages, otherwise copy the default value to the groups
          foreach ($fld['languages'] as $lang) {
            if (empty($fld["options_{$lang}"])) {
              $fld["options_{$lang}"] = $fld['options'];
            }
          }
        }
      }
    }

    // some group names contain a '.', which messes with the quickform api or something else.
    // replace them with _, and store them locally, to be mapped afterwards
    foreach ($data['groups'] as &$g) {
      foreach ($g['fields'] as &$f) {
        if (strpos($f['name'], '.')) {
          // we need to replace this, and map it locally
          $replacement_name = str_replace('.', '_', $f['name']);
          $this->replacement_index[$replacement_name] = $f['name'];
          $f['name'] = $replacement_name;
        }
      }
    }

    // data is now sorted. create forms now for all groups
    foreach($data['groups'] as $indexed_group) {
      foreach ($indexed_group['fields'] as $indexed_field) {
//        $this->createFormElements($indexed_group['name'], $indexed_field['name']);
//        $this->createDefaultFormValues($indexed_field, $indexed_group['name'], $indexed_field['name'], 0);
        foreach ($indexed_field['languages'] as $indexed_language) {
//          if (!isset($indexed_field['name']))
          $this->createFormElements($indexed_group['name'], $indexed_field['name'], $indexed_language);
          $this->createDefaultFormValues($indexed_field, $indexed_group['name'], $indexed_field['name'], $indexed_language);
          if (isset($indexed_field["options_{$indexed_language}"])) {
            $i = 1;
            foreach ($indexed_field["options_{$indexed_language}"] as $option) {
              $this->createFormElementOptions($indexed_group['name'], $indexed_field['name'], $i, $indexed_language, $option);
              $i++;
            }
          }
        } // for loop over field languages
      } // for loop fields
    } // for loop groups

    // save the data structure
    $this->data= $data;

    // assign the whole groups array to the template form
    $this->assign('groups', $data['groups']);

    // submit button
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    parent::buildQuickForm();
  }

  /**
   * set the default (=current) values in the form
   */
  public function setDefaultValues() {
    return $this->default_data;
  }


  /**
   * post processing function
   */
  public function postProcess() {
    $values = $this->exportValues();

    $groups = array();
    $fields = array();

    $pattern = "/(?<type>[A-Za-z]+)__(?<group>[A-Za-z0-9-._]+)__(?<field>[0-9A-Za-z_.]+)__(?<language>[a-z0]{1,2})$/";
    $option_pattern = "/option__(?<group>[A-Za-z0-9-._]+)__(?<field>[0-9A-Za-z_.]+)__(?<count>[0-9]{1,2})__(?<language>[a-z0]{1,2})$/";
    $matches = array();
    // iterate values, then build group and fields array
    foreach ($values as $name => $value) {

      if (preg_match($option_pattern, $name, $matches)) {
        if (!isset($fields[$matches['field']])) {
          $fields[$matches['field']] = array();
        }
        if (!isset($fields[$matches['field']]["options_{$matches['language']}"])) {
          $fields[$matches['field']]["options_{$matches['language']}"] = array();
        }
        $fields[$matches['field']]["options_{$matches['language']}"][] = $value;

      } elseif (preg_match($pattern, $name, $matches)) {

        $type     = $matches['type'];
        $group    = $matches['group'];
        $field    = $matches['field'];
        $language = $matches['language'];

        // if language is 0, then this is the default value for the type,
        // otherwise only set the language specific field
        if ($language === "0") {
          // we don't need to do anything here, but might for future releases
          $fields[$field][$type] = $value;
        } else {
          $fields[$field]['group'] = $group;
          $fields[$field]['name'] = $field;
          // handle 'description' specially, because this is not a required field,
          // but can be added nonetheless
          if ($type == 'description') {
            $fields[$field][$type . "_" . $language] = $value;
          } elseif (isset($this->data['fields'][$field][$type . "_" . $language])) {
            $fields[$field][$type . "_" . $language] = $value;
          }
        }
      } else {
        continue;
      }
    }

    foreach($this->replacement_index as $replacement_name => $replacement_value) {
      $fields[$replacement_value] = $fields[$replacement_name];
      $fields[$replacement_value]['name'] = $replacement_value;
      unset($fields[$replacement_name]);
    }

    foreach ($this->data['groups'] as $group) {
      unset($group['fields']);
      $groups[] = $group;
    }

    // store it
    $data = $this->registrationRenderer->updateCustomisation($groups, $fields);
    parent::postProcess();
  }

  // Helper functions

    /**
     * compares to arrays elements weight and returns the difference
     * according to callback function of usort
     * @param $a
     * @param $b
     *
     * @return integer
     */
    private static function compareHelper($a, $b) {
      return $a['weight'] - $b['weight'];
    }

    /**
     * creates form elements indexed by group, field and language
     *
     * @param $groupIndex
     * @param $fieldIndex
     * @param $languageIndex (0 = default language, always available)
     */
    private function createFormElements($group_title, $field_title, $language = 0) {
      $this->add(
        'text',
        "title__{$group_title}__{$field_title}__{$language}",
        'title'
      );
      $this->add(
        'text',
        "description__{$group_title}__{$field_title}__{$language}",
        'description'
      );
      $this->add(
        'advcheckbox',
        "required__{$group_title}__{$field_title}__{$language}",
        'required'
      );
      $this->add(
        'text',
        "weight__{$group_title}__{$field_title}__{$language}",
        'weight'
      );
    }

    private function createFormElementOptions($group_title, $field_title, $option, $language,  $default_value) {
      $this->add(
        'text',
        "option__{$group_title}__{$field_title}__{$option}__{$language}",
        'option'
      );
      $this->default_data["option__{$group_title}__{$field_title}__{$option}__{$language}"] = $default_value;
    }

    private function createDefaultFormValues($value, $group_title, $field_title, $language = 0) {

      if ($language == "0") {
        $this->default_data["title__{$value['group']}__{$value['name']}__0"] = $value['title'];
        if (isset($value['description'])) {
          $this->default_data["description__{$value['group']}__{$value['name']}__0"] = $value['description'];
        }
        $this->default_data["required__{$value['group']}__{$value['name']}__0"] = $value['required'];
        $this->default_data["weight__{$value['group']}__{$value['name']}__0"] = $value['weight'];
      } else {
        $this->default_data["title__{$value['group']}__{$value['name']}__{$language}"] = $value["title_{$language}"];
        if (isset($value["description_{$language}"])) {
          $this->default_data["description__{$value['group']}__{$value['name']}__{$language}"] = $value['description'];
        } else {
          $this->default_data["description__{$value['group']}__{$value['name']}__{$language}"] = "";
        }
        $this->default_data["required__{$value['group']}__{$value['name']}__{$language}"] = $value['required'];
        $this->default_data["weight__{$value['group']}__{$value['name']}__{$language}"] = $value['weight'];
      }

    }

  }
