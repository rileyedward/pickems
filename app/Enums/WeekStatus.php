<?php

namespace App\Enums;

enum WeekStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
