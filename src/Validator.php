<?php

namespace User\Crud;

class Validator
{
    public function validate(array $user, array $users): array
    {
        $errors = [];
        if (mb_strlen($user['nickname']) < 4 || mb_strlen($user['nickname']) > 20) {
            $errors['nickname'] = "User's nickname must be between 4 and 20 symbols";
        }
        if ($user['email'] === '') {
            $errors['email'] = "Can't be blank";
        }
        foreach ($users as $currentUser) {
            if ($user['email'] === $currentUser['email']) {
                $errors['email'] = "Some user already used this email. Email must be unique!";
            }
        }
        return $errors;
    }
}
