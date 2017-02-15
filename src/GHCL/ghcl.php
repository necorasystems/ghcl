<?php
/**
 * Fastest and smallest Github changelog generator ever.
 *
 * @author Markus Lervik <markus.lervik@necora.fi>
 * @copyright 2016 Necora Systems Oy
 * @license MIT
 */
require_once(__DIR__ . '/../../vendor/autoload.php');

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;

$specs = new OptionCollection;
$specs->add('m|milestone:', 'The milestone' )
	->isa('String');
$specs->add('f|file:', 'The file to write the changelog to. Defaults to STDOUT')
	->isa('String');
$specs->add('p|prepend', 'Prepend the changelog to the file given with --file [NOT IMPLEMENTED YET]');
$specs->add('t|token:', 'GitHub access token')->isa('string');
$specs->add('u|user:', 'The user or GitHub organisation')->isa('string');
$specs->add('r|repository:', 'The repository name')->isa('string');
$specs->add('title:', 'The title of the changelog file. Defaults to "Change Log"')->isa('string');
$specs->add('h|help', 'Show this help');


echo "Fastest and smallest GitHub changelog generator ever version 0.1\n\n";

$parser = new OptionParser($specs);

$output = STDOUT;

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

	if (strlen($options->file) > 0) {
		$output = $options->file;
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

$number = 0;

try {
	$response = $http->request('GET', "repos/" . $options->user . "/" . $options->repository . '/milestones?state=all');
	$data = json_decode($response->getBody());
	foreach ($data as $d) {
		if ($d->title == $options->milestone) {
			$number = $d->number;
			break;
		}
	}
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

if ($number == 0) {
	echo "Could not find milestone \"" . $options->milestone . "\"\n\n"; exit(-1);
}

$bugs = array();
$enhancements = array();
try {
	$response = $http->request('GET', "repos/" . $options->user . "/" . $options->repository . "/issues?milestone=$number&state=all");
	$issues = json_decode($response->getBody());


	foreach ($issues as $issue) {
		if (isset($issue->pull_request)) continue;
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

	if (is_array($response->getHeader('Link'))) {
		$l = $response->getHeader('Link');
		if (is_array($l) && !empty($l)) {
			$links = explode(',', $l[0]);
			foreach ($links as $link) {
				$parts = explode(';', $link);
				if (strpos($parts[0], '<') !== false && trim($parts[1]) == 'rel="next"') {
					$url = str_replace(array('<', '>'), '', $parts[0]);
					$response = $http->request('GET', $url);
					$issues = json_decode($response->getBody());

					foreach ($issues as $issue) {
						if (isset($issue->pull_request)) continue;
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
				}
			}
		}

	}
} catch (\GuzzleHttp\Exception\RequestException $e) {
	if ($e->hasResponse()) {
		if ($e->getResponse()->getStatusCode() == 404) {
			echo "The request resulted in a 404 Not Found response. Check the username, repository and possible access token."; exit(-1);
		}
	} else {
		echo "An unexpected error occurred:\n\n";
		throw $e;
	}
}

if (is_string($output)) {
	$output = fopen($output, 'w');
	if (!is_resource($output)) {
		die('Could not open ' . $output . ' for writing');
	}
}

fputs($output, "##Version " . $options->milestone . " (" . date('Y-m-d') . ")\n\n");
if (!empty($bugs)) {
	fputs($output, "**Fixed bugs**\n\n");
	foreach ($bugs as $bug) {
		fputs($output, $bug . "\n");
	}
	fputs($output, "\n");
}
if (!empty($enhancements)) {
	fputs($output, "**Implemented enhancements**\n\n");
	foreach ($enhancements as $enhancement) {
		fputs($output, $enhancement . "\n");
	}
	fputs($output, "\n");
}
