<?php

use App\Support\RoleHelper;

if (!function_exists('current_role')) {
    function current_role(): ?string
    {
        return RoleHelper::currentRole();
    }
}

if (!function_exists('has_role')) {
    function has_role(string ...$roles): bool
    {
        return RoleHelper::currentHasRole($roles);
    }
}
