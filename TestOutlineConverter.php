#!/usr/bin/php
<?php

/**
 * Take a plain text test outline and turn it into helpful test
 * cases formatted in a TestLink-friendly XML format.
 * 
 *
 * @author Andrew Leaf <andrew@clockwork.net>
 * 
**/

if ($argc != 3 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

This is a command line PHP script that requires an INFILE and an OUTFILE.

  Usage:
  <?php echo $argv[0]; ?> <INFILE> <OUTFILE>

  <INFILE> Source of your test cases
  
  <OUTFILE> The XML file, formatted for TestLink 
  
  Notes for test outline creation:
  	+	Starting a line with '+ ' creates a test case with the 
  		rest of the line
	-	'- ' Denotes a Test Step
	=	'= ' Denotes Expected Results.  If no associated step exists, 
		a blank step is created.
	//	Comment out a line with '//'
	# 	Comment out a line with '# '
	
	[no line prefix] 
		No Line prefix means it will get entered into the Test Case
		'Summary' in Test Link.  
		
	[blank lines]
		Blank lines will be ignored.
	
	
	Always follow the key with a space.  
	
	Example:
	
	+ TestCase
		Summary Information
			- TestStep
			= Expected Result
		// Ignore this line
		#  Ignore this line, too

<?php

// TODO - if a file exists, ask to rename OUTFILE or overwrite.

} 
else 
{

	// Initializing variables

	$lines        =  file($argv[1], FILE_SKIP_EMPTY_LINES);
	$fh           =  fopen($argv[2],"w");
	$close_cases  =  "</testcases>\n";
	$header       =  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<testcases>\n";
	$open_test    =  false;
	$open_step    =  false;
	$open_steps   =  false;
	$open_summary =  false;
	$i 	          =  0;
	
	// These are the unique identifiers that prefix each line and are handled by the switch.
	// Each line prefix does get thrown into the '$keyring' array.
	
	// Keywords are not yet implemented yet
	
	$new_test_case             =  '+ ';
	$new_test_step             =  '- ';
	$new_test_expected_result  =  '= ';
	$new_keyword               =  'KW';
	
	// If you want to comment, but don't like the default comment characters, add or change the values in $commentkeys
	$commentkeys               =  array( '# ', '//', '##', '#' );
	
	// $actionkeys is an array of prefixes that make the code do something, based on the switch use at the end of the script
	$actionkeys                =  array( $new_test_case, $new_test_step, $new_test_expected_result, $new_keyword );
	
	$keyring                   =  array_merge( $actionkeys, $commentkeys );

	
	// Investigate KEYWORD support
	// Add CDATA support
	
	// Defining functions
	
	function test_case_open_case()
	{
		// If a test case is currently open, close it.
		if (  $GLOBALS['open_test']  ==  true  ) {
		test_case_close_case();
		}
		
		$open_case              =  " <testcase name=\"" . $GLOBALS['input_line'] . "\">\n";
		$GLOBALS['open_test']   =  true;
		$GLOBALS['i']           =  0;
		
		fwrite( $GLOBALS['fh'], $open_case );
		
		// TestLink truncates test case names at 100 chars, and doesn't always insert the remainder of the testcase gracefully into the summary.
		$count                  =  strlen($GLOBALS['input_line']);
		if(  $count > 100  ) {
		    echo "Warning!!!  This test case name is greater than 100 chars and will be truncated: \n";
		    echo $GLOBALS['input_line'];
		    echo "\n\n";
		    // This dumps the line gracefully into Summary
		    test_case_summary();
		    
		}
		
		
		return;	
	}
	
	function test_case_summary()
	{
	    // Added support for HTML data in summary lines
		$summary_open  =  "    <summary>\n    <![CDATA[\n";
		
		if (  $GLOBALS['open_summary']  ==  false  ) {
		fwrite( $GLOBALS['fh'], $summary_open );
		$GLOBALS['open_summary'] = true;
		}
		
		$htmlsummary =  htmlspecialchars_decode( $GLOBALS['input_line'], ENT_NOQUOTES);
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
	
	function test_case_test_step()
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
		$step      =  "  <step>\n    <step_number>" . $GLOBALS['i'] . "</step_number>\n      <actions>" . $GLOBALS['input_line'] . "</actions>\n";
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
	
	function test_case_expected()
	{	
		
		// If there isn't a currently open step, create a dummy step.
		if (  $GLOBALS['open_step']  ==  false  ) {
		$GLOBALS['i']++;
		$dummy_step =  "  <step>\n    <step_number>" . $GLOBALS['i'] . "</step_number>\n      <actions></actions>\n";
		fwrite( $GLOBALS['fh'], $dummy_step );
		$GLOBALS['open_step']  =  true;
		}

		//  Write the expected result with the content $input_line
		$expected   =  "      <expectedresults>" . $GLOBALS['input_line'] . "</expectedresults>\n";
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
		
		$GLOBALS['i']++;
		fwrite( $GLOBALS['fh'], $close_case );
		$GLOBALS['open_test']   =  false;
		return;
	}

	// Start the program
	
	fwrite( $GLOBALS['fh'], $header );
	
	// Starting the evaluation of each line as it's input into the program.
	
	
	foreach ($lines as $line_num => $line) {
			$source_line               =  trim( $line );
			$input_line                =  htmlspecialchars( $source_line, ENT_QUOTES );
			$key                       =  substr( $input_line, 0, 2 );
			
			// Detecting content on in $input_line - helps with blank lines
			if ( $input_line ) {
			
				if ( in_array($key, $keyring )) {
				
				$input_line  =  trim( $input_line, "$key" );
					switch ( $key ) {
						case $new_test_case:
							test_case_summary_close();
							test_case_open_case();
							break;
						case $new_test_step:
							test_case_summary_close();
							test_case_test_step();
							break;
						case $new_test_expected_result:
							test_case_summary_close();
							test_case_expected();
							break;
						default:
							break;					
					}
				}
				else
				{
				test_case_summary();
				}
			}
		}
	
test_case_close_case();
fwrite( $GLOBALS['fh'], $close_cases );
fclose( $GLOBALS['fh'] );

echo "In theory, a TestLink-compatible XML file was created.  \nThis file is named $argv[2].\n";
}
?>