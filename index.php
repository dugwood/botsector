<?php

/* To handle 401 access, else all calls will end in error */
include './classes/Config.php';
Config::init();
readfile('./static/app.html');
