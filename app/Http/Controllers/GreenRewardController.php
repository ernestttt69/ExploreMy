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

class GreenRewardController extends Controller
{
    public function __construct(private GreenRewardService $rewards) {}

    public function index()
    {
        $user = auth()->user();
        $wallet = $this->rewards->wallet($user);
        $tree = GreenTree::firstOrCreate(['user_id' => $user->user_id]);
        $achievements = GreenAchievement::orderBy('id')->get();
        $unlocked = DB::table('user_green_achievements')->where('user_id', $user->user_id)->pluck('achievement_id')->all();
        $claimable = DB::table('user_green_achievements')->where('user_id', $user->user_id)->whereNull('claimed_at')->pluck('achievement_id')->all();
        $items = GreenShopItem::where('is_available', true)->get();
        $inventory = GreenInventory::with('item')->where('user_id', $user->user_id)->where('quantity', '>', 0)->get();
        $transactions = GreenRewardTransaction::where('user_id', $user->user_id)->latest()->limit(10)->get();
        $nextThreshold = $tree->level * 100;
        $treeStage = $tree->level >= 10 ? 'ancient' : ($tree->level >= 5 ? 'mature' : ($tree->level >= 3 ? 'growing' : ($tree->level >= 2 ? 'small' : 'seed')));
        $treeLabel = ['seed' => 'Seed', 'small' => 'Small tree', 'growing' => 'Growing tree', 'mature' => 'Mature tree', 'ancient' => 'Tall trunk'][$treeStage];
        $treeHeight = number_format(0.1 + (($tree->level - 1) * 0.35), 2);
        $treeProgress = $nextThreshold > 0
            ? min(100, (($tree->experience % $nextThreshold) / $nextThreshold) * 100)
            : 0;
        return view('rewards.reward', compact('wallet', 'tree', 'achievements', 'unlocked', 'claimable', 'items', 'inventory', 'transactions', 'nextThreshold', 'treeProgress', 'treeStage', 'treeLabel', 'treeHeight'));
    }

    public function dailyLogin()
    {
        $claimed = $this->rewards->claimDailyLogin(auth()->user());
        return back()->with($claimed ? 'success' : 'info', $claimed ? 'Daily login reward claimed: 10 Green Points.' : 'Your daily login reward has already been claimed.');
    }

    public function purchase(GreenShopItem $item)
    {
        abort_unless($item->is_available, 404);
        $this->rewards->purchase(auth()->user(), $item);
        if (request()->expectsJson()) {
            return response()->json(['message' => $item->name . ' added to your inventory.']);
        }
        return back()->with('success', $item->name . ' added to your inventory.');
    }

    public function fertilize(Request $request, GreenInventory $inventory)
    {
        $this->rewards->fertilize(auth()->user(), $inventory);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Your tree grew by using ' . $inventory->item->name . '.']);
        }
        return back()->with('success', 'Your tree grew by using ' . $inventory->item->name . '.');
    }

    public function activity(Request $request)
    {
        $validated = $request->validate([
            'activity' => ['required', 'in:export_itinerary_pdf,export_guidance,share_itinerary'],
        ]);

        $awarded = $this->rewards->awardActivity(auth()->user(), $validated['activity']);
        return response()->json(['awarded' => $awarded]);
    }

    public function collectActivity(Request $request)
    {
        $validated = $request->validate([
            'activity' => ['required', 'in:generate_itinerary'],
        ]);
        $pendingRewards = session('pending_reward_activities', []);
        $pendingCount = $pendingRewards[$validated['activity']] ?? 0;

        abort_unless($pendingCount > 0, 422, 'This activity has no reward ready to collect.');

        $pendingRewards[$validated['activity']] = $pendingCount - 1;
        session()->put('pending_reward_activities', $pendingRewards);
        $awarded = $this->rewards->awardActivity(auth()->user(), $validated['activity']);

        return back()->with($awarded ? 'success' : 'info', $awarded
            ? 'Itinerary generation reward claimed: 50 Green Points.'
            : 'This reward could not be claimed.');
    }

    public function collectAchievement(GreenAchievement $achievement)
    {
        abort_unless($this->rewards->collectAchievement(auth()->user(), $achievement), 422, 'This achievement is not ready to collect.');
        if (request()->expectsJson()) {
            return response()->json(['message' => $achievement->reward_points . ' Green Points collected for ' . $achievement->name . '.']);
        }
        return back()->with('success', $achievement->reward_points . ' Green Points collected for ' . $achievement->name . '.');
    }
}
