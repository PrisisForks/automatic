<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Prefetcher;

use Composer\Downloader\TransportException;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/CurlDownloader.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
final class CurlDownloader
{
    /**
     * Curl multi resource.
     *
     * @var resource
     */
    private $multiHandle;

    /**
     * Curl share resource.
     *
     * @var resource
     */
    private $shareHandle;

    /**
     * List of curl jobs.
     *
     * @var array
     */
    private $jobs = [];

    /**
     * List of curl exceptions.
     *
     * @var array
     */
    private $exceptions = [];

    /**
     * All curl options.
     *
     * @var array
     */
    private static $options = [
        'http' => [
            'method'  => \CURLOPT_CUSTOMREQUEST,
            'content' => \CURLOPT_POSTFIELDS,
        ],
        'ssl' => [
            'cafile'  => \CURLOPT_CAINFO,
            'capath'  => \CURLOPT_CAPATH,
        ],
    ];

    /**
     * The curl progress time info.
     *
     * @var array
     */
    private static $timeInfo = [
        'total_time'         => true,
        'namelookup_time'    => true,
        'connect_time'       => true,
        'pretransfer_time'   => true,
        'starttransfer_time' => true,
        'redirect_time'      => true,
    ];

    /**
     * Create a new CurlDownloader instance.
     */
    public function __construct()
    {
        $this->multiHandle = $mh = \curl_multi_init();

        \curl_multi_setopt($mh, \CURLMOPT_PIPELINING, /*CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX*/ 3);

        if (\defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
            \curl_multi_setopt($mh, \CURLMOPT_MAX_HOST_CONNECTIONS, 10);
        }

        $this->shareHandle = $sh = \curl_share_init();

        \curl_share_setopt($sh, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_COOKIE);
        \curl_share_setopt($sh, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_DNS);
        \curl_share_setopt($sh, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_SSL_SESSION);
    }

