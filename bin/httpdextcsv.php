#!/usr/bin/env php
<?php

require_once('ezc/Base/base.php');
spl_autoload_register( array( 'ezcBase', 'autoload' ) );

$input = new ezcConsoleInput();

$helpOption = $input->registerOption( new ezcConsoleOption( 'h', 'help' ) );
$helpOption->isHelpOption = true;
$helpOption->shorthelp = $helpOption->longhelp = 'show extended help';

$input->argumentDefinition = new ezcConsoleArguments();

$input->argumentDefinition[0] = new ezcConsoleArgument( 'server-status-file' );
$input->argumentDefinition[0]->shorthelp = 'server-status file';
$input->argumentDefinition[0]->longhelp = 'the file with Apache server-status, can be a local or a remote path if PHP\'s fopen wrappers are enabled';
$input->argumentDefinition[0]->mandatory = true;

$output = new ezcConsoleOutput();
$output->formats->error->color = 'red';
$output->formats->error->target = ezcConsoleOutput::TARGET_STDERR;

$progDescription = 'Exports the extended server-status information from an Apache HTTP server to a CSV file. See http://httpd.apache.org/docs/current/mod/mod_status.html for background information.';

try {
  $input->process();
}
catch ( ezcConsoleException $e )
{
  die( $e->getMessage() . str_repeat(PHP_EOL, 2) . $input->getHelpText( $progDescription, 80) );
}

if ( $helpOption->value === true )
{
  echo $input->getHelpText( $progDescription, 80, true );
}
else
{
  $source = $input->argumentDefinition["server-status-file"]->value;

  libxml_use_internal_errors(true);
  $doc = new DOMDocument('1.0', 'utf-8');

  // TODO add HTTP authentication options and work with a stream wrapper
  $success = $doc->loadHTMLFile($source);

  if (!$success) {
    $output->outputLine( 'unable to parse the given file, it does not seem to be a valid extended server-status file', 'error' );
    exit(1);
  }

  // TODO: add -o/--output option to output directly to a file
  // otherwise just print to STDOUT like it does now
  $f = STDOUT;

  $tableElement = $doc->getElementsByTagName('table')->item(0);

  if (!$tableElement) {
    $output->outputLine( 'unable to parse the given file, it does not seem to be a valid extended server-status file', 'error' );
    exit(1);
  }

  foreach ($tableElement->childNodes as $trElement) {
    $row = array();

    foreach ($trElement->childNodes as $tdElement) {
      // sometimes there are line endings inside the td, so need to trim
      $row[] = trim($tdElement->textContent);
    }

    $ip = $row[10];

    if ($ip == '127.0.0.1') {
      $name = 'localhost';
    }
    else {
      if ( preg_match( "/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/", $ip ) ) {
        $name = gethostbyaddr($ip);
      }
      else {
        // probably a host name already, so leave it alone
        $name = $ip;
      }
    }

    $row[] = $name;

    fputcsv($f, $row);
  }
}
