# YOURLS-auto-prune-expired

[![Listed in Awesome YOURLS!](https://img.shields.io/badge/Awesome-YOURLS-C5A3BE)](https://github.com/YOURLS/awesome-yourls/)

[YOURLS](https://github.com/YOURLS/YOURLS)  Plugin that enables configuring expiry time and automatically removing expired links.

[](https://github.com/rijensky/YOURLS-auto-prune-expired)

### FINALLY
Auto delete your expired links.
The plugin lets you configure link expiry time in days or minutes (default is 14 days).
It installs a Cronjob that runs once a day (default is at 3am)
The Cronjob removes all expired links from the database.

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