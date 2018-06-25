<?php

/**
 * This script translates C++ function signatures and comments into their Python
 * equivalent. It is not a transpiler or a converter, its sole purpose is to help
 * port documentation from C++ to Python projects.
 *
 * Copyright (c) 2018 Fernando Bevilacqua <dovyski@gmail.com>
 * Licensed under the MIT license, see the LICENSE file.
 */

function parseFunctionSignature($theSignature) {
    return array();
}

function parseCommentBlock($theCommentBlock) {
    $aCommentLines = explode("\n", $theCommentBlock);
    $aData = array('params' => array(), 'return' => array(), 'description' => '');

	if(count($aCommentLines) == 0) {
		return $aData;
	}

    // Parse comment lines individually
	foreach($aCommentLines as $aComment) {
        // Check for comments like "\param name desc"
		if(preg_match_all('/\\\\param ([a-zA-Z0-9]*) (.*)/m', $aComment, $aMatches) === FALSE) {
            echo 'Problem parsing file!' . "\n";
            exit(4);
		}

        if(count($aMatches[0]) > 0) {
            $aParamName = $aMatches[1][0];
            $aParamDesc = $aMatches[2][0];
            $aData['params'][$aParamName] = $aParamDesc;
            continue;
        }

        // Check for comments like "\return desc"
		if(preg_match_all('/\\\\return (.*)/m', $aComment, $aMatches) === FALSE) {
            echo 'Problem parsing file!' . "\n";
            exit(4);
		}

        if(count($aMatches[0]) > 0) {
            $aReturnDesc = $aMatches[1][0];
            $aData['return']['desc'] = $aReturnDesc;
            continue;
        }

        $aData['description'] .= $aComment . ' ';
	}

    return $aData;
}

function findFunctionsEntries($theInputFile) {
    $aFunctions = array();
    $aWithinCommentBlock = false;
    $aCommentBlock = '';

    while (($aLine = fgets($theInputFile)) !== false) {
    	if($aWithinCommentBlock) {
            // We are collecting lines within a comment block.
            $aCommentBlockEnded = stripos($aLine, '*/') !== false;

    		if($aCommentBlockEnded) {
                // We collected everything available in the current
                // comment block, it is time to wrap it up.
    			$aWithinCommentBlock = false;
    			$aFunctionSignature = fgets($theInputFile);
    			$aFunctions[] = array(
                    'comment' => $aCommentBlock,
                    'signature' => $aFunctionSignature
                );

                continue;
    		}

            $aCommentBlock .= $aLine;
    	}

        $aIsCommentBlockStart = stripos($aLine, '/**') !== false;

    	if($aIsCommentBlockStart) {
            // Found the start of a function comment block, so we are
            // within a comment block from now on. Let's start collecting lines.
    		$aWithinCommentBlock = true;
    		$aCommentBlock = '';
    		continue;
    	}
    }

    return $aFunctions;
}

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
    exit(3);
}

$aFunctions = findFunctionsEntries($aInputFile);

if(count($aFunctions) == 0) {
	echo 'No functions or comment blocks found. Is the input a C++ file?' . "\n";
	echo 'Input file: ' . $aInputPath . "\n";
	exit(4);
}

// Let's parse the function entries previously collected
foreach($aFunctions as $aKey => $aEntry) {
    $aFunctions[$aKey]['comment_data'] = parseCommentBlock($aEntry['comment']);
    $aFunctions[$aKey]['signature_data'] = parseFunctionSignature($aEntry['signature']);
}

$aStatus = fwrite($aOutputFile, print_r($aFunctions, true));
if($aStatus === false) {
    echo 'Unable to write to output file: ' . $aOutputFile . "\n";
    exit(5);
}

fclose($aInputFile);
fclose($aOutputFile);

echo 'Python transcribe finished successfuly!' . "\n";
echo 'Output file: ' . $aOutputPath . "\n";
exit(0);

?>
