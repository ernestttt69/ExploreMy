<nav class="main-navbar">

    <div class="navbar-container">

        <a href="{{ route('dashboard') }}" class="navbar-brand">
            ExploreMY
        </a>

        <ul class="nav-menu">

            <li>
                <a
                    href="{{ route('dashboard') }}"
                    class="nav-link-custom {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                >
                    {{ __('ui.nav.dashboard') }}
                </a>
            </li>

            <li>
                <a
                    href="{{ route('attractions.index') }}"
                    class="nav-link-custom {{ request()->routeIs('attractions.*') ? 'active' : '' }}"
                >
                    {{ __('ui.nav.explore') }}
                </a>
            </li>

            <li>
                <a
                    href="{{ route('saved-places.index') }}"
                    class="nav-link-custom {{ request()->routeIs('saved-places.*') ? 'active' : '' }}"
                >
                    Saved Places
                </a>
            </li>

            <li>
                <a
                    href="{{ route('travel-preferences.edit') }}"
                    class="nav-link-custom {{ request()->routeIs('travel-preferences.*') ? 'active' : '' }}"
                >
                    Travel Preferences
                </a>
            </li>

            <li>
                <a
                    href="{{ route('profile') }}"
                    class="nav-link-custom {{ request()->routeIs('profile') ? 'active' : '' }}"
                >
                    Profile
                </a>
            </li>

        </ul>

        <div class="navbar-user">

            <span class="navbar-user-name">
                {{ Auth::user()->name }}
            </span>

            <form
                method="POST"
                action="{{ route('logout') }}"
                class="logout-form"
            >
                @csrf

                <button
                    type="submit"
                    class="logout-button"
                >
                    Logout
                </button>
            </form>

        </div>

    </div>

</nav>