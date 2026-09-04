<?php

namespace App\Services;

use App\Models\GreenAchievement;
use App\Models\GreenInventory;
use App\Models\GreenRewardTransaction;
use App\Models\GreenShopItem;
use App\Models\GreenTree;
use App\Models\GreenWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Wishlist;

class GreenRewardService
{
    private const ACTIVITY_POINTS = [
        'daily_login' => 10,
        'save_attraction' => 20,
        'save_itinerary' => 50,
        'export_itinerary' => 30,
        'export_guidance' => 30,
        'share_itinerary' => 30,
    ];

    public function wallet(User $user): GreenWallet
    {
        return GreenWallet::firstOrCreate(['user_id' => $user->user_id]);
    }

    public function award(User $user, string $activity, int $points, array $metadata = [], bool $checkAchievements = true): bool
    {
        if ($points <= 0) return false;
        return DB::transaction(function () use ($user, $activity, $points, $metadata, $checkAchievements) {
            $wallet = GreenWallet::firstOrCreate(['user_id' => $user->user_id]);
            $wallet = GreenWallet::where('user_id', $user->user_id)->lockForUpdate()->first();
            $wallet->increment('points', $points);
            GreenRewardTransaction::create([
                'user_id' => $user->user_id, 'activity' => $activity, 'amount' => $points,
                'transaction_type' => 'earning', 'metadata' => $metadata,
            ]);
            if ($checkAchievements) {
                $this->checkAchievements($user);
            }
            return true;
        });
    }

    public function queueActivity(User $user, string $activity): bool
    {
        if (!isset(self::ACTIVITY_POINTS[$activity])) return false;

        $pendingRewards = session('pending_reward_activities', []);

        if ($activity === 'daily_login') {
            $alreadyClaimed = GreenRewardTransaction::where('user_id', $user->user_id)
                ->where('activity', 'daily_login')
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if ($alreadyClaimed || ($pendingRewards[$activity] ?? 0) > 0) return false;
        }

        $pendingRewards[$activity] = ($pendingRewards[$activity] ?? 0) + 1;
        session()->put('pending_reward_activities', $pendingRewards);

        return true;
    }

    public function collectQueuedActivity(User $user, string $activity): bool
    {
        if ($activity === 'tree_milestone') {
            return $this->collectTreeMilestone($user);
        }

        if (!isset(self::ACTIVITY_POINTS[$activity])) return false;

        $pendingRewards = session('pending_reward_activities', []);
        $pendingCount = $pendingRewards[$activity] ?? 0;

        if ($pendingCount < 1) return false;

        $pendingRewards[$activity] = $pendingCount - 1;
        if ($pendingRewards[$activity] === 0) unset($pendingRewards[$activity]);
        session()->put('pending_reward_activities', $pendingRewards);

        return $this->award($user, $activity, self::ACTIVITY_POINTS[$activity]);
    }

    public function syncTreeMilestones(User $user, GreenTree $tree): void
    {
        $claimedLevels = GreenRewardTransaction::where('user_id', $user->user_id)
            ->where('activity', 'tree_milestone')
            ->get()
            ->map(fn (GreenRewardTransaction $transaction): int =>
                (int) ($transaction->metadata['milestone_level'] ?? 0))
            ->filter()
            ->all();
        $pendingLevels = array_map(
            'intval',
            session('pending_tree_milestone_levels', [])
        );

        for ($level = 5; $level <= $tree->level; $level += 5) {
            if (!in_array($level, $claimedLevels, true)
                && !in_array($level, $pendingLevels, true)) {
                $pendingLevels[] = $level;
            }
        }

        sort($pendingLevels);
        session()->put('pending_tree_milestone_levels', $pendingLevels);

        $pendingRewards = session('pending_reward_activities', []);
        if ($pendingLevels === []) {
            unset($pendingRewards['tree_milestone']);
        } else {
            $pendingRewards['tree_milestone'] = count($pendingLevels);
        }
        session()->put('pending_reward_activities', $pendingRewards);
    }

