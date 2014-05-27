<?php

//print_r($_SESSION);
if(isset($_SESSION["userid"])):
	db_connect();
	show_connection_details();
	if($_POST["submit_filter"]):
		process_submission();
	endif;
else:
	echo "<font color='red'>Unauthorized access to this page. Please log in.</font>";
	echo "<br><a href='$_SERVER[PHP_SELF]'>Try Again</a>";
endif;
 

function db_connect(){
	$db_conn = mysql_connect('localhost',$_SESSION["dbuser"],$_SESSION["dbpass"]) or die("Cannot query 14: ".mysql_error());
	mysql_select_db($_SESSION["dbname"],$db_conn) or die("Cannot query 15: ".mysql_error());
}


function show_connection_details(){
	$q_user = mysql_query("SELECT user_lastname, user_firstname, user_id FROM game_user ORDER by user_lastname ASC, user_firstname ASC");
	$q_brgy = mysql_query("SELECT barangay_id, barangay_name FROM m_lib_barangay") or die("Cannot query 21: ".mysql_error());

	echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
	echo "<table border='1' width='50%'>";
	echo "<tr><td>Current active database: </td><td>".$_SESSION["dbname"]."</td></tr>";
	echo "<tr><td>Select End User Account to Sync</td>";
	echo "<td><select name='sel_user'>";	
	echo "<option value='all'>All User Accounts</option>";
	while(list($lname,$fname,$uid)=mysql_fetch_array($q_user)){
		echo "<option value='$uid'>$lname, $fname</option>";
	}
	echo "</select></td></tr>";

	echo "<tr><td>Select Barangay/s to Sync</td>";
	echo "<td><select name='sel_barangay[]' size='10' multiple='multiple'>";
	echo "<option value='all'>All Barangays</option>";
	while(list($brgy_id,$brgy_name)=mysql_fetch_array($q_brgy)){
		echo "<option value='$brgy_id'>$brgy_name</option>";
	}
	echo "</select></td></tr>";

	echo "<tr><td colspan='2'>Note: Only family folders, patients and consultations from the selected barangays will be included in the sync file.</td>";
	echo "<tr><td colspan='2'><input type='submit' name='submit_filter' value='Create Sync File'></td></tr>";	
	echo "</table>";
	echo "</form>";
}

function process_submission(){
	$_SESSION["tmp_directory"] = '../../sql/';
	$_SESSION["file_name"] = 'record_push_'.$_POST["sel_user"].'_'.date('Y-m-d').'.sql';

	print_r($_POST);
	create_tmp_sql_file();

	extract_users($_POST["sel_user"]);		
	extract_patient_folder_consults();
}


function create_tmp_sql_file(){
	if($handle = fopen($_SESSION["tmp_directory"].$_SESSION["file_name"],'w') or die("Cannot write file 61")):
		chmod($_SESSION["tmp_directory"].$_SESSION["file_name"],0766);
	endif;

}

function extract_users($user_id){ 
	if($user_id=='all'):	
		$q_user = mysql_query("SELECT * FROM game_user") or die("Cannot query 59: ".mysql_error());
	else:	//specific user only
		$q_user = mysql_query("SELECT * FROM game_user WHERE user_id='$user_id'") or die("Cannot query 61: ".mysql_error());
	endif;

	if(mysql_num_rows($q_user)!=0): 
			$insert_user = '';
			$handle = fopen($_SESSION["tmp_directory"].'/'.$_SESSION["file_name"],'a') or die("Cannot open file 78");
		while($r_user=mysql_fetch_array($q_user)){ //print_r($r_user);
			$insert_user = "INSERT INTO game_user (user_id,user_lastname,user_firstname,user_middle,user_dob,user_gender,user_role,user_admin,user_login,user_password,user_lang,user_email,user_cellular,user_pin,user_active,user_receive_sms) VALUES ('$r_user[user_id]','$r_user[user_lastname]','$r_user[user_firstname]','$r_user[user_middle]','$r_user[user_dob]','$r_user[user_gender]','$r_user[user_role]','$r_user[user_admin]','$r_user[user_login]','$r_user[user_password]','$r_user[user_lang]','$r_user[user_email]','$r_user[user_cellular]','$r_user[user_pin]','$r_user[user_active]','$r_user[user_receive_sms]')".';';
			
			fwrite($handle,$insert_user."\n"); 
		}

			fclose($handle);
	else:

	endif;
}

