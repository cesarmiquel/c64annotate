<?php

require_once "parse_snapshot.php";

//$snap = new ViceSnapshot();
//$snap->loadFromFile("flappy.vsf");
//$snap->parse();

$snap = new C64Snapshot();
$snap->loadFromFile("flappy.vsf");
var_dump($snap->getVicInfo());
//var_dump($snap);
