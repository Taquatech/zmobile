<?php
session_start();
// Signup

//Send otp
function SendOTP($param){
    global $_;
    if(!isset($param['UPhone']) || trim($param['UPhone']) == "")return ["Failed"=>["Message"=>"To get an OTP a valid phone number is required","Title"=>"Invalid Phone Number"]];
    //1. get the user phone number
    $Phone = $param['UPhone'];
    //return ["Success"=>["Message"=>"OTP Resend to your phone number successfully","Phone"=>$Phone,"Title"=>"OTP Resent"]];
    //2. check if already signup
    $memdet = MemberDetails($Phone);
    if(is_array($memdet)){ //if yes
      return ["Failed"=>["Message"=>"You already have an account with us","Title"=>"Invalid Operation"]];
    }
    //if not a member yet
    //3. Check if otp already generated for the phone
    $otpdet = $_->SelectFirstRow("otp_tb","","Phone='".$_->SqlSafe($Phone)."' Limit 1",MYSQLI_ASSOC);
    if(is_array($otpdet)){ //if otp already exist
        //$sendwait = 20 * 60; //in seconds
       $Use = (int)$otpdet['OtpUse'];
       $Tries = (int)$otpdet['Tries'];
       //if already used, generate new one
       $otp = $Use > 0?mt_rand(100000,999999):$otpdet['OTP'];
       $Tries = $Tries < 1?1:$Tries;
       $Tries = $Use > 0?0:$Tries; //if already used don't let user wait
       $now = new DateTime();
       $sendwait = ($Tries * $Tries) * 3 * 60; //in seconds
       $lastSend = new DateTime($otpdet['SendTime']);
       $diffInSeconds = $now->getTimestamp() - $lastSend->getTimestamp();
       
       //if user need to wait
       if($diffInSeconds < $sendwait){
        $wait = $sendwait - $diffInSeconds; 
        $wait = gmdate("G:i:s", $wait);
        $waitarr = explode(":",$wait);
        $str = "";
        if((int)$waitarr[0] > 0){
            $plur = (int)$waitarr[0] > 1?"s":"";
            $str = $waitarr[0]." hour".$plur." ";
        }if((int)$waitarr[1] > 0){
         $plur = (int)$waitarr[1] > 1?"s":"";
         $str .= (int)$waitarr[1]." minite".$plur." ";
     }if((int)$waitarr[2] > 0){
         $plur = (int)$waitarr[2] > 1?"s":"";
         $str .= (int)$waitarr[2]." second".$plur;
     }
           return ["Success"=>["Message"=>"OTP already sent to your phone number, if you don't receive it in the next <strong>$str</strong>, try again.","Phone"=>$Phone,"Title"=>"OTP Already Sent"]];
       }
       
       $rst = $_->SendSMS("aquaya",$otp,$Phone);
       $rstdet = explode("|",$rst);
       if(strtolower($rstdet[0]) == "success"){ //if successful
           //update
           $upd = $_->Update("otp_tb",["OTP"=>$otp,"SendTime"=>date("Y-m-d H:i:s"),"Tries"=>($Tries + 1),"OtpUse"=>0],"Phone = '".$_->SqlSafe($Phone)."'");
           return ["Success"=>["Message"=>"OTP Resend to your phone number successfully","Phone"=>$Phone,"Title"=>"OTP Resent"]];
       }else{
        return ["Failed"=>["Message"=>"Ooops, we encounter errors sending OTP to your phone number, kindly confirm your phone number and try again","Title"=>"Sending OTP Failed"]];
       }
     
       
    }else{
        //generate otp and send
        $otp = mt_rand(100000,999999);
        //generate the otp
        
       $rst = $_->SendSMS("aquaya",$otp,$Phone);
       $rstdet = explode("|",$rst);
       if(strtolower($rstdet[0]) == "success"){ //if successful
        $inst = $_->Insert("otp_tb",["OTP"=>$otp,"Phone"=>$Phone,"SendTime"=>date("Y-m-d H:i:s"),"OtpUse"=>0,"Tries"=>1]);
        return ["Success"=>["Message"=>"OTP sent to your phone number","Phone"=>$Phone,"Title"=>"OTP Sent"]];
       }else{
        return ["Failed"=>["Message"=>"Ooops, we encounter errors sending OTP to your phone number, kindly confirm your phone number and try again","Title"=>"Sending OTP Failed"]];
       }
    }
   
   // return Failed($rst);
}

