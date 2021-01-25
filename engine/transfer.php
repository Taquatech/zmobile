<?php
session_start();
include "utility.php";

function VerifyTransfer($param=[]){
    global $_;
    if((isset($param['MID']) && (int)$param['MID'] < 1) || (isset($_SESSION['MID']) && (int)$_SESSION['MID'] < 1) || (!isset($param['MID']) && !isset($_SESSION['MID'])))return ["Failed"=>["Message"=>"Ooops !!!, Invalid customer identification parameter sent","Title"=>"Customer Identification Failed"]];
    $MID = isset($param['MID'])?$param['MID']:$_SESSION['MID'];
    //get the senders details
    $Sender = $_->SFR("member_tb","ID,Surname, Firstname, Middlename, AccNum, FORMAT(AccBal,2) as AccBal, AccBal as PAccBal","ID=$MID");
    if(!is_array($Sender))return ["Failed"=>["Message"=>"Ooops !!!, We encounter error reading your Account Details.","Title"=>"Customer Identification Failed"]];

    if(!isset($param['Amt']) || (float)$param['Amt'] < 1) return ["Failed"=>["Message"=>"INVALID AMOUNT ENTERED"]];

    if(((float)$Sender['PAccBal'] - 1000) < (float)$param['Amt'])return ["Failed"=>["Message"=>"INSURFICIENT FUND (Minimum Balance is NGR 1000)"]];

    if(!isset($param['AccNo']) || trim($param['AccNo'])=="") return ["Failed"=>["Message"=>"INVALID ACCOUNT NUMBER"]];
    //check if valid in db
    $recaccdet = $_->SFR("member_tb","ID,Surname, Firstname, Middlename, AccNum","AccNum='{$param['AccNo']}'");
    if(!is_array($recaccdet)) return ["Failed"=>["Message"=>"ACCOUNT VALIDATION FAILED"]];

    return ["Sender"=>$Sender,"Receiver"=>$recaccdet,"FAmt"=>number_format((float)$param['Amt'],2),"Amt"=>(float)$param['Amt']];

    

    //return ["Failed"=>["Message"=>json_encode($param)]];
}


function PerformTransfer($param=[]){
global $_;
    // {"SID":"8","Amt":"5400","RID":"1","PassW":"fggffg"
        if(!isset($param['SID']) || (int)$param['SID'] < 1 || !isset($param['RID']) || (int)$param['RID'] < 1)return ["Failed"=>["Message"=>"INVALID PARAMETER"]];
        if(!isset($param['PassW']) || trim($param['PassW']) == "")return ["Failed"=>["Message"=>"INVALID PASSWORD"]];

        $HashPassw = md5($param['PassW']);

        //check if valid customer
        $Sender = $_->SFR("member_tb","ID,Surname, Firstname, Middlename, AccNum, FORMAT(AccBal,2) as AccBal,Passw","ID=".$param['SID']);
        

        if(!is_array($Sender))return ["Failed"=>["Message"=>"Ooops !!!, We encounter error reading your Account Details.","Title"=>"Customer Identification Failed"]];

        if($Sender['Passw'] != $HashPassw)return ["Failed"=>["Message"=>"Ooops !!!, Wrong Password Supplied"]];

        if(!isset($param['Amt']) || (float)$param['Amt'] < 1) return ["Failed"=>["Message"=>"INVALID AMOUNT ENTERED"]];

        $Receiver = $_->SFR("member_tb","ID,Surname, Firstname, Middlename, AccNum, FORMAT(AccBal,2) as AccBal,Passw","ID=".$param['RID']);
        if(!is_array($Receiver))return ["Failed"=>["Message"=>"Ooops !!!, We encounter error reading Destination Account Details."]];

        //debit sender
        $dsender = UpdateAccBal($param['SID'],(float)$param['Amt'],"NIP Transfer to ({$Receiver['AccNum']}) ".strtoupper($Receiver['Surname']." ".$Receiver['Firstname']." ".$Receiver['Middlename']),false);
        if( $dsender === true){
            $Sender = $_->SFR("member_tb","ID,Surname, Firstname, Middlename, AccNum, FORMAT(AccBal,2) as AccBal,Passw","ID=".$param['SID']);
            $creciever = UpdateAccBal($param['RID'],(float)$param['Amt'],"NIP Transfer from ({$Sender['AccNum']}) ".strtoupper($Sender['Surname']." ".$Sender['Firstname']." ".$Sender['Middlename']),true);
            if($creciever === true){
                return ["Sender"=>$Sender,"Receiver"=>$Receiver,"FAmt"=>number_format((float)$param['Amt'],2)];
            }else{
                return ["Failed"=>["Message"=>"Ooops !!!, Destination Account Update Failed"]];
            }
        }



        
        
    return ["Failed"=>["Message"=>"Transfer Failed"]];
    }

?>