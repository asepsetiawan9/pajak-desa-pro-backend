<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait HandlesCollectorDusunFilter
{
    /**
     * Resolves the effective dusun filter string based on the logged in collector user.
     */
    protected function getEffectiveDusunFilter(Request $request): ?string
    {
        $user = $request->user();
        $inputDusun = $request->query('dusun') ?? $request->input('dusun');

        if (!$user) {
            return $inputDusun;
        }

        if ($user->role === 'KOLEKTOR' && !empty($user->dusun_akses) && strtoupper($user->dusun_akses) !== 'ALL') {
            $assignedDusuns = array_map('trim', explode(',', $user->dusun_akses));

            if (empty($inputDusun) || strtoupper($inputDusun) === 'ALL') {
                return implode(',', $assignedDusuns);
            }

            $requestedDusuns = array_map('trim', explode(',', $inputDusun));
            $allowed = array_values(array_filter($requestedDusuns, function ($d) use ($assignedDusuns) {
                return in_array(strtoupper($d), array_map('strtoupper', $assignedDusuns));
            }));

            if (empty($allowed)) {
                return implode(',', $assignedDusuns);
            }

            return implode(',', $allowed);
        }

        return $inputDusun;
    }

    /**
     * Checks if a specific dusun is allowed for the authenticated user.
     */
    protected function isDusunAllowedForUser($user, ?string $dusunName): bool
    {
        if (!$user || $user->role !== 'KOLEKTOR') {
            return true;
        }

        if (empty($user->dusun_akses) || strtoupper($user->dusun_akses) === 'ALL') {
            return true;
        }

        if (empty($dusunName)) {
            return false;
        }

        $assignedDusuns = array_map('strtoupper', array_map('trim', explode(',', $user->dusun_akses)));
        return in_array(strtoupper(trim($dusunName)), $assignedDusuns);
    }
}
