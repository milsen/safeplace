<meta name=viewport content='width=700'>
<!--used for  alignment. This information should be placed elsewhere.-->
<?php

include '../util.php';
include '../solver.php';
include '../reader.php';
include '../checklist.php';

date_default_timezone_set('Europe/Amsterdam');

$errors = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	switch ($_POST['action']) {
	case 'run':
		header('Location: webfrontend.php');
		exit;
		break;

	case 'analyse':
		header('Location: analyse.php');
		exit;
		break;

	case 'download':
		header('Location: download.php');
		exit;
		break;

	case 'upload':
		process_file($_FILES['knowledgebase'], $errors);
		break;

	case 'reset':
		if (file_exists(MODIFIED_KB)) {
			unlink(MODIFIED_KB);
		}
		break;
	}
}

/**
 * Check uploaded file for various errors (wrong filename, upload error, wrong
 * knowledge base syntax, error while moving file on server) and describe each
 * error in $errors using the keys 'number', 'message', 'file' and 'line'.
 * If no errors occurr, the uploaded file is moved to MODIFIED_KB.
 *
 * @param array $kb_file Knowledge base file that is uploaded.
 * @param array &$errors Filled with a description of all errors:
 *		'number'  => error level
 *		'message' => description of error
 *		'file'    => file in which error occurred
 *		'line'    => line in which error occurred
 * @return void
 */
function process_file($kb_file, array &$errors = array())
{
	// create default values of error level, filename and line number
	// to describe errors which occurr while uploading and not while linting
	$number = E_USER_WARNING;
	$file = "File to be uploaded";
	$line = 0;
	$number_file_line = array("number","file","line");

	// check filename
	if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.xml$/i', $kb_file['name'])) {
		$message = "The file name includes characters that cannot be " .
			"processed correctly, or the file is not an XML-file.";
		$errors[] = (object) compact("message",$number_file_line);
		return;
	}

	// check for upload errors: file size too large, connection error etc.
	if ($kb_file['error'] != 0) {
		$message = "An error occurred while uploading the file.";
		$errors[] = (object) compact("message",$number_file_line);
		return;
	}

	// check knowledge base syntax
	$reader = new KnowledgeBaseReader();
	$errors = $reader->lint($kb_file['tmp_name']);

	if (count($errors) > 0) {
		return;
	}

	// save uploaded file in MODIFIED_KB, overwrite it if it already exists
	if (!move_uploaded_file($kb_file['tmp_name'], MODIFIED_KB)) {
		$message = "It was not possible to transfer the knowledge " .
			"base to its correct destination on the server.";
		$errors[] = (object) compact("message",$number_file_line);
	}
}

$template = new Template('templates/single.phtml');
$template->errors = $errors;

echo $template->render();
