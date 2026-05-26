<?php

namespace App\Modules\Addons\Marketing\Repositories;

use App\Modules\Addons\Marketing\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CampaignRepository
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Campaign::withCount(['contents', 'leads', 'schedules'])
            ->when(isset($filters['status']) && $filters['status'], fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['search']) && $filters['search'], fn ($q) => $q->where('title', 'like', "%{$filters['search']}%"))
            ->when(isset($filters['channel']) && $filters['channel'], fn ($q) => $q->whereJsonContains('channel', $filters['channel']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findWithRelations(int $id): Campaign
    {
        return Campaign::with([
            'contents.approvedBy:id,name',
            'schedules',
            'leads',
            'approvedBy:id,name',
            'createdBy:id,name',
        ])->findOrFail($id);
    }

    public function activeCampaigns(): Collection
    {
        return Campaign::active()->with('contents')->get();
    }

    public function stats(): array
    {
        $counts = Campaign::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'total'            => array_sum($counts),
            'draft'            => $counts['draft'] ?? 0,
            'pending_approval' => $counts['pending_approval'] ?? 0,
            'approved'         => $counts['approved'] ?? 0,
            'active'           => $counts['active'] ?? 0,
            'paused'           => $counts['paused'] ?? 0,
            'finished'         => $counts['finished'] ?? 0,
        ];
    }
}
