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
require 'digitalocean.config.php';

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\DigitalOceanV2;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$ipFilterOptions = array('flags'=>FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE);

$adaptor = new BuzzAdapter($oceanAuthKey);
$ocean   = new DigitalOceanV2($adaptor);
$log     = new Logger('DigitalOcean_DynDns');

try {
	$log->pushHandler(new StreamHandler($logFile, Logger::INFO));
} catch (Exception $ex) {
	echo("Logger exception caught in $log->pushHandler(), unable to create Log file");
	die("dnserr\r\n");
}

// FQDN provided by the URL (reguired)
$hostname = null;

// IP Address provided by the URL (optional)
$myip = null;

// Authorized domain's provided by DigitalOcean
try {
	$domainAPI      = $ocean->domain();
} catch (Exception $ex) {
	$data = array(
		'Exception'=>$ex,
	);
	$log->addError("DigitalOceanV2 exception caught in $ocean->domain()", $data);
	die("dnserr\r\n");
}

$handledDomains = [];

try {
	$handledDomains = $domainAPI->getAll();
} catch (Exception $ex) {
	$data = array(
		'Exception'=>$ex,
	);
	$log->addError("DigitalOceanV2 exception caught in $domainAPI->getAll()", $data);
	die("dnserr\r\n");
}

if (array_key_exists('hostname', $_GET)) {
	$hostname = $_GET['hostname'];
}

if (array_key_exists('myip', $_GET)) {
	$myip = $_GET['myip'];
}

// If $myip is not provided attempt to find real IP
if (empty($myip)) {
	$myip = $_SERVER['REMOTE_ADDR'];
	$data = array(
		'URI'=>$_SERVER[REQUEST_URI],
		'Detected IP'=>$myip,
	);

	$log->addInfo("Successful response from URL, IP not provided attempting to detect", $data);

}

// Make sure the IP Address is IPv4 and isn't Private or Reserved
$isIpValid = filter_var($myip, FILTER_VALIDATE_IP, $ipFilterOptions);
if ($isIpValid == false) {
	$data = array(
		'URI'=>$_SERVER[REQUEST_URI],
		'IP'=>$myip,
	);

	$log->addError("Invalid IP Address, is not IPv4 or is Private or Reserved", $data);
	die("dnserr\r\n");
	continue;
}

$ipFromDNS = '';

// Dyn.com API allows multiple hosts in hostname so convert to an array
$hostArray = explode(',', $hostname);
if (!is_array($hostArray)) {
	$data = array(
		'URI'=>$_SERVER[REQUEST_URI],
		'Hostname'=>$hostname,
	);

	$log->addWarning('Invalid response from URL, could not detect hostname', $data);
	die("nohost\r\n");
}

// Loop hosts array to process each update
foreach ($hostArray as $currentHost) {
	// Find which domain we are working on
	if (empty($currentHost)) {
		$data = array(
			'URI'=>$_SERVER[REQUEST_URI],
		);

		$log->addError("Invalid response from URL, hostname not provided", $data);
		echo("nohost\r\n");
		continue;
	}

	// Append . to hostname for faster resolution to help prevent query timeout
	// Query DNS for matching $hostname
	$result = @checkdnsrr($currentHost . '.', 'A');
	if (!$result) {
		$data=array(
			'Host'=>$currentHost,
		);

		$log->addError("Invalid response from DNS, can't resolve hostname", $data);

		echo("nohost\r\n");
		continue;
	} else {
		// Append . to hostname for faster resolution to help prevent query timeout
		$records = @dns_get_record($currentHost . '.', DNS_A);

		if (is_array($records)) {
			foreach ($records as $record) {
				if (array_key_exists('ip', $record)) {
					$ipFromDNS = $record['ip'];
					break;
				}
			}
		}
	}

	// If $myip matches the IP resolved by DNS no update needed
	if ($myip === $ipFromDNS) {
		$data = array(
			'Host'=>$currentHost,
			'New IP'=>$myip,
			'Old IP'=>$ipFromDNS,
		);

		$log->addInfo("Successful match between DNS and IP Address, no update required", $data);
		echo("nochg\r\n");
		continue;
	}

	// If we get here, an update is required
	// Find which domain we are working on
	$currentDomain = null;
	$recordName    = null;
	$recordId      = null;

	foreach ($handledDomains as $domain) {
		if (stristr($currentHost, $domain->name)) {
			$currentDomain = $domain->name;

			$recordName = trim(str_replace($currentDomain, '', $currentHost), '.');

			if (empty($recordName)) {
				$recordName = '@';
				break;
			}
			
			continue;
		}
	}

	if (empty($currentDomain)) {
		$log->addError("Invalid response from DigitalOcean, domain not found");
		echo("dnserr\r\n");
		continue;
	}

	// Get the records for this domain from DigitalOcean
	$recordsAPI    = $ocean->domainRecord();
	$domainRecords = [];
	try {
		$domainRecords = $recordsAPI->getAll($currentDomain);
	} catch (Exception $ex) {
		$data = array(
			'Exception'=>$ex,
		);

		$log->addError("DigitalOceanV2 exception caught in $recordsAPI->getAll()", $data);
		echo("dnserr\r\n");
		continue;
	}

	if (!is_array($domainRecords)) {
		$log->addError("Invalid response from DigitalOcean, domain records not found");
		echo("dnserr\r\n");
		continue;
	}

	if (empty($domainRecords)) {
		$data = array(
			'Domain'=>$currentDomain,
		);

		$log->addError("Invalid response from DigitalOcean, domain records not found", $data);
		echo("dnserr\r\n");
		continue;
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
		
		if (!array_key_exists('type', $record)) {
			continue;
		}

		if ($record->type !== "A") {
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

		$log->addError("Invalid response from DigitalOcean, host records not found", $data);
		echo("nohost\r\n");
		continue;
	}

	// Submit record update to DigitalOcean
	try {
		if (!$disableDigitalOceanUpdate) {
			$recordsAPI->updateData($currentDomain, $recordId, $myip);
		}

		$data = array(
			'Domain'=>$currentDomain,
			'Host'=>$recordName,
			'New IP'=>$myip,
			'Old IP'=>$ipFromDNS,
			'Update Disabled'=>$disableDigitalOceanUpdate ? 'true' : 'false',
		);

		$log->addInfo("Successful response from DigitalOcean, host record updated", $data);
		echo("good\r\n");
		continue;
	} catch (Exception $ex) {
		$data = array(
			'Exception'=>$ex,
		);

		$log->addError("DigitalOceanV2 Exception", $data);
		echo("dnserr\r\n");
	}
}

