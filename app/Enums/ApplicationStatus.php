<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case LO_REVIEW = 'lo_review';
    case ABM_REVIEW = 'abm_review';
    case BM_REVIEW = 'bm_review';
    case CREDIT_REVIEW = 'credit_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case DISBURSEMENT_PENDING = 'disbursement_pending';
    case DISBURSED = 'disbursed';
}
