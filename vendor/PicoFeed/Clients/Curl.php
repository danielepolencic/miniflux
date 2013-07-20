<?php

namespace PicoFeed\Clients;

use \PicoFeed\Logging;

class Curl extends \PicoFeed\Client
{
    private $body = '';
    private $body_length = 0;
    private $headers = array();
    private $headers_counter = 0;


    public function readBody($ch, $buffer)
    {
        $length = strlen($buffer);
        $this->body_length += $length;

        if ($this->body_length > $this->max_body_size) return -1;

        $this->body .= $buffer;

        return $length;
    }


    public function readHeaders($ch, $buffer)
    {
        $length = strlen($buffer);

        if ($buffer === "\r\n") {
            $this->headers_counter++;
        }
        else {

            if (! isset($this->headers[$this->headers_counter])) {
                $this->headers[$this->headers_counter] = '';
            }

            $this->headers[$this->headers_counter] .= $buffer;
        }

        return $length;
    }


    public function doRequest()
    {
        $request_headers = array('Connection: close');

        if ($this->etag) $request_headers[] = 'If-None-Match: '.$this->etag;
        if ($this->last_modified) $request_headers[] = 'If-Modified-Since: '.$this->last_modified;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->max_redirects);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For auto-signed certificates...
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'readBody'));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeaders'));
        curl_exec($ch);

        Logging::log('cURL total time: '.curl_getinfo($ch, CURLINFO_TOTAL_TIME));
        Logging::log('cURL dns lookup time: '.curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME));
        Logging::log('cURL connect time: '.curl_getinfo($ch, CURLINFO_CONNECT_TIME));
        Logging::log('cURL speed download: '.curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD));

        if (curl_errno($ch)) {

            Logging::log('cURL error: '.curl_error($ch));

            curl_close($ch);
            return false;
        }

        curl_close($ch);

        list($status, $headers) = $this->parseHeaders(explode("\r\n", $this->headers[$this->headers_counter - 1]));

        return array(
            'status' => $status,
            'body' => $this->body,
            'headers' => $headers
        );
    }
}