function extract_patient_folder_consults(){
	$tables_for_export = array('consult','family','patient','dental'); //list the tables of which selection will be based on the barangays. For other tables, Export ALL records
	
	$arr_table = array(); //this shall contain all the tables that passes through the filter

	$get_tables = mysql_query("SHOW TABLES FROM ".$_SESSION["dbname"]) or die("Cannot query 93: ".mysql_error());

	while(list($table)=mysql_fetch_array($get_tables)){
		$str_array = explode('_',$table);
		if(in_array($str_array[1],$tables_for_export)):
			array_push($arr_table,$table);
		endif;
	}

	$_SESSION["arr_table"] = $arr_table;
	
	if(isset($_POST["sel_barangay"])):
		get_family_folders();	//return the patient_ids
	else:
		echo "Please select barangay/s.";
	endif;
}

function get_family_folders(){
	$patient_arr = array();	//stores the patient_id's
	$family_arr = array();

	if(in_array('all',$_POST["sel_barangay"])): 
		$q_family_address = mysql_query("SELECT * FROM m_family_address");
	else: 
		$str_brgy = "'".implode("','",$_POST["sel_barangay"])."'";

		$q_family_address = mysql_query("SELECT * FROM m_family_address WHERE barangay_id IN ($str_brgy)") or die("Cannot query 119: ".mysql_error());
	endif;

	//insert the m_family_address into the text file
	if(mysql_num_rows($q_family_address)!=0):
	
		$handle = fopen($_SESSION["tmp_directory"].'/'.$_SESSION["file_name"],'a') or die("Cannot open file 124");

		while($r_family=mysql_fetch_array($q_family_address)){
			$insert_family_address = "INSERT INTO m_family_address (family_id,address_year,address,barangay_id) VALUES ('$r_family[family_id]','$r_family[address_year]','$r_family[address]','$r_family[barangay_id]');";
			array_push($family_arr,$r_family["family_id"]);
			fwrite($handle,$insert_family_address."\n"); 
		}

		fclose($handle);

		foreach($family_arr as $key=>$family_id){
			insert_family($family_id);
			insert_family_cct($family_id);
			$patient_arr = insert_family_members($family_id);	//get the patient_id's
			print_r($patient_arr);
		}
	endif;
}


function insert_family($family_id){
	$q_family = mysql_query("SELECT * FROM m_family WHERE family_id='$family_id'") or die("Cannot query 145: ".mysql_error());

	if(mysql_num_rows($q_family)!=0):
		$handle = fopen($_SESSION["tmp_directory"].'/'.$_SESSION["file_name"],'a') or die("Cannot open file 148");

		while($r_family=mysql_fetch_array($q_family)){
			$insert_family = "INSERT INTO family (family_id,head_patient_id) VALUES ('$r_family[family_id]','$r_family[head_patient_id]');";

			fwrite($handle,$insert_family."\n"); 
		}

		fclose($handle);
	endif;

}

function insert_family_cct($family_id){
	$q_family_cct = mysql_query("SELECT * FROM m_family_cct_member WHERE family_id='$family_id'") or die("Cannot query 145: ".mysql_error());

	if(mysql_num_rows($q_family_cct)!=0){
		$handle = fopen($_SESSION["tmp_directory"].'/'.$_SESSION["file_name"],'a') or die("Cannot open file 165");

		while($r_cct = mysql_fetch_array($q_family_cct)){
			$insert_cct = "INSERT INTO m_family_cct_members (cct_id,family_id,date_enroll,last_updated) VALUES ('$r_cct[cct_id]','$r_cct[family_id]','$r_cct[date_enroll]','$r_cct[last_updated]');";
			fwrite($handle,$insert_cct."\n");
		}
	
		fclose($handle);
	}
}

function insert_family_members($family_id){
	$patient_arr = array(); 

	$q_family_members = mysql_query("SELECT * FROM m_family_members WHERE family_id='$family_id'") or die("Cannot query 145: ".mysql_error());


	if(mysql_num_rows($q_family_members)!=0):
		$handle = fopen($_SESSION["tmp_directory"].'/'.$_SESSION["file_name"],'a') or die("Cannot open file 186");

		while($r_members = mysql_fetch_array($q_family_members)){
			$insert_members = "INSERT INTO m_family_members (family_id,family_role,patient_id) VALUES ('$r_members[family_id]','$r_members[family_role]','$r_members[family_role]','$r_members[patient_id]');";
			array_push($patient_arr,$r_members["patient_id"]);		
			fwrite($handle,$insert_members."\n");
		}

		fclose($handle);
	else:

	endif;

	return $patient_arr;
}

?>
