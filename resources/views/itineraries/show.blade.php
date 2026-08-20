@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #2d3748;
    margin-top: 100px; /* offset for fixed-top header */
    background: #eef2f0;
}

/* Header Navbar Styling (duplicated — see trips.css / dashboard.css) */
.header-wrapper {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.top-header-bar {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.nav-brand-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-logo-badge {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    overflow: hidden;
    background: #aed9cb;
}

.nav-logo-badge img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.nav-brand-title {
    font-size: 24px;
    font-weight: 800;
    color: #1b4332;
}

.sub-navbar {
    padding: 10px 0;
}

.nav-menu {
    display: flex;
    list-style: none;
    margin: 0 auto;
    padding: 0;
    gap: 40px;
    justify-content: center;
}

.nav-link-custom {
    text-decoration: none;
    color: #52796f;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.2s ease;
}

.nav-link-custom:hover,
.nav-link-custom.active {
    color: #1b4332;
}

.nav-user-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    padding: 3px 12px 3px 4px;
    border-radius: 30px;
    transform: translateY(16px);
    color: #2d3748;
    text-decoration: none;
    cursor: pointer;
}

.nav-user-pill:hover {
    color: #1b4332;
}

.btn-logout {
    transform: translateY(16px);
    background-color: #fee2e2;
    color: #dc2626;
    font-weight: 600;
    border-radius: 20px;
    border: none;
    padding: 6px 16px;
}

.btn-logout:hover {
    background-color: #fca5a5;
    color: #991b1b;
}

.nav-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.custom-footer {
    background: transparent;
    color: #64748b;
}

.footer-text {
    font-size: 13px;
}

/* Itinerary Page — page-specific */
.itinerary-page {
    padding-top: 140px;
}

.public-banner {
    background: #dcf3e2;
    color: #1b4332;
    font-weight: 600;
    font-size: 14px;
    padding: 10px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.itinerary-title-row {
    display: flex;
    align-items: center;
    gap: 14px;
}

.itinerary-title-row .trip-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e3f3e8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.itinerary-title-row h1 {
    font-size: 26px;
    font-weight: 700;
    color: #1b4332;
}

/* Timeline (left column) */
.day-block {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
}

.day-date-col {
    flex: 0 0 70px;
    text-align: right;
    padding-top: 20px;
}

.day-date-main {
    font-size: 20px;
    font-weight: 700;
    color: #1b4332;
    line-height: 1.1;
}

.day-date-sub {
    font-size: 12px;
    color: #94a3b8;
}

.day-content-col {
    flex: 1;
}

.day-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.day-card h3 {
    font-size: 17px;
    font-weight: 700;
    color: #1b4332;
    margin-bottom: 14px;
}

.event-row {
    display: flex;
    gap: 12px;
    padding: 10px 0;
    border-top: 1px solid #f1f5f9;
}

.event-row:first-of-type {
    border-top: none;
}

.event-time {
    flex: 0 0 80px;
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
}

.event-text {
    font-size: 14px;
    color: #1e293b;
    margin-bottom: 6px;
}

.badge-highlight,
.badge-eco,
.badge-renewable {
    display: inline-block;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
}

.badge-highlight { background: #dcf3e2; color: #1b4332; }
.badge-eco       { background: #e0f2fe; color: #075985; }
.badge-renewable { background: #fef9c3; color: #854d0e; }

/* Side cards (right column) */
.side-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.side-card h4 {
    font-size: 15px;
    font-weight: 700;
    color: #1b4332;
    margin-bottom: 12px;
}

.map-card {
    padding: 0;
    overflow: hidden;
}

.map-placeholder {
    height: 220px;
    background: linear-gradient(135deg, #dbeafe 0%, #dcfce7 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    font-size: 14px;
    font-weight: 600;
}

/* Donut chart */
.donut-chart {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.donut-center {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    color: #1b4332;
}

.donut-legend {
    list-style: none;
    font-size: 13px;
    color: #475569;
}

.donut-legend li {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

/* Achievements */
.achievement-list {
    list-style: none;
    font-size: 13px;
    color: #334155;
}

.achievement-list li {
    margin-bottom: 8px;
}

/* Quick actions */
.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.btn-quick-action {
    background: #f1f5f9;
    color: #1b4332;
    font-weight: 600;
    font-size: 13px;
    border-radius: 10px;
    padding: 8px 14px;
    border: none;
    text-decoration: none;
}

.btn-quick-action:hover {
    background: #dcf3e2;
    color: #1b4332;
}

.share-result {
    margin-top: 14px;
}

.share-result input {
    width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 13px;
    margin-bottom: 8px;
}

.btn-copy {
    background: #1b4332;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
}

.btn-copy:hover {
    background: #2d6a4f;
}

/* Responsive */
@media (max-width: 992px) {
    .day-block {
        flex-direction: column;
    }

    .day-date-col {
        text-align: left;
        padding-top: 0;
    }
}

@media (max-width: 768px) {
    .itinerary-page {
        padding-top: 180px;
    }
}