<?php
/**
 * Fastest and smallest Github changelog generator ever.
 *
 * @author Markus Lervik <markus.lervik@necora.fi>
 * @copyright 2016 Necora Systems Oy
 * @license MIT
 */
require_once('vendor/autoload.php');

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;

$specs = new OptionCollection;
$specs->add('m|milestone:', 'The milestone' )
	->isa('String');
$specs->add('f|file:', 'The file to write the changelog to. Defaults to STDOUT')
	->isa('String');
$specs->add('p|prepend', 'Prepend the changelog to the file given with --file');
$specs->add('t|token:', 'GitHub access token')->isa('string');
$specs->add('u|user:', 'The user or GitHub organisation')->isa('string');
$specs->add('r|repository:', 'The repository name')->isa('string');
$specs->add('title:', 'The title of the changelog file. Defaults to "Change Log"')->isa('string');
$specs->add('h|help', 'Show this help');


echo "Fastest and smallest GitHub changelog generator ever version 0.1\n\n";

$parser = new OptionParser($specs);

try {
	$options = $parser->parse($argv);

	if ($options->help === true) {
		echo "Usage:\n\n";
		$printer = new ConsoleOptionPrinter();
		echo $printer->render($specs);
		exit(0);
	}
	if (strlen($options->user) == 0) {
		echo "You must specify a username or organisation name.\n"; exit(-1);
	}
	if (strlen($options->repository) == 0) {
		echo "You must specify a repository name.\n"; exit(-1);
	}
	if (strlen($options->token) == 0) {
		echo "**WARNING** You have not specified a GitHub Token.\nThis means the request rate is severely limited.\nTo generate a GitHub token, see https://help.github.com/articles/creating-an-access-token-for-command-line-use/\n\n";
	}
	if (strlen($options->milestone) == 0) {
		echo "You must specify the milestone.\n"; exit(-1);
	}

} catch (Exception $e) {
	echo "An unexpected error occured.\n\n";
	echo $e->getMessage() . "\n\n";
	echo $e->getTraceAsString();
	exit(-1);
}

$headers = [];

if (strlen($options->token) > 0) {
	$headers = ['Authorization' => "token " . $options->token];
}

$http = new GuzzleHttp\Client(['base_uri' => "https://api.github.com/", 'verify' => false, 'headers' => $headers]);

try {
	$response = $http->request('GET', "repos/" . $options->user . "/" . $options->repository . '/milestones?state=all');
} catch (\GuzzleHttp\Exception\RequestException $e) {
	if ($e->hasResponse()) {
		if ($e->getResponse()->getStatusCode() == 404) {
			echo "2The request resulted in a 404 Not Found response. Check the username, repository and possible access token."; exit(-1);
		}
	} else {
		echo "An unexpected error occurred:\n\n";
		throw $e;
	}
}

$data = json_decode($response->getBody());
$number = 0;
foreach ($data as $d) {
	if ($d->title == $options->milestone) {
		$number = $d->number;
		break;
	}
}
if ($number == 0) {
	echo "Could not find milestone \"" . $options->milestone . "\"\n\n"; exit(-1);
}

try {
	$response = $http->request('GET', "repos/" . $options->user . "/" . $options->repository . "/issues?milestone=$number&state=all");
} catch (\GuzzleHttp\Exception\RequestException $e) {
	if ($e->hasResponse()) {
		if ($e->getResponse()->getStatusCode() == 404) {
			echo "1The request resulted in a 404 Not Found response. Check the username, repository and possible access token."; exit(-1);
		}
	} else {
		echo "An unexpected error occurred:\n\n";
		throw $e;
	}
}

$issues = json_decode($response->getBody());
$bugs = array();
$enhancements = array();

foreach ($issues as $issue) {
	if (isset($issue->labels) && is_array($issue->labels)) {
		foreach ($issue->labels as $label) {
			if ($label->name == 'bug') {
				$bugs[$issue->number] = "- " . $issue->title . " [#" . $issue->number . "]";
			} elseif ($label->name == 'enhancement') {
				$enhancements[$issue->number] = "- " . $issue->title . " [#" . $issue->number . "]";
			} elseif ($label->name == 'skip-changelog') {
				if (isset($bugs[$issue->number])) unset($bugs[$issue->number]);
				if (isset($enhancements[$issue->number])) unset($enhancements[$issue->number]);
				continue 2;
			}
		}
	}
}

echo "##Version 3.6.0 (" . date('Y-m-d') . ")\n\n";
if (!empty($bugs)) {
	echo "**Fixed bugs**\n\n";
	foreach ($bugs as $bug) {
		echo $bug . "\n";
	}
	echo "\n";
}
if (!empty($enhancements)) {
	echo "**Implemented enhancements**\n\n";
	foreach ($enhancements as $enhancement) {
		echo $enhancement . "\n";
	}
	echo "\n";
}
