<?php

namespace App\Enums;

enum LoanStatus: string
{
    case PENDING_DISBURSEMENT = 'pending_disbursement';
    case ACTIVE = 'active';
    case OVERDUE = 'overdue';
    case SETTLED = 'settled';
    case REFINANCED = 'refinanced';
    case WRITTEN_OFF = 'written_off';
    case CANCELLED = 'cancelled';
}
