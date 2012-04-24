<?php

   include_once "globals.php";

   if(!$in) {
	die("<meta http-equiv=Refresh content='1; url=everlogin.php?username=$username&title=$title'>");
   }

   if(isset($_POST["update"])) {
	   $enotes = $_POST["edata"];
	   $title = $_POST["title"];
	   updateNote($title, $enotes);
   } else {
	   if(!isset($_GET["target"])) {
		   if(isset($_POST["title"]))
			$_SESSION["title"] = $title;
		   if(!isset($_GET["title"]))
			$title = $_SESSION["title"];
		   else
			$title = $_GET["title"];
	   }
	   $enotes = getNote($title);
   }

?>
<html>
<head>
<title>Simple Evernote - evernote for dummies</title>
</head>
<body>
<div style="width: 100%; height: 95%">
<form style = "display: inline" method='post' action="evernote.php?title=<?php echo $title; ?>&username=<?php echo $username; ?>">
<?php 
	if (preg_match("/kindle/", strtolower ( $_SERVER['HTTP_USER_AGENT'] ))) {
		echo "<textarea style='font-size: small' rows=\"44\" cols=\"47\" name='edata'>";
       } else {
		echo "<textarea style=\"font-size: small; width: 100%; height: 100%\" name='edata'>";
       }
	echo "$enotes";
 ?>
</textarea>
<br>
<input style="font-size: x-small" type=submit name=update value="Save"/>
<input type=hidden name=title value="<? echo $title; ?>"/>
</form>
<form style = "display: inline; font-size: small" method='get' action="evernote.php?username=<?php echo $username; ?>">
<select onchange="submit();" name="title" style="font-size: x-small">
<?php
foreach (split(",", $_SESSION["notelist"]) as $entry) {
	echo "<option value='" . $entry . "'";
	if($entry == $title)
		echo "selected";
	echo ">" . $entry . "</option>";
}
?>
</select>
<?php
echo "<a href='everlogin.php?username=$username&title=$title'>Logout</a></b>";
?>
<input type="hidden" name="username" value="<? echo $username; ?>"/>
</form>
</div>
</body>
</html>
