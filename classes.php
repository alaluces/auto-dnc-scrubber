<?php
  
class classFileSystem {
    // list all files specified, and also remove invalid characters on filename
    function getFileList($dir) {       
        $files = array();
        foreach (glob("$dir") as $fileName) {
            $baseName = ereg_replace("[^0-9A-Za-z\.]", "_", basename($fileName));
            $dirName  = dirname($fileName);
            rename($fileName, "$dirName/$baseName");            
            array_push($files, "$dirName/$baseName");
        } 
        return $files;
    }
    
    function deleteByExtension($ext) {
        array_map('unlink', glob("*.$ext"));        
    } 
    
    function xlsx2csv($fileName) {            
        $baseName = basename($fileName);
        $dirName  = dirname($fileName);        
        $csvName  = str_replace('.xlsx', '.csv', $baseName);
        exec("xlsx2csv $fileName $dirName/$csvName");
        rename($fileName, "$dirName/original/$baseName");       
    }
    
    function rebuildDirectory($cwd) {
        $oldmask = umask(0); // this is used to force the mode to 0777
        if (!file_exists("$cwd")) { mkdir("$cwd", 0777); }
        if (!file_exists("$cwd/scrubbed")) { mkdir("$cwd/scrubbed", 0777); }
        if (!file_exists("$cwd/original")) { mkdir("$cwd/original", 0777); } 
        umask($oldmask);
    }
    
    function saveFile($phoneNumbers, $fileName) {
        $baseName = basename($fileName);
        $dirName  = dirname($fileName);        
        $wFile = fopen("$dirName/scrubbed/scrubbed_$baseName","w"); 
        fputs($wFile,"PHONE NUMBER\r\n");    
        foreach ($phoneNumbers as $sPhoneNumber) {
            fputs($wFile,"$sPhoneNumber\r\n");
        }
        fclose($wFile);       
    }   
}

class classDnc {
    
    function __construct($DBH) {
        $this->DBH = $DBH;        
    }
    
    public function deleteLeads($ipAddress) 
    {       
        $STH = $this->DBH->prepare("DELETE FROM `temp_scrub_leads2` WHERE ip_address = :ipAddress");         
        $STH->bindParam(':ipAddress', $ipAddress);        
        $STH->execute();    
    } 
    
    public function loadCsv($ipAddress, $fileName) {
        $baseName = basename($fileName);     
        $dirName  = dirname($fileName);
        $rFile = fopen("$dirName/$baseName","r");           
        while(!feof($rFile)){
            $aFields = explode(",",fgets($rFile));        
            if (count($aFields)==0){continue;}
            unset($phone_number);
            $phone_number = ereg_replace("[^0-9]","","$aFields[0]");        
            if(strlen($phone_number) == 0){ continue; }        
            $this->insertLeads($ipAddress, $baseName, $phone_number);         
        }    
        fclose($rFile);       
    }
    
    // used by $this->loadCsv()
    public function insertLeads($ip_address,$filename,$phone_number) 
    {       
        $STH = $this->DBH->prepare("INSERT INTO temp_scrub_leads2 VALUES(
            :ip_address,                 
            :filename,                   
            :phone_number,               
            '', '', '', '', '', '', '', '', '',      
            'CLEAN'          
            )");         
        $STH->bindParam(':ip_address', $ip_address);                       
        $STH->bindParam(':filename', $filename);                           
        $STH->bindParam(':phone_number', $phone_number);               
        $STH->execute();    
    }    
    
   
    public function scrubFederal($ipAddress) 
    {       
        $STH = $this->DBH->prepare("UPDATE temp_scrub_leads2 AS a, dnc_list_federal AS b 
                    SET a.lead_status = 'FEDERAL' 
                    WHERE a.phone_number = b.phone_number           
                    AND ip_address = :ipAddress");         
        $STH->bindParam(':ipAddress', $ipAddress);        
        $STH->execute();                      
    }    
    
    public function scrubFederalState($ipAddress) 
    {       
        $STH = $this->DBH->prepare("UPDATE temp_scrub_leads2 AS a, dnc_list_federal_state AS b 
                    SET a.lead_status = 'FEDERAL' 
                    WHERE a.phone_number = b.phone_number           
                    AND ip_address = :ipAddress");         
        $STH->bindParam(':ipAddress', $ipAddress);        
        $STH->execute();                      
    }    
    
    public function scrubCustom($ipAddress) 
    {       
        // old version
        $STH = $this->DBH->prepare("UPDATE temp_scrub_leads2 AS a, dnc_list_campaigns_lite AS b 
                    SET a.lead_status = 'CAMPAIGN' 
                    WHERE a.phone_number = b.phone_number           
                    AND ip_address = :ipAddress");         
        $STH->bindParam(':ipAddress', $ipAddress);        
        $STH->execute();                      
    } 
    
    public function getCleanNumbers($ipAddress, $fileName) 
    {           
        $STH = $this->DBH->prepare("SELECT phone_number FROM temp_scrub_leads2
                WHERE filename = :fileName
                AND ip_address = :ipAddress
                AND lead_status = 'CLEAN'");         
        $STH->bindParam(':fileName', $fileName);
        $STH->bindParam(':ipAddress', $ipAddress); 
        $STH->execute(); 
        return $STH->fetchAll(PDO::FETCH_COLUMN, 0);       
    }    
}