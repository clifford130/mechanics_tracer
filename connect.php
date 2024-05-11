<?php
$HOSTMAME='localhost';
$USERNAME='root';
$PASSWORD='';
$DATABASE='mechanicstracer';
$con="";
// try{
$con=mysqli_connect($HOSTMAME,$USERNAME,$PASSWORD,$DATABASE );
// }
// catch(mysqli_sql_exception){

    if(!$con){
        die(mysqli_error($con));

}
// if($con){
//     echo"connection successfull";
// }
// else{
//     die(mysqli_error($con));
// }

?>