<?php
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\FederatedInvitesService;
?>
<html>
    <head></head>
    <body>
        <ul>
            <?php foreach($_['providers'] as $meshProvider) {
                $inviteAcceptDialogEndpoint = trim($meshProvider['inviteAcceptDialog'], '/');
                $host =  $meshProvider['fqdn'];
                $token = $_['token'];
                $provider = $_['provider'];
                $link = "https://$host/$inviteAcceptDialogEndpoint?token=$token&provider=$provider"; ?>
                <li><a href="<?php p($link); ?>"><?php p($meshProvider['name']); ?></a></li>
            <?php } ?>
        </ul>
    </body>
</html>