<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExploreMY - Search Attractions</title>
    <!-- Google Font Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Dedicated Recommendations Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/recommendations.css') }}">
</head>
<body>

    <!-- Navigation Header -->
    <header class="header-wrapper mb-4">
        <div class="container top-header-bar d-flex justify-content-between align-items-center">
            <div class="nav-brand-container">
                <div class="nav-logo-badge">
                    <img src="{{ asset('images/ExploreMy_icon.jpeg') }}" alt="ExploreMy Logo">
                </div>
                <a href="{{ route('recommendations.index') }}" class="nav-brand-title">ExploreMY</a>
            </div>

            <div>
                @auth
                    <div class="d-flex align-items-center gap-3">
                        <div class="nav-user-pill">
                            <span class="avatar-initials">{{ strtoupper(substr(auth()->user()?->name ?? 'ST', 0, 2)) }}</span>
                            <span>{{ auth()->user()?->name }}</span>
                        </div>
                        <form action="{{ url('/logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn-logout">Logout</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-dark-green px-4 py-2">Login</a>
                @endauth
            </div>
        </div>

        <nav class="sub-navbar">
            <div class="container d-flex justify-content-center">
                <ul class="nav-menu">
                    @auth
                        <li><a href="{{ url('/dashboard') }}" class="nav-link-custom">Dashboard</a></li>
                    @endauth
                    <li><a href="{{ route('recommendations.index') }}" class="nav-link-custom active">Explore</a></li>
                    @auth
                        <li><a href="{{ url('/trips') }}" class="nav-link-custom">My Trips</a></li>
                    @endauth
                </ul>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        
        <h2 class="fw-bold mb-1" style="color: #1b4332;">Search Attractions</h2>
        <p class="text-muted mb-4">Find Attractions in Malaysia</p>

        <!-- Validation Error Alert -->
        @if ($errors->any())
            <div class="alert alert-danger rounded-4 mb-4">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('recommendations.index') }}" method="GET">
            <input type="hidden" name="search" value="1">

            <!-- Search Inputs -->
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <select name="state_id" id="state_id" class="form-select custom-search-input @error('state_id') is-invalid @enderror" required>
                        <option value="">-- Select Destination State or City --</option>
                        @foreach($states as $state)
                            <option value="{{ $state->state_id }}" {{ request('state_id') == $state->state_id ? 'selected' : '' }}>
                                {{ $state->state_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <input type="number" name="trip_duration" id="trip_duration" 
                           class="form-control custom-search-input @error('trip_duration') is-invalid @enderror" 
                           placeholder="Trip Duration (e.g., 3 Days, 1 Week)" 
                           value="{{ request('trip_duration') }}" min="1" max="30" required>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark-green w-100 h-100 d-flex align-items-center justify-content-center gap-2">
                        <span>Search Attractions</span> &rarr;
                    </button>
                </div>
            </div>

            <!-- Profile Info Banner -->
            <div class="info-banner mb-4 d-flex align-items-center gap-2">
                <span>🍃</span>
                <span>Personalized recommendations based on your preferences from your profile!</span>
                <span class="ms-auto">🧑‍💼</span>
            </div>

            <!-- Category Filter Pills -->
            <div class="category-pill-group mb-4">
                <button type="submit" name="category" value="" class="category-pill-btn {{ !request('category') ? 'active' : '' }}">
                    🚌 All
                </button>
                <button type="submit" name="category" value="Nature" class="category-pill-btn {{ request('category') == 'Nature' ? 'active' : '' }}">
                    🌱 Nature
                </button>
                <button type="submit" name="category" value="Culture" class="category-pill-btn {{ request('category') == 'Culture' ? 'active' : '' }}">
                    🏛️ Culture
                </button>
                <button type="submit" name="category" value="Food" class="category-pill-btn {{ request('category') == 'Food' ? 'active' : '' }}">
                    🍜 Food
                </button>
                <button type="submit" name="category" value="Adventure" class="category-pill-btn {{ request('category') == 'Adventure' ? 'active' : '' }}">
                    🧗 Adventure
                </button>
                <button type="submit" name="category" value="Relaxation" class="category-pill-btn {{ request('category') == 'Relaxation' ? 'active' : '' }}">
                    🏖️ Relaxation
                </button>
                <button type="submit" name="category" value="Budget" class="category-pill-btn {{ request('category') == 'Budget' ? 'active' : '' }}">
                    💰 Budget
                </button>
                <button type="submit" name="category" value="Rating" class="category-pill-btn {{ request('category') == 'Rating' ? 'active' : '' }}">
                    ⭐ Rating
                </button>
            </div>

            <!-- Detailed Filters -->
            <h5 class="fw-bold mb-3" style="color: #1b4332;">Detailed Filters:</h5>
            <div class="row g-3 mb-5">
                
                <!-- Budget Filter Card -->
                <div class="col-md-6">
                    <div class="filter-card h-100">
                        <div class="filter-card-title">Budget:</div>
                        <div class="filter-sub-label">Price Range (Optional):</div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="budget" value="" id="b_all" 
                                   {{ !request('budget') ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="b_all">All Prices</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="budget" value="free" id="b_free" 
                                   {{ request('budget') == 'free' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="b_free">Free Attractions</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="budget" value="under_50" id="b_under50" 
                                   {{ request('budget') == 'under_50' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="b_under50">Under RM 50</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="budget" value="50_150" id="b_50_150" 
                                   {{ request('budget') == '50_150' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="b_50_150">RM 50 - RM 150</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="budget" value="above_150" id="b_above150" 
                                   {{ request('budget') == 'above_150' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="b_above150">Above RM 150</label>
                        </div>
                    </div>
                </div>

                <!-- Rating Filter Card -->
                <div class="col-md-6">
                    <div class="filter-card h-100">
                        <div class="filter-card-title">Rating:</div>
                        <div class="filter-sub-label">Minimum Visitor Score (Optional):</div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="rating" value="" id="r_all" 
                                   {{ !request('rating') ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="r_all">All Ratings</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="rating" value="4.5" id="r_45" 
                                   {{ request('rating') == '4.5' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="r_45">⭐ 4.5 & Above (Highly Rated)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="rating" value="4.0" id="r_40" 
                                   {{ request('rating') == '4.0' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="r_40">⭐ 4.0 & Above</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="rating" value="3.5" id="r_35" 
                                   {{ request('rating') == '3.5' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="filter-checkbox-label" for="r_35">⭐ 3.5 & Above</label>
                        </div>
                    </div>
                </div>

            </div>
        </form>

        <!-- Attraction Cards Grid -->
        @if(request()->has('search'))
            <div class="row row-cols-1 row-cols-md-4 g-4 mb-5">
                @if(isset($attractions) && $attractions->count() > 0)
                    @foreach($attractions as $attraction)
                        @php
                            $rawPath = $attraction->images->first()?->image_path;

                            if (!empty($rawPath)) {
                                if (str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
                                    $imageUrl = $rawPath;
                                } else {
                                    $fileName = basename($rawPath);
                                    
                                    // Verify physical file existence before setting the URL
                                    if (file_exists(public_path('images/attractions/' . $fileName))) {
                                        $imageUrl = asset('images/attractions/' . $fileName);
                                    } else {
                                        $imageUrl = asset('images/default-attraction.jpg');
                                    }
                                }
                            } else {
                                $imageUrl = asset('images/default-attraction.jpg');
                            }
                        @endphp

                        <div class="col">
                            <div class="attraction-card h-100">
                                <div class="card-top-tag">
                                    📍 {{ $attraction->attraction_name }}
                                </div>
                                <div class="card-save-badge">
                                    Save to List 🤍
                                </div>

                                <!-- Attraction Image -->
                                <img src="{{ $imageUrl }}" class="attraction-img" alt="{{ $attraction->attraction_name }}">

                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold mb-0 text-truncate" style="max-width: 140px; color: #1b4332;">
                                            {{ $attraction->attraction_name }}
                                        </h6>
                                        <span class="fw-bold text-warning fs-6">⭐ {{ number_format($attraction->rating ?? 4.5, 1) }}</span>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between meta-icon-text mb-2">
                                        <span>📍 {{ $attraction->state?->state_name ?? 'Malaysia' }}</span>
                                        <span>🕒 Hours</span>
                                        <span>💰 RM {{ number_format($attraction->entrance_fee ?? 20, 0) }}</span>
                                    </div>

                                    <div class="meta-icon-text mb-2">
                                        🚌 {{ $attraction->nearby_transport ?? 'Nearby MRT/LRT available' }}
                                    </div>

                                    <p class="text-muted small mb-3 text-truncate">
                                        {{ $attraction->description ?? 'Explore popular destinations in Malaysia.' }}
                                    </p>

                                    <a href="{{ route('attractions.show', $attraction->attraction_id) }}" class="btn-card-action">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="col-12">
                        <div class="alert alert-light text-center border rounded-4 py-4">
                            No recommendations match your selected criteria or filters. Please adjust your destination or filter options.
                        </div>
                    </div>
                @endif
            </div>
        @endif

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>