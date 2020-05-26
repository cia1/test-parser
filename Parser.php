<?php

/**
 * Класс парсера.
 * Класс не выбрасывает исключений в случае ошибок получения данных, вместо этого возвращает false или null.
 */
class Parser
{

    protected const BASE_URL = 'https://www.myarena.ru/'; //Базовый URL

    private $_ch;
    private $_content;
    private $_auth = false;

    public function __construct()
    {
        //Подготовка CURL
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->_ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        $f = tmpfile();
        curl_setopt($this->_ch, CURLOPT_COOKIEFILE, $f);
        curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $f);
    }

    public function __destruct()
    {
        curl_close($this->_ch);
    }

    /**
     * Устанавливает прокси-сервер, через который будут осуществляться запросы
     * @param string      $ip       IP-адрес
     * @param int         $port     Номер порта
     * @param string|null $user     Логин для авторизации
     * @param string|null $password Пароль для авторизации
     */
    public function setProxy(string $ip, int $port, ?string $user = null, ?string $password = null): void
    {
        curl_setopt($this->_ch, CURLOPT_PROXY, $ip . ':' . $port);
        curl_setopt($this->_ch, CURLOPT_PROXYPORT, 8080);
        if ($user !== null && $password !== null) {
            curl_setopt($this->_ch, CURLOPT_PROXYUSERPWD, $user . ':' . $password);
            curl_setopt($this->_ch, CURLOPT_PROXYAUTH, CURLAUTH_NTLM);
        }
    }

    /**
     * Выполняет авторизацию.
     * @param string $login    Логин
     * @param string $password Пароль
     * @return bool true если удалось войти и false если не удалось
     */
    public function login(string $login, string $password): bool
    {
        $code = $this->get('ajax.php?action=checklogin&ulogin=' . $login . '&upassword=' . $password . '&gcode=&pcode=&ecode=&tcode=&capcode=');
        if ($code !== 200) {
            return false;
        }
        $response = json_decode($this->_content, true);
        //В случае ошибки ответ будет содержать три элемента, второй из которых указывает код ошибки
        if (count($response) !== 2) {
            return false;
        }
        $this->_auth = true;
        return true;
    }

    /**
     * Загружает и парсит историю входов
     * @return array|null NULL, если запрос выполнить не удалось
     * @throws BadMethodCallException
     */
    public function history(): ?array
    {
        if ($this->_auth === false) {
            throw new BadMethodCallException('You have to call LOGIN method before');
        }
        if ($this->get('home.php?m=profile&p=history') !== 200) {
            return null;
        }
        $data = [];
        $cnt = preg_match_all('~<tr.*?<nobr>([^<]+)<\/nobr>.*?<td[^>]+>([\d\.]+).*?<a.*?<a[^>]+>([^<]+)~is', $this->_content, $tmp);
        for ($i = 0; $i < $cnt; $i++) {
            $data[] = [
                'date' => $tmp[1][$i],
                'ip' => $tmp[2][$i],
                'info' => $tmp[3][$i]
            ];
        }
        return $data;
    }

    /**
     * Загружает и парсит основную информацию о пользователе
     * @return array|null NULL если запрос выполнить не удалось
     * @throws BadMethodCallException
     */
    public function info(): ?array
    {
        if ($this->_auth === false) {
            throw new BadMethodCallException('You have to call LOGIN method before');
        }
        if ($this->get('home.php?m=profile') !== 200) {
            return null;
        }
        $content = str_replace('&nbsp;', ' ', iconv('Windows-1251', 'UTF-8', $this->_content));
        if (preg_match('~Регистрация:\s+<b>([^<]+).*?Баланс:\s+<b>([^<]+).*?Номер договора:\s+<b>([^<]+)~is', $content, $tmp) != 1) {
            return null;
        }
        return [
            'balance' => (float)$tmp[2],
            'number' => $tmp[3],
            'reg_date' => $tmp[1]
        ];
    }

    /**
     * Выполняет GET-запрос
     * @param string $url URL относительно self::BASE_URL
     * @return int
     */
    protected function get(string $url): int
    {
        curl_setopt($this->_ch, CURLOPT_POST, 0);
        return $this->_exec(self::BASE_URL . $url);
    }

    private function _exec($url): int
    {
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        $this->_content = curl_exec($this->_ch);
        return curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
    }

}