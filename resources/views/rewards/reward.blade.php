@extends('layouts.app')

@section('title', __('rewards.page_title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rewards.css') }}?v={{ filemtime(public_path('css/rewards.css')) }}">
@endpush

@section('content')
<main class="rewards-page">
    <div class="container rewards-container">
        @if(session('success') || session('info'))
            <div class="rewards-toast" role="status">{{ session('success') ?? session('info') }}</div>
        @endif
        <section class="tree-growth-container" aria-label="{{ __('rewards.growing_tree_aria') }}">
        <section class="rewards-hero" aria-labelledby="rewards-title">
            <div class="rewards-hero-copy">
                <span class="rewards-eyebrow">{{ __('rewards.green_points') }}</span>
                <strong class="hero-points">{{ number_format($wallet->points) }}</strong>
                <span class="hero-points-label">{{ __('rewards.points') }}</span>
            </div>
            <div class="hero-tree-scene" aria-label="{{ __('rewards.tree_aria', ['tree' => $treeLabel, 'height' => $treeHeight]) }}" role="img">
                <div class="hero-land"></div>
                <div class="hero-tree tree-stage-{{ $treeStage }} tree-level-{{ $tree->level }}">
                    <span class="tree-ground"></span><span class="tree-trunk"></span>
                    <span class="tree-crown tree-crown-one"></span><span class="tree-crown tree-crown-two"></span><span class="tree-crown tree-crown-three"></span>
                    <span class="tree-leaf tree-leaf-one"></span><span class="tree-leaf tree-leaf-two"></span><span class="tree-leaf tree-leaf-three"></span>
                </div>
                <span class="hero-tree-height">{{ $treeHeight }} {{ __('rewards.height_unit') }}</span>
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

        <section class="rewards-overview" aria-label="{{ __('rewards.summary_aria') }}">
            <div class="points-panel">
                <div class="panel-heading"><span class="panel-label">{{ __('rewards.balance') }}</span></div>
                    <strong class="wallet-points">{{ number_format($wallet->points) }}</strong>
                <span class="points-caption">{{ __('rewards.green_points') }}</span>
                <div class="progress-track" role="progressbar" aria-label="{{ __('rewards.progress_aria') }}" aria-valuenow="{{ $treeProgress }}" aria-valuemin="0" aria-valuemax="100"><span class="progress-fill" data-progress="{{ $treeProgress }}"></span></div>
                <p>{{ __('rewards.to_level', ['exp' => max(0, $nextThreshold - ($tree->experience % max(1, $nextThreshold))), 'level' => $tree->level + 1]) }}</p>
            </div>
            <div class="level-panel">
                <span class="panel-label">{{ __('rewards.current_level') }}</span>
                <div class="level-row"><span class="level-badge">{{ str_pad($tree->level, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ $treeLabel }}</h2><p>{{ __('rewards.nurtured', ['exp' => number_format($tree->experience)]) }}</p></div></div>
                <div class="level-steps">@for($step = 1; $step <= 5; $step++)<span class="{{ $progressSteps >= $step ? 'complete' : '' }}"></span>@endfor</div>
                <small>{{ __('rewards.level_unlock', ['level' => $tree->level + 1, 'exp' => number_format($nextThreshold)]) }}</small>
            </div>
        </section>

        <section class="rewards-section fertilizer-section" aria-labelledby="fertilizer-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('rewards.grow_tree') }}</span><h2 id="fertilizer-title">{{ __('rewards.choose_fertilizer') }}</h2></div><span class="section-note">{{ __('rewards.use_owned') }}</span></div>
            <div class="fertilizer-grid">
                @forelse($inventory as $owned)
                    @php($ownedItemKey = Illuminate\Support\Str::snake($owned->item->name))
                    <article class="fertilizer-card" data-inventory-id="{{ $owned->id }}">
                        <div><span class="fertilizer-exp">+{{ $owned->item->exp_value }} EXP</span><h3>{{ __("rewards.shop_items.$ownedItemKey.name") }}</h3><p>{{ __("rewards.shop_items.$ownedItemKey.description") }}</p></div>
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
                <article class="earn-card"><span class="earn-icon earn-icon-amber">↗</span><span class="earn-points">+30 pts</span><h3>{{ __('rewards.share_trip') }}</h3><p>{{ __('rewards.share_trip_desc') }}</p><a href="{{ route('itineraries.index') }}">{{ __('rewards.view_trips') }} <span aria-hidden="true">&rarr;</span></a></article>
            </div>
        </section>

        <section class="rewards-section" aria-labelledby="badges-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('rewards.collection') }}</span><h2 id="badges-title">{{ __('rewards.badges') }}</h2></div><span class="section-note">{{ __('rewards.activities') }}</span></div>
            @php($badgeActivities = [
                ['key' => 'daily_login', 'points' => '+10', 'icon' => '◷'],
                ['key' => 'save_attraction', 'points' => '+20', 'icon' => '♡'],
                ['key' => 'save_itinerary', 'points' => '+50', 'icon' => '⌖'],
                ['key' => 'export_itinerary', 'points' => '+30', 'icon' => '↓'],
                ['key' => 'export_guidance', 'points' => '+30', 'icon' => '≡'],
                ['key' => 'share_itinerary', 'points' => '+30', 'icon' => '↗'],
                ['key' => 'tree_milestone', 'points' => __('rewards.activity_points.tree_milestone'), 'icon' => '✦'],
            ])
            <div class="badges-row">
                @foreach($badgeActivities as $activity)
                    @php($pendingCount = (int) session('pending_reward_activities.' . $activity['key'], 0))
                    <div class="badge-item badge-activity">
                        <span class="badge-art">{{ $activity['icon'] }}</span>
                        <strong>{{ __('rewards.activity_names.' . $activity['key']) }}</strong>
                        <small>{{ $activity['points'] }} {{ __('rewards.green_points') }} · {{ __('rewards.activity_frequency.' . $activity['key']) }}</small>
                        @if($pendingCount > 0)
                            <span class="pending-reward-count">{{ trans_choice('rewards.rewards_ready', $pendingCount, ['count' => $pendingCount]) }}</span>
                            <form method="POST" action="{{ route('rewards.activity.collect') }}" data-async-reward data-reward-collection>
                                @csrf
                                <input type="hidden" name="activity" value="{{ $activity['key'] }}">
                                <button type="submit" class="collect-button">{{ __('rewards.collect_one', ['count' => $pendingCount]) }}</button>
                            </form>
                        @else
                            <span class="pending-reward-count pending-reward-count-empty">{{ __('rewards.none_ready') }}</span>
                            <button type="button" class="collect-button" disabled>{{ __('rewards.collect') }}</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rewards-section rewards-tools" aria-labelledby="tree-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('rewards.living_reward') }}</span><h2 id="tree-title">{{ $treeLabel }}</h2></div><span class="section-note">{{ __('rewards.level_exp', ['level' => $tree->level, 'exp' => $tree->experience]) }}</span></div>
                        <div class="tools-grid"><div class="tree-panel"><div class="tree-illustration tree-stage-{{ $treeStage }}" aria-label="{{ __('rewards.tree_aria', ['tree' => $treeLabel, 'height' => $treeHeight]) }}" role="img"><span class="tree-ground"></span><span class="tree-trunk"></span><span class="tree-crown tree-crown-one"></span><span class="tree-crown tree-crown-two"></span><span class="tree-crown tree-crown-three"></span><span class="tree-leaf tree-leaf-one"></span><span class="tree-leaf tree-leaf-two"></span><span class="tree-leaf tree-leaf-three"></span></div><div><h3>{{ __('rewards.grow_virtual') }}</h3><p>{{ __('rewards.grow_virtual_desc') }}</p><div class="tree-height"><strong>{{ $treeHeight }} {{ __('rewards.height_unit') }}</strong><span>{{ __('rewards.height') }}</span></div></div></div><div class="inventory-panel"><h3>{{ __('rewards.inventory') }}</h3>@forelse($inventory as $owned)@php($ownedItemKey = \Illuminate\Support\Str::snake($owned->item->name))<div class="inventory-row"><span>{{ __("rewards.shop_items.$ownedItemKey.name") }} <b>x{{ $owned->quantity }}</b></span><form method="POST" action="{{ route('rewards.fertilize', $owned) }}" data-async-reward>@csrf<button type="submit" class="text-action">{{ __('rewards.use_fertilizer') }} &rarr;</button></form></div>@empty<p class="empty-copy">{{ __('rewards.inventory_empty') }}</p>@endforelse</div></div>
        </section>
        </section>

        <section class="rewards-section rewards-shop" aria-labelledby="shop-title"><div class="section-heading"><div><span class="section-kicker">{{ __('rewards.spend') }}</span><h2 id="shop-title">{{ __('rewards.shop') }}</h2></div><span class="section-note" data-shop-balance>{{ __('rewards.available', ['points' => number_format($wallet->points)]) }}</span></div><div class="shop-grid">@foreach($items as $item)@php($itemKey = \Illuminate\Support\Str::snake($item->name))<article class="shop-item"><span class="shop-exp">+{{ $item->exp_value }} EXP</span><h3>{{ __("rewards.shop_items.$itemKey.name") }}</h3><p>{{ __("rewards.shop_items.$itemKey.description") }}</p><form method="POST" action="{{ route('rewards.purchase', $item) }}" data-purchase-form>@csrf<button type="submit" class="shop-button" data-price="{{ $item->price }}" {{ $wallet->points < $item->price ? 'disabled' : '' }}>{{ __('rewards.buy', ['points' => $item->price]) }}</button></form></article>@endforeach</div></section>

        <section class="rewards-section rewards-history" aria-labelledby="history-title"><div class="section-heading"><div><span class="section-kicker">{{ __('rewards.activity') }}</span><h2 id="history-title">{{ __('rewards.history') }}</h2></div></div><div class="history-list">@include('rewards.history')</div></section>
    </div>
