<?php
	include_once "prefs.php";
	$evernotePort = "443";
	$evernoteScheme = "https";

	if(!session_is_registered("session")) {
		session_start();
		session_register("session");
		session_set_cookie_params(0 , "/", ".".$_SERVER['HTTP_HOST']);
	}

	$in = session_is_registered("admin");

	ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . "everlib" . PATH_SEPARATOR);

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

	   if(isset($_GET["target"])) {
		$target=$_GET["target"];
		if($target == "michael_work") {
				$title="work";
				$username="darkbeethoven";
		} else if($target == "michael_personal") {
				$title="personal";
				$username="darkbeethoven";
		} else if($target == "chen_work") {
				$title="what_is_name_of_your_work_note";
				$username="chengehines";
		} else if($target == "chen_personal") {
				$title="what_is_name_of_your_personal_note";
				$username="chengehines";
		}
	   } else {
		$title=(isset($_GET["title"]) ? $_GET["title"] : (isset($_SESSION["title"]) ? $_SESSION["title"] : "which_note_would_you_like"));
		$username=(isset($_GET["username"]) ? $_GET["username"] : (isset($_SESSION["username"]) ? $_SESSION["username"] : "username"));
	   }

	   $relogin=" - <a href='everlogin.php?username=$username&title=$title'>Re-login</a>";

	function evernote_connect($euser, $epass) {
		global $evernoteHost;
		global $evernotePort;
		global $evernoteScheme;
		global $consumerKey;
		global $consumerSecret;

		if ($evernoteHost == "")
			die("evernotehost not configured properly. - Try to <a href='everlogin.php'>Re-Login</a>");
		if ($evernoteHost == "")
			die("evernotehost not configured properly. - Try to <a href='everlogin.php'>Re-Login</a>");
		if ($consumerKey == "")
			die("consumerkey not configured properly. - Try to <a href='everlogin.php'>Re-Login</a>");
		if ($consumerSecret == "")
			die("consumersecret not configured properly. - Try to <a href='everlogin.php'>Re-Login</a>");
		try {
			$userStoreHttpClient =
			  new THttpClient($evernoteHost, $evernotePort, "/edam/user", $evernoteScheme);
			$userStoreProtocol = new TBinaryProtocol($userStoreHttpClient);
			$userStore = new UserStoreClient($userStoreProtocol, $userStoreProtocol);

			// Connect to the service and check the protocol version
			$versionOK =
			  $userStore->checkVersion("PHP EDAMTest",
						   $GLOBALS['UserStore_CONSTANTS']['EDAM_VERSION_MAJOR'],
						   $GLOBALS['UserStore_CONSTANTS']['EDAM_VERSION_MINOR']);
			if ($versionOK == 0) {
			  print "ERROR: My EDAM protocol version is not up to date!";
			  exit(1);
			}
		} catch (Exception $e) {
			die( "Failed to login: ". $e->getMessage() . "<a href='everlogin.php'>Re-Login</a>");
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
	}
	function evernote_refresh() {
		global $evernoteHost;
		global $evernotePort;
		global $evernoteScheme;
		global $consumerKey;
		global $consumerSecret;
		global $relogin;
		list($noteStore, $authToken, $title) = getEvernoteParms();

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
			die( "Failed to refresh login: ". $e->getMessage() . $relogin);
		}
	}

	function getEvernoteParms() {
	   global $evernoteHost;
	   global $evernotePort;
	   global $evernoteScheme;

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
		die('Exception creating note store: ' . $e->getMessage() . $relogin);
	   }

	   return array($noteStore, $_SESSION["evertoken"], $_SESSION["title"]);
	}

	function getEvernoteTarget($title) {
		global $relogin;
		list($noteStore, $authToken, $title) = getEvernoteParms();

		try {
			$notebooks = $noteStore->listNotebooks($authToken);
		} catch(Exception $e) {
			die('Exception during listNotebooks: ' .  $e->getMessage() . $relogin);
		}

		foreach ($notebooks as $notebook) {
		  if ($notebook->defaultNotebook) {
		    $defaultNotebook = $notebook;
		    $foundDefault=true;
		  }
		}

		$result = "";

		$filter = new edam_notestore_NoteFilter();
		$filter->inactive = false;
		$filter->notebookGuid = $defaultNotebook->guid;
		try {
			$noteList = $noteStore->findNotes($authToken, $filter, 0, 1000);
	} catch(Exception $e) {
			die('Exception during findNotes: '. $e->getMessage() . $relogin);
		}

		$target=false;
		foreach ($noteList->notes as $note) {
			if($note->title == $title) {
				$target = $note;
				break;
			}
		}

		if(!$target)
			die("Could not find note in notebook with title $title - Try to $relogin\n");

		return $target;
	}

	function getDefaultNote() {
		global $relogin;
		list($noteStore, $authToken, $title) = getEvernoteParms();
		$target = getEvernoteTarget($title);

		try {
			$note = $noteStore->getNote($authToken, $target->guid,
				true, false, false, false);
			$data = preg_replace("/(.*<en-note>|<\/en-note>)/ms", "", $note->content);
			$data = preg_replace("/<br clear=\"none\"\/>/", "\n", $data);
			$data = strip_tags($data);
			$result = $data;
		} catch(Exception $e) {
			die('Exception during getNoteContent: '. $e->getMessage() . $relogin);
		}

		return $result;
	}

	function updateDefaultNote($contents) {
		global $relogin;
		list($noteStore, $authToken, $title) = getEvernoteParms();
		$target = getEvernoteTarget();
		$target->content = 
		  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		  "<!DOCTYPE en-note SYSTEM \"http://xml.evernote.com/pub/enml2.dtd\">" .
		  "<en-note>".preg_replace("/\n/", "<br clear=\"none\"/>", $contents)."</en-note>";
		try {
			$noteStore->updateNote($authToken,$target);
		} catch(Exception $e) {
			die('Exception during updateNote: '. $e->getMessage() . $relogin);
		}
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
