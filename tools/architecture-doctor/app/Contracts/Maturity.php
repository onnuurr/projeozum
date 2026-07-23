<?php

namespace ArchitectureDoctor\Contracts;

enum Maturity: string
{
    case Experimental = 'experimental';
    case Stable = 'stable';
    case Deprecated = 'deprecated';
}
