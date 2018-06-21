<?php

/**
 * This script translates C++ function signatures and comments into their Python
 * equivalent. It is not a transpiler or a converter, its sole purpose is to help
 * port documentation from C++ to Python projects.
 *
 * Copyright (c) 2018 Fernando Bevilacqua <dovyski@gmail.com>
 * Licensed under the MIT license, see the LICENSE file.
 */

$aOptions = array(
    "input:",
    "output:",
    "help"
);

$aArgs = getopt("h", $aOptions);

if(isset($aArgs['h']) || isset($aArgs['help']) || $argc <= 2) {
     echo "Usage: \n";
     echo " dopy [options]\n\n";
     echo "Options:\n";
     echo " --input=<path>    Path to the C++ file that will be translated.\n";
     echo " --output=<path>   Path to the file where the translated Python\n";
	 echo "                   code will be written. If the file does not exist,\n";
     echo "                   it will be created.\n";
     echo " --help, -h        Show this help.\n";
     echo "\n";
     exit(1);
}

$aInputPath = isset($aArgs['input']) ? $aArgs['input'] : '';
$aOutputPath = isset($aArgs['output']) ? $aArgs['output'] : '';

$aInputFile = fopen($aInputPath, 'r');
$aOutputFile = fopen($aOutputPath, 'w');

if(!$aInputFile) {
    echo 'Unable to open input file: ' . $aInputPath . "\n";
    exit(2);
}

if(!$aOutputFile) {
    echo 'Unable to open output file: ' . $aOutputFile . "\n";
    exit(2);
}

$aFunctions = array();
$aWithinCommentBlock = false;
$aCommentBlock = '';

while (($aLine = fgets($aInputFile)) !== false) {
	if($aWithinCommentBlock) {
		$aCommentBlock .= $aLine;

		if(stripos($aLine, '*/') !== false) {
			$aWithinCommentBlock = false;
			$aFunctionLine = fgets($aInputFile);
			$aFunctions[] = array('comment' => $aCommentBlock, 'signature' => $aFunctionLine);
		}
	}

	if(stripos($aLine, '/**') !== false) {
		$aWithinCommentBlock = true;
		$aCommentBlock = '';
		continue;
	}
}

if(count($aFunctions) == 0) {
	echo 'No functions or comment blocks found. Is the input a C++ file?' . "\n";
	echo 'Input file: ' . $aInputPath . "\n";
	exit(3);
}

foreach($aFunctions as $aEntry) {
	$aCommentLines = explode("\n", $aEntry['comment']);

	if(count($aCommentLines) == 0) {
		continue;
	}

	foreach($aCommentLines as $aComment) {
		if(preg_match_all('/\\\\param ([a-zA-Z0-9]*) (.*)/m', $aComment, $aMatches) == FALSE) {
			var_dump($aMatches);
		}
	}

	$aStatus = fwrite($aOutputFile, print_r($aEntry, true));
	if($aStatus === false) {
		echo 'Unable to write to output file: ' . $aOutputFile . "\n";
		exit(4);
	}
}

fclose($aInputFile);
fclose($aOutputFile);

echo 'Python transcribe finished successfuly!' . "\n";
echo 'Output file: ' . $aOutputPath . "\n";
exit(0);

?>
