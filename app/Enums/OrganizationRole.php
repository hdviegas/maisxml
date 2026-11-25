<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Admin = 'admin';
    case Importer = 'importer';
    case Viewer = 'viewer';
    case Accountant = 'accountant';
}
