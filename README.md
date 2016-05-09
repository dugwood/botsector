# BotSector

See <http://www.dugwood.com/botsector/index.html>.

## Installation
- clone the github repo:
```
git clone https://github.com/dugwood/botsector.git
```
- copy the config/config.sample.ini.php to config/config.ini.php
- edit the config/config.ini.php to suit your needs
- create a new database, or use an existing one
- execute the SQL scripts in the selected database:
	- botsector/resources/schema.sql
	- botsector/resources/BOTSECTOR_CRAWLERS.sql
- point to botsector/index.php

## Upgrade
- upgrade your git:
```
git pull
```
- run the botsector/resources/BOTSECTOR_CRAWLERS.sql again
