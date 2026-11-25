<?php

namespace App\Enums;

enum OnDuplicateImport: string
{
    case Reject = 'reject';
    case Skip = 'skip';
    case Replace = 'replace';
}
