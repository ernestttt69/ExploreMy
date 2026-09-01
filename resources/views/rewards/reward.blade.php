@extends('layouts.app')

@section('title', 'Green Rewards | ExploreMY')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rewards.css') }}?v={{ filemtime(public_path('css/rewards.css')) }}">
@endpush

@section('content')
<main class="rewards-page">
    <div class="container rewards-container">
        @if(session('success') || session('info'))
            <div class="rewards-toast" role="status">{{ session('success') ?? session('info') }}</div>
        @endif
        <section class="tree-growth-container" aria-label="Your growing tree">
        <section class="rewards-hero" aria-labelledby="rewards-title">
            <div class="rewards-hero-copy">
                <span class="rewards-eyebrow">{{ __('rewards.green_points') }}</span>
                <strong class="hero-points">{{ number_format($wallet->points) }}</strong>
                <span class="hero-points-label">{{ __('rewards.points') }}</span>
            </div>
            <div class="hero-tree-scene" aria-label="Your {{ strtolower($treeLabel) }} virtual tree, {{ $treeHeight }} metres tall" role="img">
                <div class="hero-land"></div>
                <div class="hero-tree tree-stage-{{ $treeStage }} tree-level-{{ $tree->level }}">
                    <span class="tree-ground"></span><span class="tree-trunk"></span>
                    <span class="tree-crown tree-crown-one"></span><span class="tree-crown tree-crown-two"></span><span class="tree-crown tree-crown-three"></span>
                    <span class="tree-leaf tree-leaf-one"></span><span class="tree-leaf tree-leaf-two"></span><span class="tree-leaf tree-leaf-three"></span>
                </div>
                <span class="hero-tree-height">{{ $treeHeight }} m</span>
                <div class="hero-level"><span>{{ __('rewards.level', ['level' => $tree->level]) }}</span><strong>{{ $treeLabel }}</strong></div>
            </div>
            @if($inventory->isNotEmpty())
                @php($heroFertilizer = $inventory->first())
                <form method="POST" action="{{ route('rewards.fertilize', $heroFertilizer) }}" class="hero-fertilizer-form" data-async-reward>
                    @csrf
                    <button type="submit" class="hero-fertilizer">{{ __('rewards.apply_fertilizer') }}</button>
                </form>
            @else
                <a href="{{ route('rewards') }}#shop-title" class="hero-fertilizer">{{ __('rewards.get_fertilizer') }}</a>
            @endif
        </section>

        <section class="rewards-overview" aria-label="Your rewards summary">
            <div class="points-panel">
                <div class="panel-heading"><span class="panel-label">{{ __('rewards.balance') }}</span></div>
                    <strong class="wallet-points">{{ number_format($wallet->points) }}</strong>
                <span class="points-caption">{{ __('rewards.green_points') }}</span>
                <div class="progress-track" role="progressbar" aria-label="Progress to next tree level" aria-valuenow="{{ $treeProgress }}" aria-valuemin="0" aria-valuemax="100"><span class="progress-fill" data-progress="{{ $treeProgress }}"></span></div>
                <p>{{ __('rewards.to_level', ['exp' => max(0, $nextThreshold - ($tree->experience % max(1, $nextThreshold))), 'level' => $tree->level + 1]) }}</p>
            </div>
            <div class="level-panel">
                <span class="panel-label">{{ __('rewards.current_level') }}</span>
                <div class="level-row"><span class="level-badge">{{ str_pad($tree->level, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ $treeLabel }}</h2><p>{{ __('rewards.nurtured', ['exp' => number_format($tree->experience)]) }}</p></div></div>
                <div class="level-steps"><span class="complete"></span><span class="complete"></span><span class="complete"></span><span></span><span></span></div>
                <small>Level {{ $tree->level + 1 }} unlocks at {{ number_format($nextThreshold) }} EXP</small>
            </div>
        </section>

        <section class="rewards-section fertilizer-section" aria-labelledby="fertilizer-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('rewards.grow_tree') }}</span><h2 id="fertilizer-title">{{ __('rewards.choose_fertilizer') }}</h2></div><span class="section-note">{{ __('rewards.use_owned') }}</span></div>
            <div class="fertilizer-grid">
                @forelse($inventory as $owned)
                    <article class="fertilizer-card">
                        <div><span class="fertilizer-exp">+{{ $owned->item->exp_value }} EXP</span><h3>{{ $owned->item->name }}</h3><p>{{ $owned->item->description }}</p></div>
                        <div class="fertilizer-card-footer"><span class="fertilizer-stock">{{ __('rewards.stock', ['count' => $owned->quantity]) }}</span><form method="POST" action="{{ route('rewards.fertilize', $owned) }}" data-async-reward>@csrf<button type="submit" class="fertilizer-button">{{ __('rewards.use_fertilizer') }}</button></form></div>
                    </article>
                @empty
                    <div class="fertilizer-empty"><p class="empty-copy">{{ __('rewards.no_fertilizer') }}</p><a href="#shop-title" class="text-action">{{ __('rewards.visit_shop') }}</a></div>
                @endforelse
            </div>
        </section>

        <section class="rewards-section" id="ways-to-earn" aria-labelledby="ways-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('rewards.impact') }}</span><h2 id="ways-title">{{ __('rewards.ways') }}</h2></div><span class="section-note">{{ __('rewards.updated') }}</span></div>
            <div class="earn-grid">
                <article class="earn-card earn-card-featured"><span class="earn-icon">♧</span><span class="earn-points">+50 pts</span><h3>{{ __('rewards.itinerary') }}</h3><p>{{ __('rewards.itinerary_desc') }}</p><a href="{{ route('route.index') }}">{{ __('rewards.plan_route') }} <span aria-hidden="true">&rarr;</span></a></article>
                <article class="earn-card"><span class="earn-icon earn-icon-blue">♡</span><span class="earn-points">+20 pts</span><h3>{{ __('rewards.save_destination') }}</h3><p>{{ __('rewards.save_desc') }}</p><a href="{{ route('attractions.index') }}">{{ __('rewards.explore') }} <span aria-hidden="true">&rarr;</span></a></article>
                <article class="earn-card"><span class="earn-icon earn-icon-amber">◎</span><span class="earn-points">+75 pts</span><h3>{{ __('rewards.complete_profile') }}</h3><p>{{ __('rewards.profile_desc') }}</p><a href="{{ route('profile') }}">{{ __('rewards.view_profile') }} <span aria-hidden="true">&rarr;</span></a></article>
            </div>
        </section>

        <section class="rewards-section" aria-labelledby="badges-title">
            <div class="section-heading"><div><span class="section-kicker">Your collection</span><h2 id="badges-title">Badges earned</h2></div><span class="section-note">Reward activities</span></div>
            @php($badgeActivities = [
                ['name' => 'Daily Login', 'points' => '+10', 'frequency' => 'Once per day', 'icon' => '◷'],
                ['name' => 'Save Attraction', 'points' => '+20', 'frequency' => 'Every successful save', 'icon' => '♡'],
                ['name' => 'Generate Itinerary', 'points' => '+50', 'frequency' => 'Every successful generation', 'icon' => '⌖'],
                ['name' => 'Export Itinerary to PDF', 'points' => '+30', 'frequency' => 'Every successful export', 'icon' => '↓'],
                ['name' => 'Export Step-by-Step Guidance', 'points' => '+30', 'frequency' => 'Every successful export', 'icon' => '≡'],
                ['name' => 'Share Itinerary', 'points' => '+30', 'frequency' => 'Every successful share', 'icon' => '↗'],
                ['name' => 'Achievement / Tree Milestone', 'points' => '+50 to +150', 'frequency' => 'Once per milestone', 'icon' => '✦'],
            ])
            <div class="badges-row">@foreach($badgeActivities as $activity)<div class="badge-item badge-activity"><span class="badge-art">{{ $activity['icon'] }}</span><strong>{{ $activity['name'] }}</strong><small>{{ $activity['points'] }} Green Points · {{ $activity['frequency'] }}</small>@if($activity['name'] === 'Generate Itinerary' && session('pending_reward_activities.generate_itinerary', 0) > 0)<form method="POST" action="{{ route('rewards.activity.collect') }}">@csrf<input type="hidden" name="activity" value="generate_itinerary"><button type="submit" class="collect-button">Collect</button></form>@else<button type="button" class="collect-button" disabled>Collect</button>@endif</div>@endforeach</div>
        </section>

        <section class="rewards-section rewards-tools" aria-labelledby="tree-title">
            <div class="section-heading"><div><span class="section-kicker">Your living reward</span><h2 id="tree-title">{{ $treeLabel }}</h2></div><span class="section-note">Level {{ $tree->level }} · {{ $tree->experience }} EXP</span></div>
                        <div class="tools-grid"><div class="tree-panel"><div class="tree-illustration tree-stage-{{ $treeStage }}" aria-label="Your {{ strtolower($treeLabel) }} virtual tree, {{ $treeHeight }} metres tall" role="img"><span class="tree-ground"></span><span class="tree-trunk"></span><span class="tree-crown tree-crown-one"></span><span class="tree-crown tree-crown-two"></span><span class="tree-crown tree-crown-three"></span><span class="tree-leaf tree-leaf-one"></span><span class="tree-leaf tree-leaf-two"></span><span class="tree-leaf tree-leaf-three"></span></div><div><h3>Grow your virtual tree</h3><p>Use fertilizer from your inventory to increase EXP and unlock new stages.</p><div class="tree-height"><strong>{{ $treeHeight }} m</strong><span>Tree height</span></div></div></div><div class="inventory-panel"><h3>Inventory</h3>@forelse($inventory as $owned)<div class="inventory-row"><span>{{ $owned->item->name }} <b>x{{ $owned->quantity }}</b></span><form method="POST" action="{{ route('rewards.fertilize', $owned) }}" data-async-reward>@csrf<button type="submit" class="text-action">Use fertilizer &rarr;</button></form></div>@empty<p class="empty-copy">Your inventory is empty. Pick up fertilizer in the shop.</p>@endforelse</div></div>
        </section>
        </section>

        <section class="rewards-section rewards-shop" aria-labelledby="shop-title"><div class="section-heading"><div><span class="section-kicker">Spend your points</span><h2 id="shop-title">Green Shop</h2></div><span class="section-note"><span class="wallet-points">{{ number_format($wallet->points) }}</span> points available</span></div><div class="shop-grid">@foreach($items as $item)<article class="shop-item"><span class="shop-exp">+{{ $item->exp_value }} EXP</span><h3>{{ $item->name }}</h3><p>{{ $item->description }}</p><form method="POST" action="{{ route('rewards.purchase', $item) }}" data-async-reward>@csrf<button type="submit" class="shop-button" data-price="{{ $item->price }}" {{ $wallet->points < $item->price ? 'disabled' : '' }}>{{ $item->price }} pts · Buy</button></form></article>@endforeach</div></section>

        <section class="rewards-section rewards-history" aria-labelledby="history-title"><div class="section-heading"><div><span class="section-kicker">Your activity</span><h2 id="history-title">Points history</h2></div></div><div class="history-list">@forelse($transactions as $transaction)<div class="history-row"><span class="history-type {{ $transaction->transaction_type }}">{{ $transaction->transaction_type === 'earning' ? '+' : '-' }}{{ $transaction->amount }}</span><span>{{ str_replace('_', ' ', $transaction->activity) }}</span><small>{{ $transaction->created_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small></div>@empty<p class="empty-copy">Your Green Points activity will appear here.</p>@endforelse</div></section>
    </div>
</main>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.progress-fill').forEach(function (fill) {
            fill.style.width = fill.dataset.progress + '%';
        });

        function showRewardToast(message, isError) {
            var toast = document.querySelector('.rewards-toast') || document.createElement('div');
            toast.className = 'rewards-toast' + (isError ? ' rewards-toast-error' : '');
            toast.setAttribute('role', isError ? 'alert' : 'status');
            toast.textContent = message;
            if (!toast.parentNode) document.querySelector('.rewards-page').prepend(toast);
            toast.style.animation = 'none';
            void toast.offsetWidth;
            toast.style.animation = '';
        }

        document.querySelectorAll('[data-async-reward]').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                var button = form.querySelector('button[type="submit"]');
                var originalLabel = button ? button.textContent : '';
                if (button) button.disabled = true;

                try {
                    var response = await fetch(form.action, {
                        method: form.method,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                        },
                        body: new FormData(form)
                    });
                    var data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'This action could not be completed.');

                    showRewardToast(data.message, false);

                    if (data.points !== undefined) {
                        document.querySelectorAll('.wallet-points').forEach(function (points) {
                            points.textContent = Number(data.points).toLocaleString();
                        });
                        document.querySelectorAll('.shop-button[data-price]').forEach(function (shopButton) {
                            shopButton.disabled = Number(data.points) < Number(shopButton.dataset.price);
                        });
                    }

                    if (button && form.action.includes('/achievements/')) {
                        button.textContent = 'Collected';
                    } else if (button) {
                        button.disabled = false;
                    }

                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                } catch (error) {
                    if (button) {
                        button.disabled = false;
                        button.textContent = originalLabel;
                    }
                    showRewardToast(error.message, true);
                }
            });
        });
    </script>
@endpush