//verify otp
function VerifySignUp($param){
    global $_;
    if(!isset($param['UPhone']) || trim($param['UPhone']) == "")return ["Failed"=>["Message"=>"Phone number is required","Title"=>"Invalid Entering"]];
    if(isset($param['otp']) && trim($param['otp']) == "")return ["Failed"=>["Message"=>"OTP field is required","Title"=>"Invalid Entering"]];
    if(isset($param['cUPhone']) && trim($param['cUPhone']) == "")return ["Failed"=>["Message"=>"Phone number comfirmation field is required","Title"=>"Invalid Entering"]];
    if(!isset($param['otp']) && !isset($param['cUPhone']))return ["Failed"=>["Message"=>"All fields are required","Title"=>"Invalid Entering"]];

    $Phone = $param['UPhone'];
    //check if exist
    $memdet = MemberDetails($Phone);
    if(is_array($memdet)){ //if yes
      return ["Failed"=>["Message"=>"You already have an account with us","Title"=>"Invalid Operation"]];
    }

    if(isset($param['otp'])){ //if otp verification
        $otp =$param['otp'];
        $otpexist = $_->SelectFirstRow("otp_tb","","Phone='".$_->SqlSafe($Phone)."' AND OTP='".$_->SqlSafe($otp)."'");
        if(!is_array($otpexist)){ //if otp does not exist
            return ["Failed"=>["Message"=>"Invalid OTP or phone number supplied, check and try again","Title"=>"Wrong OTP"]];
        }

        //update the otp use
        $upd = $_->Update("otp_tb",["OtpUse"=>1],"Phone = '".$_->SqlSafe($Phone)."' AND OTP='".$_->SqlSafe($otp)."'");
        return ["Success"=>["Phone"=>$Phone,"OTP"=>$otp,"Hash"=>md5($Phone.$otp),"VType"=>1]];
    }else{ //comfirmation verification
      //check if phone number is the same
      if(trim($Phone) !== trim($param['cUPhone']))return ["Failed"=>["Message"=>"Phone number comfirmation failed, check your phone number and try again","Title"=>"Invalid Phone Number"]];
      return ["Success"=>["Phone"=>$Phone,"OTP"=>$param['cUPhone'],"Hash"=>md5($Phone.$param['cUPhone']),"VType"=>0]];
    }
    
}

