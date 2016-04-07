<?php

/**
 * Simple abstraction of Slack API
 * For all api methods, refer to https://api.slack.com/methods
 * @version  1.0.0
 */
class Slack {

    private $token;
    private $proxy;
    private $tor;
    private $endpoint = 'https://slack.com/api/<method>';
    private $timeout = 10;
    private $cache;

    /**
     * Create a new instance
     * @param string $token Your Slack api bearer token
     * @param string $proxy     eg: 172.31.31.31:3128
     * @param boolean $tor      see more https://www.torproject.org/docs/rpms.html.en
     */
    public function __construct($token, $proxy = false, $tor = false) {
        $this->token = $token;
        $this->proxy = $proxy;
        /**
         * if $tor is equal to true please check if you setup Tor
         * to run in 127.0.0.1 and in the 9050 port
         */
        $this->tor = $tor;
        $this->initCache();
    }

    private function initCache() {
        $this->cache = array();
        $this->cache['channel'] = array();
    }

    private function getCache($namespace, $key) {
        $result = false;
        if (isset($this->cache[$namespace]) && isset($this->cache[$namespace][$key])) {
            $result = $this->cache[$namespace][$key];
        }
        return $result;
    }

    private function setCache($namespace, $key, $value) {
        if (!isset($this->cache[$namespace])) {
            $this->cache[$namespace] = array();
        }
        $this->cache[$namespace][$key] = $value;
        return true;
    }

    /**
     * Calls an API method. You don't have to pass in the token, it will automatically be included.
     * @param  string  $method  The API method to call.
     * @param  array   $args    An associative array of arguments to pass to the API.
     * @return array           The response as an associative array, JSON-decoded.
     */
    public function call($method, $args = array()) {
        return $this->post($method, $args);
    }

    public function __call($name, $args) {
        if (strpos($name, '_')) {
            $name = str_replace('_', '.', $name);
            return $this->post($name, (isset($args[0]) ? $args[0] : array()));
        } else {
            return false;
        }
    }

    public function message($text, $channel = 'general') {
        return $this->chat_postMessage(array('as_user' => false, 'channel' => $this->getChannelId($channel), 'text' => $text));
    }

    /**
     * 
     * @param string $channelName
     * @return string
     */
    public function getChannelId($channelName) {
        $result = $this->getCache('channel', 'id' . $channelName);

        if (!$result) {
            $apiResult = $this->getCache('channel', 'list');

            if (!$apiResult) {
                $apiResult = $this->channels_list();
                $this->setCache('channel', 'list', $apiResult);
            }

            $channels = !empty($apiResult['channels']) ? $apiResult['channels'] : array();
            unset($apiResult);

            foreach ($channels as $channel) {
                if ($channel['name'] === $channelName) {
                    $result = $channel['id'];
                    $this->setCache('channel', 'id' . $channelName, $result);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Performs the underlying HTTP request.
     * @param  string  $method  The API method to call.
     * @param  array   $args    An associative array of arguments to pass to the API.
     * @return array           The response as an associative array, JSON-decoded.
     */
    private function post($method, $args = array()) {
        $url = str_replace('<method>', $method, $this->endpoint);
        $args['token'] = $this->token;
        $ch = $this->getCurl();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result ? json_decode($result, true) : false;
    }

    private function getCurl() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 3600);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);

        /**
         * The name of the outgoing network interface to use.
         * curl_setopt($ch, CURLOPT_INTERFACE, '127.0.0.1');
         */
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_ENCODING, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        /**
         * don't set this option!!!
         * curl_setopt($ch, CURLOPT_SSLVERSION, 3);
         */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($this->proxy) {
            if (false !== strpos($this->proxy, ':')) {
                list($host, $port) = explode(':', $this->proxy);
                curl_setopt($ch, CURLOPT_PROXY, $host);
                curl_setopt($ch, CURLOPT_PROXYPORT, $port);
            } else {
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            }
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            //curl_setopt($ch, CURLOPT_PROXYUSERPWD, $loginpassw);
        } elseif ($this->tor) {
            curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1');
            curl_setopt($ch, CURLOPT_PROXYPORT, 9050);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }

        return $ch;
    }

}
