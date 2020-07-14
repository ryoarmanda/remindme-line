<?php

$ch = curl_init("https://remindme-line.herokuapp.com/index.php/clearLastWeek");
curl_exec($ch);