//create account
function CreateAccount($param){
    global $_;
     //return ["Failed"=>["Message"=>json_encode($param),"Title"=>"Gender"]];
    if(isset($param['UPhone']) &&  isset($param['UHash']) &&  isset($param['VType'])){
       
        if(trim($param['UPhone']) == "" || trim($param['UHash']) == "")return ["Failed"=>["Message"=>"Ooops!!!, we just found out that some system data are missing, kindly try the process from the start.","Title"=>"System Data Not Found"]];

        $Phone = $param['UPhone'];

        //get the verification type
        $VType = (int)$param['VType'];


        if($VType == 1){
            //confirm otp
        $otpdet = $_->SelectFirstRow("otp_tb","","Phone='".$_->SqlSafe($Phone)."' Limit 1",MYSQLI_ASSOC);
        if(!is_array($otpdet)){
            return ["Failed"=>["Message"=>"Ooops!!!, OTP details not found, kindly repeat the process from start","Title"=>"OTP Not Found"]];
        }

        //check the hash
        if($param['UHash'] != md5($Phone.$otpdet['OTP'])){
            return ["Failed"=>["Message"=>"Ooops!!!, Your signup process failed the security test.","Title"=>"Security Test Failed"]];
        }
        }else{
            //phone verification
            //check the hash
        if($param['UHash'] != md5($Phone.$Phone)){
            return ["Failed"=>["Message"=>"Ooops!!!, Your signup process failed the security test.","Title"=>"Security Test Failed"]];
        }

        }
        

       //it is a submit operattion
       if(!isset($param['Surname']) || trim($param['Surname']) == "" || !isset($param['FirstName']) || trim($param['FirstName']) == "" || !isset($param['GenderMale']) || !isset($param['GenderFemale'])){
        return ["Failed"=>["Message"=>"Please note, only the Middle Name field is not required, check your enterings and click <strong>Create Account</strong>","Title"=>"Invalid Entering"]];
       }

       //Gender
       $Gender = $param['GenderMale']?"Male":"Female";

       
       //validate password
       if(!isset($param['Passw']) || trim($param['Passw']) == "" || strlen($param['Passw']) < 6){
        return ["Failed"=>["Message"=>"The password you entered is not allowed, password must not be less than six(6) characters","Title"=>"Bad Password"]];
       }

       //confirm password
       if($param['Passw'] != $param['CPassw'])return ["Failed"=>["Message"=>"The confirmation password does not match your password, Check and Try again","Title"=>"Password Confirmation Failed"]];

        //add the member details
        //check if exist
        $memdet = MemberDetails($Phone);
        if(is_array($memdet)){ //if yes
          return ["Failed"=>["Message"=>"You already have an account with us","Title"=>"Invalid Operation"]];
        }

        //add to member_tb
        $mem = $_->InsertID2("member_tb",["Surname"=>$param['Surname'],"Firstname"=>$param['FirstName'],"MiddleName"=>$param['MiddleName'],"Sex"=>$Gender,"Passw"=>md5($param['Passw']),"Phone"=>$Phone,"RegLevel"=>1]);

        if(is_numeric($mem)){ //if added
         return ["Success"=>["MID"=>$mem]];
        }
        return ["Failed"=>["Message"=>"We encounter an internal error while creating your account","Title"=>"Account Creation Failed"]];

    }
    return ["Form"=>["Message"=>""]];
}

// Signup Ends

//Login
function VerifyLogin($param){
    global $_;
       
    if(!isset($param['UPhone']) || !isset($param['lpassw']))return ["Failed"=>["Message"=>"Ooops !!!, we could not found your login details, make sure you are on the right page and try again.","Title"=>"Invalid Parameters"]];
    if(trim($param['UPhone']) == "" || trim($param['lpassw']) == "" || strlen($param['lpassw']) < 6)return ["Failed"=>["Message"=>"Ooops !!!, your supplied credential is invalid, kindly check and try again.","Title"=>"Invalid Credential"]];
    $UphoneDB = $_->SqlSafe($param['UPhone']);
    $HashPassw = md5($param['lpassw']);
    //check credential
    $exist = $_->SelectFirstRow("member_tb","","Phone='$UphoneDB' && Passw='$HashPassw'",MYSQLI_ASSOC);
    if(is_array($exist)){
        $_SESSION['MID'] = $exist['ID'];
      $Status = $exist['Status'];
      if($Status == 0){ //inactive account
        return ["InActive"=>["MID"=>$exist['ID']]];
      }else if($Status == 1){//pending
        return ["Pending"=>["MID"=>$exist['ID']]];
      }else{//active
        return ["Home"=>["MID"=>$exist['ID']]];
      }
    }else{
        return ["Failed"=>["Message"=>"Ooops !!!, your supplied credential is invalid, kindly check and try again.","Title"=>"Invalid Credential"]];
    }
}
//Login End

