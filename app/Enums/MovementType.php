<?php

namespace App\Enums;

enum MovementType: string
{
    case Receipt = 'receipt';
    case Sale = 'sale';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
}
