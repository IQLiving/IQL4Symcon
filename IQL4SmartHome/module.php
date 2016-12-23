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

        $children = IPS_GetChildrenIDs($this->InstanceID);
        $discover = array();
        $count = 0;
        foreach($children as $child) {
            $obj = IPS_GetObject($child);
            if($obj['ObjectType'] == 6) {
                $target = IPS_GetLink($child)['TargetID'];
                $vtarget = IPS_GetVariable($target);
                $instance = IPS_GetInstance(IPS_GetParent($target));
                if($vtarget['VariableType'] >= 0 and $vtarget['VariableType'] < 3) {
                    $discover['discoveredAppliances'][$count]['applianceId'] = $target;
                    $discover['discoveredAppliances'][$count]['manufacturerName'] = IPS_GetModule($instance['ModuleInfo']['ModuleID'])['Vendor'];
                    $discover['discoveredAppliances'][$count]['modelName'] = $instance['ModuleInfo']['ModuleName'];
                    $discover['discoveredAppliances'][$count]['friendlyName'] = $obj['ObjectName'];
                    $discover['discoveredAppliances'][$count]['version'] = IPS_GetKernelVersion();
                    $discover['discoveredAppliances'][$count]['friendlyDescription'] = "Symcon Device";
                    if($vtarget['VariableAction'] > 0) {
                        $discover['discoveredAppliances'][$count]['isReachable'] = true;
                    }
                    else {
                        $discover['discoveredAppliances'][$count]['isReachable'] = false;
                    }
                    if($vtarget['VariableType'] == 0) {
                        $discover['discoveredAppliances'][$count]['actions'][] = "turnOn";
                        $discover['discoveredAppliances'][$count]['actions'][] = "turnOff";
                    }
                    $count++;
                }
            }
        }
        $result['header'] = $header;
        $result['payload'] = $discover;
        return $result;
    }

    private function DeviceControl(array $data) {
        $header['messageId'] = $this->GenUUID();
        $header['namespace'] = $data['header']['namespace'];
        $header['name'] = str_replace("Request","Confirmation",$data['header']['name']);
        $header['payloadVersion'] = "2";
        $payload = array();
        if($data['header']['name']  == "TurnOnRequest") {
            $action = true;
            $payload = json_decode("{}");
        }
        elseif($data['header']['name']  == "TurnOffRequest") {
            $action = false;
            $payload = json_decode("{}");
        }

        if(isset($action)) {
            $obj = IPS_GetObject($data['payload']['appliance']['applianceId']);
            IPS_RequestAction($obj['ParentID'],$obj['ObjectIdent'],$action);
        }
        $result['header'] = $header;
        $result['payload'] = $payload;
        return $result;
    }

    protected function ProcessOAuthData() {
        $jsonRequest = file_get_contents('php://input');
        $data = json_decode($jsonRequest,true);

        if($data['header']['namespace'] == "Alexa.ConnectedHome.Discovery") {
            $result = $this->DeviceDiscovery($data);
            echo json_encode($result);
        }
        elseif($data['header']['namespace'] == "Alexa.ConnectedHome.Control") {
            $result = $this->DeviceControl($data);
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
}