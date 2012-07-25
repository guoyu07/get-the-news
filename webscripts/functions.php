<?php
###########################################################################
### Copyright: (2012) Marcel Jira
###
### License: GPL-3
###
### This file is part of get-the-news.
###
### get-the-news is free software: you can redistribute it and/or modify
### it under the terms of the GNU General Public License as published by
### the Free Software Foundation, either version 3 of the License, or
### (at your option) any later version.
###
### This program is distributed in the hope that it will be useful,
### but WITHOUT ANY WARRANTY; without even the implied warranty of
### MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
### GNU General Public License for more details.
###
### You should have received a copy of the GNU General Public License
### along with this program.  If not, see <http://www.gnu.org/licenses/>.
###########################################################################

## First get user settings

include("settings.php");

###########################################################################
### Helper Functions
###########################################################################

function startSessionNoCookies() {
	ini_set("session.use_only_cookies", "0");
	session_start();
}

function str_lreplace($search, $replace, $subject) {
	$pos = strrpos($subject, $search);

	if($pos == false) {
		return $subject;
	} else {
		return substr_replace($subject, $replace, $pos, strlen($search));
	}
}

function curPageURL() {
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

function sort_desc_by_mtime($file1, $file2) {
	return (filemtime($file2) - filemtime($file1));
}

function sort_asc_by_mtime($file1, $file2) {
	return (filemtime($file1) - filemtime($file2));
}

function encrypt($string, $encryptionKey) {
	if ($encryptionKey == "") {
		return $string;
	} else {
		$result = '';
		for($i=0; $i<strlen ($string); $i++) {
			$char = substr($string, $i, 1);
			$keychar = substr($encryptionKey, ($i % strlen($encryptionKey))-1, 1);
			$char = chr(ord($char)+ord($keychar));
			$result.=$char;
		}
		return base64_encode($result);
	}
}

function decrypt($string, $encryptionKey) {
	if ($encryptionKey == "") {
		return $string;
	} else {
		$result = '';
		$string = base64_decode($string);

		for($i=0; $i<strlen($string); $i++) {
			$char = substr($string, $i, 1);
			$keychar = substr($encryptionKey, ($i % strlen($encryptionKey))-1, 1);
			$char = chr(ord($char)-ord($keychar));
			$result.=$char;
		}
		return $result;
	}
}

function startDownload($path, $mimeType) {

	include("settings.php");

	## sessions enabled?
	if ($sessionsOn == TRUE) {
		## check session
		startSessionNoCookies();
		## get session
		session_id($_GET[session_name()]);

		## check session
		if (!isset($_SESSION)) {
			session_destroy();
			exit("Session invalid (or expired) go back and try again");
		} elseif (!isset($_SESSION['startTime'])) {
			session_destroy();
			exit("Session start time not set - go back and try again");
		} elseif ((time() - $_SESSION['startTime']) > $sessionTimeout) {
			session_destroy();
			exit("Session expired - go back and try again");
		}
	}

	## folderPath
	$folderPath = rtrim($newspaperFolder, "/") . "/";

	$contentFolderPath = realpath($folderPath . $path);

	if(!file_exists($contentFolderPath)) {
		// File doesn't exist, output error
		exit('File not found');
	} else {
		$size = filesize($contentFolderPath);
		$file = basename($contentFolderPath);

		// Set headers
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers 
		header("Content-Description: File Transfer");
		header("Content-Disposition: attachment; filename=\"$file\"");
		header("Content-Type: $mimeType");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: $size");
		// Read the file from disk
		readfile($contentFolderPath);
	}

	## quit session
	session_destroy();

	exit();
}

function isPassPhraseCorrect() {
	@$inputPhrase = $_GET['pass'];
	include("settings.php");
	return $inputPhrase == $passPhrase;
}

function passPhraseForm($completeURL) {
	## Don't forget any parameters here!
	@$feed        = $_GET['feed'];
	@$extension   = $_GET['ext'];
	@$short       = $_GET['short'];
	@$path        = $_GET['path'];
	@$inputPhrase = $_GET['pass'];
	
	$theURL = parse_url(curPageURL());
	$submitURL = $theURL[scheme] . "://" . $theURL[host] . $theURL[path];
	
	$message = "Passphrase incorrect! Please enter correct passphrase!";
	if ($inputPhrase == "") {
		$message = "Please enter passphrase!";
	}
	
	echo("
		<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\"\n
		\"http://www.w3.org/TR/html4/strict.dtd\">\n
		<html>\n
		<head>\n
		<title>Authentication</title>\n
		<script type=\"text/javascript\">\n
		function setFocus() {\n
		    document.getElementById(\"pass\").focus();\n
		}\n
		</script>\n
		</head>\n
		<body onload=\"setFocus()\">\n
		\n
		<p>$message</p>\n
		\n
		<form action=\"$submitURL\" method=\"get\">\n
		
		<input type=\"hidden\" name=\"feed\" value=\"$feed\">
		<input type=\"hidden\" name=\"ext\" value=\"$ext\">
		<input type=\"hidden\" name=\"short\" value=\"$short\">
		<input type=\"hidden\" name=\"path\" value=\"$path\">
		
		<p>Passphrase:<br><input name=\"pass\" type=\"text\" id=\"pass\" value=\"$inputPhrase\" size=\"20\" maxlength=\"40\"></p>\n
		\n
		<input type=\"submit\" value=\" Submit \">\n
		\n
		</form>\n
		</body>\n
		</html>\n
	");
}

function replaceFirst($input, $search, $replacement){
	$pos = stripos($input, $search);
	if($pos === false) {
		return $input;
	}
	else{
		$result = substr_replace($input, $replacement, $pos, strlen($search));
		return $result;
	}
}

function wwwAuthenticate() {
	include("settings.php");

	$theTitle = $title;
	if ($rssCopyright != "") {
		$theTitle .= " by $rssCopyright";
	}

	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="' . $theTitle . '"');
		exit('This page requires authentication!');
	}

	if (!isset($users[$_SERVER['PHP_AUTH_USER']])) {
		header('HTTP/1.0 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="' . $theTitle . '"');
		exit('Unauthorized!');
	} elseif ($users[$_SERVER['PHP_AUTH_USER']] != $_SERVER['PHP_AUTH_PW']) {
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="' . $theTitle . '"');
		exit('Unauthorized!');
	}
}

function checkPassPhrase() {
	if (!isPassPhraseCorrect()) {
		passPhraseForm($completeURL);
		exit();
	}
}

function authenticate() {
	include("settings.php");

	if ($httpOn) {
		wwwAuthenticate();
	}
	if ($phraseOn) {
		checkPassPhrase();
	}
}

?>
