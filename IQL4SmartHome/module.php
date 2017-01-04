<?
class IQL4SmartHome extends IPSModule {

    public function Create() {
        //Never delete this line!
        parent::Create();
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
    }

    public function ApplyChanges() {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterOAuth("amazon_smarthome");
    }

    private function DeviceDiscovery(array $data) {
        $header['messageId'] = $this->GenUUID();
        $header['namespace'] = "Alexa.ConnectedHome.Discovery";
        $header['name'] = "DiscoverAppliancesResponse";
        $header['payloadVersion'] = "2";

        $children = $this->GetAllChildIDs($this->InstanceID);
        $discover = array();
        $count = 0;
        foreach($children as $child) {
            $obj = IPS_GetObject($child);
            if($obj['ObjectType'] == 6) {
                $target = IPS_GetLink($child)['TargetID'];
                $objtarget = IPS_GetObject($target);
                if($objtarget['ObjectType'] == 2) {
                    $vtarget = IPS_GetVariable($target);
                    if($vtarget['VariableProfile'] == "") {
                        $vprofile = IPS_GetVariableProfile($vtarget['VariableCustomProfile']);
                    }
                    else {
                        $vprofile = IPS_GetVariableProfile($vtarget['VariableProfile']);
                    }
                    if(IPS_GetObject(IPS_GetParent($target))['ObjectType'] == 1) {
                        $instance = IPS_GetInstance(IPS_GetParent($target));
                        $ModuleName = $instance['ModuleInfo']['ModuleName'];
                        if(IPS_GetModule($instance['ModuleInfo']['ModuleID'])['Vendor'] == "") {
                            $vendor = "Symcon";
                        }
                        else {
                            $vendor = IPS_GetModule($instance['ModuleInfo']['ModuleID'])['Vendor'];
                        }
                    }
                    else {
                        $ModuleName = "Symcon Modul";
                        $vendor = "Symcon";
                    }
                    if ($vtarget['VariableType'] >= 0 and $vtarget['VariableType'] < 3) {
                        if ($vtarget['VariableType'] == 0) {
                            $discover['discoveredAppliances'][$count]['applianceId'] = $target;
                            $discover['discoveredAppliances'][$count]['manufacturerName'] = $vendor;
                            $discover['discoveredAppliances'][$count]['modelName'] = $ModuleName;
                            $discover['discoveredAppliances'][$count]['friendlyName'] = $obj['ObjectName'];
                            $discover['discoveredAppliances'][$count]['version'] = IPS_GetKernelVersion();
                            $discover['discoveredAppliances'][$count]['friendlyDescription'] = "Symcon Device";
                            if ($vtarget['VariableAction'] > 0 or $vtarget['VariableCustomAction'] > 0) {
                                $discover['discoveredAppliances'][$count]['isReachable'] = true;
                            } else {
                                $discover['discoveredAppliances'][$count]['isReachable'] = false;
                            }
                            $discover['discoveredAppliances'][$count]['actions'][] = "turnOn";
                            $discover['discoveredAppliances'][$count]['actions'][] = "turnOff";
                            $count++;
                        } elseif (trim($vprofile['Suffix']) == "%") {
                            $discover['discoveredAppliances'][$count]['applianceId'] = $target;
                            $discover['discoveredAppliances'][$count]['manufacturerName'] = $vendor;
                            $discover['discoveredAppliances'][$count]['modelName'] = $ModuleName;
                            $discover['discoveredAppliances'][$count]['friendlyName'] = $obj['ObjectName'];
                            $discover['discoveredAppliances'][$count]['version'] = IPS_GetKernelVersion();
                            $discover['discoveredAppliances'][$count]['friendlyDescription'] = "Symcon Device";
                            if ($vtarget['VariableAction'] > 0) {
                                $discover['discoveredAppliances'][$count]['isReachable'] = true;
                            } else {
                                $discover['discoveredAppliances'][$count]['isReachable'] = false;
                            }
                            $discover['discoveredAppliances'][$count]['actions'][] = "setPercentage";
                            $discover['discoveredAppliances'][$count]['actions'][] = "incrementPercentage";
                            $discover['discoveredAppliances'][$count]['actions'][] = "decrementPercentage";
                            $discover['discoveredAppliances'][$count]['actions'][] = "turnOn";
                            $discover['discoveredAppliances'][$count]['actions'][] = "turnOff";
                            $count++;
                        } elseif (trim($vprofile['Suffix']) == "°C") {
                            $discover['discoveredAppliances'][$count]['applianceId'] = $target;
                            $discover['discoveredAppliances'][$count]['manufacturerName'] = $vendor;
                            $discover['discoveredAppliances'][$count]['modelName'] = $ModuleName;
                            $discover['discoveredAppliances'][$count]['friendlyName'] = $obj['ObjectName'];
                            $discover['discoveredAppliances'][$count]['version'] = IPS_GetKernelVersion();
                            $discover['discoveredAppliances'][$count]['friendlyDescription'] = "Symcon Device";
                            if ($vtarget['VariableAction'] > 0) {
                                $discover['discoveredAppliances'][$count]['isReachable'] = true;
                            } else {
                                $discover['discoveredAppliances'][$count]['isReachable'] = false;
                            }
                            $discover['discoveredAppliances'][$count]['actions'][] = "setTargetTemperature";
                            $discover['discoveredAppliances'][$count]['actions'][] = "incrementTargetTemperature";
                            $discover['discoveredAppliances'][$count]['actions'][] = "decrementTargetTemperature";
                            $count++;
                        }
                    }
                }
                elseif($objtarget['ObjectType'] == 3) {
                    $discover['discoveredAppliances'][$count]['applianceId'] = $target;
                    $discover['discoveredAppliances'][$count]['manufacturerName'] = "Symcon";
                    $discover['discoveredAppliances'][$count]['modelName'] = "Symcon Scene";
                    $discover['discoveredAppliances'][$count]['friendlyName'] = $obj['ObjectName'];
                    $discover['discoveredAppliances'][$count]['version'] = IPS_GetKernelVersion();
                    $discover['discoveredAppliances'][$count]['friendlyDescription'] = "Symcon Script";
                    $discover['discoveredAppliances'][$count]['isReachable'] = true;
                    $discover['discoveredAppliances'][$count]['actions'][] = "turnOn";
                    $discover['discoveredAppliances'][$count]['actions'][] = "turnOff";
                    $count++;
                }
            }
        }
        $result['header'] = $header;
        $result['payload'] = $discover;
        return $result;
    }

