<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Future = 'future';
}
