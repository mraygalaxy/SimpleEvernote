<?php
	include_once "prefs.php";
	$evernotePort = "80";
	$evernoteScheme = "http";

	if(!session_is_registered("session")) {
		session_start();
		session_register("session");
		session_set_cookie_params(0 , "/", ".".$_SERVER['HTTP_HOST']);
	}

	$in = session_is_registered("admin");

	use EDAM\UserStore\UserStoreClient;
	use EDAM\NoteStore\NoteStoreClient;
	use EDAM\NoteStore\NoteFilter;
	use EDAM\Types\Data, EDAM\Types\Note, EDAM\Types\Resource, EDAM\Types\ResourceAttributes;
	use EDAM\Error\EDAMUserException, EDAM\Error\EDAMErrorCode;

	ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . "lib" . PATH_SEPARATOR);

	require_once("autoload.php");
	require_once("Thrift.php");
	require_once("transport/TTransport.php");
	require_once("transport/THttpClient.php");
	require_once("protocol/TProtocol.php");
	require_once("protocol/TBinaryProtocol.php");

	require_once("packages/Errors/Errors_types.php");
	require_once("packages/Types/Types_types.php");
	require_once("packages/UserStore/UserStore.php");
	require_once("packages/UserStore/UserStore_constants.php");
	require_once("packages/NoteStore/NoteStore.php");
	require_once("packages/Limits/Limits_constants.php");

	   $relogin=" - <a href='everlogin.php?username=$username&title=$title'>Re-login</a>";
	   $autologin="";//"<meta http-equiv=Refresh content='1; url=everlogin.php?username=$username&title=$title'>";
        
	function evernote_connect($euser, $epass) {
		global $evernoteHost;
		global $evernotePort;
		global $evernoteScheme;
		global $consumerKey;
		global $consumerSecret;
		global $relogin;
		global $autologin;

		if ($evernoteHost == "")
			die($autologin . " \n evernotehost not configured properly." . $relogin);
		if ($evernoteHost == "")
			die($autologin . " \n evernotehost not configured properly." . $relogin);
		if ($consumerKey == "")
			die($autologin . " \n consumerkey not configured properly." . $relogin);
		if ($consumerSecret == "")
			die($autologin . " \n consumersecret not configured properly." . $relogin);
		try {
			$userStoreHttpClient =
			  new THttpClient($evernoteHost, $evernotePort, "/edam/user", $evernoteScheme);
			$userStoreProtocol = new TBinaryProtocol($userStoreHttpClient);
			$userStore = new UserStoreClient($userStoreProtocol, $userStoreProtocol);

			// Connect to the service and check the protocol version
			$versionOK =
			  $userStore->checkVersion("PHP EDAMTest",
						   $GLOBALS['EDAM_UserStore_UserStore_CONSTANTS']['EDAM_VERSION_MAJOR'],
						   $GLOBALS['EDAM_UserStore_UserStore_CONSTANTS']['EDAM_VERSION_MINOR']);
			if ($versionOK == 0) {
			  print "ERROR: My EDAM protocol version is not up to date!";
			  exit(1);
			}
		} catch (Exception $e) {
			die( $autologin . " \n Failed to login: ". $e->getMessage() . $relogin);
		}

		// Authenticate the user
		try {
		  $authResult = $userStore->authenticate($euser, $epass, $consumerKey, $consumerSecret);
		} catch (edam_error_EDAMUserException $e) {
		  // See http://www.evernote.com/about/developer/api/ref/UserStore.html#Fn_UserStore_authenticate
		  $parameter = $e->parameter;
		  $errorCode = $e->errorCode;
		  $errorText = edam_error_EDAMErrorCode::$__names[$errorCode];

		  echo "Authentication failed (parameter: $parameter errorCode: $errorText)\n";

		  if ($errorCode == $GLOBALS['edam_error_E_EDAMErrorCode']['INVALID_AUTH']) {
		    if ($parameter == "consumerKey") {
		      if ($consumerKey == "en-edamtest") {
			echo "You must replace \$consumerKey and \$consumerSecret with the values you received from Evernote.\n";
		      } else {
			echo "Your consumer key was not accepted by $evernoteHost\n";
			echo "This sample client application requires a client API key. If you requested a web service API key, you must authenticate using OAuth as shown in sample/php/oauth\n";
		      }
		      echo "If you do not have an API Key from Evernote, you can request one from http://www.evernote.com/about/developer/api\n";
		    } else if ($parameter == "username") {
		      echo "You must authenticate using a username and password from $evernoteHost\n";
		      if ($evernoteHost != "www.evernote.com") {
			echo "Note that your production Evernote account will not work on $evernoteHost,\n" .
			     "you must register for a separate test account at https://$evernoteHost/Registration.action\n";
		      }
		    } else if ($parameter == "password") {
		      echo "The password that you entered is incorrect\n";
		    }
		  }

		  echo "\n";
		  exit(1);
		}

		$_SESSION["evertoken"] = $authResult->authenticationToken;
		$_SESSION["everShard"] = $authResult->user->shardId;
		getNoteList();
	}
	function evernote_refresh($noteStore, $authToken) {
		global $evernoteHost;
		global $evernotePort;
		global $evernoteScheme;
		global $consumerKey;
		global $consumerSecret;
		global $relogin;
		global $autologin;

		try {
			$userStoreHttpClient =
			  new THttpClient($evernoteHost, $evernotePort, "/edam/user", $evernoteScheme);
			$userStoreProtocol = new TBinaryProtocol($userStoreHttpClient);
			$userStore = new UserStoreClient($userStoreProtocol, $userStoreProtocol);
		} catch (Exception $e) {
			die( "Failed to refresh login: ". $e->getMessage() . " <a href='everlogin.php'>Re-Login</a>");
		}

		// Authenticate the user
		try {
		  $authResult = $userStore->refreshAuthentication($authToken);
		  $_SESSION["evertoken"] = $authResult->authenticationToken;
		} catch (Exception $e) {
			die($autologin . " \n Failed to refresh login: ". $e->getMessage() . $relogin);
		}
	}

	function getEvernoteParms() {
	   global $evernoteHost;
	   global $evernotePort;
	   global $evernoteScheme;
	   global $relogin;
	   global $autologin;
	   try {
		   $noteStoreHttpClient =
			new THttpClient($evernoteHost, $evernotePort,
				"/edam/note/" . $_SESSION["everShard"], 
				$evernoteScheme);
		   $noteStoreProtocol = new TBinaryProtocol(
			   $noteStoreHttpClient);
		   $noteStore = new NoteStoreClient($noteStoreProtocol, 
			   $noteStoreProtocol);
	   } catch(Exception $e) {
		die($autologin . ' \n Exception creating note store: ' . $e->getMessage() . $relogin);
	   }

	   return array($noteStore, $_SESSION["evertoken"], $_SESSION["title"]);
	}
	function getNoteList() {
		global $relogin;
		global $autologin;
		list($noteStore, $authToken, $title) = getEvernoteParms();
		
		$defaultNotebook = getDefaultNotebook($noteStore, $authToken);
		$filter = new NoteFilter();
		$filter->inactive = false;
		$filter->notebookGuid = $defaultNotebook;
		try {
			$noteList = $noteStore->findNotes($authToken, $filter, 0, 1000);
		} catch(Exception $e) {
			die($autologin . ' \n Exception during getNoteList: '. $e->getMessage() . $relogin);
		}
		$notelist = "";
		foreach ($noteList->notes as $note) {
			$notelist = $notelist . "," . $note->title;
		}
		$_SESSION["notelist"] = $notelist;
	}

	function getDefaultNotebook($noteStore, $authToken) {
		global $relogin;
		global $autologin;

		if(!isset($_SESSION["defaultNotebook"])) {
			try {
				$notebooks = $noteStore->listNotebooks($authToken);
			} catch(Exception $e) {
				die($autologin . ' \n Exception during listNotebooks: ' .  $e->getMessage() . $relogin);
			}
	
			foreach ($notebooks as $notebook) {
			  if ($notebook->defaultNotebook) {
				$_SESSION["defaultNotebook"] = $notebook->guid;
			 	return $notebook->guid;
			  }
			}
		} else {
			return $_SESSION["defaultNotebook"];
		}	
		die($autologin . " \n Could not get default notebook with title $title - Try to $relogin\n");
	}

	function getEvernoteTarget($title, $noteStore, $authToken) {
		global $relogin;
		global $autologin;
				
		$defaultNotebook = getDefaultNotebook($noteStore, $authToken);

		$result = "";

		$filter = new NoteFilter();
		$filter->inactive = false;
		$filter->notebookGuid = $defaultNotebook;
		try {
			$noteList = $noteStore->findNotes($authToken, $filter, 0, 1000);
		} catch(Exception $e) {
			die($autologin . ' \n Exception during findNotes: '. $e->getMessage() . $relogin);
		}

		$target=false;
		foreach ($noteList->notes as $note) {
			if($note->title == $title) {
				$target = $note;
				break;
			}
		}

		if(!$target)
			die($autologin . " \n Could not find note in notebook with title $title - Try to $relogin\n");

		return $target;
	}

	function convert_to_ascii($data) {
		$result = "";
		for ($i = 0; $i < strlen($data); $i++) {
			$value = substr($data, $i, 1);
			$num = ord($value);
			if ( $num == 10 ) {
				$result = $result . "\n";
			} else {
				$result = $result . $num . " ";
			}
		}	
		return $result;
	}

	function getNote($title) {
		global $relogin;
		global $autologin;
		list($noteStore, $authToken, $defaulttitle) = getEvernoteParms();
		$target = getEvernoteTarget($title, $noteStore, $authToken);

		try {
			$note = $noteStore->getNote($authToken, $target->guid,
				true, false, false, false);
			$data = preg_replace("/(.*<en-note>|<\/en-note>)/ms", "", $note->content);
			//$data = preg_replace("/Â/ms", "", $data);
			$data = preg_replace("/".chr(194)."/ms", "", $data);
			$data = preg_replace("/".chr(160)."/ms", "", $data);
			$data = preg_replace("/".chr(13)."/ms", "", $data);
			$data = preg_replace("/".chr(32)."\n/ms", "", $data);
			$data = preg_replace("/<br clear=\"none\"\/>/", "\n", $data);
			$data = preg_replace("/<br\/>/", "\n", $data);
			$data = strip_tags($data);
			$data = preg_replace("/\n([A-Z,0-9])/ms", "NEWLINEPLACEHOLDER\\1", $data);
			$data = preg_replace("/^ /ms", "", $data);
			$data = preg_replace("/^".chr(32)."\n/ms", "", $data);
			$data = preg_replace("/".chr(32)."\n/ms", "\n", $data);
			$data = preg_replace("/\n\n+/ms", "\n", $data);
			$data = preg_replace("/NEWLINEPLACEHOLDER/", "\n", $data);
			$result = $data;
			/* Use this loop to discover wierd ascii codes that don't belong to the text */
			//$result = convert_to_ascii($data);

		} catch(Exception $e) {
			die($autologin . ' \n Exception during getNoteContent: '. $e->getMessage() . $relogin);
		}

		evernote_refresh($noteStore, $authToken);

		return $result;
	}

	function updateNote($title, $contents) {
		global $relogin;
		global $autologin;
		list($noteStore, $authToken, $defaulttitle) = getEvernoteParms();
		$target = getEvernoteTarget($title, $noteStore, $authToken);
		$contents = preg_replace("/&/ms", "&amp;", $contents);
		$contents = preg_replace("/\n/", "<br clear=\"none\"/>", $contents);
		$target->content = 
		  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		  "<!DOCTYPE en-note SYSTEM \"http://xml.evernote.com/pub/enml2.dtd\">" .
		  "<en-note>".$contents."</en-note>";
		try {
			$noteStore->updateNote($authToken,$target);
		} catch(Exception $e) {
			die($autologin . ' \n Exception during updateNote: '. $e->getMessage() . $relogin);
		}
		evernote_refresh($noteStore, $authToken);
	}

	/* Disable that goddamn magic quotes bullshit. */
	if (get_magic_quotes_gpc()) {
    		function stripslashes_gpc(&$value)
		    {
        		$value = stripslashes($value);
		    }
	    array_walk_recursive($_GET, 'stripslashes_gpc');
	    array_walk_recursive($_POST, 'stripslashes_gpc');
	    array_walk_recursive($_COOKIE, 'stripslashes_gpc');
	    array_walk_recursive($_REQUEST, 'stripslashes_gpc');
	}
?>
