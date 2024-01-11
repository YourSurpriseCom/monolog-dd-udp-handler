<?php

declare(strict_types=1);

namespace YourSurpriseCom\Monolog\DatadogUdp\Handler;

use DDTrace\Contracts\Span;
use DDTrace\GlobalTracer;
use DDTrace\Tag;
use LogicException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\MissingExtensionException;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Socket;

use function array_keys;
use function array_map;
use function dd_trace_peek_span_id;
use function DDTrace\logs_correlation_trace_id;
use function extension_loaded;
use function function_exists;
use function gethostname;
use function implode;
use function is_array;
use function is_scalar;
use function json_encode;
use function socket_close;
use function socket_create;
use function socket_sendto;
use function strlen;
use function strtolower;
use function substr;

use const AF_INET;
use const AF_UNIX;
use const IPPROTO_IP;
use const JSON_THROW_ON_ERROR;
use const SOCK_DGRAM;
use const SOL_UDP;

final class DataDogUdpHandler extends AbstractProcessingHandler
{
    private const MESSAGE_MAX_LENGTH = 65000;

    private Socket|null $socket = null;

    public function __construct(
        private readonly string $ip,
        private readonly int $port = 514,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        private readonly bool $tagContext = true,
    ) {
        if (! extension_loaded('sockets')) {
            throw new MissingExtensionException('The sockets extension is required to use the DataDogUdpHandler');
        }

        if (! function_exists('DDTrace\logs_correlation_trace_id')) {
            throw new MissingExtensionException('The datadog extension is required to use the DataDogUdpHandler');
        }

        if (! function_exists('dd_trace_peek_span_id')) {
            throw new MissingExtensionException('The datadog extension is required to use the DataDogUdpHandler');
        }

        parent::__construct($level, $bubble);
    }

    private function getSocket(): Socket
    {
        if ($this->socket !== null) {
            return $this->socket;
        }

        $domain   = AF_INET;
        $protocol = SOL_UDP;
        // Check if we are using unix sockets.
        if ($this->port === 0) {
            $domain   = AF_UNIX;
            $protocol = IPPROTO_IP;
        }

        $socket = socket_create($domain, SOCK_DGRAM, $protocol);
        if ($socket instanceof Socket) {
            $this->socket = $socket;

            return $socket;
        }

        throw new RuntimeException('The UdpSocket to ' . $this->ip . ':' . $this->port . ' could not be opened via socket_create');
    }

    protected function write(LogRecord $record): void
    {
        $activeSpan = GlobalTracer::get()->getActiveSpan();
        if (! $activeSpan) {
            throw new LogicException('No span is active.');
        }

        $scope   = GlobalTracer::get()->startActiveSpan('sendUdpMessage');
        $logSpan = GlobalTracer::get()->getActiveSpan();
        if (! $logSpan) {
            throw new LogicException('No log span is created.');
        }

        $logSpan->setTag(Tag::SERVICE_NAME, 'log-proxy');

        try {
            $this->doWrite($record, $activeSpan);
        } finally {
            $scope->close();
        }
    }

    private function doWrite(LogRecord $record, Span $span): void
    {
        if ($this->tagContext) {
            foreach ($record->context as $key => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $span->setTag('monolog-context.' . $key, $value);
            }
        }

        $tags = $span->getAllTags();

        if (is_array($tags)) {
            $tags = implode(', ', array_map(static function ($key, $value) {
                return $key . ':' . $value;
            }, array_keys($tags), $tags));
        }

        $log = [
            'service' => $span->getService(),
            'hostname' => gethostname(),
            'ddsource' => 'monologDataDogUdpHandler',
            'ddtags' => $tags,
            'message' => $record->datetime->format('Y-m-d\\TH:i:sP') . ' ' . $record->message,
            'level' => strtolower($record->level->getName()),
            'traceid' => logs_correlation_trace_id(),
            'spanid' => dd_trace_peek_span_id(),
        ];

        $chunk = json_encode($log, JSON_THROW_ON_ERROR);

        //Truncate message if total message is too large
        if (strlen($chunk) > self::MESSAGE_MAX_LENGTH) {
            $diff           = strlen($chunk) - self::MESSAGE_MAX_LENGTH;
            $log['message'] = substr($log['message'], 0, ($diff * -1));
            $chunk          = json_encode($log, JSON_THROW_ON_ERROR);
        }

        socket_sendto($this->getSocket(), $chunk, strlen($chunk), $flags = 0, $this->ip, $this->port);
    }

    public function close(): void
    {
        if (! ($this->socket instanceof Socket)) {
            return;
        }

        socket_close($this->socket);
        $this->socket = null;
    }
}