    private function collectTreeMilestone(User $user): bool
    {
        $tree = GreenTree::where('user_id', $user->user_id)->first();
        if (!$tree) return false;

        $this->syncTreeMilestones($user, $tree);
        $pendingLevels = session('pending_tree_milestone_levels', []);
        $level = (int) array_shift($pendingLevels);

        if ($level < 5 || $level % 5 !== 0) return false;

        session()->put('pending_tree_milestone_levels', array_values($pendingLevels));
        $pendingRewards = session('pending_reward_activities', []);
        if ($pendingLevels === []) {
            unset($pendingRewards['tree_milestone']);
        } else {
            $pendingRewards['tree_milestone'] = count($pendingLevels);
        }
        session()->put('pending_reward_activities', $pendingRewards);

        $points = min(150, (int) (($level / 5) * 50));

        return $this->award(
            $user,
            'tree_milestone',
            $points,
            ['milestone_level' => $level]
        );
    }

    public function collectAchievement(User $user, GreenAchievement $achievement): bool
    {
        return DB::transaction(function () use ($user, $achievement) {
            $record = DB::table('user_green_achievements')
                ->where('user_id', $user->user_id)
                ->where('achievement_id', $achievement->id)
                ->whereNull('claimed_at')
                ->lockForUpdate()
                ->first();

            if (!$record) {
                return false;
            }

            DB::table('user_green_achievements')->where('user_id', $user->user_id)->where('achievement_id', $achievement->id)->update(['claimed_at' => now()]);
            $this->award($user, 'achievement:' . $achievement->slug, $achievement->reward_points, ['achievement_id' => $achievement->id], false);
            return true;
        });
    }

    public function purchase(User $user, GreenShopItem $item): GreenInventory
    {
        return DB::transaction(function () use ($user, $item) {
            $wallet = GreenWallet::where('user_id', $user->user_id)->lockForUpdate()->firstOrCreate(['user_id' => $user->user_id]);
            if ($wallet->points < $item->price) abort(422, __('messages.reward_points_insufficient'));
            $wallet->decrement('points', $item->price);
            GreenRewardTransaction::create(['user_id' => $user->user_id, 'activity' => 'fertilizer_purchase', 'amount' => $item->price, 'transaction_type' => 'spending', 'metadata' => ['item_id' => $item->id]]);
            $inventory = GreenInventory::firstOrCreate(['user_id' => $user->user_id, 'shop_item_id' => $item->id]);
            $inventory->increment('quantity');
            return $inventory->fresh('item');
        });
    }

    public function fertilize(User $user, GreenInventory $inventory): void
    {
        DB::transaction(function () use ($user, $inventory) {
            $inventory = GreenInventory::whereKey($inventory->id)->where('user_id', $user->user_id)->lockForUpdate()->firstOrFail();
            if ($inventory->quantity < 1) abort(422, __('misc.rewards.no_inventory'));
            $item = $inventory->item;
            $tree = GreenTree::firstOrCreate(
                ['user_id' => $user->user_id],
                ['level' => 0, 'experience' => 0, 'growth_stage' => 'Seed']
            );
            $inventory->decrement('quantity');
            $tree->experience += $item->exp_value;
            while ($tree->experience >= ($tree->level + 1) * 100) $tree->level++;
            $tree->growth_stage = $tree->level >= 10 ? 'Tall trunk' : ($tree->level >= 5 ? 'Mature tree' : ($tree->level >= 3 ? 'Growing tree' : ($tree->level >= 2 ? 'Small tree' : 'Seed')));
            $tree->save();
            $this->checkAchievements($user, $tree);
        });
    }

    public function checkAchievements(User $user, ?GreenTree $tree = null): void
    {
        $tree = $tree ?: GreenTree::where('user_id', $user->user_id)->first();
        $points = $this->wallet($user)->points;
        $dailyLogins = GreenRewardTransaction::where('user_id', $user->user_id)->where('activity', 'daily_login')->exists();
        $itinerary = GreenRewardTransaction::where('user_id', $user->user_id)
            ->whereIn('activity', ['save_itinerary', 'generate_itinerary'])
            ->exists();
        $wishlists = Wishlist::where('user_id', $user->user_id)->count();
        $values = ['points' => $points, 'daily_login' => $dailyLogins ? 1 : 0, 'itinerary' => $itinerary ? 1 : 0, 'tree_level' => $tree?->level ?? 1, 'wishlist_count' => $wishlists];
        $unlocked = DB::table('user_green_achievements')->where('user_id', $user->user_id)->pluck('achievement_id');
        foreach (GreenAchievement::whereNotIn('id', $unlocked)->get() as $achievement) {
            if (($values[$achievement->requirement_type] ?? 0) < $achievement->requirement_value) continue;
            DB::table('user_green_achievements')->insertOrIgnore(['user_id' => $user->user_id, 'achievement_id' => $achievement->id, 'unlocked_at' => now()]);
        }
    }
}
