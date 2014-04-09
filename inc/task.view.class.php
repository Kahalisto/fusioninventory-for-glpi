<?php

/*
   ------------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2013 by the FusionInventory Development Team.

   http://www.fusioninventory.org/   http://forge.fusioninventory.org/
   ------------------------------------------------------------------------

   LICENSE

   This file is part of FusionInventory project.

   FusionInventory is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   FusionInventory is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with FusionInventory. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FusionInventory
   @author    David Durieux
   @co-author Kevin Roy
   @copyright Copyright (c) 2010-2014 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010

   ------------------------------------------------------------------------
 */

class PluginFusioninventoryTaskView extends PluginFusioninventoryCommonView {


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $CFG_GLPI;

      $tab_names = array();

      if ( $this->can("task", "r") ) {
         if ($item->getType() == 'Computer') {
            $tab_names[] = __('FusInv', 'fusioninventory').' '. _n('Task', 'Tasks', 2);
         }
      }

      if (!empty($tab_names)) {
         return $tab_names;
      } else {
         return '';
      }
   }

   function defineTabs($options=array()){
      global $CFG_GLPI;
      $ong = array();

      $this->addDefaultFormTab($ong);

      return $ong;
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      if ($item->getType() == 'Computer') {
         echo "<b>To Be Done</b>";
      }
   }
   /**
    * Display form for task configuration
    *
    * @param $items_id integer ID of the task
    * @param $options array
    *
    * @return bool TRUE if form is ok
    *
    **/
   function showForm($id, $options=array()) {
      $pfTaskjob = new PluginFusioninventoryTaskjob();

      $taskjobs = array();
      $new_item = false;
      Toolbox::logDebug($id);
      if ($id > 0) {
         $this->getFromDB($id);
         $taskjobs = $pfTaskjob->find("`plugin_fusioninventory_tasks_id`='".$id."'", "id");
      } else {
         $this->getEmpty();
         $new_item = true;
      }


      $options['colspan'] = 2;
      $this->initForm($id,$options);
      $this->showFormHeader($options);


      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='4'>";
      echo "<div class='fusinv_form'>";

      $this->showTextField( __('Name'), "name");
      $this->showTextArea(__('Comments'), "comment");
      echo "</div>";
      if ( ! $new_item ) {
         echo "<div class='fusinv_form'>";
         $this->showCheckboxField( __('Active'), "is_active" );

         $datetime_field_options = array(
            'timestep' => 1,
            'maybeempty' => true,
         );
         $this->showDateTimeField(
            __('Schedule start', 'fusioninventory'),
            "datetime_start",
            $datetime_field_options
         );

         $this->showDateTimeField(
            __('Schedule end', 'fusioninventory'),
            "datetime_end",
            $datetime_field_options
         );

         $this->showDropdownForItemtype(
            __('Timeslot','fusioninventory'),
            "PluginFusioninventoryTimeslot",
            array('value' => $this->fields['plugin_fusioninventory_timeslots_id'])
            );
         echo "</div>";
      }

      echo "</div>";
      echo "</td>";
      echo "</tr>";
      $this->showFormButtons($options);

      return true;
   }


   public function submitForm($postvars) {

      if (isset($postvars['forcestart'])) {
         Session::checkRight('plugin_fusioninventory_task', UPDATE);

         /**
          * TODO: forcing the task execution should be done in the task object
          */
         $pfTaskjob = new PluginFusioninventoryTaskjob();

         $pfTaskjob->forceRunningTask($postvars['id']);

         Html::back();

      } else if (isset ($postvars["add"])) {

         Session::checkRight('plugin_fusioninventory_task', CREATE);

         $items_id = $this->add($postvars);

         Html::redirect(str_replace("add=1", "", $_SERVER['HTTP_REFERER'])."?id=".$items_id);

      } else if (isset($postvars["purge"])) {
         Toolbox::logDebug("Start Delete");

         Session::checkRight('plugin_fusioninventory_task', PURGE);

         $pfTaskJob = new PluginFusioninventoryTaskjob();

         $taskjobs = $pfTaskJob->find("`plugin_fusioninventory_tasks_id` = '".$postvars['id']."' ");

         foreach ($taskjobs as $taskjob) {
            $pfTaskJob->delete($taskjob);
         }

         $this->delete($postvars);
         Html::redirect(Toolbox::getItemTypeSearchURL(get_class($this)));

      } else if (isset($_POST["update"])) {

         Session::checkRight('plugin_fusioninventory_task', UPDATE);

         $this->getFromDB($postvars['id']);

         //Ensure empty value are set to NULL for datetime fields
         if( isset($postvars['datetime_start']) and $postvars['datetime_start'] === '') {
            $postvars['datetime_start'] = 'NULL';
         }
         if( isset($postvars['datetime_end']) and $postvars['datetime_end'] === '') {
            $postvars['datetime_end'] = 'NULL';
         }
         $this->update($postvars);

         Html::back();
      }
   }
}
