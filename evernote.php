<?php

   include_once "globals.php";
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

   if(!$in) {
	die("<meta http-equiv=Refresh content='1; url=everlogin.php?username=$username&title=$title'>");
   }

   if(isset($_POST["update"])) {
	   $enotes = $_POST["edata"];
	   updateDefaultNote($enotes);
	   echo "<h4>Update to evernote succeeded - Refreshing page now.</h4>";
   } else {
	   $enotes = getDefaultNote();
   }

   if(!isset($_GET["norefresh"]))
	echo "<meta http-equiv=Refresh content='1; url=evernote.php?norefresh&username=$username&title=$title'>";
?>
<html>
<head>
<title>Simple Evernote - evernote for dummies</title>
</head>
<body>
<div style="width: 100%; height: 90%">
<form method='post' action="evernote.php">
<textarea style="width: 100%; height: 100%" name='edata'><?php echo "$enotes"; ?></textarea>
<input type=submit name=update value="Save"/>  or 
<?php
echo "<a href='everlogin.php?username=$username&title=$title'>Logout</a> of note: <b>".$_SESSION["title"]."</b>";
?>
</form>
</div>
</body>
</html>
