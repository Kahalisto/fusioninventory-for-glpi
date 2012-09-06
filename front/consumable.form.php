<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

define('GLPI_ROOT', '..');
include (GLPI_ROOT . "/inc/includes.php");

Session::checkRight("consumable", "r");

if (!isset($_GET["cartridgeitems_id"])) {
   $_GET["cartridgeitems_id"] = "";
}

$con      = new Consumable();
$constype = new ConsumableItem();

if (isset($_POST["add_several"])) {
   $constype->check($_POST["consumableitems_id"],'w');

   for ($i=0 ; $i<$_POST["to_add"] ; $i++) {
      unset($con->fields["id"]);
      $con->add($_POST);
   }
   Event::log($_POST["consumableitems_id"], "consumables", 4, "inventory",
              //TRANS: %s is the user login
              sprintf(__('%s adds consumables'), $_SESSION["glpiname"]));

   Html::back();

} else if (isset($_GET["delete"])) {
   $con->check($_GET["id"],'d');

   if ($con->delete($_GET)) {
      Event::log($_GET["consumableitems_id"], "consumables", 4, "inventory",
                 //TRANS: %s is the user login
                 sprintf(__('%s deletes a consumable'), $_SESSION["glpiname"]));
   }
   Html::back();

} else if (isset($_POST["give"])) {
   $constype->check($_POST["consumableitems_id"],'w');

   if (($_POST["items_id"] > 0)
       && !empty($_POST['itemtype'])) {
      if (isset($_POST["out"])) {
         foreach ($_POST["out"] as $key => $val) {
            $con->out($key,$_POST['itemtype'],$_POST["items_id"]);
         }
      }
      $item = new $_POST['itemtype']();
      $item->getFromDB($_POST["items_id"]);
      Event::log($_POST["consumableitems_id"], "consumables", 5, "inventory",
                 //TRANS: %s is the user login
                 sprintf(__('%s gives a consumable'), $_SESSION["glpiname"]));
   }
   Html::back();

} else if (isset($_GET["restore"])) {
   $con->check($_GET["id"],'w');

   if ($con->restore($_GET)) {
      Event::log($_GET["consumableitems_id"], "consumables", 5, "inventory",
                 //TRANS: %s is the user login
                 sprintf(__('%s restores a consumable'), $_SESSION["glpiname"]));
   }
   Html::back();

} else {
   Html::back();
}
?>