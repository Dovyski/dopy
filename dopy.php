<?php

/**
 * This script translates C++ function signatures and comments into their Python
 * equivalent. It is not a transpiler or a converter, its sole purpose is to help
 * port documentation from C++ to Python projects.
 *
 * Copyright (c) 2018 Fernando Bevilacqua <dovyski@gmail.com>
 * Licensed under the MIT license, see the LICENSE file.
 */

function outputData($theOutputPath, $theData, $theInputPath) {
    $aOutputFile = fopen($theOutputPath, 'w');

    if(!$aOutputFile) {
        echo 'Unable to open output file: ' . $aOutputFile . "\n";
        exit(3);
    }

    foreach($theData as $aEntry) {
        $aOut = '';
        $aOut .= 'def ' . $aEntry['signature_data']['name'] . '(';

        foreach($aEntry['signature_data']['params'] as $aParam) {
            $aOut .= $aParam['name'] . (!empty($aParam['default_value']) ? ' = ' . $aParam['default_value'] : '') . ', ';
        }

        $aOut = substr($aOut, 0, strlen($aOut) - 2);
        $aOut .= '):' . "\n";
        $aOut .= "\t" . '"""' . "\n";
        $aOut .= "\t" . implode("\n\t", $aEntry['comment_data']['description']) . "\n";

        if(count($aEntry['signature_data']['params']) > 0) {
            $aOut .= "\t" . 'Parameters' . "\n";
            $aOut .= "\t" . '----------' . "\n";

            foreach($aEntry['signature_data']['params'] as $aParam) {
                if(!isset($aEntry['comment_data']['params'][$aParam['name']])) {
                    echo 'WARN: ' . basename($theInputPath) . ' (line '.$aEntry['line'].') param "'.$aParam['name'].'" not documented.' . "\n";
                    continue;
                }
                $aOut .= "\t" . $aParam['name'] . ': ' . $aParam['type'] . "\n";
                $aOut .= "\t\t" . $aEntry['comment_data']['params'][$aParam['name']] . "\n";
            }
        }

        $aOut .= "\t" . '"""' . "\n\n";

        $aStatus = fwrite($aOutputFile, $aOut);

        if($aStatus === false) {
            echo 'Unable to write to output file: ' . $theOutputPath . "\n";
            exit(4);
        }
    }



    fclose($aOutputFile);
}

function parseFunctionSignature($theSignature) {
    $aRet = array('name' => '', 'return' => '', 'params' => array());
    $aMainParts = explode('(', $theSignature);

    $aReturnParts = explode(' ', $aMainParts[0]);
    $aItems = 0;

    // Parse return type and function name
    foreach($aReturnParts as $aPart) {
        if(!empty($aPart) && $aItems == 0) {
            $aRet['return'] = $aPart;

        } else if(!empty($aPart) && $aItems == 1) {
            $aRet['name'] = $aPart;
        }
        $aItems++;
    }

    // Parse list of arguments
    $aParamsString = str_replace(')', '', $aMainParts[1]);
    $aParamParts = explode(',', $aParamsString);

    if(count($aParamParts) > 0) {
        foreach($aParamParts as $aParamEntry) {
            $aEntry = array('name' => '', 'type' => '', 'default_value' => '');
            $aTypeNameString = $aParamEntry;

            $aValueParts = explode('=', $aTypeNameString);
            if(count($aValueParts) > 1) {
                $aEntry['default_value'] = trim($aValueParts[1]);
                $aTypeNameString = trim($aValueParts[0]);
            }

            $aTypeNameParts = explode(' ', $aTypeNameString);
            for($i = 0; $i < count($aTypeNameParts) - 1; $i++) {
                $aEntry['type'] .= trim($aTypeNameParts[$i]) . ' ';
            }
            $aEntry['name'] = trim($aTypeNameParts[$i]);

            // Check for special types, i.e. arrays/pointers
            $aIsArray = stripos($aEntry['name'], '[') !== false;
            $aIsPointer = stripos($aEntry['name'], '*') !== false || stripos($aEntry['name'], '&') !== false;

            if($aIsArray || $aIsPointer) {
                $aEntry['name'] = str_replace(array('[', ']', '*', '&'), '', $aEntry['name']);
                $aEntry['type'] .= $aIsArray ? '[]' : '*';
            }

            if($aEntry['name'] != '...' && $aEntry['name'] != '') {
                $aRet['params'][] = $aEntry;
            }
        }
    }

    return $aRet;
}

function parseCommentBlock($theCommentBlock) {
    $aCommentLines = explode("\n", $theCommentBlock);
    $aData = array('params' => array(), 'return' => '', 'description' => array());

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
            $aData['return'] = $aMatches[1][0];
            continue;
        }

        // TODO: check for \sa

        $aData['description'][] = trim($aComment);
	}

    return $aData;
}

function findFunctionsEntries($theInputFile) {
    $aFunctions = array();
    $aWithinCommentBlock = false;
    $aCommentBlock = '';
    $aLineCount = 0;

    while (($aLine = fgets($theInputFile)) !== false) {
        $aLineCount++;

    	if($aWithinCommentBlock) {
            // We are collecting lines within a comment block.
            $aCommentBlockEnded = stripos($aLine, '*/') !== false;

    		if($aCommentBlockEnded) {
                // We collected everything available in the current
                // comment block, it is time to wrap it up.
    			$aWithinCommentBlock = false;
    			$aFunctionSignature = fgets($theInputFile);
                $aLineCount++;

                if(stripos($aFunctionSignature, 'template <') !== false) {
                    // Template declaration, not funciton signature.
                    $aFunctionSignature = fgets($theInputFile);
                    $aLineCount++;
                }

    			$aFunctions[] = array(
                    'comment' => trim($aCommentBlock),
                    'signature' => str_replace(';', '', trim($aFunctionSignature)),
                    'line' => $aLineCount
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

if(!$aInputFile) {
    echo 'Unable to open input file: ' . $aInputPath . "\n";
    exit(2);
}

$aFunctions = findFunctionsEntries($aInputFile);
fclose($aInputFile);

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

outputData($aOutputPath, $aFunctions, $aInputPath);

echo 'Python transcribe finished successfuly!' . "\n";
echo 'Output file: ' . $aOutputPath . "\n";
exit(0);

?>
