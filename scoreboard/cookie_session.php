<?php

class CookieSession implements SessionHandlerInterface
{
    private $secret, $cookie_name, $persist_time;

    public function __construct($secret=null, $cookie_name='session', $persist_time=86400)
    {
        if(!$secret || strlen($secret) === 0) {
            die('empty session secret');
        }
        $this->secret = $secret;
        $this->cookie_name = $cookie_name;
        $this->persist_time = $persist_time;
    }
    public function open($savePath, $sessionName) { return true; }
    public function close() { return true; }

    public function read($id)
    {
        list($str_session, $sig, $data, $expire_at) = explode('.', $_COOKIE[$this->cookie_name], 4);
        if ($str_session !== 'SESSION' ||
            hash_hmac('sha512', $data.'---'.$expire_at, SECRET . md5($id), true) !== base64_decode($sig) ||
            time() > (int)$expire_at) {
            return '';
        }
        return (string)base64_decode($data);
    }

    public function write($id, $data)
    {
        $expire_at = (string)(time() + $this->persist_time);
        $data = base64_encode($data);
        $sig = base64_encode(hash_hmac('sha512', $data.'---'.$expire_at, SECRET . md5($id), true));
        $session = "SESSION.{$sig}.{$data}.{$expire_at}";
        setcookie($this->cookie_name, $session, time() + $this->persist_time, '/');
        return true;
    }

    public function destroy($id)
    {
        setcookie($this->cookie_name, '', 0, '/');
        return true;
    }

    public function gc($maxlifetime) { return true; }
}
