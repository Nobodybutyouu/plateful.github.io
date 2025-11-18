<?php
require_once __DIR__ . '/init.php';

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>

<section class="hero-section text-white" id="home">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <span class="badge text-bg-light text-uppercase" style="letter-spacing: 0.1em;">Food Service Marketplace</span>
                <h1 class="display-4 fw-bold mt-3">Connect with curated caterers for every occasion</h1>
                <p class="lead mt-3">Plateful streamlines bookings, menu management, and reviews so customers, caterers, and admins stay in sync from inquiry to event day.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="/plateful_web-app/register.php" class="btn btn-primary btn-lg px-4">Get Started</a>
                    <a href="/plateful_web-app/index.php#features" class="btn btn-outline-light btn-lg px-4">Explore Features</a>
                </div>
            </div>
            <div class="col-lg-5 offset-lg-1 mt-5 mt-lg-0">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold">Platform Snapshot</h5>
                        <p class="mb-4 text-muted">Key metrics across the ecosystem</p>
                        <div class="row text-center">
                            <div class="col-6 border-end">
                                <h3 class="fw-bold mb-0">120+</h3>
                                <small class="text-muted">Active Caterers</small>
                            </div>
                            <div class="col-6">
                                <h3 class="fw-bold mb-0">4.8★</h3>
                                <small class="text-muted">Avg. Rating</small>
                            </div>
                        </div>
                        <hr class="my-4">
                        <div class="d-flex align-items-start">
                            <div class="icon-circle flex-shrink-0 me-3">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-1">Seamless booking flow</h6>
                                <p class="text-muted mb-0 small">Track every step—from pending requests to completed events—in a unified dashboard.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="features">
    <div class="container">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6">
                <h2 class="section-title">Built for every role in the catering journey</h2>
                <p class="text-muted">Plateful keeps customers, caterers, and admins aligned with role-specific dashboards and clear workflows.</p>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="feature-card p-4 bg-white shadow-sm h-100">
                            <div class="icon-circle mb-3">
                                <i class="bi bi-people"></i>
                            </div>
                            <h6 class="fw-semibold">User Management</h6>
                            <p class="text-muted small mb-0">Role-based access with caterer approvals and flexible account settings.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="feature-card p-4 bg-white shadow-sm h-100">
                            <div class="icon-circle mb-3">
                                <i class="bi bi-search"></i>
                            </div>
                            <h6 class="fw-semibold">Caterer Discovery</h6>
                            <p class="text-muted small mb-0">Search, filter, and compare caterers with menus, reviews, and availability.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="feature-card p-4 bg-white shadow-sm h-100">
                            <div class="icon-circle mb-3">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <h6 class="fw-semibold">Booking Lifecycle</h6>
                            <p class="text-muted small mb-0">Request, approve, complete, and review events with automated notifications.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="feature-card p-4 bg-white shadow-sm h-100">
                            <div class="icon-circle mb-3">
                                <i class="bi bi-kanban"></i>
                            </div>
                            <h6 class="fw-semibold">Menu & Packages</h6>
                            <p class="text-muted small mb-0">Caterers manage offerings with add, edit, delete, and photo support.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="role-card">
                    <div class="icon-circle mb-3">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <h4 class="fw-bold">Customer Experience</h4>
                    <p class="text-muted">Plan events confidently with curated caterer profiles, transparent pricing, and real reviews.</p>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Personal dashboard</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Booking tracker</li>
                        <li><i class="bi bi-check-circle-fill text-primary me-2"></i> Post-event reviews</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="role-card">
                    <div class="icon-circle mb-3">
                        <i class="bi bi-shop-window"></i>
                    </div>
                    <h4 class="fw-bold">Caterer Tools</h4>
                    <p class="text-muted">Showcase menus, manage bookings, and stay on top of customer requests in one place.</p>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Menu builder</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Booking approvals</li>
                        <li><i class="bi bi-check-circle-fill text-primary me-2"></i> Business profile hub</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="role-card">
                    <div class="icon-circle mb-3">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4 class="fw-bold">Admin Control</h4>
                    <p class="text-muted">Monitor platform health, approve caterers, and manage categories effortlessly.</p>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Platform analytics</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Approval workflows</li>
                        <li><i class="bi bi-check-circle-fill text-primary me-2"></i> Category management</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-5" id="how-it-works">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-5">
                <h2 class="section-title">End-to-end booking flow</h2>
                <p class="text-muted">From discovery to review, follow a guided experience that ensures clarity for every party.</p>
            </div>
            <div class="col-lg-7">
                <div class="timeline-step" data-step="01">
                    <h6 class="fw-semibold">Customer discovers caterer</h6>
                    <p class="text-muted small mb-0">Browse by cuisine, budget, or event type to find the perfect match.</p>
                </div>
                <div class="timeline-step" data-step="02">
                    <h6 class="fw-semibold">Booking request submitted</h6>
                    <p class="text-muted small mb-0">Capture key event details and review availability instantly.</p>
                </div>
                <div class="timeline-step" data-step="03">
                    <h6 class="fw-semibold">Caterer decides</h6>
                    <p class="text-muted small mb-0">Approve or decline requests and send updates to the customer.</p>
                </div>
                <div class="timeline-step" data-step="04">
                    <h6 class="fw-semibold">Event completion & feedback</h6>
                    <p class="text-muted small mb-0">Track completion, collect reviews, and enhance your platform presence.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="roles">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge text-bg-secondary bg-opacity-10 text-secondary mb-2">Role-Based Dashboards</span>
            <h2 class="section-title">Tailored control for every role</h2>
            <p class="text-muted">Each user type gets a focused workspace for faster decisions and simplified management.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 h-100 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Customer Dashboard</h5>
                        <p class="text-muted small">Manage bookings, view upcoming events, and keep track of past experiences.</p>
                        <ul class="small text-muted list-unstyled mb-0">
                            <li class="mb-2"><i class="bi bi-dot"></i> Upcoming bookings</li>
                            <li class="mb-2"><i class="bi bi-dot"></i> Booking status tracking</li>
                            <li><i class="bi bi-dot"></i> Profile management</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 h-100 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Caterer Dashboard</h5>
                        <p class="text-muted small">Respond to new booking requests, organize menus, and monitor business performance.</p>
                        <ul class="small text-muted list-unstyled mb-0">
                            <li class="mb-2"><i class="bi bi-dot"></i> Pending requests</li>
                            <li class="mb-2"><i class="bi bi-dot"></i> Menu/package sections</li>
                            <li><i class="bi bi-dot"></i> Profile completion tracker</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 h-100 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Admin Dashboard</h5>
                        <p class="text-muted small">Oversee platform health, approve caterers, and manage categories efficiently.</p>
                        <ul class="small text-muted list-unstyled mb-0">
                            <li class="mb-2"><i class="bi bi-dot"></i> Pending caterer approvals</li>
                            <li class="mb-2"><i class="bi bi-dot"></i> Booking summaries</li>
                            <li><i class="bi bi-dot"></i> Manage cuisine/event types</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-5" id="cta">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h2 class="section-title">Built to scale with your catering brand</h2>
                <p class="text-muted">Centralize bookings, approvals, menus, and reviews in a streamlined system that grows with you.</p>
            </div>
            <div class="col-lg-5 text-lg-end">
                <a href="/plateful_web-app/register.php" class="btn btn-primary btn-lg">Create your account</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
