<?
class IQL4SmartHome extends IPSModule {

    private $switchFunctions = Array("turnOn", "turnOff");
    private $dimmingFunctions = Array("setPercentage", "incrementPercentage", "decrementPercentage");
    private $targetTemperatureFunctions = Array("setTargetTemperature", "incrementTargetTemperature", "decrementTargetTemperature", "getTargetTemperature");
    private $readingTemperatureFunctions = Array("getTemperatureReading");

    public function Create() {

        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Sender","AlexaSmartHome");
        $this->RegisterPropertyString("Devices", "");
        $this->RegisterPropertyBoolean("EmulateStatus",true);
        $this->RegisterPropertyBoolean("MultipleLinking",false);
    }

    public function ApplyChanges() {

        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterOAuth("amazon_smarthome");

    }

    private function GetActionsForProfile($profile, $profileAction) {

        if(($profile['ProfileType'] < 0) or ($profile['ProfileType'] >= 3)) {
            return Array();
        }

        //Support all Boolean profile
        if($profile['ProfileType'] == 0) {
            return $this->switchFunctions;
        }

        //Support percent suffix
        if(trim($profile['Suffix']) == "%") {
            return array_merge($this->switchFunctions, $this->dimmingFunctions);
        }

        //Support temperature suffix
        if(trim($profile['Suffix']) == "°C") {
            if ($profileAction > 10000) {
                return $this->targetTemperatureFunctions;
            } else {
                return $this->readingTemperatureFunctions;
            }
        }

        return Array();

    }

    private function GetProfileForVariable($variable) {

        if ($variable['VariableCustomProfile'] != "") {
            return $variable['VariableCustomProfile'];
        } else {
            return $variable['VariableProfile'];
        }

    }

    private function GetActionForVariable($variable) {

        if ($variable['VariableCustomAction'] > 0) {
            return $variable['VariableCustomAction'];
        } else {
            return $variable['VariableAction'];
        }

    }

    private function BuildBasicAppliance($objectID, $targetID, $actionID) {

        $moduleName = "Generic Device";
        $moduleVendor = "Symcon";
        $friendlyName = $moduleName;
        $friendlyDescription = "No further description";

        $o = IPS_GetObject($objectID);
        if($o['ObjectName'] != "") {
            $friendlyName = substr($o['ObjectName'], 0, 128);
        }
        if($o['ObjectInfo'] != "") {
            $friendlyDescription = substr($o['ObjectInfo'], 0, 128);
        }

        //Enrich if we have an instance which will deliver more information
        if(IPS_GetObject($actionID)['ObjectType'] == 1 /* Instance */) {
            $module = IPS_GetModule(IPS_GetInstance($actionID)['ModuleInfo']['ModuleID']);
            $moduleName = $module['ModuleName'];

            //This might be empty which would be invalid. Therefore only copy if we have a valid string
            if($module['Vendor'] != "") {
                $moduleVendor = $module['Vendor'];
            }
        }

        //Enrich if we have a script
        if(IPS_GetObject($actionID)['ObjectType'] == 3 /* Script */) {
            $moduleName = "Generic Script";
        }

        if($this->ReadPropertyBoolean("MultipleLinking") == true) {
            $deviceID = $objectID;
        }
        else {
            $deviceID = $targetID;
        }

        return Array(
            'applianceId' => $deviceID,
            'manufacturerName' => $moduleVendor,
            'modelName' => $moduleName,
            'friendlyName' => $friendlyName,
            'version' => IPS_GetKernelVersion(),
            'friendlyDescription' => $friendlyDescription,
            'isReachable' => true,
            'actions' => Array()
        );

    }

