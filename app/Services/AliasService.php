<?php

namespace App\Services;

class AliasService
{
    /**
     * Create a new class instance.
     */
    public function handle($username)
    {
        $splitName = explode(" ", $username);
        if (count($splitName) >= 3) {
            $alias = $splitName[0][0] . $splitName[1][0] . $splitName[2][0];
        } elseif (count($splitName) == 2) {
            $alias = $splitName[0][0] . $splitName[1][0] . $splitName[1][1];
        } else {
            $alias = substr($username, 0, 3);
        }

        return $alias;
    }
}
