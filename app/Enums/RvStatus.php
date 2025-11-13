<?php

namespace App\Enums;

enum RvStatus: string
{
    case NEW = 'NEW';
    case USED = 'USED';
    case CLOSED = 'CLOSED';
}
