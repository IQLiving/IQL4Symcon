<?
class IQL4SmartHome extends IPSModule {

    private $switchFunctions = Array("turnOn", "turnOff");
    private $dimmingFunctions = Array("setPercentage", "incrementPercentage", "decrementPercentage");
    private $targetTemperatureFunctions = Array("setTargetTemperature", "incrementTargetTemperature", "decrementTargetTemperature", "getTargetTemperature");
    private $readingTemperatureFunctions = Array("getTemperatureReading");
    private $rgbColorFunctions = Array("SetColor");
    private $rgbTemeratureFunctions = Array("SetColorTemperature", "IncrementColorTemperature", "DecrementColorTemperature");

    public function Create() {

        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Sender","AlexaSmartHome");
        $this->RegisterPropertyString("Scripts","[]");
        $this->RegisterPropertyString("Variables","[]");
        $this->RegisterPropertyBoolean("EmulateStatus",true);
        $this->RegisterPropertyBoolean("MultipleLinking",false);
    }

    public function ApplyChanges() {

        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterOAuth("amazon_smarthome");

        $newDevices = array();
        $newScripts = array();
        $wasChanged = false;
        if($this->ReadPropertyString("Variables") != "") {
            foreach (json_decode($this->ReadPropertyString("Variables"), true) as $entry) {
                if ($entry['amzID'] == 0) {
                    $entry['amzID'] = $this->GenUUID();
                    $wasChanged = true;
                }
                array_push($newDevices, $entry);
            }
        }
        if($this->ReadPropertyString("Scripts") != "") {
            foreach (json_decode($this->ReadPropertyString("Scripts"), true) as $entry) {
                if ($entry['amzID'] == 0) {
                    $entry['amzID'] = $this->GenUUID();
                    $wasChanged = true;
                }
                array_push($newScripts, $entry);
            }
        }
        if(count($newDevices) >0) {
            IPS_SetProperty($this->InstanceID, "Variables", json_encode($newDevices));
        }
        if(count($newScripts) >0) {
            IPS_SetProperty($this->InstanceID, "Scripts", json_encode($newScripts));
        }
        if($wasChanged == true) {
            IPS_ApplyChanges($this->InstanceID);
        }
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
                return array_merge($this->targetTemperatureFunctions, $this->readingTemperatureFunctions);
            } else {
                return $this->readingTemperatureFunctions;
            }
        }
        // Support RGBColor
        if($profile['ProfileName'] == "~HexColor") {
            return array_merge($this->rgbColorFunctions);
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

        $o = $this->GetListDetails($objectID);

        if($o['Name'] != "") {
            $friendlyName = substr($o['Name'], 0, 128);
        }
        /*
        if($o['ObjectInfo'] != "") {
            $friendlyDescription = substr($o['ObjectInfo'], 0, 128);
        }
        */

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

        return Array(
            'applianceId' => $objectID,
            'manufacturerName' => $moduleVendor,
            'modelName' => $moduleName,
            'friendlyName' => $friendlyName,
            'version' => IPS_GetKernelVersion(),
            'friendlyDescription' => $friendlyDescription,
            'isReachable' => true,
            'actions' => Array()
        );

    }

    private function ConvertRequestV3ToV2($v3Request) {

        // Discovery
        if ($v3Request['directive']['header']['namespace'] == 'Alexa.Discovery') {
            return [
                'header' => [
                    'namespace' => 'Alexa.ConnectedHome.Discovery'
                ]
            ];
        }
        // ReportState
        elseif ($v3Request['directive']['header']['namespace'] == 'Alexa') {
            return [
                'header' => [
                    'namespace' => 'Alexa.ConnectedHome.Query',
                    'name' => 'GetTemperatureReadingRequest'
                ],
                'payload' => [
                    'appliance' => [
                        'applianceId' => $v3Request['directive']['endpoint']['endpointId']
                    ]
                ]
            ];
        }
        else {
            $name = '';
            $payload = [
                'appliance' => [
                    'applianceId' => $v3Request['directive']['endpoint']['endpointId']
                ]
            ];

            switch ($v3Request['directive']['header']['name']) {
                case 'TurnOn':
                    $name = 'TurnOnRequest';
                    break;

                case 'TurnOff':
                    $name = 'TurnOffRequest';
                    break;

                case 'SetBrightness':
                    $name = 'SetPercentageRequest';
                    $payload['percentageState'] = [
                        'value' => $v3Request['directive']['payload']['brightness']
                    ];
                    break;

                case 'AdjustBrightness':
                    if ($v3Request['directive']['payload']['brightnessDelta'] > 0) {
                        $name = 'IncrementPercentageRequest';
                        $payload['deltaPercentage']['value'] = $v3Request['directive']['payload']['brightnessDelta'];
                    }
                    else {
                        $name = 'DecrementPercentageRequest';
                        $payload['deltaPercentage']['value'] = -$v3Request['directive']['payload']['brightnessDelta'];
                    }
                    break;

                case 'SetColor':
                    $name = 'SetColorRequest';
                    $payload['color'] = $v3Request['directive']['payload']['color'];
                    break;

                case 'SetTargetTemperature':
                    $name = 'SetTargetTemperatureRequest';
                    $payload['targetTemperature'] = [
                        'value' => $v3Request['directive']['payload']['targetSetpoint']['value']
                    ];
                    break;

                case 'AdjustTargetTemperature':
                    if ($v3Request['directive']['payload']['targetSetpointDelta']['value'] > 0) {
                        $name = 'IncrementTargetTemperatureRequest';
                        $payload['deltaTemperature']['value'] = $v3Request['directive']['payload']['targetSetpointDelta']['value'];
                    }
                    else {
                        $name = 'DecrementTargetTemperatureRequest';
                        $payload['deltaTemperature']['value'] = -$v3Request['directive']['payload']['targetSetpointDelta']['value'];
                    }
                    break;

                case 'SetColorTemperature':
                    $name = 'SetColorTemperature';
                    $payload['colorTemperature'] = [
                        'value' => $v3Request['directive']['payload']['colorTemperatureInKelvin']
                    ];
                    break;

                case 'IncreaseColorTemperature':
                    $name = 'IncrementColorTemperatureRequest';
                    break;

                case 'DecreaseColorTemperature':
                    $name = 'DecrementColorTemperatureRequest';
                    break;
            }

            return [
                'header' => [
                    'namespace' => 'Alexa.ConnectedHome.Control',
                    'name' => $name
                ],
                'payload' => $payload
            ];

        }
    }

    private function ConvertResponseV2ToV3($v2Response, $applianceId) {
        // Discovery
        if ($v2Response['header']['namespace'] == 'Alexa.ConnectedHome.Discovery') {
            $endpoints = [];

            foreach ($v2Response['payload']['discoveredAppliances'] as $appliance) {
                $capabilities = [];
                $displayCategories = [];
                if (array_intersect($this->switchFunctions, $appliance['actions']) == $this->switchFunctions) {
                    $capabilities[] = [
                        'type' => 'AlexaInterface',
                        'interface' => 'Alexa.PowerController',
                        'version' => '3',
                        'properties' => [
                            'supported' => [[
                                'name' => 'powerState'
                            ]],
                            'proactivelyReported' => false,
                            'retrievable' => false
                        ]
                    ];

                    if (!in_array('LIGHT', $displayCategories)) {
                        $displayCategories[] = 'LIGHT';
                    }
                }

                if (array_intersect($this->dimmingFunctions, $appliance['actions']) == $this->dimmingFunctions) {
                    $capabilities[] = [
                        'type' => 'AlexaInterface',
                        'interface' => 'Alexa.BrightnessController',
                        'version' => '3',
                        'properties' => [
                            'supported' => [[
                                'name' => 'brightness'
                            ]],
                            'proactivelyReported' => false,
                            'retrievable' => false
                        ]
                    ];

                    if (!in_array('LIGHT', $displayCategories)) {
                        $displayCategories[] = 'LIGHT';
                    }
                }

                if (array_intersect($this->targetTemperatureFunctions, $appliance['actions']) == $this->targetTemperatureFunctions) {
                    $capabilities[] = [
                        'type' => 'AlexaInterface',
                        'interface' => 'Alexa.ThermostatController',
                        'version' => '3',
                        'properties' => [
                            'supported' => [[
                                'name' => 'targetSetpoint'
                            ]],
                            'proactivelyReported' => false,
                            'retrievable' => false
                        ]
                    ];

                    if (!in_array('THERMOSTAT', $displayCategories)) {
                        $displayCategories[] = 'THERMOSTAT';
                    }
                }

                if (array_intersect($this->readingTemperatureFunctions, $appliance['actions']) == $this->readingTemperatureFunctions) {
                    $capabilities[] = [
                        'type' => 'AlexaInterface',
                        'interface' => 'Alexa.TemperatureSensor',
                        'version' => '3',
                        'properties' => [
                            'supported' => [[
                                'name' => 'temperature'
                            ]],
                            'proactivelyReported' => false,
                            'retrievable' => true
                        ]
                    ];

                    if (!in_array('TEMPERATURE_SENSOR', $displayCategories)) {
                        $displayCategories[] = 'TEMPERATURE_SENSOR';
                    }
                }

                if (array_intersect($this->rgbColorFunctions, $appliance['actions']) == $this->rgbColorFunctions) {
                    $capabilities[] = [
                        'type' => 'AlexaInterface',
                        'interface' => 'Alexa.ColorController',
                        'version' => '3',
                        'properties' => [
                            'supported' => [[
                                'name' => 'color'
                            ]],
                            'proactivelyReported' => false,
                            'retrievable' => false
                        ]
                    ];

                    if (!in_array('LIGHT', $displayCategories)) {
                        $displayCategories[] = 'LIGHT';
                    }
                }

                if (array_intersect($this->rgbTemeratureFunctions, $appliance['actions']) == $this->rgbTemeratureFunctions) {
                    $capabilities[] = [
                        'type' => 'AlexaInterface',
                        'interface' => 'Alexa.ColorTemperatureController',
                        'version' => '3',
                        'properties' => [
                            'supported' => [[
                                'name' => 'colorTemperatureInKelvin'
                            ]],
                            'proactivelyReported' => false,
                            'retrievable' => false
                        ]
                    ];

                    if (!in_array('LIGHT', $displayCategories)) {
                        $displayCategories[] = 'LIGHT';
                    }
                }

                $endpoints[] = [
                    'endpointId' => $appliance['applianceId'],
                    'friendlyName' => $appliance['friendlyName'],
                    'description' => $appliance['friendlyDescription'],
                    'manufacturerName' => $appliance['manufacturerName'],
                    'displayCategories' => $displayCategories,
                    'capabilities' => $capabilities
                ];
            }

            return [
                'event' => [
                    'header' => [
                        'namespace' => 'Alexa.Discovery',
                        'name' => 'Discover.Response',
                        'payloadVersion' => '3',
                        'messageId' => $v2Response['header']['messageId']
                    ],
                    'payload' => [
                        'endpoints' => $endpoints
                    ]
                ]
            ];
        }
        // Query = ReportState
        elseif ($v2Response['header']['namespace'] == 'Alexa.ConnectedHome.Query') {
            return [
                'context' => [
                    'properties' => [[
                        'namespace' => 'Alexa.TemperatureSensor',
                        'name' => 'temperature',
                        'value' => [
                            'value' => $v2Response['payload']['temperatureReading']['value'],
                            'scale' => 'CELSIUS'
                        ],
                        'timeOfSample' => gmdate('o-m-d\TH:i:s\Z'),
                        'uncertaintyInMilliseconds' => 0
                    ],[
                        'namespace' => 'Alexa.ThermostatController',
                        'name' => 'targetSetpoint',
                        'value' => [
                            'value' => $v2Response['payload']['temperatureReading']['value'],
                            'scale' => 'CELSIUS'
                        ],
                        'timeOfSample' => gmdate('o-m-d\TH:i:s\Z'),
                        'uncertaintyInMilliseconds' => 0
                    ]]
                ],
                'event' => [
                    'header' => [
                        'namespace' => 'Alexa',
                        'name' => 'StateReport',
                        'payloadVersion' => '3',
                        'messageId' => $v2Response['header']['messageId']
                    ],
                    'endpoint' => [
                        'endpointId' => $applianceId
                    ],
                    'payload' => new stdClass()
                ]
            ];
        }
        // Control
        else {
            $context = [];
            switch ($v2Response['header']['name']) {
                case 'SetTargetTemperatureConfirmation':
                case 'IncrementTargetTemperatureConfirmation':
                case 'DecrementTargetTemperatureConfirmation':
                    $context = [
                        'properties' => [[
                            'namespace' => 'Alexa.ThermostatController',
                            'name' => 'targetSetpoint',
                            'value' => [
                                'value' => $v2Response['payload']['targetTemperature']['value'],
                                'scale' => 'CELSIUS'
                            ],
                            'timeOfSample' => gmdate('o-m-d\TH:i:s\Z'),
                            'uncertaintyInMilliseconds' => 0
                        ]]
                    ];
                    break;

                case 'SetColorConfirmation':
                    $context = [
                        'properties' => [[
                            'namespace' => 'Alexa.ColorController',
                            'name' => 'color',
                            'value' => $v2Response['payload']['achievedState']['color'],
                            'timeOfSample' => gmdate('o-m-d\TH:i:s\Z'),
                            'uncertaintyInMilliseconds' => 0
                        ]]
                    ];
                    break;

            }
            $result = [
                'event' => [
                    'header' => [
                        'namespace' => 'Alexa',
                        'name' => 'Response',
                        'payloadVersion' => '3',
                        'messageId' => $v2Response['header']['messageId']
                    ],
                    'endpoint' => [
                        'endpointId' => $applianceId
                    ],
                    'payload' => new stdClass()
                ]
            ];
            if (sizeof($context) > 0) {
                $result['context'] = $context;
            }
            return $result;
        }
    }

    private function DeviceDiscovery(array $data) {
        $childrenIDs = $this->GetChildrenIDs("amzID");

        $appliances = Array();
        foreach($childrenIDs as $childID) {
            $targetID = $this->GetListDetails($childID)['ID'];
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
                $appliance['actions'] = array_merge($this->switchFunctions, $this->dimmingFunctions, $this->targetTemperatureFunctions, $this->rgbColorFunctions,$this->rgbTemeratureFunctions);

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

        $childrenIDs = $this->GetChildrenIDs("ID");

        $appliances = Array();
        foreach($childrenIDs as $childID) {

            $targetID = $childID;

            //Check supported types

            if(!IPS_ObjectExists($targetID)) {
                $checkResult[$childID] = "Not found!";
                continue;
            }

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
        $sourceID = $data['payload']['appliance']['applianceId'];
        $targetID = $this->GetListDetails($data['payload']['appliance']['applianceId'])['ID'];

        $o = IPS_GetObject($targetID);

        $hsvToRGB = function ($iH, $iS, $iV) {

            if($iH < 0)   $iH = 0;   // Hue:
            if($iH > 360) $iH = 360; //   0-360
            if($iS < 0)   $iS = 0;   // Saturation:
            if($iS > 100) $iS = 100; //   0-100
            if($iV < 0)   $iV = 0;   // Lightness:
            if($iV > 100) $iV = 100; //   0-100
            $dS = $iS/100.0; // Saturation: 0.0-1.0
            $dV = $iV/100.0; // Lightness:  0.0-1.0
            $dC = $dV*$dS;   // Chroma:     0.0-1.0
            $dH = $iH/60.0;  // H-Prime:    0.0-6.0
            $dT = $dH;       // Temp variable
            while($dT >= 2.0) $dT -= 2.0; // php modulus does not work with float
            $dX = $dC*(1-abs($dT-1));     // as used in the Wikipedia link
            switch(floor($dH)) {
                case 0:
                    $dR = $dC; $dG = $dX; $dB = 0.0; break;
                case 1:
                    $dR = $dX; $dG = $dC; $dB = 0.0; break;
                case 2:
                    $dR = 0.0; $dG = $dC; $dB = $dX; break;
                case 3:
                    $dR = 0.0; $dG = $dX; $dB = $dC; break;
                case 4:
                    $dR = $dX; $dG = 0.0; $dB = $dC; break;
                case 5:
                    $dR = $dC; $dG = 0.0; $dB = $dX; break;
                default:
                    $dR = 0.0; $dG = 0.0; $dB = 0.0; break;
            }
            $dM  = $dV - $dC;
            $dR += $dM; $dG += $dM; $dB += $dM;
            $dR *= 255; $dG *= 255; $dB *= 255;
            return array(round($dR), round($dG), round($dB));
        };

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

            elseif($data['header']['name'] == "SetColorRequest") {
                if(trim($profile['ProfileName']) == "~HexColor") {
                    $components = $hsvToRGB($data['payload']['color']['hue'],$data['payload']['color']['saturation']*100,$data['payload']['color']['brightness']*100);
                    $value = ($components[0] << 16) + ($components[1] << 8) + $components[2];
                    $payload = Array();
                    $payload['achievedState']['color']['hue'] = $data['payload']['color']['hue'];
                    $payload['achievedState']['color']['saturation'] = $data['payload']['color']['saturation'];
                    $payload['achievedState']['color']['brightness'] = $data['payload']['color']['brightness'];
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
            elseif($data['header']['name'] == "SetColorRequest") {
                $components = $hsvToRGB($data['payload']['color']['hue'],$data['payload']['color']['saturation']*100,$data['payload']['color']['brightness']*100);
                $action = $this->RGBToHex($components[0],$components[1],$components[2]);
                $payload = Array();
                $payload['achievedState']['color']['hue'] = $data['payload']['color']['hue'];
                $payload['achievedState']['color']['saturation'] = $data['payload']['color']['saturation'];
                $payload['achievedState']['color']['brightness'] = $data['payload']['color']['brightness'];
            }
            elseif($data['header']['name'] == "SetColorTemperatureRequest") {
                $action = $data['payload']['colorTemperature']['value'];
            }
            elseif($data['header']['name'] == "IncrementColorTemperatureRequest" or $data['header']['name'] == "DecrementColorTemperatureRequest") {
                $action = true;
            }
            if(isset($action)) {
                IPS_RunScriptEx($targetID, Array("VARIABLE" => $sourceID, "VALUE" => $action, "SENDER" => $this->ReadPropertyString("Sender"), "REQUEST" => $data['header']['name']));
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
        $targetID = $this->GetListDetails($data['payload']['appliance']['applianceId'])['ID'];
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
        $convertResponse = false;
        $previousValue = 0;

        if (isset($data['directive']['header']['payloadVersion']) && $data['directive']['header']['payloadVersion'] == '3') {
            if (isset($data['directive']['endpoint']['endpointId'])) {
                $targetID = $this->GetListDetails($data['directive']['endpoint']['endpointId'])['ID'];
                $targetObject = IPS_GetObject($targetID);
            }
            $data = $this->ConvertRequestV3ToV2($data);
            $convertResponse = true;
            $this->SendDebug("IQL4SmartHomeConvertedResponse",json_encode($data),0);
        }

        $result = '';
        if($data['header']['namespace'] == "Alexa.ConnectedHome.Discovery") {
            ob_start();
            $result = $this->DeviceDiscovery($data);
            $error = ob_get_contents();
            if($error != "") {
                $this->SendDebug("IQL4SmartHomeError", $error, 0);
            }
            ob_end_clean();
        }
        elseif($data['header']['namespace'] == "Alexa.ConnectedHome.Control") {
            ob_start();
            $result = $this->DeviceControl($data);
            $error = ob_get_contents();
            if($error != "") {
                $this->SendDebug("IQL4SmartHomeError", $error, 0);
            }
            ob_end_clean();
        }
        elseif($data['header']['namespace'] == "Alexa.ConnectedHome.Query") {
            ob_start();
            $result = $this->DeviceQuery($data);
            $error = ob_get_contents();
            if($error != "") {
                $this->SendDebug("IQL4SmartHomeError", $error, 0);
            }
            ob_end_clean();
        }

        if ($convertResponse) {
            $this->SendDebug('V2 Response', json_encode($result), 0);
            $applianceId = '';
            if (isset($data['payload']['appliance']['applianceId'])) {
                $applianceId = $data['payload']['appliance']['applianceId'];
            }
            $this->SendDebug("IQL4SmartHomeResult",json_encode($this->ConvertResponseV2ToV3($result, $applianceId)),0);
            echo json_encode($this->ConvertResponseV2ToV3($result, $applianceId));
        }
        else {
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

    protected function GetChildrenIDs($type) {
        $childrenIDs = array();
        foreach(json_decode($this->ReadPropertyString("Variables"),true) as $d) {
            $childrenIDs[] = $d[$type];
        }
        foreach(json_decode($this->ReadPropertyString("Scripts"),true) as $s) {
            $childrenIDs[] = $s[$type];
        }
        return $childrenIDs;
    }

    protected function GetListDetails($amzID) {
        foreach(json_decode($this->ReadPropertyString("Variables"),true) as $d) {
            if($d['amzID'] == $amzID) {
                return $d;
            }
        }
        foreach (json_decode($this->ReadPropertyString("Scripts"),true) as $s) {
            if($s['amzID'] == $amzID) {
                return $s;
            }
        }
        return false;
    }

    public function GetObjectList() {
        $return = array();
        foreach(json_decode($this->ReadPropertyString("Variables"),true) as $d) {
            array_push($return,$d);
        }
        foreach (json_decode($this->ReadPropertyString("Scripts"),true) as $s) {
            array_push($return,$s);
        }
        return $return;
    }

    protected function RGBToHex($r, $g, $b) {
        //String padding bug found and the solution put forth by Pete Williams (http://snipplr.com/users/PeteW)
        $hex = "#";
        $hex.= str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
        $hex.= str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
        $hex.= str_pad(dechex($b), 2, "0", STR_PAD_LEFT);

        return $hex;
    }

    public function ConvertToV2() {

        $convertToUTF8 = function($arr) {
            $strencode = function(&$item, $key) {
                if ( is_string($item) && !mb_detect_encoding($item, 'UTF-8', true) )
                    $item = utf8_encode($item);
                else if ( is_array($item) )
                    array_walk_recursive($item, $strencode);
            };
            array_walk_recursive($arr, $strencode);
            return $arr;
        };

        if($this->ReadPropertyString("Variables") == "[]" and $this->ReadPropertyString("Scripts") == "[]") {
            $newDevices = array();
            $newScripts = array();
            $wasChanged = false;
            $oldDevices = $this->GetChildrenIDsRecursive($this->InstanceID);
            if(count($oldDevices) >0) {
                foreach($oldDevices as $device) {
                    if(IPS_GetObject($device)['ObjectType'] != 6)
                        continue;
                    $targetID = IPS_GetLink($device)['TargetID'];
                    $targetObject = IPS_GetObject($targetID);
                    if($targetObject['ObjectType'] == 2 /* Variable */) {
                        if($this->ReadPropertyBoolean("MultipleLinking") == true) {
                            $d['amzID'] = $device;
                        }
                        else {
                            $d['amzID'] = IPS_GetLink($device)['TargetID'];
                        }
                        $d['ID'] = IPS_GetLink($device)['TargetID'];
                        $d['Name'] = IPS_GetObject($device)['ObjectName'];
                        array_push($newDevices,$d);
                    }
                    elseif($targetObject['ObjectType'] == 3 /* Script */) {
                        if($this->ReadPropertyBoolean("MultipleLinking") == true) {
                            $s['amzID'] = $device;
                        }
                        else {
                            $s['amzID'] = IPS_GetLink($device)['TargetID'];
                        }
                        $s['ID'] = IPS_GetLink($device)['TargetID'];
                        $s['ScriptType'] = "Legacy";
                        $s['Name'] = IPS_GetObject($device)['ObjectName'];
                        array_push($newScripts,$s);
                    }
                }
            }
            if(count($newDevices) > 0) {
                $jsonVariables = json_encode($convertToUTF8($newDevices));
                if($jsonVariables === false) {
                    echo "Fehler, die Konvertierung der Variablen konnte nicht durchgeführt werden";
                    return false;
                }
                IPS_SetProperty($this->InstanceID,"Variables", $jsonVariables);
                $wasChanged = true;
            }
            if(count($newScripts) > 0) {
                $jsonScripts = json_encode($convertToUTF8($newScripts));
                if($jsonScripts === false) {
                    echo "Fehler, die Konvertierung der Skripte konnte nicht durchgeführt werden";
                    return false;
                }
                IPS_SetProperty($this->InstanceID,"Scripts", $jsonScripts);
                $wasChanged = true;
            }
            if($wasChanged == true) {
                IPS_ApplyChanges($this->InstanceID);
            }
            echo "Konvertierung erfolgreich abgeschlossen, bitte die Instanz schließen und wieder öffnen";
            return true;
        }
        else {
            echo "Fehler, die Konvertierung konnte nicht durchgeführt werden";
            return false;
        }
    }

    public function GetConfigurationForm() {
        if($this->ReadPropertyString("Variables") == "[]" and $this->ReadPropertyString("Scripts") == "[]" and count($this->GetChildrenIDsRecursive($this->InstanceID)) >0) {
            $data['elements'][0] = Array("type" => "Label", "label" => "Bitte den Button klicken um in das neue Modulformat zu konvertieren");
            $data['actions'][0] = array("type" => "Button", "label" => "Convert", "onClick" => "IQL4SH_ConvertToV2(\$id);");
        }
        else {
            $data = json_decode(file_get_contents(__DIR__ . "/form.json"),true);
            $devices = $this->DiscoveryCheck();
            $ids = IPS_GetInstanceListByModuleID("{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}");
            if(IPS_GetInstance($ids[0])['InstanceStatus'] != 102) {
                $message = "Error: Symcon Connect is not active!";
            } else {
                $message = "Status: Symcon Connect is OK!";
            }

            $data['elements'][0] = Array("type" => "Label", "label" => $message);

            if($this->ReadPropertyString("Variables") != "") {
                $treeDataDevice = json_decode($this->ReadPropertyString("Variables"),true);
                foreach($treeDataDevice as $treeRowD) {
                    //We only need to add annotations. Remaining data is merged from persistance automatically.
                    //Order is determinted by the order of array elements
                    if(IPS_ObjectExists($treeRowD['ID'])) {
                        if($devices[$treeRowD['ID']] != "OK") {
                            $rowcolor = "#ff0000";
                        }
                        else {
                            $rowcolor = "";
                        }
                        $data['elements'][1]['values'][] = Array(
                            "Device" => IPS_GetLocation($treeRowD['ID']),
                            "State" => $devices[$treeRowD['ID']],
                            "rowColor" => $rowcolor
                        );
                    } else {
                        $data['elements'][1]['values'][] = Array(
                            "Device" => "Not found!",
                            "rowColor" => "#ff0000"
                        );
                    }
                }
            }
            if($this->ReadPropertyString("Scripts") != "") {
                $treeDataScripts = json_decode($this->ReadPropertyString("Scripts"),true);
                foreach($treeDataScripts as $treeRowS) {
                    //We only need to add annotations. Remaining data is merged from persistance automatically.
                    //Order is determinted by the order of array elements
                    if(IPS_ObjectExists($treeRowS['ID'])) {
                        $data['elements'][2]['values'][] = Array(
                            "Script" => IPS_GetLocation($treeRowS['ID']),
                            "State" => "OK",
                        );
                    } else {
                        $data['elements'][2]['values'][] = Array(
                            "Script" => "Not found!",
                            "rowColor" => "#ff0000"
                        );
                    }
                }
            }
        }
        return json_encode($data);
    }
}