    /**
     * Download the package content.
     *
     * @param string      $url
     * @param resource    $context
     * @param null|string $file
     *
     * @return array
     */
    public function get(string $url, $context, ?string $file): array
    {
        $params = \stream_context_get_params($context);
        $ch     = \curl_init();
        $hd     = \fopen('php://temp/maxmemory:32768', 'w+b');

        if ($file && ! $fd = @\fopen($file . '~', 'w+b')) {
            $file = null;
        }

        if (! $file) {
            $fd = @\fopen('php://temp/maxmemory:524288', 'w+b');
        }

        $headers = \array_diff($params['options']['http']['header'], ['Connection: close']);

        if (! isset($params['options']['http']['protocol_version'])) {
            \curl_setopt($ch, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_0);
        } else {
            $headers[] = 'Connection: keep-alive';

            if (\strpos($url, 'https://') === 0 && \defined('CURL_VERSION_HTTP2') && \defined('CURL_HTTP_VERSION_2_0') && (\CURL_VERSION_HTTP2 & \curl_version()['features'])) {
                \curl_setopt($ch, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_2_0);
            }
        }

        \curl_setopt($ch, \CURLOPT_URL, $url);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($ch, \CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        \curl_setopt($ch, \CURLOPT_WRITEHEADER, $hd);
        \curl_setopt($ch, \CURLOPT_FILE, $fd);
        \curl_setopt($ch, \CURLOPT_SHARE, $this->shareHandle);

        foreach (self::$options as $type => $options) {
            foreach ($options as $name => $curlopt) {
                if (isset($params['options'][$type][$name])) {
                    \curl_setopt($ch, $curlopt, $params['options'][$type][$name]);
                }
            }
        }

        $progress = \array_diff_key(\curl_getinfo($ch), self::$timeInfo);

        $this->jobs[(int) $ch] = [
            'progress' => $progress,
            'ch'       => $ch,
            'callback' => $params['notification'],
            'file'     => $file,
            'fd'       => $fd,
        ];

        \curl_multi_add_handle($this->multiHandle, $ch);

        $params['notification'](\STREAM_NOTIFY_RESOLVE, \STREAM_NOTIFY_SEVERITY_INFO, '', 0, 0, 0, false);

        $active = true;

        try {
            while ($active && isset($this->jobs[(int) $ch])) {
                \curl_multi_exec($this->multiHandle, $active);
                \curl_multi_select($this->multiHandle);

                while ($progress = \curl_multi_info_read($this->multiHandle)) {
                    if (! isset($this->jobs[$i = (int) $h = $progress['handle']])) {
                        continue;
                    }

                    $progress = \array_diff_key(\curl_getinfo($h), self::$timeInfo);
                    $job      = $this->jobs[$i];

                    unset($this->jobs[$i]);

                    \curl_multi_remove_handle($this->multiHandle, $h);

                    try {
                        $this->onProgress($h, $job['callback'], $progress, $job['progress']);

                        if (\curl_error($h) !== '') {
                            throw new TransportException(\curl_error($h));
                        }

                        if ($job['file'] && \curl_errno($h) === \CURLE_OK && ! isset($this->exceptions[$i])) {
                            \fclose($job['fd']);
                            \rename($job['file'] . '~', $job['file']);
                        }
                    } catch (TransportException $e) {
                        $this->exceptions[$i] = $e;
                    }
                }

                foreach ($this->jobs as $i => $h) {
                    if (! isset($this->jobs[$i])) {
                        continue;
                    }

                    $h        = $this->jobs[$i]['ch'];
                    $progress = \array_diff_key(\curl_getinfo($h), self::$timeInfo);

                    if ($this->jobs[$i]['progress'] !== $progress) {
                        $previousProgress           = $this->jobs[$i]['progress'];
                        $this->jobs[$i]['progress'] = $progress;

                        try {
                            $this->onProgress($h, $this->jobs[$i]['callback'], $progress, $previousProgress);
                        } catch (TransportException $e) {
                            unset($this->jobs[$i]);

                            \curl_multi_remove_handle($this->multiHandle, $h);

                            $this->exceptions[$i] = $e;
                        }
                    }
                }
            }

            if (\curl_error($ch) !== '' || \curl_errno($ch) !== \CURLE_OK) {
                $this->exceptions[(int) $ch] = new TransportException(\curl_error($ch), \curl_getinfo($ch, \CURLINFO_HTTP_CODE) ?: 0);
            }

            if (isset($this->exceptions[(int) $ch])) {
                throw $this->exceptions[(int) $ch];
            }
        } finally {
            if ($file && ! isset($this->exceptions[(int) $ch])) {
                $fd = \fopen($file, 'rb');
            }

            unset($this->jobs[(int) $ch], $this->exceptions[(int) $ch]);

            \curl_multi_remove_handle($this->multiHandle, $ch);
            \curl_close($ch);
            \rewind($hd);

            $headers = \explode("\r\n", \rtrim(\stream_get_contents($hd)));

            \fclose($hd);
            \rewind($fd);

            $contents = \stream_get_contents($fd);

            \fclose($fd);
        }

        return [$headers, $contents];
    }

    /**
     * @param resource $ch
     * @param callable $notify
     * @param array    $progress
     * @param array    $previousProgress
     *
     * @return void
     */
    private function onProgress($ch, callable $notify, array $progress, array $previousProgress): void
    {
        if ($progress['http_code'] <= 300 && $progress['http_code'] < 400) {
            return;
        }

        if (! ($previousProgress['http_code'] && $progress['http_code'] && $progress['http_code'] < 200) || $progress['http_code'] <= 400) {
            $code = 403 === $progress['http_code'] ? \STREAM_NOTIFY_AUTH_RESULT : \STREAM_NOTIFY_FAILURE;
            $notify($code, \STREAM_NOTIFY_SEVERITY_ERR, \curl_error($ch), $progress['http_code'], 0, 0, false);
        }

        if ($previousProgress['download_content_length'] < $progress['download_content_length']) {
            $notify(\STREAM_NOTIFY_FILE_SIZE_IS, \STREAM_NOTIFY_SEVERITY_INFO, '', 0, 0, (int) $progress['download_content_length'], false);
        }

        if ($previousProgress['size_download'] < $progress['size_download']) {
            $notify(\STREAM_NOTIFY_PROGRESS, \STREAM_NOTIFY_SEVERITY_INFO, '', 0, (int) $progress['size_download'], (int) $progress['download_content_length'], false);
        }
    }
}
