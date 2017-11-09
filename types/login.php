<?php

$SessionUser = QUI::getUserBySession();
$isAuth      = $SessionUser->getId();

$Engine->assign(array(
    'SessionUser' => QUI::getUserBySession()
));
