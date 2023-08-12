<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$users = json_decode(file_get_contents(__DIR__ . "/../database.json"), true) ?? [];

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
});

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    $callback = fn($user) => str_contains($user['nickname'], $term);
    $filteredUsers = array_filter($users, $callback);
    $params = ['filteredUsers' => $filteredUsers, 'term' => $term];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['id' => '', 'nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->post('/users', function ($request, $response) use ($users) {
    // $validator = new Validator();
    $user = $request->getParsedBodyParam('user');
    $user['id'] = count($users) + 1;
    $users[] = $user;
    file_put_contents(__DIR__ . "/../database.json", json_encode($users));
    return $response->withRedirect('/users', 302);
    // $errors = $validator->validate($user);
    // if (count($errors) === 0) {
    //     $repo->save($user);
    //     return $response->withRedirect('/users', 302);
    // }
    // $params = [
    //     'user' => $user,
    //     'errors' => $errors
    // ];
    // return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = htmlspecialchars($args['id']);
    return $response->write("Course id: {$id}");
});

$app->get('/users/{id}', function ($request, $response, $args) use ($users) {
    $params = ['id' => $args['id'], 'nickname' => $users[$args['id'] - 1]['nickname']];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->run();
