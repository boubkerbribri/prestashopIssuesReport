<?php

include("run.php");
if (getenv('START_DATE') === false) {
    throw new Exception("START_DATE var is mandatory.");
}

//start_date stuff
$start_date = strtotime(getenv('START_DATE'));
if (date('Y-m-d', $start_date) != getenv('START_DATE')) {
    throw new Exception("Unrecognizable START_DATE format : ".getenv('START_DATE').PHP_EOL);
}

//end_date stuff
if (getenv('END_DATE') === false) {
    $end_date = strtotime("+28 day", $start_date);
    echo "Notice: END_DATE var not found, calculating to " . date('Y-m-d', $end_date) . PHP_EOL;
} else {
    $end_date = strtotime(getenv('END_DATE'));
}
$start_date_formatted = date('Y-m-d', $start_date);
$end_date_formatted = date('Y-m-d', $end_date);

//get all issues between these dates
$parameters = [
    'type:issue created:'.$start_date_formatted.'..'.$end_date_formatted.' sort:created-desc repo:prestashop/prestashop'
];
//$issues = $paginator->fetchAll($client->api('search'), 'issues', $parameters);

$filename = 'result.json';
$f = fopen($filename, 'r');
$issues = json_decode(fread($f, filesize($filename)), TRUE);

echo "Found " . count($issues) . " issues" . PHP_EOL;

$results = [
    'open' => 0,
    'closed' => 0,
    'regressions_TE' => 0,
    'regressions' => [],
    'duplicates' => []
];

if (count($issues) > 0) {
    foreach($issues as $issue) {
        if ($issue['state'] == 'open') {
            $results['open'] ++;
        } else {
            $results['closed'] ++;
        }
        $reg = false;
        $te = false;
        foreach($issue['labels'] as $label) {
            if ($label['name'] == 'Regression') {
                $reg = true;
            }
            if ($label['name'] == 'Detected by TE') {
                $te = true;
            }
            if ($label['name'] == 'Duplicate') {
                $results['duplicates'][] = $issue;
            }
        }
        if ($reg) {
            $results['regressions'][] = $issue;
        }
        if ($te) {
            $results['regressions_TE'] ++;
        }
    }
}

//let's get all source regressions
echo "Retrieving duplicates data...".PHP_EOL;
$duplicates = $results['duplicates'];
$pattern = '/Duplicates? of #(\d+)/i';
$original_issues = [];
$i = 1;
foreach($duplicates as $duplicate) {
    //get all comments
    $comments = $client->api('issue')->comments()->all('PrestaShop', 'PrestaShop', $duplicate['number']);
    foreach($comments as $comment) {
        preg_match($pattern, $comment['body'], $matches);
        if (isset($matches[1])) {
            //we got the original issue
            $original_issue_number = $matches[1];
            $original_issue = $client->api('issue')->show('PrestaShop', 'PrestaShop', $original_issue_number);

            if (!isset($original_issues[$original_issue_number])) {
                $original_issues[$original_issue_number] = $original_issue;
            }
            $original_issues[$original_issue_number]['regressions'][] = $duplicate;
            break;
        }
    }
    $i++;
}

echo "Open : ".$results['open'].PHP_EOL;
echo "Closed : ".$results['closed'].PHP_EOL;
echo "Duplicates : ".count($results['duplicates']).PHP_EOL;
echo "Regressions : ".count($results['regressions']).PHP_EOL;
echo "    Dont regressions TE : ".$results['regressions_TE'].PHP_EOL;
