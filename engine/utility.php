<?php
//update account
function UpdateAccBal($CID,$Amt,$Desrc="",$Cr=true){
    global $_;
    $op = $Cr?"+":"-";
     //add the transaction
     $in = $_->InsertID2("transaction_tb",["CID"=>$CID,"Amt"=>$Amt,"Descr"=>$Desrc,"Credit"=>$Cr?1:0]);
    
    if(is_numeric($in)){
     //update the balance
    $up = $_->Query("UPDATE member_tb SET AccBal = AccBal $op $Amt WHERE ID=".$CID." OR Phone='$CID'");
      if(is_array($up)){
          return true;
      }else{
          $del = $_->Delete("transaction_tb","ID=".$in);
      }
    }
    return $in;
    
    }


?>