    private function DeviceControl(array $data) {
        $obj = IPS_GetObject($data['payload']['appliance']['applianceId']);
        if($obj['ObjectType'] == 2) {
            $var = IPS_GetVariable($data['payload']['appliance']['applianceId']);
            if($var['VariableProfile'] == "") {
                $profile = IPS_GetVariableProfile($var['VariableCustomProfile']);
            }
            else {
                $profile = IPS_GetVariableProfile($var['VariableProfile']);
            }
            $header['messageId'] = $this->GenUUID();
            $header['namespace'] = $data['header']['namespace'];
            $header['name'] = str_replace("Request","Confirmation",$data['header']['name']);
            $header['payloadVersion'] = "2";
            if($data['header']['name']  == "TurnOnRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $action = ((100 / 100) * ($profile['MaxValue'] - $profile['MinValue']) + $profile['MinValue']);
                }
                else {
                    $action = true;
                }
            }
            elseif($data['header']['name']  == "TurnOffRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $action = ((0 / 100) * ($profile['MaxValue'] - $profile['MinValue']) + $profile['MinValue']);
                }
                else {
                    $action = false;
                }
            }
            elseif($data['header']['name'] == "SetPercentageRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $action = (($data['payload']['percentageState']['value'] / 100) * ($profile['MaxValue'] - $profile['MinValue']) + $profile['MinValue']);
                }
            }
            elseif($data['header']['name'] == "IncrementPercentageRequest" or $data['header']['name'] == "DecrementPercentageRequest") {
                if(trim($profile['Suffix']) == "%") {
                    $oldvalue = GetValue($data['payload']['appliance']['applianceId']);
                    $newvalue = (($data['payload']['deltaPercentage']['value'] / 100) * ($profile['MaxValue'] - $profile['MinValue']) + $profile['MinValue']);
                    if($data['header']['name'] == "IncrementPercentageRequest") {
                        $action = $oldvalue + $newvalue;
                    }
                    else {
                        $action = $oldvalue - $newvalue;
                    }
                }
            }
            elseif($data['header']['name'] == "SetTargetTemperatureRequest") {
                if(trim($profile['Suffix']) == "°C") {
                    $action = $data['payload']['targetTemperature']['value'];
                    $payload['targetTemperature']['value'] = $data['payload']['targetTemperature']['value'];
                    $payload['temperatureMode']['value'] = "AUTO";
                    $payload['previousState']['targetTemperature']['value'] = GetValue($data['payload']['appliance']['applianceId']);
                    $payload['previousState']['mode']['value'] = "AUTO";
                }
            }
            elseif($data['header']['name'] == "IncrementTargetTemperatureRequest" or $data['header']['name'] == "DecrementTargetTemperatureRequest") {
                if(trim($profile['Suffix']) == "°C") {
                    if($data['header']['name'] == "IncrementTargetTemperatureRequest") {
                        $action = GetValue($data['payload']['appliance']['applianceId']) + $data['payload']['deltaTemperature']['value'];
                    }
                    elseif($data['header']['name'] == "DecrementTargetTemperatureRequest") {
                        $action = GetValue($data['payload']['appliance']['applianceId']) - $data['payload']['deltaTemperature']['value'];
                    }
                    $payload['targetTemperature']['value'] = $action;
                    $payload['temperatureMode']['value'] = "AUTO";
                    $payload['previousState']['targetTemperature']['value'] = GetValue($data['payload']['appliance']['applianceId']);
                    $payload['previousState']['mode']['value'] = "AUTO";
                }
            }

            if(isset($action)) {
                if($var['VariableCustomAction'] > 0) {
                    IPS_RunScriptEx($var['VariableCustomAction'], Array("VARIABLE" => $data['payload']['appliance']['applianceId'], "VALUE" => $action));
                } else {
                    $obj = IPS_GetObject($data['payload']['appliance']['applianceId']);
                    IPS_RequestAction($obj['ParentID'],$obj['ObjectIdent'],$action);
                    $this->SendDebug("RequestAction",$action,0);
                }
            }
            $result['header'] = $header;
            if(isset($payload)) {
                $result['payload'] = $payload;
            }
            else {
                $result['payload'] = json_decode("{}");
            }

            return $result;
        }
        elseif($obj['ObjectType']== 3) {
            if($data['header']['name']  == "TurnOnRequest") {
                $action = true;
            }
            elseif($data['header']['name']  == "TurnOffRequest") {
                $action = false;
            }
            if(isset($action)) {
                IPS_RunScriptEx($data['payload']['appliance']['applianceId'], Array("VALUE" => $action));
            }
            $header['messageId'] = $this->GenUUID();
            $header['namespace'] = $data['header']['namespace'];
            $header['name'] = str_replace("Request","Confirmation",$data['header']['name']);
            $header['payloadVersion'] = "2";
            $result['header'] = $header;
            $result['payload'] = json_decode("{}");
            return $result;
        }
        $header['messageId'] = $this->GenUUID();
        $header['namespace'] = $data['header']['namespace'];
        $header['name'] = str_replace("Request","Confirmation",$data['header']['name']);
        $header['payloadVersion'] = "2";
        $result['header'] = $header;
        $result['payload'] = json_decode("{}");
        return $result;
    }

    protected function ProcessOAuthData() {
        $jsonRequest = file_get_contents('php://input');
        $data = json_decode($jsonRequest,true);
        $this->SendDebug("IQL4SmartHomeRequest",print_r($data,true),0);

        if($data['header']['namespace'] == "Alexa.ConnectedHome.Discovery") {
            $result = @$this->DeviceDiscovery($data);
            $this->SendDebug("IQL4SmartHomeResult",print_r($result,true),0);
            echo json_encode($result);
        }
        elseif($data['header']['namespace'] == "Alexa.ConnectedHome.Control") {
            $result = @$this->DeviceControl($data);
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
    protected function GetAllChildIDs($parent) {
        $children = IPS_GetChildrenIDs($parent);
        foreach($children as $entry) {
            if(IPS_GetObject($entry)['ObjectType'] == 0) {
                $kategorie[] = $entry;
            }
            elseif(IPS_GetObject($entry)['ObjectType'] == 6) {
                $return[] = IPS_GetObject($entry)['ObjectID'];
            }
        }
        if(isset($kategorie)) {
            foreach($kategorie as $locate) {
                foreach($this->GetAllChildIDs($locate) as $newentry) {
                    $return[] = $newentry;
                }
            }
        }
        return $return;
    }

    public function GetConfigurationForm() {
        $connectMod = IPS_GetInstanceListByModuleID("{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}");
        $connectIns = IPS_GetInstance($connectMod[0]);
        if($connectIns['InstanceStatus'] != 102) {
            $return = '{
    "elements":
    [
		{ "type": "Label", "label": "Bitte ConnectControl Prüfen" }
    ]
}';
        }
        else {
            $return = '{}';
        }
        return $return;
    }
}