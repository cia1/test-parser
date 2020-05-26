<?php
/**
 * Процесс получения данных от сервере реализован в классе Parser. Тут только вызов методов.
 * Все настройки содержатся в файле config.php
 */
require_once __DIR__ . '/Parser.php';
$config = include __DIR__ . '/config.php';

$parser = new Parser();
if ($config['proxyHost'] !== null && $config['proxyPort'] !== null) {
    $parser->setProxy($config['proxyHost'], $config['proxyPort'], $config['proxyUser'], $config['proxyPassword']);
}
if ($parser->login($config['login'], $config['password']) === false) {
    die('Войти не удалось');
}
$history = $parser->history();
if ($history === null) {
    die('Не удалось получить историю входов');
}
$info = $parser->info();
if ($info === null) {
    die('Не удалось получить информацию о пользователе');
}
$info['history_login'] = $history;
?>
<p>Login: <?= $config['login'] ?><br/>Password: <?= $config['password'] ?></p>
<p>Proxy: <?= $config['proxyHost'] ?? 'нет' ?></p>
<textarea style="width:100%;height:100%;"><?php var_export($info) ?></textarea>