//Logout
function Logout($param){
    if(isset($_SESSION['MID'])){
        unset($_SESSION['MID']);
        session_destroy();
    }
    return ["Logout"=>1];
}
//Logout End

//Activate
function GotoLevel($param){
    
    $memdet = MemberDetailsID();
    if(!is_array($memdet))return ["Failed"=>["Message"=>"We could not identify the account holder, kindly reload and try again","Title"=>"Account Identification Failed"]];
    if($memdet["RegLevel"] == 1){
        return ["Basic"=>["MID"=>$memdet["ID"]]];
    }elseif($memdet["RegLevel"] == 2){
        return ["Bus"=>["MID"=>$memdet["ID"]]];
    }elseif($memdet["RegLevel"] == 3){
        return ["IDCard"=>["MID"=>$memdet["ID"]]];
    }elseif($memdet["RegLevel"] == 4){ //passprt upload
        return ["Passport"=>["MID"=>$memdet["ID"]]];
    }elseif($memdet["RegLevel"] == 5){ //passprt upload
        return ["Finish"=>["MID"=>$memdet["ID"]]];
    }
    
}

function UpdateBasic($param){
   // return ["Failed"=>["Message"=>(!isset($param['MID']) || (int)$param['MID'] < 1 || !isset($_SESSION['MID']) || (int)$_SESSION['MID'] != (int)$param['MID'] || !isset($param['UPhone']) || trim($param['Uphone']) == ""),"Title"=>"Invalid Parameter"]];
    global $_;
    if(!isset($param['MID']) || (int)$param['MID'] < 1 || !isset($_SESSION['MID']) || (int)$_SESSION['MID'] != (int)$param['MID'] || !isset($param['UPhone']) || trim($param['UPhone']) == "" || !isset($param['RegLevel']) || (int)$param['RegLevel'] < 1){
        return ["Failed"=>["Message"=>"Ooops!!!, we discorvered that the parameters sent to us is invalid, Reload this page and try again","Title"=>"Invalid Parameter"]];
    }
    $RegLevel = (int)$param['RegLevel'];
$Phone = $param['UPhone'];
    //confirm user
    $memdet = MemberDetails($Phone);
    if(!is_array($memdet))return ["Failed"=>["Message"=>"We could not identify the account holder, kindly reload and try again","Title"=>"Account Identification Failed"]];
    if($memdet['ID'] != (int)$param['MID'])return ["Failed"=>["Message"=>"","Title"=>"User Mismatch"]];
     if($memdet['RegLevel'] < $RegLevel){
        $nextLvl = $memdet['RegLevel'];
    }else{
        $nextLvl = $RegLevel + 1;
    }
    $data = ["RegLevel"=>$nextLvl];
    if($RegLevel == 1){
        //Basic Details
        if(!isset($param['StateID']) || (int)$param['StateID'] < 1 || !isset($param['LGAID']) || (int)$param['LGAID'] < 1 || !isset($param['MarSt']) || !isset($param['ResAddr']) || !isset($param['DOB']) || trim($param['DOB']) == ""){
            return ["Failed"=>["Message"=>"Invalid entering, make sure you supply valid information","Title"=>"Invalid Entering"]];
        }
        
        //update member details
        $data = ["StateID"=>$param['StateID'],"LGAID"=>$param['LGAID'],"DOB"=>$_->MysqlDateEncode($param['DOB']),"MaritalSt"=>$param['MarSt'],"Addr"=>$param['ResAddr'],"RegLevel"=>$nextLvl];
       

        
    }elseif($RegLevel == 2){
        if(!isset($param['Occup']) || !isset($param['OffName']) || !isset($param['OffAddr'])){
            return ["Failed"=>["Message"=>"Invalid entering, make sure you supply valid information","Title"=>"Invalid Entering"]];
        }
        //return ["Failed"=>["Message"=>json_encode($param),"Title"=>"Invalid Entering"]];"BVN"=>$param['BVN'],
        $data = ["Occupation"=>$param['Occup'],"BusName"=>$param['OffName'],"BusAddr"=>$param['OffAddr'],"RegLevel"=>$nextLvl];
        //return ["Failed"=>["Message"=>json_encode($data),"Title"=>"Internal Error"]];
    }elseif($RegLevel == 3){
        //ID Details
        if(!isset($param['IDType']) || (int)$param['IDType'] < 1 || !isset($param['IDNum']) || !isset($param['ExpDate']) || !isset($param['ScanID'])){
            return ["Failed"=>["Message"=>"Invalid entering, make sure you supply valid information","Title"=>"Invalid Entering"]];
        }
        //return ["Failed"=>["Message"=>json_encode($param),"Title"=>"Invalid Entering"]];
        //upload file
        $uploadfile = $_->SaveFile($param['ScanID'],$param["/"]."asset/members/idscan",md5($Phone));
        if($uploadfile->Error){
            return ["Failed"=>["Message"=>"We encounter an error while trying to save your Scanned ID Card <br/> Error: ".$uploadfile->Message,"Title"=>"SCANNED ID UPLOAD FAILED"]]; 
        }
        $data = ["IDType"=>$param['IDType'],"IDNum"=>$param['IDNum'],"IDExpDate"=>$_->MysqlDateEncode($param['ExpDate']),"IDScan"=>$uploadfile->FileName,"RegLevel"=>$nextLvl];
        //return ["Failed"=>["Message"=>json_encode($data),"Title"=>"Internal Error"]];
    }elseif($RegLevel == 4){
        //ID Details
        if(!isset($param['PassP']) || trim($param['PassP']) == ""){
            return ["Failed"=>["Message"=>"Invalid entering, make sure set a valid image file","Title"=>"Invalid Entering"]];
        }
        //return ["Failed"=>["Message"=>json_encode($param),"Title"=>"Invalid Entering"]];
        //upload file
        $uploadfile = $_->SaveFile($param['PassP'],$param["/"]."asset/members/passport",md5($Phone));
        if($uploadfile->Error){
            return ["Failed"=>["Message"=>"We encounter an error while trying to save your Passport photograph <br/> Error: ".$uploadfile->Message,"Title"=>"PASSPORT UPLOAD FAILED"]]; 
        }
        $data = ["Passport"=>$uploadfile->FileName,"RegLevel"=>$nextLvl,"Status"=>1];
        //return ["Failed"=>["Message"=>json_encode($data),"Title"=>"Internal Error"]];
    }
    $upd = $_->Update("member_tb",$data,"ID=".$memdet['ID']);
    if(!is_array($upd))return ["Failed"=>["Message"=>"Ooops !!!, we encounter an internal error while updating your record - ".$upd,"Title"=>"Internal Error"]];
    if($nextLvl == 5){ //completed activation process
        //send finish sms
        $sms= "Dear ".$memdet['Surname'].",\nWe have received your account activation request.\nWe will get back to you soon.\nThanks for choosing Aquaya.";
        $rst = $_->SendSMS("aquaya",$sms,$Phone);
       $rstdet = explode("|",$rst);
       if(strtolower($rstdet[0]) == "success"){
           
       }
    }
    return ["Success"=>["MID"=>$memdet['ID']]];
    //"UPhone":"07085980906","UID":"1","RegLevel":"1","StateID":"9","lgaid":"184","DOB":"15\/10\/2020","MarSt":"Single","ResAddr":"dsds"

}

