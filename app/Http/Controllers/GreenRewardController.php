<?php

namespace App\Http\Controllers;

use App\Models\GreenAchievement;
use App\Models\GreenInventory;
use App\Models\GreenShopItem;
use App\Models\GreenTree;
use App\Models\GreenRewardTransaction;
use App\Services\GreenRewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GreenRewardController extends Controller
{
    public function __construct(private GreenRewardService $rewards) {}

    public function index()
    {
        $user = auth()->user();
        $wallet = $this->rewards->wallet($user);
        $tree = GreenTree::firstOrCreate(
            ['user_id' => $user->user_id],
            ['level' => 0, 'experience' => 0, 'growth_stage' => 'Seed']
        );
        $achievements = GreenAchievement::orderBy('id')->get();
        $unlocked = DB::table('user_green_achievements')->where('user_id', $user->user_id)->pluck('achievement_id')->all();
        $claimable = DB::table('user_green_achievements')->where('user_id', $user->user_id)->whereNull('claimed_at')->pluck('achievement_id')->all();
        $items = GreenShopItem::where('is_available', true)->get();
        $inventory = GreenInventory::with('item')->where('user_id', $user->user_id)->where('quantity', '>', 0)->get();
        $transactions = GreenRewardTransaction::where('user_id', $user->user_id)->latest()->limit(10)->get();
        $experiencePerLevel = 100;
        $nextThreshold = ($tree->level + 1) * $experiencePerLevel;
        $currentLevelExperience = $tree->experience % $experiencePerLevel;
        $treeStage = $tree->level >= 10 ? 'ancient' : ($tree->level >= 5 ? 'mature' : ($tree->level >= 3 ? 'growing' : ($tree->level >= 2 ? 'small' : 'seed')));
        $treeLabel = __('rewards.tree_stages.' . $treeStage);
        $visualLevel = min(10, $tree->level);
        $treeHeight = number_format(max(0.1, 0.1 + (($visualLevel - 1) * 0.35)), 2);
        $treeProgress = ($currentLevelExperience / $experiencePerLevel) * 100;
        $progressSteps = (int) floor($currentLevelExperience / 20);
        return view('rewards.reward', compact('wallet', 'tree', 'achievements', 'unlocked', 'claimable', 'items', 'inventory', 'transactions', 'nextThreshold', 'treeProgress', 'progressSteps', 'treeStage', 'treeLabel', 'treeHeight'));
    }

    public function dailyLogin()
    {
        $queued = $this->rewards->queueActivity(auth()->user(), 'daily_login');
        return back()->with($queued ? 'success' : 'info', $queued ? __('messages.daily_claimed') : __('messages.daily_already_claimed'));
    }

    public function purchase(GreenShopItem $item)
    {
        abort_unless($item->is_available, 404);
        $inventory = $this->rewards->purchase(auth()->user(), $item);
        if (request()->expectsJson()) {
            $itemKey = Str::snake($item->name);
            return response()->json([
                'message' => __('messages.inventory_added', ['item' => $item->name]),
                'points' => $this->rewards->wallet(auth()->user())->points,
                'inventory' => [
                    'id' => $inventory->id,
                    'name' => __("rewards.shop_items.$itemKey.name"),
                    'description' => __("rewards.shop_items.$itemKey.description"),
                    'experience' => $item->exp_value,
                    'quantity' => $inventory->quantity,
                    'fertilizeUrl' => route('rewards.fertilize', $inventory),
                    'stockLabel' => __('rewards.stock', ['count' => $inventory->quantity]),
                    'applyLabel' => __('rewards.apply_fertilizer'),
                ],
            ]);
        }
        return back()->with('success', __('messages.inventory_added', ['item' => $item->name]));
    }

    public function fertilize(Request $request, GreenInventory $inventory)
    {
        $this->rewards->fertilize(auth()->user(), $inventory);
        if ($request->expectsJson()) {
            return response()->json(['message' => __('messages.tree_grew', ['item' => $inventory->item->name])]);
        }
        return back()->with('success', __('messages.tree_grew', ['item' => $inventory->item->name]));
    }

    public function activity(Request $request)
    {
        $validated = $request->validate([
            'activity' => ['required', 'in:export_itinerary,export_guidance,share_itinerary'],
        ]);

        $queued = $this->rewards->queueActivity(auth()->user(), $validated['activity']);
        return response()->json(['queued' => $queued]);
    }

    public function collectActivity(Request $request)
    {
        $validated = $request->validate([
            'activity' => ['required', 'in:daily_login,save_attraction,generate_itinerary,export_itinerary,export_guidance,share_itinerary'],
        ]);

        $awarded = $this->rewards->collectQueuedActivity(auth()->user(), $validated['activity']);

        abort_unless($awarded, 422, __('messages.activity_unavailable'));

        return back()->with('success', __('messages.itinerary_reward_claimed'));
    }

    public function collectAchievement(GreenAchievement $achievement)
    {
        abort_unless($this->rewards->collectAchievement(auth()->user(), $achievement), 422, __('messages.achievement_unavailable'));
        if (request()->expectsJson()) {
            return response()->json(['message' => __('messages.achievement_collected', ['points' => $achievement->reward_points, 'achievement' => $achievement->name])]);
        }
        return back()->with('success', __('messages.achievement_collected', ['points' => $achievement->reward_points, 'achievement' => $achievement->name]));
    }
}
