<?php
/*
Malware Scan - 31/01/2016 - Designed by tecnoes.com
Contact: hello@tecnoes.com
You can redistribute this file freely by mentioning tecnoes.com

IMPORTANT:
For security reasons, be sure to change the default username and password by editing the first two lines
Of downloaded scan.php file. Once you finish using Malware Scan, delete it from your server.

This tool is intended for users of medium / high knowledge. It is very common that false positives appear
Or suspicious files that are vital to the operation of a website. The use of the tool is
Responsibility of the user. We insist on the importance of making a complete backup before
Using this tool.
*/




/*Change username and password to access this script here*/
$username = "test";
$password = "test";
$nonsense = "onceuponatimeunamaripositavolaba";

/*Debug*/
//ini_set('error_reporting', E_ALL);//ini_set("display_errors", 1); //error_reporting(E_ALL);
$time1 = microtime(true);

/***************/
/*Search MySQL*/
/*************/
	
	//Check database connection function
	function checkDB($db,$dbuser,$dbpass,$dbhost){
		$link = @mysql_connect($dbhost,$dbuser,$dbpass);
			if($link == false){
				return "Could not connect :-(";
			} else {					
					$db_selected = mysql_select_db($db, $link);
					if (!$db_selected) {
						return "Wrong database name '$db' - ".mysql_error()." :-(";
					}
					else {
					setcookie('db', $db);
					setcookie('dbuser', $dbuser);
					setcookie('dbpass', $dbpass);
					setcookie('dbhost', $dbhost);						
					return "Database connected & saved :-)";
					}
				}
	}		
	function getDatabases(){
		$return=array();
		$db_list = mysql_list_dbs($conn);		
		while ($row = mysql_fetch_object($db_list)) {
			if(!in_array($row->Database,$protectedDatabases)){
				$return[]=$row->Database;
			}
		}
		return $return;
	}	
	function getTables($databaseArray){
		$return=array();
		$count=count($databaseArray);
		$table=array();
		for($i=0;$i<$count;$i++){
			$database=$databaseArray[$i];
			$sql = "SHOW TABLES FROM $database";
			$result = mysql_query($sql);		
			$table=array();
			while ($row = @mysql_fetch_row($result)) {              
			   $table[]=$row[0];
			}
			$return[$database]=$table;
		}
		return $return;
	}
	function getFields($tableArray){
		$return=array();
		foreach($tableArray as $database=>$table) {
			$count=count($table);
			$tmpTables=array();
			for($i=0;$i<$count;$i++){				
				mysql_select_db($database);
				$tableName=$table[$i];	
				$sql="SHOW COLUMNS FROM `$tableName`";
				$result = mysql_query($sql) or die("$database-> $sql -- ".mysql_error());								
				$fieldArray=array();
				while ($row = mysql_fetch_assoc($result)) {					
					$type=$row['Type'];
					$pos=strpos($type,"(");
					if($pos!==false){
						$type=substr($type,0,$pos);
					}
					$fieldArray[]=array($row['Field'],$type);					
				}	
				$tmpTables[$tableName]=$fieldArray;				
			}
			$return[$database]=$tmpTables;	
		}
		return $return;
	}
	/*DETECT DATABASE FUNCTIONS*/				
	function finFileRec($dir, $pattern, &$results = array(), &$resultadoOK){
			$files = scandir($dir);
			//$resultadoOK="";
			foreach($files as $key => $value){								
				$path = realpath($dir.DIRECTORY_SEPARATOR.$value);								
				if (strpos($value,$pattern)!== false) { /*ECHO $path;*/ return $resultadoOK=$path;}							
				if(!is_dir($path)) {
					$results[] = $path;
				} else if(is_dir($path) && $value != "." && $value != "..") {
					finFileRec($path, $pattern, $results, $resultadoOK);
					$results[] = $path;
				}												
			}
					return $resultadoOK;							
		}
													
	/*Find Database config for WP, Joomla & Prestashop in any subfolder*/
	function detectDatabase(){		
			/*WP*/
			$find = 'wp-config.php'; //The file to find
			$foundWP = "";								
			$encuentra=@finFileRec(".",$find);				
			$rutawp=$encuentra;					
			if (!empty($rutawp)) $foundWP=$encuentra;
				if (!empty($foundWP)) {
					//echo $foundWP; echo "<br>";
					ob_start(); 
					include $foundWP;
					ob_end_clean();
					$GLOBALS['cms']=1;
					$GLOBALS['db']=DB_NAME;
					$GLOBALS['dbuser']=DB_USER;
					$GLOBALS['dbpass']=DB_PASSWORD;
					$GLOBALS['dbhost']=DB_HOST;	
					return $foundWP;
					}
				else{
					/*JOOMLA*/
					$find = 'configuration.php'; //The file to find
					$foundJoomla = "";
					$encuentra=@finFileRec(".",$find);
					$rutajo=$encuentra;
					if (!empty($rutajo)) $foundJoomla=$encuentra;
					if (!empty($foundJoomla)) { 
						//echo $foundJoomla;  echo "<br>";
						ob_start(); 
						include $foundJoomla;
						$JConfig = new JConfig;
						ob_end_clean();
						$GLOBALS['cms']=2;
						$GLOBALS['db']=$JConfig->db;
						$GLOBALS['dbuser']=$JConfig->user;
						$GLOBALS['dbpass']=$JConfig->password;
						$GLOBALS['dbhost']=$JConfig->host;
						return $foundJoomla;
						}
						else {
							/*PRESTASHOP*/					
							$find = 'settings.inc.php'; //The file to find
							$foundPresta = false;
							$encuentra=@finFileRec(".",$find);
							$rutapre=$encuentra;
							if (!empty($rutapre)) $foundPresta=$encuentra;
							if (!empty($foundPresta)) { 
								//echo $foundPresta;  echo "<br>";
								ob_start(); 
								include $foundPresta;
								ob_end_clean();	
								$GLOBALS['cms']=3;
								$GLOBALS['db']=_DB_NAME_;
								$GLOBALS['dbuser']=_DB_USER_;
								$GLOBALS['dbpass']=_DB_PASSWD_;
								$GLOBALS['dbhost']=_DB_SERVER_;	
								return $foundPresta;
								}	
								else $GLOBALS['cms']=4;							
								}
						}						
					}
	function printSearch($resultArray){		
		$printresult="<div>";			
		foreach($resultArray as $database=>$tableArray){			
			foreach($tableArray as $table=>$fieldArray){				
				foreach($fieldArray as $fields){
					$field=$fields['field'];
					$querySQL=$fields['select'];
					$printresult.="<tr>";
					$printresult.="<td>$table</td>";					
					$printresult.="<td>$field</td>";
					$printresult.="<td><input type='text' style='width:100%;' value=\"$querySQL\"></td>";
					$printresult.="</tr>";
				}				
			}
		}
		return $printresult;
	}	
	function getResults($keyword,$tableList){		
		$return=array();
		foreach($tableList as $database=>$tableArray){
			mysql_select_db($database);
			foreach($tableArray as $tableName=>$fieldArray){				
				$count=count($fieldArray);
				$tmp=array();
				foreach($fieldArray as $fields){
					$field=$fields[0];
					$keyword=strtolower($keyword);
						$sql="SELECT * FROM `$tableName` WHERE lower(`$field`) like '%$keyword%' OR lower(`$field`) like '$keyword%' OR lower(`$field`) like '%$keyword'";
						$res=mysql_query($sql) or die("$sql -- ".mysql_error());
						$num=mysql_num_rows($res);
						if($num>0){
							$tmp[]=array('field'=>$field,"select"=>$sql);
						}				
				}
				if(count($tmp)>0){
					$return[$database][$tableName]=$tmp;
				}
			}
		}
		return $return;
	}
	
	


