# IQL4SmartHome
Verbindet IP-Symcon mit dem Symcon Alexa Skill

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanz in IP-Symcon](#4-einrichten-der-instanz-in-ip-symcon)
5. [Scripte](#7-scripte)
6. [Migration von Version 1](#5-migration-von-version-1)
7. [WebFront](#6-webfront)
8. [Bemerkungen](#8-bemerkungen)
9. [FAQ](#9-faq)


### 1. Funktionsumfang

Dieses Skill ermöglicht die Verbindung von IP-Symcom mit Amazon Alexa.

Unterstützte Geräte (müssen im WebFront schaltbar sein): 
 - alle Variablen vom Typ Boolean 
 - alle Variablenprofile mit dem Suffix "°C"
 - alle Variablenprofile mit dem Suffix "%"
 - das Variablenprofil "~HexColor"

Unterstützte Scripte:
- alle, hier gibt es keinerlei Einschränkungen, weitere Informationen siehe "Scripte"


### 2. Voraussetzungen

1. IP-Symcon 4.2 oder neuer
2. Eine gültige Subscription für IP-Symcon!
3. Aktivierter IP-Symcon Connect Dienst.
4. Mindestens ein Amazon Alexa kompatibles Gerät

### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/IQLiving/IQL4Symcon.git`  

### 4. Einrichten der Instanz in IP-Symcon

1. Erstellen Sie eine IQL4SmartHome Instanz
2. Stellen Sie sicher das die Instanz "Symcon Connect is OK" anzeigt
3. Fügen Sie Geräte und / oder Scripte hinzu.
4. Verbinden Sie das [Symcon Skill](http://alexa.amazon.de/spa/index.html#skills/dp/B01MY4T8EN/?ref=skill_dsk_skb_sr_0) mit Ihrem Symcon Account
    - Es wird eine eMail mit einem Aktivierungscode an Ihre Lizenz eMail Adresse geschickt
5. Führen Sie in der Alexa App eine Gerätesuche durch
6. Viel Spaß mit Alexa und IP-Symcon :)

### 5. Scripte
#### Systemvariablen

 Variable   | Wert
-----------|---------
$_IPS['SENDER'] | "AlexaSmartHome"
$_IPS['Variable'] |IQL4SmartHome interne ID
$_IPS['VALUE'] | 	Neuer Wert der Variable
$_IPS['REQUEST']| siehe Request Liste
                                    
- mögliche Werte der Request Variable
    - "TurnOnRequest"
    - "TurnOffRequest"
    - "SetPercentageRequest"
    - "IncrementPercentageRequest"
    - "DecrementPercentageRequest"
    - "SetTargetTemperatureRequest"
    - "IncrementTargetTemperatureRequest"
    - "DecrementTargetTemperatureRequest"
    - "SetColorRequest"



#### Beispiele

- Schalten eines HomeMatic Gerätes

```php
<?
if($_IPS['SENDER'] == "AlexaSmartHome") {
	HM_WriteValueBoolean(12345, "STATE", $_IPS['VALUE']);
}
?>
```




### 6. Migration von Version 1

- Ab Version 2 ist die IQL4SmartHome Instanz eine Kern Instanz.
- Nach dem Modul Update muss die IQL4SmartHome Instanz geöffnet werden und der Konvertieren-Button geklickt werden, danach muss die Instanz geschlossen und erneut geöffnet werden um weitere Konfigurationsänderungen vornehmen zu können. 

### 7. WebFront

Es wird nichts im WebFront angezeigt.

### 8. Bemerkungen

- Eine gültige Subscription für IP-Symcon ist erforderlich!
- Nach einer Änderung innerhalb der Instanz (Hinzufügen oder Entfernen von Geräten oder Scripten) ist ein "Geräte suchen" in der Alexa App notwendig

### 9. FAQ

- Alexa findet keine Geräte:
    - Ist Ihre Subscription gültig?
    - Ist der Connect Dienst aktiviert und verbunden?
    - Ist in der "WebOAuth" Instanz der Eintrag "amazon_smarthome" vorhanden?
    - Werden in der IQL4SmartHome Instanz rote Einträge angezeigt?