    private function DeviceDiscovery(array $data) {

        $childrenIDs = $this->GetChildrenIDsRecursive($this->InstanceID);

        $appliances = Array();
        foreach($childrenIDs as $childID) {

            //We are only interested in links
            if(IPS_GetObject($childID)['ObjectType'] != 6)
                continue;

            $targetID = IPS_GetLink($childID)['TargetID'];

            //Check supported types
            $targetObject = IPS_GetObject($targetID);

            if($targetObject['ObjectType'] == 2 /* Variable */) {
                $targetVariable = IPS_GetVariable($targetID);
                $profileName = $this->GetProfileForVariable($targetVariable);

                if (!IPS_VariableProfileExists($profileName))
                    continue;

                $profile = IPS_GetVariableProfile($profileName);
                $profileAction = $this->GetActionForVariable($targetVariable);
                $actions = $this->GetActionsForProfile($profile, $profileAction);

                //only allow devices which have actions
                if (sizeof($actions) == 0)
                    continue;

                $appliance = $this->BuildBasicAppliance($childID, $targetID, $profileAction);
                $appliance["isReachable"] = in_array($this->readingTemperatureFunctions, array($actions)) || $profileAction > 10000;
                $appliance['actions'] = $actions;

                //append to discovered devices
                $appliances[] = $appliance;

            } elseif($targetObject['ObjectType'] == 3 /* Script */) {

                $appliance = $this->BuildBasicAppliance($childID, $targetID, $targetID);
                $appliance['actions'] = array_merge($this->switchFunctions, $this->dimmingFunctions, $this->targetTemperatureFunctions);

                //append to discovered devices
                $appliances[] = $appliance;

            }
        }

        return Array(
            'header' => Array(
                'messageId' => $this->GenUUID(),
                'namespace' => "Alexa.ConnectedHome.Discovery",
                'name' => "DiscoverAppliancesResponse",
                'payloadVersion' => "2"
            ),
            'payload' => Array(
                'discoveredAppliances' => $appliances
            )
        );

    }

    private function DiscoveryCheck() {

        $checkResult = Array();

        $childrenIDs = $this->GetChildrenIDsRecursive($this->InstanceID);

        $appliances = Array();
        foreach($childrenIDs as $childID) {

            //We are only interested in links
            if(IPS_GetObject($childID)['ObjectType'] != 6) {
                $checkResult[$childID] = "Object is not a link";
                continue;
            }

            $targetID = IPS_GetLink($childID)['TargetID'];

            //Check supported types
            $targetObject = IPS_GetObject($targetID);

            if($targetObject['ObjectType'] == 2 /* Variable */) {
                $targetVariable = IPS_GetVariable($targetID);
                $profileName = $this->GetProfileForVariable($targetVariable);

                if (!IPS_VariableProfileExists($profileName)) {
                    $checkResult[$childID] = "Profile is missing";
                    continue;
                }

                $profile = IPS_GetVariableProfile($profileName);
                $profileAction = $this->GetActionForVariable($targetVariable);
                $actions = $this->GetActionsForProfile($profile, $profileAction);

                //only allow devices which have actions
                if (sizeof($actions) == 0) {
                    $checkResult[$childID] = "Profile is not compatible";
                    continue;
                }

                $checkResult[$childID] = "OK";

            } elseif($targetObject['ObjectType'] == 3 /* Script */) {

                $checkResult[$childID] = "OK";

            } else {

                $checkResult[$childID] = "Unsupported Objecttype";

            }
        }

        return $checkResult;

    }

