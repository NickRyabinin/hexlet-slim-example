<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$users = json_decode(file_get_contents(__DIR__ . "/../database.json"), true) ?? [];

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
})->setName('main');

$app->get('/user404', function ($request, $response) {
    return $response->write('This user does not exist!');
})->setName('404');

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    $callback = fn($user) => str_contains($user['nickname'], $term);
    $filteredUsers = array_filter($users, $callback);
    $messages = $this->get('flash')->getMessages();
    $params = ['filteredUsers' => $filteredUsers, 'term' => $term, 'flash' => $messages];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('showUsers');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['id' => '', 'nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('createUser');

$app->post('/users', function ($request, $response) use ($users, $router) {

    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    
    if (count($errors) === 0) {
        $user['id'] = count($users) + 1;
        $users[] = $user;
        file_put_contents(__DIR__ . "/../database.json", json_encode($users));
        $this->get('flash')->addMessage('success', 'User was added successfully');
        return $response->withRedirect($router->urlFor('showUsers'), 302);
    }

    $params = ['user' => $user, 'errors' => $errors];
    $errorResponse = $response->withStatus(422);

    return $this->get('renderer')->render($errorResponse, "users/new.phtml", $params);
})->setName('saveUser');

$app->get('/users/{id}', function ($request, $response, $args) use ($users, $router) {
    $id = $args['id'];
    if (search($id, $users)) {
        $params = ['id' => $id, 'nickname' => $users[$id - 1]['nickname']];
        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
    return $response->withRedirect($router->urlFor('404'), 302);
})->setName('showUser');

$app->run();

function search(int $id, array $users): bool
{
    foreach ($users as $user) {
        if ($user['id'] === $id) {
            return true;
        }
    }
    return false;
}

function validate(array $user): array
{
    $errors = [];
    if (mb_strlen($user['nickname']) < 4 || mb_strlen($user['nickname']) > 20) {
        $errors['nickname'] = "User's nickname must be between 4 and 20 symbols";
    }
    if ($user['email'] === '') {
        $errors['email'] = "Can't be blank";
    }
    return $errors;
}
