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
   @co-author
   @copyright Copyright (c) 2010-2013 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010

   ------------------------------------------------------------------------
 */

class PluginFusioninventoryAgentWakeup extends  CommonDBTM {

   static $rightname = 'plugin_fusioninventory_taskjob';

   function __construct() {
      parent::__construct();
   }

   /**
   * Get name of this type
   *
   * @return text name of this type by language of the user connected
   *
   **/
   static function getTypeName($nb=0) {
      return __('Job', 'fusioninventory');
   }


   /**
    * This class can be created by GLPI framework.
    */
   static function canCreate() {
      return true;
   }

   /*
    * @function cronUpdateDynamicTasks
    * This function update already running tasks with dynamic groups
    */
   static function cronWakeupAgents($crontask) {
      global $DB;

      
      $wakeupArray = array();
      $tasks       = array();
      //Get the maximum number of agent to wakeup, 
      //as allowed in the general configuration
      $config      = new PluginFusioninventoryConfig();
      $maxWakeUp   = $config->getValue('wakeup_agent_max');
      $counter     = 0;
      $continue    = true;
      
      $date        = new DateTime('NOW');
      $day_of_week = $date->format("N");
      $timeinsecs  = $date->format('H') * HOUR_TIMESTAMP + $date->format('i') * MINUTE_TIMESTAMP + $date->format('s');
      
      //Get all timeslots currently active
      $query_timeslot = "SELECT `t`.`id` 
                         FROM `glpi_plugin_fusioninventory_timeslots` as t 
                         INNER JOIN `glpi_plugin_fusioninventory_timeslotentries` as te 
                           ON (`te`.`plugin_fusioninventory_timeslots_id`=`t`.`id`) 
                         WHERE $timeinsecs BETWEEN `te`.`begin` AND `te`.`end` AND `day`='".$day_of_week."'";
      
      //Get all active task requiring an agent wakeup
      //Check all tasks without timeslot or task with a current active timeslot 
      //We get the results in a randomized way, in order not to always launched the same tasks
      $query  = "SELECT `id`, `wakeup_agent_counter`, `wakeup_agent_time`, `last_agent_wakeup` 
                 FROM `glpi_plugin_fusioninventory_tasks` 
                 WHERE `wakeup_agent_time` > 0 
                    AND `wakeup_agent_counter` > 0
                    AND `is_active`='1' 
                    AND ((`plugin_fusioninventory_timeslots_id`='0') 
                    OR (`plugin_fusioninventory_timeslots_id` IN ($query_timeslot))) 
                 ORDER BY RAND()";

      foreach ($DB->request($query) as $task) {
         if (!is_null($task['wakeup_agent_time'])) {
            //Do not wake up is last wake up in inferior to the minimum wake up interval
            $interval   = time() - strtotime($task['last_agent_wakeup']);
            if ($interval < ($task['wakeup_agent_time'] * MINUTE_TIMESTAMP)) {
               continue;
            }
         }
         
         //For each task, get a number of taskjobs at the PREPARED state 
         //(the maximum is defined in wakeup_agent_counter)
         $query_states = "SELECT `taskjobstates`.`plugin_fusioninventory_agents_id`, 
                                 `tasks`.`id` as `taskID`, 
                                 `tasks`.`wakeup_agent_time`,
                                 `tasks`.`last_agent_wakeup`
                          FROM `glpi_plugin_fusioninventory_taskjobstates` as `taskjobstates`,
                               `glpi_plugin_fusioninventory_taskjobs` as `taskjobs`
                          LEFT JOIN `glpi_plugin_fusioninventory_tasks` as `tasks` 
                             ON `tasks`.`id`=`taskjobs`.`plugin_fusioninventory_tasks_id`
                          WHERE `tasks`.`id`='".$task['id']."' 
                             AND `taskjobs`.`id`=`taskjobstates`.`plugin_fusioninventory_taskjobs_id` 
                             AND `taskjobstates`.`state`='".PluginFusioninventoryTaskjobstate::PREPARED."' 
                          ORDER BY RAND() LIMIT ".$task['wakeup_agent_counter'];
         foreach ($DB->request($query_states) as $state) {
            //Check if agent is already added to the list of agents to wake up
            if (!in_array($state['plugin_fusioninventory_agents_id'], $wakeupArray)) {
               //This agent must be woken up
               $wakeupArray[$state['plugin_fusioninventory_agents_id']] = $state['plugin_fusioninventory_agents_id'];
               $counter++;
            }
            //Store task ID
            if (!in_array($state['taskID'], $tasks)) {
               $tasks[] = $state['taskID'];
            }
            
            //Do not process more than the maximum number of wakeup allowed in the configuration
            if ($counter >= $maxWakeUp) {
               Toolbox::logDebug(__("Maximum number of agent wakeup reached", 'fusioninventory').":".$maxWakeUp);
               $continue = false;
               break;
            }
         }
         //We've reached the maximum number of agents to wake up !
         if (!$continue) {
            break;
         }

      }

      //Number of agents successfully woken up
      $wokeup = 0;
      $myTask = new PluginFusioninventoryTask();
      if (!empty($tasks)) {
         //Update last wake up time each task
         $query_lastrun = "UPDATE `glpi_plugin_fusioninventory_tasks` 
                           SET `last_agent_wakeup`='".$_SESSION['glpi_currenttime']."' 
                           WHERE `id` IN (".implode(",", $tasks).")";
         $DB->query($query_lastrun);

         $agent  = new PluginFusioninventoryAgent();
         //Try to wake up agents one by one
         foreach ($wakeupArray as $ID => $value) {
            $agent->getFromDB($ID);
            if ($agent->wakeUp()) {
               $wokeup++;
            }
         }
      }
      
      $crontask->addVolume($wokeup);
      return true;
   }
}

?>
