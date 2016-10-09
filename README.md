# BotSector

See <http://www.dugwood.com/botsector/index.html>.

BotSector analyzes Apache's logs (or other servers' ones, based on NCSA's format) to look for bots and special hits on your server. Then you can explore which bots crawl your website, and what pages are the most important.

There's a demo here: https://botsector.dugwood.com/

## Installation
- clone the github repo:
```
git clone https://github.com/dugwood/botsector.git
```
- copy the config file:
```
cp config/config.sample.ini.php config/config.ini.php
```
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
