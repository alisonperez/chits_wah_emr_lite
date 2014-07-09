<?php
	$dir_dbselect = '../../modules/_dbselect.php';
	$dir_globals = '../../chits_query/globals.php';
	$dir_config = '../../config.xml';
	$destination = getcwd();	// site/lite
	
	if(isset($_SESSION["userid"])):
		copy_files($dir_dbselect,$dir_globals,$dir_config,$destination);
	else:
		echo "<font color='red'>Unauthorized access to this page. Please log in.</font>";
		echo "<br><a href='$_SERVER[PHP_SELF]'>Try Again</a>";
	endif;

	function copy_files($dir_dbselect,$dir_globals,$dir_config,$destination){ 
	//transfer first the configuration files to temporary storage before editing

		if(file_exists($dir_dbselect)):
			copy($dir_dbselect,$destination) or die("Cannot copy _dbselect.php");
		endif;

		if(file_exists($dir_globals)):
			copy($dir_globals,$destination) or die("Cannot copy globals.php");
		endif;

		if(file_exists($dir_config)):
			copy($dir_config,$destination) or die("Cannot copy config.xml");
		endif;
		
		echo 'File copy is a success!';
	}

?>