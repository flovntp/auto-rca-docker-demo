<?php

namespace App\Triage;

enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
