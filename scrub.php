#!/usr/bin/php -q
<?php 

// 20151014
// Auto Scrubber 
// Uses Samba and xlsx2csv
//
// runs every 2 minutes via cron
// */2 * * * * /root/auto_scrub/scrub.php >/dev/null 2>&1

require 'db.php';
require 'classes.php';

$dnc = new classDnc($DBH); 
$fs  = new classFileSystem();

$ipAddress = array(
    '10.0.0.1',
    '10.0.0.2',
    '10.0.0.3'

); 

$cwd = array(
    '/shared/qub/scrubber',
    '/shared/iconcept/scrubber',
    '/shared/public/scrubber'
);


for($n = 0; $n < count($ipAddress); $n++){    
    // Rebuild the directory structure if somebody deleted them
    $fs->rebuildDirectory($cwd[$n]);
    echo "Processing folder $cwd[$n]\n";
    // convert xlsx files to csv if needed
    $xlsxList = $fs->getFileList("$cwd[$n]/*.xlsx");
    if (count($xlsxList) > 0){
        for($i=0; $i < count($xlsxList);$i++){  
            $fs->xlsx2csv($xlsxList[$i]);
        }   
    }
    
    $csvList = $fs->getFileList("$cwd[$n]/*.csv");

    if (count($csvList) <= 0){ continue; }
    
    $dnc->deleteLeads($ipAddress[$n]);

    // Load all the files to database
    for($i = 0; $i < count($csvList); $i++){    
        $baseName = basename($csvList[$i]); 
        echo"Loading $baseName to database\n";
        $dnc->loadCsv($ipAddress[$n], $csvList[$i]);
        rename($csvList[$i], "$cwd[$n]/original/$baseName");
    }

    // Scrub the phone numbers
    echo"Scrubbing\n";
    $dnc->scrubFederal($ipAddress[$n]);
    $dnc->scrubFederalState($ipAddress[$n]);
    $dnc->scrubCustom($ipAddress[$n]);  

    // Write clean numbers to file
    echo"Saving all files\n";
    for($i = 0; $i < count($csvList); $i++){
        $baseName = basename($csvList[$i]);    
        $phoneNumbers = $dnc->getCleanNumbers($ipAddress[$n], $baseName);
        $fs->saveFile($phoneNumbers, $csvList[$i]);
    } 
}

    