//Activate End

//Utility
//Failed Response
function Failed($msg){
    return ["Failed"=>["Message"=>$msg]];
}

//Get the member details
function MemberDetails($Phone){
    global $_;
    return $_->SelectFirstRow("member_tb","","Phone='".$_->SqlSafe($Phone)."' Limit 1",MYSQLI_ASSOC);
}

function MemberDetailsID($param){
    global $_;
    if((isset($param['MID']) && (int)$param['MID'] < 1) || (isset($_SESSION['MID']) && (int)$_SESSION['MID'] < 1) || (!isset($param['MID']) && !isset($_SESSION['MID'])))return ["Failed"=>["Message"=>"Ooops !!!, Invalid member identification parameter sent","Title"=>"Member Identification Failed"]];
    $MID = isset($param['MID'])?$param['MID']:$_SESSION['MID'];
    $rst = $_->SelectFirstRow("member_tb","*,FORMAT(AccBal,2) as AccBal, COALESCE(AccNum,'******') as AccNum","ID='".$_->SqlSafe($MID)."' Limit 1",MYSQLI_ASSOC);
    if(!is_array($rst)){ //if not found
        return ["Failed"=>["Message"=>"Ooops !!!, we could not find the member details","Title"=>"Member Identification Failed"]];
    }
    //check if stateid exist
    $StateID = $rst['StateID'];
    if(!is_null($StateID) && (int)$StateID > 0){
        //get state details
        $stdet = $_->SelectFirstRow("state_tb","StateID, UCASE(StateName) as StateName","StateID=$StateID LIMIT 1",MYSQLI_ASSOC);
        if(is_array($stdet)){
            $rst = array_merge($rst,$stdet);
        }
    }else{
        $rst = array_merge($rst,["StateName"=>"State of Origin","StateID"=>0]);
    }

    //check if lga exist
    $LGAID = $rst['LGAID'];
    if(!is_null($LGAID) && (int)$LGAID > 0){
        //get lga details
        $lgadet = $_->SelectFirstRow("lga_tb","LGAID, UCASE(LGAName) as LGAName","LGAID=$LGAID LIMIT 1",MYSQLI_ASSOC);
        if(is_array($lgadet)){
            $rst = array_merge($rst,$lgadet);
        }
    }else{
        $rst = array_merge($rst,["LGAName"=>"Local Government Area","LGAID"=>0]); 
    }

    //covert date of birth from db format to display format 
    if(!is_null($rst['DOB']))$rst['DOB'] = $_->MysqlDateDecode($rst['DOB']);
    if(!is_null($rst['IDExpDate']))$rst['IDExpDate'] = $_->MysqlDateDecode($rst['IDExpDate']);

    //process marital status dropdown
    $ramrt = [["MName"=>"Single","MSelect"=>""],
    ["MName"=>"Married","MSelect"=>""],
    ["MName"=>"Divorced","MSelect"=>""],
    ["MName"=>"Widowed","MSelect"=>""],
    ["MName"=>"Others","MSelect"=>""]];
    if(!is_null($rst['MaritalSt']) && trim($rst['MaritalSt']) != ""){
       foreach($ramrt as $key=>$det){
           if(trim($rst['MaritalSt']) == $det["MName"]){
            $ramrt[$key]['MSelect'] = "selected";
           }
       }
    }
    //include the list of marital status to return array (for dropdown menus)
    $rst["MaritalSts"]=$ramrt;
    //id card types
    $idarr = [
    ["IDName"=>"National ID","IDSelect"=>""],
    ["IDName"=>"Voters Card","IDSelect"=>""],
    ["IDName"=>"Drivers License","IDSelect"=>""],
    ["IDName"=>"International Passport","IDSelect"=>""]];
    foreach($idarr as $key=>$det){
        if((int)$rst['IDType'] == $key){
         $idarr[$key]['IDSelect'] = "selected";
        }
    }
    $rst["IDCards"]=$idarr;
    //return ["Failed"=>["Message"=>json_encode($rst),"Title"=>"Member Identification Failed"]];
    return $rst;
}

