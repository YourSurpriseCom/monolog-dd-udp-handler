# Monolog Datadog UDP Handler
![workflow](https://github.com/YourSurpriseCom/monolog-dd-udp-handler/actions/workflows/ci.yml/badge.svg)
[![Minimum PHP Version](https://img.shields.io/packagist/php-v/yoursurprisecom/monolog-dd-udp-handler.svg?maxAge=3600)](https://packagist.org/packages/yoursurprisecom/monolog-dd-udp-handler)
![phpstan](https://img.shields.io/badge/PHPStan-level%20Max-brightgreen.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Overview
This [Monolog](https://github.com/Seldaek/monolog) handler will send the messages to the [dd-log-proxy](https://github.com/YourSurpriseCom/dd-log-proxy) over UDP and the `dd-log-proxy` will send the messages over TCP to the Datadog API.

## Datadog Log Proxy
This handler is build to use in conjunction with the [Datadog Log Proxy](https://github.com/YourSurpriseCom/dd-log-proxy) written in go.
The proxy can be run as a container next to you application as a sidecar or as a standalone service.

### One proxy for all applications
As this handler gather the information from the current trace, only one proxy is needed for all your applications. 
The logs will be connected to the correct trace and service.

## Usage
Install the package using composer: `composer require yoursurprisecom/monolog-dd-udp-handler`

Simply create the handler with the proxy hostname and port, and push the handler into monolog:

```PHP
<?php

use Monolog\Logger;
use YourSurpriseCom\Monolog\DatadogUdp\Handler\DataDogUdpHandler;

$logger  = new Logger('my_logger');
$handler = new DataDogUdpHandler("<proxy host>",1053);
$logger->pushHandler($handler);

$logger->info("This log message is sent non blocking over UDP to Datadog!");
```

## Data flow
The data flow is as following:

```
+-----+       +-------------------------+             +--------------+             +-------------+ 
| PHP |  ==>  | Monolog Datadog Handler |  ==> (UDP)  | dd-log-proxy |  ==> (TCP)  | Datadog API |
+-----+       +-------------------------+             +--------------+             +-------------+ 
```

## Datadog
This handler uses the [Datadog PHP ddtrace package](https://github.com/DataDog/dd-trace-php) to gather information about the current span and trace.
Logs will be connected to the trace in the UI.

It will also create its own Datadog service `log-proxy` with it own spans.
  
