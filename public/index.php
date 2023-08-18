<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;
use User\Crud\Database;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);
$router = $app->getRouteCollector()->getRouteParser();

$database = new Database();
// $users = $database->loadUsers();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
})->setName('main');

$app->get('/user404', function ($request, $response) {
    return $response->write('This user does not exist!');
})->setName('404');

$app->get('/users', function ($request, $response) use ($database) {
    $term = $request->getQueryParam('term');
    $users = $database->loadUsers();
    $callback = fn($user) => str_contains($user['nickname'], $term);
    $allFilteredUsers = array_filter($users, $callback);
    $messages = $this->get('flash')->getMessages();
    $page = $request->getQueryParam('page', 1);
    $per = 5;
    $offset = ($page - 1) * $per;
    $filteredUsers = array_slice($allFilteredUsers, $offset, $per);
    $params = ['filteredUsers' => $filteredUsers, 'term' => $term, 'flash' => $messages, 'page' => $page];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('showUsers');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['id' => '', 'nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('createUser');

$app->post('/users', function ($request, $response) use ($database, $router) {
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    $users = $database->loadUsers();

    if (count($errors) === 0) {
        $user['id'] = ($users[count($users) - 1]['id'] ?? 0) + 1;
        $users[] = $user;
        $database->saveUsers($users);
        $this->get('flash')->addMessage('success', 'User was added successfully');
        return $response->withRedirect($router->urlFor('showUsers'), 302);
    }

    $params = ['user' => $user, 'errors' => $errors];
    $errorResponse = $response->withStatus(422);

    return $this->get('renderer')->render($errorResponse, "users/new.phtml", $params);
})->setName('saveUser');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($database, $router) {
    $id = $args['id'];
    $editableUser = $database->findUser($id);
    $users = $database->loadUsers();
    $data = $request->getParsedBodyParam('user');

    $errors = validate($data);

    if (count($errors) === 0) {
        $editableUserKey = array_search($editableUser, $users);
        $editableUser['nickname'] = $data['nickname'];
        $editableUser['email'] = $data['email'];
        $users[$editableUserKey] = $editableUser;
        $database->saveUsers($users);
        $this->get('flash')->addMessage('success', 'User has been updated');
        return $response->withRedirect($router->urlFor('showUsers'), 302);
    }

    $params = ['user' => $editableUser,'errors' => $errors];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, array $args) use ($database, $router) {
    $id = $args['id'];
    $deletableUser = $database->findUser($id);
    $users = $database->loadUsers();
    unset($users[array_search($deletableUser, $users)]);
    $database->saveUsers([...$users]);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect($router->urlFor('showUsers'));
});

$app->get('/users/{id}', function ($request, $response, $args) use ($database, $router) {
    $id = $args['id'];
    $user = $database->findUser($id);
    if ($user) {
        $params = ['user' => $user];
        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
    return $response->withRedirect($router->urlFor('404'), 302);
})->setName('showUser');

$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($database, $router) {
    $id = $args['id'];
    $user = $database->findUser($id);
    if ($user) {
        $params = ['user' => $user, 'errors' => []];
        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }
    return $response->withRedirect($router->urlFor('404'), 302);
})->setName('editUser');

$app->run();

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
