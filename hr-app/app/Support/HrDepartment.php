<?php

namespace App\Support;

final class HrDepartment
{
    public static function matches(?string $department): bool
    {
        return in_array(strtolower(trim($department ?? '')), ['hr', 'hr department', 'human resources', 'human resources department'], true);
    }
}
