<?php
session_start();
include "utility.php";
function BuyAirtime($param=[]){
    /* "MTN":true,"Glo":false,"Airtel":false,"9Mobile":false,"Amt":"3000","PhoneNo":"07035033918","UpassW":"kogistae" */
    global $_;
    if((isset($param['MID']) && (int)$param['MID'] < 1) || (isset($_SESSION['MID']) && (int)$_SESSION['MID'] < 1) || (!isset($param['MID']) && !isset($_SESSION['MID'])))return ["Failed"=>["Message"=>"Ooops !!!, Invalid customer identification parameter sent","Title"=>"Customer Identification Failed"]];
    $MID = isset($param['MID'])?$param['MID']:$_SESSION['MID'];
$FromPrompt = isset($param['FrmPrompt']) && (int)$param['FrmPrompt'] == 1;
$IsData = isset($param['DataPlan']) && (int)$param['DataPlan'] > 0;
//return ["Failed"=>["Message"=>json_encode($param)]];
//if not from prompt
    if(!$FromPrompt){
        if(!isset($param['UpassW']) || trim($param['UpassW']) == "")return ["Failed"=>["Message"=>"INVALID PASSWORD"]];
        $HashPassw = md5($param['PassW']);
        $networks = ["MTN"=>"MTN","Glo"=>"Globalcom","Airtel"=>"Airtel","9Mobile"=>"9Mobile"];
    $selnetwork = "";
    foreach($networks as $net=>$netdescr){
        if(isset($param[$net]) && $param[$net] === true){
            $selnetwork = $netdescr;
        break;
        }
    }
    //if no network selected
    if(trim($selnetwork) == ""){
        return ["Failed"=>["Message"=>"No valid Network Selected"]];
    }

    }
    
    //get the phone number
    if(!isset($param['PhoneNo']) || trim($param['PhoneNo']) == "" || (int)$param['PhoneNo'] == 0)return ["Failed"=>["Message"=>"Invalid Phone Number"]];
    $dataplan =0;
    if(!$IsData){
       //get the amount
    if(!isset($param['Amt']) || (float)$param['Amt'] < 50)return ["Failed"=>["Message"=>"Invalid Amount Entered"]]; 
    }else{
        /* <option value="1">₦500 (1G Daily)</option>
                            <option value="2">₦750 (700MB Weekly)</option>
                            <option value="3">₦1000 (1GB Montly)</option>
                            <option value="4">₦1500 (2GB Montly)</option>
                            <option value="5">₦2000 (2.5GB Montly)</option>
                            <option value="6">₦2500 (3GB Montly)</option> */
        $dataplanarr = [1=>[500,"1G Daily"],[750,"700MB Weekly"],[1000,"1GB Montly"],[1500,"2GB Montly"],[2000,"2.5GB Montly"],[2500,"GB Montly"]];
        $dataplan = (int)$param['DataPlan'];
        if(!isset($dataplanarr[$dataplan])){
            return ["Failed"=>["Message"=>"Invalid Data Plan Selected"]]; 
        }else{
            $param['Amt'] = $dataplanarr[$dataplan][0];
            $selnetwork .=  " ".$dataplanarr[$dataplan][1];
        }
    }
    
       //get the senders details
       $Sender = $_->SFR("member_tb","ID,Surname, Firstname, Middlename, AccNum, FORMAT(AccBal,2) as AccBal, AccBal as PAccBal,Passw","ID=$MID");
       if(!is_array($Sender))return ["Failed"=>["Message"=>"Ooops !!!, We encounter error reading your Account Details.","Title"=>"Customer Identification Failed"]];

       if(!$FromPrompt){
       //verify password
       $HashPassw = md5($param['UpassW']);
       if($Sender['Passw'] != $HashPassw)return ["Failed"=>["Message"=>"Ooops !!!, Wrong Password Supplied"]];
       }

     if(((float)$Sender['PAccBal'] - 1000) < (float)$param['Amt'])return ["Failed"=>["Message"=>"INSURFICIENT FUND (Minimum Balance is NGR 1000)"]]; 
     if(!$FromPrompt){
         $tp = !$IsData?"Recharge":"Subscribe";
        // $dp = !$IsData?0:(int)$param['DataPlan'];
     return ["Prompt"=>["Amt"=>$param['Amt'],"Descr"=>$selnetwork." - ₦".$param['Amt'],"PhoneNo"=>$param['PhoneNo'],"Type"=>$tp,"DataPlan"=>$dataplan]];
     }else{
        $param['Descr'] = !isset($param['Descr']) || trim($param['Descr']) == ""?$param['Amt']:$param['Descr'];
         $trandescr = !$IsData?("Airtime Topup: ".$param['Descr']." to ".$param['PhoneNo']):("Data Plan: ".$param['Descr']." to ".$param['PhoneNo']);
         //process the transaction
         $dsender = UpdateAccBal($MID,(float)$param['Amt'],$trandescr,false);
         if($dsender === true){
            return ["Success"=>["Message"=>$trandescr]];
         }
     }
return ["Failed"=>["Message"=>"Airtime Topup Failed"]];
}

?>