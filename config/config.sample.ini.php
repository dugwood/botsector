;<?php exit; ?> Leave this here, for security reasons

[logs]
directory = "/var/log/apache2/"		; main directory of logs (will be parsed for subdirectories too)
extensions = "log"					; extension of the logs (here it will be "*.log")
use_server_ip = 0					; set to 1 if you have multiple servers, with the same filenames, so that each file on each server will be parsed

[websites]
max_levels = 3						; maximum number of directories' levels: 3 will limit /one/two/three/four/five/page.html to /one/two/three/page.html
max_directories = 1000				; maximum number of directories allowed per website. When reached, a merge will be done to reduce the number of directories

[database]
host = localhost
user =
password =
database =

[security]
user = demo
password = demo