    private function DeviceControl(array $data) {

        $payload = new stdClass;
        $headerName = str_replace("Request","Confirmation",$data['header']['name']);
        if(IPS_GetObject($data['payload']['appliance']['applianceId'])['ObjectType'] == 6) {
            $targetID = IPS_GetLink($data['payload']['appliance']['applianceId'])['TargetID'];
        }
        else {
            $targetID = $data['payload']['appliance']['applianceId'];
        }

        $o = IPS_GetObject($targetID);

        if($o['ObjectType'] == 2 /* Variable */) {
            $targetVariable = IPS_GetVariable($targetID);

            if ($targetVariable['VariableCustomProfile'] != "") {
                $profileName = $targetVariable['VariableCustomProfile'];
            } else {
                $profileName = $targetVariable['VariableProfile'];
            }

            $profile = IPS_GetVariableProfile($profileName);

            if ($targetVariable['VariableCustomAction'] != "") {
                $profileAction = $targetVariable['VariableCustomAction'];
            } else {
                $profileAction = $targetVariable['VariableAction'];
            }

            if($profileAction < 1000) {
                echo "No action was defined!";
                return null;
            }

            $percentToValue = function($value) use ($profile) {
                return (($value / 100) * ($profile['MaxValue'] - $profile['MinValue']) + $profile['MinValue']);
            };

            if($data['header']['name']  == "TurnOnRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $value = $profile['MaxValue'];
                } else {
                    $value = true;
                }
            }
            elseif($data['header']['name']  == "TurnOffRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $value = $profile['MinValue'];
                } else {
                    $value = false;
                }
            }
            elseif($data['header']['name'] == "SetPercentageRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $value = $percentToValue($data['payload']['percentageState']['value']);
                }
            }
            elseif($data['header']['name'] == "IncrementPercentageRequest") {
                if (trim($profile['Suffix']) == "%") {
                    $value = GetValue($targetID) + $percentToValue($data['payload']['deltaPercentage']['value']);
                }
            }
            elseif($data['header']['name'] == "DecrementPercentageRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $value = GetValue($targetID) - $percentToValue($data['payload']['deltaPercentage']['value']);
                }
            }
            elseif($data['header']['name'] == "SetTargetTemperatureRequest") {
                if(trim($profile['Suffix']) == "°C") {
                    $value = $data['payload']['targetTemperature']['value'];
                    $payload = Array();
                    $payload['targetTemperature']['value'] = $data['payload']['targetTemperature']['value'];
                    $payload['temperatureMode']['value'] = "AUTO";
                    $payload['previousState']['targetTemperature']['value'] = GetValue($targetID);
                    $payload['previousState']['mode']['value'] = "AUTO";
                }
            }
            elseif($data['header']['name'] == "IncrementTargetTemperatureRequest" or $data['header']['name'] == "DecrementTargetTemperatureRequest") {
                if(trim($profile['Suffix']) == "°C") {
                    if($data['header']['name'] == "IncrementTargetTemperatureRequest") {
                        $value = GetValue($targetID) + $data['payload']['deltaTemperature']['value'];
                    }
                    elseif($data['header']['name'] == "DecrementTargetTemperatureRequest") {
                        $value = GetValue($targetID) - $data['payload']['deltaTemperature']['value'];
                    }
                    $payload = Array();
                    $payload['targetTemperature']['value'] = $value;
                    $payload['temperatureMode']['value'] = "AUTO";
                    $payload['previousState']['targetTemperature']['value'] = GetValue($targetID);
                    $payload['previousState']['mode']['value'] = "AUTO";
                }
            }

            if(!isset($value)) {
                echo "Action value is missing";
                return null;
            }

            if($targetVariable['VariableType'] == 0 /* Boolean */) {
                $value = boolval($value);
            } else if($targetVariable['VariableType'] == 1 /* Integer */) {
                $value = intval($value);
            } else if($targetVariable['VariableType'] == 2 /* Float */) {
                $value = floatval($value);
            } else {
                echo "Strings are not supported";
                return null;
            }

            if(IPS_InstanceExists($profileAction)) {
                if($this->ReadPropertyBoolean("EmulateStatus") == true) {
                    IPS_RunScriptText("IPS_RequestAction(".var_export($profileAction, true).", ".var_export($o['ObjectIdent'], true).", ".var_export($value, true).");");
                }
                else {
                    $result = IPS_RequestAction($profileAction, $o['ObjectIdent'], $value);
                }

            } elseif(IPS_ScriptExists($profileAction)) {
                IPS_RunScriptEx($profileAction, Array("VARIABLE" => $targetID, "VALUE" => $value, "SENDER" => $this->ReadPropertyString("Sender")));
            }

        }
        elseif($o['ObjectType'] == 3 /* Script */) {
            if($data['header']['name']  == "TurnOnRequest") {
                $action = true;
            }
            elseif($data['header']['name']  == "TurnOffRequest") {
                $action = false;
            }
			elseif($data['header']['name'] == "SetPercentageRequest") {
				$action = $data['payload']['percentageState']['value'];
			}
			elseif($data['header']['name'] == "IncrementPercentageRequest" or $data['header']['name'] == "DecrementPercentageRequest") {
				$action = $data['payload']['deltaPercentage']['value'];
			}
			elseif($data['header']['name'] == "SetTargetTemperatureRequest") {
				$action = $data['payload']['targetTemperature']['value'];
			}
			elseif($data['header']['name'] == "IncrementTargetTemperatureRequest" or $data['header']['name'] == "DecrementTargetTemperatureRequest") {
				$action = $data['payload']['deltaTemperature']['value'];
			}

            if(isset($action)) {
                IPS_RunScriptEx($targetID, Array("VALUE" => $action, "SENDER" => $this->ReadPropertyString("Sender")));
            }
        }
        
        if(isset($result)) {
            if($result == false) {
                $headerName = 'TargetHardwareMalfunctionError';
                $payload = new stdClass;
            }
        }
        return Array(
            'header' => Array(
                'messageId' => $this->GenUUID(),
                'namespace' => $data['header']['namespace'],
                'name' => $headerName,
                'payloadVersion' => "2"
            ),
            'payload' => $payload
        );
    }

    private function DeviceQuery (array $data) {
        $payload = new stdClass;
        if(IPS_GetObject($data['payload']['appliance']['applianceId'])['ObjectType'] == 6) {
            $targetID = IPS_GetLink($data['payload']['appliance']['applianceId'])['TargetID'];
        }
        else {
            $targetID = $data['payload']['appliance']['applianceId'];
        }
        $o = IPS_GetObject($targetID);

        if($o['ObjectType'] == 2 /* Variable */) {
            if($data['header']['name']  == "GetTargetTemperatureRequest") {
                $payload = Array();
                $payload['targetTemperature']['value'] = GetValue($targetID);
                $payload['temperatureMode']['value'] = "AUTO";
            }
            elseif($data['header']['name']  == "GetTemperatureReadingRequest") {
                $payload = Array();
                $payload['temperatureReading']['value'] = GetValue($targetID);
            }
        }

        return Array(
            'header' => Array(
                'messageId' => $this->GenUUID(),
                'namespace' => $data['header']['namespace'],
                'name' => str_replace("Request","Response",$data['header']['name']),
                'payloadVersion' => "2"
            ),
            'payload' => $payload
        );
    }

    protected function ProcessOAuthData() {
        $jsonRequest = file_get_contents('php://input');
        $data = json_decode($jsonRequest,true);
        $this->SendDebug("IQL4SmartHomeRequest",print_r($data,true),0);

        if($data['header']['namespace'] == "Alexa.ConnectedHome.Discovery") {
            ob_start();
            $result = $this->DeviceDiscovery($data);
            $error = ob_get_contents();
            if($error != "") {
                $this->SendDebug("IQL4SmartHomeError", $error, 0);
            }
            ob_end_clean();
            $this->SendDebug("IQL4SmartHomeResult",print_r($result,true),0);
            echo json_encode($result);
        }
        elseif($data['header']['namespace'] == "Alexa.ConnectedHome.Control") {
            ob_start();
            $result = $this->DeviceControl($data);
            $error = ob_get_contents();
            if($error != "") {
                $this->SendDebug("IQL4SmartHomeError", $error, 0);
            }
            ob_end_clean();
            $this->SendDebug("IQL4SmartHomeResult",print_r($result,true),0);
            echo json_encode($result);
        }
        elseif($data['header']['namespace'] == "Alexa.ConnectedHome.Query") {
            ob_start();
            $result = $this->DeviceQuery($data);
            $error = ob_get_contents();
            if($error != "") {
                $this->SendDebug("IQL4SmartHomeError", $error, 0);
            }
            ob_end_clean();
            $this->SendDebug("IQL4SmartHomeResult",print_r($result,true),0);
            echo json_encode($result);
        }
    }

    private function RegisterOAuth($WebOAuth) {
        $ids = IPS_GetInstanceListByModuleID("{F99BF07D-CECA-438B-A497-E4B55F139D37}");
        if(sizeof($ids) > 0) {
            $clientIDs = json_decode(IPS_GetProperty($ids[0], "ClientIDs"), true);
            $found = false;
            foreach($clientIDs as $index => $clientID) {
                if($clientID['ClientID'] == $WebOAuth) {
                    if($clientID['TargetID'] == $this->InstanceID)
                        return;
                    $clientIDs[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if(!$found) {
                $clientIDs[] = Array("ClientID" => $WebOAuth, "TargetID" => $this->InstanceID);
            }
            IPS_SetProperty($ids[0], "ClientIDs", json_encode($clientIDs));
            IPS_ApplyChanges($ids[0]);
        }
    }

    protected function GenUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function GetChildrenIDsRecursive($parentID, $appendIDs = Array()) {
        foreach(IPS_GetChildrenIDs($parentID) as $childID) {
            if(IPS_GetObject($childID)['ObjectType'] == 0 /* Category */) {
                $appendIDs = $this->GetChildrenIDsRecursive($childID, $appendIDs);
            } else {
                $appendIDs[] = $childID;
            }
        }
        return $appendIDs;
    }

    public function GetConfigurationForm() {

        $form = Array(
            "elements" => Array()
        );

        //Check Connect service
        $ids = IPS_GetInstanceListByModuleID("{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}");
        if(IPS_GetInstance($ids[0])['InstanceStatus'] != 102) {
            $message = "Error: Symcon Connect is not active!";
        } else {
            $message = "Status: Symcon Connect is OK!";
        }
        $form['elements'][] = Array("type" => "CheckBox", "name" => "EmulateStatus", "caption" => "Emulate status");
        $form['elements'][] = Array("type" => "CheckBox", "name" => "MultipleLinking", "caption" => "Multiple linking");

        $form['elements'][] = Array("type" => "Label", "label" => $message);

        //Add spacing
        $form['elements'][] = Array("type" => "Label", "label" => "----------------------------------------------------" );

        //Check Devices
        $devices = $this->DiscoveryCheck();
        foreach($devices as $id => $message) {
            $form['elements'][] = Array("type" => "Label", "label" => IPS_GetName($id) . ": " . $message);
        }

        return json_encode($form);
    }
}