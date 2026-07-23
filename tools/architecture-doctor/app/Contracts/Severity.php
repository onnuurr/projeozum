<?php

namespace ArchitectureDoctor\Contracts;

enum Severity: string
{
    case Notice = 'notice';
    case Warning = 'warning';
    case Critical = 'critical';
}