function GetState($param){
    global $_;
    $states = $_->Select("state_tb","StateID as SID, UCASE(StateName) as SName","1=1",MYSQLI_ASSOC);
    return ["States"=>$states[0]];
}

//function Get LGA
function GetLGA($param){
    global $_;
    if(!isset($param['StateID'])){
      return ["Failed"=>["Message"=>"Invalid State Selected","Title"=>"Internal Error"]] ;  
    }
    if((int)$param['StateID'] < 1){
        return ["Failed"=>["Message"=>"Kindly select your State of Origin before selecting your Local Government Area (LGA)","Title"=>"No State Selected"]] ;  
      }
    if(!isset($param['StateID']))$param['StateID'] = $param['State'];
    $lga =$_->Select("lga_tb","LGAID, UCASE(LGAName) as LGAName","StateID=".$param['StateID']);
    if(is_array($lga) && $lga[1] > 0){
        return ["Success"=>["LGAs"=>$lga[0]]];
    }else{
        return ["Failed"=>["Message"=>"Ooops !!!, no Local Government Area (LGA) found for the selected State","Title"=>"LGA Not Found"]]; 
    }
    }

    //function to preparethe sign up page
    function PrepareSignUp($param){
        global $_;
        $OTPVer = [];
        $ConfirmVer = [];
        //get the otp control
        $otpcontrol = $_->SelectFirstRow("controls_tb","Value","Attr = 'OTPVerify'");
        if(is_array($otpcontrol)){//if record exist
          
              $allowotp = (int)$otpcontrol['Value'];
            if($allowotp > 0){
                //display otp verification
                $OTPVer = [1];
            }else{
                $ConfirmVer = [1];
            }  
            
        }else{
            $OTPVer = [1]; 
        }
        return ["OTPVer"=>$OTPVer,"ComfirmVer"=>$ConfirmVer];
    }

