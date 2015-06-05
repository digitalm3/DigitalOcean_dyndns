<?php

// The MIT License (MIT)
//
// Copyright (c) 2015 Christopher Ferris, Richard Dern
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

require 'vendor/autoload.php';

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\DigitalOceanV2;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//  *** NOTE:
// Get your Personal Access Token from DigitalOcean
// https://cloud.digitalocean.com/settings/applications
//
// Modify 'insert_personal_access_token' with your token
//
$adaptor = new BuzzAdapter('insert_personal_access_token');
$ocean   = new DigitalOceanV2($adaptor);
$log     = new Logger('DigitalOcean_DynDns');
$log->pushHandler(new StreamHandler('digitalocean.dyndns.log', Logger::INFO));

//
// *** NOTE: Domain update disabled, change to false when finished testing
//
$disable_update = true;

// FQDN provided by the URL (reguired)
$hostname = null;

// IP Address provided by the URL (optional)
$myip = null;

// Authorized domain's provided by DigitalOcean
$domainAPI      = $ocean->domain();
$handledDomains = [];

try {
    $handledDomains = $domainAPI->getAll();
} catch (Exception $ex) {
    $log->addError('DigitalOceanV2 exception caught in Domain getAll()');
    $log->addError($ex);
    die('dnserr');
}

if (array_key_exists('hostname', $_GET)) {
    $hostname = $_GET['hostname'];
}

if (array_key_exists('myip', $_GET)) {
    $myip = $_GET['myip'];
}

// If $myip is not provided attempt to find real IP
if (empty($myip)) {
    $log->addWarning('Response from URL, IP not provided attempting to detect');
    $myip = $_SERVER['REMOTE_ADDR'];
}

$ipFromDNS = '';

// Find which domain we are working on
if (empty($hostname)) {
    $log->addError('Invalid response from URL, hostname not provided');
    die('nohost');
}
// Append . to hostname for faster resolution
// Query DNS for matching $hostname
$result = @checkdnsrr($hostname . '.', 'A');
if (!$result) {
    $log->addError('Invalid response from DNS, can\'t resolve hostname');
    die('nohost');
} else {
    // append . to hostname for faster resolution since php dns doesn't have a timeout
    $records = @dns_get_record($hostname . '.', DNS_A);

    if (is_array($records)) {
        foreach ($records as $record) {
            if (array_key_exists('ip', $record)) {
                $ipFromDNS = $record['ip'];
            }
        }
    }
}

// If $myip matches the IP resolved by DNS no update needed
if ($myip === $ipFromDNS) {

    $data = array(
        'Host'=>$hostname,
        'New IP'=>$myip,
	'Old IP'=>$ipFromDNS,
    );

    $log->addInfo('Response from DNS matches IP provided, no update required', $data);
    die('nochg');
}

// If we get here, an update is required
// Find which domain we are working on
$currentDomain = null;
$recordName    = null;
$recordId      = null;

foreach ($handledDomains as $domain) {
    if (stristr($hostname, $domain->name)) {
        $currentDomain = $domain->name;

        $recordName = trim(str_replace($currentDomain, '', $hostname), '.');

        if (empty($recordName)) {
            $recordName = '@';
        }
        break;
    }
}

if (empty($currentDomain)) {
    $log->addError('Invalid response from DigitalOcean, domain not found');
    die('dnserr');
}

// Get the records for this domain from DigitalOcean
$recordsAPI    = $ocean->domainRecord();
$domainRecords = [];
try {
    $domainRecords = $recordsAPI->getAll($currentDomain);
} catch (Exception $ex) {
    $log->addError('DigitalOceanV2 exception caught in Domain Records getAll()');
    $log->addError($ex);
    die('dnserr');
}

if (!is_array($domainRecords)) {
    $log->addError('Invalid response from DigitalOcean, domain records not found');
    die('dnserr');
}

if (empty($domainRecords)) {

    $data = array(
        'Domain'=>$currentDomain,
    );

    $log->addError('Invalid response from DigitalOcean, domain records not found', $data);
    die('dnserr');
}

foreach ($domainRecords as $record) {
    // Have to check if name is present in all records
    if (!array_key_exists('name', $record)) {
        continue;
    }

    // We need the id too
    if (!array_key_exists('id', $record)) {
        continue;
    }

    if ($record->name !== $recordName) {
        continue;
    }

    $recordId = $record->id;
    break;
}

if (empty($recordId)) {
    $data = array(
        'Domain'=>$currentDomain,
        'Host'=>$recordName,
    );

    $log->addError('Invalid response from DigitalOcean, host records not found', $data);
    die('nohost');
}

// Submit record update to DigitalOcean
try {
    if (!$disable_update) {
        $recordsAPI->updateData($currentDomain, $recordId, $myip);
    }

    $data = array(
        'Domain'=>$currentDomain,
        'Host'=>$recordName,
        'New IP'=>$myip,
	'Old IP'=>$ipFromDNS,
    );

    $log->addInfo('Successful response from DigitalOcean, host record updated', $data);
    die('good');
} catch (Exception $ex) {
    $log->addError('DigitalOceanV2 Exception');
    $log->addError($ex);
    die('dnserr');
}
