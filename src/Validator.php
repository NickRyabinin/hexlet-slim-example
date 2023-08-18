<?php

namespace User\Crud;

class Validator
{
    public function validate(array $user): array
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
}