</main>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.progress-fill').forEach(function (fill) {
            fill.style.width = fill.dataset.progress + '%';
        });

        function updateRewardSummary(data) {
            if (data.points !== undefined) {
                document.querySelectorAll('.wallet-points, .hero-points').forEach(function (points) {
                    points.textContent = Number(data.points).toLocaleString();
                });
                document.querySelectorAll('.shop-button[data-price]').forEach(function (button) {
                    button.disabled = Number(data.points) < Number(button.dataset.price);
                });
            }
            if (data.availableLabel !== undefined) {
                document.querySelector('[data-shop-balance]').textContent = data.availableLabel;
            }
            if (data.historyHtml !== undefined) {
                document.querySelector('.history-list').innerHTML = data.historyHtml;
            }
        }

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
                    if (!response.ok) throw new Error(data.message || {{ Illuminate\Support\Js::from(__('rewards.action_failed')) }});

                    showRewardToast(data.message, false);

                    updateRewardSummary(data);

                    if (button && form.action.includes('/achievements/')) {
                        button.textContent = {{ Illuminate\Support\Js::from(__('rewards.collected')) }};
                        button.disabled = true;
                    } else if (button) {
                        button.disabled = false;
                    }

                    if (form.dataset.rewardCollection !== undefined) {
                        var badge = form.closest('.badge-activity');
                        var pending = badge ? badge.querySelector('.pending-reward-count') : null;
                        if (pending) {
                            pending.textContent = data.remainingLabel;
                            pending.classList.toggle('pending-reward-count-empty', Number(data.remaining) === 0);
                        }
                        if (button) {
                            button.textContent = data.buttonLabel;
                            button.disabled = Number(data.remaining) === 0;
                        }
                        if (!data.hasPendingRewards) {
                            document.querySelectorAll('[data-reward-dot]').forEach(function (dot) {
                                dot.hidden = true;
                            });
                        }
                    } else if (!form.action.includes('/achievements/')) {
                        setTimeout(function () {
                            window.location.reload();
                        }, 800);
                    }
                } catch (error) {
                    if (button) {
                        button.disabled = false;
                        button.textContent = originalLabel;
                    }
                    showRewardToast(error.message, true);
                }
            });
        });

        document.querySelectorAll('[data-purchase-form]').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                var button = form.querySelector('button[type="submit"]');
                if (button) button.disabled = true;

                try {
                    var response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                        },
                        body: new FormData(form)
                    });
                    var data = await response.json();
                    if (!response.ok) throw new Error(data.message || {{ Illuminate\Support\Js::from(__('rewards.action_failed')) }});

                    var inventory = data.inventory;
                    var emptyState = document.querySelector('.fertilizer-empty');
                    if (emptyState) emptyState.remove();

                    var stock = document.querySelector('[data-inventory-id="' + inventory.id + '"] .fertilizer-stock');
                    if (!stock) {
                        var card = document.createElement('article');
                        card.className = 'fertilizer-card';
                        card.dataset.inventoryId = inventory.id;
                        card.innerHTML = '<div><span class="fertilizer-exp"></span><h3></h3><p></p></div><div class="fertilizer-card-footer"><span class="fertilizer-stock"></span><form method="POST"><input type="hidden" name="_token"><button type="submit" class="fertilizer-button"></button></form></div>';
                        card.querySelector('.fertilizer-exp').textContent = '+' + inventory.experience + ' EXP';
                        card.querySelector('h3').textContent = inventory.name;
                        card.querySelector('p').textContent = inventory.description;
                        var inventoryForm = card.querySelector('form');
                        inventoryForm.action = inventory.fertilizeUrl;
                        inventoryForm.querySelector('input[name="_token"]').value = form.querySelector('input[name="_token"]').value;
                        card.querySelector('.fertilizer-button').textContent = inventory.applyLabel;
                        document.querySelector('.fertilizer-grid').appendChild(card);
                        stock = card.querySelector('.fertilizer-stock');
                    }
                    stock.textContent = inventory.stockLabel;

                    var heroButton = document.querySelector('.hero-fertilizer');
                    if (heroButton && heroButton.tagName === 'A') {
                        var heroForm = document.createElement('form');
                        heroForm.method = 'POST';
                        heroForm.action = inventory.fertilizeUrl;
                        heroForm.className = 'hero-fertilizer-form';
                        heroForm.innerHTML = '<input type="hidden" name="_token"><button type="submit" class="hero-fertilizer"></button>';
                        heroForm.querySelector('input[name="_token"]').value = form.querySelector('input[name="_token"]').value;
                        heroForm.querySelector('button').textContent = inventory.applyLabel;
                        heroButton.replaceWith(heroForm);
                    }

                    updateRewardSummary(data);
                    showRewardToast(data.message, false);
                } catch (error) {
                    if (button) button.disabled = false;
                    showRewardToast(error.message, true);
                }
            });
        });
    </script>
@endpush
