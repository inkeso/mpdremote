<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once ("inc/header.php");
    if (isset($_POST["token"])) $_SESSION['token'] = $_POST["token"];
    if (isset($_POST["usr"]) && isset($_POST["pw"]) && array_key_exists($_POST["usr"], $users)) {
        $hash = $users[$_POST["usr"]];
        if (password_verify($_POST["pw"], $hash)) {
            // store user in Session
            $_SESSION['usr'] = $_POST["usr"];
        }
    }
    header ("Location: index.php");
}
?>
<div id="songinfo">
<form method="POST" name="sform" action="login.php">
    <table style="margin:auto;">
        <tr><td colspan="2"><center><b>Dauernutzer-login</b></center></td></tr>
        <tr><td>Benutzername:</td><td><input type="user" size="20" name="usr" value=""/></td></tr>
        <tr><td>Passwort:</td><td><input type="password" size="20" name="pw" value=""/></td></tr>
        <tr><td colspan="2"><hr/></td></tr>
        <tr><td colspan="2"><center><b>Gastnutzer-login</b></center></td></tr>
        <tr><td>Fahrschein:</td><td><input type="text" size="20" name="token" value=""/></td></tr>
        <tr><td colspan="2"><hr/></td></tr>
        <tr><td colspan="2" align="center"><input type="submit" value="Login" name="wo"/></td></tr>
    </table>
</form>
</div>