if (isset($_COOKIE['AdminLogin'])) {
   if ($_COOKIE['AdminLogin'] == md5($password.$nonsense)) {   
		if (isset($_GET['ajax'])){
				$ajaxvalue=$_GET['ajax'];
				$db=$_GET['db'];
				$dbuser=$_GET['dbuser'];
				$dbpass=$_GET['dbpass'];
				$dbhost=$_GET['dbhost'];										
				echo checkDB($db,$dbuser,$dbpass,$dbhost);	
				exit;		
		}
		
/*MENU & SPAM QUERIES*/
$domain=$_SERVER['SERVER_NAME'];		
$spamquery1="https://www.google.com/?gws_rd=ssl#q=site:$domain + (poker|blackjack|bingo|roulette|casino|gambling|fetish|fuck|pussy|porn|anal|sexcam|webcam|cam|lesbian|diet|pills|fat|cheap|payday|loan|credit|essay|binary)";
$spamquery2="https://www.google.com/?gws_rd=ssl#q=site:$domain + (longchamp|prada|hermes|mulberry|moncler|coach|vuitton|hollister|louboutin|northface|jersey|vibram|fitflops|ugg|timberland)";
$spamquery3="https://www.google.com/?gws_rd=ssl#q=site:$domain + (shoes|adidas|nike|lebron|kobe|zoom|jordan|oakley|rayban|sunglasses|rolex|watch|replica|dre|beats|oem|wallpaper|ringtones|software|download)";
$spamquery4="https://www.google.com/?gws_rd=ssl#q=site:$domain + (poker|blackjack|bingo|roulette|casino|gambling|fetish|fuck|pussy|porn|anal|sexcam|webcam|cam|lesbian|loss|diet|pills|fat|cheap|payday|loan|credit|essay|binary)";

$menu="<div class='navigation'><ul class='nav'><li><a href='".basename($_SERVER['PHP_SELF'])."'><i class='fa fa-home'></i> Home</a></li><li><a href='#'><i class='fa fa-file-code-o'></i> File Scan</a><ul><li><a href='?action=3'><i class='fa fa-bug'></i> Normal Scan</a></li><li><a href='?action=4'><i class='fa fa-bug'></i> Advanced Scan</a></li><li><a href='?action=2'><i class='fa fa-bug'></i> Normal & Advanced</a></li></ul></li><li><a href='#'><i class='fa fa-database'></i> Database Scan</a><ul><li><a href='?db=1'><i class='fa fa-cog'></i> Database Settings</a></li><li><a href='?db=2&keyword=%3Cscript'><i class='fa fa-bug'></i> Javascript Scan </a></li></ul></li><li><a href='#'><i class='fa fa-external-link'></i> External Tools</a><ul><li><a href='https://sitecheck.sucuri.net/results/".$domain."' target='_blank'><i class='fa fa-external-link-square'></i> Sucuri</a></li><li><a href='http://www.google.com/safebrowsing/diagnostic?site=".$domain."' target='_blank'><i class='fa fa-external-link-square'></i> Google Safebrowsing</a></li><li><a href=\"$spamquery1\" target='_blank'><i class='fa fa-google'></i> Spam Query 1</a></li><li><a href=\"$spamquery2\" target='_blank'><i class='fa fa-google'></i> Spam Query 2</a></li><li><a href=\"$spamquery3\" target='_blank'><i class='fa fa-google'></i> Spam Query 3</a></li><li><a href=\"$spamquery4\" target='_blank'><i class='fa fa-google'></i> Spam Query 4</a></li></ul></li><li><a href='?action=6'><i class='fa fa-question-circle'></i> Help</a></li></ul></div>";
?>
<html>
<head>
		<title>Malware Scan</title>
		<style type="text/css"> 		
			body {background-color: #DEDEDE; margin: 0px; margin-left: 5px; margin-right: 5px;} 		
			.form {
				text-align:center;
				margin: 0 auto;
				margin-top:10px;
			}
			.contienetop{
				text-align:center;
				width:100%;			
			}
			.resaltada { 
				background-color:black;
				color:white;
				text-decoration:underline;
			} 
			#view-source {
				display: none;
			}
			body.view-source > * {
			  display: none;
			}			
			.title {font-size: 1.5em; color:black;}
			.title a {color: black;}
			.fa-stack {font-size: 1em!important;}
			body.view-source #view-source {
			  display: block !important;
			}	
			.undermenu{
				float:left;
				margin:20px;
				width:50%
				text-align:center;
			}
			.num { 
				float: left; 
				color: gray; 
				font-size: 13px;    
				font-family: monospace; 
				text-align: right; 
				margin-right: 6pt; 
				padding-right: 6pt; 
				border-right: 1px solid gray;
			}
			.urlinput{
				width: 50%;
			}			
			td {vertical-align: top;} 
			code {white-space: nowrap;} 			
			.red { color:#F34B4B;}
			.green { color:green;}
			.blue { color:blue;}			
			.indice {padding:6px; position: fixed; top: 0; width: 100%; z-index: 100; background-color:#F7F7F7; border:solid 1px grey; color:black;}			
			.indice a {text-decoration:none; color:black;}			
			.filestable span {
				display:none;
				color:yellow;
			}
			
			.filestable a:link {
				color: #666;
				font-weight: bold;
				text-decoration:none;
			}
			.filestable a:visited {
				color: #999999;
				font-weight:bold;
				text-decoration:none;
			}
			.filestable a:active,
			.filestable a:hover {
				color: #bd5a35;
				text-decoration:underline;
			}
			.filestable {
				width:95%;
				font-family:Arial, Helvetica, sans-serif;
				color:#666;
				font-size:12px;
				text-shadow: 1px 1px 0px #fff;
				background:#eaebec;
				margin:20px;
				border:#ccc 1px solid;
				-moz-border-radius:3px;
				-webkit-border-radius:3px;
				border-radius:3px;
				-moz-box-shadow: 0 1px 2px #AFAEAE;
				-webkit-box-shadow: 0 1px 2px #AFAEAE;
				box-shadow: 0 1px 2px #AFAEAE;
			}
			.filestable tr:first-child th:first-child {
				-moz-border-radius-topleft:3px;
				-webkit-border-top-left-radius:3px;
				border-top-left-radius:3px;
			}
			.filestable tr:first-child th:last-child {
				-moz-border-radius-topright:3px;
				-webkit-border-top-right-radius:3px;
				border-top-right-radius:3px;
			}
			.filestable tr {
				text-align: center;
				padding-left:20px;
			}
			.filestable td:first-child {
				text-align: left;
				padding-left:20px;
				border-left: 0;
			}
			.filestable td {				
				border-top: 1px solid #ffffff;
				border-bottom:1px solid #E2DDDD;
				border-left: 1px solid #E2DDDD;
				background: #fafafa;
				background: -webkit-gradient(linear, left top, left bottom, from(#fbfbfb), to(#fafafa));
				background: -moz-linear-gradient(top,  #fbfbfb,  #fafafa);
			}
			.filestable tr.even td {
				background: #f6f6f6;
				background: -webkit-gradient(linear, left top, left bottom, from(#f8f8f8), to(#f6f6f6));
				background: -moz-linear-gradient(top,  #f8f8f8,  #f6f6f6);
			}
			.filestable tr:last-child td {
				border-bottom:0;
			}
			.filestable tr:last-child td:first-child {
				-moz-border-radius-bottomleft:3px;
				-webkit-border-bottom-left-radius:3px;
				border-bottom-left-radius:3px;
			}
			.filestable tr:last-child td:last-child {
				-moz-border-radius-bottomright:3px;
				-webkit-border-bottom-right-radius:3px;
				border-bottom-right-radius:3px;
			}
			.filestable tr:hover td {
				background: #f2f2f2;
				background: -webkit-gradient(linear, left top, left bottom, from(#f2f2f2), to(#f0f0f0));
				background: -moz-linear-gradient(top,  #f2f2f2,  #f0f0f0);	
			}			
			.nav {
				margin: 0px;
				padding: 0px;
				list-style: none;
			}
			.nav li {
				float: left;
				width: 20%;
				position: relative;
				text-align: center;		
			}
			.nav li a {
				color: #FFFFFF;
				display: block;
				padding: 7px 8px;
				text-decoration: none;
				
				background: #616161;
				background: -webkit-gradient(linear, left top, left bottom, from(#616161), to(#343435));
				background: -moz-linear-gradient(top,  #616161,  #343435);
			}
			.nav li a:hover {
				color: #C0CDFF;
			}
			/*=== submenu ===*/
			.nav ul {
				display: none;
				position: absolute;
				margin-left: 0px;
				list-style: none;
				padding: 0px;
				z-index: 999;
				width: 100%;				
			}
			.nav ul li {
				float: left;
				width: 100%;
			}
			.nav ul a {
				display: block;
				height: 15px;
				padding: 7px 8px;
				color: #FFFFFF;
				text-decoration: none;
				background: -webkit-gradient(linear, left top, left bottom, from(#616161), to(#343435));
				background: -moz-linear-gradient(top,  #616161,  #343435);
			}
			.nav ul li a:hover {
				color: #C0CDFF;
			}
			.homelogo{
				clear:both;
				height:600px;
				padding-top: 150px;
				font-size:4em;
				width:100%;
				text-align:center;
			}
			
			input {
				margin: 6px;
				background-color: #F7F4F4;
			}
			.botondb {
				background-color: #9E9C9C;
				color:white;
				padding:10px;
			}
			
			.helpcontainer{
				padding:30px;
			}
			
			
			/*LOADING*/
			.loader {
			  font-size: 90px;
			  text-indent: -9999em;
			  overflow: hidden;
			  width: 1em;
			  height: 1em;
			  border-radius: 50%;
			  margin: 72px auto;
			  position: relative;
			  -webkit-transform: translateZ(0);
			  -ms-transform: translateZ(0);
			  transform: translateZ(0);
			  -webkit-animation: load6 1.2s infinite ease;
			  animation: load6 1.2s infinite ease;
			}
			@-webkit-keyframes load6 {
			  0% {
				-webkit-transform: rotate(0deg);
				transform: rotate(0deg);
				box-shadow: 0 -0.83em 0 -0.4em #616161, 0 -0.83em 0 -0.42em #616161, 0 -0.83em 0 -0.44em #616161, 0 -0.83em 0 -0.46em #616161, 0 -0.83em 0 -0.477em #616161;
			  }
			  5%,
			  95% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, 0 -0.83em 0 -0.42em #616161, 0 -0.83em 0 -0.44em #616161, 0 -0.83em 0 -0.46em #616161, 0 -0.83em 0 -0.477em #616161;
			  }
			  10%,
			  59% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, -0.087em -0.825em 0 -0.42em #616161, -0.173em -0.812em 0 -0.44em #616161, -0.256em -0.789em 0 -0.46em #616161, -0.297em -0.775em 0 -0.477em #616161;
			  }
			  20% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, -0.338em -0.758em 0 -0.42em #616161, -0.555em -0.617em 0 -0.44em #616161, -0.671em -0.488em 0 -0.46em #616161, -0.749em -0.34em 0 -0.477em #616161;
			  }
			  38% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, -0.377em -0.74em 0 -0.42em #616161, -0.645em -0.522em 0 -0.44em #616161, -0.775em -0.297em 0 -0.46em #616161, -0.82em -0.09em 0 -0.477em #616161;
			  }
			  100% {
				-webkit-transform: rotate(360deg);
				transform: rotate(360deg);
				box-shadow: 0 -0.83em 0 -0.4em #616161, 0 -0.83em 0 -0.42em #616161, 0 -0.83em 0 -0.44em #616161, 0 -0.83em 0 -0.46em #616161, 0 -0.83em 0 -0.477em #616161;
			  }
			}
			@keyframes load6 {
			  0% {
				-webkit-transform: rotate(0deg);
				transform: rotate(0deg);
				box-shadow: 0 -0.83em 0 -0.4em #616161, 0 -0.83em 0 -0.42em #616161, 0 -0.83em 0 -0.44em #616161, 0 -0.83em 0 -0.46em #616161, 0 -0.83em 0 -0.477em #616161;
			  }
			  5%,
			  95% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, 0 -0.83em 0 -0.42em #616161, 0 -0.83em 0 -0.44em #616161, 0 -0.83em 0 -0.46em #616161, 0 -0.83em 0 -0.477em #616161;
			  }
			  10%,
			  59% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, -0.087em -0.825em 0 -0.42em #616161, -0.173em -0.812em 0 -0.44em #616161, -0.256em -0.789em 0 -0.46em #616161, -0.297em -0.775em 0 -0.477em #616161;
			  }
			  20% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, -0.338em -0.758em 0 -0.42em #616161, -0.555em -0.617em 0 -0.44em #616161, -0.671em -0.488em 0 -0.46em #616161, -0.749em -0.34em 0 -0.477em #616161;
			  }
			  38% {
				box-shadow: 0 -0.83em 0 -0.4em #616161, -0.377em -0.74em 0 -0.42em #616161, -0.645em -0.522em 0 -0.44em #616161, -0.775em -0.297em 0 -0.46em #616161, -0.82em -0.09em 0 -0.477em #616161;
			  }
			  100% {
				-webkit-transform: rotate(360deg);
				transform: rotate(360deg);
				box-shadow: 0 -0.83em 0 -0.4em #616161, 0 -0.83em 0 -0.42em #616161, 0 -0.83em 0 -0.44em #616161, 0 -0.83em 0 -0.46em #616161, 0 -0.83em 0 -0.477em #616161;
			  }
			}			
			.helpcontainer h3, .helpcontainer h4{
				display:inline;
			}
			#av_toolbar_iframe, #av_toolbar_regdiv, .av_site{
				display:none;
			}
			body {
				margin-top: 5px!important;
			}
	
		</style>
		
		<!-- DataTables CSS -->
		<link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.9/css/jquery.dataTables.css">

		<!-- jQuery -->
		<script type="text/javascript" charset="utf8" src="//code.jquery.com/jquery-1.10.2.min.js"></script>

		<!-- Font Awesome -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
			
		<!-- DataTables -->
		<script type="text/javascript" charset="utf8" src="//cdn.datatables.net/1.10.9/js/jquery.dataTables.js"></script>

		
			
		<script>
			
		<?php
		//Hacemos todas las llamadas vía Ajax para implementar preloader
		$path = $_SERVER['SCRIPT_NAME'];
		$queryString = $_SERVER['QUERY_STRING'];
		$menujs=str_replace("'","\"",$menu);
		?>						
		function loadingAjax(div_id)
		{
				$("#"+div_id).html('<?php echo $menujs;?><center><div class="homelogo"><div class="loader"></div></div></center>');
				$.ajax({
					type: "GET",
					url: "<?php echo $path.'?'.$queryString; ?>",
					data: "ajaxcall=1",
					success: function(msg){
						$("#"+div_id).html(msg);
					}
				});
		}	

		jQuery(document).ready(function($) {
				/* Next part of code handles hovering effect and submenu appearing */
				$('.nav li').hover(
				  function () { //appearing on hover
					$('ul', this).fadeIn();
				  },
				  function () { //disappearing on hover
					$('ul', this).fadeOut();
				  }
				);

			/*Datatables*/
			$('#filess').dataTable( {
				"iDisplayLength": 250,	
				"order": [],
				"columnDefs": [ {
				  "targets"  : 'no-sort',
				  "orderable": false,
				}]
			} );
			
			/*Disable/Enable form buttons*/
			$('.sendButton1').prop('disabled',true);
			$('#keyword1').keyup(function(){
					$('.sendButton1').prop('disabled', this.value == "" ? true : false);     
				})
			$('.sendButton2').prop('disabled',true);
			$('#keyword2').keyup(function(){
					$('.sendButton2').prop('disabled', this.value == "" ? true : false);     
				})
			
			/*Highlight*/
			$('.resaltada').each(function(i) {
				$(this).addClass("item" + (i+1));
				$('.indice').append( " | <a href='#' id='Item"+i+"' style='color:#CC0000;'><i class='fa fa-bug'></i>"+(i+1)+"</a> <script>$('#Item"+i+"').click(function(){   $('html,body').animate({scrollTop: $('.item"+(i+1)+"').offset().top-120}, 'fast') });<\/script>" );			
			});
			
			/*Window popup*/
			$('.popup').click(function(event) {
				ancho=$( window ).width();
				event.preventDefault();
				window.open($(this).attr("href"), "popupWindow", "width="+ancho+",height=600,scrollbars=yes");
			});
			

		});
		</script>

		
</head>	


<body onload="loadingAjax('mainDIV');">
<?php 
//All calls to php are done via ajax
if (empty($_GET['ajaxcall'])) {
	echo "<div id='mainDIV'></div></body></html>";
	exit;
}
?>

</div>

			<?php
				//Highlight function
				function resaltar($file, $string) 
				{ 
				  $lines = implode(range(1, count(file($file))), '<br />'); 
				  				  
				  if (function_exists('highlight_file'))  $content = highlight_file($file, true);
				  else  $content = highlight_string(file_get_contents($file), true);
				  				  
				  if ($string=="base64") {
					 $content=str_ireplace("base64","<span class='resaltada'>base64</span>",$content);
					 $content=str_ireplace("eval","<span class='resaltada'>eval</span>",$content);
				  }
				  elseif ($string=="x") {
					 $content=preg_replace("/\\\\x([a-f0-9]{2})/i","<span class='resaltada'>$0</span>",$content);
				  }
				  else $content=str_replace("$string","<span class=\"resaltada\">$string</span>",$content);
				  				  
				 // if (!function_exists('highlight_file')) $content = highlight_string($content, true);  				
					
					//echo "asdf";
				 echo "<table style='margin-top:40px;'><tr><td class=\"num\">\n$lines\n</td><td>\n  $content  \n</td></tr></table>"; 
				
				} 				
				//Menu and button functions				
					//Inspect File
					@$action=$_GET['action'];	
					@$db=$_GET['db'];
					if ($action==1){
						$file=$_GET['file'];
						?>
						<div class="indice"><input type='text' class='urlinput' value='<?php echo $file; ?>' readonly>
							<a href='#' id='top'><i class="fa fa-arrow-up"></i></a>
							<script>$('#top').click(function(){ $('html,body').animate({scrollTop: 0}, 'fast'); });</script>
						</div>
						<?php
						$string=$_GET['string'];
						resaltar($file, $string); 
						exit;
					}
				//arrays of files
				$infected_files = array();
				$scanned_files = array();								
				//Scan dir function
				function scan($dir) {
					$action=$_GET['action'];
					$scanned_files[] = $dir;
					$files = scandir($dir);					
					if(!is_array($files)) {
						throw new Exception('Unable to scan directory ' . $dir . '.  check permissions.');
					}					
					foreach($files as $file) {
						if(is_file($dir.'/'.$file) && !in_array($dir.'/'.$file,$scanned_files)) {										
							if (filesize($dir.'/'.$file)<=1000000) @check(file_get_contents($dir.'/'.$file),$dir.'/'.$file,$action);
							//else echo "<br>Big file halted: $dir/$file ".filesize($dir.'/'.$file);
						} elseif(is_dir($dir.'/'.$file) && substr($file,0,1) != '.') {
							scan($dir.'/'.$file);
						}
					}		
				}				
				//Check perms function
				function showPerm($path)
				{
					clearstatcache(null, $path);
					return decoct( fileperms($path) & 0777 );
				}				
				//Format file size function
				function formatSizeUnits($bytes)
				{
					if ($bytes >= 1073741824)
					{
						$bytes = number_format($bytes / 1073741824, 2) . ' GB';
					}
					elseif ($bytes >= 1048576)
					{
						$bytes = number_format($bytes / 1048576, 2) . ' MB';
					}
					elseif ($bytes >= 1024)
					{
						$bytes = number_format($bytes / 1024, 2) . ' KB';
					}
					elseif ($bytes > 1)
					{
						$bytes = $bytes . ' bytes';
					}
					elseif ($bytes == 1)
					{
						$bytes = $bytes . ' byte';
					}
					else
					{
						$bytes = '0 bytes';
					}
					return $bytes;
				}				
				//Check file against malware function
				function check($contents,$file,$action) {
					$scanned_files[] = $file;		
					@$GLOBALS['countfiles']++;

					//Ignore this file
					if ($file !=__FILE__) {
						$keybuscada=@$_GET['keyword'];						
						//     "/#p@\\$c@#/";
						
						$footprintSoft[]='base64_decode(';
						$footprintSoft[]='HTTP_REFERER'; // checks for referrer based conditions
						$footprintSoft[]='HTTP_USER_AGENT'; // checks for user agent based conditions
						
						$footprintMedium[]='move_uploaded_file';
						$footprintMedium[]='chmod';
						$footprintMedium[]='googlebot';
						$footprintMedium[]='strrev';
						
						$footprintHard[]='edoced_46esab'; // base64_decode reversed
						$footprintHard[]='assert(';
						$footprintHard[]='create_function(';
						$footprintHard[]='$_REQUEST['; 
						$footprintHard[]='passssword';	
						
						//Key Plain Text
						if (!empty($keybuscada)){
							if (preg_match("/$keybuscada/", $contents)) {
									echo "<tr><td>";
									echo "<strong>$keybuscada</strong>";
									echo "</td><td><small>";
									echo $infected_files[] = $file;
									@$GLOBALS['countinfectedfiles']++;
									echo "<small></td><td>";
									echo "<strong><span>".filemtime($file)."</span>".date ("d/m/Y - H:i:s", filemtime($file))."</strong>";
									echo "</td><td>";
									echo @formatSizeUnits(filesize($file));
									echo "</td><td>";
									echo @showPerm($file);
									echo "</td><td>";
									echo " <a target='_blank' class='popup' href='?action=1&file=$file&string=$keybuscada'><i class='fa fa-eye'></i></a>";				
									echo "</td></tr>";
								}
						}
						else { //No Key Search

							if (($action==3)||($action==2)){
							
								//high suspicious values
								foreach ($footprintHard as $value){
									if (@preg_match("/$value/", $contents)) {
										echo "<tr class='red'><td>";
										echo "<strong>$value</strong>";
										echo "</td><td><small>";
										echo $infected_files[] = $file;
										@$GLOBALS['countinfectedfiles']++;
										echo "<small></td><td>";
										echo "<strong><span>".filemtime($file)."</span>".date ("d/m/Y - H:i:s", filemtime($file))."</strong>";
										echo "</td><td>";
										echo @formatSizeUnits(filesize($file));
										echo "</td><td>";
										echo @showPerm($file);
										echo "</td><td>";
										echo " <a target='_blank' class='popup' href='?action=1&file=$file&string=$value'><i class='fa fa-eye'></i></a>";				
										echo "</td></tr>";
									}
								}
								
								//warning dangerous regex
								if(@preg_match('/eval\((base64|eval|\$_|\$\$|\$[A-Za-z_0-9\{]*(\(|\{|\[))/i',$contents)) {
									echo "<tr class='red'><td>";
									echo "<strong>eval/base64</strong>";
									echo "</td><td><small>";
									echo $infected_files[] = $file;
									@$GLOBALS['countinfectedfiles']++;
									echo "<small></td><td>";
									echo "<strong><span>".filemtime($file)."</span>".date ("d/m/Y - H:i:s", filemtime($file))."</strong>";
									echo "</td><td>";
									echo @formatSizeUnits(filesize($file));
									echo "</td><td>";
									echo @showPerm($file);
									echo "</td><td>";
									echo " <a target='_blank' class='popup' href='?action=1&file=$file&string=base64'><i class='fa fa-eye'></i></a>";
									echo "</td></tr>";
								}
							}							
							if (($action==4)||($action==2)){							
								//medium suspicious values
								foreach ($footprintMedium as $value){
									if (@preg_match("/$value/", $contents)) {
										echo "<tr class='blue'><td>";
										echo "<strong>$value</strong>";
										echo "</td><td><small>";
										echo $infected_files[] = $file;
										@$GLOBALS['countinfectedfiles']++;
										echo "<small></td><td>";
										echo "<strong><span>".filemtime($file)."</span>".date ("d/m/Y - H:i:s", filemtime($file))."</strong>";
										echo "</td><td>";
										echo @formatSizeUnits(filesize($file));
										echo "</td><td>";
										echo @showPerm($file);
										echo "</td><td>";
										echo " <a target='_blank' class='popup' href='?action=1&file=$file&string=$value'><i class='fa fa-eye'></i></a>";				
										echo "</td></tr>";
									}
								} 
								//hex encoded chars
								if(@preg_match('/\\\\x([a-f0-9]{2})/i',$contents)) {
									echo "<tr class='blue'><td>";
									echo "<strong>HEX</strong>";
									echo "</td><td><small>";
									echo $infected_files[] = $file;
									@$GLOBALS['countinfectedfiles']++;
									echo "<small></td><td>";
									echo "<strong><span>".filemtime($file)."</span>".date ("d/m/Y - H:i:s", filemtime($file))."</strong>";
									echo "</td><td>";
									echo @formatSizeUnits(filesize($file));
									echo "</td><td>";
									echo @showPerm($file);
									echo "</td><td>";
									echo " <a target='_blank' class='popup' href='?action=1&file=$file&string=x'><i class='fa fa-eye'></i></a>";
									echo "</td></tr>";
								}
								//low suspicious values
								foreach ($footprintSoft as $value){
									if (@preg_match("/$value/", $contents)) {
										echo "<tr><td>";
										echo "<strong>$value</strong>";
										echo "</td><td><small>";
										echo $infected_files[] = $file;
										@$GLOBALS['countinfectedfiles']++;
										echo "<small></td><td>";
										echo "<strong><span>".filemtime($file)."</span>".date ("d/m/Y - H:i:s", filemtime($file))."</strong>";
										echo "</td><td>";
										echo @formatSizeUnits(filesize($file));
										echo "</td><td>";
										echo @showPerm($file);
										echo "</td><td>";
										echo " <a target='_blank' class='popup' href='?action=1&file=$file&string=$value'><i class='fa fa-eye'></i></a>";				
										echo "</td></tr>";
									}
								} 
							}		
						}	
					}
				}
								
				
									
				/*PHP TIMEOUT SETTINGS*/
				@ini_set('memory_limit', '-1'); ## Avoid memory errors (i.e in foreachloop)
				@set_time_limit(0);
				@ini_set("max_execution_time",0);
				//ignore_user_abort();			FUL	
				?>
				
				<!-- MENU -->
				<?php echo $menu;?>
				<!-- MENU -->
				
				<div class='contienetop'>
					<div class='title'>
						<div class='undermenu'>
							<a href='<?php echo basename($_SERVER['PHP_SELF']); ;?>'>Malware<span class="fa-stack fa-lg"><i class="fa fa-bug fa-stack-1x"></i>
							  <i class="fa fa-ban fa-stack-2x text-danger" style='color:red;'></i>
							  </span>Scan</a>
							<p style="font-size:10px;margin-top:0px;"><a target='_blank' href='http://tecnoes.com'>by tecnoes.com</a></p>
						</div>
	
						<div class='undermenu'>
							<form action='' method='get' class='form'>
							  <input type='hidden' name='action' value='2'>
							  <i class="fa fa-file-code-o"></i><input id='keyword1' type='text' name='keyword' placeholder='Find keyword in files'>
							  <input type='submit' value='Find' class='sendButton1'>
							</form>
						</div>
						<div class='undermenu'>
							<form action='' method='get' class='form'>
							  <input type='hidden' name='db' value='2'>
							  <i class="fa fa-database"></i><input type='text' id='keyword2' name='keyword' placeholder='Find keyword in database'>
							  <input type='submit' value='Find' class='sendButton2'>
							</form>
						</div>
					</div>	
				</div>								
				<?php
				if ($action==6){
							/*BEGIN HELP*/
							?>
							<div style='clear:both;'></div>
							<div class='helpcontainer'>
								<h1><i class="fa fa-cogs"></i> Tool Options</h1>
								<ul style='list-style-type: none;'>
									<li><h2><i class="fa fa-search"></i> Find Keywords</h2></li>
										<ul>
											<li><h4>Find Keywords in files: </h4> <span> It will search your keyword in all your web files and folders. It´s useful to locate malicious code if you cannot use Linux grep tool</span> </li>
											<li><h4>Find Keywords in database: </h4> <span> It will search your keyword in all your database tables. It´s useful to locate malware code in MySQL database</span> </li>
										</ul>
									<li><h2><i class="fa fa-file-code-o"></i> File Scan</h2></li>
										<ul>
											<li><h4>Normal Scan: </h4> <span> It will scan for common strings used in web code malware </span> </li>
											<li><h4>Advanced Scan: </h4 > <span> It will scan for suspicious strings used in web code malware </span> </li>
											<li><h4>Normal & Advanced Scan: </h4> <span> It will run normal and advanced scan </span></li>
										</ul>
									<li><h2><i class="fa fa-database"></i> Database Scan</h2></li>
										<ul>
											<li><h4>Database Settings: </h4> <span> It will auto detect your database settings (valid for Wordpress, Joomla and Prestashop) or let you enter its values manually </span> </li>
											<li><h4>Javascript Scan: </h4> <span> It will search for javascript code in database </span> </li>
										</ul>
									<li><h2><i class="fa fa-external-link"></i> External Tools</h2></li>
										<ul>
											<li><h4>Sucuri: </h4> <span> It will show Sucuri tool for current domain </span> </li>
											<li><h4>Google Safebrowsing: </h4> <span> It will show Google Safebrowsing tool for current domain </span> </li>
											<li><h4>Spam Queries: </h4> <span> It will run common Google queries to detect malware in your website </span> </li>
										</ul>
								</ul>
								<h1><i class="fa fa-info-circle"></i> Read Me</h1>
								<ul>
									<li><h4>IMPORTANT: </h4> <span> for security reasons, your first steps using this script should be creating a new user and password and rename this file. You can change default user and password editing this file with any text editor and changing its first 3 lines. After this, you should delete your browser cookies. </span> </li>
									<li><h4>You should be an experienced user </h4> <span> before removing any code from your site. This tool is made for identifying malware code easily but it´s common to see false positive code that should not be deleted.</span> </li>
									<li><h4>If you are hosting a lot of sites </h4> <span> or a huge one, or your server is very slow or limited, you can use this tool in every subfolder to avoid timeouts. </span> </li>
								</ul>
							</div>
							<?php
							/*END HELP*/
							} 
				elseif (!empty($action)){
				?>

				<!-- File Tables-->
				<table class='filestable' id='filess'>
					<thead>
						<tr>
							<th>String</th>
							<th>File path</th>
							<th>Date/Time</th>
							<th>Size</th>
							<th>Perm</th>
							<th class='no-sort'></th>
						</tr>
					</thead>
					<?php
					//Begin Scan
					scan(dirname(__FILE__));				
					if (count(@$GLOBALS['countinfectedfiles'])<=0) echo "</table><table style='width:100%; text-align:center;'><tr><td><h3 class='green' style='font-size:0.8em;'><i class='fa fa-thumbs-o-up'></i> No suspicious files found</h3></td></tr>";
					else echo "</table><table style='width:100%; text-align:center;'><tr><td><h3 class='red' style='font-size:0.8em;'><i class='fa fa-bug'></i>".@$GLOBALS['countinfectedfiles']." suspicious files found</h3></td></tr>";				
					?>
				</table>
				<?php } else { 
							if (!empty($db)){?>																
								<?php 
								/*Database Settings Selected*/
								if ($db==1){?>
									<?php
									$fileconDB=detectDatabase();
									?>
									 <div class='homelogo' style='font-size:14px;'><h1>Database Settings</h1>
									 <h3><?php if ($cms==1) echo "Wordpress Database Detected: <em>$fileconDB</em>";?>
									 <?php if ($cms==2) echo "Joomla Database Detected: <em>$fileconDB</em>";?>
									 <?php if ($cms==3) echo "Prestashop Database Detected: <em>$fileconDB</em>";?>
									  <?php if ($cms==4) echo "No Database Detected: <em>please enter settings</em>";?></h3>
									 <br>
									 Database: <input type='text' id='db' value='<?php echo @$db;?>'><br>
									 User: <input type='text' id='dbuser' value='<?php echo @$dbuser;?>'><br>
									 Password: <input type='text' id='dbpass' value='<?php echo @$dbpass;?>'><br>
									 Server: <input type='text' id='dbhost' value='<?php echo @$dbhost;?>'><br>
									 <input type='button' value='Test & Save Database' class='botondb'><br>
									 </div>
								<?php }?>
								
								<?php
								/*Database Scan Selected*/
								if ($db==2){?>
									<div style='clear:both;'></div>
									<?php									
									$keybuscada="default"; //si no viene key buscada, escaneo keys de la database típicas									
									//Get saved database settings									
									if ((isset($_COOKIE['db']))&&(isset($_COOKIE['dbuser']))&&(isset($_COOKIE['dbhost']))){ //&&(isset($_COOKIE['dbpass']))
										$GLOBALS['db']=$_COOKIE['db'];
										$GLOBALS['dbuser']=$_COOKIE['dbuser'];
										$GLOBALS['dbpass']=@$_COOKIE['dbpass'];
										$GLOBALS['dbhost']=$_COOKIE['dbhost'];										
										$keybuscada=@$_GET['keyword'];										
										$searchFromDb=array($GLOBALS['db']);									
										$conn=mysql_pconnect($GLOBALS['dbhost'],$GLOBALS['dbuser'],$GLOBALS['dbpass']) or die(mysql_error());			
										if(is_array($searchFromDb) && count($searchFromDb)>0) {
											$databaseArray=$searchFromDb;
										}
										else{
											$databaseArray=getDatabases();
											}
										$tableArray=getTables($databaseArray);
										$tableList=getFields($tableArray);
										$result=getResults($keybuscada,$tableList);
										$count=count($result);
										//keys posibles: gzinflate																				
										if ($count==0) echo "<table style='width:100%; text-align:center;'><tr><td><h3 class='red'><i class='fa fa-bug'></i> <em>$keybuscada</em> not found in database</h3></td></tr>";
										else{										
										?>
											<table class='filestable' id='filess'>
												<thead>
													<tr>
														<th>Table name</th>
														<th>Field name</th>
														<th class='no-sort' style='width:80%;'>SQL Query</th>
													</tr>
												</thead>
												<?php
												//Begin Database Scan
												echo printSearch($result);
												?>
											</table>
										<?php
										}	
									} else echo "<h3 style='width:100%; text-align:center;'>Please, save database settings first</h3>";
									?>
								<?php }?>	 
						<?php } else {?>
							<div class='homelogo'>Malware<span class="fa-stack fa-lg">  <i class="fa fa-bug fa-stack-1x"></i>	  <i class="fa fa-ban fa-stack-2x text-danger" style='color:red;'></i> </span>Scan</a></div>
						<?php }?>
				<?php }?>
				<div style='width:100%;text-align:center;font-size:small'>
				<?php /*TOTAL DE FICHEROS ESCANEADOS*/ if (!empty($GLOBALS['countfiles'])) { echo @$GLOBALS['countfiles'].' scanned files'; }?>
				
				<?php
				$time2 = microtime(true);
				echo "<br>execution time: ".round(($time2-$time1),2)." sec"; //value in seconds			
				?>
				</div>
			</body>	
	<script>
		jQuery(document).ready(function($) {		
			/*AJAX*/
			$('.botondb').click(function() {
				var val = $(this).val();
				$.ajax({
					url: '<?php echo basename($_SERVER['PHP_SELF']); ;?>',
					type: "GET",
					data: {
						db:$('#db').val(),
						dbuser:$('#dbuser').val(),
						dbpass:$('#dbpass').val(),
						dbhost:$('#dbhost').val(),
						ajax:1
					},
				   success: function(response) {
						alert(response);
					},
					error: function(xhr) {
						alert("Error");
					}  
				});
			});	
		});			
	</script>		
	</html>
<?php				
      exit;
   } else {
      echo "Cookie error";
      exit;
   }
}
 if(isset($_POST['keypass'])){
   if ($_POST['user'] != $username) {
      echo "<div style='color:red;font-size:0.7em;padding:5px;'>Sorry, username or password does not match.</div>";
      //exit;
   } else if ($_POST['keypass'] != $password) {
      echo "<div style='color:red;font-size:0.7em;padding:5px;'>Sorry, username or password does not match.</div>";
     // exit;
   } else if ($_POST['user'] == $username && $_POST['keypass'] == $password) {
	  setcookie('AdminLogin', md5($_POST['keypass'].$nonsense));
      header("Location: $_SERVER[PHP_SELF]");
   } else {
      echo "Login error";
   }
}
?>	
<!-- BEGIN LOGIN SCREEN-->
<html>
	<head>	
			<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
			<title>Malware Scan</title>
			<style type="text/css"> 
				body { padding:50px;  text-align:center;font-size:1.8em; background-color:#DEDEDE; color: #525252;}
				form label { font-size: 0.7em; float: left; margin-left: 32px;}
				form { text-align:center; margin: 0 auto; margin-top:10px; }
				.container { width:300px; margin:0 auto; padding:20px;background:#eaebec; border:#ccc 1px solid; -moz-border-radius:3px; -webkit-border-radius:3px; border-radius:3px; -moz-box-shadow: 0 1px 2px #AFAEAE; -webkit-box-shadow: 0 1px 2px #AFAEAE; box-shadow: 0 1px 2px #AFAEAE;}
				.button: { padding: 20px;font-size:1.4em;}
				.fa-stack { font-size: 1em!important;}
				input { font-size: 24px; width: 75%; padding: 3px; margin: 2px 6px 16px 0;}
				#av_toolbar_iframe, #av_toolbar_regdiv, .av_site{
				display:none;
				}
				body {
					margin-top: 5px!important;
				}
			</style>
	</head>	
	<body>
		<a href='<?php echo basename($_SERVER['PHP_SELF']); ;?>'>Malware<span class="fa-stack fa-lg">
									  <i class="fa fa-bug fa-stack-1x"></i>
									  <i class="fa fa-ban fa-stack-2x text-danger" style='color:red;'></i>
									  </span>Scan</a>
		<div style="clear:both;height:20px;"></div>
		<div class="container">	
			<form action="" method="post">
			<label>user</label><div style="clear:both;height:3px;"></div><input type="text" name="user" id="user" placeholder="username" /> <br />
			<label>password</label><div style="clear:both;height:3px;"></div><input type="password" name="keypass" id="keypass" placeholder="password"/> <br />
			<input class='button' type="submit" id="submit" value="login" /><br>
			<small style='font-size:0.7em;'>user: min<br>password: word</small>
			</form>		
		</div>
	</body>
</html>
<!-- END LOGIN SCREEN-->
