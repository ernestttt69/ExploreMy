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
        'generate_itinerary' => 50,
        'export_itinerary_pdf' => 30,
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

    public function claimDailyLogin(User $user): bool
    {
        $alreadyClaimed = GreenRewardTransaction::where('user_id', $user->user_id)
            ->where('activity', 'daily_login')->whereDate('created_at', Carbon::today())->exists();
        return !$alreadyClaimed && $this->award($user, 'daily_login', self::ACTIVITY_POINTS['daily_login']);
    }

    public function awardActivity(User $user, string $activity): bool
    {
        return isset(self::ACTIVITY_POINTS[$activity])
            && $this->award($user, $activity, self::ACTIVITY_POINTS[$activity]);
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

    public function purchase(User $user, GreenShopItem $item): void
    {
        DB::transaction(function () use ($user, $item) {
            $wallet = GreenWallet::where('user_id', $user->user_id)->lockForUpdate()->firstOrCreate(['user_id' => $user->user_id]);
            if ($wallet->points < $item->price) abort(422, 'You do not have enough Green Points.');
            $wallet->decrement('points', $item->price);
            GreenRewardTransaction::create(['user_id' => $user->user_id, 'activity' => 'fertilizer_purchase', 'amount' => $item->price, 'transaction_type' => 'spending', 'metadata' => ['item_id' => $item->id]]);
            $inventory = GreenInventory::firstOrCreate(['user_id' => $user->user_id, 'shop_item_id' => $item->id]);
            $inventory->increment('quantity');
        });
    }

    public function fertilize(User $user, GreenInventory $inventory): void
    {
        DB::transaction(function () use ($user, $inventory) {
            $inventory = GreenInventory::whereKey($inventory->id)->where('user_id', $user->user_id)->lockForUpdate()->firstOrFail();
            if ($inventory->quantity < 1) abort(422, 'This fertilizer is not in your inventory.');
            $item = $inventory->item;
            $tree = GreenTree::firstOrCreate(['user_id' => $user->user_id]);
            $inventory->decrement('quantity');
            $tree->experience += $item->exp_value;
            while ($tree->experience >= $tree->level * 100) $tree->level++;
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
        $itinerary = GreenRewardTransaction::where('user_id', $user->user_id)->where('activity', 'generate_itinerary')->exists();
        $wishlists = Wishlist::where('user_id', $user->user_id)->count();
        $values = ['points' => $points, 'daily_login' => $dailyLogins ? 1 : 0, 'itinerary' => $itinerary ? 1 : 0, 'tree_level' => $tree?->level ?? 1, 'wishlist_count' => $wishlists];
        $unlocked = DB::table('user_green_achievements')->where('user_id', $user->user_id)->pluck('achievement_id');
        foreach (GreenAchievement::whereNotIn('id', $unlocked)->get() as $achievement) {
            if (($values[$achievement->requirement_type] ?? 0) < $achievement->requirement_value) continue;
            DB::table('user_green_achievements')->insertOrIgnore(['user_id' => $user->user_id, 'achievement_id' => $achievement->id, 'unlocked_at' => now()]);
        }
    }
}
