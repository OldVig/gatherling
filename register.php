<?php include 'lib.php';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
<title>PDCMagic.com | Gatherling | Register</title>
<?php include '../header2.ssi';?>
<?php include 'gathnav.php';?>
<div id="contentwrapper">
<div id="contentcolumn"><br>
<div class="articles">
<table width=95% align=center border=1 bordercolor=black 
cellspacing=0 cellpadding=5>
<tr><td class=articles bgcolor=#B8E0FE align=center cellpadding=5>
<h1>Gatherling Login</h1></td></tr>
<tr><td bgcolor=white><br>

<?php content(); ?>

<br></td></tr>
<tr><td align=center bgcolor=#DDDDDD cellpadding=15>
<h3><?php version_tagline(); ?>
</td></tr></table></div>
<br /><br /></div></div>
<?php include '../footer.ssi';?>



<?php
function content() {
	if(!isset($_POST['pw1'])) {regForm();}
	else {
		$code = doRegister();
		if($code == 0) {
			echo "Registration was successful. You may now ";
			echo "<a href=\"login.php\">Log In</a>.\n";
		} elseif ($code == -1) {
      echo "Passwords don't match. Please go back and try again.\n";
    } elseif ($code == -2) {
			echo "The specified username was not found in the database ";
			echo "Please contact jamuraa on the forums if you feel this is ";
			echo "an error.\n";
		} elseif ($code == -3) {
			echo "A password has already been created for this account.\n";}	
	}
}

function regForm() {
	echo "<form action=\"register.php\" method=\"post\">\n";
	echo "<table align=\"center\" style=\"border-width: 0px\">\n";
	echo "<tr><td><b>MTGO Username</td>\n";
	echo "<td><input type=\"text\" name=\"username\" value=\"\">\n";
	echo "</td></tr>\n";
	echo "<tr><td><b>Password</td>\n";
	echo "<td><input type=\"password\" name=\"pw1\" value=\"\">\n";
	echo "</td></tr>";
	echo "<tr><td><b>Confirm Password</td>\n";
	echo "<td><input type=\"password\" name=\"pw2\" value=\"\">\n";
	echo "</td></tr>\n";
	echo "<tr><td>&nbsp;</td></tr>\n";
	echo "<tr><td align=\"center\" colspan=\"2\">\n";
	echo "<input type=\"submit\" name=\"mode\" value=\"Register Account\">";
	echo "</td></tr></table></form>\n";
}

function doRegister() {
	$code = 0;
  if (strcmp($_POST['pw1'], $_POST['pw2']) != 0) {
    $code = -1;
  }
  $player = Player::findOrCreateByName($_POST['username']);
  if (!is_null($player->password)) { 
    $code = -3; 
  }   
  if ($code == 0) {
    $player->password = hash('sha256', $_POST['pw1']);
    $player->save();
	}
	return $code;
}

?>
