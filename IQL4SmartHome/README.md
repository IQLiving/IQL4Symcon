# IQL4SmartHome

## Requirements

1. IP-Symcon 4.1+ (with update from 02.01.2017 or newer)
2. Amazon Alexa compatible device

## Installation

1. Use Core -> Modules to install a new PHP Module. [Open Module]([https://github.com/IQLiving/IQL4Symcon)
2. Create the IQL4SmartHome instance in IP-Symcon
3. Verify that the instance shows "Symcon Connect is OK"
4. Add links to switchable variables beneath the IQL4SmartHome instance. (Hint: Not instances!)
5. Reopen the IQL4SmartHome instance and check that every link is marked as OK
6. Link your Alexa account with the Symcon Skill. [Open Skill](http://alexa.amazon.de/spa/index.html#skills/dp/B01MY4T8EN/?ref=skill_dsk_skb_sr_0)
7. Search for new devices in the Alexa app
8. Have fun with Alexa and IP-Symcon

## Hints

- A valid subscription is required to use the Symcon Connect service
- Links may be added in categories beneath the IQL4SmartHome instance (The category names are not evaluated)
- Links are required to point to variables with actions (You can easily check this through your WebFront)
- After adding/removing links you need to search for new devices in the Alexa app
- Only scripts, boolean variables and integer/float variable with suffix %/°C are supported at the moment