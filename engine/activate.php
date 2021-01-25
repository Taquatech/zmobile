<?php

include "utility.php";
//Activate account
function ActivateAccount($param=[]){
    global $_;
    if(!isset($param['UPhone']) || trim($param['UPhone']) == "")return ["Failed"=>["Message"=>"Invalid Account Phone Number Supplied"]];
    if(!isset($param['openbal']) || (float)$param['openbal'] < 1000)return ["Failed"=>["Message"=>"Invalid Opening Balance Supplied, minimum is NGR 1000.00"]];
    //get the customers details
    $CustDet = $_->SFR("member_tb","","Phone='".$_->SS($param['UPhone'])."'");
    if(!is_array($CustDet))return ["Failed"=>["Message"=>"Non of our customer is using the supplied Phone Number, Check and Try Again"]];
   
    if(is_null($CustDet['AccNum']) || trim($CustDet['AccNum']) == ""){ //generate
        do{
          $CustDet['AccNum'] = mt_rand(1000000000,9999999999)."";  
        }while(is_array($_->SFR("member_tb","AccNum","AccNum='{$CustDet['AccNum']}'")));
        
    }

    $opbal = (float)$param['openbal'];
    
    //make the customer active
    $ma = $_->Update("member_tb",["Status"=>2,"AccNum"=>$CustDet['AccNum']],"ID=".$CustDet['ID']);
    if(!is_array($ma))return ["Failed"=>["Message"=>"Activating Account Failed"]];
  $upbal = UpdateAccBal($CustDet['ID'],$opbal,"Opening Balance brought forward");
    if($upbal === true){
      return ["Success"=>["Message"=>"Account Activated and Opening Balance Updated"]];
    }
    return ["Success"=>["Message"=>"Account Activated but Opening Balance Update Failed - ".$upbal]];
    
}




?>