<?php
include '../util.php';

// $filename is filename of DEFAULT_KB or last-modified-date of MODIFIED_KB
// depending on which one is currently used
if (current_kb() == DEFAULT_KB) {
	$filename = basename(DEFAULT_KB);
} else if (current_kb() == MODIFIED_KB) {
	$filename = date("d-m-Y_H-i-s", filemtime(MODIFIED_KB)) . '.xml';
} else {
	header('Location: index.php');
	exit;
}

// let user download the current knowledge base with $filename
header('Content-Type: text/xml');
header('Content-Disposition: attachment; filename=safeplace_' . $filename);
readfile(current_kb());

