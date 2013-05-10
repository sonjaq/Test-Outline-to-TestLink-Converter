#!/usr/bin/php
<?php

/**
 * Take a plain text test outline and turn it into helpful test
 * cases formatted in a TestLink-friendly XML format.
 * 
 *
 * @author Andrew Leaf <leaf@clockwork.net>
 * 
**/

if ($argc < 3 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

This is a command line PHP script that requires an INFILE and an OUTFILE.
Optionally, a third argument can be supplied for a Test Plan name.

  Usage:
  <?php echo $argv[0]; ?> <INFILE> <OUTFILE> <TESTPLAN NAME>

  <INFILE> Source of your test cases
  
  <OUTFILE> The XML file, formatted for TestLink 
  
  <TESTPLAN NAME>  This will be your test plan name.  If it is not set, you will be prompted for it.
  
  Read the README for more information on how to use your newly created TestLink-friendly XML file.
  
  Notes for test outline creation:
  	+	Starting a line with '+ ' creates a test case with the 
  		rest of the line
	-	'- ' Denotes a Test Step
	=	'= ' Denotes Expected Results.  If no associated step exists, 
		a blank step is created.
	//	Comment out a line with '//'
	# 	Comment out a line with '# '
	{ <SUITE NAME>  Opens a test suite or folder in TestLink.  
	                Each test plan must start with one of these. 
	                If there is no name, you will be prompted.
	}   Closes a test suite or folder in TestLink.  
	
	[no line prefix] 
		No Line prefix means it will get entered into the Test Case
		'Summary' in Test Link.  Basic HTML elements are safe here.
		
	[blank lines]
		Blank lines will be ignored.
	
	
	Always follow the key with a space.  
	
	Example:
	
    { TestSuite
    + TestCase
        Summary Information
            - TestStep
            = Expected Result
        // Ignore this line
        #  Ignore this line, too
    }
    
    After running the script, here's how to import into TestLink: 

    1. Log into your TestLink instance.  
    2. Proceed to "Edit Test Cases".  
    3. Select the folder level you'd like to import your test plan to.
    4. Select "Import Test Suite"
    5. Upload the generated XML file that was created
    6. Add your test cases to a test plan and execute
    
<?php

// TODO - if a file exists, ask to rename OUTFILE or overwrite.

} 
else 
{

	// Initializing variables

	$lines         =  file($argv[1], FILE_SKIP_EMPTY_LINES);
	$fh            =  fopen($argv[2],"w");
	$close_cases   =  "</testcases>\n";
	$header        =  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	$open_test     =  false;
	$open_step     =  false;
	$open_steps    =  false;
	$open_summary  =  false;
	$open_preconditions = false;
  $i 	           =  0;
	$open_suite    =  0;
	$testplan_name =  $argv[3];
	
	
	// These are the unique identifiers that prefix each line and are handled by the switch.
	// Each line prefix does get thrown into the '$keyring' array.
	
	// Keywords are not yet implemented yet
	
	$new_test_case             =  '+ ';
	$new_test_step             =  '- ';
	$new_test_expected_result  =  '= ';
	$new_keyword               =  'KW';
	$open_test_suite           =  '{ ';
	$close_test_suite          =  '} ';
	$open_suite_alt            =  '{';
	$close_suite_alt           =  '}';
  $open_test_preconditions   =  '~ ';  
	
	// If you want to comment, but don't like the default comment characters, add or change the values in $commentkeys
	$commentkeys               =  array( '# ', '//', '##', '#' );
	
	// $actionkeys is an array of prefixes that make the code do something, based on the switch use at the end of the script
	$actionkeys                =  array( 
	                            $new_test_case, 
	                            $new_test_step, 
	                            $new_test_expected_result, 
	                            $new_keyword, 
	                            $open_test_suite, 
	                            $close_test_suite, 
	                            $open_suite_alt, 
	                            $close_suite_alt,
	                            $open_test_preconditions
                              );
	
	$keyring                   =  array_merge( $actionkeys, $commentkeys );

	
	// Defining functions
	
	function test_suite_open(  $input  )
	{
	    
	    
		// If a test case is currently open, close it.
        if (  $GLOBALS['open_test']  ==  true  ) {
            test_case_close_case();
		}
		
		// Checking the length of $GLOBALS['input_line'] to make sure there is a name.
        $count                  =  strlen($input);
        
	
		// TestLink truncates test case and probably suite names that are long.  Requesting new test suite name if there is more than 30 chars or less than 1.
        while (  $count > 255 || $count < 1  ) {
             echo "Warning!!!  This test suite name is too short or too long... please provide a new one \n";
             echo $input;
             echo "\n\n";
             $input  =  trim(  fgets(  STDIN  )  );
             $input  =  htmlspecialchars(  $input, ENT_QUOTES  );
             $count  =  strlen(  $input  );
        }
            
        $input  =  htmlspecialchars( $input, ENT_QUOTES );
		
		
		
		// Increment the level of suites.  The number of 
	    $GLOBALS['open_suite']++;
		$open_suite              =  " <testsuite name=\"" . $input . "\">\n";
	
		fwrite( $GLOBALS['fh'], $open_suite );
			
		return;	
	}
	
	
	function test_case_open_case(  $input  )
	{
	    
        if ($GLOBALS['open_suite'] == 0 ) {
            
            
            // If this is an older test plan without any suites, and the first test case starts before seeing a suite, it will force a suite name.
            
            // We store the original line so it doesn't get overwritten.
            

            $input_line_holder       =  $input;
            $input                   =  "";
            test_suite_open($input);
            $input                   =  $input_line_holder;
        }	    

	
		// If a test case is currently open, close it.
		if (  $GLOBALS['open_test']  ==  true  ) {
            test_case_close_case();
		}
		
		$open_case              =  " <testcase name=\"" . $input . "\">\n";
		$GLOBALS['open_test']   =  true;
		
		// Setting the step incrementer to 0 just to be sure.
		$GLOBALS['i']           =  0;
		
		fwrite( $GLOBALS['fh'], $open_case );
		
		// TestLink truncates test case names at 100 chars, and doesn't always insert the remainder of the testcase gracefully into the summary.
		$count                  =  strlen( $input );
		if(  $count > 100  ) {
		    echo "Warning!!!  This test case name is greater than 100 chars and will be truncated: \n";
		    echo $input;
		    echo "\n\n";
		    // This dumps the line gracefully into Summary
		    test_case_summary($input);
		    
		}
		
		
		return;	
	}
	
	function test_case_summary(  $input  )
	{
        
        if (  $GLOBALS['open_test']  == false  ) {
            echo "You have a summary line outside of a test case. Here's the line:\n\n";
            echo $input;
            echo "\n\nYou probably meant to do something with this line.\n\n Exiting.......";
            exit;
        }
        
		$summary_open  =  "    <summary>\n    <![CDATA[\n";
		
		if (  $GLOBALS['open_summary']  ==  false  ) {
            fwrite( $GLOBALS['fh'], $summary_open );
            $GLOBALS['open_summary'] = true;
		}
		
		// Added support for HTML data in summary lines
	    // Support for quotes in HTML elements is non-existent in this release
	    // You likely aren't adding any class or div data, anyway, but basic HTML elements work 
		$htmlsummary =  htmlspecialchars_decode( $input, ENT_NOQUOTES);
		// check to see if it's summary step before doing anything with the line, methinks
		$summary     =  $htmlsummary . "\n";
		fwrite( $GLOBALS['fh'], $summary );
				
		return;
		
	}
	
	function test_case_summary_close()
	{
    $summary_close =  "    ]]>\n    </summary>  \n";
		
		if (  $GLOBALS['open_summary']  ==  true  ) {
            fwrite( $GLOBALS['fh'], $summary_close );
            $GLOBALS['open_summary']  = false;
		
		return;
		}
		
	}

  function test_case_preconditions( $input )
  {
    $preconditions_open = "   <preconditions>\n   <![CDATA[\n";
    if ( $GLOBALS['open_preconditions'] == false ) {
      fwrite( $GLOBALS['fh'], $preconditions_open );
      $GLOBALS['open_preconditions'] = true;
    }
    $htmlpreconditions  = htmlspecialchars_decode( $input, ENT_NOQUOTES );
    $preconditions      = $htmlpreconditions . "<br>\n";
    fwrite( $GLOBALS['fh'], $preconditions );
  }

  function test_case_preconditions_close()
  {
    $preconditions_close = "  ]]>\n   </preconditions>  \n";
    if ( $GLOBALS['open_preconditions'] == true ) {
      fwrite( $GLOBALS['fh'], $preconditions_close );
      $GLOBALS['open_preconditions'] = false;
    }
  }
	
	function test_case_test_step(  $input  )
	{
		// Check to see if steps are already open.  If they are not, open steps.
		if (  $GLOBALS['open_steps']  ==  false  ) {
            $steps_open  =  "  <steps>\n";
            fwrite($GLOBALS['fh'], $steps_open);
            $GLOBALS['open_steps']  = true;
		}
		
		if (  $GLOBALS['open_step']  ==  true  ) {
            test_case_close_step();
		}
		
		// Increment the step counter, open the step, then write the step number, 
		// and the content of the step as $input_line 
		$GLOBALS['i']++;
		$step      =  "  <step>\n    <step_number>" . $GLOBALS['i'] . "</step_number>\n      <actions><![CDATA[" . $input . "]]></actions>\n";
		fwrite( $GLOBALS['fh'], $step );
		$GLOBALS['open_step']  =  true;
		$GLOBALS['open_steps'] =  true;
		
		return;
	}
	
	function test_case_close_step()
	{
		//  If steps aren't open, get out
		if (  $GLOBALS['open_step'] ==  false  ) {
            return;
		}

		// Close the step
		$close_step   =  "    </step>\n";
		
		fwrite( $GLOBALS['fh'], $close_step );
		$GLOBALS['open_step']    =   false;
		return;
		
	}
	
	function test_case_expected(  $input  )
	{	
		
		// If there isn't a currently open step, create a dummy step.
		if (  $GLOBALS['open_step']  ==  false  ) {
            $GLOBALS['i']++;
            $dummy_step =  "  <step>\n    <step_number>" . $GLOBALS['i'] . "</step_number>\n      <actions></actions>\n";
            fwrite( $GLOBALS['fh'], $dummy_step );
            $GLOBALS['open_step']  =  true;
		}

		//  Write the expected result with the content $input_line
		$expected   =  "      <expectedresults><![CDATA[" . $input . "]]></expectedresults>\n";
		fwrite( $GLOBALS['fh'], $expected );
		test_case_close_step();
		return;
	}
	
	
	function test_case_close_steps()
	{
		// If there aren't open steps, bail.
		if (  $GLOBALS['open_steps']  ==  false  ) {
            return;
		}
		
		$closing_steps   =  "  </steps>\n";

		// If there is an open step, close it.
		if (  $GLOBALS['open_step'] == true  ) {
            test_case_close_step();
		}
		
		// Write the $closing_steps string, set openSteps to false, and reset the step counter.
		fwrite( $GLOBALS['fh'], $closing_steps );
		$GLOBALS['open_steps']    =  false;
		$GLOBALS['i']             =  0;
		return;
	}
	
	
	
	
	function test_case_close_case()
	{
		$close_case  =  " </testcase>\n";
		
		if (  $GLOBALS['open_steps'] == true  ) {
            test_case_close_step();
            test_case_close_steps();
		}
		
		test_case_summary_close();
		
		fwrite( $GLOBALS['fh'], $close_case );
		$GLOBALS['open_test']   =  false;
		return;
	}
	
	function test_suite_close()
	{
	    if (  $GLOBALS['open_test']  ==  true  ) {
            test_case_close_case();
		}
	    
		$close_suite  =  "</testsuite>\n";
		
		// Only close a suite if there is one open
		if ( $GLOBALS['open_suite'] > 0 ) {
		    $GLOBALS['open_suite']--;
		    fwrite( $GLOBALS['fh'], $close_suite );
		}
		return;
	}
	
	function test_suite_close_plan()
	{
        // For each open suite level, this loop will close.  This means, if you forget to close your suites, you'll be fine.
		while (  $GLOBALS['open_suite'] > 0  ) {
           	test_suite_close();
		}
		
		return;
	}

	// Start the program
	
	fwrite( $fh, $header );

    if (  $testplan_name  == ""  ) {       
        $input_line  =  $testplan_name;
        
        echo "\n\nALERT!!!! ALERT!!!\n\nLooks like you're going to need to enter a name for this test plan. \n\nTest plans are also referred to as 'suites' with TestLink'.\n\n";

        test_suite_open($input_line);        
    }            
	else {
		$input_line  =  htmlspecialchars( $testplan_name, ENT_QUOTES );
		$open_plan   =  " <testsuite name=\"" . $input_line . "\">\n";
	
    	$GLOBALS['open_suite']++;
  		fwrite( $fh, $open_plan );

	}
		
		
	// Starting the evaluation of each line as it's input into the program.
	
	
	foreach ($lines as $line_num => $line) {
			$source_line               =  trim( $line );
			$input_line                =  htmlspecialchars( $source_line, ENT_QUOTES );
			$key                       =  substr( $input_line, 0, 2 );
			// Detecting content on in $input_line - helps with blank lines
			if ( $input_line ) {
			    
			    
				if ( in_array( $key, $keyring )) {
				
				$input_line  =  trim( $input_line, "$key" );
					switch ( $key ) {
					    
					    case $open_test_suite:
					    case $open_suite_alt:
					        test_suite_open(  $input_line  );
					        break;
					    case $close_test_suite:
					    case $close_suite_alt:
					        test_suite_close();
					        break;
						case $new_test_case:
							test_case_summary_close();
							test_case_open_case(  $input_line  );
							break;
						case $open_test_preconditions:
              test_case_summary_close();
              test_case_preconditions( $input_line );
              break;
            case $new_test_step:
              test_case_summary_close();
							test_case_preconditions_close();
              test_case_test_step(  $input_line  );
							break;
						case $new_test_expected_result:
							test_case_summary_close();
							test_case_expected(  $input_line  );
							break;
						default:
							break;					
					}
				}
				else
				{
				test_case_summary(  $input_line  );
				}
			}
		}
	
test_suite_close_plan();

// fwrite( $GLOBALS['fh'], $close_cases );
fclose( $fh );

$xmllint = shell_exec("xmllint $argv[2]");
echo $xmllint;
echo "In theory, a TestLink-compatible XML file was created.  \nThis file is named $argv[2].\n";
echo "\n\nIf there were any errors, xmllint should have pointed them out above using ^s and some nebulous information.\n\n";
echo "If the contents of your XML testplan were displayed, this should import into TestLink without hassle.\n\nInstructions on how to import into TestLink can be found in the README or by running the --help command.\n\n";
}
?>
