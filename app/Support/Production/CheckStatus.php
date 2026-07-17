<?php

namespace App\Support\Production;

enum CheckStatus: string
{
    case Pass = 'PASS';
    case Warning = 'WARNING';
    case Fail = 'FAIL';
}