//Utility Ends

function GetHistory($param=[]){
    global $_;
    if((isset($param['MID']) && (int)$param['MID'] < 1) || (isset($_SESSION['MID']) && (int)$_SESSION['MID'] < 1) || (!isset($param['MID']) && !isset($_SESSION['MID'])))return ["Failed"=>["Message"=>"Ooops !!!, Invalid member identification parameter sent","Title"=>"Member Identification Failed"]];
    $MID = isset($param['MID'])?$param['MID']:$_SESSION['MID'];
    //get all transactio history
    $transhis = $_->Select("transaction_tb","","CID=$MID ORDER BY TransTime DESC LIMIT 100");
    $Hist =[];
    if(is_array($transhis) && $transhis[1] > 0){
        $lastday = "";
        /* "History":[
      {"Day":"25","Month":"JAN","Record":[
      {"Amt":"389,389.00","Info":"MNH Tran to Adaobi 374768","Color":"red"},
      {"Amt":"389,389.00","Info":"dsd Tran to Adaobi weerre","Color":"green"}
      ]},
      {"Day":"25","Month":"JAN","Record":[
      {"Amt":"389,000.00","Info":"MNH Tran to Adaobi 374768","Color":"red"}
      ]}
    ] */
    $cnt = 0;
        while($transhisrec = $transhis[0]->fetch_assoc()){
           //get day
           $RecDate = new DateTime($transhisrec['TransTime']);
           $currentday = $RecDate->format("d_m_y");
           if($currentday == $lastday){
            $Hist[$cnt-1]["Record"][] = ["Amt"=>number_format($transhisrec['Amt'],2),"Info"=>$transhisrec['Descr'],"Color"=>$transhisrec['Credit']==1?"green":"red"];
           }else{
            $Hist[$cnt] = ["Day"=>$RecDate->format("d"),"Month"=>$RecDate->format("M"),"Record"=>[
                ["Amt"=>number_format($transhisrec['Amt'],2),"Info"=>$transhisrec['Descr'],"Color"=>$transhisrec['Credit']==1?"green":"red"]
            ]];
            $cnt++;
            $lastday = $currentday;
           }

        }
    }
    return ["History"=>$Hist];
}
?>