<?php 
	include_once "globals.php";

	$username="username";
	$title="which_note";

	if(isset($_POST["title"]))
		$title=$_POST["title"];
	else if(isset($_GET["title"]))
		$title=$_GET["title"];
	if(isset($_POST["username"]))
		$username=$_POST["username"];
	else if(isset($_GET["username"]))
		$username=$_GET["username"];


	if($in) {
		session_unregister("admin");    
		header("Location: everlogin.php?username=$username&title=$title");
	}

	if(isset($_POST["pass"])) {
		evernote_connect($username, $_POST["pass"]);
		session_register("admin");
		$_SESSION["title"] = $title;
		$_SESSION["username"] = $username;
		header("Location: evernote.php?username=$username&title=$title");
	}
?>
<html>
<head>
<title>Simple Evernote - Evernote for Dummies</title>
</head>
<body>
<center>
<form style="display: inline" action="everlogin.php" method="post">
<table width="200">
<tr><td>Username: </td><td><input type="text" name="username" value="<?php echo $username; ?>"></td></tr>
<tr><td>Password: </td><td><input type="password" name="pass"></td></tr>
<tr><td>Note: </td><td><input type="text" name="title" value="<?php echo $title; ?>"></td></tr>
<tr><td></td><td><input type="submit" value="<?php echo (!session_is_registered("admin") ? "Not Logged In" : "In"); ?>"></td></tr>
<tr><td colspan="2">
<br>
<b>Instructions:</b> Type the name of a note from your default notebook in the last box. After you login, that note will be permanently open (until your login expires). The interface was designed for scatter-brained people, like myself, who don't like to have all their notes separated out, but rather need one large piece of paper, so to speak, with all the notes in one place.
</td></tr>
</table>
</form>
</center>
</body>
</html>
