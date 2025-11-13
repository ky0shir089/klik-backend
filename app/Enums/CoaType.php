<?php

namespace App\Enums;

enum CoaType: string
{
    case ASSET = 'ASSET';
    case EQUITY = 'EQUITY';
    case EXPENSE = 'EXPENSE';
    case LIABILITIES = 'LIABILITIES';
    case REVENUE = 'REVENUE';
}
