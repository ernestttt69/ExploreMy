<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $attraction->attraction_name }} - Details</title>
    <!-- Google Font Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Dedicated Recommendations View Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/recommendations.css') }}">
</head>
<body>

    <!-- Unified Header Navigation -->
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

    <div class="container my-5">
        <a href="{{ route('recommendations.index') }}" class="btn btn-outline-secondary mb-4 rounded-3">&larr; Back to Recommendations</a>

        <div class="filter-card border-0 shadow-sm overflow-hidden p-0">
            <div class="row g-0">
                <div class="col-md-6">
                    <img src="{{ $attraction->images->first()?->image_path ?? asset('images/default-attraction.jpg') }}" 
                         class="img-fluid w-100 h-100" style="object-fit: cover; min-height: 380px;" alt="{{ $attraction->attraction_name }}">
                </div>
                <div class="col-md-6 p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="category-pill-btn active py-1 px-3 fs-6">{{ $attraction->category }}</span>
                            <span class="fw-bold text-warning fs-6">⭐ {{ number_format($attraction->rating, 1) }} / 5.0</span>
                        </div>
                        
                        <h2 class="fw-bold mb-1" style="color: #1c4434;">{{ $attraction->attraction_name }}</h2>
                        <p class="text-muted mb-3">📍 {{ $attraction->state?->state_name }}</p>
                        <hr class="text-muted">

                        <p class="mb-2"><strong>Description:</strong> {{ $attraction->description }}</p>
                        <p class="mb-2"><strong>Location:</strong> {{ $attraction->location }}</p>
                        <p class="mb-2"><strong>Operating Hours:</strong> {{ $attraction->operating_hours ?? 'N/A' }}</p>
                        <p class="mb-2"><strong>Entrance Fee:</strong> RM {{ number_format($attraction->entrance_fee, 2) }}</p>
                        <p class="mb-2"><strong>Nearby Transport:</strong> 🚌 {{ $attraction->nearby_transport ?? 'N/A' }}</p>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-dark-green w-100 py-2" disabled>Save Attraction (Coming Soon)</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>