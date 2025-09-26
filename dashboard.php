<?php
session_start();
require_once 'wo_data.json';

$wo_data = json_decode(file_get_contents('wo_data.json'), true);
$visible_wo = [];

foreach ($wo_data as $wo) {
    if ($_SESSION['role'] === 'entreprenor') {
        if (
            isset($wo['entreprenor_firma']) &&
            isset($_SESSION['entreprenor_firma']) &&
            $wo['entreprenor_firma'] === $_SESSION['entreprenor_firma']
        ) {
            $visible_wo[] = $wo;
        }
    } else {
        $visible_wo[] = $wo;
    }
}
?>
<!-- Display $visible_wo here -->