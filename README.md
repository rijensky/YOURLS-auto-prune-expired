# YOURLS-auto-prune-expired

[](https://github.com/joshp23/YOURLS-Expiry#yourls-expiry)

[YOURLS](https://github.com/YOURLS/YOURLS)  Plugin that enables configuring expiry time and automatically removing expired links.

The plugin installs a cronjob that deletes the expired links automatically.

## Installation
* Extract the  `auto-prune`  folder from this repo, and place it at  `user/plugins/auto-prune`
* Enable in admin area

## Configuration
* Configure expiry time in the plugin's admin page (default is 14 days)

### IMPORTANT!
* The plugin automatically adds a cronjob when enabled
* The Cronjob will be automatically removed when disabling the plugin

### Tips:
Ada (Cardano): addr1qxn3nqaef0q83d3uan45kpp95mdgquwklfxn02jcq39hdcxsq5egt9sjn9gtdu86k9ttxay5crgcxnn5esaj74kunawqpq6u3z