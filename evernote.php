<?php

   include_once "globals.php";

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

   evernote_refresh();
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
